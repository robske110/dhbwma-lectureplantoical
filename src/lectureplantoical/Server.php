<?php
declare(strict_types=1);

namespace robske_110\dhbwma\lectureplantoical;

/* Load Composer Autoloader. */
require __DIR__."/../../vendor/autoload.php";

$_ENV = getenv();

use robske_110\Logger\Logger;
Logger::init();

$main = new Main();
$main->start();