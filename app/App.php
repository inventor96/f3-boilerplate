<?php
namespace App;

use Base;
use DateTimeZone;
use DB\SQL;
use Models\Creds;
use Models\Mailer;
use Models\Resource;
use PDO;
use PHPMailer\PHPMailer\Exception;
use Prefab;
use Registry;
use Template;

class App extends Prefab {
	/** @var Base $f3 The framework instance */
	private $f3 = null;

	/** @var SQL $db The db connection instance (used for testing) */
	private static $db;

	/** @var array A list of error messages we don't care to be notified about */
	private const IGNORED_ERR_MSGS = [
		'The password does not meet our minimum strength requirements.',
	];

	/** @var array A list of error http response codes we don't care to be notified about */
	private const IGNORED_ERR_CODES = [
		401,
		404,
		405,
	];

	public function __construct() {
		$this->f3 = Base::instance();
	}

	/**
	 * Configure and run as the web application
	 */
	public function runWebApp() {
		// constants
		$this->defineDirConsts();
		$this->defineTimeConsts();

		// load config
		if (!$this->loadConfig()) {
			die('You need to setup the config file.');
		}

		// environment
		$this->configEnvIndicators();

		// basics
		$this->configF3();

		// debugging
		$this->configDebugStuff();

		// error handling
		$this->configErrHandler();

		// headers
		$this->setHeaders();

		// db
		$this->configDb();

		// versions
		$this->loadVersions();

		// routes
		$this->loadRoutes();

		// srv tz
		$this->setTz();

		$this->f3->run();
	}

	/**
	 * Removes the current instance of thr framework and creates a fresh one
	 *
	 * @return void
	 */
	public function resetF3() {
		Registry::clear('Base');
		unset($this->f3);
		$this->f3 = Base::instance();
	}

	/**
	 * Prepares a testing environment
	 */
	public function testingBootstrap() {
		// constants
		$this->defineDirConsts();
		$this->defineTimeConsts();

		// load config
		if (!$this->loadConfig()) {
			echo "You need to setup the config file.\n";
			exit(1);
		}

		// environment
		$this->configEnvIndicators();

		// basics
		$this->configF3();

		// debugging
		$this->configDebugStuff();

		// db
		$this->configDb();

		// versions
		$this->loadVersions();

		// routes
		$this->loadRoutes();

		// srv tz
		$this->setTz('UTC');

		// don't want to output real content
		$this->f3->QUIET = true;
	}

	/**
	 * Sets the constants for application directories
	 */
	private function defineDirConsts() {
		define('APP_ROOT_DIR', realpath(__DIR__.'/..').'/');
		define('APP_DIR', APP_ROOT_DIR.'app/');
		define('CONFIG_DIR', APP_DIR.'config/');
		define('PUB_DIR', APP_ROOT_DIR.'public/');
	}

	/**
	 * Sets the constants for time formatting
	 */
	private function defineTimeConsts() {
		define('TIME_FORMAT_SQL', 'Y-m-d H:i:s');
		define('TIME_FORMAT_FRIENDLY', 'M j, Y g:i a T');
		define('TIME_FORMAT_HTML_DATE', 'Y-m-d');
		define('TIME_FORMAT_HTML_TIME', 'H:i');
	}

	/**
	 * Loads the config file into the framework
	 *
	 * @return bool Whether the loading was successful
	 */
	private function loadConfig(): bool {
		if (!file_exists(CONFIG_DIR.'config.php')) {
			return false;
		}

		$this->f3->config = require(CONFIG_DIR.'config.php');
		return true;
	}

	/**
	 * Configures the F3 settings
	 */
	private function configF3() {
		$this->f3->AUTOLOAD = APP_DIR.'autoload/';
		$this->f3->UI = APP_DIR.'views/';
		$this->f3->TEMP = APP_DIR.'tmp/';
		$this->f3->LOGS = APP_DIR.'logs/';
		$this->f3->UPLOADS = $this->f3->TEMP.'uploads/';
	}

