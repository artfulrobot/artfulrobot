<?php
namespace ArtfulRobot;

/**
  * @file
  * class for dealing with creating CSV
  *
  * @todo rename and bring in importer code from rlcore
  */

class CSV
{
  /**
   * returns csv field, quoted if string.
	 *
	 * @param string $str
	 * @return string
	 */
	static public function csvSafe($str) {
		if ( $str === null || $str === false || $str === '') $out = '';
		// identify numbers and output as is, no quotes
		// 1	1.2		-1	-1.2	0.2	-0.5
		elseif ($str =='0') $out = '0';
		// why not use is_numeric()?
		elseif (preg_match('/^-?(?:0[0-9]*?\.[0-9]|[1-9][0-9]*\.?)[0-9]*$/',$str)) $out = (string)$str;
		else if ($str === '.') $out = '"\'."'; // "." gets interpreted as as a number by openoffice
		else  $out = '"' . strtr($str, array('"'=>'""')) . '"';
		return $out ;
	}
  /**
   * Return a CSV file to a browser from csv string, then exit()s.
   *
   * @param string $csv
   * @param string $filename - if given passed into Content-Disposition
   */
	static public function outputCsvFile( $csv, $filename='' ) {
    static::outputCsvFileHeaders($filename, strlen($csv));
		print $csv ;
		exit;
	}
  /**
   *  Output HTTP headers.
   *
   * @param null|string $filename (optional) for Content-Disposition
   * @param null|int $content_length (optional) Content-Length
   */
	static public function outputCsvFileHeaders($filename='', $content_length=NULL) {
		// text/csv
		header("Pragma: public"); // required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false); // required for certain browsers 
		header("Content-Type: text/csv");
    if ($content_length !== null) {
      header("Content-Length: " . $content_length );
    }
    if($filename) {
      header("Content-Disposition: attachment; filename=\"$filename\";" );
    }
	}
  /**
   * fetches from PDOStatement object, outputs to csv string.
   *
   * @param PDOStatement $result
   * @param bool|array false: no headers; true: col headers; array: alt. headers
   *
   * @return string|null
   */
	static public function pdoResultToCsv($result, $headers=TRUE) {
    $csv = '';
    static::pdoResultProcessor($result, $headers, function($line) use(&$csv) { $csv .= "$line\n"; });
		return $csv;
	}
  /**
   * outputs array to csv string.
   *
   * @param array $result
   * @param bool|array false: no headers; true: key headers; array: alt. headers
   *
   * @return string|null
   */
	static public function arrayToCsv($result, $headers=TRUE) {
		if (count($result)==0) return null;

		$csv_body = array();
		$csv_headers = is_array($headers) ? $headers : array();

		// grab headers if needed
		if ($headers===TRUE) $csv_headers = array_keys(reset($result));
		if ($csv_headers) $csv_body[] = self::arrayToCsvLine($csv_headers);

		foreach ($result as $record)
			$csv_body[] = self::arrayToCsvLine($record);

		$csv = implode("\n", $csv_body);
		return $csv;
	}
  /**
   * map a record to a csv line
	 */
	static public function arrayToCsvLine($result) {
		$row = array();
		foreach ($result as $_)
			$row[] = self::csvSafe($_);

		return implode(',',$row);
	}
  /**
   * fetches from PDOStatement object, outputs to file.
   *
   * @param PDOStatement $result
   * @param bool|array false: no headers; true: col headers; array: alt. headers
   *
   * @return string|null
   */
	static public function pdoResultToFile($result, $headers=TRUE, $filename) {
    $fh = fopen($filename, 'w');
    if (!$fh) {
      throw new \Exception("Failed to open file for writing CSV to. Filename: $filename");
    }
    static::pdoResultProcessor($result, $headers, function($line) use($fh) { fwrite($fh, "$line\n"); });
    fclose($fh);
	}
  /**
   * internal function to iterate a PDO result.
   *
   * @param PDOStatement $result
   * @param bool|array false: no headers; true: col headers; array: alt. headers
   * @param callback $output_callback: this is passed each line of the output.
   */
	static public function pdoResultProcessor($result, $headers=TRUE, $output_callback) {
		if ($result->rowCount()==0) return;

		$csv_body = array();
		$csv_headers = is_array($headers) ? $headers : array();

		while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
			// grab headers if needed
			if ($headers===TRUE && ! $csv_headers) {
				$csv_headers = array_keys($record);
        $output_callback(self::arrayToCsvLine($csv_headers));
      }

			$output_callback(self::arrayToCsvLine($record));
		}
	}
  /**
   * fetches from PDOStatement object into a temporary file and outputs that to the browser and exits.
   *
   * @param PDOStatement $result
   * @param bool|array false: no headers; true: col headers; array: alt. headers
   * @param string $filename used in Content-Disposition
   */
	static public function pdoResultToCsvDownload($result, $headers=TRUE, $filename='') {

    $fh = tmpfile();
    if (!$fh) {
      throw new \Exception("Failed to open temporary file for writing CSV to.");
    }
    // Copy csv to file.
    static::pdoResultProcessor($result, $headers, function($line) use($fh) { fwrite($fh, "$line\n"); });
    // Now output the file.
    $content_length = fstat($fh)['size'];
    rewind($fh);
    static::outputCsvFileHeaders($filename, $content_length);
    // disable output buffer
    ob_end_flush();
    fpassthru($fh);
    // Close tempfile (which deletes it).
    fclose($fh);
    exit();
	}
}

