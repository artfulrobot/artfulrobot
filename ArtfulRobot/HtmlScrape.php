<?php
/**
 * @file
 * This class helps you scrape pages for key info.
 */

namespace ArtfulRobot;

/**
 * HTML page scraper.
 */
class HtmlScrape {
  /**
   * The URL this came from.
   */
  public $url;

  /**
   * The raw content of the URL.
   */
  public $raw;

  /**
   * DOMDocument object
   */
  public $dom;

  /**
   * DOMXPath object
   */
  public $xpath;

  /**
   * Convenience factory method.
   */
  public static function factory($url) {
    $scraper = new HtmlScrape($url);
    return $scraper;
  }
  /**
   * Constructor.
   *
   * @param string $url URL to load.
   */
  public function __construct($url=NULL) {
    $this->url = $url;
    $this->raw = file_get_contents($url);
    $this->dom = new \DOMDocument();
    $this->dom->loadHTML($this->raw);
    $this->xpath = new \DOMXPath($this->dom);
  }
  /**
   * Return a URL to a representative image.
   */
  public function getImageUrl() {

    // Try the og:image.
    $image = $this->getOgProperty('og:image');
    if ($image) {
      return $image;
    }

  }
  /**
   * Get HTML title.
   */
  public function getTitle() {
    return $this->getTextContentFromXPath('//html/head/title');
  }
  /**
   * Get a description.
   */
  public function getDescription() {

    $nodes = $this->xpath->evaluate("//html/head/meta[@name='description']");
    if ($nodes->length > 0 && $nodes->item(0)->hasAttribute('content')) {
      return $nodes->item(0)->getAttribute('content');
    }

  }
  /**
   * Return the content attribute from a meta element with given property.
   *
   * Works for og:image, for example.
   */
  public function getOgProperty($property) {

    $nodes = $this->xpath->evaluate("//html/head/meta[@property=\"$property\"]");
    if ($nodes->length > 0 && $nodes->item(0)->hasAttribute('content')) {
      return $nodes->item(0)->getAttribute('content');
    }

  }
  /**
   * Helper.
   */
  public function getTextContentFromXPath($expression) {
    $nodes = $this->xpath->evaluate($expression);
    if ($nodes->length > 0) {
      return $nodes->item(0)->textContent;
    }
  }
}


