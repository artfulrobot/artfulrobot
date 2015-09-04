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

class ValidateItem {
  protected $validate;
  protected $value;
  protected $allow_empty = FALSE;
  protected $empty_value = null;

  public function __construct($validate, $key) {
    $this->validate = $validate;
    $this->key = $key;
    if ($this->validate->inputExists($key)) {
      $this->value = $this->validate->input($key);
    }
  }
  public function required() {
    if ($this->value === null) {
      throw new \InvalidArgumentException("$this->key missing.");
    }
    return $this;
  }
  /**
   * Ignore cast validations if empty, and set the value returned.
   *
   * This is different to defaultTo(); the value is not changed.
   */
  public function allowEmpty($empty_value = null) {
    $this->allow_empty = TRUE;
    $this->empty_value = $empty_value;
    return $this;
  }
  /**
   * If the value is empty, set it to this default.
   */
  public function defaultIfEmpty($default) {
    if (empty($this->value)) {
      $this->set($default);
    }
    return $this;
  }
  /**
   * If the value is missing, set it to this default.
   *
   * This is more specific than defaultIfEmpty.
   */
  public function defaultIfMissing($default) {
    if ($this->value === null) {
      $this->set($default);
    }
    return $this;
  }
  public function __invoke() {
    return $this->get();
  }
  public function get() {
    if (empty($this->value) && $this->allow_empty) {
      return $this->empty_value;
    }
    return $this->value;
  }
  public function __tostring() {
    return (string) $this->get();
  }
  public function set($value) {
    $this->value = $value;
    return $this;
  }
  public function castToDate($format='Y-m-d') {
    if ($this->allow_empty && empty($this->value)) {
      return $this;
    }
    $time = strtotime($this->value);
    if ($time === FALSE) {
      throw new \InvalidArgumentException("$this->key is an invalid date.");
    }
    $this->value = date($format, $time);
    return $this;
  }
  public function castToInt($min=0) {
    $_ = (int) $this->value;
    if ($_ < $min) {
      throw new \InvalidArgumentException("$this->key must be above $min");
    }
    $this->value = $_;
    return $this;
  }
  public function castToBool() {
    $_ = (bool) $this->value;
    $this->value = $_;
    return $this;
  }
}
