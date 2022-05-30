<?php
return [
	'env' => 'prod',
	'srv_tz' => 'UTC',
	'db' => [
		'host' => '127.0.0.1',
		'port' => 3306,
		'dbname' => 'f3_boilerplate',
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
		'subject' => 'Error Report from Prod Site',
	],
	'base_url' => 'https://app.f3_boilerplate.net/',
	'host' => 'app.f3_boilerplate.net',
	'headers' => [
		"Content-Security-Policy" =>
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
		'csrf' => 'l9N4B1jA',
		'session' => '7T9ewAUg',
		'remember_me' => 'GbRp7Jla',
	],
];