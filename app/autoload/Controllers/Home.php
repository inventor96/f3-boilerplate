<?php
namespace Controllers;

use Base;
use Models\Helpers;
use Models\Resource;
use Models\UserRole;
use Web;

class Home extends ControllerBase {
	public function homePage(Base $f3, array $args) {
		$this->simplePageRender('home', 'Home');
	}

	public function aboutPage(Base $f3, array $args) {
		$this->simplePageRender('about', 'About');
	}

	public function contactPage(Base $f3, array $args) {
		$this->simplePageRender('contact', 'Contact');
	}

	public function legalPage(Base $f3, array $args) {
		$this->simplePageRender('legal', 'Legal');
	}

	public function pingResponse(Base $f3, array $args) {
		echo "pong";
	}

	public function getResource(Base $f3, array $args) {
		// get info
		$path = Resource::getResourceDir($args['t']);
		$files = Resource::getFileList($args['t'], explode(',', $_GET['f']), true, $f3->REQUEST['d'] !== '0');

		// output minified files
		if ($files) {
			echo Web::instance()->minify($files, null, true, $path);
		}
	}

	public function getType(Base $f3, array $args) {
		switch ($args['type']) {
			case 'user_roles':
				$this->requireRole(UserRole::getById(UserRole::ROLE_ROOT_ADMIN));
				break;

			case 'locations':
			case 'states':
				// anyone can see this
				break;

			default:
				$f3->error(404, "Unknown type");
				break;
		}

		$results = Helpers::getType($args['type']);
		$this->jsonSuccess([$args['type'] => $results]);
	}
}