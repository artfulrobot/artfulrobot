<?php
namespace ArtfulRobot;

/**
 * Cache with max age using memory and Drupal's cache as database storage.
 *
 * Synopsis:
 *
 *     $x = ARL\DrupalCache::key('foo')
 *        ->get(function() { return 'hello at ' . date('H:i:s'); });
 *
 *     $x = ARL\DrupalCache::key('foo')
 *        ->maxAge(5, 'days')
 *        ->get(function() { return 'hello at ' . date('H:i:s'); });
 *
 *     $x = ARL\DrupalCache::key('foo')->clear();
 *     $x = ARL\DrupalCache::key('foo')->set('blah');
 */
class DrupalCache
{
  protected static $singles = [];

  /** Cache key */
  public $key;

  /** Cached value */
  public $value;

  /** Expiry timestamp */
  public $expires;

  /** Cache creation date */
  public $created;

  /** Drupal Cache object */
  public $drupal_cache_obj;

  /**
   * Main factory method.
   */
  public static function key($key) {
    if (!isset(static::$singles[$key])) {
      static::$singles[$key] = new static($key);
    }
    return static::$singles[$key];
  }

  /**
   * Access objects through key().
   *
   * @param string $key Cache id
   */
  protected function __construct($key) {
    $this->key = $key;
  }

  /**
   * Set maximum age before cache is discarded.
   *
   * @param int $id
   * @param string $unit hour|min|s|day|month|year
   */
  public function maxAge($i, $unit='hour') {
    if ($i === NULL) {
      $this->max_age = $i;
      return $this;
    }

    if ($unit != 's') {
      $unit = rtrim($unit, 's');
      $map = [
        'min' => 60,
        'minute' => 60,
        'hour' => 60*60,
        'day' => 60*60*24,
        'month' => 60*60*24*30,
        'year' => 60*60*24*365,
      ];
      if (!isset($map[$unit])) {
        throw new \InvalidArgumentException("'$unit' not a valid unit. Should be one of: " . implode(', ', array_keys($map)));
      }
      $i *= $map[$unit];
    }
    $this->max_age = $i;

    return $this;
  }

  /**
   * Clear cache.
   */
  public function clear() {
    $this->value = NULL;
    $this->expires = NULL;
    $this->created = NULL;
    cache_clear_all($this->key, 'cache');
    return $this;
  }
  /**
   * Get.
   *
   * @param callback $callback this function will be called if there's a miss.
   */
  public function get($callback) {

    if ($this->drupal_cache_obj === NULL) {
      // We don't have it yet. Does Drupal?
      $this->drupal_cache_obj = cache_get($this->key);
    }

    if (!$this->drupal_cache_obj
      || (
        $this->max_age !== NULL
        && $this->drupal_cache_obj->created + $this->max_age < time()
      )
    ) {
      // No cache object, or it's expired.
      $value = $callback();
      $this->set($value);
    }

    return $this->drupal_cache_obj->data;
  }
  /**
   * Set.
   */
  public function set($value) {

    if ($this->max_age !== NULL) {
      // We have an expiry.
      cache_set($this->key, $value, 'cache', $this->max_age + time());
    }
    else {
      cache_set($this->key, $value);
    }
    $this->drupal_cache_obj = cache_get($this->key);

    return $this;
  }
}
