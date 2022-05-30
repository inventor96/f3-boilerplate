<?php
/*
see https://fatfreeframework.com/3.7/base#route for how some options work (roles and redirect are custom)

Example:
'POST /login' => [              // required - The URL pattern to match
	'callback' => Auth::class.'->loginUser', // required - Callback function for this route
	'roles' => [                // required - The user roles that are allowed to access this route (see the UserRole model for individual roles and preset arrays)
		UserRole::ROLE_ANONYMOUS,
	],
	'redirect' => '/',          // optional - The URL to redirect to if a user does not have an allowed role (defaults to '/')
	'ttl' => 3600,              // optional - How long the result is cached for
	'kbps' => 128,              // optional - Throttle speed
	'csrf' => true,             // optional - whether a valid CSRF is required
	'skip_json_parse' => false, // optional - skip parsing the BODY as JSON
],
*/

use Controllers\Auth;
use Controllers\Crons;
use Controllers\Demo;
use Controllers\Home;
use Controllers\User;
use Models\UserRole;

return [
	// Home routes
	'GET|HEAD /' => [
		'callback' => Home::class.'->homePage',
		'roles' => UserRole::LIST_ALL,
	],
	'GET|HEAD /about' => [
		'callback' => Home::class.'->aboutPage',
		'roles' => UserRole::LIST_ALL,
	],
	'GET|HEAD /contact' => [
		'callback' => Home::class.'->contactPage',
		'roles' => UserRole::LIST_ALL,
	],
	'GET|HEAD /legal' => [
		'callback' => Home::class.'->legalPage',
		'roles' => UserRole::LIST_ALL,
	],
	'GET|HEAD /ping' => [
		'callback' => Home::class.'->pingResponse',
		'roles' => UserRole::LIST_ALL,
	],
	'GET|HEAD /min/@t' => [
		'callback' => Home::class.'->getResource',
		'roles' => UserRole::LIST_ALL,
		'ttl' => 86400,
	],
	'GET|HEAD /type/@type' => [
		'callback' => Home::class.'->getType',
		'roles' => UserRole::LIST_ALL,
	],

	// Demo routes
	'GET|HEAD /admin' => [
		'callback' => Demo::class.'->adminUserPage',
		'roles' => [UserRole::ROLE_ROOT_ADMIN],
	],
	'GET|HEAD /user' => [
		'callback' => Demo::class.'->regUserPage',
		'roles' => [UserRole::ROLE_USER],
	],
	'GET|HEAD /anonymous' => [
		'callback' => Demo::class.'->anonUserPage',
		'roles' => [UserRole::ROLE_ANONYMOUS],
	],

	// Authentication routes
	'GET|HEAD /login' => [
		'callback' => Auth::class.'->loginPage',
		'roles' => [UserRole::ROLE_ANONYMOUS],
	],
	'POST /login' => [
		'callback' => Auth::class.'->logInUser',
		'roles' => [UserRole::ROLE_ANONYMOUS],
		'kbps' => 128,
	],
	'GET|HEAD /logout' => [
		'callback' => Auth::class.'->logOutUser',
		'roles' => UserRole::LIST_AUTHENTICATED,
	],
	'GET|HEAD /forgot' => [
		'callback' => Auth::class.'->forgotPwdPage',
		'roles' => [UserRole::ROLE_ANONYMOUS],
	],
	'POST /forgot' => [
		'callback' => Auth::class.'->forgotPwd',
		'roles' => [UserRole::ROLE_ANONYMOUS],
		'kbps' => 128,
	],
	'GET|HEAD /password-reset' => [
		'callback' => Auth::class.'->pwdResetPage',
		'roles' => [UserRole::ROLE_ANONYMOUS],
	],
	'POST /password-reset' => [
		'callback' => Auth::class.'->pwdReset',
		'roles' => [UserRole::ROLE_ANONYMOUS],
		'kbps' => 128,
	],

	// User routes
	'GET|HEAD /signup' => [
		'callback' => User::class.'->signUpPage',
		'roles' => [UserRole::ROLE_ANONYMOUS],
	],
	'POST /signup' => [
		'callback' => User::class.'->signUpUser',
		'roles' => [UserRole::ROLE_ANONYMOUS],
		'kbps' => 128,
	],
	'GET|HEAD /signup-confirm' => [
		'callback' => User::class.'->signUpConfimPage',
		'roles' => [UserRole::ROLE_ANONYMOUS],
	],
	'GET|HEAD /email-confirm' => [
		'callback' => User::class.'->emailConfirmPage',
		'roles' => UserRole::LIST_ALL,
		'kbps' => 128,
	],
	'POST /user/send-verification-email' => [
		'callback' => User::class.'->sendVerificationEmail',
		'roles' => UserRole::LIST_AUTHENTICATED,
		'kbps' => 128,
	],

	// Cron routes
	'GET|HEAD /crons/example-cron' => [
		'callback' => Crons::class.'->exampleCron',
		'roles' => [UserRole::ROLE_ANONYMOUS],
	],
];