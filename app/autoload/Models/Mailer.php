<?php
namespace Models;

use Audit;
use Base;
use Exceptions\InvalidValue;
use Exceptions\MailerError;
use Exceptions\ObjectNotDefined;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Template;

class Mailer {
	public const MAILER_SETUP_ERROR = 1;
	public const MAILER_SEND_ERROR = 2;

	/**
	 * Setup the PHPMailer object with our default settings.
	 *
	 * @param bool $catch If true, indicates that we will catch our own exceptions.
	 * @return PHPMailer The PHPMailer object.
	 * @throws MailerError
	 */
	public static function createMailer(bool $catch = true): PHPMailer {
		// get creds
		$f3 = Base::instance();
		$creds = Creds::instance()->get('email');
		$config = $f3->config['email'];

		// setup basics
		$mailer = null;
		try {
			$mailer = new PHPMailer($catch);
			$mailer->isSMTP();
			$mailer->Host       = $config['server'];
			$mailer->SMTPAuth   = true;
			$mailer->Username   = $creds['username'];
			$mailer->Password   = $creds['password'];
			$mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
			$mailer->Port       = $config['port'];
		} catch (Exception $e) {
			throw new MailerError($e->getMessage(), self::MAILER_SETUP_ERROR, $e, $mailer);
		}

		// set default 'from'
		$mailer->setFrom($config['noreply_email'], $config['noreply_name']);

		return $mailer;
	}

	/**
	 * Send a simple email
	 *
	 * @param EmailRecipient[] $to The list of addresses to send this email to. Can be single or double dimension array. The second array element, if present, is the display name.
	 * @param string $subject The subject line of the email to be sent.
	 * @param string $text_message The plain-text version of the message.
	 * @param string $html_message The HTML-formatted message to send. If omitted, the nl2br() version of $text_message will be used.
	 * @param string $from_addr The address the email will come from. Defaults to the "no-reply" email in the config.
	 * @param string $from_name The display name of the sender. Defaults to the "no-reply" email in the config.
	 * @param array $reply_to The list of addresses that should be in the reply-to header. Can be single or double dimension array. The second param is the display name, if present.
	 * @return bool The result of sending the email.
	 */
	public static function send(array $to, string $subject, string $text_message, ?string $html_message = null, ?string $from_addr = null, ?string $from_name = null, array $reply_to = []): bool {
		// require message
		if (empty($text_message) && empty($html_message)) {
			throw new ObjectNotDefined("No message content");
		}

		// setup
		$mailer = self::createMailer();

		try {
			// basics
			$mailer->setFrom($from_addr ?? $mailer->From, $from_name ?? $mailer->FromName);
			$mailer->isHTML(true);
			$mailer->Subject = $subject;
			$mailer->Body    = $html_message ?? nl2br($text_message);
			$mailer->AltBody = empty($text_message) ? preg_replace('/<br\s*\/?>/i', "\n", $html_message) : $text_message;

			// recipients
			foreach ($to as $recipient) {
				$mailer->addAddress($recipient->email, $recipient->name);
			}

			// reply-to
			foreach ($reply_to as $recipient) {
				$mailer->addReplyTo($recipient->email, $recipient->name);
			}

			// fire in the hole!
			return $mailer->send();
		} catch (Exception $e) {
			throw new MailerError($e->getMessage(), self::MAILER_SEND_ERROR, $e, $mailer);
		}
	}

	/**
	 * Sends an email based on a template provided.
	 *
	 * @param string $template_name The name of the template as found in `app/views/emails/`.
	 * @param EmailRecipient[] $to The list of addresses to send this email to. Can be single or double dimension array. The second array element, if present, is the display name.
	 * @param string $subject The subject line of the email to be sent.
	 * @param array $params An associative array containing the params used in the template.
	 * @param string $from_addr The address the email will come from. Defaults to the "no-reply" email in the config.
	 * @param string $from_name The display name of the sender. Defaults to the "no-reply" email in the config.
	 * @param array $reply_to The list of addresses that should be in the reply-to header. Can be single or double dimension array. The second param is the display name, if present.
	 * @return bool The result of sending the email.
	 */
	public static function sendTemplate(string $template_name, array $to, string $subject, array $params = [], ?string $from_addr = null, ?string $from_name = null, array $reply_to = []): bool {
		$f3 = Base::instance();

		// check for templates
		$txt_exists = file_exists($f3->UI.'emails/'.$template_name.'.txt');
		$html_exists = file_exists($f3->UI.'emails/'.$template_name.'.html');

		if (!$txt_exists && !$html_exists) {
			throw new ObjectNotDefined("The '{$template_name}' template does not exist.");
		}

		// process the template(s)
		$t = Template::instance();
		$text = '';
		$html = '';
		if ($txt_exists) {
			$text = $t->render('emails/'.$template_name.'.txt', 'text/plain', $params);
		}
		if ($html_exists) {
			$html = $t->render('emails/'.$template_name.'.html', 'text/html', $params);
		}

		// send it off
		return self::send($to, $subject, $text, $html, $from_addr, $from_name, $reply_to);
	}

	/**
	 * Checks an email address for validity, optionally checking DNS records to make sure the email domain exists.
	 *
	 * @param string $email The address the check.
	 * @param boolean $throw_exception Throw an exception on an invalid input.
	 * @param boolean $skip_dns Don't check the DNS records.
	 * @return boolean Whether the check passed or not.
	 */
	public static function checkEmailValidity(string $email, bool $throw_exception = true, bool $skip_dns = false): bool {
		$audit = Audit::instance();

		// check format
		if (!$audit->email($email, false)) {
			if ($throw_exception) {
				throw new InvalidValue("The supplied email address is invalid.");
			}
			return false;
		}

		// check domain
		if (!$skip_dns && !$audit->email($email, true)) {
			$domain = explode('@', $email, 2)[1];
			if ($throw_exception) {
				throw new InvalidValue("We can't find the email host for '{$domain}'. Are you sure you typed it correctly?");
			}
			return false;
		}

		return true;
	}
}