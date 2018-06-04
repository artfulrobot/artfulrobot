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
  /**
   * Test that parsing XML works.
   */
  public function testParseXml() {
    $xml = simplexml_load_string(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
	<gesmes:subject>Reference rates</gesmes:subject>
	<gesmes:Sender>
		<gesmes:name>European Central Bank</gesmes:name>
	</gesmes:Sender>
	<Cube>
		<Cube time='2018-06-04'>
			<Cube currency='USD' rate='1.1737'/>
			<Cube currency='JPY' rate='128.44'/>
			<Cube currency='BGN' rate='1.9558'/>
			<Cube currency='CZK' rate='25.696'/>
			<Cube currency='DKK' rate='7.4434'/>
			<Cube currency='GBP' rate='0.87673'/>
			<Cube currency='HUF' rate='318.64'/>
			<Cube currency='PLN' rate='4.2848'/>
			<Cube currency='RON' rate='4.6548'/>
			<Cube currency='SEK' rate='10.2583'/>
			<Cube currency='CHF' rate='1.1546'/>
			<Cube currency='ISK' rate='122.50'/>
			<Cube currency='NOK' rate='9.5030'/>
			<Cube currency='HRK' rate='7.3790'/>
			<Cube currency='RUB' rate='72.6626'/>
			<Cube currency='TRY' rate='5.4193'/>
			<Cube currency='AUD' rate='1.5311'/>
			<Cube currency='BRL' rate='4.3893'/>
			<Cube currency='CAD' rate='1.5148'/>
			<Cube currency='CNY' rate='7.5166'/>
			<Cube currency='HKD' rate='9.2091'/>
			<Cube currency='IDR' rate='16281.57'/>
			<Cube currency='ILS' rate='4.1836'/>
			<Cube currency='INR' rate='78.7120'/>
			<Cube currency='KRW' rate='1254.88'/>
			<Cube currency='MXN' rate='23.3661'/>
			<Cube currency='MYR' rate='4.6655'/>
			<Cube currency='NZD' rate='1.6654'/>
			<Cube currency='PHP' rate='61.647'/>
			<Cube currency='SGD' rate='1.5655'/>
			<Cube currency='THB' rate='37.511'/>
			<Cube currency='ZAR' rate='14.7053'/>
		</Cube>
	</Cube>
</gesmes:Envelope>
XML
		);
		$x = new ECBExchangeRates();
    $x->extractFromSimpleXML($xml);
    $this->assertEquals(0.87673, $x->getRate('GBP', 'EUR'));
    $this->assertEquals(1/0.87673, $x->getRate('EUR'));

  }
  /**
   * Test that wrong XML isn't parsed - completely random.
   *
   * @expectedException \RuntimeException
   * @expectedExceptionMessage Invalid data loaded.
   */
  public function testParseDuffXml1() {
    $xml = simplexml_load_string(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<something></something>
XML
		);
		$x = new ECBExchangeRates();
    $x->extractFromSimpleXML($xml);
    $this->assertEquals(0.87673, $x->getRate('GBP', 'EUR'));
    $this->assertEquals(1/0.87673, $x->getRate('EUR'));

  }
  /**
   * Test that wrong XML isn't parsed - completely random.
   *
   * @expectedException \RuntimeException
   * @expectedExceptionMessage Invalid data loaded - missing currency or rate attribute(s).
   */
  public function testParseDuffXml2() {
    $xml = simplexml_load_string(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
	<Cube>
		<Cube time='2018-06-04'>
			<Cube wrong-attr='USD' foo='1.1737'/>
    </Cube>
  </Cube>
</gesmes:Envelope>
XML
		);
		$x = new ECBExchangeRates();
    $x->extractFromSimpleXML($xml);
  }
  /**
   * Test how network error works.
   *
   * @expectedException \RuntimeException
   * @expectedExceptionMessage Rates could not be loaded from ECB
   */
  public function testNetworkError() {
		$x = new ECBExchangeRates();
    $x->loadFromECB('http://localhost/__this-will-not-work__');
  }
}
