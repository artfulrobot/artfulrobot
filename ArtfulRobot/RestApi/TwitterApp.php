<?php
namespace ArtfulRobot;

/**
 * This class does the application-only authenticated requests
 */
class RestApi_TwitterApp extends RestApi {

  protected $consumer_key;
  protected $consumer_secret;
  protected $bearer_token;

  static protected $bearer_token_cache = array();

  public function __construct($settings) {
    $this->consumer_key = $settings['consumer_key'];
    $this->consumer_secret = $settings['consumer_secret'];
    $this->setJson(FALSE, TRUE);


    if (!empty(static::$bearer_token_cache[$this->consumer_key])) {
      // use cached bearer_token for this consumer_key
      $this->bearer_token = static::$bearer_token_cache[$this->consumer_key];
    }
    else {
      // Our first job is to request bearer keys.
      $this->server = '';
      $response = $this->post('https://api.twitter.com/oauth2/token', array('grant_type'=>'client_credentials'));
      if ($response->status != 200) {
        throw new \Exception("Failed to obtain bearer token");
      }
      static::$bearer_token_cache[$this->consumer_key] =
        $this->bearer_token =
        $response->body->access_token;
    }

    // Ready, set normal endpoint.
    parent::__construct('https://api.twitter.com/1.1');
  }

  /**
   * Set up headers
   */
  protected function alterHeaders(&$headers) {
    if ($this->bearer_token) {
      $headers['Authorization'] = 'Bearer ' . $this->bearer_token;
    }
    else {
      $headers['Authorization'] = 'Basic ' .base64_encode(rawurlencode($this->consumer_key) . ':' . rawurlencode($this->consumer_secret));
    }
  }

}
