<?php
namespace Controllers;

use Base;

class Demo extends ControllerBase {
	public function adminUserPage(Base $f3, array $args) {
		$this->inlinePageRender('<h1>Admin Page</h1><p>You should only be able to view this as an admin.</p>', 'Admin Page');
	}

	public function regUserPage(Base $f3, array $args) {
		$this->inlinePageRender('<h1>Regular Page</h1><p>You should only be able to view this as a regular user.</p>', 'Regular User Page');
	}

	public function anonUserPage(Base $f3, array $args) {
		$this->inlinePageRender('<h1>Anonymous Page</h1><p>You should only be able to view this while not logged in.</p>', 'Anonymous User Page');
	}
}