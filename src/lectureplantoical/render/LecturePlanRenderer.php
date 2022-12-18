<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\render;

use DateTimeImmutable;
use robske_110\dhbwma\lectureplantoical\lectureplan\DataRepository;

abstract class LecturePlanRenderer{
	public function __construct(
		protected DataRepository $lecturePlan,
		protected string $courseName, protected DateTimeImmutable $start, protected DateTimeImmutable $end
	){
	}
	
	/**
	 * @return string
	 */
	public abstract function renderContent(): string;
	
	/**
	 * @return array
	 */
	public abstract function getHeaders(): array;
}