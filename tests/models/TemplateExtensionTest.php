<?php
namespace Tests\Models;

use Exceptions\InvalidValue;
use Models\TemplateExtension;
use Tests\TestBase;

class TEHlpr extends TemplateExtension {
	public static function callBuildAttrs(array $attrs): string {
		return self::buildAttrs($attrs);
	}

	public static function callTokenizeAttrs(array $attrs): string {
		return self::tokenizeAttrs($attrs);
	}
}

class TemplateExtensionTest extends TestBase {
	public function testBuildAttrs() {
		$this->disable_truncation = true;

		// no attrs
		$this->expect('' === TEHlpr::callBuildAttrs([]));

		// static attrs
		$this->expect('hi' === TEHlpr::callBuildAttrs(['hi']));
		$this->expect('me="hi"' === TEHlpr::callBuildAttrs(['me' => 'hi']));
		$this->expect('sup me="hi"' === TEHlpr::callBuildAttrs(['sup', 'me' => 'hi']));
		$this->expect('sup me="hi" you="hello"' === TEHlpr::callBuildAttrs(['sup', 'me' => 'hi', 'you' => 'hello']));
		$this->expect('sup me="hi" you="hello" them' === TEHlpr::callBuildAttrs(['sup', 'me' => 'hi', 'you' => 'hello', 'them']));

		// dynamic attrs
		$this->expectStringContains('$hi', TEHlpr::callBuildAttrs(['{{@hi}}']));
		$result = TEHlpr::callBuildAttrs(['{{@hi}}' => '{{@me}}']);
		$this->expectStringContains('$hi', $result);
		$this->expectStringContains('$me', $result);
		$result = TEHlpr::callBuildAttrs(['static' => '{{@dynamic}}']);
		$this->expectStringContains('static=', $result);
		$this->expectStringContains('$dynamic', $result);
		$result = TEHlpr::callBuildAttrs(['{{@static}}' => 'dynamic']);
		$this->expectStringContains('$static', $result);
		$this->expectStringContains('"dynamic"', $result);
		$result = TEHlpr::callBuildAttrs(['static' => '{{func(@dynamic)}}']);
		$this->expectStringContains('static=', $result);
		$this->expectStringContains('func($dynamic)', $result);
		$result = TEHlpr::callBuildAttrs(['{{method(@static)}}' => 'dynamic']);
		$this->expectStringContains('method($static)', $result);
		$this->expectStringContains('"dynamic"', $result);
		$result = TEHlpr::callBuildAttrs(['somethingElse{{method(@static)}}' => 'dynamic']);
		$this->expectStringContains('somethingElse', $result);
		$this->expectStringContains('method($static)', $result);
		$this->expectStringContains('"dynamic"', $result);

		// invalid dynamic attrs
		$result = TEHlpr::callBuildAttrs(['{@static}' => 'dynamic']);
		$this->expectStringContains('{@static}', $result);
		$this->expectStringNotContains('$static', $result);
		$this->expectStringContains('"dynamic"', $result);
		$result = TEHlpr::callBuildAttrs(['static' => '{{@dynamic}']);
		$this->expectStringContains('static=', $result);
		$this->expectStringContains('{{@dynamic}', $result);
		$this->expectStringNotContains('$dynamic', $result);
	}

