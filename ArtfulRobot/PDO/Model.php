<?php
namespace ArtfulRobot;

abstract class PDO_Model
{
    const FORMAT_TIME = 'G.i.s';
    const FORMAT_DATE = 'Y-m-d';
    const FORMAT_DATETIME = 'Y-m-d G.i.s';
    const TABLE_NAME = '';

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

    /** Must return a \ArtfulRobot\PDO object */
    abstract static protected function getConnection();
    static public function buildCollection( $filters )//{{{
    {
        // filters must be field=>value
        $collection = new ARL\Collection($this);

        $sql=$params = array();
        foreach ($filters as $key=>$value){
            $params[":$key"] = $value;
            $sql[] = "`$key` = :$key";
        }
        if ($sql) $sql= "WHERE " . implode(' AND ',$sql);
        else $sql = '';

        $stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
                "Fetch records from " . static::TABLE_NAME,
                "SELECT * FROM `" . static::TABLE_NAME . "` $sql"));
        if ($stmt->errorCode()!='00000') 
            throw new Exception("PDO error: " . print_r($stmt->errorInfo(),1));

        while ($row = $stmt->fetch( \PDO::FETCH_ASSOC )) {
            // create an object of the class
            $obj = new static; 
            $obj->loadFromArray($row,false,false);
            $collection->append($obj, $obj->id);
            unset($obj);
        }
        return $collection;
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
        $this->loadPostprocess();

        // chainable
        return $this;
    }/*}}}*/
    public function loadFromArray( Array $src, $is_new=false, $cast_data=true )/*{{{*/
    {
        $this->loadDefaults();
        if ($cast_data) {
            foreach ($src as $key=>$value)
                $this->$key = $value;
        } else {
            $this->myData = $src;
        }
        /* hmmm, or maybe this is better:
        foreach (array_keys($this->myData) as $key)
            if (array_key_exists($key, $src))
                $this->$key = $src[$key];
                */
        $this->unsaved_changes = false;
        $this->is_new = $is_new;

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

        return $this;
    }/*}}}*/
    public function delete()/*{{{*/
    {
        // new data
        if ( ! $this->myData['id'] )
        {
            \ArtfulRobot\Debug::log("TOP Warning: attempted to delete an unsaved " . get_class($this) . " object");
            return;
        }

        $stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
                    "delete row in " . static::TABLE_NAME,
                    "delete FROM `" . static::TABLE_NAME . "` WHERE id = :id",
                    array(':id' => $this->myData['id'] )));

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
}

