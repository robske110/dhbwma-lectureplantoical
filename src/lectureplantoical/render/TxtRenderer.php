<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\render;

use Amp\Promise;
use robske_110\Logger\Logger;
use function Amp\call;

class TxtRenderer extends LecturePlanRenderer{
	public function renderContent(): Promise{
		return call(function(){
			$lectures =
				yield $this->lecturePlan->getLecturesForCourseBetween($this->courseName, $this->start, $this->end);
			
			return Logger::var_dump($lectures, return: true);
		});
	}
	
	public function getHeaders(): array{
		return [
			"Content-Type" => "text/plain; charset=utf-8",
		];
	}
}