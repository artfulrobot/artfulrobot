<?php
/**
  * @file
  * class for dealing with CSV
  */

class ARL_CSV
{
	// 	static public function csv_safe($str)  {{{
	/** returns csv field, quoted if string.
	 *
	 * @param string $str
	 * @return string
	 */
	static public function csv_safe($str)  
	{
		if ( $str === null || $str === false || $str === '') $out = '';
		// identify numbers and output as is, no quotes
		// 1	1.2		-1	-1.2	0.2	-0.5	
		elseif ($str =='0') $out = '0';
		// why not use is_numeric()?
		elseif (preg_match('/^-?(?:0[0-9]*?\.[0-9]|[1-9][0-9]*\.?)[0-9]*$/',$str)) $out = (string)$str; 
		else if ($str === '.') $out = '"\'."'; // "." gets interpreted as as a number by openoffice
		else  $out = '"' . strtr($str, array('"'=>'""')) . '"';
		return $out ;
	} // }}}
	static public function output_csv_file( $csv, $filename='' ) // {{{
	{
		// text/csv
		header("Pragma: public"); // required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false); // required for certain browsers 
		header("Content-Type: text/csv");
		header("Content-Length: " . strlen($csv) );
		if($filename) header("Content-Disposition: attachment; filename=\"$filename\";" );
		print $csv ;
		exit;
	} // }}}
	//static public function pdo_result_to_csv($result) {{{
	/** fetches from PDOStatement object, outputs to csv string.
	  *
	  * @param PDOStatement $result
	  * @param bool|array false: no headers; true: col headers; array: alt. headers
	  *
	  * @return string|null
	  */
	static public function pdo_result_to_csv($result, $headers=TRUE)
	{
		if ($result->rowCount()==0) return null;

		$csv_body = array();
		$csv_headers = is_array($headers) ? $headers : array();

		while ($record = $result->fetch(PDO::FETCH_ASSOC))
		{
			// grab headers if needed
			if ($headers===TRUE && ! $csv_headers)
				$csv_headers = array_keys($record);

			$csv_body[] = self::array_to_csv_line($record);
		}

		if ($csv_headers) array_unshift($csv_body, self::array_to_csv_line($csv_headers));

		$csv = implode("\n", $csv_body);
		return $csv;
	}//}}}
	//static public function array_to_csv($result, $headers=TRUE) {{{
	/** outputs array to csv string.
	  *
	  * @param array $result
	  * @param bool|array false: no headers; true: key headers; array: alt. headers
	  *
	  * @return string|null
	  */
	static public function array_to_csv($result, $headers=TRUE)
	{
		if (count($result)==0) return null;

		$csv_body = array();
		$csv_headers = is_array($headers) ? $headers : array();

		// grab headers if needed
		if ($headers===TRUE) $csv_headers = array_keys(reset($result));
		if ($csv_headers) $csv_body[] = self::array_to_csv_line($csv_headers);

		foreach ($result as $record)
			$csv_body[] = self::array_to_csv_line($record);

		$csv = implode("\n", $csv_body);
		return $csv;
	}//}}}
	//static public function array_to_csv_line($result)/*{{{*/
	/** map a record to a csv line
	  */
	static public function array_to_csv_line($result)
	{
		$row = array();
		foreach ($result as $_)
			$row[] = self::csv_safe($_);

		return implode(',',$row);
	}/*}}}*/
}

