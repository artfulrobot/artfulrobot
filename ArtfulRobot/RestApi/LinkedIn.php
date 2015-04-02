<?php
namespace ArtfulRobot;

/**
 * This class does the application-only authenticated requests
 */
class RestApi_LinkedIn extends RestApi {

  const LI_SERVER_AUTH = 'https://www.linkedin.com',
        LI_SERVER_API  = 'https://api.linkedin.com';

  protected $consumer_key;
  protected $consumer_secret;
  protected $oauth_user_token;
  protected $oauth_user_secret;
  protected $bearer_token;

  /**
   * Constructor called with array with at least consumer_key and consumer_secret keys.
   */
  public function __construct($settings) {

    $this->server = 'https://api.linkedin.com';
    $this->setJson(FALSE, TRUE);

      // required keys
      foreach (array('consumer_key','consumer_secret', 'oauth_user_token', 'oauth_user_secret') as $_) {
        if (empty($settings[$_])) {
          throw new \Exception(__CLASS__ . " constructor called missing $_");
        }
        $this->$_ = $settings[$_];
      }
      // optional keys
      foreach (array('bearer_token') as $_) {
        $this->$_ = array_key_exists($_, $settings) ? $settings[$_] : '';
      }

  }

  /**
   * Function to sign requests.
   */
  protected function buildRequestAlter() {
    // Request JSON data
    $this->headers['x-li-format'] = 'json';

    // if we need auth...
    if ($this->server == static::LI_SERVER_API) {
      if ($this->bearer_token) {
        $this->headers['Authorization'] = "Bearer $this->bearer_token";
      }
    }

  }


  /**
   * Return a link to redirect user to for getting an authorized access token
   * https://developer.linkedin.com/docs/oauth2
   */
  public function getAuthRedirect($redirect_uri, $csrf, $scopes=[])
  {
    $url = static::LI_SERVER_AUTH . '/uas/oauth2/authorization?response_type=code&client_id='
      . $this->consumer_key . '&redirect_uri=' . rawurlencode($redirect_uri) . "&state=$csrf";

    if ($scopes) {
      $url .= '&scope=' . rawurlencode(implode(' ', $scopes));
    }

    return $url;
  }

  /**
   * Exchange Authorization Code for a Request Token
   * https://developer.linkedin.com/docs/oauth2
   *
   * If successful, this sets the bearer token in this object for future use.
   */
  public function getRequestToken($authorization_code, $redirect_uri) {

    // Temporarily switch servers
    $this->server = static::LI_SERVER_AUTH;
    $result = $this->post('/uas/oauth2/accessToken', [
      'grant_type'    => 'authorization_code',
      'code'          => $authorization_code,
      'redirect_uri'  => $redirect_uri,
      'client_id'     => $this->consumer_key,
      'client_secret' => $this->consumer_secret,
      ]);
    $this->server = static::LI_SERVER_API;

    if ($result->status==200 && !empty($result->body->access_token)) {
      // Store it.
      $this->bearer_token = $result->body->access_token;
    }
    return $result;
  }

  public function __get($prop) {
    if (in_array($prop, ['bearer_token'])) {
      return $this->$prop;
    }
    throw new Exception("Attempted to access $prop - not gettable property");
  }

}
