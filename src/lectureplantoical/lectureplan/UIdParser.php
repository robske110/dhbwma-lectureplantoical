<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\lectureplan;

use DOMDocument;
use DOMXPath;

abstract class UIdParser{
	/**
	 * Retrieves all the UIds for a given GId from the lecture plan
	 * @param int $gId The GId for which all UIds should be retrieved
	 * @return array [COURSE_ID => UID]
	 */
    public static function getCourses(int $gId): array{
        $dom = new DOMDocument();
        $dom->strictErrorChecking = false;
        $courseList = file_get_contents("https://vorlesungsplan.dhbw-mannheim.de/index.php?action=list&gid=$gId");
        @$dom->loadHTML($courseList);
        $xPath = new DomXPath($dom);

        $content = $xPath->query("//div[@data-role='content']");
        $years = $content->item(0)->firstChild->childNodes;
        $courses = [];
		
		// Iterate over each year of the current category of courses, specified by the GId (e.g. TINF22, TINF21, TINF20, …)
        foreach($years as $year){
			$prefix = $year->firstChild->firstChild->nodeValue; // Prefix format: e.g. "TINF20"
	        // Append the suffix of each course for the given year to the prefix (key) and assigning it a value with UId and add to course array
			foreach($xPath->query("div/a", $year) as $course){
				$courses[$prefix.$course->firstChild->nodeValue] = (int) substr($course->attributes->getNamedItem("href")->nodeValue,-7);
			}
        }
        return $courses;
    }
}
