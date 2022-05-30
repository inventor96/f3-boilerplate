<?php
$return = require(__DIR__.'/credentials.testing.php');
$return['db'] = [
	'username' => 'root',
	'password' => getenv('MYSQL_ROOT_PASSWORD'),
];
return $return;