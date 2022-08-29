<?php
namespace Controllers;

use Base;
use DateTimeZone;
use Exceptions\DuplicateIdentifier;
use Exceptions\MailerError;
use Models\Captcha;
use Models\Creds;
use Models\EmailRecipient;
use Models\Helpers;
use Models\Mailer;
use Models\User as ModelsUser;
use Models\UserRole;
use Throwable;

class User extends ControllerBase {
	public function signUpPage(Base $f3, array $args) {
		$params = [
			'site_key' => Creds::instance()->get('recaptcha.site_key'),
		];
		$this->simplePageRender('signup', 'Sign Up', $params, [], ['validate']);
	}

	public function signUpConfimPage(Base $f3, array $args) {
		$this->simplePageRender('signup-confirm', 'Sign Up');
	}

	public function signUpUser(Base $f3, array $args) {
		// make sure we have everything
		$required_fields = ['fname', 'lname', 'email', 'password', 'password2', 'recaptcha', 'tz'];
		$this->checkRequiredFields($required_fields);
		$r = $f3->REQUEST;

		// verify recaptcha
		if (!Captcha::check($r['recaptcha'])) {
			$f3->error(401, "Recaptcha verification failed");
			return;
		}

		// check password match
		if ($r['password'] !== $r['password2']) {
			$f3->error(400, 'Passwords do not match!');
			return;
		}

		// create user
		try {
			$user = ModelsUser::createUser($r['fname'], $r['lname'], $r['email'], $r['password'], new DateTimeZone($r['tz']), [UserRole::getById(UserRole::ROLE_USER)]);
			$user->sendVerificationEmail();
		} catch (DuplicateIdentifier $e) {
			// send confirmation email for existing account
			Mailer::sendTemplate('signup-existing',
				[new EmailRecipient($r['email'], $r['fname'].' '.$r['lname'])],
				'Existing Account',
				[
					'fname' => $r['fname'],
					'base_url' => $f3->config['base_url'],
				]
			);
		} catch (MailerError $e) {
			$f3->error(500, 'There was an error while sending you a confirmation email. Please try again later. If the issue persists, please let us know via our Contact page.');
		}

		$this->jsonSuccess();
	}

	public function emailConfirmPage(Base $f3, array $args) {
		$title = "Whoops!";
		$message = "We're not sure what happened with that link, but we don't recognize it. If you need assistance, please reach us via our <a href=\"/contact\">Contact page</a>.";

		try {
			$claims = Helpers::decodeJwt($f3->REQUEST['code']);

			$user = ModelsUser::getByEmail($claims['email'])->verifyAccount();

			$title = 'You\'re all set!';
			$message = 'Welcome, '.$user->first_name.'! <a href="/login">Go to the Log In page</a> to log in with your new account.';
		} catch (Throwable $e) {
			// error message would already be set by this point, but we don't want un-friendly errors for our users
		}

		$this->inlinePageRender('<h1 class="text-center mt-5">'.$title.'</h1><p class="text-center mt-4">'.$message.'</p>', 'Sign Up');
	}

	public function sendVerificationEmail(Base $f3, array $args) {
		$f3->user->sendVerificationEmail();
		$this->jsonSuccess();
	}
}