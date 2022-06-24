<?php
namespace Models;

use Base;
use ReCaptcha\ReCaptcha;

class Captcha {
	/**
	 * Verifies a recaptcha from a form input
	 *
	 * @param string $input The input from the form
	 * @return bool Indicates whether it passes
	 */
	public static function check(string $input): bool {
		$f3 = Base::instance();

		// don't verify on dev
		if ($f3->get('is_dev')) {
			return true;
		} else {
			$recaptcha = new ReCaptcha(Creds::instance()->get('recaptcha.secret_key'));
			$resp = $recaptcha->setExpectedHostname($f3->HOST)->verify($input);
			return $resp->isSuccess();
		}
	}
}