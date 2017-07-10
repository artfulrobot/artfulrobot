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
  /**
   * http://www.regextester.com/?fam=60203
   */
  const VALID_UK_POSTCODE_REGEX = '/^(GIR 0AA)|((([A-Z][0-9][0-9]?)|(([A-Z][A-HJ-Y][0-9][0-9]?)|(([A-Z][0-9][A-Z])|([A-Z][A-HJ-Y][0-9]?[A-Z])))) [0-9][A-Z]{2})$/';

  protected $validate;
  protected $value;
  protected $raw;
  protected $allow_empty = FALSE;
  protected $empty_value = null;
  protected $is_missing;

  public function __construct($key, $value, $is_missing) {
    $this->key = $key;
    $this->value = $value;
    $this->raw = $value;
    $this->is_missing = $is_missing;
  }
  public function __invoke() {
    return $this->__get('v');
  }
  public function __get($prop) {
    if (empty($this->value) && $this->allow_empty) {
      $_ = $this->empty_value;
    }
    else {
      $_ = $this->value;
    }
    if ($prop == 'v' || $prop == 'value') {
      return $_;
    }
    elseif ($prop == 'string') {
      return (string)$_;
    }
    elseif ($prop == 'missing') {
      return $this->is_missing;
    }
    elseif ($prop == 'given') {
      return ! $this->is_missing;
    }
    elseif ($prop == 'int') {
      return (int)$_;
    }
    elseif ($prop == 'bool') {
      return (bool) $_;;
    }
    elseif ($prop == 'raw') {
      return $this->raw;
    }
    elseif ($prop == 'date') {
      // hmmm....
      // Date string Y-m-d wanted.
      $time = strtotime($_);
      if ($time === FALSE) {
        throw new \InvalidArgumentException("$this->key is an invalid date.");
      }
      return date('Y-m-d', $time);
    }
    throw new \Exception("Request Item does not have property '$prop'");

  }
  public function __tostring() {
    return $this->__get('string');
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
  public function isType($type) {
    if (gettype($this->value) !== $type) {
      throw new \InvalidArgumentException("$this->key must be $type");
    }
    return $this;
  }
  /**
   * Try to coerce given user in put into a UK postcode.
   */
  public function castToUKPostcode() {
    if ($this->allow_empty && empty($this->value)) {
      return $this;
    }

    // First, trim excess spaces and make it upper case.
    $this->value = trim(preg_replace('/ {2,}/',' ',strtoupper($this->value)));
    if (preg_match(static::VALID_UK_POSTCODE_REGEX, $this->value)) {
      return $this;
    }

    // if the user entered multiple spaces, try to match it with every combination.
    $parts = explode(' ', $this->value);
    if (count($parts)>2) {
      for ($i=1;$i<count($parts);$i++) {
        $try = implode('', array_slice($parts,0,$i)) . ' ' . implode('', array_slice($parts,$i));
        if (preg_match(static::VALID_UK_POSTCODE_REGEX, $try)) {
          $this->value = $try;
          return $this;
        }
      }
    }

    // Still here. OK try removing ALL spaces.
    $_ = implode('', $parts);
    // Now try inserting a space at every possible place until we have a match.
    for ($i=1;$i<strlen($_);$i++) {
      $try = substr($_,0,$i) . ' ' . substr($_,$i);
      if (preg_match(static::VALID_UK_POSTCODE_REGEX, $try)) {
        $this->value = $try;
        return $this;
      }
    }

    // Postcode did not match.
    throw new \InvalidArgumentException("Could not recognise UK postcode");
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
    if ($this->is_missing) {
      $this->set($default);
    }
    return $this;
  }
  /**
   * Ensure value matches.
   *
   * $valid can be:
   * - regexp
   * - array of strings
   */
  public function matches($valid) {
    if ($this->allow_empty && empty($this->value)) {
      return $this;
    }
    if (is_string($valid)) {
      if (!preg_match($valid, $this->value)) {
        throw new \InvalidArgumentException("$this->key is not as expected");
      }
    }
    elseif (is_array($valid)) {
      if (!in_array($this->value, $valid)) {
        throw new \InvalidArgumentException("$this->key is not one of the allowed values.");
      }
    }
    return $this;
  }
  /**
   * Ensure value is exactly as expected.
   *
   * Probably want to do required() first.
   *
   */
  public function is($expected) {
    if ($expected !== $this->value) {
      throw new \InvalidArgumentException("$this->key is not as expected");
    }
    return $this;
  }
  /**
   * We must have *something* for this item.
   *
   * - Any non-empty value passed in input
   * - Value set through a defaultIfMissing
   *
   * It makes no sense to do allowEmpty()->notEmpty(). allowEmpty is ignored
   * and notEmpty will still throw exception.
   */
  public function notEmpty() {
    if (empty($this->value)) {
      throw new \InvalidArgumentException("$this->key required.");
    }
    return $this;
  }
  /**
   * We must have a value for this item.
   *
   * - Any value passed in input
   * - Value set through a defaultIfMissing
   * - 'empty' value set through allowEmpty (this can still be 'null')
   */
  public function required() {
    if ($this->allow_empty && empty($this->value)) {
      return $this;
    }
    if ($this->value === null) {
      throw new \InvalidArgumentException("$this->key missing.");
    }
    return $this;
  }
  /**
   * Sets the value.
   *
   * If value is itself a ValidateItem, the value of that is extracted.
   */
  public function set($value) {
    if ($value instanceof ValidateItem) {
      $value = $value->value;
    }
    $this->value = $value;
    return $this;
  }
}
