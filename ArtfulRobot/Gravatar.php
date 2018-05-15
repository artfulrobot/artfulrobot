<?php
namespace ArtfulRobot;
/**
 * @file class to Gravatar API
 *
 * @author Rich Lott | Artful Robot
 * @licence GPL3+
 *
 * Synopsis:
 *
 * $g = \ArtfulRobot\Gravatar::factory()
 *  ->setSize(80)
 *  ->setDefault('https://example.com/foo.jpg');
 *
 * $url = $g->getFirstUrl(['email1@example.com', 'email2@example.com']);
 * $image_data = $g->getFirstImage(['email1@example.com', 'email2@example.com']);
 *
 */

class Gravatar {

  /** Size of image to request. */
  protected $size = 80;

  /** Default image. */
  protected $default = '';

  /**
   * Factory to enable fluency.
   */
  public static function factory() {
    $obj = new static();
    return $obj;
  }

  /**
   * Set the size.
   */
  public function setSize($_) {
    $s = (int) $_;
    if ($s < 1 || $s>2048) {
      throw new \Exception("Gravatar size out of range 1-2048");
    }
    $this->size = $s;
    return $this;
  }

  /**
   * Set the default image.
   */
  public function setDefault($_) {
    $this->default = $_;
    return $this;
  }


  /**
   * Returns the first URL.
   */
  public function getFirstUrl($emails) {
    return $this->getFirst($emails)->url;
  }
  /**
   * Returns the first Gravatar as image data.
   */
  public function getFirstImageData($emails) {
    return $this->getFirst($emails)->data;
  }
  /**
   * Returns the first Gravatar as image data.
   *
   * If none found, a zero length string is returned.
   *
   * @return string
   */
  public function getFirstDataUri($emails) {
    $data = $this->getFirst($emails)->data;
    if ($data) {
      return 'data:image/png;base64,' . base64_encode($data);
    }
    else {
      return '';
    }
  }
  /**
   * Returns the first Gravatar found.
   *
   * @param array $emails
   * @return object with properties:
   * - url   URL to the found image, or the default URL
   * - data  Image data found, or NULL
   * - email The email that matched, or NULL.
   */
  public function getFirst($emails) {

    foreach ($emails as $email) {
      $url = 'https://www.gravatar.com/avatar/' . md5(strtolower($email)) . "?s=$this->size";

      $curl = curl_init();
      // Nb. sending a HEAD request causes a hang.
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, TRUE);
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
      curl_setopt($curl, CURLOPT_URL, "$url&d=x");
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      $result = curl_exec($curl);
      $info = curl_getinfo($curl);
      curl_close($curl);

      // Check response.
      if (empty($info['http_code'])) {
        throw new \Exception("http_code missing in curl response info");
      }
      if ($info['http_code'] == 200) {
        // Found one.
        return (object) [
          'url' => $url . (empty($this->default)?'':'&d=' . urlencode($this->default)),
          'data' => $result,
          'email' => $email,
        ];
      }
    }

    // Not found.
    return (object) ['url' => $this->default, 'data' => NULL, 'email' => NULL];
  }
}
