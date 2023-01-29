<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical;

use Amp\Http\Server\HttpServer as AmpHttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Status;
use Amp\Promise;
use Amp\Socket\Server;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use robske_110\dhbwma\lectureplantoical\render\IcalRenderer;
use robske_110\dhbwma\lectureplantoical\render\TxtRenderer;
use robske_110\Logger\PSRLogger;
use function Amp\call;

class HttpServer{
	private AmpHttpServer $server;
	
	public function __construct(int $port, private readonly Main $main){
		$sockets = [
			Server::listen("0.0.0.0:".$port),
			Server::listen("[::]:".$port)
		];
		$this->server = new AmpHttpServer($sockets, $this->buildRouter(), new PSRLogger);
	}
	
	public function start(){
		call(function(){
			var_dump($this->server->getState());
			yield $this->server->start();
		});
	}
	
	/**
	 * @param Request $request
	 * @param string $renderer
	 *
	 * @return Promise<Response>
	 */
	private function renderCourseCalendarRequest(Request $request, string $renderer): Promise{
		return call(function() use ($request, $renderer){
			parse_str($request->getUri()->getQuery(), $query);
			
			try{
				$renderer = new $renderer(
					$this->main->getLecturePlan(), $request->getAttribute(Router::class)["course"],
					isset($query["start"]) ?
						new DateTimeImmutable($query["start"], new DateTimeZone("UTC")) :
						(new DateTimeImmutable)->sub(DEFAULT_DATE_RANGE),
					isset($query["end"]) ?
						new DateTimeImmutable($query["end"], new DateTimeZone("UTC")) :
						(new DateTimeImmutable())->add(DEFAULT_DATE_RANGE)
				);
			}catch(Exception $e){
				return new Response(
					Status::BAD_REQUEST,
					["Access-Control-Allow-Origin" => "*"],
					"Fail"
				);
			}
			
			return new Response(
				Status::OK,
				$renderer->getHeaders() + ["Access-Control-Allow-Origin" => "*"],
				yield $renderer->renderContent()
			);
		});
	}
	
	private function buildRouter(): Router{
		define("DEFAULT_DATE_RANGE", new DateInterval("P1Y"));
		
		$router = new Router;
		$router->addRoute("GET", "/", new CallableRequestHandler(function(){
			return new Response(
				Status::OK, ["content-type" => "text/plain; charset=utf-8"],
				"apiSRV/dhbwmalectureplantoical"
			);
		}));
		
		$router->addRoute("GET", "/{course}/ics", new CallableRequestHandler(function(Request $request){
			return yield $this->renderCourseCalendarRequest($request, IcalRenderer::class);
		}));
		
		$router->addRoute("GET", "/{course}/txt", new CallableRequestHandler(function(Request $request){
			return yield $this->renderCourseCalendarRequest($request, TxtRenderer::class);
		}));
		
		return $router;
	}
}