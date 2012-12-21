<?php
namespace ArtfulRobot;

/** class to add functionality to PDO
 */
class PDO extends \PDO
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
			$output = \ArtfulRobot\Utils::arrayValue( $col_name, $row);
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
}

