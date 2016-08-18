<?php
use \ArtfulRobot\HtmlScrape;

class HtmlScrapeTest extends \PHPUnit_Framework_TestCase {
  const FIXTURE_URL_1 = './tests/fixtures/scrape1.html';

  public function testOgImage() {
    $url = HtmlScrape::factory(self::FIXTURE_URL_1)
      ->getImageUrl();
    $this->assertEquals('http://example.com/image.jpg', $url);
  }
  public function testTitle() {
    $_ = HtmlScrape::factory(self::FIXTURE_URL_1)
      ->getTitle();
    $this->assertEquals('HtmlScrape test fixture 1', $_);
  }
  public function testDescription() {
    $_ = HtmlScrape::factory(self::FIXTURE_URL_1)
      ->getDescription();
    $this->assertEquals('This is the meta description.', $_);
  }
}

