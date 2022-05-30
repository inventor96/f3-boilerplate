<?php
namespace Tests\Controllers;

use Tests\TestBase;

class HomeTest extends TestBase {
	public function testPingResponse() {
		$this->disable_truncation = true;

		$this->expectGetRequestContains('pong', 'GET /ping');
	}
}