<?php
abstract class ARL_PDO_Model/*{{{*/
{
	const FORMAT_TIME = 'G.i.s';
	const FORMAT_DATE = 'Y-m-d';
	const FORMAT_DATETIME = 'Y-m-d G.i.s';

	/** @var data array */
	protected $myData ;
	/** Definitions must be specified as an array indexed by fieldname
	  * of arrays including keys cast, size, null
	  * 
	  * @var definition */
	protected $definition;
	/** @var bool Nb. also true for new records */
	protected $unsaved_changes=false;
	/** @var array additional properties gettable from extended getter method */
	protected $model_props_r=array();
	/** @var array additional properties settable from extended setter method */
	protected $model_props_w=array();

	/** @var PDO connection */
	protected $conn;

	/*  Nb. class constants must be overridden in extended classes 
	   (php5.2 can't cope with this, so they're normal properties here) */
	/** @var string optional alias, e.g. surveyId */
	protected $ID_ALIAS = false;
	/** @var string table name used in default save() and load_from_database() methods */
	protected $TABLE_NAME = false;

	public function __construct( $id=null )/*{{{*/
	{
		$this->db_connect();
		if (is_int($id)) $this->load_from_database( $id );
		else $this->load_defaults();
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
		if ( $this->ID_ALIAS == $name ) $lookup = 'id';
		else $lookup = $name;
		// ...know this field?
        if ( array_key_exists($lookup, $this->myData)) return $this->myData[$lookup];

		// other properties
		if ($name == 'definition')      return $this->definition;
		if ($name == 'field_names')     return array_keys($this->myData);
		if ($name == 'unsaved_changes') return $this->unsaved_changes;
		if ($name == 'is_new')          return (! $this->myData['id'] );

		if ($name== 'htmlSafeData')
		{
			$tmp = array();
			foreach ($this->myData as $k=>$v) if (! (is_object($v) || is_array($v))) $tmp[$k] = htmlspecialchars($v);
			return $tmp;
		}

		// unknown
        throw new Exception( get_class($this) . " does not have requested '$name' property");
    } // }}}
	public function __set($name, $newValue)  // {{{
	{
		if (in_array($name, $this->model_props_w)) return $this->setter($name,$newValue);
		$lookup = ($name === $this->ID_ALIAS) ? 'id' : $name;
		if ( is_array($this->myData)
				&& array_key_exists($lookup, $this->myData) )
		{
			if ( $this->myData[$lookup] !== $newValue )
			{
				$this->myData[$lookup] = $this->cast_data( $lookup, $newValue );
				$this->unsaved_changes = true; //only when changed.
			}
			return;
		}

		throw new Exception( get_class($this) . " does not know how to set property '$name'."
				. " -- myData keys are: " . ($this->myData?implode(', ', array_keys($this->myData)):'undefined'));

    } // }}}
	abstract protected function getter($name);
	abstract protected function setter($name, $newValue) ;
	/** this function must initialise an ARL_PDO object in $this->conn */
	abstract protected function db_connect();
	public function load_from_database( $id )/*{{{*/
	{
		// clear current data first
		$this->load_defaults();

		if (! $this->TABLE_NAME) throw new Exception( get_class($this) . " trying to use abstract save method but TABLE_NAME is not defined");
		if (($id = (int)$id)<1) throw new Exception( get_class($this) . "::load_from_database called without proper id");

		$stmt = $this->conn->prep_and_execute( new ARL_PDO_Query(
				"Fetch all fields from $this->TABLE_NAME for record $id",
				"SELECT * FROM `$this->TABLE_NAME` WHERE id = $id"));
		$this->myData = $stmt->fetch(PDO::FETCH_ASSOC);
	}/*}}}*/
	public function load_from_array( Array $src )/*{{{*/
	{
		$this->load_defaults();
		foreach ($src as $key=>$value)
			$this->$key = $value;
		/*
		foreach (array_keys($this->myData) as $key)
			if (array_key_exists($key, $src))
				$this->$key = $src[$key];
				*/
	}/*}}}*/
	public function load_defaults()/*{{{*/
	{
		$this->myData = array('id' => 0);
		// note that these will be done through the setter function, so cast correctly.
		foreach ($this->definition as $field=>$details)
		{
			$this->myData[$field] = null;
			// if given a default value, use that.
			if (array_key_exists('default', $details)) $this->$field = $details['default'];
			// otherwise use zls.
			else $this->$field = '';
		}
	}/*}}}*/
	public function save()/*{{{*/
	{
		if (! $this->TABLE_NAME) throw new Exception( get_class($this) . " trying to use abstract save method but TABLE_NAME is not defined");
		// do nothing if unsaved changes
		if (!$this->unsaved_changes) return;

		// new data - build insert
		if ( ! $this->myData['id'] ) /*{{{*/
		{
			// take a copy of the data array, less the id field
			$fields = array();
			foreach ($this->myData as $key=>$value)
			{
				if ($key=='id') continue;
				$fields[] = "`$key`";
				$data[":$key"] = $value;
			}

			$sql = "INSERT INTO `" . $this->TABLE_NAME . "` ("
				. implode(", ", $fields)
				. ") VALUES ( "
				. implode(", ", array_keys($data))
				. ")";
			$stmt = $this->conn->prep_and_execute( new ARL_PDO_Query(
					"Create new row in $this->TABLE_NAME",
					$sql,
					$data));
			// if ( ! $stmt ) throw new Exception( get_class($this) . " failed to create row :" . print_r($this->conn->errorInfo(),1));

			$_ = $this->conn->lastInsertId();
			ARL_Debug::log("TOP lastInsertId" ,$_);
			$this->myData['id'] = (int) $_;
			if (! $this->myData['id']) throw new Exception( get_class($this) . " failed to create row");
		}/*}}}*/
		else/*{{{*/
		{
			// update
			$sql = array();
			foreach ($this->myData as $key=>$value)
			{
				if ($key!='id') 
				{
					$sql[]= "`$key` = :$key";
				}
				$data[":$key"] = $value;
			}
			$sql = "UPDATE " . $this->TABLE_NAME  . " "
				. "SET "
				. implode(", ", $sql)
				. " WHERE id = :id";
			$stmt = $this->conn->prep_and_execute( new ARL_PDO_Query(
					"Update record {$this->myData['id']} in $this->TABLE_NAME",
					$sql,
					$data));
		}/*}}}*/
	}/*}}}*/
	public function delete()/*{{{*/
	{
		if (! $this->TABLE_NAME) throw new Exception( get_class($this) . " trying to use abstract save method but TABLE_NAME is not defined");
		// new data - build insert
		if ( ! $this->myData['id'] ) return;

		$stmt = $this->conn->prep_and_execute( new ARL_PDO_Query(
					"Delete row in $this->TABLE_NAME",
					"DELETE FROM `$this->TABLE_NAME` WHERE id = :id",
					array(':id' => $this->myData['id'] )));

		// zero the id, so if saved it would create a new record
		$this->myData['id'] = 0;
	}/*}}}*/

