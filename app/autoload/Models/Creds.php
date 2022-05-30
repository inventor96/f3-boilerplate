<?php
namespace Models;

use Exceptions\ObjectNotDefined;

class Creds extends \Prefab {
	private $cache = [];

	public function __construct() {
		$f3 = \Base::instance();

		// get creds with environment-specific overrides
		if (file_exists(CONFIG_DIR.'credentials.php')) {
			$this->cache = require(CONFIG_DIR.'credentials.php');
		}
		if (file_exists(CONFIG_DIR."credentials.{$f3->config['env']}.php")) {
			$this->cache = array_merge($this->cache, require(CONFIG_DIR."credentials.{$f3->config['env']}.php"));
		}
	}

	/**
	 * Gets a credential value
	 *
	 * @param string $path The path to the desired credential value (e.g. "jwt" or "db.password")
	 * @return mixed The credential value
	 * @throws ObjectNotDefined
	 */
	public function get(string $path) {
		$path_parts = explode('.', $path);

		// check cache
		$node = $this->cache;
		$found = true;
		foreach ($path_parts as $step) {
			if (isset($node[$step])) {
				$node = $node[$step];
			} else {
				$found = false;
				break;
			}
		}

		// return it if we found it
		if ($found) {
			return $node;
		} else {
			throw new ObjectNotDefined("Credential doesn't exist");
		}
	}
}