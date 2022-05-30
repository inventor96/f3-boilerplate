<?php
namespace Controllers;

use Base;

class Csrf {
	/**
	 * Checks whether the CSRF in the request matches that in the session
	 *
	 * @param bool $check_framework When true, checks the framework value instead of the cookie value
	 * @return bool Whether or not the CSRF is valid
	 */
	public static function isValid(bool $check_framework = true): bool {
		$f3 = Base::instance();

		if ($check_framework) {
			return $f3->valid_csrf;
		} else {
			return $f3->COOKIE[$f3->config['cookies']['csrf']] && $f3->SESSION['csrf'] && $f3->COOKIE[$f3->config['cookies']['csrf']] == $f3->SESSION['csrf'];
		}
	}

	/**
	 * Updates the CSRF token
	 */
	public static function update(): void {
		$f3 = Base::instance();

		// store new csrf
		$f3->copy('CSRF', 'SESSION.csrf');
		setcookie($f3->config['cookies']['csrf'], $f3->CSRF, [
			'expires' => 0,
			'path' => '/',
			'domain' => $f3->config['host'],
			'secure' => $f3->is_local_dev ? false : true,
			'httponly' => true,
			'samesite' => 'Strict']);
	}

	/**
	 * Checks whether or not the CSRF should be required
	 *
	 * @return bool Whether the CSRF should be required
	 */
	public static function isRequired(): bool {
		$f3 = Base::instance();
		$route_settings = $f3->route_settings[$f3->VERB.' '.$f3->PATTERN];
		return isset($route_settings['csrf']) ? $route_settings['csrf'] : ($f3->AJAX || $f3->VERB === 'POST');
	}

	/**
	 * Requires a valid CSRF, erroring out if it fails
	 *
	 * @param string $message The message to return
	 * @param bool $check_framework When true, checks the framework value instead of the cookie value
	 */
	public static function requireCsrf(string $message = '', bool $check_framework = true): void {
		if (!self::isValid($check_framework)) {
			Base::instance()->error(401, $message ?: 'Whoops! There was something wrong with that request. Please refresh the page and try again. Technical message: Missing or invalid security parameter.');
		}
	}
}