<?php
namespace Models;

use Exceptions\InvalidValue;
use Exceptions\UnknownError;

class Password {
	/**
	 * Checks to see if password meets strength requirements
	 *
	 * @param string $plain_text_password The password to check
	 * @return bool Whether or not the requirements have been met
	 */
	public static function passwordMeetReqs(string $plain_text_password): bool {
		return strlen($plain_text_password) > 7
			&& strlen($plain_text_password) < 65
			&& preg_match('/[A-Z]/', $plain_text_password)
			&& preg_match('/[a-z]/', $plain_text_password)
			&& preg_match('/\d/', $plain_text_password)
			&& preg_match('/[^A-Za-z\d]/', $plain_text_password);
	}

	/**
	 * Generates the hash and salt for a given password
	 *
	 * @param string $plain_text_password The password in plain text form
	 * @return string The resulting hash of the password
	 * @throws InvalidValue
	 * @throws UnknownError
	 */
	public static function generatePasswordHash(string $plain_text_password): string {
		// check password requirements
		if (!self::passwordMeetReqs($plain_text_password)) {
			throw new InvalidValue('The password does not meet our minimum strength requirements.');
		}

		// generate hash
		$hash = password_hash($plain_text_password, PASSWORD_DEFAULT, ['cost' => 12]);

		// check if it was successful
		if ($hash) {
			return $hash;
		} else {
			throw new UnknownError('There was an error while processing the password.');
		}
	}

	/**
	 * Creates a random plain text password of the given length
	 *
	 * @param int $length The desired length of the password
	 * @param bool $check_requirements Set to true if the generated password must meet our minimum requirements
	 * @return string The generated password
	 * @throws InvalidValue
	 */
	public static function generateRandomPlaintextPassword(int $length = 64, bool $check_requirements = true): string {
		if ($length < 1) {
			throw new InvalidValue("Password length needs to be at least 1");
		}

		// generate password
		$attempts = 0;
		do {
			$password = '';
			do {
				$password .= chr(mt_rand(32, 255));
			} while (strlen($password) < $length);
		} while ($check_requirements && !self::passwordMeetReqs($password) && ++$attempts < 20);

		// ensure requirements are met
		if ($check_requirements && !self::passwordMeetReqs($password)) {
			// most likely the error, but it could be a logic/randomization error, too
			throw new InvalidValue("Invalid length to meet minimum password requirements");
		}

		return $password;
	}
}