<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\render;

use Amp\Promise;
use robske_110\Logger\Logger;
use function Amp\call;

class JsonRenderer extends LecturePlanRenderer{
	public function renderContent(): Promise{
		return call(function(){
			$lectures =
				yield $this->lecturePlan->getLecturesForCourseBetween($this->courseName, $this->start, $this->end);
			
			return json_encode($lectures);
		});
	}
	
	public function getHeaders(): array{
		return [
			"Content-Type" => "application/json; charset=utf-8",
		];
	}
}