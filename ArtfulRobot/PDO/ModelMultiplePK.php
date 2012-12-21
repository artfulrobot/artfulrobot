<?php
namespace ArtfulRobot;

abstract class PDO_ModelMultiplePK extends ARL_PDO_Model
{
	/*  Nb. class constants must be overridden in extended classes 
	   (php5.2 can't cope with this, so they're normal properties here) */
	/** @var array(string, string...) of fields that form the primary key */
	protected $pk_fields=array();

	public function __construct( $id=null )/*{{{*/
	{
		$this->db_connect();
		if (is_array($id) && $id) $this->load_from_database( $id );
		else $this->load_defaults();
	}/*}}}*/
	public function load_from_database( $id )/*{{{*/
	{
		// clear current data first
		$this->load_defaults();

		if (! $this->TABLE_NAME) throw new Exception( get_class($this) . " trying to use abstract save method but TABLE_NAME is not defined");
		if (!$id || !is_array($id)) throw new Exception( get_class($this) . "::load_from_database called without proper id");

		$params = array();
		$pk_where = $this->preparePK($params, $id);
		$stmt = $this->conn->prep_and_execute( new ARL_PDO_Query(
				"Fetch all fields from $this->TABLE_NAME for record $id",
				"SELECT * FROM `$this->TABLE_NAME` WHERE $pk_where",
				$params));
		$this->myData = $stmt->fetch(PDO::FETCH_ASSOC);

		$this->load_postprocess();
	}/*}}}*/
	public function load_defaults()/*{{{*/
	{
		$this->myData = array();
		// note that these will be done through the setter function, so cast correctly.
		foreach ($this->definition as $field=>$details)
		{
			$this->myData[$field] = null;
			// if given a default value, use that.
			if (array_key_exists('default', $details)) $this->$field = $details['default'];
			// otherwise use zls.
			else $this->$field = '';
		}
		$this->load_postprocess();
	}/*}}}*/
	//protected function preparePK(&$params, $id=null){{{
	/** if $id is not given, the current values are used.
	  * outputs sql WHERE clause to identify the record by PK
	  * populates params[pk_FIELDN] with PK values
	  */
	protected function preparePK(&$params, $id=null)
	{
		$sql = array();
		foreach ($this->pk_fields as $field) {
			if (!isset($id[$field])) throw new Exception( get_class($this) . " no $field given - required for PK lookup");
			$params[":pk_$field"] = $id===null 
				? $this->myData[$field]
				: $id[$field];
			$sql[] = "`$field` = :pk_$field";
		}
		return "(" . implode(' AND ', $sql) . ")";
	}/*}}}*/
	//protected function getPK(&$params, $id=null){{{
	protected function getPK()
	{
		$pk = array();
		foreach ($this->pk_fields as $field)
			$pk[$field] = $this->myData[$field];
		return $pk;
	}/*}}}*/
	// save is update. To insert use insert();
	public function save()/*{{{*/
	{
		if (! $this->TABLE_NAME) throw new Exception( get_class($this) . " trying to use abstract save method but TABLE_NAME is not defined");

		// do nothing if unsaved changes
		if (!$this->unsaved_changes) return;

		$this->save_preprocess();

		// update
		$sql = array();
		foreach ($this->myData as $key=>$value)
		{
			if (! in_array($key, $this->pk_fields))
			{
				$sql[]= "`$key` = :$key";
			}
			$data[":$key"] = $value;
		}
		$pk_where = $this->preparePK($data);

		$sql = "UPDATE " . $this->TABLE_NAME 
			. " SET " . implode(", ", $sql)
			. " WHERE $pk_where";
		$stmt = $this->conn->prep_and_execute( new ARL_PDO_Query(
				"Update record {$this->myData['id']} in $this->TABLE_NAME",
				$sql,
				$data));

		$this->load_postprocess();

		// for conformity, return the pk values
		return $this->getPK();
	}/*}}}*/
	public function insert()/*{{{*/
	{
		if (! $this->TABLE_NAME) throw new Exception( get_class($this) . " trying to use abstract save method but TABLE_NAME is not defined");

		// do nothing if unsaved changes
		if (!$this->unsaved_changes) return;

		$this->save_preprocess();

		// new data - build insert
		// take a copy of the data array, less the id field
		$fields = array();
		foreach ($this->myData as $key=>$value)
		{
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

		$this->load_postprocess();


		// for conformity, return the pk values
		return $this->getPK();
	}/*}}}*/
	public function delete()/*{{{*/
	{
		if (! $this->TABLE_NAME) throw new Exception( get_class($this) . " trying to use abstract save method but TABLE_NAME is not defined");

		$params = array();
		$pk_where = $this->preparePK($params);
		$stmt = $this->conn->prep_and_execute( new ARL_PDO_Query(
					"Delete row in $this->TABLE_NAME",
					"DELETE FROM `$this->TABLE_NAME` WHERE $pk_where",
					$params));

	}/*}}}*/
}

