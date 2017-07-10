<?php
use \ArtfulRobot\Scale;

class ScaleTest extends \PHPUnit_Framework_TestCase {

  /**
   * @dataProvider scaleProvider
   */
  public function testScale($domain, $range, $x, $expected, $limit=FALSE) {
    $scale = new Scale($domain, $range);
    if ($limit) {
      $scale->limitToRange($limit);
    }
    $this->assertEquals($expected, $scale($x));
  }

  /**
   * provider.
   */
  public function scaleProvider() {
    return [
      [ [100], [200], 0, 0],
      [ [100], [200], 50, 100],
      [ [100], [200], 200, 400],
      [ [0, 100], [0, 200], 0, 0],
      [ [0, 100], [0, 200], 50, 100],
      [ [0, 100], [0, 200], 200, 400],
      [ [-100, 100], [0, 200], 0, 100],
      [ [-100, 100], [-200, 200], 0, 0],
      [ [-100, 100], [-200, 200], 10, 20],
      [ [-100, 100], [-200, 200], 10, 20],
      [ [-100, 100], [-200, 200], -10, -20],
      [ [100], [200], 0, 0, 1],
      [ [100], [200], 50, 100, 1 ],
      [ [100], [200], 200, 200, 1 ],
      [ [0, 100], [0, 200], 0, 0, 1 ],
      [ [0, 100], [0, 200], 50, 100, 1 ],
      [ [0, 100], [0, 200], 200, 200, 1 ],
      [ [-100, 100], [0, 200], 0, 100, 1 ],
      [ [-100, 100], [-200, 200], 0, 0, 1 ],
      [ [-100, 100], [-200, 200], 10, 20, 1 ],
      [ [-100, 100], [-200, 200], 10, 20, 1 ],
      [ [-100, 100], [-200, 200], -10, -20, 1 ],
    ];
  }

  /**
   * @dataProvider fractionProvider
   */
  public function testFraction($domain, $x, $expected, $limit=FALSE) {
    $scale = new Scale($domain, [0, 1000]);
    if ($limit) {
      $scale->limitToRange($limit);
    }
    $this->assertEquals($expected, $scale->fraction($x));
  }

  /**
   * provider.
   */
  public function fractionProvider() {
    return [
      [ [100], 0, 0],
      [ [100], 100, 1],
      [ [100], 50, 0.5],
      [ [100], 50, 0.5, 1],
      [ [100], 200, 1, 1],
      [ [100], -200, 0, 1],
    ];
  }

  /**
   * @dataProvider colourProvider
   */
  public function testRgba($domain, $colours, $x, $expected) {
    $scale = new Scale($domain);
    $scale->setColours($colours);
    $this->assertEquals($expected, $scale->rgba($x));
  }

  /**
   * provider.
   */
  public function colourProvider() {
    return [
      // Test with just two colours.
      [ [100], ['00000000', 'ffffffff'], 0, 'rgba(0, 0, 0, 0)'],
      [ [100], ['00000000', 'ffffffff'], 100, 'rgba(255, 255, 255, 255)'],
      [ [255], ['00000000', 'ffffffff'], 128, 'rgba(128, 128, 128, 128)'],
      [ [255], ['000000ff', '00ffffff'], 128, 'rgba(0, 128, 128, 255)'],
      // Tests with three colours.
      [ [255], ['000000ff', '00ff00ff', 'ff0000ff'], 0, 'rgba(0, 0, 0, 255)'],
      [ [255], ['000000ff', '00ff00ff', 'ff0000ff'], 255, 'rgba(255, 0, 0, 255)'],
      [ [255], ['000000ff', '00ff00ff', 'ff0000ff'], 127.5, 'rgba(0, 255, 0, 255)'],
      // Tests with three colours: mix.
      [ [255], ['000000ff', '00ff00ff', 'ff0000ff'], 32, 'rgba(0, 64, 0, 255)'],
      [ [255], ['000000ff', '00ff00ff', 'ff0000ff'], 160, 'rgba(65, 190, 0, 255)'],
    ];
  }

  /**
   * @dataProvider scaleExceptionProvider
   *
   * @expectedException InvalidArgumentException
   */
  public function testInvalidInput($domain, $range) {
    $scale = new Scale($domain, $range);
  }
  /**
   * provider.
   */
  public function scaleExceptionProvider() {
    return [
      [ [], [0, 200]],
      [ [100], []],
      [ [0], []],
      [ [10, 10], []],
    ];
  }
}

