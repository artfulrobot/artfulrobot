<?php 
/*
	Copyright 2007-2011 © Rich Lott 

This file is part of Artful Robot Libraries.

Artful Robot Libraries is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by the Free
Software Foundation, either version 3 of the License, or (at your option) any
later version.

Artful Robot Libraries is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
details.

You should have received a copy of the GNU General Public License along with
Artful Robot Libraries.  If not, see <http://www.gnu.org/licenses/>.

 */


/** Exception for ARL_Ajax* classes 
 */
class ARL_Ajax_Exception extends Exception { }
//class ARL_Ajax_Response/*{{{*/
/** The response object holds text/html, error and object and has one method to send this out.
 */
class ARL_Ajax_Response
{
	public $html='';
	public $obj=array();
	public $error='';
	// public function send()/*{{{*/
	/** Send the response out to the browser and exit.
	 */
	public function send()
	{
		$object = json_encode($this->obj);
		$header = array(
				'objectLength' => mb_strlen($object,'UTF-8'),
				'errorLength'  => mb_strlen($this->error,'UTF-8'),
				'textLength'   => mb_strlen($this->html,'UTF-8'));
		$rsp =  json_encode($header) 
					. $object
					. $this->error
					. $this->html;
		// mb_convert_encoding(, 'UTF-8');
		header('Content-Type: text/html; charset=UTF-8');
		echo $rsp;
		exit;
	}/*}}}*/
}/*}}}*/

abstract class ARL_Ajax_Module/*{{{*/
{
	/** ARL_Ajax_Response object that we write to.
	 */
	protected $response;
	/** array of authgroups that the person must be in
	 *  in order to run this module
	 */ 
	protected $groups_required = array('staff'); 

	function __construct( $response )
	{
		if ( ! ($response instanceof ARL_Ajax_Response) )
			throw new Exception("ARL_Ajax_Module::__construct requires ARL_Ajax_Response object.");
		$this->response = $response;
	}

	/** Externally callable method - ensures check_permissions is called before run_module
	 */
	final public function run()
	{
		if (! $this->check_permissions()) throw new ARL_Ajax_Exception("Permission denied.");
		$this->run_module();
	}

	/** do-ing code goes in here. 
	 */
	abstract protected function run_module();

	/** Check permissions using CMS::user_in_group(), return true|false 
	 *
	 * Nb. a minimal CMS class must therefore be created for each CMS it runs under
	 */
	public function check_permissions()
	{
		if (! $this->groups_required) throw new Exception( get_class($this) . ' has no groups_required -- security risk.');
		foreach ($this->groups_required as $group_name)
			if (!CMS::user_in_group($group_name)) return false;
		return true;
	}

}/*}}}*/

// ARL_Ajax_Request::process() /*{{{*/
/** this class deals with requests, handing them on to the correct module and sending the response.
 *
 *  Nb. if _GET contains ajax=2 then debugging mode is turned on.
 *      currently no security checks are done on this fixme 
 *
 * Synopsis (very simple!):
<code>
ARL_Ajax_Request::process();
</code>
 *
 */
class ARL_Ajax_Request
{
	static private $response;
	static public function get_response()/*{{{*/
	{
		if (! self::$response) self::$response = new ARL_Ajax_Response();
		return self::$response;
	}/*}}}*/
	static public function process()/*{{{*/
	{
		// response object is a singleton
		$response = self::get_response();

		// odd bodge we use
		$debugging = ARL_Array::value('ajax',$_GET)==2 ;
		if ( $debugging )
		{
			$_POST=$_GET;
			magic_unquote($_POST); // xxx ?
			debug_control('not silent');
			debug("TOP debugging: data (from _POST=_GET):", $_POST);
		}

		if ( ($todo=isgiven($_GET,'request')) || ($todo=isgiven($_POST,'request')))
			$todo = "Ajax_$todo";

		if ($todo == 'Ajax_keepalive')
		{
			// noop
			$response->obj['success']=1;
		}
		else self::run_process( $todo );

		// send response and exit now unless debugging
		if ( ! $debugging ) $response->send();

		debug("TOP Response object:", $response);
		debug_control('print_full');
		echo "<hr />" . $response->html;
		exit;
	}/*}}}*/
	static public function run_process( $todo )/*{{{*/
	{
		$response = self::get_response();

		if (!$todo) 
		{
			$response->error = 'Malformed call';
			return;
		}

		try { $processor = new $todo($response); }
		catch (Exception $e)
		{
			$response->error = 'Code missing for ' . $todo;
			$response->send(); // exits script
		}

		$processor->run();
	}/*}}}*/
}/*}}}*/