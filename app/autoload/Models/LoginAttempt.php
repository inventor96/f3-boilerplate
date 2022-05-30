<?php
namespace Models;

use DateTime;
use DB\Cortex;
use DB\SQL\Schema;
use Traits\CreatedDt;

/**
 * @property string $username
 * @property bool $succeeded
 * @property string $ip
 * @property string $provider
 * @property DateTime $created_dt
 */
class LoginAttempt extends Cortex {
	use CreatedDt;

	/**
	 * The user used the login form on this site
	 */
	public const P_FORM = 'form';

	protected $db = 'DB';
	protected $table = 'login_attempts';
	protected $fieldConf = [
		'username' => [
			'type' => Schema::DT_VARCHAR128,
		],
		'succeeded' => [
			'type' => Schema::DT_BOOL,
		],
		'ip' => [
			'type' => Schema::DT_VARCHAR128,
		],
		'provider' => [
			'type' => Schema::DT_VARCHAR128,
		],
		'created_dt' => [
			'type' => Schema::DT_DATETIME,
			'default' => Schema::DF_CURRENT_TIMESTAMP,
		],
	];

	/**
	 * Records a new login attempt
	 *
	 * @param string $username The username used to log in
	 * @param boolean $succeeded Whether the attempt was successful
	 * @param string $ip The IP address of the client trying to log in
	 * @param string $provider The method of login (see the P_* constants)
	 * @param DateTime|null $created_dt The time of the attempt (leave null for the current time)
	 * @return self The newly-created record
	 */
	public static function record(string $username, bool $succeeded, string $ip, string $provider, ?DateTime $created_dt = null): self {
		$self = new self();
		$self->username = $username;
		$self->succeeded = $succeeded;
		$self->ip = $ip;
		$self->provider = $provider;
		if ($created_dt !== null) {
			$self->created_dt = $created_dt;
		}
		$self->save();
		return $self;
	}
}