<?php
namespace Tests\Models;

use Exceptions\InvalidType;
use Models\Resource;
use Tests\TestBase;

class ResourceTest extends TestBase {
	public function testGetSafeType() {
		$this->disable_truncation = true;

		try {
			Resource::getSafeType('bad');
			$this->fail('Should have failed');
		} catch (InvalidType $e) {
			$this->expectStringContains('Disallowed resource type', $e->getMessage());
		}
		try {
			Resource::getSafeType('cssbad');
			$this->fail('Should have failed');
		} catch (InvalidType $e) {
			$this->expectStringContains('Disallowed resource type', $e->getMessage());
		}
		try {
			Resource::getSafeType('badjs');
			$this->fail('Should have failed');
		} catch (InvalidType $e) {
			$this->expectStringContains('Disallowed resource type', $e->getMessage());
		}

		$this->expect(Resource::getSafeType('JS') === 'js');
		$this->expect(Resource::getSafeType('Js') === 'js');
		$this->expect(Resource::getSafeType('jS') === 'js');
		$this->expect(Resource::getSafeType('CSS') === 'css');
		$this->expect(Resource::getSafeType('CsS') === 'css');
		$this->expect(Resource::getSafeType('Css') === 'css');
		$this->expect(Resource::getSafeType('csS') === 'css');
	}

	public function testGetResourceDir() {
		$this->disable_truncation = true;

		$this->expect(Resource::getResourceDir('js') === PUB_DIR.'js/');
		$this->expect(Resource::getResourceDir('css') === PUB_DIR.'css/');
	}

	public function testGetFileList() {
		$this->disable_truncation = true;

		$this->expect(['bootstrap.css', 'bi.css', 'base.css'] === Resource::getFileList('css', []));
		$this->expect(['bootstrap', 'bi', 'base'] === Resource::getFileList('css', [], false));
		$this->expect(count(Resource::getFileList('css', [], true, false)) === 0);
		$this->expect(count(Resource::getFileList('css', [], false, false)) === 0);

		$result = Resource::getFileList('css', ['notme']);
		$this->expect(!in_array('notme.css', $result));
		$this->expect(!in_array('notme', $result));

		$result = Resource::getFileList('css', ['boostrap']);
		$this->expect($result === array_unique($result));

		$result = Resource::getFileList('css', ['../secret.css', '../bootstrap', '../bootstrap.css']);
		$this->expect(!in_array('secret.css', $result));
		$this->expect(!in_array('secret', $result));
		$this->expect(!in_array('../bootstrap.css', $result));
		$this->expect(!in_array('../bootstrap', $result));
		$this->expect(!in_array('/bootstrap.css', $result));
		$this->expect(!in_array('/bootstrap', $result));

		$this->expect(['jq.js', 'bootstrap.js', 'base.js'] === Resource::getFileList('js', []));
		$this->expect(['jq', 'bootstrap', 'base'] === Resource::getFileList('js', [], false));
		$this->expect(count(Resource::getFileList('js', [], true, false)) === 0);
		$this->expect(count(Resource::getFileList('js', [], false, false)) === 0);

		$result = Resource::getFileList('js', ['notme']);
		$this->expect(!in_array('notme.js', $result));
		$this->expect(!in_array('notme', $result));

		$result = Resource::getFileList('js', ['jq']);
		$this->expect($result === array_unique($result));

		$result = Resource::getFileList('js', ['../secret.js', '../bootstrap', '../bootstrap.js']);
		$this->expect(!in_array('secret.js', $result));
		$this->expect(!in_array('secret', $result));
		$this->expect(!in_array('../bootstrap.js', $result));
		$this->expect(!in_array('../bootstrap', $result));
		$this->expect(!in_array('/bootstrap.js', $result));
		$this->expect(!in_array('/bootstrap', $result));

		$result = Resource::getFileList('js', ['validate']);
		$this->expect($result[0] === 'jq.js');
		$this->expect(in_array('validate.js', $result));
		$this->expect(in_array('vdefault.js', $result));
		$this->expect(array_search('vdefault.js', $result) > array_search('validate.js', $result));
	}

	public function testGetVersionString() {
		$this->disable_truncation = true;

		$files = Resource::getFileList('css', []);
		$result = Resource::getVersionString('css', []);
		$this->expect(substr_count($result, '.') === (count($files) - 1));

		$files = Resource::getFileList('css', ['dashboard']);
		$result = Resource::getVersionString('css', ['dashboard']);
		$this->expect(substr_count($result, '.') === (count($files) - 1));

		$files = Resource::getFileList('js', []);
		$result = Resource::getVersionString('js', []);
		$this->expect(substr_count($result, '.') === (count($files) - 1));

		$files = Resource::getFileList('js', ['dashboard']);
		$result = Resource::getVersionString('js', ['dashboard']);
		$this->expect(substr_count($result, '.') === (count($files) - 1));
	}
}