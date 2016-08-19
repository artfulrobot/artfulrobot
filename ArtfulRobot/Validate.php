<?php
namespace ArtfulRobot;

/**
 * @file
 *
 * Helpers for validating simple key-value arrays.
 *
 * @copyright 2015 Rich Lott / Artful Robot
 * @licence GPL3+
 */

class Validate{
  protected $input;
  protected $items = [];
  public function __construct($input) {
    $this->input = $input;
  }
  /**
   * Return true if the input exists [for given key].
   */
  public function inputExists($key=null) {
    if ($key === null) {
      return !empty($this->input);
    }
    return isset($this->input[$key]);
  }
  public function notEmpty() {
    if (!$this->inputExists()) {
      throw new \Exception("No data!");
    }
    return $this;
  }
  public function input($key) {
    return $this->input[$key];
  }
  public function __get($key) {
    if (!array_key_exists($key, $this->items)) {
      if ($this->inputExists($key)) {
        $is_missing = FALSE;
        $value = $this->input[$key];
      }
      else {
        $is_missing = TRUE;
        $value = null;
      }
      $this->items[$key] = new ValidateItem($key, $value, $is_missing);
    }
    return $this->items[$key];
  }
  public function getInput() {
    return $this->input;
  }
}

