<?php
return [
	'env' => 'staging',
	'srv_tz' => 'UTC',
	'db' => [
		'host' => '127.0.0.1',
		'port' => 3306,
		'dbname' => 'f3_boilerplate_staging',
	],
	'email' => [
		'server' => 'server.com',
		'port' => 587,
		'noreply_email' => 'no-reply@f3_boilerplate.net',
		'noreply_name' => 'F3 Boilerplate',
	],
	'error_email' => [
		'enabled' => false,
		'from' => 'example@example.net',
		'from_name' => 'Error Report',
		'to' => 'example@example.com',
		'subject' => 'Error Report from Staging Site',
	],
	'base_url' => 'https://staging.f3_boilerplate.net/',
	'host' => 'staging.f3_boilerplate.net',
	'headers' => [
		"Content-Security-Policy-Report-Only" =>
			"default-src 'none';"
			."style-src 'self';"
			."font-src 'self';"
			."img-src 'self' data:;"
			."script-src 'self';"
			."connect-src 'self';"
			."manifest-src 'self';",
		"Referrer-Policy" => "same-origin",
	],
	'cookies' => [
		'csrf' => 'MomHO4YE',
		'session' => 'mokihQJQ',
		'remember_me' => 'AiQoRN3e',
	],
];