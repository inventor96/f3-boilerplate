<?php
namespace Exceptions;

use Exception;

class MissingField extends Exception {
	private $field;

	public function __construct(string $field, string $message = "", int $code = 400, Exception $previous = null) {
		$this->field = $field;
		parent::__construct(($message ?: "Missing field: {$field}"), $code, $previous);
	}

	public function getField(): string {
		return $this->field;
	}
}