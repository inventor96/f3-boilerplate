<?php
namespace Models;

use Base;
use DateTimeZone;
use DB\Cortex;
use DB\SQL\Schema;
use Exceptions\DuplicateIdentifier;
use Exceptions\InvalidValue;
use Exceptions\MissingField;
use Exceptions\ObjectNotDefined;
use Throwable;
use Traits\CreatedDt;

/**
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property bool $verified
 * @property string $password
 * @property DateTimeZone|null $timezone
 * @property DateTime $created_dt
 * @property bool $enabled
 * @property UserRole[]|null $roles
 */
class User extends Cortex {
	use CreatedDt;

	protected $db = 'DB';
	protected $table = 'users';
	protected $fieldConf = [
		'first_name' => [
			'type' => Schema::DT_VARCHAR128,
		],
		'last_name' => [
			'type' => Schema::DT_VARCHAR128,
		],
		'email' => [
			'type' => Schema::DT_VARCHAR128,
			'index' => true,
			'unique' => true,
		],
		'verified' => [
			'type' => Schema::DT_BOOL,
			'default' => false,
		],
		'password' => [
			'type' => Schema::DT_VARCHAR256,
		],
		'timezone' => [
			'type' => Schema::DT_VARCHAR128,
		],
		'created_dt' => [
			'type' => Schema::DT_DATETIME,
			'default' => Schema::DF_CURRENT_TIMESTAMP,
		],
		'enabled' => [
			'type' => Schema::DT_BOOL,
			'default' => true,
		],
		'roles' => [
			'has-many' => [
				UserRole::class,
				'users',
				'user_roles_map',
				'relField' => 'user_id',
			],
		],
	];

	protected function set_timezone(DateTimeZone $tz): string {
		return $tz->getName();
	}

	protected function get_timezone(?string $tz_str): ?DateTimeZone {
		$tz = null;
		if ($tz_str !== null) {
			try {
				$tz = new DateTimeZone($tz_str);
			} catch (Throwable $e) {}
		}
		return $tz;
	}

	/**
	 * Gets the user by their email address
	 *
	 * @param string $email The email address to look up
	 * @return self The user
	 */
	public static function getByEmail(string $email): self {
		$user = new self();
		$user->load(['email = ?', $email]);
		return $user;
	}

	/**
	 * Gets a user by their ID
	 *
	 * @param int $user_id The ID of the user
	 * @return self The user
	 */
	public static function getByID(int $user_id): self {
		$user = new self();
		$user->load(['id = ?', $user_id]);
		return $user;
	}

	/**
	 * Gets a list of users with the specified role
	 *
	 * @param UserRole $role The role to query on
	 * @return self[] The list of users
	 */
	public static function getByRole(UserRole $role) {
		$self = new self();
		$self->has('roles', ['id = ?', $role->_id]);
		return $self->find();
	}

	/**
	 * Check if an email already exists in the system
	 *
	 * @param string $email The email address to check for
	 * @return bool Whether the email exists
	 */
	public static function emailInUse(string $email): bool {
		$test_user = self::getByEmail($email);
		return !$test_user->dry();
	}

	/**
	 * Creates a new user object
	 *
	 * @param string $fname First name
	 * @param string $lname Last name
	 * @param string $email Email address
	 * @param string $plain_text_password The password in plain text
	 * @param DateTimeZone $timezone The default PHP timezone for the user account
	 * @param UserRole[] $roles The initial roles being assigned to the user (see the `UserRole` model)
	 * @param bool $sso Set to true when creating a use from an SSO login
	 * @throws MissingField
	 */
	public static function createUser(string $fname, string $lname, string $email, string $plain_text_password, DateTimeZone $timezone, array $roles, bool $sso = false): self {
		// check required fields
		if (!$fname) {
			throw new MissingField('fname');
		}
		if (!$lname) {
			throw new MissingField('lname');
		}
		if (!$email) {
			throw new MissingField('email');
		}
		if (!$sso && !$plain_text_password) {
			throw new MissingField('plain_text_password');
		}
		if (!$timezone) {
			throw new MissingField('timezone');
		}
		if (count(array_filter($roles, function($r) {return !is_a($r, UserRole::class);})) > 0) {
			throw new InvalidValue("All elements in the roles array must be of the UserRole type");
		}

		// check email validity, letting the exceptions filter up to the user
		Mailer::checkEmailValidity($email, true);

		// check a user doesn't already exist
		if (self::emailInUse($email)) {
			throw new DuplicateIdentifier("The email address is already used by an existing account");
		}

		// generate password stuff
		if (!$sso) {
			$pw_hash = Password::generatePasswordHash($plain_text_password);
		}

		// setup user
		$user = new self();
		$user->first_name = $fname;
		$user->last_name = $lname;
		$user->email = $email;
		$user->password = $pw_hash;
		$user->timezone = $timezone;
		$user->verified = false;
		$user->enabled = true;
		$user->roles = $roles;
		$user->save();
		$user->load(['id = ?', $user->_id]);

		return $user;
	}

