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


/** The response object holds text/html, error and object and has one method to send this out.
 */
class Ajax_Response
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
}

