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

/** @file API for linking with CMS functionality
 *
 *
 */

class AJAX_Bridge
{
    /** holds callback for determining permission */
    protected static $permission_callback = false;
    public static function setPermissionCallBack($cb)/*{{{*/
    {
        if (is_callable($cb)) {
            static::$permission_callback = $cb;
        } else {
            throw new \Exception("Given callback is not callable.");
        }
    }/*}}}*/
	//public static function checkPermission($permission)/*{{{*/
	/** Asks CMS if the current user has the given permission
     * 
     * You should call ArtfulRobot\Bridge::setPermissionCallBack()
     * to call something meaningful to your CMS.
     *
     * As a failsafe, permission will be denied until a permission 
     * callback is set with setPermissionCallBack()
     *
     * @return bool whether this permission is granted.
	 */
	public static function checkPermission($permission)
	{
        if (!static::$permission_callback) {
            return false;
        }
		return call_user_func_array(static::$permission_callback, func_get_args());
	}/*}}}*/

}
