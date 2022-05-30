<?php
namespace Controllers;

use Base;
use Exceptions\MissingField;
use Models\Helpers;
use Models\Resource;
use Models\TemplateExtension;
use Models\UserRole;

class ControllerBase {
	/** @var array $template_params An associative array of parameters used in the default templates */
	private static array $template_params = [];

	/**
	 * Setup and security checks
	 *
	 * @param Base $f3 The F3 instance
	 * @param array $args URL params
	 * @return void
	 */
	public function beforeroute(Base $f3, array $args) {
		// setup custom template elements
		TemplateExtension::setUp();

		// update user session info
		$skip_remember_reset = in_array($f3->PATTERN, ['/logout', '/min/@t']);
		SessionTools::setupSessioning($skip_remember_reset);
		SessionTools::loadTz();

		// just in case there's no user
		if ($f3->user->dry() || $f3->user->timezone === null) {
			$f3->user->timezone = $f3->tz;
		}

		// check role-based access
		$this->checkRouteAccess();

		// validate csrf
		$f3->valid_csrf = Csrf::isValid(false);

		// require valid csrf for ajax calls
		if (Csrf::isRequired()) {
			Csrf::requireCsrf(false);
		}

		// store new csrf
		Csrf::update();

		// update request var
		$this->updateRequestFromBody();
		$this->sanitizeRequest();

		// build template params
		self::$template_params = [
			'_user_roles' => UserRole::toIds($f3->user->roles), // see TemplateExtension::requireroles()
			'_is_dev' => $f3->is_dev,
			'_is_prod' => $f3->is_prod,
			'_UI' => $f3->UI,
			'_env' => $f3->config['env'],
		];
	}

	/**
	 * Simply render a HTML file template in the app/views/pages/ folder
	 *
	 * @param string $page The HTML file name (minus the .html file extension)
	 * @param string $title The title of the page (used in the header)
	 * @param string $params An associative array containing the variables used in the template
	 * @param array $extra_css Any additional CSS files to include (see the public/css/ folder and the app/config/versions.php file)
	 * @param array $extra_js Any additional JavaScript files to include (see the public/js/ folder and the app/config/versions.php file)
	 * @param int $cache The number of second to cache the output of the page
	 * @throws Exceptions\InvalidPath
	 */
	protected function simplePageRender(string $page, string $title, array $params = [], array $extra_css = [], array $extra_js = [], int $cache = 0) {
		$f3 = Base::instance();

		// make sure page exists
		$safe_page = preg_replace('/[^\w-]+/', '', $page);
		if (!file_exists($f3->UI."pages/{$safe_page}.html")) {
			$f3->error(404, "The requested page does not exist.");
		}

		// resources
		$css_files = Resource::getFileList('css', array_merge($extra_css, [$safe_page]), false);
		$js_files = Resource::getFileList('js', array_merge($extra_js, [$safe_page]), false);
		$css_v = Resource::getVersionString('css', $css_files);
		$js_v = Resource::getVersionString('js', $js_files);

		// combine params
		$params = array_merge(self::$template_params, $params, [
			'_page' => $safe_page,
			'_title' => $title,
			'_css' => $css_files,
			'_css_v' => $css_v,
			'_js' => $js_files,
			'_js_v' => $js_v,
		]);

		// return to client
		echo \Template::instance()->render('templates/default.html', 'text/html', $params, $cache);
	}

	/**
	 * Render a page with inline content
	 *
	 * @param string $contents The raw contents to be displayed
	 * @param string $title The title of the page (used in the header)
	 * @param array $extra_css Any additional CSS files to include (see the public/css/ folder and the app/config/versions.php file)
	 * @param array $extra_js Any additional JavaScript files to include (see the public/js/ folder and the app/config/versions.php file)
	 */
	protected function inlinePageRender(string $contents, string $title, array $extra_css = [], array $extra_js = []) {
		// resources
		$css_files = Resource::getFileList('css', $extra_css, false);
		$js_files = Resource::getFileList('js', $extra_js, false);
		$css_v = Resource::getVersionString('css', $css_files);
		$js_v = Resource::getVersionString('js', $js_files);

		// combine params
		$params = array_merge(self::$template_params, [
			'_contents' => $contents,
			'_title' => $title,
			'_css' => $css_files,
			'_css_v' => $css_v,
			'_js' => $js_files,
			'_js_v' => $js_v,
		]);

		echo \Template::instance()->render('templates/inline.html', 'text/html', $params);
	}

	/**
	 * Responds with a JSON object and optionally sets the HTTP response code.
	 *
	 * @param array $object The associative array representing the JSON object to be returned.
	 * @param int $status_code The HTTP status code to be used.
	 */
	protected function jsonOutput(array $object, int $status_code = 200) {
		header('Content-type: text/json; charset=UTF-8');
		http_response_code($status_code);
		$object['code'] = $status_code;
		echo json_encode($object);
	}

