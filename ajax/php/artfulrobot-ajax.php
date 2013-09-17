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
	 *  if empty, anyone can run it.
	 */ 
	protected $groups_required = array(); 

	/** reference to either _POST or _GET, set in  */
	public $request;

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

	/** Check permissions using CMS::userInGroup(), return true|false 
	 *
	 * Nb. a minimal CMS class must therefore be created for each CMS it runs under
	 */
	public function check_permissions()
	{
		// if no minimal CMS class, no security(!)
		if (! class_exists('CMS')) return true;

		// no groups_required = ok
		if (! $this->groups_required) return true;

		// all must match
		foreach ($this->groups_required as $group_name)
			if (!CMS::userInGroup($group_name)) return false;
		return true;
	}

}/*}}}*/
// abstract class ARL_Ajax_Module_Group extends ARL_Ajax_Module //{{{
/** 
  * Simple way to group ajax functions, e.g. arlClass=GroupName, task=methodname
  */
abstract class ARL_Ajax_Module_Group extends ARL_Ajax_Module
{
	/** undefined, or list of allowed task methods */
	private $task_methods;

	function run_module()
	{
		$task = ARL_Array::value('task', $this->request);
		if (! $task )
		{
			$this->response->error = get_class($this) . " Task '$task' invalid";
			return;
		}
		if (! method_exists($this,$task))
		{
			$this->response->error = get_class($this) . " Task '$task' unknown";
			return;
		}
		if ( $this->task_methods && ! in_array($task, $this->task_methods))
		{
			$this->response->error = get_class($this) . " '$task' Method not a Task";
			return;
		}
		$this->$task();
	}
} //}}}

// ARL_Ajax_Request::process() /*{{{*/
/** this class deals with requests, handing them on to the correct module and sending the response.
 *
 * Synopsis (very simple!):
<code>
ARL_Ajax_Request::process();
</code>
 *
 * Nb. put debug=1 in the request params to turn on debugging via ARL_Debug.
 *     On production sites, ARL_Debug should be silent, but if not, this would be a security risk. 
 */
class ARL_Ajax_Request
{
	static private $response;
	static private $request;
	static public function get_response()/*{{{*/
	{
		if (! self::$response) self::$response = new ARL_Ajax_Response();
		return self::$response;
	}/*}}}*/
	static public function process()/*{{{*/
	{
		// response object is a singleton
		$response = self::get_response();

		if ($_POST)
		{
			$todo = ARL_Array::value('arlClass',  $_POST);
			self::$request = & $_POST;
		}
		else
		{
			$todo = ARL_Array::value('arlClass',  $_GET);
			self::$request = & $_GET;
		}

		if($todo) $todo = "Ajax_$todo";

		if ($todo == 'Ajax_keepalive')
		{
			// noop
			$response->obj['success']=1;
		}
		else self::run_process( $todo );

		// send response and exit now unless debugging
		if (!ARL_Array::value('debug', self::$request))
		   	$response->send();

		ARL_Debug::log("!! Response object:", $response);
		ARL_Debug::legacy_api('print_full');
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

		try {
			if (!class_exists($todo))
				throw new Exception("Class $todo does not exist");
		   	$processor = new $todo($response); 
		}
		catch (Exception $e)
		{
			$response->error = 'Code missing for ' . $todo;
			$response->send(); // exits script
		}

		$processor->request = & self::$request;
		$processor->run();
	}/*}}}*/
}/*}}}*/