	public function print_data() // {{{
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
		ARL_Debug::log($msg, $this->myData);
	} // }}}

	//private function cast_data($name, $value)/*{{{*/
	/** cast supplied data into required type. Throw exception if can't be done */
	private function cast_data($name, $value)
	{
		if (!array_key_exists($name, $this->definition))
			throw new Exception(get_class($this) . " no definition for field '$name'");
		extract($this->definition[$name]);

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
}/*}}}*/

// class ARL_PDO extends PDO/*{{{*/
/** class to add functionality to PDO
 */
class ARL_PDO extends PDO
{
	const FORMAT_TIME = 'G.i.s';
	const FORMAT_DATE = 'Y-m-d';
	const FORMAT_DATETIME = 'Y-m-d G.i.s';
	//public function fetch_single(ARL_PDO_Query $query, $col_name=null)/*{{{*/
	/** Run the ARL_PDO_Query supplied and return first (or $col_name) field from first row */
	public function fetch_single(ARL_PDO_Query $query, $col_name=null)
	{
		$stmt = $this->prep_and_execute($query);

		if (!$stmt) $output = null;
		elseif ($col_name===null) $output = $stmt->fetch( PDO::FETCH_COLUMN );
		else
		{
			$row = $stmt->fetch( PDO::FETCH_ASSOC );
			$output = ARL_Array::value( $col_name, $row);
		}

		ARL_Debug::log("! fetch_single returning: $output");
		return $output;
	}/*}}}*/
	public function fetch_row_assoc(ARL_PDO_Query $query )/*{{{*/
	{
		ARL_Debug::log(">>$query->comment");

		$stmt = $this->prep_and_execute( $query );
		if (!$stmt) 
		{
			ARL_Debug::log("<< failed to run");
			return null;
		}

		$output = $stmt->fetch( PDO::FETCH_ASSOC );

		ARL_Debug::log("<< row fetched");
		return $output;
	}/*}}}*/
	public function fetch_rows_assoc(ARL_PDO_Query $query , $key_field = null )/*{{{*/
	{
		ARL_Debug::log(">>$query->comment");

		$stmt = $this->prep_and_execute( $query );
		if (!$stmt) 
		{
			ARL_Debug::log("<< failed to run");
			return null;
		}
		$output = array();

		if ($key_field) while ($row = $stmt->fetch( PDO::FETCH_ASSOC ))
			$output[ $row[$key_field] ] = $row;
		else while ($row = $stmt->fetch( PDO::FETCH_ASSOC ))
			$output[] = $row;

		ARL_Debug::log("<< " . count($output) . " rows fetched");
		return $output;
	}/*}}}*/
	// public function fetch_rows_single(ARL_PDO_Query $query , $col_name = null, $key_field = null )/*{{{*/
	/** fetch array of single fields (defaults to first field), optionally indexed by another field 
	 * 
	 *  Nb. specifying key_field is quite different to not.
	 */
	public function fetch_rows_single(ARL_PDO_Query $query , $col_name = null, $key_field = null )
	{
		ARL_Debug::log(">>$query->comment");

		$stmt = $this->prep_and_execute( $query );
		if (!$stmt) 
		{
			ARL_Debug::log("<< failed to run");
			return null;
		}
		$output = array();

		if ($col_name === null && $key_field===null)
			$output = $stmt->fetchAll(PDO::FETCH_COLUMN);
		elseif ($col_name === null && $key_field!==null)
			throw new Exception("fetch_rows_single requires \$col_name if \$key_field given");
		else
		{
			if ($key_field) while ($row = $stmt->fetch( PDO::FETCH_ASSOC ))
				$output[ $row[$key_field] ] = $row[ $col_name ];
			else while ($row = $stmt->fetch( PDO::FETCH_ASSOC ))
				$output[] = $row[ $col_name ];
		}

		ARL_Debug::log("<< " . count($output) . " rows fetched");
		return $output;
	}/*}}}*/
	// public function fetch_affected_count(ARL_PDO_Query $query )/*{{{*/
	/** fetch number of rows affected by the INSERT, DELETE, UPDATE query given
	 */
	public function fetch_affected_count(ARL_PDO_Query $query )
	{
		ARL_Debug::log(">>$query->comment");

		$stmt = $this->prep_and_execute( $query );
		if (!$stmt) 
		{
			ARL_Debug::log("<< failed to run");
			return null;
		}
		
		$count = $stmt->rowCount();
		ARL_Debug::log("<< $count affected");
		return $count;
	}/*}}}*/
	// public function fetch_insert_id(ARL_PDO_Query $query )/*{{{*/
	/** run an INSERT query and return just the new insert_id
	 */
	public function fetch_insert_id(ARL_PDO_Query $query )
	{
		ARL_Debug::log(">>$query->comment");

		$stmt = $this->prep_and_execute( $query );
		if (!$stmt) 
		{
			ARL_Debug::log("<< failed to run");
			return null;
		}
		
		$id = $this->lastInsertId();
		ARL_Debug::log("<< id: $id");
		return $id;
	}/*}}}*/
	//public function fetch_found_rows()/*{{{*/
	/** executes SELECT FOUND_ROWS() which will return the count of rows from the last SQL_CALC_FOUND_ROWS query
	 */
	public function fetch_found_rows()
	{
		return $this->fetch_single(new ARL_PDO_Query( "Get FOUND_ROWS()", "SELECT FOUND_ROWS();"));
	}/*}}}*/
	//public function prep_and_execute( ARL_PDO_Query $query )/*{{{*/
	/** prepare the ARL_PDO_Query given, then execute it and return a PDOStatement object
	 */
	public function prep_and_execute( ARL_PDO_Query $query )
	{
		ARL_Debug::log( "prep_and_execute: $query->comment",  strtr($query->sql, array("\t" => '  ')) );
		if (! $query->params) $stmt = $this->query($query->sql);
		else
		{
			ARL_Debug::log("params: ", $query->params);
			ARL_Debug::log("sql: ", $query->sql);
			$stmt = $this->prepare($query->sql);
			if (! $stmt->execute($query->params) )
				throw new Exception("Failed to execute statement (SQL error " . $stmt->errorCode().")");
		}
		return $stmt;
	}/*}}}*/

