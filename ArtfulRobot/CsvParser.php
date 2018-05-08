<?php
/**
 * @file
 * CSV Parsing class.
 *
 * Copyright 2015 Rich Lott
 *
 * @author: Rich Lott / Artful Robot
 * @licence: GPL3+
 *
 */

namespace ArtfulRobot;

/**
 * Parse a CSV file.
 *
 * Synopsis:
 *
 *     $csv = CsvParser::createFromFile('foo.csv');
 *     print "Name: $csv->Name\n";
 *     print "Age: $csv->Age\n";
 *     print "Name: " . $csv->getCell($col=0) . "\n";
 *     foreach ($csv as $row) {
 *        print "Hello, $row->Name\n"; // Actually identical to $csv->Name
 *     }
 *     print "There are " . $csv->count() . " rows\n";
 *
 * Notes
 *
 * - You can access cells of the current row by header name, unless empty.
 * - All headers must be unique (or blank); an exception is thrown if duplicate headers are found.
 * - Within the data range, a zero-length string is returned if there is no data.
 * - $row in the above code is actually identical to the object itself; the foreach just moves the internal pointer.
 *
 */
class CsvParser implements \Iterator {

  /**
   * Holds the data.
   */
  protected $data;

  /**
   * Holds the original headings
   */
  public $headers;

  /**
   * Maps header names to indexes
   */
  protected $header_map;

  /**
   * Holds current pointer
   */
  protected $current_row = 1;


  /**
   * Return reference to this object; the internal pointer is now at the right row.
   */
  public function current() {
    return $this;
  }
  /**
   * Return current row number (from 1)
   */
  public function key() {
    return $this->current_row;
  }
  /**
   * Move to next row
   */
  public function next() {
    if (count($this->data) == $this->current_row) {
      $this->current_row = FALSE;
    }
    else {
      $this->current_row++;
    }
  }
  /**
   * Move back to row 1
   */
  public function rewind() {
    $this->current_row = 1;
  }
  /**
   * Check if valid
   */
  public function valid() {
    return isset($this->data[$this->current_row]);
  }


  /**
   * Returns count of rows
   */
  public function count() {
    return (int) count($this->data);
  }

  /**
   * Open and parse an entire CSV file
   */
  public function loadFromFile($filename, $max_buffer_length=null) {

    if ($max_buffer_length===null) {
      $max_buffer_length = 1000;
    }
    // Parse CSV file
    $csv_file = fopen($filename, "r");
    $row_data = fgetcsv($csv_file, $max_buffer_length, ",");
    if ($row_data === FALSE) {
      throw new \InvalidArgumentException("Failed to read a row of CSV from '$filename'");
    }
    // this row contains the headers.
    $this->headers = $row_data;
    $this->header_map = [];
    foreach ($row_data as $i=>$_) {
      if ($_) {
        if (isset($this->header_map[$_])) {
          throw new \InvalidArgumentException("Duplicate header name: $_");
        }
        $this->header_map[$_] = $i;
      }
    }
    // Load data
    $this->data = [];
    $row = 1;
    while (($row_data = fgetcsv($csv_file, $max_buffer_length, ",")) !== FALSE) {
      $this->data[$row] = $row_data;
      $row++;
    }
    // tidy up
    fclose($csv_file);
    $this->rewind();
    return $this;
  }

  /**
   * Factory method to create an object and load a file.
   */
  public static function createFromFile($filename, $max_buffer_length = null) {
    $csv_parser = new static();
    $csv_parser->loadFromFile($filename, $max_buffer_length);
    return $csv_parser;
  }


  /**
   * Magic method to fetch a value by a header
   */
  public function __get($property) {
    if (isset($this->header_map[$property])) {
      $i = $this->header_map[$property];
      if ($this->valid()) {
        if (empty($this->data[$this->current_row][$i])) {
          // No data returns ZLS
          return '';
        }
        else {
          return $this->data[$this->current_row][$i];
        }
      }
      else {
        throw new \Exception("No data");
      }
    }
    throw new \Exception("Unknown property '$property'");
  }
  /**
   * Access by numeric co-ordinates, column[, row]
   *
   * First column is column 0.
   * First row is row 1.
   */
  public function getCell($col_number, $row_number=null) {
    if ($row_number) {
      $this->setRow($row_number);
    }
    if (!$this->valid()) {
      throw new \InvalidArgumentException("Row not found.");
    }

    if ($col_number<0 || $col_number>=count($this->headers)) {
      throw new \InvalidArgumentException("Column out of bounds.");
    }

    if (empty($this->data[$this->current_row][$col_number])) {
      // No data returns ZLS
      return '';
    }
    else {
      return $this->data[$this->current_row][$col_number];
    }
  }

  /**
   * Set current row
   *
   * First row is row 1.
   */
  public function setRow($row_number) {
    $row_number = (int) $row_number;
    if ($row_number < 1 || $row_number > $this->count()) {
      $this->row_number = FALSE;
      throw new \InvalidArgumentException("Row not found.");
    }
    $this->current_row = $row_number;
  }
  /**
   * Returns an array of column headers.
   */
  public function getHeaders() {
    return array_keys($this->header_map);
  }
}
