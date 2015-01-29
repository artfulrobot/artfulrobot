<?php
namespace ArtfulRobot;

/**
 * This class does the application-only authenticated requests
 */
class RestApi_TwitterUser extends RestApi {

  /*
   * Each instance is an instance of a registered Twitter App.
   * Each app gets its own consumer key and secret
   */
  protected $consumer_key;
  protected $consumer_secret;

  /*
   * Access tokens
   *
   * These will have different values (inc. none) at diferent stages and uses.
   * They are only used for User-authenticated (3 legged) API use; the Application-only access
   * does not require these.
   */
  protected $oauth_token = '';
  protected $oauth_token_secret = '';

  /**
   * Holds token for application only access.
   */
  protected $bearer_token;

  /**
   * Each instance exists in a stage or mode for accessing the API.
   * Ready-states are STAGE_USER and STAGE_APP, but there are various steps
   * before achieving those.
   *
   * This property is used in determining how to sign requests.
   */
  protected $oauth_stage = 0;
  const
    STAGE_DEFAULT=0,
    STAGE_USER_REQUEST_TOKEN=1,
    STAGE_USER_REQUEST_ACCESS=2,
    STAGE_USER=3,
    STAGE_APP_REQUEST_BEARER=4,
    STAGE_APP=5
    ;

  /**
   * Used to hold the callback URL during STAGE_USER_REQUEST_TOKEN operation.
   */
  protected $oauth_callback = '';


  // Public Methods

  /**
   * Constructor called with array with at least consumer_key and consumer_secret keys.
   */
  public function __construct($settings) {

    $this->server = 'https://api.twitter.com';

    // required keys
    foreach (array('consumer_key', 'consumer_secret') as $_) {
      if (empty($settings[$_])) {
        throw new \Exception(__CLASS__ . " constructor called missing one or more of: consumer_key, consumer_secret");
      }
      $this->$_ = $settings[$_];
    }

    // optional keys
    foreach (array('oauth_token', 'oauth_token_secret', 'bearer_token') as $_) {
      $this->$_ = array_key_exists($_, $settings) ? $settings[$_] : '';
    }

    // Default to application mode.
    $this->setModeApplication();

  }

  /**
   * Part of Sign in with Twitter, issues a /oauth/request_token request.
   *
   * Pass the callback url.
   *
   * Returns a Twitter API URL to send the user to.
   */
  public function getRequestToken($callback_url) {
    $this->oauth_stage = self::STAGE_USER_REQUEST_TOKEN;
    $this->oauth_token = '';
    $this->oauth_token_secret = '';
    $this->oauth_callback = $callback_url;

    $response = $this->post('/oauth/request_token');
    if ($response->status == 200) {
      // e.g. oauth_token=4ZmPhaTN7VO9vJxlxmJzAJsB426MTL6k&oauth_token_secret=DQaUjai6Vxxxcd9IxgDtxurisER8K9VA&oauth_callback_confirmed=true
      parse_str($response->body, $details);
      return "https://api.twitter.com/oauth/authenticate?oauth_token=$details[oauth_token]";
    }
    throw new \Exception("Could not get request token from Twitter. ");
  }

  /**
   * Part of Sign in with Twitter, issues a /oauth/access_token request.
   *
   * Nb. both parameters are sent in the GET data to the callback by Twitter.
   *
   * Should result in oauth_token and oauth_token_secret being set up, ready
   * for doing calls against user quotas.
   */
  public function getAccessToken($oauth_verifier, $oauth_token) {
    $this->oauth_stage = self::STAGE_USER_REQUEST_ACCESS;
    $this->oauth_token = $oauth_token;
    $this->oauth_token_secret = '';
    $this->oauth_callback = '';

    $response = $this->post('/oauth/access_token',array(
      'oauth_verifier' => $oauth_verifier
    ));
    if ($response->status == 200) {
      // e.g. oauth_token=296175613-PWkvUMvxx0w5BjhHsgC7WtL1fh58QrKarfRpgwO7&oauth_token_secret=kZ6U9Qy0vxxCRUGbsxZPhe8Ss1qC9bvFq0OD6zS3tXIs0&user_id=296175613&screen_name=ArtfulRobot
      parse_str($response->body, $details);
      $this->oauth_token = $details['oauth_token'];
      $this->oauth_token_secret = $details['oauth_token_secret'];
      $this->user_id = $details['user_id'];
      $this->screen_name = $details['screen_name'];
    }
    throw new \Exception("Could not get access token from Twitter.");
  }
  /**
   * switch to accessing as Application-only
   */
  public function setModeApplication() {
    if ($this->bearer_token) {
      // We already have the bearer token, assume it's still fine and set the stage.
      $this->oauth_stage = self::STAGE_APP;
      return;
    }

    // No bearer token yet, start this process.

    $this->oauth_stage = self::STAGE_APP_REQUEST_BEARER;
    $response = $this->post('/oauth2/token', array('grant_type'=>'client_credentials'));
    if ($response->status != 200) {
      throw new \Exception("Failed to obtain bearer token");
    }
    $this->bearer_token = $response->body->access_token;
    $this->oauth_stage = self::STAGE_APP;

  }

