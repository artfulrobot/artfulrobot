<?php
/**
 * @file
 * Tests for ECBExchangeRates class.
 *
 */

use \ArtfulRobot\ECBExchangeRates;

class ECBExchangeRatesTest extends \PHPUnit_Framework_TestCase
{
  // the actual test:
  public function getFixtureObj() {
    $x = new ECBExchangeRates();
    $x->loadFromArray([
      'GBP' => 0.85,
      'USD' => 1.16,
    ]);
    return $x;
  }
  /**
   */
  public function testExpectedWayAround() {
    $x = $this->getFixtureObj();

    // We expect there to be more than 1 USD per GBP. (even if this becomes untrue, it's true given the fixture data)
    $this->assertGreaterThan(1, $x->getRate('USD'));
    // And vice versa.
    $this->assertLessThan(1, $x->getRate('GBP', 'USD'));
    // And check via getRates.
    $rates = $x->getRates();
    $this->assertGreaterThan(1, $rates['USD']);
    $rates = $x->getRates('GBP', TRUE);
    $this->assertLessThan(1, $rates['USD']);
  }
		/**
		 * @dataProvider ratesProvider
		 */
  public function testExacts($from, $to, $expected_rate) {
    $result = $this->getFixtureObj()->getRate($to, $from);
    $this->assertEquals($expected_rate, $result, "Error converting from $from to $to. Got $result, expected $expected_rate");
  }
  /**
   * Data provider for testTwo.
   */
  public function ratesProvider() {
    return [
      [ 'EUR', 'EUR', 1 ], // Sanity check.
      [ 'GBP', 'GBP', 1 ], // Sanity check.
      [ 'GBP', 'EUR', 1/0.85 ], // How many EUR is 1 GBP?
      [ 'EUR', 'GBP', 0.85 ], // How many GBP is 1 EUR?
      [ 'GBP', 'USD', 1.16/0.85 ],  // How many USD is 1 GBP?
    ];
  }
  /**
   * Check exception thrown for unknown currency.
   *
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage Unknown currency: 'XXX'
   */
  public function testUnknownCurrency() {
    $result = $this->getFixtureObj()->getRate('XXX');
  }
		/**
		 * @dataProvider ratesProvider
		 */
  public function testRates() {
    $result = $this->getFixtureObj()->getRates();
    $this->assertEquals([
      'EUR' => 1/0.85,
      'GBP' => 1,
      'USD' => 1.16/0.85,
    ], $result);
  }
		/**
		 * @dataProvider ratesProvider
		 */
  public function testRatesEUR() {
    $result = $this->getFixtureObj()->getRates('EUR');
    $this->assertEquals([
      'EUR' => 1,
      'GBP' => 0.85,
      'USD' => 1.16,
    ], $result);
  }
}
