<?php
namespace Controllers;

use Base;

class Crons extends ControllerBase {
	public function exampleCron(Base $f3, array $args) {
		echo "The example cron was run.";
	}
}