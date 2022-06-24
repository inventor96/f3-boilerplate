<?php
namespace Models;

use Base;
use Exceptions\InvalidValue;
use Exceptions\MissingField;
use Firebase\JWT\JWT;

class Helpers {
	/**
	 * Returns an obfuscated string representing the JSON error.
	 *
	 * @param int $err The error returned from json_last_error()
	 * @return string The textual version of the input error
	 */
	public static function jsonError(int $err): string {
		switch ($err) {
			case JSON_ERROR_NONE:
				return "Nothing is wrong. Not sure why you got this message.";
				break;

			case JSON_ERROR_DEPTH:
				return "We received gobbily-gook. (5)";
				break;

			case JSON_ERROR_STATE_MISMATCH:
				return "We received gobbily-gook. (3)";
				break;

			case JSON_ERROR_CTRL_CHAR:
				return "We received gobbily-gook. (7)";
				break;

			case JSON_ERROR_SYNTAX:
				return "We received gobbily-gook. (6)";
				break;

			case JSON_ERROR_UTF8:
				return "We received gobbily-gook. (1)";
				break;

			case JSON_ERROR_RECURSION:
				return "We received gobbily-gook. (4)";
				break;

			case JSON_ERROR_INF_OR_NAN:
				return "We received gobbily-gook. (2)";
				break;

			case JSON_ERROR_UNSUPPORTED_TYPE:
				return "We received gobbily-gook. (9)";
				break;

			case JSON_ERROR_INVALID_PROPERTY_NAME:
				return "We received gobbily-gook. (8)";
				break;

			case JSON_ERROR_UTF16:
				return "We received gobbily-gook. (16)";
				break;

			default:
				return "We received gobbily-gook. (0)";
				break;
		}
	}

	/**
	 * Create a JWT string using our internal parameters
	 *
	 * @param array $payload The data to include and sign within the JWT
	 * @return string The encoded and signed JWT
	 */
	public static function encodeJwt(array $payload): string {
		$f3 = Base::instance();
		$key = Creds::instance()->get('jwt');
		$payload += [
			'iss' => $f3->config['base_url'],
			'aud' => $f3->config['base_url'],
			'iat' => time(),
			'nbf' => time(),
			'exp' => time() + (60 * 60 * 6),
		];
		return JWT::encode($payload, $key, 'HS512');
	}

	/**
	 * Decode and verify a JWT based on our internal parameters
	 *
	 * @param string $jwt The input JWT to check
	 * @return array The payload from the JWT
	 */
	public static function decodeJwt(string $jwt): array {
		$key = Creds::instance()->get('jwt');
		JWT::$leeway = 60;
		$decoded = JWT::decode($jwt, $key, ['HS512']);
		return (array)$decoded;
	}

	/**
	 * Trims a string that overflows the number of characters and adds an ellipsis at the end
	 *
	 * @param string $str The string to trim
	 * @param int $chars The max length of the resulting string. Negative numbers count backward from the end of the string.
	 * @return string The trimmed string
	 * @throws InvalidValue
	 */
	public static function ellipsis(string $str, int $chars = 25): string {
		if ($chars < 4) {
			throw new InvalidValue('Character count must be higher than 3');
		}

		// check if we even need to trim this
		if (mb_strlen($str) > $chars) {
			return mb_substr($str, 0, $chars - 3).'...';
		} else {
			return $str;
		}
	}

	/**
	 * Gets the list of a type from the database
	 *
	 * @param string $type The desired type
	 * @param bool $id_index Use the ID as the index in the returned array
	 * @return array The list of types
	 * @throws InvalidValue
	 */
	public static function getType(string $type, int $cache_seconds = 0, bool $id_index = false): array {
		$query = '';

		switch ($type) {
			case 'user_roles':
				$query = "SELECT `id`, `name` FROM `{$type}` ORDER BY `name`";
				break;

			default:
				throw new InvalidValue("Unknown type");
				break;
		}

		$results = Base::instance()->DB->exec($query, null, $cache_seconds);
		return $id_index ? array_column($results, 'name', 'id') : $results;
	}

	/**
	 * Creates an array of the page links that should be displayed
	 *
	 * @param array $pagination_result The result from calling paginate() on a Mapper object
	 * @param int $range The range of immediately-accessible page links
	 * @return array The set of page links
	 * @throws MissingField
	 * @throws InvalidValue
	 */
	public static function getPagerSet(array $pagination_result, int $range = 5): array {
		// require fields and types
		foreach (['count', 'pos'] as $field) {
			if (!isset($pagination_result[$field])) {
				throw new MissingField($field);
			}
			if (!is_numeric($pagination_result[$field]) || $pagination_result[$field] < 0) {
				throw new InvalidValue($field.' needs to be a number greater than or equal to 0');
			}
		}
		if ($range < 0 || $range % 2 === 0) {
			throw new InvalidValue("Range must be an odd number greater than 0");
		}

		// set first page
		$pages = [
			[
				'type' => 'first',
				'disabled' => $pagination_result['pos'] === 0,
				'value' => 0,
			]
		];

		// add other pages
		$start = $pagination_result['pos'] - (($range - 1) / 2);
		$end = $start + $range;
		for ($i = $start; $i < $end; $i++) {
			// make sure the index is in range
			if ($i >= 0 && $i < $pagination_result['count']) {
				// add the page
				$pages[] = [
					'type' => $i === $pagination_result['pos'] ? 'active' : 'link',
					'disabled' => false,
					'value' => $i,
				];
			}
		}

		// keep at least one page visible
		if (count($pages) === 1) {
			$pages[] = [
				'type' => 'active',
				'disabled' => false,
				'value' => 0,
			];
		}

		// set last page
		$last_val = $pagination_result['count'] > 0 ? intval($pagination_result['count'] - 1) : 0;
		$pages[] = [
			'type' => 'last',
			'disabled' => $pagination_result['pos'] === $last_val,
			'value' => $last_val,
		];

		return $pages;
	}
}