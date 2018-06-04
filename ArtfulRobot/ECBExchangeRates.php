<?php
/**
 *
 * @file
 * Class to provide currency conversions based on European Central Bank's public data.
 *
 * @see https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html
 *
 * Typical usage:
 *
 *     $converter = new ECBExchangeRates();
 *
 * Convert from one currency to another:
 *     $value_in_eur = $value_in_gbp * $converter->getRate('EUR');
 *     $value_in_usd = $value_in_eur * $converter->getRate('USD', 'EUR');
 *
 * You can get all the rates for a given currency in an array like:
 *     $all_rates = $converter->getRates('GBP');
 *
 */
namespace ArtfulRobot;
use SimpleXMLElement;

class ECBExchangeRates {
	const XML_ENDPOINT_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

  /**
   * @var array $rates. Keys are currencies, values are rates.
   */
  public $rates;

  /**
   * Load from external source.
   */
	public function loadFromECB($url=NULL) {
    if (!$url) {
      $url = static::XML_ENDPOINT_URL;
    }
		$xml = @simplexml_load_file($url);
    if (!$xml) {
      throw new \RuntimeException("Rates could not be loaded from ECB");
    }
    return $this->extractFromSimpleXML($xml);
	}

  /**
   * Load from external source.
   *
   * @return ECBExchangeRates $this.
   */
	public function loadFromArray($rates) {
    $this->rates = $rates;
    $this->rates['EUR'] = 1.0;
    return $this;
	}

  /**
   * Load from XML
   *
   * @param SimpleXMLElement $xml
   */
	public function extractFromSimpleXML(SimpleXMLElement $xml) {
		$this->rates = [];
    if (!isset($xml->Cube->Cube->Cube)) {
      throw new \RuntimeException("Invalid data loaded.");
    }
		foreach($xml->Cube->Cube->Cube as $rate) {
      if (!isset($rate['currency']) || !isset($rate['rate'])) {
        throw new \RuntimeException("Invalid data loaded - missing currency or rate attribute(s).");
      }
      $this->rates[(string) $rate['currency']] = (double) $rate['rate'];
	  }
    $this->rates['EUR'] = 1.0;
    return $this;
	}
  /**
   * Get the rate based on a particular other currency.
   *
   * @param string $currency
   * @param string $from_currency Defaults to GBP.
   * @return float.
   */
  public function getRate($currency, $from_currency='GBP') {
    $this->getRates();
    $this->assertValidCurrency($currency);
    $this->assertValidCurrency($from_currency);
    return $this->rates[$currency] / $this->rates[$from_currency];
  }
  /**
   * Check we have a rate for the given currency.
   *
   * @param string $currency e.g. GBP
   */
  protected function assertValidCurrency($currency) {
    if (!isset($this->rates[$currency])) {
      throw new \InvalidArgumentException("Unknown currency: '$currency'");
    }
  }
  /**
   * Access full rates array.
   *
   * @param string $from_currency Defaults to GBP
   * @param bool $inverse.
   * @return array with keys of currency, values of rates such that:
   *   Value in EUR = Value in GBP * $return['EUR'];
   *
   * if $inverse is TRUE then:
   *   Value in GBP = Value in EUR * $return['EUR'];
   *
   */
  public function getRates($from_currency='GBP', $inverse=FALSE) {
    if (!isset($this->rates)) {
      throw new \RuntimeException("Rates not loaded");
    }
    $this->assertValidCurrency($from_currency);

    $mapped = [];
    $base_rate = $this->rates[$from_currency];
    if ($inverse) {
      foreach ($this->rates as $currency => $rate) {
        $mapped[$currency] = $base_rate / $rate;
      }
    }
    else {
      foreach ($this->rates as $currency => $rate) {
        $mapped[$currency] = $rate / $base_rate;
      }
    }
    return $mapped;
  }
}
