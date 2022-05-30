<?php
namespace Controllers;

use Base;
use Models\Captcha;
use Models\Creds;
use Models\Helpers;
use Models\LoginAttempt;
use Models\User;
use Throwable;

class Auth extends ControllerBase {
	public function loginPage(Base $f3, array $args) {
		$this->simplePageRender('login', 'Log In', [], [], ['validate']);
	}

	public function logInUser(Base $f3, array $args) {
		$r = $f3->REQUEST;
		$last_url = $f3->SESSION['last_url'];

		// get user
		$user = User::getByEmail($r['email']);

		// check authentication
		if (!$user->checkPwd($r['password'])) {
			// that's a no no
			LoginAttempt::record($r['email'], false, $f3->IP, LoginAttempt::P_FORM);
			$f3->error(401, "We don't recognize that username or password.");
		}
		LoginAttempt::record($r['email'], true, $f3->IP, LoginAttempt::P_FORM);

		// setup session
		unset($f3->SESSION['last_url']);
		SessionTools::logInUser($user, !empty($r['remember']));

		$this->jsonSuccess(['redirect' => $last_url ?: '/']);
	}

	public function logOutUser(Base $f3, array $args) {
		// update session
		SessionTools::logOutUser();

		// send to login page
		$f3->reroute('/login');
	}

	public function forgotPwdPage(Base $f3, array $args) {
		if ($f3->logged_in) {
			$f3->reroute('/');
		}

		$params = [
			'site_key' => Creds::instance()->get('recaptcha.site_key'),
		];
		$this->simplePageRender('forgot', 'Password Reset', $params, [], ['validate']);
	}

	public function forgotPwd(Base $f3, array $args) {
		// make sure we have everything
		$required_fields = ['email', 'recaptcha'];
		$this->checkRequiredFields($required_fields);
		$r = $f3->REQUEST;

		// verify recaptcha
		if (!$f3->is_dev && !Captcha::check($r['recaptcha'])) {
			$f3->error(401, "Recaptcha verification failed");
			return;
		}

		// return to the client
		$this->jsonSuccess();
		$f3->abort();

		// find the user
		$u = User::getByEmail($r['email']);
		if (!$u->dry()) {
			// send the forgot password email
			$u->sendPwdResetEmail();
		}
	}

	public function pwdResetPage(Base $f3, array $args) {
		if ($f3->logged_in) {
			$f3->reroute('/');
		}

		try {
			// we don't need the result here, but this will throw an exception if it's a bad code
			Helpers::decodeJwt($f3->REQUEST['code']);

			$params = [
				'code' => $f3->REQUEST['code'],
				'site_key' => Creds::instance()->get('recaptcha.site_key'),
			];
			$this->simplePageRender('password-reset', 'Password Reset', $params, [], ['validate']);
		} catch (Throwable $e) {
			$message = "We're not sure what happened with that link, but we don't recognize it. Maybe the email expired? If you need assistance, please reach us via our <a href=\"/contact\">Contact page</a>.";
			$this->inlinePageRender('<h1 class="text-center mt-5">Whoops!</h1><p class="text-center mt-4">'.$message.'</p>', 'Password Reset');
		}
	}

	public function pwdReset(Base $f3, array $args) {
		// make sure we have everything
		$required_fields = ['password', 'password2', 'code', 'recaptcha'];
		$this->checkRequiredFields($required_fields);
		$r = $f3->REQUEST;

		// verify recaptcha
		if (!$f3->is_dev && !Captcha::check($r['recaptcha'])) {
			$f3->error(401, "Recaptcha verification failed");
			return;
		}

		// make sure passwords match
		if ($r['password'] !== $r['password2']) {
			$f3->error(400, 'Passwords do not match!');
		}

		// verify jwt
		$claims = Helpers::decodeJwt($r['code']);

		// update password
		$u = User::getByID($claims['user_id']);
		$u->updatePassword($r['password']);

		$this->jsonSuccess();
	}
}