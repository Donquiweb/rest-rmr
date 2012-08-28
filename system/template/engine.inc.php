<?php
/*
 * See the NOTICE file distributed with this work for information
 * regarding copyright ownership.  QUT licenses this file to you
 * under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

class TemplateEngine {

	/** Items that can be replaced using %%ITEM%% patterns. */
	private $items = array(
		'BASEURL'    => '',
		'TITLE_PREFIX'    => SITENAME,
		'TITLE_SEPARATOR' => ' | ',
		'_DOCTITLE'  => '',
		'_PAGETITLE' => '',
		'CONTENT'    => '',
		'CSS'        => '',
		# Magic:
		'DOCTITLE'  => SITENAME,
		'PAGETITLE' => SITENAME,
	);

	private $request = NULL;
	private $default_filename = NULL;

	private static $fallback_filename = NULL;

	/**
	 * Creates a new template engine, associated with a request.
	 */
	public function __construct($request=NULL, $default_filename=NULL) {
		if (is_null(TemplateEngine::$fallback_filename))
			TemplateEngine::$fallback_filename = APPDIR.'/default-template.thtml';
		$this->request = $request;
		$this->default_filename = $default_filename;
	}

	/**
	 * Get or set the BASEURL.
	 */
	public function baseurl($value=NULL) {
		if (func_num_args() < 1) {
			return $this->_get('BASEURL');
		} else {
			return $this->_set_title('BASEURL', $value);
		}
	}

	/**
	 * Get or set the prefix for both DOCTITLE and PAGETITLE.
	 */
	public function title_prefix($value=NULL) {
		if (func_num_args() < 1) {
			return $this->_get('TITLE_PREFIX');
		} else {
			return $this->_set_title('TITLE_PREFIX', $value);
		}
	}

	/**
	 * Get or set the separator that joins PREFIX to DOCTITLE or PAGETITLE.
	 */
	public function title_separator($value=NULL) {
		if (func_num_args() < 1) {
			return $this->_get('TITLE_SEPARATOR');
		} else {
			return $this->_set_title('TITLE_SEPARATOR', $value);
		}
	}

	/**
	 * Get or set the document title (from the HTML HEAD).
	 */
	public function doctitle($value=NULL) {
		if (func_num_args() < 1) {
			return $this->_get('_DOCTITLE');
		} else {
			return $this->_set_title('_DOCTITLE', $value);
		}
	}

	/**
	 * Get or set the page title (in the print header).
	 */
	public function pagetitle($value=NULL) {
		if (func_num_args() < 1) {
			return $this->_get('_PAGETITLE');
		} else {
			return $this->_set_title('_PAGETITLE', $value);
		}
	}

	/**
	 * Sets both PAGETITLE and DOCTITLE at the same time.
	 */
	public function set_title($value) {
		$this->_set('_DOCTITLE', $value);
		return $this->_set_title('_PAGETITLE', $value);
	}

	/**
	 * Get or set the actual doctitle (get: TITLE_PREFIX, TITLE_SEPARATOR, DOCTITLE; set: override).
	 */
	public function full_doctitle($value=NULL) {
		if (func_num_args() < 1) {
			return $this->_get('DOCTITLE');
		} else {
			return $this->_set('DOCTITLE', $value);
		}
	}

	/**
	 * Get the actual pagetitle (get: TITLE_PREFIX, TITLE_SEPARATOR, PAGETITLE; set: override).
	 */
	public function full_pagetitle($value=NULL) {
		if (func_num_args() < 1) {
			return $this->_get('PAGETITLE');
		} else {
			return $this->_set('DOCTITLE', $value);
		}
	}

	/**
	 * Get or set the page content.
	 */
	public function content($value=NULL) {
		if (func_num_args() < 1) {
			return $this->_get('CONTENT');
		} else {
			return $this->_set('CONTENT', $value);
		}
	}

	/**
	 * Append some text to the page content.
	 */
	public function append($content) {
		$this->items['CONTENT'] .= $content;
		return $this;
	}

	/**
	 * Get or set the custom stylesheet.
	 */
	public function css($value=NULL) {
		if (func_num_args() < 1) {
			return $this->_get('CSS');
		} else {
			return $this->_set('CSS', $value);
		}
	}

	/**
	 * Append some css to the page's custom stylesheet.
	 */
	public function append_css($css) {
		$this->items['CSS'] .= $css;
		return $this;
	}

	protected function _get($prop) {
		if (isset($this->items[$prop]))
			return $this->items[$prop];
		return NULL;
	}

	protected function _set($prop,$value) {
		$this->items[$prop] = $value;
		return $this;
	}

	protected function _set_title($prop,$value) {
		$this->items[$prop] = $value;

		$pfx = $this->_get('TITLE_PREFIX');
		$sep = $this->_get('TITLE_SEPARATOR');
		foreach (array('DOCTITLE', 'PAGETITLE') as $key) {
			if ($sfx = $this->_get("_$key")) {
				$this->_set($key, $pfx.$sep.$sfx);
			} else {
				$this->_set($key, $pfx);
			}
		}

		return $this;
	}

	protected function _get_local($items,$prop) {
		if (isset($items[$prop]))
			return $items[$prop];
		return NULL;
	}

	protected function _set_local(&$items,$prop,$value) {
		$items[$prop] = $value;
		return $this;
	}

	protected function _set_local_title(&$items,$prop,$value) {
		$items[$prop] = $value;

		$pfx = $this->_get_local($items, 'TITLE_PREFIX');
		$sep = $this->_get_local($items, 'TITLE_SEPARATOR');
		foreach (array('DOCTITLE', 'PAGETITLE') as $key) {
			if ($sfx = $this->_get_local($items, "_$key")) {
				$this->_set_local($items, $key, $pfx.$sep.$sfx);
			} else {
				$this->_set_local($items, $key, $pfx);
			}
		}

		return $this;
	}

	/**
	 * Run template substitutions on a string, and return the
	 * final product.
	 *
	 * @see #execFile
	 */
	public function exec($string) {
		// first extract page-local variables
		$items = $this->items;
		$pattern = '/^\s*%set ([^=]+)=(.*)(\r\n|\r|\n)+/';
		while ($string && preg_match($pattern, $string, $m)) {
			$var = $m[1];
			$val = $m[2];
			switch ($var) {
			#case 'DOCTITLE':
			#case 'PAGETITLE':
			case 'TITLE_PREFIX':
			case 'TITLE_SEPARATOR':
				$this->_set_local_title($items,$var,$val);
				break;
			default:
				$this->_set_local($items,$var,$val);
			}
			$string = substr($string, strlen($m[0]));
		}
		// then substitute away!
		$result = '';
		$pattern = $this->regex($items);
		while ($string && preg_match($pattern, $string, $m)) {
			$result .= $m[1];
			if ($key = $m[2]) {
				$result .= $items[$key];
			} elseif ($file = $m[3]) {
				$result .= $this->execFile($file);
			} elseif ($method = $m[4]) {
				$result .= $this->invoke($method);
			} else {
				// FIXME
				$result .= '<i>'.$m[5].'</i>';
			}
			$string = substr($string, strlen($m[0]));
		}
		$result .= $string;
		return $result;
	}

	/**
	 * Construct the regular expression used to search for
	 *  %%PROPERTY%% and %<file>% type tags.
	 */
	protected function regex($items) {

		# %%PROPERTY%%
		$property_keys = array();
		foreach ($items as $key=>$val) {
			$property_keys[] = preg_quote($key);
		}
		$property_regexp = '%('.implode('|',$property_keys).')%';

		# %<some-file.mhtml>%
		$file_regexp = '<([^>]+)>';

		# %?METHOD:param:param?%
		$method_regexp = '\?([^?]+)\?';

		return "/^(.*)%(?:$property_regexp|$file_regexp|$method_regexp|%([^%]+)%)%/Us";
	}

	/**
	 * Get the contents of a template file.
	 */
	public function load($filename,$fatal=FALSE) {
		$files = array(
			$filename,
			APPDIR.'/'.$filename,
			$filename.'.thtml',
			APPDIR.'/'.$filename.'.thtml',
		);

		foreach ($files as $fullname) {
			if (file_exists($fullname)) {
				return file_get_contents($fullname);
			}
		}

		if ($fatal) {
			throw new Exception('failed to load "'.$filename.'" : no such file');
		} else {
			error_log('failed to load "'.$filename.'" : no such file');
			return '';
		}
	}

	/**
	 * Tries as hard as it can to get a filename, given either a String or NULL.
	 *
	 * $f -> $this->default_filename -> TemplateEngine::$fallback_filename -> err
	 *
	 */
	protected function resolve_filename($f) {
		// if they gave us something real-looking, use it
		if (!is_null($f)) {
			return $f;
		}
		// if we were given a filename in the constructor, use that
		if (!is_null($this->default_filename)) {
			return $this->default_filename;
		}
		// if the default-template file exists, use that
		if (file_exists(TemplateEngine::$fallback_filename) && is_readable(TemplateEngine::$fallback_filename)) {
			return TemplateEngine::$fallback_filename;
		}
		// I give up
		throw new Exception("no filename given");
	}

	/**
	 * Get the contents of a template file, run the template
	 * substitutions on it, and return the final product.
	 *
	 * @see #exec
	 * @see #load
	 */
	public function execFile($filename=NULL) {
		$filename = $this->resolve_filename($filename);
		$doc = $this->load($filename);
		return $this->exec($doc);
	}

	/**
	 * Returns TRUE iff a call to execFile wouldn't die because of bad file stuff.
	 */
	public function canExec($filename=NULL) {
		try {
			$filename = $this->resolve_filename($filename);
			$this->load($filename, TRUE);
			return TRUE;
		} catch (Exception $e) {
			return FALSE;
		}
	}

	/**
	 * Commands are of the form:
	 *
	 *   COMMAND
	 *   COMMAND:param
	 *   COMMAND:param1:param2:...
	 *
	 * FIXME !!?
	 */
	public function invoke($command) {
		$args = explode(':', $command);
		$cmd = array_shift($args);
		return call_user_func_array( array($this, $cmd), $args );
	}

	protected function LASTMODIFIED() {
		return date('M n, Y');
	}

	protected function BENCHMARK() {
		return sprintf('%0.3f', elapsed());
	}

	protected function SELECTED($page) {
		if (is_null($this->request)) return '';

		$page = ltrim($page, '/');

		$regex = '/^';
		if (substr($page, -1) == '*') {
			$page = substr($page, 0, -1);
			$regex .= preg_quote($page,'/' );
			$regex .= '/';
		} else {
			$regex .= preg_quote($page,'/');
			$regex .= '$/';
		}

		if (preg_match($regex, $this->request->get_page())) {
			return 'selected';
		} else {
			return '';
		}
	}

	protected function BREADCRUMBS() {
		if (is_null($this->request)) return '';

		$page = $this->request->get_page();
		$base = $this->items['BASEURL'];

		$page = str_replace('//', '/', $page);

		$b = ltrim($base,'/');
		$l = strlen($b);
		if (substr($page, 0, $l) == $b) {
			$page = ltrim(substr($page, $l), '/');
		}

		$page = rtrim($page, '/');

		$s = '';
		$s .= '<div id="breadcrumb" class="breadcrumb"><span class="bold">location:</span> ';
		$s .= '<ul id="breadcrumb-list">';
		$s .= '<li><a href="'.$base.'/">home</a></li>';

		if ($page) {
			$parts = explode('/', $page);
			$accum = $base.'/';
			foreach ($parts as $part) {
				// TODO: part titles? do I need a full tree description somewhere?
				$accum .= $part . '/';
				$s .= '<li><a href="'.$accum.'">'.$part.'</a></li>';
			}
		}

		$s .= '</ul>';
		$s .= '</div><div class="clear"></div>';
		return $s;
	}

}
