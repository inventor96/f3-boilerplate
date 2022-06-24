<?php
namespace Models;

use Base;
use Exceptions\InvalidPath;
use Exceptions\InvalidType;

class Resource {
	public const DEFAULT_CSS = [
		'bootstrap',
		'bi',
		'base',
	];

	public const DEFAULT_JS = [
		'jq',
		'bootstrap',
		'base',
	];

	/**
	 * Returns the safe type of resource from a client-provided string
	 *
	 * @param $type The type of resource
	 * @return string The safe form of the requested resource type
	 * @throws InvalidType
	 */
	public static function getSafeType(string $type): string {
		// close potential hacking attempts
		$safe_type = strtolower(preg_replace('/[^\w]+/', '', $type));
		if (!in_array($safe_type, ['js', 'css'])) { // whitelist
			throw new InvalidType("Disallowed resource type");
		}
		return $safe_type;
	}

	/**
	 * Determines whether the folder for the requested type of resource is valid
	 *
	 * @param string $type The type of resource being requested
	 * @return string The full path, if it exists
	 * @throws InvalidPath
	 */
	public static function getResourceDir(string $type): string {
		$safe_type = self::getSafeType($type);

		$path = PUB_DIR."{$safe_type}/";
		if (file_exists($path) && is_dir($path)) {
			return $path;
		} else {
			throw new InvalidPath("Resource type does not exist");
		}
	}

	/**
	 * Returns the list of appropriate files to use when providing a minified resource
	 *
	 * @param string $type The type of resource (js or css)
	 * @param array $files The files requested by the client (minus the extensions)
	 * @param bool $include_ext Whether or not the results should include the file extension
	 * @param bool $include_default Set to false to skip adding the default files
	 * @return array The list of OK'd files, plus any standard
	 */
	public static function getFileList(string $type, array $files, bool $include_ext = true, bool $include_default = true): array {
		$safe_type = self::getSafeType($type);
		$path = self::getResourceDir($type);

		// provide standard resources
		$ok_files = [];
		switch ($safe_type) {
			case 'css':
				$ok_files = $include_default ? self::DEFAULT_CSS : [];
				break;

			case 'js':
				$ok_files = $include_default ? self::DEFAULT_JS : [];

				// add validation defaults
				$location = array_search('validate', $files);
				if ($location !== false) {
					array_splice($files, $location + 1, 0, 'vdefault');
				}
				break;
		}
		if ($include_ext) {
			for ($i = 0; $i < count($ok_files); $i++) {
				$ok_files[$i] .= '.'.$safe_type;
			}
		}

		// expand filenames
		foreach ($files as $file) {
			$safe_name = trim(preg_replace('/([\.\/]+)/', '', $file));
			$fname = $safe_name.'.'.$safe_type;
			if ($safe_name && file_exists($path.$fname)) {
				$ok_files[] = $include_ext ? $fname : $safe_name;
			}
		}

		$ok_files = array_unique($ok_files);
		return $ok_files;
	}

	/**
	 * Gets the version string to include in a view to force a refresh of a resource when the version changes
	 *
	 * @param string $type The type of resource
	 * @param array $files The list of files that are being included
	 * @param bool $include_default Set to false to skip adding the default files
	 * @return string The compiled version string
	 */
	public static function getVersionString(string $type, array $files, bool $include_default = true): string {
		$safe_type = self::getSafeType($type);
		$safe_files = self::getFileList($type, $files, false, $include_default);

		// compile versions
		$versions = Base::instance()->versions[$safe_type];
		$version_array = [];
		foreach ($safe_files as $name) {
			$version_array[] = isset($versions[$name]) ? $versions[$name] : 0;
		}
		return implode('.', $version_array);
	}
}