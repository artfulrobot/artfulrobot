<?php
namespace ArtfulRobot;

abstract class PDO_ModelMultiplePK extends \ArtfulRobot\PDO_Model
{
	/** @var array(string, string...) of fields that form the primary key */
	protected $pk_fields=array();

    static public function buildCollection( $filters )//{{{
    {
        // filters must be field=>value
        $collection = new Collection($this);

        $sql=$params = array();
        foreach ($filters as $key=>$value){
            $params[":$key"] = $value;
            $sql[] = "`$key` = :$key";
        }
        if ($sql) $sql= "WHERE " . implode(' AND ',$sql);
        else $sql = '';
        $sql = "SELECT * FROM `" . static::TABLE_NAME ."` $sql";
        $stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
                "Fetch records from " . static::TABLE_NAME,
                $sql, $params));
        if ($stmt->errorCode()!='00000') 
            throw new Exception("PDO error: " . print_r($stmt->errorInfo(),1));

        while ($row = $stmt->fetch( \PDO::FETCH_ASSOC )) {
            // create an object of the class
            $obj = new static; 
            $obj->loadFromArray($row,false,false);
            // these lines differ from main code
            $id = implode("\A", $obj->getPK());
            $collection->append($obj,$id);
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
        $id = implode("\A", $id);
        return parent::loadCached($id, $data);
    }//}}}
	public function __construct( $id=null, $not_found_creates_new=true  )/*{{{*/
	{
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
		$stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
				"Fetch all fields from " . static::TABLE_NAME ." for record $id",
				"SELECT * FROM `" . static::TABLE_NAME ."` WHERE $pk_where",
				$params));

		// no rows may be allowable if $not_found_creates_new
		if ($stmt->rowCount()==0 && $not_found_creates_new) return $this;
				
		// more than one record always wrong. Zero records wrong unless $not_found_creates_new set.
		if ($stmt->rowCount()!=1) throw new Exception(get_class($this) 
				. "::loadFromDatabase failed to load single row from " . static::TABLE_NAME ." with id $id");

		$this->myData = $stmt->fetch(PDO::FETCH_ASSOC);
		$this->is_new = false;
		$this->unsaved_changes = false;

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
	//protected function getPK(){{{
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

		$sql = "UPDATE " . static::TABLE_NAME 
			. " SET " . implode(", ", $sql)
			. " WHERE $pk_where";
		$stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
				"Update record {$this->myData['id']} in " . static::TABLE_NAME,
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

		$sql = "INSERT INTO `" . static::TABLE_NAME . "` ("
			. implode(", ", $fields)
			. ") VALUES ( "
			. implode(", ", array_keys($data))
			. ")";
		$stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
				"Create new row in " . static::TABLE_NAME,
				$sql,
				$data));
	}/*}}}*/
	public function delete()/*{{{*/
	{
		$params = array();
		$pk_where = $this->preparePK($params);
		$stmt = static::getConnection()->prepAndExecute( new \ArtfulRobot\PDO_Query(
					"Delete row in " . static::TABLE_NAME,
					"DELETE FROM `" . static::TABLE_NAME ."` WHERE $pk_where",
					$params));

		$this->unsaved_changes = $this->is_new = true;
	}/*}}}*/
}

