<?php
namespace ArtfulRobot;

/* @file
 * Simple class provides access to HTTP status code and data received in PHP friendly way.
 *
 */
class RestApiResponse {
  /** HTTP Status code */
  protected $status=200;
  /** Body */
  protected $body;
  /** simple object */
  public function __construct($status=null, $body=null) {
    if ($status !== null) {
      $this->__set('status', $status);
    }
    else {
      // set default status
      $this->status = 200;
    }
    if ($body !== null) {
      $this->__set('body', $body);
    }
    else {
      // set default body as empty StdClass
      $this->body = (object) Array();
    }
  }
  /** Provide access to status and body */
  public function __get($prop) {
    switch ($prop) {
    case 'status':
    case 'body':
      return $this->$prop;
    default:
      throw new \Exception("Attempt to access unknown property '$prop'");
    }
  }
  /** adds some validity checks */
  public function __set($prop, $value) {
    switch ($prop) {
    case 'status':
      if (is_numeric($value) && $value>0 && $value<1000) {
        $this->status = (int) $value;
      }
      break;
    case 'body':
      $this->body = $value;
      break;
    default:
      throw new \Exception("Attempt to set unknown property '$prop'");
    }
  }
  /** Factory method to create from Curl
   *
   * If the data received is json, it is decoded.
   */
  public static function createFromCurl($body, $info) {
    if (empty($info['http_code'])) {
      throw new \Exception("Missing http_code in curl info");
    }
    // create object
    $object = new static($info['http_code']);

    if (!empty($info['content_type'])) {
      if (preg_match('@^application/json@i', $info['content_type'])) {
        $object->body = json_decode($body);
      }
      else {
        $object->body = $body;
      }
    }

    return $object;
  }
}
