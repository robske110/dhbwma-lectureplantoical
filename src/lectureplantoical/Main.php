<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical;

use Amp\Loop;
use robske_110\dhbwma\lectureplantoical\lectureplan\DataRepository;

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
	
	public function start(){
		$this->httpServer->start();
		Loop::run(function(){
			Loop::onSignal(SIGINT, function(){
				Loop::stop();
			});
		});
	}
}