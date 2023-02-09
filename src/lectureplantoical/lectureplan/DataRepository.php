<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\lectureplan;

use Amp\Promise;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use robske_110\dhbwma\lectureplantoical\lectureplan\representation\Lecture;
use robske_110\Logger\Logger;
use RuntimeException;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Reader;
use function Amp\call;

class DataRepository{
	/** @var int[] */
	private array $courseListCache;
	private int $courseListCacheCreatedAt = 0;
	
	/**
	 * Gets the full list of courses, using the GIds, containing its name and UId (e.g. TINF20AI1 => 8062001)
	 * @return int[] [string COURSE_ID => int UID]
	 */
	public function getFullCourseList(): array{
		if(empty($this->courseListCache) || $this->courseListCacheCreatedAt < time() - 60*60*24){
			Logger::warning("Full course list cache miss!");
			$this->warmFullCourseListCache();
		}
		return $this->courseListCache;
	}
	
	public function warmFullCourseListCache(): Promise{
		return call(function(){
			Logger::log("Warming full course list cache...");
			$gIds = yield GIdParser::getGIds();
			$courses = [];
			foreach($gIds as $gId){
				$courses[] = yield UIdParser::getCourses($gId);
			}
			$this->courseListCache = array_merge(...$courses);
			$this->courseListCacheCreatedAt = time();
			Logger::debug("Updated full course list cache");
		});
	}
	
	/**
	 * Gets the lectures between start and end of the particular course.
	 *
	 * @param string $courseName
	 * @param DateTimeImmutable $start
	 * @param DateTimeImmutable $end
	 *
	 * Limitations: Does not properly handle events with no times
	 *
	 * @return Promise<Lecture[]>
	 */
	public function getLecturesForCourseBetween(
		string $courseName, DateTimeInterface $start, DateTimeInterface $end
	): Promise{
		Logger::log(
			"getLecturesForCourseBetween($courseName,".$start->format(DATE_ATOM).",".$end->format(DATE_ATOM).")"
		);
		if($start > $end){
			throw new RuntimeException("Could not find course ".$courseName);
		}
		
		return $this->fetchLecturesByMonthForCourse($courseName, $start, $end);
	}
	
	/**
	 * @param string $courseName
	 * @param DateTimeInterface $start
	 * @param DateTimeInterface $end
	 * @return Promise<Lecture[]>
	 */
	private function fetchLecturesByMonthForCourse(
		string $courseName, DateTimeInterface $start, DateTimeInterface $end
	): Promise{
		$monthsToFetch = [];
		// calculate months to be fetched
		for($y = (int) $start->format("Y"); $y <= (int) $end->format("Y"); ++$y){
			for(
				$m = ($y === (int) $start->format("Y") ? (int) $start->format("n") : 1);
				$m <= ($y === (int) $end->format("Y") ? (int) $end->format("n") : 12); ++$m
			){
				$monthsToFetch[] = [$m, $y];
				echo($m."@".$y.PHP_EOL);
			}
		}
		Logger::var_dump($monthsToFetch, "LPDR monthsToFetch");
		
		$courseUId = $this->getFullCourseList()[$courseName] ?? null;
		if($courseUId === null){
			throw new RuntimeException("Could not find course ".$courseName);
		}
		Logger::var_dump($courseUId, "LPDR courseUId");
		
		
		return $this->enrichLecturesWithIds(call(function() use ($courseUId, $monthsToFetch){
			$lectures = [];
			foreach($monthsToFetch as [$monthToFetch, $year]){
				$month = (new DateTime())->setDate($year, $monthToFetch, 0)->setTime(0,0);
				$lectures[] = MonthParser::getLectures($courseUId, $month->getTimestamp());
			}
			$lectures = yield Promise\all($lectures);
			return array_merge(...$lectures);
		}), $courseUId);
	}
	
