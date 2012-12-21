<?php
namespace ArtfulRobot;

abstract class PDO_ModelMultiplePK extends \ArtfulRobot\PDO_Model
{
	/*  Nb. class constants must be overridden in extended classes 
	   (php5.2 can't cope with this, so they're normal properties here) */
	/** @var array(string, string...) of fields that form the primary key */
	protected $pk_fields=array();

	public function __construct( $id=null, $not_found_creates_new=true  )/*{{{*/
	{
		$this->db_connect();
		if (is_array($id) && $id) $this->loadFromDatabase( $id, $not_found_creates_new=true  );
		else $this->loadDefaults();
	}/*}}}*/
	public function loadFromDatabase( $id, $not_found_creates_new=true  )/*{{{*/
	{
		// clear current data first
		$this->loadDefaults();

		if (!$id || !is_array($id)) throw new Exception( get_class($this) 
				. "::loadFromDatabase called without proper id");

		$params = array();
		$pk_where = $this->preparePK($params, $id);
		$stmt = $this->conn->prepAndExecute( new \ArtfulRobot\PDO_Query(
				"Fetch all fields from $this->TABLE_NAME for record $id",
				"SELECT * FROM `$this->TABLE_NAME` WHERE $pk_where",
				$params));

		// no rows may be allowable if $not_found_creates_new
		if ($stmt->rowCount()==0 && $not_found_creates_new) return $this;
				
		// more than one record always wrong. Zero records wrong unless $not_found_creates_new set.
		if ($stmt->rowCount()!=1) throw new Exception(get_class($this) 
				. "::loadFromDatabase failed to load single row from $this->TABLE_NAME with id $id");

		$this->myData = $stmt->fetch(PDO::FETCH_ASSOC);
		$this->is_new = false;
		$this->unsaved_changes = false;

		$this->loadPostprocess();
	}/*}}}*/
	public function loadDefaults()/*{{{*/
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
		$this->loadPostprocess();
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
	protected function saveByUpdate()/*{{{*/
	{
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
		$stmt = $this->conn->prepAndExecute( new \ArtfulRobot\PDO_Query(
				"Update record {$this->myData['id']} in $this->TABLE_NAME",
				$sql,
				$data));
		$this->unsaved_changes = false;
		$this->is_new = false;

		$this->loadPostprocess();
	}/*}}}*/
	protected function saveByInsert()/*{{{*/
	{
		// take a copy of the data array
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
		$stmt = $this->conn->prepAndExecute( new \ArtfulRobot\PDO_Query(
				"Create new row in $this->TABLE_NAME",
				$sql,
				$data));
	}/*}}}*/
	public function delete()/*{{{*/
	{
		if (! $this->TABLE_NAME) throw new Exception( get_class($this) . " trying to use abstract save method but TABLE_NAME is not defined");

		$params = array();
		$pk_where = $this->preparePK($params);
		$stmt = $this->conn->prepAndExecute( new \ArtfulRobot\PDO_Query(
					"Delete row in $this->TABLE_NAME",
					"DELETE FROM `$this->TABLE_NAME` WHERE $pk_where",
					$params));

		$this->unsaved_changes = $this->is_new = true;
	}/*}}}*/
}

