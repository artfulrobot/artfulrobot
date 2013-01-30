<?php 
namespace ArtfulRobot;

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

/** this class deals with requests, handing them on to the correct module and sending the response.
 *
 * Synopsis (very simple!):
<code>
\ArtfulRobot\AJAX_Request::process();
</code>
 *
 * Nb. put debug=1 in the request params to turn on debugging via \ArtfulRobot\Debug.
 *     On production sites, \ArtfulRobot\Debug should be silent, but if not, this would be a security risk. 
 */
class AJAX_Request
{
	static private $response;
	static private $request;
	static public function getResponse()/*{{{*/
	{
		if (! self::$response) self::$response = new \ArtfulRobot\AJAX_Response();
		return self::$response;
	}/*}}}*/
	static public function process()/*{{{*/
	{
		// response object is a singleton
		$response = self::getResponse();

		if ($_POST)
		{
			$todo = \ArtfulRobot\Utils::arrayValue('arlClass',  $_POST);
			self::$request = & $_POST;
		}
		else
		{
			$todo = \ArtfulRobot\Utils::arrayValue('arlClass',  $_GET);
			self::$request = & $_GET;
		}

		if ($todo == 'keepalive')
		{
			// noop
			$response->obj['success']=1;
		}
		else self::runProcess( $todo );

		// send response and exit now unless debugging
		if (!\ArtfulRobot\Utils::arrayValue('debug', self::$request))
		   	$response->send();

		\ArtfulRobot\Debug::log("TOP Response object:", $response);
		\ArtfulRobot\Debug::legacy_api('print_full');
		echo "<hr />" . $response->html;
		exit;
	}/*}}}*/
	static public function runProcess( $todo )/*{{{*/
	{
		$response = self::getResponse();

		if (!$todo) 
		{
			$response->error = 'Malformed call';
			return;
		}

		try {
			if (!class_exists($todo))
				throw new Exception("Class $todo does not exist");

            if (!(is_subclass_of($todo,'\ArtfulRobot\AJAX_Module')))
                throw new Exception("Class $todo is not an ajax module");
		   	$processor = new $todo($response); 
		}
		catch (Exception $e)
		{
			$response->error = $e->getMessage();
			$response->send(); // exits script
		}

		$processor->request = & self::$request;
		$processor->run();
	}/*}}}*/
}
