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

//class Array
/** provides various utility functions mostly for dealing with arrays, objects
  */
class Utils
{
	// static public function arrayValue( $key, &$array, $default=null, $create_if_missing=false)/*{{{*/
	/** return value from an array for given key, or default.
	 *  
	 *  @param string $key
	 *  @param array &$array reference to array
	 *  @param mixed $default defaults to null
	 *  @param bool $create_if_missing 
	 *  @return mixed
	 */
	static public function arrayValue( $key, &$array, $default=null, $create_if_missing=false)
	{
		if (! is_array($array)) 
		{
			trigger_error( "\ArtfulRobot\Utils::arrayValue called with something other than an array",E_USER_NOTICE);
			return null;
		}
		if (array_key_exists($key, $array)) return $array[$key];
		if ($create_if_missing) $array[$key] = $default;
		return $default;
	}/*}}}*/
	// static public function arrayReference( $key, &$array, $default=null )/*{{{*/
	/** return reference to an array for given key, initialising default value if necessary.
	 *  
	 *  @param string $key
	 *  @param array &$array reference to array
	 *  @param mixed $default defaults to null
	 *  @return mixed
	 */
	static public function & ArrayReference( $key, &$array, $default=null)
	{
		if (! is_array($array)) throw new Exception( "\ArtfulRobot\Utils::arrayReference called with something other than an array");
		if (!array_key_exists($key, $array)) $array[$key] = $default;
		return $array[$key];
	}/*}}}*/
	//public static function arrayValueRecursive( $keys, &$array, $default=null, $create_if_missing=false)/*{{{*/
	/** return value from an array nested key array, or default.
	 *  
	 *  @param array $keys 
	 *  @param array &$array reference to array
	 *  @param mixed $default defaults to null
	 *  @param bool $create_if_missing 
	 *  @return mixed
	 */
	public static function arrayValueRecursive( $keys, &$array, $default=null, $create_if_missing=false)
	{
		if (! is_array($array)) throw new Exception( "\ArtfulRobot\Utils::arrayValue_recursive called with something other than an array");

		$ptr = &$array;

		$parent_keys = $keys;
		$child_key = array_pop($parent_keys);

		while (isset($ptr) && count($parent_keys))
		{
			$key = array_shift($parent_keys);
			if (array_key_exists($key, $ptr)) 
			{
				$ptr = &$ptr[$key];
				if (! is_array($ptr)) throw new Exception(
					"\ArtfulRobot\Utils::arrayValue_recursive failed, something in the chain is not an array.");
			}
			else unset($ptr);
		}
		if (! $create_if_missing) 
		{
			if (! isset($ptr)) return $default;
			else return self::value($child_key, $ptr, $default, false);
		}

		// create_if_missing is required
		// chain exists.
		if (isset($ptr))
			return self::value($child_key, $ptr, $default, true);

		// chain failed
		$ptr = &$array;
		foreach (array_slice($keys,0,-1) as $key)
		{
			if (! array_key_exists($key, $ptr))
				$ptr[$key] = array();
			$ptr = &$ptr[$key];
		}
		$ptr[$child_key] = $default;
			
		return $default;
	}/*}}}*/

	// public static function tokeniseSearchString( $search_text )/*{{{*/
	/** tokenise a search string into an array, preserving phrases in quotes as individual tokens
	 *  
	 *  this taken from http://www.php.net/manual/en/function.strtok.php#94463
	 */
	public static function tokeniseSearchString( $search_text )
	{
		$tokens = array();
		$token = strtok($search_text, ' ');
		while ($token) 
		{
			// find double quoted tokens
			if (substr($token,0,1)=='"')
				$token = substr($token,1) . ' ' . strtok('"'); 
			// find single quoted tokens
			elseif (substr($token,0,1)=="'")
				$token = substr($token,1) . ' ' . strtok("'"); 

			$tokens[] = $token;
			$token = strtok(' ');
		}
		return $tokens;
	}/*}}}*/
	// static public function objectProperty( $property, $object, $default=null, $create_if_missing=false)/*{{{*/
	/** return given property of object, or default if not exists.
	 *  
	 *  @param string $property
	 *  @param object $object
	 *  @param mixed $default defaults to null
	 *  @param bool $create_if_missing 
	 *  @return mixed
	 */
	static public function objectProperty( $property, $object, $default=null, $create_if_missing=false)
	{
		if (! is_object($object)) throw new Exception( "\ArtfulRobot\Object::value called with something other than an object");
		if (array_key_exists($property, $object)) return $object->$property;
		if ($create_if_missing) $object->$property = $default;
		return $default;
	}/*}}}*/
	// static public function objectPropertyReference( $property, &$object, $default=null )/*{{{*/
	/** return reference to an object's property, initialising default value if necessary.
	 *  
	 *  @param string $property
	 *  @param object $object
	 *  @param mixed $default defaults to null
	 *  @return mixed
	 */
	static public function & ObjectPropertyReference( $property, &$object, $default=null)
	{
		if (! is_object($object)) throw new Exception( "\ArtfulRobot\Object::reference called with something other than an object");
		if (!array_key_exists($property, $object)) $object->$property = $default;
		return $object->$property;
	}/*}}}*/
}

