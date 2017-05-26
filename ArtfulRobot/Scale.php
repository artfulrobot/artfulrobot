<?php
namespace ArtfulRobot;

/**
 * @file
 * Simple class for providing scale.
 *
 */
class Scale {

  public $factor;

  public $domain_min=0;
  public $domain_max=1;
  public $range_min=0;
  public $range_max=1;
  public $limit=FALSE;
  public $colours=[];

  /**
   * Constructor
   *
   * @param array $domain [ $max ] or [ $min, $max ] for input data.
   * @param array $range [ $max ] or [ $min, $max ] for output data.
   */
  public function __construct($domain, $range=[0, 1]) {
    if (!is_array($domain) || count($domain) < 1 || count($domain) > 2) {
      throw new \InvalidArgumentException("Invalid domain provided. Must be array with one or two numbers.");
    }
    if (!is_array($range) || count($range) < 1 || count($range) > 2) {
      throw new \InvalidArgumentException("Invalid range provided. Must be array with one or two numbers.");
    }
    if (isset($domain[1])) {
      $this->setDomain($domain[1], $domain[0]);
    }
    else {
      $this->setDomain($domain[0]);
    }
    if (isset($range[1])) {
      $this->setRange($range[1], $range[0]);
    }
    else {
      $this->setRange($range[0]);
    }
  }

  public function limitToRange($limit=TRUE) {
    $this->limit = TRUE;
  }

  /**
   * Set the domain for the input data.
   * @param number $max
   * @param number $min defaults to 0
   */
  public function setDomain($max, $min=0) {
    if (!is_numeric($max)) {
      throw new \InvalidArgumentException("'$max' is invalid domain max - must be a number.");
    }
    if (!is_numeric($min)) {
      throw new \InvalidArgumentException("'$min' is invalid domain min - must be a number.");
    }
    if ($max == $min) {
      throw new \InvalidArgumentException("zero domain range will lead to divide by zero errors.");
    }
    $this->domain_max = $max;
    $this->domain_min = $min;
    $this->recalcFactor();
  }

  /**
   * Set the range for the output data.
   *
   * @param number $max
   * @param number $min defaults to 0
   */
  public function setRange($max, $min=0) {
    if (!is_numeric($max)) {
      throw new \InvalidArgumentException("'$max' is invalid range max - must be a number.");
    }
    if (!is_numeric($min)) {
      throw new \InvalidArgumentException("'$min' is invalid range min - must be a number.");
    }
    $this->range_max = $max;
    $this->range_min = $min;
    $this->recalcFactor();
  }

  /**
   * Return scaled value.
   */
  public function scaled($x) {
    $y = ($x - $this->domain_min) * $this->factor + $this->range_min;
    if ($this->limit) {
      if ($y > $this->range_max) {
        return $this->range_max;
      }
      elseif ($y < $this->range_min) {
        return $this->range_min;
      }
    }
    return $y;
  }

  /**
   * Return fraction of input in within range; domain does not matter.
   */
  public function fraction($x) {
    $y = ($x - $this->domain_min) / ($this->domain_max - $this->domain_min);
    if ($this->limit) {
      $y = max(min($y, 1), 0);
    }
    return $y;
  }

  /**
   * Shortcut for scaled().
   */
  public function __invoke($x) {
    return $this->scaled($x);
  }

  /**
   * Set an array of #112233ff style colours.
   */
  public function setColours($colours) {
    $this->colours = [];
    foreach ($colours as $webhex) {
      $this->colours[] = [
        'r' => hexdec(substr($webhex, 0, 2)),
        'g' => hexdec(substr($webhex, 2, 2)),
        'b' => hexdec(substr($webhex, 4, 2)),
        'a' => hexdec(substr($webhex, 6, 2)),
      ];
    }
  }
  /**
   * return CSS rgba() format.
   */
  public function colour($col, $col2) {

  }
  /**
   * Get a rgba() colour for the given input.
   */
  public function rgba($x) {
    $fraction = ($x - $this->domain_min) / ($this->domain_max - $this->domain_min);
    $n = count($this->colours);
    // Colour index.
    $i = $fraction * ($n - 1);
    // Ensure bounded.
    $i = min(max($i, 0), ($n -1));

    // Exact?
    if ($i == (int) $i) {
      $colour = $this->colours[(int) $i];
    }
    else {
      // Need to interpolate two colours.
      $j=$i;
      $i = (int) $i;
      $colour = $this->colours[$i];
      $colour2 = $this->colours[$i+1];
      // Compare fraction with fraction between $i, $i+1.
      $range_start = $i/($n-1);
      $range_end = ($i+1)/($n-1);
      $mix2 = ($fraction - $range_start) / ($range_end - $range_start);
      $mix1 = 1-$mix2;
      foreach ($colour as $_ => $val) {
        $colour[$_] = (int) ($mix1 * $val + $mix2 * $colour2[$_]);
      }
    }
    return "rgba($colour[r], $colour[g], $colour[b], $colour[a])";
  }
  /**
   * Calculate the factor.
   */
  protected function recalcFactor() {
    $this->factor = ($this->range_max - $this->range_min) / ($this->domain_max - $this->domain_min);
  }

}
