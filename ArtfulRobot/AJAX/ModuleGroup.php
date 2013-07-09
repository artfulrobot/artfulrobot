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


/** 
  * Simple way to group ajax functions, e.g. arlClass=GroupName, task=methodname
  */
abstract class AJAX_ModuleGroup extends \ArtfulRobot\AJAX_Module
{
	/** undefined, or array of allowed task methods */
	protected $task_methods;

	function runModule()
	{
		$task = \ArtfulRobot\Utils::arrayValue('task', $this->request);
		if (! $task )
		{
			$this->response->error = get_class($this) . " Task '$task' invalid";
			return;
		}

        // convert task into PSR-2 (camel case)
        // some_task_name -> someTaskName
        $method_name = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $task))));
		if (! method_exists($this,$method_name))
		{
			$this->response->error = get_class($this) . " Task '$task' unknown";
			return;
		}
		if ( $this->task_methods && ! in_array($method_name, $this->task_methods))
		{
			$this->response->error = get_class($this) . " '$method_name' Method not a Task";
			return;
		}
		$this->$method_name();
	}
}

