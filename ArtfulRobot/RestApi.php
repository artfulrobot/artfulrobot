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
 * $rest = new \ArtfulRobot\RestApi('http://example.com');
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

  /** Whether to send data as json */
  protected $json_request = FALSE;

  /** Whether to request json in the return */
  protected $require_json_response = TRUE;

  /** auth string username:password, if HTTP basic auth is needed.*/
  protected $http_basic_auth = '';
  /** Should the host verify our certificate? boolean. */
  protected $ssl_verify_peer = TRUE;
  /** Should we verify the host's certificate? 0, 1, 2 */
  protected $ssl_verify_host = 2;
  /** Turn on XDEBUG-ing */
  protected $xdebug_ide_key = '';


  // Per-request internals
  protected $method = '';
  protected $url = '';
  protected $headers = array();
  protected $payload;
  protected $body;

  // Public methods for configuration.

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
    $this->require_json_response = (bool) $response;
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


  // Public methods for making (or testing) requests

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

  /** Perform a REST PATCH command for updating resources.
   *
   * The uri can be a string, or a template, see RestApi::request()
   */
  public function patch($uri, $data=null) {
    return $this->request('PATCH', $uri, $data);
  }

  /** Perform a REST PUT command for updating resources.
   *
   * The uri can be a string, or a template, see RestApi::request()
   */
  public function put($uri, $data=null) {
    return $this->request('PUT', $uri, $data);
  }

  /**
   * Debug
   */
  public function getMockRequest($method, $uri='', $data=null) {
    $this->buildRequest($method, $uri, $data);
    return $this->requestMock();
  }

  /** static shortcut method */
  public static function api($verb, $url, $data=null) {
    $rest = new RestApi();
    if (!$rest->verbIsValid($verb)) {
      throw new \Exception("Verb '$verb' is invalid");
    }
    return $rest->$verb($url, $data);
  }


  // Private internal methods

  /**
   * Set up things we offer.
   */
  protected function buildRequest($method, $uri, $data) {

    // Initialise per-request internal vars.
    $this->method = $method;
    $this->url = '';
    $this->body = '';
    $this->headers = array();
    $this->payload = $data;

    // What url are we sending to?
    if (is_array($uri)) {
      // Implement templating.
      $this->url = strtr($this->server . $uri[0], $uri[1]);
    }
    else {
      $this->url = $this->server . $uri;
    }

    // Now allow customisation
    $this->buildRequestAlter();

    // Finally put payload in headers or body

    // Our json_request flag encodes the data as json
    if ($this->json_request) {
      $this->headers['Content-Type'] = 'Application/json;charset=UTF-8';
      $this->payload = json_encode($this->payload);
    }

    // Apply default encoding unless we already have a string.
    if (!is_string($this->payload) && $this->payload !== null) {
      $this->payload = http_build_query($this->payload);
    }


    // Where to put data?
    if ($this->payload) {
      if ($this->method == 'GET') {
        // Append to or add a query string.
        $this->url .= ((strpos($this->url, '?')===FALSE) ? '?' : '&')
          . $this->payload;
      }
      else {
        $this->body = $this->payload;
      }
    }

    // XDebug support.
    if ($this->xdebug_ide_key) {
      $this->url .=
        ( (strpos($this->url,'?')===FALSE)
          ? '?'
          : '&' )
        . "XDEBUG_SESSION_START=" . rawurlencode($this->xdebug_ide_key);
    }
    // Clear this, not needed now.
    $this->payload = '';
  }
  /**
   * Allow sub classes to customise the request
   *
   * This may alter $this->payload and $this->headers
   *
   * At the end, the payload will either be a ready built string or
   * an array which will be processed as follows:
   *
   * - if json is to be used, and it's not a GET request,
   *   json_encode will be called on it.
   * - then http_build_query will be applied.
   */
  protected function buildRequestAlter() {

  }

  /**
   * Common request method used by get, post, delete, put.
   *
   * Allow $uri templating: Array('/some/%template/url', array('%template' => 'foo'));
   *
   * @todo Currently we ignore headers in responses. If we need these we'll impliment it.
   *
   * @throws RestApi_NetworkException
   */
  protected function request($method, $uri, $data) {

    // Most customisation will be done here.
    // It should end with a headers array and body.
    $this->buildRequest($method, $uri, $data);
    // Now make request.

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->method);

    if ($this->body) {
      curl_setopt($curl, CURLOPT_POSTFIELDS, $this->body);
      $headers['Content-Length'] = strlen($this->body) ;
    }

    // Set headers.
    $headers = array();
    foreach ($this->headers as $k=>$v) {
      $headers[] = "$k: $v";
    }
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    // Optional Authentication:
    if ($this->http_basic_auth) {
      curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
      curl_setopt($curl, CURLOPT_USERPWD, $this->http_basic_auth);
    }
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->ssl_verify_peer);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->ssl_verify_host);
    curl_setopt($curl, CURLOPT_URL, $this->url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);

    // Check response.
    if (empty($info['http_code'])) {
      throw new RestApi_NetworkException("Missing http_code in curl response info");
    }

    // Should we try to decode a json response?
    if ($result
      && ($this->require_json_response
      || (isset($info['content_type']) && preg_match('@^application/(problem\+)?json\b@i', $info['content_type'])))
    ) {

        // We were sent json, or we require it.
        $result = json_decode($result);
    }

    // Pass on to another method to create the response object, so this can be
    // overridden as needed by particular APIs.
    return $this->createResponse($result, $info);
  }
  /**
   * Create response object.
   *
   * Override this if you want to return a different object, or handle errors with exceptions etc.
   *
   * @param mixed $result     The raw result, unless JSON received/required, in which case a decoded object.
   * @param array $curl_info. This will definitely include the http_code key.
   *
   * @returns RestApiResponse
   */
  protected function createResponse($result, $curl_info) {
    // create object
    $response = new RestApiResponse($curl_info['http_code'], $result);
    return $response;
  }

  /**
   * Finally we assemble and send the request.
   */
  protected function requestMock() {
    // Copy the headers array.
    $headers = $this->headers;
    // Create the first line of the request
    $request = $this->method . ' ' . $this->url . " HTTP/1.1\n";

    // mock Curl's headers.
    if ($this->http_basic_auth) {
      $headers['Authorization'] = "Basic $this->http_basic_auth";
    }
    if ($this->ssl_verify_peer) {
    }

    if ($this->ssl_verify_host) {
    }
    if ($this->body) {
      $headers['Content-Length'] = strlen($this->body) ;
    }
    // Add in headers, then body.
    foreach ($headers as $k=>$v) {
      $request .= "$k: $v\n";
    }
    $request .= "\n$this->body";

    return $request;
  }

}
