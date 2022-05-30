<?php
namespace Models;

use DB\Cortex;
use DB\CortexCollection;
use DB\SQL\Schema;
use Exceptions\ObjectNotDefined;

/**
 * @property-read string $name
 * @property-read bool $enabled
 */
class UserRole extends Cortex {
	const ROLE_ANONYMOUS = 0;
	const ROLE_ROOT_ADMIN = 1;
	const ROLE_USER = 2;

	const LIST_ALL = [
		self::ROLE_ANONYMOUS,
		self::ROLE_ROOT_ADMIN,
		self::ROLE_USER,
	];

	const LIST_AUTHENTICATED = [
		self::ROLE_ROOT_ADMIN,
		self::ROLE_USER,
	];

	protected $db = 'DB';
	protected $table = 'user_roles';
	protected $fieldConf = [
		'name' => [
			'type' => Schema::DT_VARCHAR128,
		],
		'enabled' => [
			'type' => Schema::DT_BOOL,
			'default' => true,
		],
		'users' => [
			'has-many' => [
				User::class,
				'roles',
				'user_roles_map',
				'relField' => 'role_id',
			],
		],
	];

	/**
	 * Fetches the UserRole object with the given ID
	 *
	 * @param int $role_id The ID
	 * @return self The UserRole
	 */
	public static function getById(int $role_id): self {
		$self = new self();
		$self->load(['id = ?', $role_id]);
		if ($self->dry()) {
			throw new ObjectNotDefined("Unknown role");
		}
		return $self;
	}

	/**
	 * Converts a list of roles to a list of their respective IDs (generally used for comparisons)
	 *
	 * @param self[]|CortexCollection|null $roles The list of roles
	 * @param bool $null_as_anonymous If `$roles` is null, assume the respective user is anonymous
	 * @return int[] The list of IDs
	 */
	public static function toIds($roles, bool $null_as_anonymous = true): array {
		// special case for anonymous
		if (($null_as_anonymous && $roles === null) || (is_array($roles) && count($roles) === 1 && $roles[0] === 0)) {
			return [0];
		}
		return array_map(function($r) {return $r->_id;}, (array)$roles);
	}
}