<?php
use \ArtfulRobot\HtmlScrape;

class HtmlScrapeTest extends \PHPUnit_Framework_TestCase {
  const FIXTURE_URL_1 = './tests/fixtures/scrape1.html';
  const FIXTURE_URL_2 = './tests/fixtures/scrape2.html';
  const FIXTURE_URL_3 = './tests/fixtures/scrape3.html';
  const FIXTURE_URL_FACEBOOK_1 = './tests/fixtures/scrape-facebook-1.html';

  public function testParseUrl() {
    foreach ([
      'http://example.com' => (object)['protocol'=>'http://', 'domain'=>'example.com', 'path'=>'', 'query'=>'', 'fragment'=>''],
      'http://example.com/' => (object)['protocol'=>'http://', 'domain'=>'example.com', 'path'=>'/', 'query'=>'', 'fragment'=>''],
      'http://example.com/some/path?some=query&foo=bar#here' => (object)['protocol'=>'http://', 'domain'=>'example.com', 'path'=>'/some/path', 'query'=>'?some=query&foo=bar', 'fragment'=>'#here'],
    ] as $input=>$expected_output) {
      $actual = HtmlScrape::factory($input)->splitUrl();
      $this->assertEquals($expected_output, $actual);
    }
  }
  public function testFavicon() {
    $favicon = HtmlScrape::factory(self::FIXTURE_URL_1)
      ->getFaviconUrl();
    $this->assertEquals('http://example.com/some/favicon.ico', $favicon);
  }
  public function testImage() {

    $url = HtmlScrape::factory(self::FIXTURE_URL_1)
      ->getImageUrl();
    $this->assertEquals('http://example.com/image.jpg', $url);

    $url = HtmlScrape::factory(self::FIXTURE_URL_2)
      ->getImageUrl();
    $this->assertEquals('http://example.com/image.jpg', $url);

    //$this->assertEquals('https://scontent-cdg2-1.xx.fbcdn.net/v/t1.0-0/s480x480/13912360_826628420770281_2857235518930843627_n.jpg?oh=a69e81388a4b1e9d2c5753eff9c2bbbb&oe=58516057', $url);
    
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

    $_ = HtmlScrape::factory(self::FIXTURE_URL_2)
      ->getDescription();
    $this->assertEquals('This is the og:description.', $_);

    // Document without any description, should take the first bit of the main content.
    $_ = HtmlScrape::factory(self::FIXTURE_URL_3)
      ->getDescription();
    $this->assertEquals("Test document\nThis document is all about socks.\nThere's a lot of information in here...",
      $_);

    // Facebook post.
    $x=1;
    $_ = HtmlScrape::factory(self::FIXTURE_URL_FACEBOOK_1)
      ->getDescription();
    $this->assertEquals("People & Planet, Oxford, Oxfordshire. 7,798 likes · 96 talking about this · 4 were here. Student action on human rights, world poverty and the...",
      $_);
  }
  public function testMain() {
    $_ = HtmlScrape::factory(self::FIXTURE_URL_1)
      ->getMainText();
    $this->assertEquals("Test document\nThis document is all about socks.", $_);

    $_ = HtmlScrape::factory(self::FIXTURE_URL_2)
      ->getMainText();
    $this->assertEquals("Test document\nScrape2 uses drupal style #main-content anchor", $_);
  }
}

