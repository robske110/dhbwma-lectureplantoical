<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\lectureplan;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use robske_110\dhbwma\lectureplantoical\lectureplan\representation\Lecture;
use robske_110\Logger\Logger;
use RuntimeException;

class DataRepository{
	private array $courseListCache;
	private int $courseListCacheCreatedAt = 0;
	
	/**
	 * Gets the full list of courses, using the GIds, containing its name and UId (e.g. TINF20AI1 => 8062001)
	 * @return array [COURSE_ID => UID]
	 */
	public function getFullCourseList(): array{
		if(!empty($this->courseListCache) && $this->courseListCacheCreatedAt > time() - 60*60*24){
			return $this->courseListCache;
		}
		Logger::warning("Cache miss at cLC");
		$gIds = GIdParser::getGIds();
		$courses = [];
		foreach($gIds as $gId){
			$courses = array_merge($courses, UIdParser::getCourses($gId));
		}
		$this->courseListCacheCreatedAt = time();
		return $this->courseListCache = $courses;
	}
	
	/**
	 * Gets the lectures between start and end of the particular course.
	 *
	 * @param string $courseName
	 * @param DateTimeImmutable $start
	 * @param DateTimeImmutable $end
	 *
	 * Limitations: cannot fetch a range larger than a year, Does not properly handle events with no times
	 *
	 * @return Lecture[]
	 */
	public function getLecturesForCourseBetween(string $courseName, DateTimeInterface $start, DateTimeInterface $end): array{
		Logger::log("getLecturesForCourseBetween($courseName,".$start->format(DATE_ISO8601).",".$end->format(DATE_ISO8601).")");
		if($start > $end){
			Logger::warning("Incorrect start, end order for getLecturesForCourseBetween!");
			return []; //TODO: throw Exception
		}
		
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
			throw new RuntimeException("Could not find ...");
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