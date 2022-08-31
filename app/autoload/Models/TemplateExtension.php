<?php
namespace Models;

use Base;
use Exceptions\InvalidType;
use Exceptions\InvalidValue;
use Exceptions\ObjectNotDefined;
use ReflectionClass;
use ReflectionMethod;
use Template;

class TemplateExtension {
	/**
	 * Resolve the remaining/unhandled attributes of a node (all handled attributes should be unset before calling this)
	 *
	 * @param array $attr The remaining values from the ['@attrib'] value of the node
	 * @return string The resolved attributes
	 */
	protected static function buildAttrs(array $attrs): string {
		$t = Template::instance();
		$out = [];
		foreach ($attrs as $key => $value) {
			// build dynamic tokens
			if (preg_match('/{{[^\{\}]+?}}/s', $key)) {
				$key = $t->build($key);
			}
			if (preg_match('/{{[^\{\}]+?}}/s', $value)) {
				$value = $t->build($value);
			}

			// inline or key/value
			if (is_numeric($key)) {
				$out[] = $value;
			} elseif ($value == null) {
				$out[] = $key;
			} else {
				$out[] = $key.'="'.$value.'"';
			}
		}

		// compile
		return implode(' ', $out);
	}

	/**
	 * Turn values into PHP code to execute. Similar to buildAttrs(), except this is intended for compile time instead of run time
	 *
	 * @param array $attrs The values from the ['@attrib'] value of the node
	 * @return string The compiled attributes as a PHP array
	 */
	protected static function tokenizeAttrs(array $attrs): string {
		$out = [];
		foreach ($attrs as $key => $value) {
			$out[] = self::fullStringTokenization($key).' => '.self::fullStringTokenization($value);
		}

		// compile
		return '['.implode(', ', $out).']';
	}

	/**
	 * Properly quotes, escapes, and tokenizes attributes
	 *
	 * @param string $value The value to parse
	 * @return string The formatted value
	 */
	protected static function fullStringTokenization(?string $value): string {
		if ($value === null) {
			return 'null';
		}

		$t = Template::instance();

		// don't need to process this if it's a number or if it's only a templating value
		if (is_numeric($value) || preg_match('/^{{[^\}]+?}}$/s', $value)) {
			return $t->token($value);
		}

		// escape quotes
		$value = str_replace("'", "\\'", $value);

		// replace all instances of templating
		$value = "'".preg_replace_callback('/({{[^\}]+?}})/s', function($matches) use ($t) {
			return "'.".$t->token($matches[0]).".'";
		}, $value)."'";

		// remove empty strings
		$value = preg_replace('/\.?\'\'\.?/s', '', $value);

		return $value;
	}

	/**
	 * Checks for a boolean HTML attribute
	 *
	 * @param array $attrib The `@attrib` key from the node.
	 * @param string $attrib_name The name of the HTML attribute to check on.
	 * @return bool The state of the specified attribute.
	 */
	protected static function getHtmlBool(array $attrib, string $attrib_name): bool {
		// doesn't exist
		if (!in_array($attrib_name, array_keys($attrib), true)) {
			return false;
		}

		// exists with strings that should be false
		if (in_array(trim($attrib[$attrib_name]), ['false', 'off', 'no', 'null'], true)) {
			return false;
		}

		// exists as a boolean
		if ($attrib[$attrib_name] === null) {
			return true;
		}

		// exists with some other string
		return boolval($attrib[$attrib_name]);
	}

	/**
	 * Sets up the templating extensions. Call this before any templates are rendered.
	 */
	public static function setUp(): void {
		// get applicable methods
		$class = new ReflectionClass(get_called_class());
		$methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);