	public function testTokenization() {
		$this->disable_truncation = true;

		// no attrs
		$this->expect('[]' === TEHlpr::callTokenizeAttrs([]));

		// static attrs
		$this->expect("[0 => 'hi']" === TEHlpr::callTokenizeAttrs(['hi']));
		$this->expect("[0 => 'hi\'s and more']" === TEHlpr::callTokenizeAttrs(['hi\'s and more']));
		$this->expect("['me' => 'hi']" === TEHlpr::callTokenizeAttrs(['me' => 'hi']));
		$this->expect("[0 => 'sup', 'me' => 'hi']" === TEHlpr::callTokenizeAttrs(['sup', 'me' => 'hi']));
		$this->expect("[0 => 'sup', 'me' => 'hi', 'you' => 'hello']" === TEHlpr::callTokenizeAttrs(['sup', 'me' => 'hi', 'you' => 'hello']));
		$this->expect("[0 => 'sup', 'me' => 'hi', 'you' => 'hello', 1 => 'them']" === TEHlpr::callTokenizeAttrs(['sup', 'me' => 'hi', 'you' => 'hello', 'them']));

		// dynamic attrs
		$this->expect("[0 => \$hi]" === TEHlpr::callTokenizeAttrs(['{{@hi}}']));
		$this->expect("[\$hi => \$me]" === TEHlpr::callTokenizeAttrs(['{{@hi}}' => '{{@me}}']));
		$this->expect("['static' => \$dynamic]" === TEHlpr::callTokenizeAttrs(['static' => '{{@dynamic}}']));
		$this->expect("[\$static => 'dynamic']" === TEHlpr::callTokenizeAttrs(['{{@static}}' => 'dynamic']));
		$this->expect("['static' => func(\$dynamic)]" === TEHlpr::callTokenizeAttrs(['static' => '{{func(@dynamic)}}']));
		$this->expect("[method(\$static) => 'dynamic']" === TEHlpr::callTokenizeAttrs(['{{method(@static)}}' => 'dynamic']));
		$this->expect("['somethingElse'.method(\$static) => 'dynamic']" === TEHlpr::callTokenizeAttrs(['somethingElse{{method(@static)}}' => 'dynamic']));

		// invalid dynamic attrs
		$this->expect("['{@static}' => 'dynamic']" === TEHlpr::callTokenizeAttrs(['{@static}' => 'dynamic']));
		$this->expect("['static' => '{{@dynamic}']" === TEHlpr::callTokenizeAttrs(['static' => '{{@dynamic}']));
	}

	public function testEllipsis() {
		$this->disable_truncation = true;

		$result = TemplateExtension::ellipsis([]);
		$this->expect("" === $result);
		$result = TemplateExtension::ellipsis(['@attrib' => []]);
		$this->expect("" === $result);
		$result = TemplateExtension::ellipsis(['@attrib' => ['value' => '']]);
		$this->expect("" === $result);

		$result = TemplateExtension::ellipsis(['@attrib' => ['value' => 'hi']]);
		$this->expectStringContains("ellipsis('hi')", $result);
		$result = TemplateExtension::ellipsis(['@attrib' => ['value' => 'a really long string twice. a really long string twice.']]);
		$this->expectStringContains("ellipsis('a really long string twice. a really long string twice.')", $result);
		$result = TemplateExtension::ellipsis(['@attrib' => ['value' => '{{@some_var}}']]);
		$this->expectStringContains("ellipsis(\$some_var)", $result);
		$result = TemplateExtension::ellipsis(['@attrib' => ['value' => 'hi', 'length' => 50]]);
		$this->expectStringContains("ellipsis('hi', 50)", $result);
		$result = TemplateExtension::ellipsis(['@attrib' => ['value' => 'hi', 'length' => '{{@var}}']]);
		$this->expectStringContains("ellipsis('hi', \$var)", $result);
		$result = TemplateExtension::ellipsis(['@attrib' => ['value' => 'hi', 'chars' => 50]]);
		$this->expectStringContains("ellipsis('hi', 50)", $result);
		$result = TemplateExtension::ellipsis(['@attrib' => ['value' => 'hi', 'chars' => '{{@var}}']]);
		$this->expectStringContains("ellipsis('hi', \$var)", $result);
	}

	public function testPager() {
		$this->disable_truncation = true;

		$result = TemplateExtension::pager(['@attrib' => ['path' => '/my/path/', 'pager' => []]]);
		$this->expectStringContains("renderPager('/my/path/', [])", $result);
		$result = TemplateExtension::pager(['@attrib' => ['path' => '{{@path_var}}', 'pager' => ['hi' => 'there']]]);
		$this->expectStringContains("renderPager(\$path_var, ['hi' => 'there'])", $result);
		$result = TemplateExtension::pager(['@attrib' => ['path' => '{{@path_var}}', 'pager' => "{{['hi' => 'there']}}"]]);
		$this->expectStringContains("renderPager(\$path_var, ['hi' => 'there'])", $result);
		$result = TemplateExtension::pager(['@attrib' => ['path' => '{{@path_var}}', 'pager' => "{{@pager_var}}"]]);
		$this->expectStringContains("renderPager(\$path_var, \$pager_var)", $result);
	}

