<?php
namespace ArtfulRobot;

/** class to add functionality to PDO
 */
class PDO extends \PDO
{
	const FORMAT_TIME = 'G.i.s';
	const FORMAT_DATE = 'Y-m-d';
	const FORMAT_DATETIME = 'Y-m-d G.i.s';
	//public function fetchSingle(\ArtfulRobot\PDO_Query $query, $col_name=null)/*{{{*/
	/** Run the \ArtfulRobot\PDO_Query supplied and return first (or $col_name) field from first row */
	public function fetchSingle(\ArtfulRobot\PDO_Query $query, $col_name=null)
	{
		$stmt = $this->prepAndExecute($query);

		if (!$stmt) $output = null;
		elseif ($col_name===null) $output = $stmt->fetch( PDO::FETCH_COLUMN );
		else
		{
			$row = $stmt->fetch( PDO::FETCH_ASSOC );
			$output = \ArtfulRobot\Utils::arrayValue( $col_name, $row);
		}

		\ArtfulRobot\Debug::log("! fetchSingle returning: $output");
		return $output;
	}/*}}}*/
	public function fetchRowAssoc(\ArtfulRobot\PDO_Query $query )/*{{{*/
	{
		\ArtfulRobot\Debug::log(">>$query->comment");

		$stmt = $this->prepAndExecute( $query );
		if (!$stmt) 
		{
			\ArtfulRobot\Debug::log("<< failed to run");
			return null;
		}

		$output = $stmt->fetch( PDO::FETCH_ASSOC );

		\ArtfulRobot\Debug::log("<< row fetched");
		return $output;
	}/*}}}*/
	public function fetchRowsAssoc(\ArtfulRobot\PDO_Query $query , $key_field = null )/*{{{*/
	{
		\ArtfulRobot\Debug::log(">>$query->comment");

		$stmt = $this->prepAndExecute( $query );
		if (!$stmt) 
		{
			\ArtfulRobot\Debug::log("<< failed to run");
			return null;
		}
		$output = array();

		if ($key_field) while ($row = $stmt->fetch( PDO::FETCH_ASSOC ))
			$output[ $row[$key_field] ] = $row;
		else while ($row = $stmt->fetch( PDO::FETCH_ASSOC ))
			$output[] = $row;

		\ArtfulRobot\Debug::log("<< " . count($output) . " rows fetched");
		return $output;
	}/*}}}*/
	// public function fetchRowsSingle(\ArtfulRobot\PDO_Query $query , $col_name = null, $key_field = null )/*{{{*/
	/** fetch array of single fields (defaults to first field), optionally indexed by another field 
	 * 
	 *  Nb. specifying key_field is quite different to not.
	 */
	public function fetchRowsSingle(\ArtfulRobot\PDO_Query $query , $col_name = null, $key_field = null )
	{
		\ArtfulRobot\Debug::log(">>$query->comment");

		$stmt = $this->prepAndExecute( $query );
		if (!$stmt) 
		{
			\ArtfulRobot\Debug::log("<< failed to run");
			return null;
		}
		$output = array();

		if ($col_name === null && $key_field===null)
			$output = $stmt->fetchAll(PDO::FETCH_COLUMN);
		elseif ($col_name === null && $key_field!==null)
			throw new Exception("fetchRowsSingle requires \$col_name if \$key_field given");
		else
		{
			if ($key_field) while ($row = $stmt->fetch( PDO::FETCH_ASSOC ))
				$output[ $row[$key_field] ] = $row[ $col_name ];
			else while ($row = $stmt->fetch( PDO::FETCH_ASSOC ))
				$output[] = $row[ $col_name ];
		}

		\ArtfulRobot\Debug::log("<< " . count($output) . " rows fetched");
		return $output;
	}/*}}}*/
	// public function fetchAffectedCount(\ArtfulRobot\PDO_Query $query )/*{{{*/
	/** fetch number of rows affected by the INSERT, DELETE, UPDATE query given
	 */
	public function fetchAffectedCount(\ArtfulRobot\PDO_Query $query )
	{
		\ArtfulRobot\Debug::log(">>$query->comment");

		$stmt = $this->prepAndExecute( $query );
		if (!$stmt) 
		{
			\ArtfulRobot\Debug::log("<< failed to run");
			return null;
		}
		
		$count = $stmt->rowCount();
		\ArtfulRobot\Debug::log("<< $count affected");
		return $count;
	}/*}}}*/
	// public function fetchInsertId(\ArtfulRobot\PDO_Query $query )/*{{{*/
	/** run an INSERT query and return just the new insert_id
	 */
	public function fetchInsertId(\ArtfulRobot\PDO_Query $query )
	{
		\ArtfulRobot\Debug::log(">>$query->comment");

		$stmt = $this->prepAndExecute( $query );
		if (!$stmt) 
		{
			\ArtfulRobot\Debug::log("<< failed to run");
			return null;
		}
		
		$id = $this->lastInsertId();
		\ArtfulRobot\Debug::log("<< id: $id");
		return $id;
	}/*}}}*/
	//public function fetchFoundRows()/*{{{*/
	/** executes SELECT FOUND_ROWS() which will return the count of rows from the last SQL_CALC_FOUND_ROWS query
	 */
	public function fetchFoundRows()
	{
		return $this->fetchSingle(new \ArtfulRobot\PDO_Query( "Get FOUND_ROWS()", "SELECT FOUND_ROWS();"));
	}/*}}}*/
	//public function prepAndExecute( \ArtfulRobot\PDO_Query $query )/*{{{*/
	/** prepare the \ArtfulRobot\PDO_Query given, then execute it and return a PDOStatement object
	 */
	public function prepAndExecute( \ArtfulRobot\PDO_Query $query )
	{
		\ArtfulRobot\Debug::log( "prepAndExecute: $query->comment",  strtr($query->sql, array("\t" => '  ')) );
		if (! $query->params) $stmt = $this->query($query->sql);
		else
		{
			\ArtfulRobot\Debug::log("params: ", $query->params);
			\ArtfulRobot\Debug::log("sql: ", $query->sql);
			$stmt = $this->prepare($query->sql);
			if (! $stmt->execute($query->params) )
				throw new Exception("Failed to execute statement (SQL error " . $stmt->errorCode().")");
		}
		return $stmt;
	}/*}}}*/

	//public static function castDatetime( $date, $format='datetime', $false_value=null)/*{{{*/
	/** prepare a string that should be a date|datetime|time
	 * 
	 * @param string $date
	 * @param string $format one of datetime(default), date or time
	 * @param mixed $false_value returned if $date is ZLS/null/0/false
	 */
	public static function castDatetime( $date, $format='datetime', $false_value=null)
	{
		// nothing sent?
		if (! $date) 
		{
			// special case, "now" strtotime will handle this nicely.
			if ($false_value != 'now') return $false_value;
		}

		$time = strtotime($date);
		if ($time === false) throw new Exception("\ArtfulRobot\PDO::castDatetime cannot parse $date");

		if     ($format == 'datetime') $format = self::FORMAT_DATETIME;
		elseif ($format == 'date') $format = self::FORMAT_DATE;
		elseif ($format == 'time') $format = self::FORMAT_TIME;

		return date($format, $time);
	}/*}}}*/
}

