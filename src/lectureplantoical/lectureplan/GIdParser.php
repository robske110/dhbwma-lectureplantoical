<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\lectureplan;

use Amp\Promise;
use DOMDocument;
use DOMXPath;
use Generator;
use function Amp\call;

abstract class GIdParser{
	/**
	 * Retrieves all the GIds from the lecture plan
	 *
	 * @return Promise<int[]> Contains every GId
	 */
	public static function getGIds(): Promise{
		return call(self::_getGIds(...));
	}
	
	public static function _getGIds(): Generator{
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		$groupList = yield HttpClient::get(HttpClient::LECTURE_PLAN_BASE_URL);
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
