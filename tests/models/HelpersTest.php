<?php
namespace Tests\Models;

use Exceptions\InvalidValue;
use Exceptions\MissingField;
use Models\Helpers;
use Tests\TestBase;

class HelpersTest extends TestBase {
	public function testJsonError() {
		$this->disable_truncation = true;

		$this->expectStringContains('Nothing is wrong', Helpers::jsonError(JSON_ERROR_NONE));
		$this->expectStringContains("We received gobbily-gook. (5)", Helpers::jsonError(JSON_ERROR_DEPTH));
	}

	public function testEllipsis() {
		$this->disable_truncation = true;

		try {
			Helpers::ellipsis('', -1);
			$this->fail('Should have thrown an exception');
		} catch (InvalidValue $e) {
			$this->expectStringContains('count must be higher than 3', $e->getMessage());
		}
		try {
			Helpers::ellipsis('', 0);
			$this->fail('Should have thrown an exception');
		} catch (InvalidValue $e) {
			$this->expectStringContains('count must be higher than 3', $e->getMessage());
		}
		try {
			Helpers::ellipsis('', 3);
			$this->fail('Should have thrown an exception');
		} catch (InvalidValue $e) {
			$this->expectStringContains('count must be higher than 3', $e->getMessage());
		}

		$this->expectEqual('hi', Helpers::ellipsis('hi'));
		$this->expectEqual('kinda longer', Helpers::ellipsis('kinda longer'));
		$this->expectEqual('lllllllllllllllllllooo...', Helpers::ellipsis('llllllllllllllllllloooooooooooooonnnnnnnnnnnnnnngggggggggggggggeeeeeeeeeeeeeerrrrrrrrrrr'));
		$this->expectEqual('llllllllllllllllllloooooooo...', Helpers::ellipsis('llllllllllllllllllloooooooooooooonnnnnnnnnnnnnnngggggggggggggggeeeeeeeeeeeeeerrrrrrrrrrr', 30));
		$this->expectEqual('this string has 24 chars', Helpers::ellipsis('this string has 24 chars', 24));
	}

	public function testJwt() {
		$this->disable_truncation = true;

		$input = [
			'something' => 'hi',
		];
		$output = Helpers::encodeJwt($input);
		$result = Helpers::decodeJwt($output);
		$this->expect($result['something'] === 'hi');
	}

