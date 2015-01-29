<?php
namespace ArtfulRobot;

/**
 * This class talks to the Flickr API
 */
class RestApi_Flickr extends RestApi {

  /** server to send requests to */
  protected $server = 'https://api.flickr.com/services/rest';

  /** Flickr API key */
  protected $flickr_api_key = '';

  /** Flickr API secret */
  protected $flickr_api_secret = '';

  /** Flickr methods that require signing */
  protected $flickr_sign = array(
    'flickr.photo.delete',
  );

  public function __construct($settings=array()) {
    $this->flickr_api_key = $settings['api_key'];
    $this->flickr_api_secret = $settings['api_secret'];
  }
  /**
   * Certain flickr API functions require signing.
   *
   * This may alter $this->payload and $this->headers
   */
  protected function buildRequestAlter() {

    // All requests need this
    $this->payload['api_key'] = $this->flickr_api_key;

    if (!empty($this->payload['method']) && in_array($this->payload['method'], $this->flickr_sign)) {
      // we need to sign this.
      $crunch = $this->flickr_api_secret;
      ksort($this->payload);
      foreach ($this->payload as $key=>$val) {
        $crunch .= $key . $val;
      }
      $this->payload['api_sig'] = md5($crunch);
    }
  }
  /** Returns the url to a photo from a photo object.
   *  https://farm{farm-id}.staticflickr.com/{server-id}/{id}_{secret}.jpg
   *    or
   *  https://farm{farm-id}.staticflickr.com/{server-id}/{id}_{secret}_[mstzb].jpg
   *    or
   *  https://farm{farm-id}.staticflickr.com/{server-id}/{id}_{o-secret}_o.(jpg|gif|png)
   *
   * From https://www.flickr.com/services/api/misc.urls.html
   *  s small square 75x75
   *  t thumbnail, 100 on longest side
   *  m small, 240 on longest side
   *  z medium 640, 640 on longest side
   *  b large, 1024 on longest side*
   *
   * Others don't seem supported a lot...
   *  q large square 150x150
   *  n small, 320 on longest side
   *  - medium, 500 on longest side
   *  c medium 800, 800 on longest side†
   *  h large 1600, 1600 on longest side†
   *  k large 2048, 2048 on longest side†
   */
  public static function getPhotoUrl($photo, $size='') {
    if (!$size) {
      $url = "https://farm{$photo->farm}.staticflickr.com/{$photo->server}/{$photo->id}_{$photo->secret}.jpg";
    }
    elseif (strpos('mstzb', $size)!==FALSE) {
      $url = "https://farm{$photo->farm}.staticflickr.com/{$photo->server}/{$photo->id}_{$photo->secret}_$size.jpg";
    }
    elseif ($size=='o') {
      throw new \Exception("original image not programmed");
    }
    else {
      throw new \Exception("Size '$size' not known");
    }
    return $url;
  }
}