	//public static function cast_datetime( $date, $format='datetime', $false_value=null)/*{{{*/
	/** prepare a string that should be a date|datetime|time
	 * 
	 * @param string $date
	 * @param string $format one of datetime(default), date or time
	 * @param mixed $false_value returned if $date is ZLS/null/0/false
	 */
	public static function cast_datetime( $date, $format='datetime', $false_value=null)
	{
		// nothing sent?
		if (! $date) 
		{
			// special case, "now" strtotime will handle this nicely.
			if ($false_value != 'now') return $false_value;
		}

		$time = strtotime($date);
		if ($time === false) throw new Exception("ARL_PDO::cast_datetime cannot parse $date");

		if     ($format == 'datetime') $format = self::FORMAT_DATETIME;
		elseif ($format == 'date') $format = self::FORMAT_DATE;
		elseif ($format == 'time') $format = self::FORMAT_TIME;

		return date($format, $time);
	}/*}}}*/
}/*}}}*/

//class ARL_PDO_Query/*{{{*/
/** class to add features to standard sql queries, used by various ARL_PDO methods
 * 
 * For readability this class insists on a comment for the sql that should
 * explain what's supposed to be achived by the SQL
 *
 * sql and optionally, array of named parameters are self-explanatory.
 *
 * However, this class also imlpemements prepared statements for arrays of data
 * on the input parameters.
 *
 * Example: fetch a number of specified records:
 *
 * $records = $my_ARL_PDO->fetch_rows_assoc( new ARL_PDO_Query(
 *  "Fetch records 1,2,5",
 * 	"SELECT * FROM table WHERE id IN (:id_list) AND lname = :lname"
 * 	array( ':id_list' => array(1,2,5) ) ) );
 */ 
