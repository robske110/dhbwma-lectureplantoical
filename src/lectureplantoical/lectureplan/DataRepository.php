<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\lectureplan;

use Amp\Promise;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use robske_110\dhbwma\lectureplantoical\lectureplan\representation\Lecture;
use robske_110\Logger\Logger;
use RuntimeException;
use function Amp\call;

class DataRepository{
	private array $courseListCache;
	private int $courseListCacheCreatedAt = 0;
	
	/**
	 * Gets the full list of courses, using the GIds, containing its name and UId (e.g. TINF20AI1 => 8062001)
	 * @return array [COURSE_ID => UID]
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
		
		
		return call(function() use ($courseUId, $monthsToFetch){
			$lectures = [];
			foreach($monthsToFetch as [$monthToFetch, $year]){
				$month = (new DateTime())->setDate($year, $monthToFetch, 0)->setTime(0,0);
				$lectures[] = yield MonthParser::getLectures($courseUId, $month->getTimestamp());
			}
			return array_merge(...$lectures);
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