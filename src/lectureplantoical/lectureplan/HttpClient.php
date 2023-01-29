<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical\lectureplan;

use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Promise;
use robske_110\Logger\Logger;
use function Amp\call;

class HttpClient{
	private static UnlimitedConnectionPool $pool;
	private static \Amp\Http\Client\HttpClient $httpClient;
	
	const LECTURE_PLAN_BASE_URL = "https://vorlesungsplan.dhbw-mannheim.de/index.php";
	
	public static function httpClient(): \Amp\Http\Client\HttpClient{
		if(!isset(self::$httpClient)){
			self::$pool = new UnlimitedConnectionPool;
			self::$httpClient = (new HttpClientBuilder)->usingPool(self::$pool)->build();
		}
		return self::$httpClient;
	}
	
	public static function pool(): UnlimitedConnectionPool{
		if(!isset(self::$pool)){
			self::httpClient();
		}
		return self::$pool;
	}
	
	public static function debugPool(): void{
		Logger::debug(
			"connAttempts: ".self::pool()->getTotalConnectionAttempts().
			" streamREQs: ".self::pool()->getTotalStreamRequests().
			" openConns: ".self::pool()->getOpenConnectionCount()
		);
	}
	
	public static function get(string $uri): Promise{
		return call(function() use ($uri){
			Logger::debug("Requesting ".$uri."...");
			$response = yield HttpClient::httpClient()->request(new Request($uri));
			HttpClient::debugPool();
			return yield $response->getBody()->buffer();
		});
	}
}