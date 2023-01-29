<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\lectureplan;

use Amp\Promise;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use Generator;
use robske_110\dhbwma\lectureplantoical\lectureplan\representation\Lecture;
use function Amp\call;

abstract class MonthParser{
	/**
	 * Retrieves all the lectures for a given UId and a specified month
	 * @param int $uId The UId for which all lectures shall be returned
	 * @param int $uTime The Unix timestamp that specifies the month to fetch the lectures for
	 * @return Promise<Lecture[]> Contains the Lecture objects for the parsed month
	 */
	public static function getLectures(int $uId, int $uTime): Promise{
		return call(self::_getLectures(...), $uId, $uTime);
	}
	
	private static function _getLectures($uId, $uTime): Generator{
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		// the gId is not needed to fetch the lecture plan for a course
		$lecturePlan = yield HttpClient::get(
			HttpClient::LECTURE_PLAN_BASE_URL."?".http_build_query([
				"action" => "view",
				"uid" => $uId,
				"date" => $uTime,
				"view" => "month"
			])
		);
		@$dom->loadHTML($lecturePlan);
		$xpath = new DomXPath($dom);
		
		$content = $xpath->query("//div[@data-role='content']/div[@class='ui-grid-e']/a");
		$days = [];
		foreach($content->getIterator() as $item){
			$days[] = $item->firstChild->firstChild->firstChild;
		}
		/** @var Lecture[] $lectures */
		$lectures = [];
		
		$year = date('Y', $uTime);
		foreach($days as $day){
			$date = $day->firstChild->nodeValue;
			$date = explode(", ", $date)[1];
			$lectureList = $day->childNodes;
			
			for($i = 1; $i < $lectureList->length; $i++){
				//checks for empty days in month view, which have "ghost entries"
				if($lectureList[$i]->nodeName === "#text"){
					continue;
				}
				if($xpath->query("div[@class='cal-title']", $lectureList[$i])->count() === 0){
					continue;
				}
				//begin parsing
				$time = $xpath->query("div[@class='cal-time']", $lectureList[$i])->item(0)->nodeValue ?? null;
				$timeZone = new DateTimeZone("Europe/Berlin");
				if($time !== null){
					$time = explode("-", $time);
					
					$start = DateTimeImmutable::createFromFormat('d.mYH:i', $date.$year.$time[0], $timeZone);
					$end = DateTimeImmutable::createFromFormat('d.mYH:i', $date.$year.$time[1], $timeZone);
				}else{ //no time available, set to occur at midnight
					$start = $end = DateTimeImmutable::createFromFormat("d.mY", $date.$year, $timeZone)->setTime(0,0);
				}
				
				$title = $xpath->query("div[@class='cal-title']", $lectureList[$i])->item(0)->nodeValue;
				$description = $xpath->query("div[@class='cal-text']", $lectureList[$i])->item(0)?->nodeValue;
				$room = $xpath->query("div[@class='cal-res']", $lectureList[$i])->item(0)?->nodeValue;
				
				$lectures[] = new Lecture($title, $start, $end, $description, $room);
			}
		}
		return $lectures;
	}
}
