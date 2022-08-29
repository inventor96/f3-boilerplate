<?php
namespace Models;

class EmailRecipient {
	/** @var string $email The recipient's email address */
	public string $email;

	/** @var string $name The recipient's name */
	public string $name;

	/**
	 * Creates a new email recipient.
	 *
	 * @param string $email The recipient's email address.
	 * @param string $name The recipient's name.
	 */
	public function __construct(string $email, string $name = '') {
		$this->email = $email;
		$this->name = $name;
	}
}