<?php

add_include_path(SYSDIR);
add_include_path(APPDIR);

require_once('http-exception.inc.php');
require_once('autoloader.inc.php');
require_once('uri-map.inc.php');
require_once('uri-registrar.inc.php');
require_once('representation-manager.inc.php');
require_once('representer.inc.php');
require_once('request.inc.php');
require_once('response.inc.php');

class Application {
	const VERSION = '0.8';
	const TITLE = 'M<i>a<b>M</b></i><b>a</b>';

	/* standard interfaces */
	const IF_PUBLIC  = 'pub';
	const IF_MACHINE = 'api';
	const IF_AUTHED  = 'auth';

	/**
	 * Initialises the application; loading resource request handlers, etc.
	 *
	 * Scans and includes all {APPDIR}/resource-types/*.php in alphabetical order,
	 * then ditto {APPDIR}/representation-types/*.php
	 */
	public static function init() {
		set_error_handler(array('Application','error_response'), E_ALL & (~E_STRICT));

		$paths = array(
			APPDIR.'/resource-handlers',
			APPDIR.'/representations',
		);
		foreach ($paths as $path) {
			if (is_dir($path)) {
				$candidates = scandir($path);
				foreach ($candidates as $filename) {
					$filename = $path . '/' . $filename;
					if (is_readable($filename) && substr($filename,-4) == '.php') {
						require_once($filename);
					}
				}
			}
		}
	}

	/**
	 * Register a Class so that it can be loaded later, if required.
	 *
	 * @param String $classname the name of the class
	 * @param String $filename the name of the file that defines the class
	 */
	public static function register_class($classname, $filename) {
		Autoloader::register($classname, $filename);
	}

	/**
	 * Sets up a Representer which may be able to represent a model.
	 */
	public static function register_representer($representer) {
		RepresentationManager::add($representer);
	}

	/**
	 * Returns a registrar object which lets you register URI handlers.
	 *
	 * @param String $module the name of the module
	 */
	public static function uri_registrar($module) {
		return new URIRegistrar($module);
	}

	/**
	 * Responds to an incoming HTTP request by invoking the appropriate registered handler.
	 *
	 * Creates and returns an appropriate Response object.
	 */
	public static function handle_request() {
		$request = new Request();
		if ($request->method() == 'OPTIONS') {
			$uri = $request->uri();
			if ($uri == '*') {
				$methods = URIMap::methods();
			} else {
				$methods = URIMap::allowed_methods($uri);
			}
			if ($methods) {
				$response = new Response($request->http_version());
				$response->header('Allow', implode(', ', $methods));
			} else {
				$response = Response::generate(404);
			}
		} else {
			$model = self::get_model_for($request);
			if ($model instanceof Response) {
				// short circuit; usually means get_model_for failed
				$response = $model;
			} else {
				$response = self::get_response_for($model, $request);
			}
		}
		$response->commit($request);
	}

	/**
	 * Uses the URIMap to route the incoming request to the most appropriate
	 * registered handler.
	 *
	 * If the handler succeeds, it returns a Model.  If not, it returns a
	 * Response object.
	 */
	protected static function get_model_for($request) {
		$httpmethod = strtoupper( $request->method() );
		$uri = $request->uri();

		if (!URIMap::knows_method($httpmethod)) {
			return self::_tweak_allow_headers(Response::generate(501), $uri);
		}

		$final_response = null;
		foreach (URIMap::method($httpmethod) as $rule) {
			$match = $rule['match'];
			$regex = $match[0];
			if (preg_match($regex, $uri, $hits)) {
				$result = array_combine($match, $hits);
				array_shift($result); // drop $result[0] -- pattern=>uri
				$request->_set_params($result);

				$handler = $rule['handler'];
				$handler = URIMap::realise_handler($handler);

				try {
					$model = call_user_func($handler, $request);
					return $model;
				} catch (Exception $e) {
					if (is_null($final_response)) {
						$final_response = Response::generate_ex($e);
					}
				}
			}
		}

		if (is_null($final_response)) {
			// could they have made any request at all against that URI?
			$allowed_methods = URIMap::allowed_methods($uri);
			if ($allowed_methods) {
				// yep -- current method not allowed
				$status = 405;
			} else {
				// nope -- no such resource
				$status = 404;
			}

			if (defined('DEBUG') && DEBUG)
				$final_response = Response::generate($status, "<p>No $httpmethod <a href=\"/debug/handlers\">handler registered</a> for '<code>$uri</code>'</p>", TRUE);
			else
				$final_response = Response::generate($status);

			if ($allowed_methods) {
				$final_response->header('Allow', implode(', ', $allowed_methods));
			}
		}

		return $final_response;

	}

	/**
	 * If the URI Map recognises the URI for any methods at all, tack
	 * an 'Allow' header onto the response.
	 * @return $response
	 */
	protected static function _tweak_allow_headers($response, $uri) {
		$allowed_methods = URIMap::allowed_methods($uri);
		if ($allowed_methods)
			$response->header('Allow', implode(', ', $allowed_methods));
		return $response;
	}

	/**
	 * Uses the RepresentationManager to represent a given Model according
	 * to the incoming request.
	 */
	protected static function get_response_for($model, $request) {
		try {
			$response = RepresentationManager::represent($model, $request);
		} catch (Exception $e) {
			$response = Response::generate_ex($e);
		}
		return $response;
	}

	/**
	 * Called by PHP when an error occurs.
	 * Does a little bit of niceification, then passes it off to Response.
	 */
	public static function error_response($errno, $errstr, $errfile, $errline) {
		$tmp = array();
		for ($i = 0; $i < 15; $i++) {
			switch ($errno & pow(2,$i)) {
			case E_ERROR:             $tmp[] = 'E_ERROR'; break;
			case E_WARNING:           $tmp[] = 'E_WARNING'; break;
			case E_PARSE:             $tmp[] = 'E_PARSE'; break;
			case E_NOTICE:            $tmp[] = 'E_NOTICE'; break;
			case E_CORE_ERROR:        $tmp[] = 'E_CORE_ERROR'; break;
			case E_CORE_WARNING:      $tmp[] = 'E_CORE_WARNING'; break;
			case E_COMPILE_ERROR:     $tmp[] = 'E_COMPILE_ERROR'; break;
			case E_COMPILE_WARNING:   $tmp[] = 'E_COMPILE_WARNING'; break;
			case E_USER_ERROR:        $tmp[] = 'E_USER_ERROR'; break;
			case E_USER_WARNING:      $tmp[] = 'E_USER_WARNING'; break;
			case E_USER_NOTICE:       $tmp[] = 'E_USER_NOTICE'; break;
			case E_STRICT:            $tmp[] = 'E_STRICT'; break;
			case E_RECOVERABLE_ERROR: $tmp[] = 'E_RECOVERABLE_ERROR'; break;
			#case E_DEPRECATED:        $tmp[] = 'E_DEPRECATED'; break;
			#case E_USER_DEPRECATED:   $tmp[] = 'E_USER_DEPRECATED'; break;
			}
		}
		if ($tmp) $title = implode(' | ', $tmp);
		else $title = "#$errno";
		Response::error($title, $errstr, $errfile, $errline);
	}

	private function __construct() {}
	private function __clone() {}
}


