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


abstract class AJAX_Module
{
	/** \ArtfulRobot\Ajax_Response object that we write to.
	 */
	protected $response;
	/** array of permissions that the person must have
	 *  in order to run this module
	 *  if empty, anyone can run it.
	 */ 
	protected $groups_required = array(); 

	/** reference to either _POST or _GET, set in  */
	public $request;

	function __construct( $response )
	{
		if ( ! ($response instanceof \ArtfulRobot\Ajax_Response) )
			throw new \Exception("\\ArtfulRobot\\Ajax_Module::__construct requires \\ArtfulRobot\\Ajax_Response object.");
		$this->response = $response;
	}

	/** Externally callable method - ensures checkPermissions is called before run_module
	 */
	public final function run()
	{
		if (! $this->checkPermissions()) {
            throw new \ArtfulRobot\Ajax_Exception("Permission denied.");
        }
		$this->run_module();
	}

	/** do-ing code goes in here. 
	 */
	abstract protected function run_module();

	//public final function checkPermissions()/*{{{*/
	/** Check permissions using CMS::userInGroup(), return true|false 
	 *
	 * Nb. a minimal CMS class must therefore be created for each CMS it runs under
	 */
	public final function checkPermissions()
	{
		// no groups_required = ok, no security
		if (! $this->groups_required) return true;

		// all must match
		foreach ($this->groups_required as $group_name) {
			if (! AJAX_Bridge::checkPermission($group_name)) {
                return false;
        }}
		return true;
	}/*}}}*/


}
