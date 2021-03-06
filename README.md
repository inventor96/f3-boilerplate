# F3 Boilerplate #

## Overview ##

This project is built on the [Fat-Free Framework](https://fatfreeframework.com/) (F3), and uses Composer for dependency and package management. This is an MVC project. Models, and Controllers can be found within the respective folders inside the `app/autoload` folder. Views are in the `app/views` folder. Composer files are located in `app/vendor`. The `app/tmp` folder contains any temporary files generated by F3 (processed template files, file-based caches, minified JS or CSS, etc.). `app/logs` contain application-generated logs.

To run this project locally, run `php -S localhost:8001 -t public/`.

### Controllers ###

Requests are routed to methods within controllers. All controller classes should extend the `Base` controller class; this will perform some security checks on all requests, setup templating extensions, and allow for simple page rendering within the extending controller class. Calling `$this->simplePageRender()` within a method of a class that extends `Base` provides an easy and consistent way to return a templated page to the browser. See the documentation on the method to learn more.

### Views ###

Views are broken down into three folders: `templates`, `pages`, and `emails`. `templates` and `pages` are used to build HTML pages which are sent to the browser. `emails` are used to render text (`.txt` files) and HTML (`.html` files) versions of emails.

## Web Server Configuration ##

This project comes with the example Apache configuration [found in the F3 docs](https://fatfreeframework.com/3.7/routing-engine#DynamicWebSites). If you need a configuration for a different web server, see those docs for other example configurations.

The web server root should be set to the `public` directory, and requests should be routed through `public/index.php` as needed.

## Configuring the App ##

All app configuration settings should be stored within the `app/config` folder.

### General Configuration ###

A config file is required at `app/config/config.php`. There are various existing `config.*.php` files for different environments. Copy or link the respective file as `config.php` to enable the server to run in that environment. The `env` setting sets the environment which the app runs under. Valid options are `dev` (local development environment), `staging`, and `prod`.

### Routes ###

Routes are defined in the `routes.php` file. This is interpreted by F3. See the [user guide on the routing engine](https://fatfreeframework.com/3.7/routing-engine), and [routing API documentation](https://fatfreeframework.com/3.7/base#route). The Controller class and method to which the request should be routed are specified within each route. We've added some extra route settings for application use. See the documentation in the `routes.php` file for details.

### Versions ###

The `versions.php` file contains the version number of JavaScript and CSS resources. This allows us to cache the minification of the resources, but force a refresh (server- and client-side) when one of the minified resources is changed. The resources are stored in the respective folders with the `public` folder. Whenever one of the resources within those folders is updated, the respective value in `versions.php` should be incremented.

## Database ##

This project is built to use a MySQL (or equivalent) database. See the [\DB\SQL constructor documentation](https://fatfreeframework.com/3.7/sql#constructor) for compatibility.

## Built-in Constants and Variables ##

There are a few constants defined to get the app started, and are used as needed in some other places in the app.

* `APP_ROOT_DIR`: The root directory of the application and repository. This contains the `app` and `public` directories, and the Composer files.
* `PUB_DIR`: The folder with all publicly-accessible files. Anything that can be directly accessed by browsers should go here.
* `APP_DIR`: The folder containing the executable, or otherwise private files (such as logs and configuration).
* `CONFIG_DIR`: The folder with all app configuration files.

During the execution of the app, there are a few common variables set within the F3 hive:

* `$f3->is_dev`: Set to `true` when the environment is `dev` or `staging`.
* `$f3->is_local_dev`: Set to `true` only when operating in a local development environment.
* `$f3->is_staging`: Set to `true` only when operating in a staging environment.
* `$f3->is_prod`: Set to `true` only when operating in the production environment.
* `$f3->config`: An array containing all configuration items from the loaded configuration file.
* `$f3->valid_csrf`: Once control is within a method of a class that extends the `Base` class, this will be `true` when the incoming request has a valid CSRF token. `Base->beforeRoute()` requires a valid CSRF for AJAX requests, but this should be manually checked within the controller method when a request should have been made by a client that has recently loaded a resource. Also consider specifying the `[ajax]` modifier in the respective route.