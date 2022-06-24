<?php
namespace Tests;

use App\App;
use Base;
use Test;

abstract class TestBase extends \inventor96\F3TestManager\TestBase {
	private $truncations = ['SET foreign_key_checks = 0;'];
	
	/** @var bool $disable_truncation Set this to true if trunation is nott necessary for the current test */
	protected $disable_truncation = false;

	/** @var Base $f3 The framework instance */
	protected $f3;

	const REQ_TYPE_AJAX = '[ajax]';
	const REQ_TYPE_SYNC = '[sync]';
	const REQ_TYPE_CLI = '[cli]';

	const KEEP_TABLES = [
		// tables
		'banned_scooter_ids',
		'locations',
		'scooter_commands',
		'scooter_command_parameters',
		'scooter_config_directives',
		'scooter_config_targets',
		'scooter_config_types',
		'scooter_flag_types',
		'scooter_limit_types',
		'scooter_state_types',
		'scooter_task_types',
		'scooter_telemetry_types',
		'states',
		'user_roles',

		// views
		'scooters_display',
		'scooter_flags_display',
		'scooter_states_display',
		'scooter_telemetry_display',
		'users_roles',
	];

	/**
	 * Creates the basics for a unit test class
	 *
	 * @param Test $test_instance The instance of the `Test` class to use
	 */
	public function __construct(Test &$test_instance) {
		parent::__construct($test_instance, [get_class()]);
		$this->f3 = Base::instance();

		// get list of tables to truncate after each test
		$key = "Tables_in_{$this->f3->config['db']['dbname']}";
		$tables = $this->f3->DB->exec("SHOW TABLES");
		foreach ($tables as $table) {
			$t = $table[$key];
			if (!in_array($t, self::KEEP_TABLES)) {
				$this->truncations[] = "TRUNCATE TABLE `{$t}`";
			}
		}
	}

	/**
	 * Attempts to give as fresh a start as possible for the next test
	 *
	 *  
	 * @return void
	 */
	public function postTest(): void {
		if ($this->disable_truncation) {
			$this->disable_truncation = false;
		} else {
			// clean the db
			$this->truncateTables();
		}

		// clean the framework
		unset($this->f3);
		$app = App::instance();
		$app->resetF3();
		$app->testingBootstrap();
		$this->f3 = Base::instance();

		// remove cache
		if (file_exists(APP_ROOT_DIR.'tests/tmp/cache/')) {
			@unlink(APP_ROOT_DIR.'tests/tmp/cache/*');
			rmdir(APP_ROOT_DIR.'tests/tmp/cache/');
		}

		// progress
		echo '.';
	}

	/**
	 * Truncates all applicable tables in the active database
	 *
	 * @return void
	 */
	protected function truncateTables(): void {
		$this->f3->DB->exec($this->truncations);
	}

	/**
	 * Records a failure
	 *
	 * @param string $message The message to attach to the test
	 * @return void
	 */
	protected function fail(string $message = ''): void {
		$this->expect(false, $message);
	}

	/**
	 * Evaluate whether two values are equal
	 *
	 * @param mixed $expected What the value should be
	 * @param mixed $actual What the value actually is
	 * @param string $message The message to attach to the test
	 * @return void
	 */
	protected function expectEqual($expected, $actual, string $message = ''): void {
		$this->expect($expected == $actual, $message);
	}

	/**
	 * Evaluate whether a string contains another string
	 *
	 * @param string $expected The string we're expecting in the result
	 * @param string $actual The string that should contain the expected substring
	 * @param string $message The message to attach to the test
	 * @return void
	 */
	protected function expectStringContains(string $expected, string $actual, string $message = ''): void {
		$this->expect(false !== strpos(strtolower($actual), strtolower($expected)), $message);
	}

	/**
	 * Evaluate whether a string does not another string
	 *
	 * @param string $expected The string that should not be in the result
	 * @param string $actual The string to check
	 * @param string $message The message to attach to the test
	 * @return void
	 */
	protected function expectStringNotContains(string $expected, string $actual, string $message = ''): void {
		$this->expect(false === strpos(strtolower($actual), strtolower($expected)), $message);
	}

	/**
	 * Simulates a GET request and ensures the server response contains the expected string
	 *
	 *  
	 * @param string $expected The string to expect in the response
	 * @param string $url The URL to simulate
	 * @param string $request_type Simulate a particular kind of request (see the REQ_TYPE_* constants)
	 */
	protected function expectGetRequestContains(string $expected, string $url, string $request_type = '', string $message = ''): void {
		$this->expect(null === $this->f3->mock(($request_type ? $request_type.' ' : '').$url));
		$this->expectStringContains($expected, $this->f3->RESPONSE, $message);
	}

	/**
	 * Simulates a POST request with a structured POST body and ensures the server response contains the expected string
	 *
	 *  
	 * @param string $expected The string to expect in the response
	 * @param string $url The URL to simulate
	 * @param array $post_body The POST body to send with the request
	 * @param string $request_type Simulate a particular kind of request (see the REQ_TYPE_* constants)
	 */
	protected function expectPostRequestContains(string $expected, string $url, array $post_body, string $request_type = '', string $message = ''): void {
		$this->expect(null === $this->f3->mock(($request_type ? $request_type.' ' : '').$url, null, null, json_encode($post_body)));
		$this->expectStringContains($expected, $this->f3->RESPONSE, $message);
	}

	/**
	 * Simulates a POST request with a raw POST body and ensures the server response contains the expected string
	 *
	 *  
	 * @param string $expected The string to expect in the response
	 * @param string $url The URL to simulate
	 * @param array $post_body The POST body to send with the request
	 * @param string $request_type Simulate a particular kind of request (see the REQ_TYPE_* constants)
	 */
	protected function expectRawPostRequestContains(string $expected, string $url, string $post_body, string $request_type = '', string $message = ''): void {
		$this->expect(null === $this->f3->mock(($request_type ? $request_type.' ' : '').$url, null, null, $post_body));
		$this->expectStringContains($expected, $this->f3->RESPONSE, $message);
	}
}