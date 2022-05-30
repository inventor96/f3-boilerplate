<?php
$return = require(__DIR__.'/config.testing.php');
$return['env'] = 'gitlabci';
$return['db'] = [
	'host' => 'mariadb',
	'port' => 3306,
	'dbname' => getenv('MYSQL_DATABASE'),
];
return $return;