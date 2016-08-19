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

  /** DOMElement as cache of main section identified by getMainSection.
   */
  public $mainSectionNode;
  /**
   * @var bool Whether the content has been loaded.
   */
  public $content_loaded = FALSE;
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
   * @param NULL|string $url URL to load.
   */
  public function __construct($url=NULL) {
    if ($url) {
      $this->loadFromUrl($url);
    }
  }
  /**
   * Initialise with URL.
   */
  public function loadFromUrl($url) {
    $this->url = $url;
    $this->content_loaded = FALSE;
    $this->xpath = NULL;
    $this->dom = NULL;
  }
  /**
   * Initialise with HTML string.
   *
   * Useful for testing.
   */
  public function loadFromString($html) {
    $this->raw = $html;
    $this->parseRaw();
    return $this;
  }
  /**
   * Return a URL to a representative image.
   */
  public function getImageUrl() {
    $this->requireContents();

    // Try the og:image.
    if ($image = $this->getOgProperty('og:image')) {
      return $image;
    }

    // Try to fetch first image from main section.
    $nodes = $this->xpath->evaluate("//img", $this->getMainSection());
    if ($nodes->length) {
      return $nodes->item(0)->getAttribute('src');
    }

  }
  /**
   * Get HTML title.
   */
  public function getTitle() {
    $this->requireContents();
    return $this->getTextContentFromXPath('//html/head/title');
  }
  /**
   * Get a description.
   *
   * Return the first available description:
   *
   * 1. meta description
   * 2. meta og:description
   */
  public function getDescription() {
    $this->requireContents();

    if ($_ = $this->getMetaByName('description')) {
      return $_;
    }
    if ($_ = $this->getOgProperty('og:description')) {
      return $_;
    }

    // We can't find a description, take the start of the text.
    $text = substr($this->getMainText(), 0, 160);
    $i = strrpos($text, '.');
    if ($i===FALSE) {
      // No full stops here, just don't split a word.
      $i = strrpos($text, ' ');
      if ($i>0) {
        $text = substr($text, 0, $i) . '...';
      }
    }
    else {
      // Full stop found.
      $text = substr($text, 0, $i) . '...';
    }
    return $text;
  }
  /**
   * Return the content attribute from a meta element with given property.
   *
   * Works for og:image, for example.
   */
  public function getOgProperty($property) {
    $this->requireContents();

    $nodes = $this->xpath->evaluate("//html/head/meta[@property=\"$property\"]");
    if ($nodes->length > 0 && $nodes->item(0)->hasAttribute('content')) {
      return $nodes->item(0)->getAttribute('content');
    }

  }
  /**
   * Return the content attribute from a meta element with given name.
   *
   * e.g. description.
   */
  public function getMetaByName($name) {
    $this->requireContents();

    $nodes = $this->xpath->evaluate("//html/head/meta[@name=\"$name\"]");
    if ($nodes->length > 0 && $nodes->item(0)->hasAttribute('content')) {
      return $nodes->item(0)->getAttribute('content');
    }

  }
  /**
   * Helper.
   */
  public function getTextContentFromXPath($expression) {
    $this->requireContents();
    $nodes = $this->xpath->evaluate($expression);
    if ($nodes->length > 0) {
      return $nodes->item(0)->textContent;
    }
  }
  /**
   * Find main section and return plain text.
   *
   * All lines are trim()ed.
   */
  public function getMainText() {
    $this->requireContents();
    $node = $this->getMainSection();
    $text = '';
    foreach (explode("\n", $node->textContent) as $_) {
      $text .= trim($_) . "\n";
    }
    return trim($text);
  }
  public function getFaviconUrl() {
    $this->requireContents();
    // check for <link rel="shortcut icon" href="url" >
    $nodes = $this->xpath->evaluate("//html/head/link[@rel='shortcut icon']");
    if ($nodes->length > 0 && $nodes->item(0)->hasAttribute('href')) {
      return $nodes->item(0)->getAttribute('href');
    }

    // Default to domain/favion.ico.
    $u = $this->splitUrl();
    $_ = "{$u->protocol}{$u->domain}/favicon.ico";

    return $_;
  }
  public function splitUrl() {
    if (!preg_match('@^(https?://)([^/]+)((?:/[^?#]*)?)((?:[?][^#]*)?)((?:#.*)?)$@', $this->url, $matches)) {
      throw new \InvalidArgumentException("URL '$this->url' could not be parsed.");
    }
    return (object)[
      'protocol' => $matches[1],
      'domain'   => $matches[2],
      'path'     => $matches[3],
      'query'    => $matches[4],
      'fragment' => $matches[5],
    ];
  }
  /**
   * Ensures we have the content loaded and parsed.
   */
  protected function requireContents() {
    if ($this->content_loaded) {
      return;
    }
    // Content not loaded yet.
    if (empty($this->url)) {
      throw new \Exception("Cannot load content without URL.");
    }
    $this->raw = file_get_contents($this->url);
    $this->parseRaw();
    return $this;
  }
  /**
   * Attempt to find the main content section.
   *
   * @return DOMElement
   */
  protected function getMainSection() {
    if (isset($this->mainSectionNode)) {
      return $this->mainSectionNode;
    }

    // Is there a #main-content?
    $nodes = $this->xpath->evaluate("//*[@id='main-content']");
    if ($nodes->length > 0) {
      // This is probably an <a> tag.
      if (strtolower($nodes->item(0)->tagName) == 'a') {
        // Return the parent.
        $this->mainSectionNode = $nodes->item(0)->parentNode;
      }
      else {
        $this->mainSectionNode = $nodes->item(0);
      }
      return $this->mainSectionNode;
    }

    // HTML5 <main> tag?
    $nodes = $this->xpath->evaluate("//main");
    if ($nodes->length) {
      $this->mainSectionNode = $nodes->item(0);
      return $this->mainSectionNode;
    }

    // By default just return <body>
    $this->mainSectionNode = $this->xpath->evaluate("//body")->item(0);
    return $this->mainSectionNode;
  }
  /**
   * Parses the HTML.
   *
   * Called immediately by loadFromString() and called by requireContents().
   */
  protected function parseRaw() {
    $this->dom = new \DOMDocument();
    libxml_use_internal_errors(TRUE);
    $this->dom->loadHTML($this->raw);
    $this->xpath = new \DOMXPath($this->dom);
    $this->content_loaded = TRUE;
  }
}


