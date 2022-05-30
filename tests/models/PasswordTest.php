<?php
namespace Tests\Models;

use Exceptions\InvalidValue;
use Models\Password;
use Tests\TestBase;

class PasswordTest extends TestBase {
	public function testPasswordMeetReqs() {
		$this->disable_truncation = true;

		$this->expect(Password::passwordMeetReqs('asdf') === false);
		$this->expect(Password::passwordMeetReqs('asdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdfasdf') === false);
		$this->expect(Password::passwordMeetReqs('ASDFASDF') === false);
		$this->expect(Password::passwordMeetReqs('AsDfAsDf') === false);
		$this->expect(Password::passwordMeetReqs('AsDfAsDf1') === false);
		$this->expect(Password::passwordMeetReqs('AsDfAsDf1!') === true);
	}

	public function testGeneratePasswordHash() {
		$this->disable_truncation = true;

		$result = Password::generatePasswordHash('Password1!');
		$this->expect(password_verify('Password1!', $result) === true);
	}

	public function testGenerateRandomPlaintextPassword() {
		$this->disable_truncation = true;

		$this->expect(strlen(Password::generateRandomPlaintextPassword(10, false)) === 10);
		$this->expect(strlen(Password::generateRandomPlaintextPassword(30, false)) === 30);
		$this->expect(strlen(Password::generateRandomPlaintextPassword(70, false)) === 70);
		$this->expect(strlen(Password::generateRandomPlaintextPassword(1, false)) === 1);

		try {
			Password::generateRandomPlaintextPassword(1, true);
			$this->fail('should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains("Invalid length to meet minimum password requirements", $e->getMessage());
		}
	}
}