	public function testGetType() {
		$this->disable_truncation = true;

		try {
			Helpers::getType('something that does not exist');
			$this->fail('Should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains('Unknown type', $e->getMessage());
		}

		// non-standard regular pull
		$results = Helpers::getType('user_roles');
		$found = false;
		foreach ($results as $result) {
			if ($result['name'] === 'User') {
				$found = true;
				break;
			}
		}
		$this->expect($found);

		// non-standard id'd pull
		$results = Helpers::getType('user_roles', 0, true);
		$this->expect(in_array('User', $results));

		// standard regular pull
		$results = Helpers::getType('user_roles');
		$found = false;
		foreach ($results as $result) {
			if ($result['name'] === 'Root Admin') {
				$found = true;
				break;
			}
		}
		$this->expect($found);

		// standard id'd pull
		$results = Helpers::getType('user_roles', 0, true);
		$this->expect(in_array('Root Admin', $results));
	}

	public function testGetPagerSet() {
		$this->disable_truncation = true;

		try {
			Helpers::getPagerSet([]);
			$this->fail('Should have failed');
		} catch (MissingField $e) {
			$this->expectStringContains('count', $e->getMessage());
		}
		try {
			Helpers::getPagerSet(['count']);
			$this->fail('Should have failed');
		} catch (MissingField $e) {
			$this->expectStringContains('count', $e->getMessage());
		}
		try {
			Helpers::getPagerSet(['count' => 'hi']);
			$this->fail('Should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains('count', $e->getMessage());
		}
		try {
			Helpers::getPagerSet(['count' => -1]);
			$this->fail('Should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains('count', $e->getMessage());
		}

		try {
			Helpers::getPagerSet(['count' => '0']);
			$this->fail('Should have failed');
		} catch (MissingField $e) {
			$this->expectStringContains('pos', $e->getMessage());
		}
		try {
			Helpers::getPagerSet(['count' => 0, 'pos']);
			$this->fail('Should have failed');
		} catch (MissingField $e) {
			$this->expectStringContains('pos', $e->getMessage());
		}
		try {
			Helpers::getPagerSet(['count' => 1, 'pos' => 'hi']);
			$this->fail('Should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains('pos', $e->getMessage());
		}
		try {
			Helpers::getPagerSet(['count' => '1', 'pos' => -1]);
			$this->fail('Should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains('pos', $e->getMessage());
		}

		try {
			Helpers::getPagerSet(['count' => 1, 'pos' => 1], 0);
			$this->fail('Should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains('Range', $e->getMessage());
		}
		try {
			Helpers::getPagerSet(['count' => 1, 'pos' => 1], -1);
			$this->fail('Should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains('Range', $e->getMessage());
		}
		try {
			Helpers::getPagerSet(['count' => 1, 'pos' => 1], 2);
			$this->fail('Should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains('Range', $e->getMessage());
		}

		$result = Helpers::getPagerSet(['count' => 0, 'pos' => 0]);
		$this->expect(count($result) === 3);
		$this->expect($result[0]['type'] === 'first');
		$this->expect($result[0]['disabled'] === true);
		$this->expect($result[0]['value'] === 0);
		$this->expect($result[1]['type'] === 'active');
		$this->expect($result[1]['disabled'] === false);
		$this->expect($result[1]['value'] === 0);
		$this->expect($result[2]['type'] === 'last');
		$this->expect($result[2]['disabled'] === true);
		$this->expect($result[2]['value'] === 0);

		$result = Helpers::getPagerSet(['count' => 1, 'pos' => 0]);
		$this->expect(count($result) === 3);
		$this->expect($result[0]['type'] === 'first');
		$this->expect($result[0]['disabled'] === true);
		$this->expect($result[0]['value'] === 0);
		$this->expect($result[1]['type'] === 'active');
		$this->expect($result[1]['disabled'] === false);
		$this->expect($result[1]['value'] === 0);
		$this->expect($result[2]['type'] === 'last');
		$this->expect($result[2]['disabled'] === true);
		$this->expect($result[2]['value'] === 0);

		$result = Helpers::getPagerSet(['count' => 2, 'pos' => 0]);
		$this->expect(count($result) === 4);
		$this->expect($result[0]['type'] === 'first');
		$this->expect($result[0]['disabled'] === true);
		$this->expect($result[0]['value'] === 0);
		$this->expect($result[1]['type'] === 'active');
		$this->expect($result[1]['disabled'] === false);
		$this->expect($result[1]['value'] === 0);
		$this->expect($result[2]['type'] === 'link');
		$this->expect($result[2]['disabled'] === false);
		$this->expect($result[2]['value'] === 1);
		$this->expect($result[3]['type'] === 'last');
		$this->expect($result[3]['disabled'] === false);
		$this->expect($result[3]['value'] === 1);

		$result = Helpers::getPagerSet(['count' => 2, 'pos' => 1]);
		$this->expect(count($result) === 4);
		$this->expect($result[0]['type'] === 'first');
		$this->expect($result[0]['disabled'] === false);
		$this->expect($result[0]['value'] === 0);
		$this->expect($result[1]['type'] === 'link');
		$this->expect($result[1]['disabled'] === false);
		$this->expect($result[1]['value'] === 0);
		$this->expect($result[2]['type'] === 'active');
		$this->expect($result[2]['disabled'] === false);
		$this->expect($result[2]['value'] === 1);
		$this->expect($result[3]['type'] === 'last');
		$this->expect($result[3]['disabled'] === true);
		$this->expect($result[3]['value'] === 1);

		$result = Helpers::getPagerSet(['count' => 3, 'pos' => 1]);
		$this->expect(count($result) === 5);
		$this->expect($result[0]['type'] === 'first');
		$this->expect($result[0]['disabled'] === false);
		$this->expect($result[0]['value'] === 0);
		$this->expect($result[1]['type'] === 'link');
		$this->expect($result[1]['disabled'] === false);
		$this->expect($result[1]['value'] === 0);
		$this->expect($result[2]['type'] === 'active');
		$this->expect($result[2]['disabled'] === false);
		$this->expect($result[2]['value'] === 1);
		$this->expect($result[4]['type'] === 'last');
		$this->expect($result[4]['disabled'] === false);
		$this->expect($result[4]['value'] === 2);

		$result = Helpers::getPagerSet(['count' => 5, 'pos' => 2]);
		$this->expect(count($result) === 7);
		$this->expect($result[0]['type'] === 'first');
		$this->expect($result[0]['disabled'] === false);
		$this->expect($result[0]['value'] === 0);
		$this->expect($result[1]['type'] === 'link');
		$this->expect($result[1]['disabled'] === false);
		$this->expect($result[1]['value'] === 0);
		$this->expect($result[3]['type'] === 'active');
		$this->expect($result[3]['disabled'] === false);
		$this->expect($result[3]['value'] === 2);
		$this->expect($result[5]['type'] === 'link');
		$this->expect($result[5]['disabled'] === false);
		$this->expect($result[5]['value'] === 4);
		$this->expect($result[6]['type'] === 'last');
		$this->expect($result[6]['disabled'] === false);
		$this->expect($result[6]['value'] === 4);
	}
}