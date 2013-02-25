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
    /** @var array additional properties gettable from extended getter method */
    protected $model_props_r=array();
    /** @var array additional properties settable from extended setter method */
    protected $model_props_w=array();

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
    //static public function buildCollection( $filters, $order )//{{{
    /**
      * return a Collection object 
      */
    static public function buildCollection( $filters, $order=null )
    {
        $collection = new Collection();

        $sql = static::sqlWhere($params, $filters);
        
        if ($order === null) $order = static::$default_order;
        if ($order) $sql .= " ORDER BY $order";

        $stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
                "Fetch records from " . static::TABLE_NAME,
                "SELECT * FROM `" . static::TABLE_NAME . "` $sql", $params));
        if ($stmt->errorCode()!='00000') 
            throw new Exception("PDO error: " . print_r($stmt->errorInfo(),1));

        while ($row = $stmt->fetch( \PDO::FETCH_ASSOC )) {
            // create an object of the class
            $obj = new static; 
            $obj->loadFromArray($row,false,self::CAST_DB);
            $collection->append($obj, $obj->id);
            unset($obj);
        }
        return $collection;
    }//}}}
    //static public function bulkDelete( $filters )//{{{
    /**
      * return a Collection object 
      * 
      * $filters is an array of 
      *     field => 'value'
      * or  field => { operator:'>=', value: 'value' }
      * 
      * filters are ANDed together.
      */
    static public function bulkDelete( $filters )
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
    //static public function loadCached( $id )//{{{
    /** Returns cache or creates object 
     *  Used to load models from the database; ensures all php models for one record are shared
     */
    static public function loadCached( $id, $data=null )
    {
        $object_type = get_called_class();
        if (isset(self::$cached[$object_type][$id])) return self::$cached[$object_type][$id];

        // create and populate new object
        $obj = new static; 
        if (is_array($data)) $obj->loadFromArray($data);
        else $obj->loadFromDatabase($id,false);

        // cache and return
        return self::$cached[$object_type][$id] = $obj;
    }//}}}
    //static public function sqlWhere( &$params, $filters )//{{{
    /** Returns the WHERE clause (or '') based on $filters
      * 
      * $filters is an array of 
      *     field => 'value'
      * or  field => { operator:'>=', value: 'value' }
      * 
      * filters are ANDed together.
     */
    static public function sqlWhere( &$params, $filters )
    {
        $sql=$params = array();
        foreach ($filters as $key=>$filter){
            if (! is_array($filter)) {
                $params[":$key"] = $filter;
                $sql[] = "`$key` = :$key";
            } else {
                $params[":$key"] = $filter['value'];
                $sql[] = "`$key` $filter[operator] :$key";
            }
        }
        if ($sql) $sql= ' WHERE ' . implode(' AND ',$sql). ' ';
        else $sql = '';

        return $sql;
    }//}}}
    public function __construct( $id=null, $not_found_creates_new=true )/*{{{*/
    {
        if (is_int($id)) $this->loadFromDatabase( $id, $not_found_creates_new );
        else $this->loadDefaults();
    }/*}}}*/
    public function __get($name)  // {{{
    {
        // try over-ridden getter function first.
        if(in_array($name, $this->model_props_r))
            return $this->getter($name);

        // if not try to return myData[$name]
        // ...test if we've been initilaised.
        if ( ! is_array($this->myData) ) return null;
        // ...requested id alias?
        if ( static::$id_alias == $name ) $lookup = 'id';
        else $lookup = $name;
        // ...know this field?
        if ( array_key_exists($lookup, $this->myData)) return $this->myData[$lookup];

        // other properties
// now getDefinition()        if ($name == 'definition') 
// now getFieldNames()        if ($name == 'field_names')     return array_keys($this->myData);
// now unsavedChanges()        if ($name == 'unsaved_changes') return $this->unsaved_changes;
//        if ($name == 'is_new')           return $this->isNew(); // xxx should be moved to method so it can be over-ridden @todo 
        // htmlSafeData now moved too

        // unknown
        throw new Exception( get_class($this) . " does not have requested '$name' property");
    } // }}}
    public function __set($name, $newValue)  // {{{
    {
        if (in_array($name, $this->model_props_w)) return $this->setter($name,$newValue);
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
            return;
        }

        throw new Exception( get_class($this) . " does not know how to set property '$name'."
                . " -- myData keys are: " . ($this->myData?implode(', ', array_keys($this->myData)):'undefined'));

    } // }}}
    abstract protected function getter($name);
    abstract protected function setter($name, $newValue) ;
    public function jsonSerialize()  // {{{
    {
        // nb. PHP 5.4 will call this automatically on json_encode();
        return $this->myData;
    } // }}}
    public function getDefinition()/*{{{*/
    {
        return static::$definition;
    }/*}}}*/
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
    public function loadFromDatabase( $id, $not_found_creates_new=true )/*{{{*/
    {
        // clear current data first
        $this->loadDefaults();

        if (($id = (int)$id)<1) throw new Exception( get_class($this) 
                . "::loadFromDatabase called without proper id");

        $stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
                "Fetch all fields from " . static::TABLE_NAME . " for record $id",
                "SELECT * FROM `" . static::TABLE_NAME . "` WHERE id = $id"));

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
            \ArtfulRobot\Debug::log("TOP Warning: attempted to delete an unsaved " . get_class($this) . " object");
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

        if ($value === null)
        {
            if (!$null) throw new Exception( get_class($this) . ' tried to set `' . $name . '` to null, but defined as not null');
            return null;
        }
        if ( $cast == 'int' )
        {
            if (! is_int($value)) $value = (int)$value;
            return $value;
        }
        elseif ( $cast == 'int_unsigned' )
        {
            if (! is_int($value)) $value = (int)$value;
            if ($value<0) throw new Exception( get_class($this) . " tried to set $name to $value, but is unsigned");
            return $value;
        }
        elseif ( $cast == 'float' )
        {
            $value = (double) $value;
            return $value;
        }
        elseif ($cast == 'string')
        {
            if (!is_string($value)) $value = (string) $value;
            return $value;
        }
        elseif ($cast == 'blob')
        {
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
        }
        elseif ($cast == 'enum')
        {
            if (!isset($enum) || !is_array($enum) || count($enum)==0)
                throw new Exception( get_class($this) . " tried to set $name field is enum but without enum values");
            if (! in_array($value, $enum))
                throw new Exception( get_class($this) . " tried to set $name field to '$value' but invalid. Valid values are " . implode(", ", $enum));
            return $value;
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
                   $this->myData[$field] = (int) $this->myData[$field];
                   break;

               case 'float' :
                   $this->myData[$field] = (float) $this->myData[$field];
           }
        }
    }/*}}}*/
}

