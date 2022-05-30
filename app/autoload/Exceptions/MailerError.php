<?php
namespace Exceptions;

use Exception;
use PHPMailer\PHPMailer\PHPMailer;

class MailerError extends Exception {
	private $mailer_obj;

	public function __construct(string $message, int $code = 0, Exception $previous, PHPMailer $mailer) {
		$this->mailer_obj = $mailer;
		parent::__construct($message, $code, $previous);
	}

	public function getMailer(): PHPMailer {
		return $this->mailer_obj;
	}
}