	public function testRenderPager() {
		$this->disable_truncation = true;

		try {
			TemplateExtension::renderPager('', [['type' => 'a bad type']]);
			$this->fail('should have failed');
		} catch (InvalidValue $e) {
			$this->expectStringContains('Unknown page type', $e->getMessage());
		}

		$result = TemplateExtension::renderPager('', []);
		$this->expectStringContains('<ul class="pagination"></ul>', $result);

		$result = TemplateExtension::renderPager('', [['type' => 'active']]);
		$this->expectStringContains('<ul class="pagination">', $result);
		$this->expectStringContains('</ul>', $result);
		$this->expectStringContains('href="?page=', $result);
		$this->expectStringContains('page-item', $result);
		$this->expectStringContains('page-link', $result);

		$result = TemplateExtension::renderPager('?', [['type' => 'active']]);
		$this->expectStringContains('href="?&page=', $result);

		$result = TemplateExtension::renderPager('/my/path?key=value', [['type' => 'active']]);
		$this->expectStringContains('href="/my/path?key=value&page=', $result);

		$result = TemplateExtension::renderPager('/my/path', [['type' => 'active']]);
		$this->expectStringContains('href="/my/path?page=', $result);

		$result = TemplateExtension::renderPager('/my/path', [['type' => 'first', 'value' => 0]]);
		$this->expectStringContains('href="/my/path?page=0', $result);
		$this->expectStringContains('arrow-left-arrow-fill', $result);
		$this->expectStringContains('rounded-left', $result);
		$this->expectStringNotContains('active', $result);

		$result = TemplateExtension::renderPager('/my/path', [['type' => 'first', 'value' => 0]]);
		$this->expectStringContains('href="/my/path?page=0', $result);
		$this->expectStringContains('arrow-left-arrow-fill', $result);
		$this->expectStringContains('rounded-left', $result);
		$this->expectStringNotContains('disabled', $result);

		$result = TemplateExtension::renderPager('/my/path', [['type' => 'first', 'value' => 1]]);
		$this->expectStringContains('href="/my/path?page=1', $result);
		$this->expectStringContains('arrow-left-arrow-fill', $result);
		$this->expectStringContains('rounded-left', $result);
		$this->expectStringNotContains('disabled', $result);

		$result = TemplateExtension::renderPager('/my/path', [['type' => 'first', 'value' => 0, 'disabled' => true]]);
		$this->expectStringContains('href="/my/path?page=0', $result);
		$this->expectStringContains('arrow-left-arrow-fill', $result);
		$this->expectStringContains('rounded-left', $result);
		$this->expectStringContains('disabled', $result);

		$result = TemplateExtension::renderPager('/my/path', [['type' => 'last', 'value' => 10]]);
		$this->expectStringContains('href="/my/path?page=10', $result);
		$this->expectStringContains('arrow-right-arrow-fill', $result);
		$this->expectStringContains('rounded-right', $result);
		$this->expectStringNotContains('active', $result);

		$result = TemplateExtension::renderPager('/my/path', [['type' => 'active', 'value' => 10]]);
		$this->expectStringContains('href="/my/path?page=10', $result);
		$this->expectStringContains('active', $result);
		$this->expectStringContains('11', $result);
		$this->expectStringNotContains('arrow-right-arrow-fill', $result);
		$this->expectStringNotContains('rounded-right', $result);
		$this->expectStringNotContains('arrow-left-arrow-fill', $result);
		$this->expectStringNotContains('rounded-left', $result);

		$result = TemplateExtension::renderPager('/my/path', [['type' => 'active', 'value' => 0]]);
		$this->expectStringContains('href="/my/path?page=0', $result);
		$this->expectStringContains('active', $result);
		$this->expectStringContains('1', $result);
		$this->expectStringNotContains('arrow-right-arrow-fill', $result);
		$this->expectStringNotContains('rounded-right', $result);
		$this->expectStringNotContains('arrow-left-arrow-fill', $result);
		$this->expectStringNotContains('rounded-left', $result);

		$result = TemplateExtension::renderPager('/my/path', [['type' => 'link', 'value' => 10]]);
		$this->expectStringContains('href="/my/path?page=10', $result);
		$this->expectStringContains('11', $result);
		$this->expectStringNotContains('active', $result);
		$this->expectStringNotContains('arrow-right-arrow-fill', $result);
		$this->expectStringNotContains('rounded-right', $result);
		$this->expectStringNotContains('arrow-left-arrow-fill', $result);
		$this->expectStringNotContains('rounded-left', $result);
	}
}