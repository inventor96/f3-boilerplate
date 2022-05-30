<?php
$start = microtime(true);

use App\App;
use inventor96\F3TestManager\TestManager;

// don't need these errors
error_reporting(E_ALL & ~E_USER_DEPRECATED & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

// include the necessary tools
require __DIR__.'/../app/vendor/autoload.php';
require __DIR__.'/../app/App.php';
require __DIR__.'/TestBase.php';

// setup the testing environment
App::instance()->testingBootstrap();

// get the list of dirs where tests are
$dirs = glob(__DIR__.'/*', GLOB_ONLYDIR | GLOB_MARK | GLOB_ERR);

// make sure we actually have something to work with
if ($dirs === false) {
	echo "There was an error while reading the current directory.\n";
	exit(1);
}

// setup the test instance
$test = new Test();

// reporting
register_shutdown_function(function() use ($start, &$test) {
	$diff = round(microtime(true) - $start, 2);
	$pass = $fail = 0;
	foreach ($test->results() as $t) {
		switch ($t['status']) {
			case true: ++$pass; break;
			case false: ++$fail; break;
		}
	}
	echo PHP_EOL.($pass + $fail)." expectations checked in {$diff} seconds, with \033[32m{$pass} passing\033[0m and \033[31m{$fail} failing\033[0m.".PHP_EOL;
});

// process each directory
foreach ($dirs as $dir) {
	TestManager::runTests($dir, $test);
}

// output results
echo "\n";
TestManager::reportTests($test);