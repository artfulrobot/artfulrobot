<?php
/**
 * @file
 * CSV Parsing class.
 *
 * Copyright 2015 Rich Lott
 *
 * @author: Rich Lott / Artful Robot
 * @licence: GPL3+
 */

namespace ArtfulRobot;

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
  public function loadFromFile($filename) {
    // Parse CSV file
    $csv_file = fopen($filename, "r");
    $row_data = fgetcsv($csv_file, 1000, ",");
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
    while (($row_data = fgetcsv($csv_file, 1000, ",")) !== FALSE) {
      $this->data[$row] = $row_data;
      $row++;
    }
    // tidy up
    fclose($csv_file);
    $this->rewind();
    return $this;
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
}
