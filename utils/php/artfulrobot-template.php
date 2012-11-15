<?php
/** basic templating class
 *
 * {{{
 * Copyright (c) 2003 Brian E. Lozier (brian@massassi.net)
 *
 * set_vars() method contributed by Ricardo Garcia (Thanks!)
 *
 * fetch_from_string_tpl() added for simple string templating.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 * }}}
 * 
 * @package webdevshared
 * @subpackage rl_core
 */

class Template {
	protected $vars = array(); /// Holds all the template variables
	protected $path; /// Path to the templates

	//public static function theme( $file, $vars)/*{{{*/
	public static function theme( $file, $vars)
	{
		$obj = new Template();
		$obj->set_vars( $vars );
		return $obj->fetch( $file );
	}/*}}}*/

	//public function __construct($path = DOCUMENT_ROOT) //{{{
	/**
	 * Constructor 
	 *
	 * @param string $path the path to the templates
	 *
	 * @return void
	 */
	public function __construct($path = null) {
		if ($path === null) $path = preg_replace('@(/?)$@','/',"/var/www/" . $_SERVER['HTTP_HOST']);
		$this->set_path($path);
	} //}}}

	// public function set_path($path) {{{
	/**
	 * Set the path to the template files. 
	 *
	 * @param string $path path to template files
	 *
	 * @return void
	 */
	public function set_path($path) {
		if (! file_exists($path)) throw new Exception( "Template::set_path requires \$path to be a directory that exists. '$path' not found.");
		// ensure path has trailing slash
		if (substr($path,-1) != '/') $path .= '/';
		$this->path = $path;
	} // }}}

	//public function set($name, $value) //{{{
	/**
	 * Set a template variable.
	 *
	 * @param string $name name of the variable to set
	 * @param mixed $value the value of the variable
	 *
	 * @return void
	 */
	public function set($name, $value) {
		$this->vars[$name] = $value;
	} // }}}

	// public function set_vars($vars, $clear = false) //{{{
	/** 
	 * Set a bunch of variables at once using an associative array.
	 *
	 * @param array $vars array of vars to set
	 * @param bool $clear whether to completely overwrite the existing vars
	 *
	 * @return void
	 */
	public function set_vars($vars, $clear = false) {
		if (! is_array($vars)) throw new Exception( "Template::set_vars requires \$vars to be an array");

		if($clear) $this->vars = $vars;
		else $this->vars = array_merge($this->vars, $vars);
	} // }}}

	//public function fetch($file) {{{
	/**
	 * Open, parse, and return the template file. 
	 *
	 * @param string string the template file name
	 *
	 * @return string
	 */
	public function fetch($file) {
		// if file is specified relative or absolute then don't use path.
		if (!preg_match('@^[./]@',$file)) $file = $this->path . $file;	

		// check it exists
		if (! file_exists($file))
			throw new Exception("Template::fetch - File does not exist: $file");

		$old_level = error_reporting(E_ERROR); // in case the file changes it.
		extract($this->vars);          // Extract the vars to local namespace
		ob_start();                    // Start output buffering
		include $file;
		$contents = ob_get_contents(); // Get the contents of the buffer
		ob_end_clean();                // End buffering and discard
		error_reporting($old_level);
		return $contents;              // Return the contents
	} // }}}

	// public function fetch_from_string_tpl( $stringtpl ) {{{
	/**
	 * Parse string template.
	 * 
	 * @param string string the template file name
	 *
	 * @return string
	 * 
	 * String templates use %varname instead of <?php echo $varname ?>
	 *
	 * %freddy and %fred then strtr seems to be clever enough to 
	 * apply %freddy first. But this does mean that you can't follow
	 * %fred with "dy" and have %fred applied. In which case use %{fred}
	 */
	public function fetch_from_string_tpl( $stringtpl ) {
		$chunk = '';
		$replacements = array();
		foreach ($this->vars as $key=>$val)
		{
			$replacements[ "%$key" ] = $val;
			// was "%\{$key}" but this failed, left the \ in!
			$replacements[ "%{" .$key ."}" ] = $val;
		}
		return strtr( $stringtpl, $replacements );
	} // }}}
}

?>