		// add each method to the templating engine
		$template = Template::instance();
		foreach ($methods as $method) {
			if ($method->name == 'setUp' || substr($method->name, 0, 6) == 'render') {
				continue;
			}
			$template->extend($method->name, "{$method->class}::{$method->name}");
		}
	}

	/**
	 * Calls the Helpers::ellipsis method on a string
	 *
	 * @param array $node The HTML node from the template
	 * @param string The resultant string
	 */
	public static function ellipsis(array $node): string {
		$a = $node['@attrib'];

		// no point in calling the method if there's nothing to process
		if (empty($a['value'])) {
			return '';
		}

		$value = self::fullStringTokenization($a['value']);

		// call different forms of the method based on params
		if ($a['length'] || $a['chars']) {
			$chars = self::fullStringTokenization($a['length'] ?: $a['chars']);
			return "<?= \Models\Helpers::ellipsis({$value}, {$chars}); ?>";
		} else {
			return "<?= \Models\Helpers::ellipsis({$value}); ?>";
		}
	}

	/**
	 * Creates a pagination element
	 *
	 * @param array $node The HTML node from the template
	 * @return string The resultant element
	 */
	public static function pager(array $node): string {
		$attrs = $node['@attrib'];

		$path_attr = self::fullStringTokenization($attrs['path']);
		$page_attr = is_array($attrs['pager']) ? self::tokenizeAttrs($attrs['pager']) : self::fullStringTokenization($attrs['pager']);

		return "<?= \Models\TemplateExtension::renderPager({$path_attr}, {$page_attr}) ?>";
	}

	/**
	 * Renders a pagination element at runtime
	 *
	 * @param string $path The base URL to use for the pager links
	 * @param array $pager The pager specs from Helpers::getPagerSet()
	 * @return string The resultant HTML
	 * @throws InvalidValue
	 */
	public static function renderPager(string $path, array $pager): string {
		// set path info
		$joiner = '';
		if (strpos($path, '?') === false) {
			$joiner = $path.'?page=';
		} else {
			$joiner = $path.'&page=';
		}

		// start list
		$html = '<ul class="pagination">';

		// add page links
		foreach ($pager as $page) {
			$extra_class = '';
			$link_text = '';
			$rounded = '';
			switch ($page['type']) {
				case 'first':
				case 'last':
					$extra_class = $page['disabled'] ? 'disabled' : '';
					if ($page['type'] == 'first') {
						$link_text = '<i class="bi arrow-left-arrow-fill"></i>';
						$rounded = 'rounded-left';
					} else {
						$link_text = '<i class="bi arrow-right-arrow-fill"></i>';
						$rounded = 'rounded-right';
					}
					break;

				case 'active':
					$extra_class = 'active';
				case 'link':
					$link_text = intval($page['value']) + 1;
					break;

				default:
					throw new InvalidValue("Unknown page type: {$page['type']}");
					break;
			}

			$html .= "<li class=\"page-item {$extra_class}\"><a class=\"page-link {$rounded}\" href=\"{$joiner}{$page['value']}\">{$link_text}</a></li>";
		}

		// wrap it up
		$html .= '</ul>';
		return $html;
	}

	/**
	 * Creates a select element with options from a table source
	 *
	 * @param array $node The HTML node from the template
	 * @return string The resultant element
	 */
	public static function dropdown(array $node): string {
		$attrs = self::tokenizeAttrs($node['@attrib']);

		return "<?= \Models\TemplateExtension::renderDropdown({$attrs}) ?>";
	}

	/**
	 * Renders a select element at runtime
	 *
	 * @param array $a An array of attributes from the original node
	 * @return string The rendered HTML
	 */
	public static function renderDropdown(array $a): string {
		// get the values
		$values = Helpers::getType($a['type'], $a['cache'] ?? 3600);

		unset($a['type']);
		unset($a['cache']);

		// add empty option
		$d_text = $a['default_option_text'] ?? 'Select one...';
		$options = ($a['default_option'] ?? true) ? '<option value="" class="d-none" disabled selected>'.$d_text.'</option>' : '';

		// build options
		foreach ($values as $value) {
			$options .= "<option value=\"{$value['id']}\">{$value['name']}</option>";
		}

		// build select element
		$other = self::buildAttrs($a ?? []);
		return "<select {$other}>{$options}</select>";
	}

	/**
	 * Creates a li element for the navbar, automatically setting the active and sr-only stuff.
	 *
	 * @param array $node The HTML node from the template.
	 * @return string The compiled PHP.
	 */
	public static function navlink(array $node): string {
		// require the href
		if (empty($node['@attrib']['href'])) {
			throw new ObjectNotDefined("navlink element is missing the href attribute");
		}

		$attrs = self::tokenizeAttrs($node['@attrib']);
		$content = self::fullStringTokenization($node[0]);

		return "<?= \Models\TemplateExtension::renderNavLink({$content}, {$attrs}) ?>";
	}

	/**
	 * Renders a li element for the navbar.
	 *
	 * @param string $content The content from the original element.
	 * @param array $a The attributes from the original element.
	 * @return string The rendered HTML element.
	 */
	public static function renderNavLink(string $content, array $a): string {
		// bootstrap
		$sr_only = '';
		if (empty($a['no-bs'])) {
			$li_class = ['nav-item'];
			$a_class = ['nav-link'];
		} else {
			$li_class = $a_class = [];
		}

		// add classes
		if (!empty($a['li-class'])) {
			$li_class = array_merge($li_class, explode(' ', $a['li-class']));
		}
		if (!empty($a['class'])) {
			$a_class = array_merge($a_class, explode(' ', $a['class']));
		}
		unset($a['class']);
		unset($a['a-class']);

		// check active status
		if ($a['href'] === Base::instance()->PATH) {
			$a_class[] = 'active';
			$sr_only = '<span class="visually-hidden">(current)</span>';
		}
		$href = $a['href'];
		unset($a['href']);

		// build elements
		return '<li class="'.implode(' ', $li_class).'">'
			.'<a href="'.$href.'" class="'.implode(' ', $a_class).'">'.$content.$sr_only.'</a>'
		.'</li>';
	}

	/**
	 * Creates a conditional section that only displays if the current user has at least one of the given roles.
	 *
	 * One or more roles are specified via the `roles` attribute (separated by a space), and should match one of
	 * the constants in the `UserRole` class.
	 *
	 * @param array $node The HTML node from the template
	 * @return string The compiled PHP
	 */
	public static function requireroles(array $node): string {
		// require roles
		if (empty($node['@attrib']['roles'])) {
			throw new ObjectNotDefined("requireroles element is missing the roles attribute");
		}

		// get list of roles
		$text_roles = array_map('strtoupper', explode(' ', $node['@attrib']['roles']));

		// translate into real values
		$avail_consts = (new ReflectionClass(UserRole::class))->getConstants();
		$roles = [];
		foreach ($text_roles as $role) {
			if (!isset($avail_consts[$role])) {
				throw new InvalidType("Unknown role '{$role}'");
			}

			// add roles from array or single value
			if (is_array($avail_consts[$role])) {
				$roles = array_merge($roles, $avail_consts[$role]);
			} else {
				$roles[] = $avail_consts[$role];
			}
		}

		$inverted = self::getHtmlBool($node['@attrib'], 'invert') ? '' : '!';

		// build final php
		unset($node['@attrib']);
		return '<?php if ('.$inverted.'empty(array_intersect('.self::tokenizeAttrs($roles).', $_user_roles))): ?>'
					.(Template::instance())->build($node)
				.'<?php endif; ?>';
	}
}