	/**
	 * Responds with a simple JSON object containing a 'message' value and optionally setting the HTTP response code.
	 *
	 * @param string $message The message to be sent in the JSON object.
	 * @param int $status_code The HTTP status code to be used.
	 */
	protected function jsonMessage(string $message, int $status_code = 200) {
		$this->jsonOutput(['message' => $message], $status_code);
	}

	/**
	 * Responds with a JSON object indicating a success result
	 *
	 * @param array $object An associative array of additional object properties
	 * @param int $status_code The HTTP response code to use
	 */
	protected function jsonSuccess(array $object = [], int $status_code = 200) {
		$object['success'] = true;
		$this->jsonOutput($object, $status_code);
	}

	/**
	 * Responds with a JSON object indicating a failure result
	 *
	 * @param array $object An associative array of additional object properties
	 * @param int $status_code The HTTP response code to use
	 */
	protected function jsonError(string $err_msg, array $object = [], int $status_code = 500) {
		$object['success'] = false;
		$object['error'] = $err_msg;
		$this->jsonOutput($object, $status_code);
	}

	/**
	 * Checks if the current user has the specific role
	 *
	 * @param UserRole The role required
	 * @param string|bool The URL to redirect to. Set to false to return a 401.
	 */
	protected function requireRole(UserRole $user_role, $redirect = false): void {
		$f3 = Base::instance();
		/** @var \Models\User */
		$user = $f3->user;

		if (!$user->hasRole($user_role)) {
			if ($redirect) {
				// record attempted URL if not logged in
				if ($user->dry()) {
					$f3->SESSION['last_url'] = $f3->URI;
				}
				$f3->reroute($redirect);
			} else {
				$f3->error(401);
			}
		}
	}

	/**
	 * Requires at least one of a group of roles
	 *
	 * @param UserRole[] $user_roles The list of allowed roles
	 * @param string|bool $redirect The URL to redirect to. Set to false to return a 401.
	 */
	protected function requireRoles(array $user_roles, $redirect = false): void {
		$f3 = Base::instance();
		/** @var \Models\User */
		$user = $f3->user;

		if (!$user->hasRoles($user_roles)) {
			if ($redirect) {
				// record attempted URL if not logged in
				if ($user->dry()) {
					$f3->SESSION['last_url'] = $f3->URI;
				}
				$f3->reroute($redirect);
			} else {
				$f3->error(401);
			}
		}
	}

	/**
	 * Ensure the input fields are present in the input array
	 *
	 * @param array The list of fields to expect
	 * @param array $input_data The input data to check (defaults to the REQUEST property of the F3 instance's hive)
	 * @param array $is_set_fields The fields, if any that should be checked with isset() instead of just a boolean check
	 * @return bool Returns true if all fields are preset
	 * @throws MissingField
	 */
	protected function checkRequiredFields(array $required_fields, array $input_data = [], array $is_set_fields = []): bool {
		if (!count($input_data)) {
			$input_data = Base::instance()->REQUEST;
		}

		foreach ($required_fields as $field) {
			if ($field === 'recaptcha' && Base::instance()->is_local_dev) {
				continue;
			}
			if (in_array($field, $is_set_fields) ? isset($field) : !$input_data[$field]) {
				throw new MissingField($field);
			}
		}

		return true;
	}

	/**
	 * Fetches the route configuration for the current request
	 */
	protected function getRouteSettings(): array {
		$f3 = Base::instance();

		// find route
		foreach ($f3->route_settings as $route => $settings) {
			$route_parts = explode(' ', $route);
			$verbs = explode('|', $route_parts[0]);
			if (in_array($f3->VERB, $verbs) && $route_parts[1] == $f3->PATTERN) {
				return $settings;
			}
		}

		// should never get here
		$f3->error(500, "Route configuration error!");
	}

	/**
	 * Checks if the current user is allowed to access the matched route
	 */
	private function checkRouteAccess(): void {
		$settings = $this->getRouteSettings();
		$this->requireRoles(array_map([UserRole::class, 'getById'], $settings['roles']), isset($settings['redirect']) ? $settings['redirect'] : false);
	}

	/**
	 * Updates the REQUEST framework variable with the values from the input JSON body
	 */
	private function updateRequestFromBody(): void {
		$settings = $this->getRouteSettings();
		$f3 = Base::instance();

		// interpret ajax calls as json
		if (
			($f3->AJAX || $f3->VERB === 'POST') &&
			is_string($f3->BODY) &&
			strlen($f3->BODY) > 0 &&
			!$settings['skip_json_parse']
		) {
			$input = json_decode($f3->BODY, true);
			$json_err = json_last_error();
			if ($json_err) {
				$f3->error(400, 'Whoops! There was something wrong with that request. Please refresh the page and try again. Technical message: '.Helpers::jsonError($json_err));
			}

			// update
			$f3->set('REQUEST', $input + $f3->REQUEST);
		}
	}

	/**
	 * Sanitizes all user input
	 */
	private function sanitizeRequest(): void {
		$f3 = Base::instance();

		// process each variable
		$r = $f3->REQUEST;
		foreach ($r as $k => $v) {
			$f3->REQUEST[$k] = $f3->clean($v);
		}
	}
}