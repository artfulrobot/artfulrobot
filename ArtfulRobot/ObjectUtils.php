<?php
namespace ArtfulRobot;


/*
	Copyright 2007-2011 Rich Lott 

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

//class Object
/** \ArtfulRobot\Object provides various functions for dealing with objects
  */
class Object
{
	// static public function property( $property, $object, $default=null, $create_if_missing=false)/*{{{*/
	/** return given property of object, or default if not exists.
	 *  
	 *  @param string $property
	 *  @param object $object
	 *  @param mixed $default defaults to null
	 *  @param bool $create_if_missing 
	 *  @return mixed
	 */
	static public function property( $property, $object, $default=null, $create_if_missing=false)
	{
		if (! is_object($object)) throw new Exception( "\ArtfulRobot\Object::value called with something other than an object");
		if (array_key_exists($property, $object)) return $object->$property;
		if ($create_if_missing) $object->$property = $default;
		return $default;
	}/*}}}*/
	// static public function property_reference( $property, &$object, $default=null )/*{{{*/
	/** return reference to an object's property, initialising default value if necessary.
	 *  
	 *  @param string $property
	 *  @param object $object
	 *  @param mixed $default defaults to null
	 *  @return mixed
	 */
	static public function & property_reference( $property, &$object, $default=null)
	{
		if (! is_object($object)) throw new Exception( "\ArtfulRobot\Object::reference called with something other than an object");
		if (!array_key_exists($property, $object)) $object->$property = $default;
		return $object->$property;
	}/*}}}*/
}