	/**
	 * Sets the variables to indicate the environment
	 */
	private function configEnvIndicators() {
		$this->f3->is_dev = in_array($this->f3->config['env'], [ 'dev', 'staging', 'testing', 'gitlabci' ], true);
		$this->f3->is_local_dev = $this->f3->config['env'] === 'dev';
		$this->f3->is_staging = $this->f3->config['env'] === 'staging';
		$this->f3->is_testing = in_array($this->f3->config['env'], ['testing', 'gitlabci'], true);
		$this->f3->is_prod = !$this->f3->is_dev;
	}

	/**
	 * Sets some debugging settings
	 */
	private function configDebugStuff() {
		if ($this->f3->is_dev || $this->f3->is_testing) {
			$this->f3->CACHE = false;
			$this->f3->DEBUG = 3;
			$this->f3->route('GET /info', function() { phpinfo(); });
		} else {
			$this->f3->CACHE = true;
			$this->f3->DEBUG = 0;
		}
	}

	/**
	 * Sets up the error handling function
	 */
	private function configErrHandler() {
		$this->f3->set('ONERROR', function ($f3) {
			// recursively clear existing output buffers
			while (ob_get_level()) {
				ob_end_clean();
			}

			// get trace (supposed to be an array, but sometimes it's a string)
			$trace = $f3->ERROR['trace'];
			$finalTrace = [];
			if (is_string($trace)) {
				$finalTrace = explode("\n", $trace);
			} elseif (is_array($trace)) {
				$finalTrace = $trace;
			}

			// check if message needs cleaned up for the user
			$clean_text = $f3->ERROR['text'];
			if (preg_match('/\[[^:]+?php:\d+\]/', $clean_text)) {
				/** @var Exception $e */
				$e = $f3->EXCEPTION;

				// check if the message came from one of our exceptions
				$found = false;
				foreach (scandir($f3->AUTOLOAD.'Exceptions/') as $file) {
					if (strlen($file) > 4 && substr($file, -4) === '.php') {
						$class_name = str_replace('.php', '', $file);
						if (is_a($e, 'Exceptions\\'.$class_name)) {
							// do the sanitizing
							$clean_text = preg_replace('/\[[^:]+?php:\d+\]/', '', $clean_text);

							// set error code
							$code = $e->getCode();
							if (is_int($code) && $code >= 400 && $code <= 599) {
								http_response_code($code);
								$f3->ERROR['code'] = $code;
							}

							// stop processing
							$found = true;
							break;
						}
					}
				}

				// set a generic message for the user if it's not our own exception
				if (!$found) {
					$clean_text = "We've encountered a technical difficulty.";
				}
			}

			// setup sections
			$email_sent = false;
			$details = [
				'Stack Trace' => $finalTrace,
				'F3' => $f3,
			];

			// ignore error notifications for specific http error codes
			if (in_array($f3->ERROR['code'], self::IGNORED_ERR_CODES) || in_array($f3->ERROR['status'], self::IGNORED_ERR_MSGS)) {
				$email_sent = false;
			} else {
				// send error message/email
				$mail_config = $f3->config['error_email'];
				if ($mail_config['enabled']) {
					$mail = Mailer::createMailer();
					try {
						// header stuff
						$mail->setFrom($mail_config['from'], $mail_config['from_name']);
						$mail->addAddress($mail_config['to']);
						$mail->Subject = $mail_config['subject'];

						// content
						$mail->isHTML(true);
						$email_template_params = [
							'ERROR' => $f3->ERROR,
							'details' => $details,
						];
						$mail->Body = Template::instance()->render('emails/error.html', 'text/html', $email_template_params);
						$mail->AltBody = Template::instance()->render('emails/error.txt', 'text/plain', $email_template_params);

						$mail->send();
						$email_sent = true;
					} catch (Exception $e) {
						$email_sent = false;
					}
				}
			}

			if ($f3->AJAX) {
				// return json response for ajax requests
				$response = [
					'success' => false,
					'code' => $f3->ERROR['code'],
					'error' => $f3->is_dev ? $f3->ERROR['text'] : $clean_text,
				];

				// add debug info on dev
				if ($f3->is_dev) {
					// prepare framework copy
					unset($details['F3']);
					$exeption = print_r($f3->EXCEPTION, true);

					// remove recursion
					$out_exception = [];
					$in_obj = false;
					$space_line = false;
					$spaces = '';
					foreach (explode("\n", $exeption) as $line) {
						if (!$in_obj) {
							if (strpos($line, 'Base Object') !== false) {
								$in_obj = true;
								$space_line = true;
							}
							$out_exception[] = $line;
						} elseif ($space_line) {
							preg_match('/(\s+)\(/', $line, $matches);
							$spaces = $matches[1];
							$space_line = false;
						} elseif ($line == $spaces.')') {
							$in_obj = false;
						}
					}
					$f3->EXCEPTION = implode("\n", $out_exception);

					// reformat everything for nice json
					$eval_str = var_export($f3, true);
					$eval_str = preg_replace('/[\w\\\]+::__set_state\(/m', 'n(', $eval_str);
					$eval_str = 'function n($a){return $a;} return '.$eval_str.';';
					$response['details'] = ['Stack Trace' => $finalTrace, 'F3' => eval($eval_str)];
				}

				echo json_encode($response);
			} else {
				// build params
				$template_params = [
					'_is_dev' => $f3->is_dev,
					'_is_prod' => $f3->is_prod,
					'_UI' => $f3->UI,
					'_env' => $f3->config['env'],
					'_title' => $f3->ERROR['code'].' Error',
					'_css' => Resource::DEFAULT_CSS,
					'_css_v' => rand(1, 1000),
					'_js' => Resource::DEFAULT_JS,
					'_js_v' => rand(1, 1000),
					'_user_roles' => [],
					'details' => $details,
					'email_sent' => $email_sent,
					'ERROR' => $f3->ERROR,
					'clean_text' => $clean_text,
				];

				// display error page
				echo Template::instance()->render('pages/error.html', 'text/html', $template_params);
			}
		});
	}

