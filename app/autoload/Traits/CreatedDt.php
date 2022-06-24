<?php
namespace Traits;

use Base;
use DateTime;
use DateTimeZone;

trait CreatedDt {
	protected function get_created_dt(string $dt_val): DateTime {
		/** @var DateTimeZone */
		$tz = Base::instance()->srv_tz;
		return new DateTime($dt_val, $tz);
	}

	protected function set_created_dt(DateTime $dt): string {
		/** @var DateTimeZone */
		$tz = Base::instance()->srv_tz;
		return $dt->setTimezone($tz)->format(TIME_FORMAT_SQL);
	}
}