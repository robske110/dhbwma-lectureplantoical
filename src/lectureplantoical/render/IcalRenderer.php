<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\render;

use Amp\Promise;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use robske_110\dhbwma\lectureplantoical\lectureplan\representation\Lecture;
use function Amp\call;

class IcalRenderer extends LecturePlanRenderer{
	public function renderContent(): Promise{
		return call(function(){
			$lectures = yield $this->lecturePlan->getLecturesForCourseBetween($this->courseName, $this->start, $this->end);
			
			$events = [];
			foreach($lectures as $lecture){
				/** @var Lecture $lecture */
				$event = (new Event(new UniqueIdentifier($lecture->id)))
					->setSummary($lecture->title)
					->setOccurrence(
						new TimeSpan(
							new DateTime($lecture->start, true),
							new DateTime($lecture->end, true)
						)
					);
				if($lecture->description !== null){
					$event->setDescription($lecture->description);
				}
				if($lecture->room !== null){
					$event->setLocation(new Location($lecture->room));
				}
				$events[] = $event;
			}
			
			
			$calendar = new Calendar($events);
			$calendar->setProductIdentifier("-//r110//dhbwmalectureplantoical//V0//DE");
			
			$componentFactory = new CalendarFactory();
			$calendarComponent = $componentFactory->createCalendar($calendar);
			
			return (string) $calendarComponent;
		});
	}
	
	public function getHeaders(): array{
		return [
			"Content-Type" => "text/calendar; charset=utf-8",
			"Content-Disposition" => "attachment; filename=\"".$this->courseName.".ics\""
		];
	}
}