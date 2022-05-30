<?php
namespace Controllers;

use Base;
use DateTimeZone;
use Models\User;

class SessionTools {
	/**
	 * Updates session info after a user logs in
	 *
	 * @param User $user The user that logged in
	 * @param bool $keep_session Whether to keep the server-side session persistent between browser sessions
	 */
	public static function logInUser(User $user, bool $keep_session): void {
		$f3 = Base::instance();

		// cookie stuff
		if ($keep_session) {
			// set 'remember me' cookie
			setcookie($f3->config['cookies']['remember_me'], '1', time() + (60 * 60 * 24 * 90), '/', $f3->config['host'], !$f3->is_local_dev, true);
		}
		session_regenerate_id();

		// record user id in table
		$f3->update_session_db = [
			'session_id' => session_id(),
			'user_id' => $user->id,
		];

		// update session data
		$f3->SESSION['user_id'] = $user->id;
		$f3->SESSION['login_ts'] = time();
		$f3->SESSION['last_rotate_ts'] = time();
		$f3->SESSION['keep_session'] = $keep_session;

		self::loadUser();
	}

	/**
	 * Updates session info after a user logs out
	 */
	public static function logOutUser(): void {
		$f3 = Base::instance();

		// record user id in table
		$f3->DB->exec("UPDATE `user_sessions` SET `user_id` = 0 WHERE `session_id` = ?", [session_id()]);

		// update session data
		$f3->SESSION['user_id'] = 0;
		$f3->SESSION['login_ts'] = time();
		$f3->SESSION['last_rotate_ts'] = time();
		$f3->SESSION['keep_session'] = false;
		session_unset();

		// cookie stuff
		session_regenerate_id();
		setcookie($f3->config['cookies']['remember_me'], '', time() - 3600, '/', $f3->config['host'], !$f3->is_local_dev, true);

		// user stuff
		self::loadUser();
	}

	/**
	 * Rotates and updates session info when the session ID is due for rotation
	 */
	public static function rotateId(): void {
		$f3 = Base::instance();

		// cookie stuff
		session_regenerate_id();

		// record user id in table
		$f3->update_session_db = [
			'session_id' => session_id(),
			'user_id' => $f3->SESSION['user_id'],
		];

		// update session data
		$f3->SESSION['last_rotate_ts'] = time();
	}

	/**
	 * Loads user into into the session
	 */
	public static function loadUser(): void {
		$f3 = Base::instance();

		// defaults
		$f3->logged_in = false;

		// check if user is logged in
		if ($f3->SESSION['user_id']) {
			$user = User::getByID($f3->SESSION['user_id']);

			// double check this user actually exists
			if (!$user->dry()) {
				$f3->logged_in = true;
			}

			// for easy access
			$f3->user = $user;
		} else {
			// explicitly set to 0
			$f3->SESSION['user_id'] = 0;
			$f3->user = new User();
		}
	}

	/**
	 * Starts and sets up the session
	 *
	 * @param bool $skip_remember_reset Skips resetting the cookie for 'remember me'
	 */
	public static function setupSessioning(bool $skip_remember_reset = false): void {
		$f3 = Base::instance();

		// handle custom db updates for session
		$f3->update_session_db = [];
		$f3->set('UNLOAD', function() {
			$f3 = Base::instance();
			if (is_array($f3->update_session_db) && count($f3->update_session_db)) {
				$f3->DB->exec("UPDATE `user_sessions` SET `user_id` = ? WHERE `session_id` = ?", [$f3->update_session_db['user_id'], $f3->update_session_db['session_id']]);
			}
		});

		// setup session cookie settings
		ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 90);
		ini_set('session.cookie_secure', $f3->is_local_dev ? 0 : 1);
		ini_set('session.cookie_httponly', 1);
		ini_set('session.cookie_domain', $f3->config['host']);
		ini_set('session.use_strict_mode', 1);
		ini_set('session.cookie_samesite', 'Lax');
		session_name($f3->config['cookies']['session']);

		// check for 'remember me' setting
		if ($f3->COOKIE[$f3->config['cookies']['remember_me']]) {
			session_set_cookie_params(60 * 60 * 24 * 90);
		}

		// start/load the session
		new \DB\SQL\Session($f3->DB, 'user_sessions', true, function ($s) { return true; }, 'CSRF');

		// reset 'remember me' and session id cookie
		if (!$skip_remember_reset && $f3->SESSION['keep_session']) {
			setcookie($f3->config['cookies']['remember_me'], '1', time() + (60 * 60 * 24 * 90), '/', $f3->config['host'], !$f3->is_local_dev, true);
			setcookie(session_name(), session_id(), time() + (60 * 60 * 24 * 90), '/', $f3->config['host'], !$f3->is_local_dev, true);
		}

		// load the user into the framework
		self::loadUser();
	}

	/**
	 * Loads the user's timezone into the framework
	 */
	public static function loadTz(): void {
		$f3 = Base::instance();

		// check for timezone cookie
		if (!empty($f3->COOKIE['tz'])) {
			try {
				$f3->tz = new DateTimeZone($f3->COOKIE['tz']);
				return;
			} catch (\Exception $e) { /* covered by next check */ }
		}

		// next try pulling from the user's default
		if ($f3->user->timezone !== null) {
			try {
				$f3->tz = $f3->user->timezone;
				return;
			} catch (\Exception $e) { /* covered by next check */ }
		}

		// hopefully never needed, but just in case...
		$f3->tz = new DateTimeZone('America/Boise');
	}
}