	/**
	 * This function will enrich an array of lectures with the IDs present in the ics file.
	 *
	 * Issues: Events with 'no' times in web interface are not matched, because they are interpreted by the
	 * parser as 00:00-00:00, while the ics file reads them as 00:00-23:59
	 *
	 * Events not present in the ics file (this is the case for events older than one year) or otherwise not matched,
	 * will have an ID generated for them based on the start and end times.
	 *
	 * @param Promise<Lecture[]> $lectures A Promise that will resolve with an array of lectures to enrich.
	 * @param int $courseUId The courseUId of all lectures in the $lectures array
	 *
	 * @return Promise<Lecture[]> A Promise that will resolve with an enriched array of lectures out of $lectures
	 */
	private function enrichLecturesWithIds(Promise $lectures, int $courseUId): Promise{
		return call(function() use ($lectures, $courseUId){
			$ics = yield HttpClient::get(
				HttpClient::LECTURE_PLAN_ICAL_URL."?".http_build_query(["uid" => $courseUId])
			);
			
			$data = Reader::read($ics);
			$vEvents = $data->select("VEVENT");
			
			$lectures = yield $lectures;
			foreach($lectures as $key => $lecture){
				$eventId = null;
				foreach($vEvents as $vEvent){
					/** @var $vEvent VEvent */
					
					$dP = DateTimeParser::parseVCardDateTime($vEvent->select("DTSTART")[0]->getValue());
					$dtStart = new DateTimeImmutable(
						"$dP[year]-$dP[month]-$dP[date] $dP[hour]:$dP[minute]:$dP[second]",
						new DateTimeZone("Europe/Berlin")
					);
					
					$dP = DateTimeParser::parseVCardDateTime($vEvent->select("DTEND")[0]->getValue());
					$dtEnd = new DateTimeImmutable(
						"$dP[year]-$dP[month]-$dP[date] $dP[hour]:$dP[minute]:$dP[second]",
						new DateTimeZone("Europe/Berlin")
					);
					
					if($dtStart->getTimestamp() !== $lecture->start->getTimestamp()){
						continue;
					}
					if($dtEnd->getTimestamp() !== $lecture->end->getTimestamp()){
						continue;
					}
					if(trim($vEvent->select("SUMMARY")[0]->getValue()) !== trim($lecture->title)){
						continue;
					}
					if(trim($vEvent->select("LOCATION")[0]->getValue()) !== trim($lecture->room ?? "")){
						continue;
					}
					$eventId = $vEvent->select("UID")[0]->getValue();
					break;
				}
				if($eventId !== null){
					Logger::debug("Found event ".$eventId);
				}else{
					$eventId =
						$lecture->start->format("Ymd\THis")."-".
						$lecture->end->format("Ymd\THis").
						"@egid.lp-parser.r110";
					Logger::warning("Could not find event for ".Logger::var_dump($lecture, return: true));
					Logger::debug("Generated ".$eventId);
				}
				$lectures[$key] = $lecture->withId($eventId);
			}
			
			return $lectures;
		});
	}
	
	private function fetchLecturesByWeekForCourse(
		string $courseName, DateTimeInterface $start, DateTimeInterface $end
	): array{
		$weeksToFetch = [];
		
		// calculate weeks to be fetched
		$startMutable = DateTime::createFromInterface($start);
		$week = new DateInterval("P7D");
		while($startMutable < $end){
			$weeksToFetch[] = [(int) $startMutable->format("W"), (int) $startMutable->format("Y")];
			$startMutable->add($week);
		}
		Logger::var_dump($weeksToFetch, "LPDR weeksToFetch");
		
		$courseUId = $this->getFullCourseList()[$courseName] ?? null;
		if($courseUId === null){
			throw new RuntimeException("Could not find course ".$courseName);
		}
		Logger::var_dump($courseUId, "LPDR courseUId");
		
		$lectures = [];
		foreach($weeksToFetch as [$weekToFetch, $year]){
			$week = (new DateTime())->setISODate($year, $weekToFetch);
			if($weekToFetch === (int) $week->format("W")){ //check if week really exists
				$lectures = array_merge($lectures, WeekParser::getLectures($courseUId, $week->getTimestamp()));
			}
		}
		
		return $lectures;
	}
}