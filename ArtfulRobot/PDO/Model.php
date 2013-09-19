<?php
namespace ArtfulRobot;

abstract class PDO_Model // in PHP 5.4 we could do this: implements \JsonSerializable
{
    const FORMAT_TIME = 'G.i.s';
    const FORMAT_DATE = 'Y-m-d';
    const FORMAT_DATETIME = 'Y-m-d G.i.s';
    const TABLE_NAME = '';
    const CAST_NONE = 0;
    const CAST_DB = 1;
    const CAST_FULL = 2;

	/** holds all cached models */
    static protected $cached=array();

    /** Definitions must be specified as an array indexed by fieldname
      * of arrays including keys cast, size, null
      *
      * @var definition */
    static protected $definition;

    /** @var bool if set INSERT statements will not set id field */
    static protected $id_is_auto_increment=true;

    /** @var string optional alias, e.g. surveyId */
    static protected $id_alias = false;

    /** @var string SQL default order clause */
    static protected $default_order = '';

    /** @var data array */
    protected $myData ;
    /** @var bool Nb. also true for new records */
    protected $unsaved_changes=false;
    /** @var bool true if not in database */
    protected $is_new=true;

    // abstract(ish) static protected function getConnection(){{{
    /** Must return a \ArtfulRobot\PDO object
      *
      * Nb. I would declare this abstract to generate compile-time
      * errors if a subclass did not  implement it, however 'abstract static'
      * functions are not allowed (which theoretically makes sense)
      * so I implement the insistance as a method that throws an exception.
      *
      * See my post:
      * http://stackoverflow.com/questions/14894635/alternative-model-for-php-abstract-static-class-methods
     */
    static protected function getConnection()
    {
        throw new Exception(get_called_class() . " must implement getConnection()");
    }/*}}}*/
    //public static function buildCollection( $filters, $order=null )//{{{
    /**
      * return a Collection object
      */
    public static function buildCollection( $filters, $order=null )
    {
        Debug::log(">>" . get_called_class(). ":: ".__FUNCTION__." called with filters:", $filters);
        $collection = new Collection();

        $sql = static::buildCollectionSql($params, $filters, $order);

        $stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
                "Fetch records from " . static::TABLE_NAME,
                $sql, $params));
        if ($stmt->errorCode()!='00000')
            throw new Exception("PDO error: " . print_r($stmt->errorInfo(),1));

        while ($row = $stmt->fetch( \PDO::FETCH_ASSOC )) {
            // create an object of the class
            $obj = new static;
            $obj->loadFromArray($row,false,self::CAST_DB);
            $collection->append($obj, $obj->id);
            unset($obj);
        }
        Debug::log("<< Returning collection with " . $collection->count() . " entries");
        return $collection;
    }//}}}
    //public static function buildCollectionSql( &params, $filters, $order=null )//{{{
    /**
      * sets up params and returns SQL for buildCollection
      */
    public static function buildCollectionSql( &$params, $filters, $order=null )
	{
        $sql = static::sqlWhere($params, $filters);

        if ($order === null) $order = static::$default_order;
        if ($order) $sql .= " ORDER BY $order";

		return "SELECT * FROM `" . static::TABLE_NAME . "` $sql";
	} // }}}
    //public static function bulkDelete( $filters )//{{{
    /**
      * return a Collection object
      *
      * $filters is an array of
      *     field => 'value'
      * or  field => { operator:'>=', value: 'value' }
      *
      * filters are ANDed together.
      */
    public static function bulkDelete( $filters )
    {
        $sql = static::sqlWhere($params, $filters);

        $stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
                "Delete records from " . static::TABLE_NAME,
                "DELETE FROM `" . static::TABLE_NAME . "` $sql", $params));
        if ($stmt->errorCode()!='00000')
            throw new Exception("PDO error: " . print_r($stmt->errorInfo(),1));

        // clear cache, which will stop loadCached() returning an out of date
        // object. However, any existing references to object in the cahce
        // are not destroyed (so it's a bad idea to cache these).
        self::$cached[get_called_class()] = array();
    }//}}}
    //public static function loadCached( $id )//{{{
    /** Returns cache or creates object
     *  Used to load models from the database; ensures all php models for one record are shared
     */
    public static function loadCached( $id, $data=null )
    {
        $object_type = get_called_class();
        if (isset(self::$cached[$object_type][$id])) return self::$cached[$object_type][$id];

        // create and populate new object
        $obj = new static;
        if (is_array($data)) {
            $obj->loadFromArray($data);
        } else {
            $obj->loadFromDatabase($id,false);
        }
        // cache and return
        return self::$cached[$object_type][$id] = $obj;
    }//}}}
    //public static function clearCache()//{{{
    /** Erases cache
     */
    public static function clearCache()
    {
        $object_type = get_called_class();
        self::$cached[$object_type] = array();
    }//}}}
    //public static function loadFirstMatch( $filters, $order=null )//{{{
    /** Returns the first match for the $filters given
	 *
     */
    public static function loadFirstMatch( $filters, $order=null)
    {
		return static::buildCollection($filters, $order)->current();
        $sql = static::sqlWhere($params, $filters);

        if ($order === null) $order = static::$default_order;
        if ($order) $sql .= " ORDER BY $order";

		$sql .= " LIMIT 1";

        $row = static::getConnection()->fetchRowAssoc( new \ArtfulRobot\PDO_Query(
                "Fetch records from " . static::TABLE_NAME,
                "SELECT * FROM `" . static::TABLE_NAME . "` $sql", $params));
		if (!$row) return null;
		// create an object of the class
		$obj = new static;
		$obj->loadFromArray($row,false,self::CAST_DB);
        return $obj;
    }//}}}
    //public static function sqlWhere( &$params, $filters )//{{{
    /** Returns the WHERE clause including "WHERE" (or '') based on $filters
      *
      * $filters is an array of
      *     field => 'value'
      * or  field => { operator:'>=', value: 'value' }
      * or  field => { operator:'<=', value: 'value' }
      * or  field => { operator:'=', value: 'value' }
      * or  field => { operator:'!=', value: 'value' }
      * or  field => { operator:'<', value: 'value' }
      * or  field => { operator:'>', value: 'value' }
      * or  field => { operator:'IN', values: array('value',...) }
      * or  field => { operator:'NOT IN', values: array('value',...) }
      * or  field => { operator:'BETWEEN', value1: '', value2:'' }
      * or  field => { operator:'NOT BETWEEN', value1: '', value2:'' }
      * or  field => { operator:'IS NULL'}
      * or  field => { operator:'IS NOT NULL'}
	  *
	  * how to do between? or field null|<=this todo
      *
      * filters are ANDed together.
     */
    public static function sqlWhere( &$params, $filters )
    {
		if (!isset($params)) $params = array();
        $sql= array();
        foreach ($filters as $key=>$filter){
            if (! is_array($filter)) {
				// simple (common) case: key = val
                $params[":$key"] = $filter;
                $sql[] = "`$key` = :$key";
            } else {
                if (in_array($filter['operator'], array('=','>=','<=','<','>','!='))) {
                    $params[":$key"] = $filter['value'];
                    $sql[] = "`$key` $filter[operator] :$key";

				} elseif (in_array($filter['operator'], array('IN','NOT IN'))) {
                    $params[":$key"] = $filter['values'];
                    $sql[] = "`$key` $filter[operator] (:$key)";

				} elseif (in_array($filter['operator'], array('BETWEEN','NOT BETWEEN'))) {
                    $params[":{$key}1"] = $filter['value1'];
                    $params[":{$key}2"] = $filter['value2'];
                    $sql[] = "`$key` $filter[operator] :{$key}1 AND :{$key}2";

				} elseif (in_array($filter['operator'], array('IS NULL','IS NOT NULL'))) {
                    $sql[] = "`$key` $filter[operator]";

                } else {
                    throw new \Exception ("Operator unknown: '$filter[operator]'");
                }
            }
        }
        if ($sql) $sql= ' WHERE ' . implode(' AND ',$sql). ' ';
        else $sql = '';

        return $sql;
    }//}}}
    public static function getDefinition()/*{{{*/
    {
        return static::$definition;
    }/*}}}*/
    public function __construct( $id=null, $not_found_creates_new=true )/*{{{*/
    {
        if ($id !== null) $this->loadFromDatabase( $id, $not_found_creates_new );
        else $this->loadDefaults();
    }/*}}}*/
    public function __clone()  // {{{
    {
        Debug::log(get_called_class() . " object cloned");
        // called when someone clones this object
        // unset id, set is_new
        $this->is_new = true;
        $this->myData['id'] = null;
    } // }}}
    public function __get($name)  // {{{
    {

        // if not try to return myData[$name]
        // ...test if we've been initilaised.
        if ( ! is_array($this->myData) ) return null;
        // ...requested id alias?
        if ( static::$id_alias == $name ) $lookup = 'id';
        else $lookup = $name;
        // ...know this field?
        if ( array_key_exists($lookup, $this->myData)) return $this->myData[$lookup];

        return $this->getter($name);
        // other properties
// now getDefinition()        if ($name == 'definition')
// now getFieldNames()        if ($name == 'field_names')     return array_keys($this->myData);
// now unsavedChanges()        if ($name == 'unsaved_changes') return $this->unsaved_changes;
//        if ($name == 'is_new')           return $this->isNew(); // xxx should be moved to method so it can be over-ridden @todo
    } // }}}
    public function __set($name, $newValue)  // {{{
    {
        $lookup = ($name === static::$id_alias) ? 'id' : $name;
        if ( is_array($this->myData)
                && array_key_exists($lookup, $this->myData) )
        {
            if ( $this->myData[$lookup] !== $newValue )
            {
                // attempt cast, might throw exeption
                $cast = $this->castData( $lookup, $newValue );
                // only set if no exception
                $this->myData[$lookup] = $cast;
                $this->unsaved_changes = true; //only when changed.
            }
        } else {
            $this->setter($name,$newValue);
        }

    } // }}}
    //protected function getter($name){{{
    /** override this function if you want to use additional __get properties */
    protected function getter($name)
    {
            throw new Exception( get_class($this) . " does not know how to set property '$name'."
                            . " -- myData keys are: " . ($this->myData?implode(', ', array_keys($this->myData)):'undefined'));
                            }//}}}
    //protected function setter($name, $newValue){{{
    /** override this if you want to have custom __set-able properties */
    protected function setter($name, $newValue)
    {
        throw new Exception( get_class($this) . " does not know how to set property '$name'."
                . " -- myData keys are: " . ($this->myData?implode(', ', array_keys($this->myData)):'undefined'));
    } // }}}
    public function jsonSerialize()  // {{{
    {
        // nb. PHP 5.4 will call this automatically on json_encode();
        return $this->myData;
    } // }}}
    public function getFieldNames()/*{{{*/
    {
        return array_keys(static::$definition);
    }/*}}}*/
    public function unsavedChanges() //{{{
    {
        return $this->unsaved_changes;
    }/*}}}*/
    public function htmlSafeData() //{{{
    {
        $tmp = array();
        foreach ($this->myData as $k=>$v) if (! (is_object($v) || is_array($v))) $tmp[$k] = htmlspecialchars($v);
        return $tmp;
    }/*}}}*/
    public function loadFromDatabase( $givenId, $not_found_creates_new=true )/*{{{*/
    {
        // clear current data first
        $this->loadDefaults();

		$id = $this->castData('id', $givenId);

		if (!$id) throw new Exception( get_class($this)
                . "::loadFromDatabase called without proper id (given: '$givenId')");

        $stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
                "Fetch all fields from " . static::TABLE_NAME . " for record $id",
                "SELECT * FROM `" . static::TABLE_NAME . "` WHERE id = :id",
				array(':id'=>$id)));

        // no rows may be allowable if $not_found_creates_new
        if ($stmt->rowCount()==0 && $not_found_creates_new) return $this;

        // more than one record always wrong. Zero records wrong unless $not_found_creates_new set.
        if ($stmt->rowCount()!=1) throw new Exception(get_class($this)
                . "::loadFromDatabase failed to load single row from " . static::TABLE_NAME . " with id $id");

        $this->myData = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->is_new = false;
        $this->unsaved_changes = false;
        $this->castDbData();
        $this->loadPostprocess();

        // chainable
        return $this;
    }/*}}}*/
    //public function loadFromArray( Array $src, $is_new=false, $cast_data=2 )/*{{{*/
    /** load model data from array.
      *
      * @param bool $is_new sets internal flag for isNew(). e.g. false for data from db, true for other
      * @param $cast_data. 2=full cast (untrusted/user input), 1=db cast, 0=no casting
      */
    public function loadFromArray( Array $src, $is_new=false, $cast_data=2 )
    {
        $this->loadDefaults();

        if ($cast_data == self::CAST_FULL) {
            foreach ($src as $key=>$value)
                $this->$key = $value;

        } else {
            // trusted data
            $this->myData = $src;
        }

        $this->unsaved_changes = false;
        $this->is_new = $is_new;

        // if data from PDO then cast numbers to numbers
        if ($cast_data==self::CAST_DB) $this->castDbData();

        $this->loadPostprocess();
    }/*}}}*/
    public function loadDefaults()/*{{{*/
    {
        $this->is_new = true;
        $this->unsaved_changes = true;
        // note that these will be done through the setter function, so cast correctly.
        foreach (static::$definition as $field=>$details)
        {
            $this->myData[$field] = null;
            // if given a default value, use that.
            if (array_key_exists('default', $details)) $this->$field = $details['default'];
            // otherwise use zls.
            else $this->$field = '';
        }
        $this->loadPostprocess();
    }/*}}}*/
    public function isNew()//{{{
    {
        return $this->is_new;
    }//}}}
    public function save()/*{{{*/
    {
        // do nothing if unsaved changes
        if (!$this->unsaved_changes) return;

        $this->savePreprocess();
        if ( $this->isNew() ) $this->saveByInsert();
        else                  $this->saveByUpdate();

        $this->unsaved_changes = $this->is_new = false;

        $this->loadPostprocess();
        $this->savePostprocess();

        return $this;
    }/*}}}*/
    public function delete()/*{{{*/
    {

        if ( ! $id=$this->myData['id'] )
        {
            \ArtfulRobot\Debug::log("!! Warning: attempted to delete an unsaved " . get_class($this) . " object");
            return;
        }

        $stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
                    "delete row in " . static::TABLE_NAME,
                    "delete FROM `" . static::TABLE_NAME . "` WHERE id = :id",
                    array(':id' => $id )));

        // remove from cache, if exists.
        $object_type = get_called_class();
        if (isset(self::$cached[$object_type][$id])) unset(self::$cached[$object_type][$id]);

        // zero the id, so if saved it would create a new record
        $this->myData['id'] = 0;
        $this->is_new = true;
        $this->unsaved_changes = true;
    }/*}}}*/

    public function printData() // {{{
    {
        echo "<pre>Record properties:\n";
        var_dump($this->myData);
        echo "Other properties:\n";
        foreach ($this->model_props_r as $prop)
        {
            echo "$prop:\n";
            var_dump( $this->$prop );
        }
                echo "</pre>";
    } // }}}
    public function debug($msg=null) // {{{
    {
        if ($msg === null) $msg = "TOP " . get_class($this) . " myData:";
        /**
         *
         */
        \ArtfulRobot\Debug::log($msg, $this->myData);
    } // }}}

    //protected function loadPostprocess() {{{
    /** hook for altering object once loaded, e.g. if data needs formatting/unpacking.
      */
    protected function loadPostprocess()
    {
    }/*}}}*/
    //protected function savePreprocess() {{{
    /** hook for altering object before saving, e.g. serialize objects into fields
      */
    protected function savePreprocess()
    {
    }/*}}}*/
    //protected function savePostprocess() {{{
    /** hook for doing anything that needs doing after saving
      */
    protected function savePostprocess()
    {
    }/*}}}*/
    protected function saveByInsert() //{{{
    {
        // take a copy of the data array, less the id field
        $fields = array();
        foreach ($this->myData as $key=>$value)
        {
            if (static::$id_is_auto_increment && $key=='id') continue;
            $fields[] = "`$key`";
            $data[":$key"] = $value;
        }

        $sql = "INSERT INTO `" . static::TABLE_NAME . "` ("
            . implode(", ", $fields)
            . ") VALUES ( "
            . implode(", ", array_keys($data))
            . ")";
        $stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
                "Create new row in " . static::TABLE_NAME,
                $sql,
                $data));

        if ( $stmt->errorCode() != '00000' ) throw new Exception(
                get_class($this) . " failed to create row errorCode:' " . $stmt->errorCode() . "':" . print_r(static::getConnection()->errorInfo(),1));

        // we must reset our ID if auto_increment
        if (static::$id_is_auto_increment) {
            $this->id = static::getConnection()->lastInsertId();
        }
    }/*}}}*/
    protected function saveByUpdate() //{{{
    {
        $sql = array();
        foreach ($this->myData as $key=>$value)
        {
            if ($key!='id')
                $sql[]= "`$key` = :$key";

            $data[":$key"] = $value;
        }
        $sql = "UPDATE " . static::TABLE_NAME
            . " SET "
            . implode(", ", $sql)
            . " WHERE id = :id";
        $stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
                    "Update record {$this->myData['id']} in " . static::TABLE_NAME,
                    $sql,
                    $data));
    }/*}}}*/
    //protected function castData($name, $value)/*{{{*/
    /** cast supplied data into required type. Throw exception if can't be done */
    protected function castData($name, $value)
    {
        if (!array_key_exists($name, static::$definition))
            throw new Exception(get_class($this) . " no definition for field '$name'");
        extract(static::$definition[$name]);

        if ($value === null) {
            if (!$null) throw new Exception( get_class($this) . ' tried to set `' . $name . '` to null, but defined as not null');
            return null;

        } if ( $cast == 'int' ) {
            if (! is_int($value)) $value = (int)$value;
            return $value;

        } elseif ( $cast == 'int_unsigned' ) {
            if (! is_int($value)) $value = (int)$value;
            if ($value<0) throw new Exception( get_class($this) . " tried to set $name to $value, but is unsigned");
            return $value;

        } elseif ( $cast == 'float' ) {
            $value = (double) $value;
            return $value;

        } elseif ($cast == 'string') {
            if (!is_string($value)) $value = (string) $value;
            return $value;

        } elseif ($cast == 'blob') {
            return $value;
        }
        elseif ($cast == 'datetime'
                || $cast == 'date'
                || $cast == 'time'
                )
        {
            if ($value == '')
            {
                // if null allowed, then zls = null
                if ($null) return null;
                else throw new Exception( get_class($this) . " tried to set $name to '', but cannot be null. Unclear what you wanted (may have defaulted to 1 jan 1970)");
            }
            if ($cast == 'time' && $value!='now')
                $value = "1 Jan 1970 $value";

            $time=strtotime($value);
            if ($time ===false) throw new Exception( get_class($this) . " tried to set $name to unparseable $cast '$value'");

            if ($cast == 'time') return date( self::FORMAT_TIME, $time);
            if ($cast == 'date') return date( self::FORMAT_DATE, $time);
            if ($cast == 'datetime') return date( self::FORMAT_DATETIME, $time);

        } elseif ($cast == 'enum') {
            if (!isset($enum) || !is_array($enum) || count($enum)==0)
                throw new Exception( get_class($this) . " tried to set $name field is enum but without enum values");
            if (! in_array($value, $enum))
                throw new Exception( get_class($this) . " tried to set $name field to '$value' but invalid. Valid values are " . implode(", ", $enum));
            return $value;

        } elseif ($cast == 'set') {
            if (!isset($values) || !is_array($values) || count($values)==0) {
                throw new Exception( get_class($this) . " tried to set $name field is SET but without values");
			}
			// if value(s) submitted, check each. Empty set is always valid.
			if ($value) {
				foreach (explode(',',$value) as $_) {
					if (! in_array($_, $values)) {
						throw new Exception( get_class($this) . " tried to set $name field to '$value' but '$_' is invalid. Valid values are " . implode(", ", $values));
					}
				}
			}
            return $value;

        } elseif ($cast == 'bool') {
            return (bool) $value;
        }
        throw new Exception( get_class($this) . " does not know type '$cast'");
    }/*}}}*/
    //protected function castDbData()/*{{{*/
    /** convert data from PDO to correct type.
      *
      * PHP's PDO (5.3) driver returns strings for everything.
      * This is a quick conversion run after loading data from db
      * for integers and floats.
     */
    protected function castDbData()
    {
        foreach (static::$definition as $field=>$definition) {
           if ($this->myData[$field] === null) continue;
           switch($definition['cast']) {

               case 'int' :
               case 'int_unsigned' :
               case 'bool' :
                   $this->myData[$field] = (int) $this->myData[$field];
				   if ($definition['cast'] == 'int_unsigned'
					   && $this->myData[$field]<0 ) {
					   throw new Exception("Attempted to cast negative number on unsigned field $name");
				   }
                   break;

               case 'float' :
                   $this->myData[$field] = (float) $this->myData[$field];
           }
        }
    }/*}}}*/
}

