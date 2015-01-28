<?php
namespace ArtfulRobot;
/**
 * @file class to access REST API.
 *
 * @author Rich Lott | Artful Robot
 * @licence GPL3+
 *
 * Synopsis:
 *
 * $rest = new \FoE\RestApi('http://example.com');
 * $rest->create($uri, $data);
 * $rest->get($uri);
 * $rest->delete($uri);
 * $rest->update($uri);
 *
 */

class RestApi {
  /** server to send requests to */
  protected $server = '';

  /** Configure which http method to use for which REST verb. */
  protected $verb_to_http_method = array(
    'POST'   => 'POST',
    'DELETE' => 'DELETE',
    'GET'    => 'GET',
    'PUT'    => 'PUT',
  );

  /** Whether to send data as json in POST requests */
  protected $json_request = TRUE;

  /** Whether to request json in the return */
  protected $accept_json_response = TRUE;

  /** auth string username:password, if HTTP basic auth is needed.*/
  protected $http_basic_auth = '';
  /** Should the host verify our certificate? boolean. */
  protected $ssl_verify_peer = TRUE;
  /** Should we verify the host's certificate? 0, 1, 2 */
  protected $ssl_verify_host = 2;
  /** Turn on XDEBUG-ing */
  protected $xdebug_ide_key = '';
  /**
   * Standard constructor optionally takes base server URL.
   *
   * Sub classes may override this and use settings differently,
   * e.g. twitter API requires consumer secrets etc.
   *
   * example:
   * $rest = new RestApi('http://example.com');
   * $thing_five = $rest->get('/things/5');
   */
  public function __construct($settings=null) {
    if ($settings !== null) {
      if (is_string($settings)) {
        $this->server = $settings;
      }
      else {
        throw new \Exception(__CLASS__
          . " expects a base URL for the REST endpoing server, or nothing. "
          . gettype($settings) . " received.");
      }
    }
  }

  /** Perform a REST GET command for fetching resources.
   *
   * This must have no side effects (i.e. it must not create, delete or update anything).
   *
   * The uri can be a string, or a template, see RestApi::request()
   */
  public function get($uri, $data=null) {
    return $this->request('GET', $uri, $data);
  }

  /** Perform a REST POST command for creating new resources.
   *
   * The uri can be a string, or a template, see RestApi::request()
   */
  public function post($uri, $data=null) {
    return $this->request('POST', $uri, $data);
  }

  /** Perform a REST DELETE command for deleting resources.
   *
   * The uri can be a string, or a template, see RestApi::request()
   */
  public function delete($uri, $data=null) {
    return $this->request('DELETE', $uri, $data);
  }

  /** Perform a REST PUT command for updating resources.
   *
   * The uri can be a string, or a template, see RestApi::request()
   */
  public function put($uri, $data=null) {
    return $this->request('PUT', $uri, $data);
  }

  /**
   * Set up username and password for HTTP basic Auth. Chainable.
   */
  public function setAuth($user, $password) {
    if ($user) {
      $this->http_basic_auth = "$user:$password";
    }
    else {
      $this->http_basic_auth = '';
    }
    return $this;
  }

  /**
   * Whether to do SSL certificat verifications.
   */
  public function setSslVerify($peer=TRUE, $host=2) {
    $host = (int) $host;
    if ($host < 0 || $host >2) {
      throw new \Exception("'$host' is not a valid value for ssl_verify_host (0, 1, 2 are possible.) ");
    }
    $this->ssl_verify_peer = (bool) $peer;
    $this->ssl_verify_host = (int) $host;
    return $this;
  }

  /**
   * Request JSON request/response. Chainable.
   */
  public function setJson($request=TRUE,$response=TRUE) {
    $this->json_request = (bool) $request;
    $this->accept_json_response = (bool) $response;
    return $this;
  }
  /**
   * Set XDEBUG_SESSION_START on URL. Chainable.
   */
  public function setXdebugIde($ide_key='') {
    if ($ide_key && !is_string($ide_key)) {
    }
    $this->xdebug_ide_key = $ide_key;
    return $this;
  }
  /** check if a verb is valid */
  public function verbIsValid($verb) {
    return array_key_exists($verb, $this->verb_to_http_method);
  }
  /** static shortcut method */
  public static function api($verb, $url, $data=null) {
    $rest = new RestApi();
    if (!$rest->verbIsValid($verb)) {
      throw new \Exception("Verb '$verb' is invalid");
    }
    return $rest->$verb($url, $data);
  }
  /**
   * Set up headers
   *
   * Extend this if your service requires special headers, e.g. OAuth signing.
   */
  protected function alterHeaders(&$headers) {

  }

  /**
   * Common request method used by get, post, delete, put.
   *
   * Allow $uri templating: Array('/some/%template/url', array('%template' => 'foo'));
   *
   * @todo Currently we ignore headers in responses. If we need these we'll impliment it.
   */
  protected function request($method, $uri, $data) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    $headers = array();

    // Impliment templating for uris
    if (is_array($uri)) {
      $uri = strtr(reset($uri), end($uri));
    }

    if ($data) {
      // Are we to make it into JSON?
      if ($this->json_request && $method != 'GET') {
        $data = json_encode($data);
        $headers['Content-Type'] = 'Application/json;charset=UTF-8';
      }
      else {
        $data = $this->buildQuery($data);
      }
      // Where to put any data
      if ($method == 'GET') {
        $uri .= '?' . $data;
      }
      else {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $headers['Content-Length'] = strlen($data) ;
      }
    }

    // Prefer JSON response?
    if ($this->accept_json_response) {
      $headers['Accept'] =  'Application/json';
    }

    // Set headers.
    $this->alterHeaders($headers);
    if ($headers) {
      foreach ($headers as $k=>$v) {
        $headers[$k] = "$k: $v";
      }
      curl_setopt($curl, CURLOPT_HTTPHEADER, array_values($headers));
    }

    // XDebug?
    if ($this->xdebug_ide_key) {
      $uri .= ((strpos($uri, '?')>0) ? '&' : '?' ) . "XDEBUG_SESSION_START=" . rawurlencode($this->xdebug_ide_key);
    }

    // Optional Authentication:
    if ($this->http_basic_auth) {
      curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
      curl_setopt($curl, CURLOPT_USERPWD, $this->http_basic_auth);
    }

    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->ssl_verify_peer);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->ssl_verify_host);

    curl_setopt($curl, CURLOPT_URL, $this->server . $uri);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);

    return RestApiResponse::createFromCurl($result, $info);
  }
  /**
   * Function to build the query string.
   *
   * This is separated out so that subclasses can extend this function,
   * e.g. to provide signing.
   */
  protected function buildQuery($data) {
    return http_build_query($data);
  }

}