	/**
	 * Sets the response headers for the client
	 */
	private function setHeaders() {
		$this->f3->PACKAGE = 'Custom framework designed by NASA and built by the NSA in China. Good luck out there, soldier.';
		if (is_array($this->f3->config['headers']) && count($this->f3->config['headers'])) {
			foreach ($this->f3->config['headers'] as $h_key => $h_val) {
				header($h_key.': '.$h_val);
			}
		}
	}

	/**
	 * Sets up the connection to the database
	 */
	private function configDb() {
		// use existing testing connection if present
		if (isset(self::$db)) {
			$this->f3->DB = self::$db;
			return;
		}

		$db_creds = Creds::instance()->get('db');
		$this->f3->DB = new SQL(
			"mysql:host={$this->f3->config['db']['host']};port={$this->f3->config['db']['port']};dbname={$this->f3->config['db']['dbname']};charset=UTF8",
			$db_creds['username'],
			$db_creds['password'],
			[
				PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
				PDO::ATTR_EMULATE_PREPARES => false,
				PDO::ATTR_STRINGIFY_FETCHES => false,
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			]);

		if ($this->f3->is_prod || $this->f3->is_testing) {
			$this->f3->DB->log(false);
		}

		self::$db = $this->f3->DB;
	}

	/**
	 * Loads client resource versions
	 */
	private function loadVersions() {
		$this->f3->versions = file_exists(CONFIG_DIR.'versions.php') ? require(CONFIG_DIR.'versions.php') : [];
	}

	/**
	 * Loads the web app routes
	 */
	private function loadRoutes() {
		$this->f3->route_settings = require(CONFIG_DIR.'routes.php');
		foreach ($this->f3->route_settings as $route => $settings) {
			if (!$route || !$settings['callback'] || !$settings['roles']) {
				$this->f3->error(500, "Invalid route configuration for: {$route}");
			}
			$this->f3->route($route, $settings['callback'], $settings['ttl'] ?: 0, $settings['kbps'] ?: 0);
		}
	}

	/**
	 * Sets the server timezone
	 */
	private function setTz() {
		$this->f3->srv_tz = new DateTimeZone($this->f3->config['srv_tz'] ?: 'UTC');
	}
}