class ARL_PDO_Query
{
	private $comment;
	private $sql;
	private $params;
	private $array_params;
	function __construct($comment, $sql, $params=null, $has_array_params='deprecated')/*{{{*/
	{
		$this->comment = $comment;
		$this->sql     = $sql;
		$this->params  = $params;

		if ( ! is_array($params) ) return;

		// scan params for array values
		foreach ($this->params as $key=>$value)
		{
			if (!is_array($value)) continue;

			$c = count($value);
			if ($c==0) $this->params[$key] = null;
			elseif ($c==1) $this->params[$key] = reset($value);
			else
			{
				$i=0;
				$replacement_params = array();
				while( $single_val = array_shift($value) )
				{
					$single_key = $key . "__" . $i++;
					$this->params[$single_key] = $single_val;
					$replacement_params[] = $single_key;
				}
				$this->sql = preg_replace("/" . preg_quote($key,'/') . '\b/', implode(", ", $replacement_params), $this->sql);
				// remove original parameter
				unset($this->params[$key]);
			}
		}
	}/*}}}*/
	public function __get($field)/*{{{*/
	{
		if ($field == 'comment' || $field == 'sql' || $field =='params' ) return $this->$field;
		throw new Exception("Unknown property '$field' in class " . __CLASS__ );
	}/*}}}*/

	public function __toString()
	{
		return "ARL_PDO_Query: \n"
			. strtr($this->sql, array("\t"=>"  "))
			. "\nParams:\n" 
			. print_r($this->params,1);
	}
}/*}}}*/

?>