	/**
	 * Sends a verification email to the user's email address
	 */
	public function sendVerificationEmail(): void {
		// verify we exist
		if ($this->dry()) {
			throw new ObjectNotDefined("We can't verify a user that doesn't exist.");
		}

		$f3 = Base::instance();

		// generate account confirmation link
		$jwt = Helpers::encodeJwt(['email' => $this->email, 'exp' => strtotime('+24 hours')]);
		$confirm_link = "{$f3->config['base_url']}email-confirm?code={$jwt}";

		// send confirmation email
		Mailer::sendTemplate('signup-confirm',
			[$this->email, $this->first_name.' '.$this->last_name],
			'Sign Up Confirmation',
			[
				'fname' => $this->first_name,
				'confirm_link' => $confirm_link,
			]
		);
	}

	/**
	 * Sets a user account as verified
	 *
	 * @return User The user object of the account just verified
	 * @throws ObjectNotDefined
	 */
	public function verifyAccount(): self {
		// verify we exist
		if ($this->dry()) {
			throw new ObjectNotDefined("We can't verify a user that doesn't exist.");
		}

		// set account as confirmed
		$this->verified = 1;
		$this->save();

		return $this;
	}

	/**
	 * Sends an email to the address on file with a link to reset the account's password
	 *
	 * @return void
	 */
	public function sendPwdResetEmail(): void {
		// can't send an email if the user doesn't exist
		if ($this->dry()) {
			throw new ObjectNotDefined("Can't send an email to a user that doesn't exist!");
		}

		$f3 = Base::instance();

		// generate link
		$jwt = Helpers::encodeJwt(['user_id' => $this->id, 'exp' => strtotime('+24 hours')]);
		$reset_link = "{$f3->config['base_url']}password-reset?code={$jwt}";

		// send password reset email
		Mailer::sendTemplate('pwd-reset',
			[$this->email, $this->first_name.' '.$this->last_name],
			'Password Rest', [
				'fname' => $this->first_name,
				'reset_link' => $reset_link,
			]
		);
	}

	/**
	 * Updates the password for a user after ensuring requirements are met
	 *
	 * @param string $password The new plaintext password
	 * @return void
	 */
	public function updatePassword(string $password): void {
		// must exist
		if ($this->dry()) {
			throw new ObjectNotDefined("Can't set a password for a non-existent user");
		}

		// check requirements
		if (!Password::passwordMeetReqs($password)) {
			throw new InvalidValue("Password does not meet minimum requirements");
		}

		// store new password
		$hash = Password::generatePasswordHash($password);
		$this->password = $hash;
		$this->save();
	}

	/**
	 * Checks if a password is correct for this user
	 *
	 * @param string $password The plain text password of the user
	 * @return bool Whether the given credentials are correct
	 */
	public function checkPwd(string $password): bool {
		// decided not to use F3's Auth::login() method because it's not safe against timing attacks to determine the presence of an account
		$db_pwd = $this->password ?: '';
		$correct = password_verify($password, $db_pwd);
		$correct = strlen($password) > 0 && $correct;
		$correct = strlen($db_pwd) > 0 && $correct;
		$correct = !$this->dry() && $correct;

		return $correct;
	}

	/**
	 * Adds a role to a user in they don't already have it
	 *
	 * @param UserRole $role The role to add
	 * @return self The updated user
	 */
	public function addRole(UserRole $role): self {
		if (!in_array($role->_id, UserRole::toIds($this->roles))) {
			if ($this->roles === null) {
				$this->roles = [$role];
			} else {
				$this->roles[] = $role;
			}
			$this->save();
			$this->load(['id = ?', $this->_id]);
		}
		return $this;
	}

	/**
	 * Removes a role from a user account if they have it
	 *
	 * @param UserRole $role The role to remove
	 * @return self The updated user
	 */
	public function removeRole(UserRole $role): self {
		if (in_array($role->_id, UserRole::toIds($this->roles, false))) {
			Base::instance()->DB->exec("DELETE FROM `user_roles_map` WHERE `user_id` = ? AND `role_id` = ?", [$this->_id, $role->_id]);
			$this->load(['id = ?', $this->_id]);
		}
		return $this;
	}

	/**
	 * Checks if a user has a particular role
	 *
	 * @param UserRole $role The role to check
	 * @return bool True if the user has the role
	 * @throws InvalidValue
	 */
	public function hasRole(UserRole $role): bool {
		if ($role->dry()) {
			throw new InvalidValue("Invalid role");
		} elseif ($this->dry()) {
			return count($this->roles ?? []) === 1 && $this->roles[0]->_id === UserRole::ROLE_ANONYMOUS;
		} else {
			return in_array($role->_id, UserRole::toIds($this->roles));
		}
	}

	/**
	 * Checks if a user has at least one from a list of roles
	 *
	 * @param UserRole[] $roles The roles to check
	 * @return bool True if the user has at least one role
	 * @throws InvalidValue
	 */
	public function hasRoles(array $roles): bool {
		if (count(array_filter($roles, function ($r) {return $r->dry();})) > 0) {
			throw new InvalidValue("One or more invalid roles");
		} elseif ($this->dry()) {
			return in_array(UserRole::ROLE_ANONYMOUS, UserRole::toIds($roles));
		} else {
			return count(array_intersect(UserRole::toIds($roles), UserRole::toIds($this->roles))) > 0;
		}
	}
}