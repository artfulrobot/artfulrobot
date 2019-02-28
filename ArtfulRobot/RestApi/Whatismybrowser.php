<?php
namespace ArtfulRobot;

/**
 * $api = new RestApi_Whatismybrowser(['api_key' => 'xxx']);
 * $api->parseUserAgent($ua);
 */
class RestApi_Whatismybrowser extends RestApi {

  protected $api_key;

  // Public Methods
  /**
   * Constructor called with array with at least consumer_key and consumer_secret keys.
   */
  public function __construct($settings) {

    $this->server = 'https://api.whatismybrowser.com/api/v2';

    // required keys
    if (empty($settings['api_key'])) {
      throw new \Exception(__CLASS__ . " constructor called missing one or more of: consumer_key, consumer_secret");
    }
    $this->api_key = $settings['api_key'];

    $this->setJson();
  }

  // Public sugar methods.
  public function parseUserAgent($ua=NULL) {
    if ($ua === NULL) {
      $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    if (empty($ua)) {
      return;
    }

    $response = $this->post('/user_agent_parse', ['user_agent' => $ua]);
    if ($response->status != 200) {
      return;
    }
    return $response->body->parse;
  }

  // Protected Methods
  /**
   * Function to add header
   */
  protected function buildRequestAlter() {
    if (!$this->payload) {
      $this->payload = array();
    }
    $this->headers['X-API-KEY'] = $this->api_key;
  }
}
