<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\lectureplan;

use DOMDocument;
use DOMXPath;

abstract class GIdParser{
	/**
	 * Retrieves all the GIds from the lecture plan
	 *
	 * @return int[] Contains every GId
	 */
	public static function getGIds(): array{
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		$groupList = file_get_contents("https://vorlesungsplan.dhbw-mannheim.de/index.php");
		@$dom->loadHTML($groupList);
		
		$xPath = new DomXPath($dom);
		
		$content = $xPath->query("//div[@data-role='content']");
		$categories = $content->item(0)->firstChild->childNodes;
		$gIds = [];
		// Iterate over each category containing the GId and adding it to the array
		foreach($categories as $category){
			$gIds[] = (int) substr($category->firstChild->firstChild->attributes->getNamedItem("href")->nodeValue, -7);
		}
		return $gIds;
	}
}