  /**
   * Switch to accessing as User
   *
   * This will return true if it looks good to go, or FALSE if a new
   * authentication flow is needed.
   */
  public function setModeUser() {
    if ($this->oauth_token && $this->oauth_token_secret) {
      // We already have the access token, assume it's still fine and set the stage.
      $this->oauth_stage = self::STAGE_USER;
      return TRUE;
    }

    $this->oauth_stage = self::STAGE_USER_REQUEST_TOKEN;
    return FALSE;
  }


  // Protected Methods

  /**
   * Function to sign requests.
   */
  protected function buildRequestAlter() {
    if (!$this->payload) {
      $this->payload = array();
    }

    switch ($this->oauth_stage) {
    case self::STAGE_APP:
    case self::STAGE_APP_REQUEST_BEARER:
      return $this->signAsApp();
    case self::STAGE_USER_REQUEST_TOKEN:
    case self::STAGE_USER_REQUEST_ACCESS:
    case self::STAGE_USER:
      return $this->signAsUser();
    default:
      throw new \Exception("Attempted to send request without initialising tokens.");
    }
  }

  /**
   * This signs when using the object in Application-only authenticated mode.
   */
  protected function signAsApp() {
    if ($this->bearer_token) {
      $this->headers['Authorization'] = 'Bearer ' . $this->bearer_token;
    }
    else {
      $this->headers['Authorization'] = 'Basic '
        . base64_encode(rawurlencode($this->consumer_key) . ':'
        . rawurlencode($this->consumer_secret));
    }
  }

  /**
   * This signs when using the object in User auth mode.
   */
  protected function signAsUser() {
    $oauth_params = array(
      "oauth_consumer_key"     => $this->consumer_key,
      // once-only random string. Nb. this does not need rawurlencode because md5 fits.
      "oauth_nonce"            => md5(microtime(TRUE) . 'twitter' . time()),
      'oauth_signature_method' => 'HMAC-SHA1',
      'oauth_timestamp'        => time(),
      'oauth_version'          => '1.0',
    );

    if ($this->oauth_token) {
      // we have an access token, add it in.
      $oauth_params['oauth_token'] = $this->oauth_token;
    }

    if ($this->oauth_stage == self::STAGE_USER_REQUEST_TOKEN) {
      $oauth_params['oauth_callback'] = $this->oauth_callback;
    }

    $params_to_sign = array();
    // add in POST data
    foreach ($this->payload as $k=>$v) {
      $params_to_sign[ rawurlencode($k) ] = rawurlencode($v);
    }
    // next check the URL for data.
    $base_url = $this->url;
    $i = strpos($this->url,'?');
    if ($i !== FALSE) {
      // remove the query from the url to make the base_url used in the sig_base_string
      $base_url = substr($base_url,0, $i);
      foreach (explode('&', substr($this->url, $i+1)) as $kv) {
        list($k, $v) = explode('=', $kv);
        if (!isset($v)) {
          $v = '';
        }
        // As these have come from a URL we assume they are already urlencoded.
        $params_to_sign[$k] = $v;
      }
    }
    // now collect oauth parameters.
    foreach ($oauth_params as $k=>$v) {
      $params_to_sign[ rawurlencode($k) ] = rawurlencode($v);
    }

    // Create signature base string
    // ...parameter string
    $parameter_string = '';
    ksort($params_to_sign);
    foreach ($params_to_sign as $k=>$v) {
      $params_to_sign[$k] = "$k=$v";
    }
    $parameter_string = implode('&', $params_to_sign);

    // ... compile
    $sig_base_string = strtoupper($this->method)
      . '&' . rawurlencode($base_url)
      . '&' . rawurlencode($parameter_string);

    // Create signing key
    $signing_key = rawurlencode($this->consumer_secret) . '&'
      // when obtaining a request token, the token secret is not yet known.
      . rawurlencode($this->oauth_token_secret);

    // Create signature.
    $oauth_params['oauth_signature'] = rawurlencode(base64_encode(hash_hmac('sha1', $sig_base_string, $signing_key, TRUE)));

    // Store these which will need to go in the headers.
    foreach ($oauth_params as $k=>$v) {
      $oauth_params[$k] = "$k=\"$v\"";
    }
    $this->headers['Authorization'] = "OAuth "
      . implode(', ', $oauth_params);

  }

}
