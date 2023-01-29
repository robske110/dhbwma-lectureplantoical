<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\lectureplan;

use Amp\Promise;
use DOMDocument;
use DOMXPath;
use Generator;
use function Amp\call;

abstract class UIdParser{
	/**
	 * Retrieves all the UIds for a given GId from the lecture plan
	 * @param int $gId The GId for which all UIds should be retrieved
	 * @return Promise<array> [COURSE_ID => UID]
	 */
	public static function getCourses(int $gId): Promise{
		return call(self::_getCourses(...), $gId);
	}
	
    public static function _getCourses(int $gId): Generator{
        $dom = new DOMDocument();
        $dom->strictErrorChecking = false;
	    $courseList = yield HttpClient::get(
			HttpClient::LECTURE_PLAN_BASE_URL."?".http_build_query([
			    "action" => "list",
			    "gid" => $gId
	        ]
	    ));
        @$dom->loadHTML($courseList);
        $xPath = new DomXPath($dom);

        $content = $xPath->query("//div[@data-role='content']");
        $years = $content->item(0)->firstChild->childNodes;
        $courses = [];
		
		// Iterate over each year prefix of the current category, specified by the GId (f.e. TINF22, TINF21, TINF20, …)
        foreach($years as $year){
			$prefix = $year->firstChild->firstChild->nodeValue; // prefix f.e. "TINF20"
	        // Append the suffix to the prefix (=COURSE_ID) and assign the UId to it
			foreach($xPath->query("div/a", $year) as $course){
				$courses[$prefix.$course->firstChild->nodeValue] =
					(int) substr($course->attributes->getNamedItem("href")->nodeValue,-7);
			}
        }
        return $courses;
    }
}
