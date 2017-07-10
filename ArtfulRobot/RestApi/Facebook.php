<?php
namespace ArtfulRobot;

/**
 * This class does the application-only authenticated requests.
 *
 * Sign In with Facebook:
 * $api = new RestApi_Facebook([
 *  'consumer_api' => 'xxx',
 *  'consumer_secret' => 'xxx',
 * ]);
 *
 * // get request token passing our callback.
 * // send user to facebook.
 * //  user comes back to the callback url given.
 * // request access token.
 * // ready.
 *
 */
class RestApi_Facebook extends RestApi {

  /*
   * Each instance is an instance of a registered Facebook App.
   * Each app gets its own app_id and we also need an access_token.
   */
  protected $app_id;

  // Public Methods

  /**
   * Constructor called with array with at least consumer_key and consumer_secret keys.
   */
  public function __construct($settings) {

    $this->server = 'https://graph.facebook.com';

    // required keys
    foreach (array('app_id', 'access_token') as $_) {
      if (empty($settings[$_])) {
        throw new \Exception(__CLASS__ . " constructor called missing one or more of: app_id, access_token");
      }
      $this->$_ = $settings[$_];
    }

    // optional keys
    foreach (array() as $_) {
      $this->$_ = array_key_exists($_, $settings) ? $settings[$_] : '';
    }

  }


  // Protected Methods

  /**
   * Function to sign requests.
   */
  protected function buildRequestAlter() {
    if (!$this->payload) {
      $this->payload = array();
    }
    $this->payload['access_token'] = $this->access_token;
  }

}

