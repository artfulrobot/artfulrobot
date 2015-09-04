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
  public function inputExists($key) {
    return isset($this->input[$key]);
  }
  public function input($key) {
    return $this->input[$key];
  }
  public function __get($key) {
    if (!array_key_exists($key, $this->items)) {
      $this->items[$key] = new ValidateItem($this, $key);
    }
    return $this->items[$key];
  }
}

