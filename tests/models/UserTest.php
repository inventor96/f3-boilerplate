<?php
namespace Tests\Models;

use DateTimeZone;
use Exceptions\InvalidValue;
use Exceptions\MissingField;
use Exceptions\ObjectNotDefined;
use Models\Password;
use Models\User;
use Models\UserRole;
use Tests\TestBase;

class UserTest extends TestBase {
	public static function getTestUser(): User {
		$u = new User();
		$u->first_name = 'test';
		$u->last_name = 'test';
		$u->password = 'test';
		$u->timezone = new DateTimeZone('UTC');
		$u->email = 'nowhere@f3_boilerplate.net';
		$u->save();
		\Base::instance()->tz = $u->tz;
		return $u;
	}

	public function testEmailInUse() {
		$this->expect(false === User::emailInUse('test@email.com'));
		$u = self::getTestUser();
		$this->expect(true === User::emailInUse($u->email));
	}

	public function testCreateUser() {
		$tz = new DateTimeZone('UTC');
		try {
			User::createUser('', '', '', '', $tz, []);
			$this->fail('should have failed');
		} catch (MissingField $e) {
			$this->expectStringContains('fname', $e->getMessage());
		}
		try {
			User::createUser('fname', '', '', '', $tz, []);
			$this->fail('should have failed');
		} catch (MissingField $e) {
			$this->expectStringContains('lname', $e->getMessage());
		}
		try {
			User::createUser('fname', 'lname', '', '', $tz, []);
			$this->fail('should have failed');
		} catch (MissingField $e) {
			$this->expectStringContains('email', $e->getMessage());
		}
		try {
			User::createUser('fname', 'lname', 'email', '', $tz, []);
			$this->fail('should have failed');
		} catch (MissingField $e) {
			$this->expectStringContains('password', $e->getMessage());
		}
		try {
			User::createUser('fname', 'lname', 'email', 'password', $tz, []);
			$this->fail('should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains('enter a valid email address', $e->getMessage());
		}
		try {
			User::createUser('fname', 'lname', 'email@idonot.exist', 'password', $tz, []);
			$this->fail('should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains("can't find the email host", $e->getMessage());
		}
		try {
			User::createUser('fname', 'lname', 'email@idonot.exist', 'password', $tz, ['not a role']);
			$this->fail('should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains("must be of the UserRole type", $e->getMessage());
		}

		$u = User::createUser('fname', 'lname', 'webmaster1@php.net', Password::generateRandomPlaintextPassword(), $tz, []);
		$this->expect(false === $u->verified);
		$this->expect(true === $u->enabled);
		$this->expect(0 === count($this->f3->DB->exec("SELECT * FROM user_roles_map")));
		$this->expect(null === $u->roles);

		$u = User::createUser('fname', 'lname', 'webmaster2@php.net', Password::generateRandomPlaintextPassword(), $tz, [UserRole::getById(UserRole::ROLE_ROOT_ADMIN)]);
		$this->expect(false === $u->verified);
		$this->expect(true === $u->enabled);
		$this->expect(1 === count($this->f3->DB->exec("SELECT * FROM user_roles_map")));
		$this->expect(1 === count($u->roles));
		$this->expect(is_a($u->roles[0], UserRole::class));
		$this->expect($u->roles[0]->_id === UserRole::ROLE_ROOT_ADMIN);
	}

	public function testSendVerificationEmail() {
		$this->disable_truncation = true;
		$u = new User();
		try {
			$u->sendVerificationEmail();
			$this->fail('should have failed');
		} catch (ObjectNotDefined $e) {
			$this->expectStringContains("user that doesn't exist", $e->getMessage());
		}
	}

	public function testVerifyAccount() {
		$u = new User();
		try {
			$u->verifyAccount();
			$this->fail('should have failed');
		} catch (ObjectNotDefined $e) {
			$this->expectStringContains("user that doesn't exist", $e->getMessage());
		}

		$u = self::getTestUser();
		$this->expect(false === $u->verified);
		$this->expect(false === $u->changed());
		$u->verifyAccount();
		$this->expect(true === $u->verified);
		$this->expect(false === $u->changed());
	}

	public function testCheckPwd() {
		$pwd = Password::generateRandomPlaintextPassword();
		$u = User::createUser('fname', 'lname', 'webmaster@php.net', $pwd, new DateTimeZone('UTC'), []);
		$this->expect(false === $u->checkPwd('some other password'));
		$this->expect(true === $u->checkPwd($pwd));
	}

	public function testRoles() {
		$u = self::getTestUser();

		try {
			$u->hasRole(new UserRole());
			$this->fail('should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains('Invalid role', $e->getMessage());
		}

		$r_ra = UserRole::getById(UserRole::ROLE_ROOT_ADMIN);
		$r_u = UserRole::getById(UserRole::ROLE_USER);

		$u->addRole($r_ra);
		$this->expect($u->hasRole($r_ra) === true);
		$this->expect($u->hasRole($r_u) === false);

		$u->addRole($r_u);
		$this->expect($u->hasRole($r_ra) === true);
		$this->expect($u->hasRole($r_u) === true);

		$u->removeRole($r_ra);
		$this->expect($u->hasRole($r_ra) === false);
		$this->expect($u->hasRole($r_u) === true);

		$u->removeRole($r_u);
		$this->expect($u->hasRole($r_ra) === false);
		$this->expect($u->hasRole($r_u) === false);
	}

	public function testUpdatePassword() {
		$u = new User();

		try {
			$u->updatePassword('');
			$this->fail('should have failed');
		} catch (ObjectNotDefined $e) {
			$this->expectStringContains('non-existent user', $e->getMessage());
		}

		$u = self::getTestUser();

		try {
			$u->updatePassword('');
			$this->fail('should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains('does not meet minimum requirements', $e->getMessage());
		}

		$old = $u->password;
		$u->updatePassword('MyTestPassword123!');
		$this->expect($old !== $u->password);
	}
}