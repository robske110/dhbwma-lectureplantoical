<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical;

use Amp\Loop;
use robske_110\dhbwma\lectureplantoical\lectureplan\DataRepository;
use robske_110\Logger\Logger;
use function Amp\Promise\wait;

class Main{
	private DataRepository $lecturePlan;
	private HttpServer $httpServer;
	
	public function __construct(){
		$this->lecturePlan = new DataRepository;
		$this->httpServer = new HttpServer(8088, $this);
	}
	
	/**
	 * @return DataRepository
	 */
	public function getLecturePlan(): DataRepository{
		return $this->lecturePlan;
	}
	
	public function start(): void{
		wait($this->lecturePlan->warmFullCourseListCache());
		$this->httpServer->start();
		Loop::run(function(){
			Loop::repeat(1000*60*60*12, function(){
				$this->lecturePlan->warmFullCourseListCache();
			});
			Loop::onSignal(SIGINT, function(){
				Logger::log("Shutting down...");
				Loop::stop();
			});
		});
	}
}