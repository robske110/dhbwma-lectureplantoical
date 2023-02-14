<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical;

const BASE_DIR = __DIR__."/../../";

/* Load Composer Autoloader. */
require BASE_DIR."vendor/autoload.php";

$_ENV = getenv();

use robske_110\Logger\Logger;
Logger::init();

$hash = exec("git -C \"".BASE_DIR."\" rev-parse HEAD 2>/dev/null");
$exitCode = -1;
exec("git -C \"".BASE_DIR."\" diff --quiet 2>/dev/null", $out, $exitCode);
if($exitCode == 1){
	$hash .= "-dirty";
}
define("VERSION", (!empty($hash) ? $hash : "<unknown version>"));
Logger::log("Starting LP-Parser v".VERSION."...");

$main = new Main();
$main->start();