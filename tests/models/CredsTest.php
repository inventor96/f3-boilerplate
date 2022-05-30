<?php
namespace Tests\Models;

use Exceptions\ObjectNotDefined;
use Models\Creds;
use Tests\TestBase;

class CredsTest extends TestBase {
	/** @var Creds The instance of the Creds model */
	private $Creds = null;

	public function preClass() {
		$this->Creds = new Creds();
	}

	public function testGet() {
		$this->disable_truncation = true;

		try {
			$this->Creds->get('does not exist');
			$this->fail('Should have failed');
		} catch (ObjectNotDefined $e) {
			$this->expectStringContains("doesn't exist", $e->getMessage());
		}
		try {
			$this->Creds->get('testing1.nothing');
			$this->fail('Should have failed');
		} catch (ObjectNotDefined $e) {
			$this->expectStringContains("doesn't exist", $e->getMessage());
		}
		try {
			$this->Creds->get('testing2.nothing');
			$this->fail('Should have failed');
		} catch (ObjectNotDefined $e) {
			$this->expectStringContains("doesn't exist", $e->getMessage());
		}

		$this->expect($this->Creds->get('testing1') === 'hi');
		$this->expect($this->Creds->get('testing2') === [ 'one' => 'sup', 'two' => 'hola' ]);
		$this->expect($this->Creds->get('testing2.one') === 'sup');
		$this->expect($this->Creds->get('testing2.two') === 'hola');
	}
}