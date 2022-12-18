<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\lectureplan\representation;

use DateTimeImmutable;

class Lecture{
	/**
	 * @param string $title
	 * @param DateTimeImmutable $start
	 * @param DateTimeImmutable $end
	 * @param ?string $description
	 * @param string|null $room
	 */
    public function __construct(
		public readonly string $title,
		public readonly DateTimeImmutable $start,
		public readonly DateTimeImmutable $end,
		public readonly ?string $description,
		public readonly ?string $room = null){
    }
}