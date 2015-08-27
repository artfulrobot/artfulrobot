<?php
namespace ArtfulRobot;

/**
 * This class talks to the Mailchimp API using v3
 */
class RestApi_Mailchimp3 extends RestApi {

  /** server to send requests to */
  protected $server = '';

  /** Mailchimp API key */
  protected $api_key = '';

  /** Mailchimp API secret */
  protected $api_secret = '';

  /**
   * Settings must include api_key and optionally api_secret
   */
  public function __construct($settings=array()) {
    // required keys
    foreach (array('api_key') as $_) {
      if (empty($settings[$_])) {
        throw new \Exception(__CLASS__ . " constructor called missing required $_ setting.");
      }
      $this->$_ = $settings[$_];
    }

    // Set URL based on datacentre identifier at end of api key.
    preg_match('/^.*-([^-]+)$/', $this->api_key, $matches);
    if (empty($matches[1])) {
      throw new \Exception("Invalid API key - could not extract datacentre from given API key.");
    }
    $datacenter = $matches[1];

    $this->server = "https://$datacenter.api.mailchimp.com/3.0/";

    // Set auth
    $this->setAuth('dummy-username', $this->api_key);
    $this->setJson(true, true);
  }
  /**
   * Create RestApiResponse from curl result.
   *
   * Overridden parent functionality so we throw exceptions for errors.
   *
   * @returns RestApiResponse
   */
  protected function createResponse($result, $curl_info) {
    $response = parent::createResponse($result, $curl_info);
    switch ($response->status) {
    case 200:
    case 204:
      return $response;
    default:
      // Useful for debugging
      // print "\ndebug\n\tRequest: $this->method $this->url\n\tStatus: $response->status: {$response->body->title}\n\t{$response->body->detail}\n\t{$response->body->type}\n";
      return $response;
    }
  }

  /**
   * Returns the resource id for an email.
   */
  public function emailMd5($email) {
    return md5(strtolower($email));
  }


  // Helper methods.
  /**
   * Subscribe someone. If they are already on the list, send a patch.
   *
   * @param string $list List ID
   * @param array $member_data Must include email_address. Can include another array under merge_fields
   */
  public function subscribeToList($list, $member_data) {
    if (empty($member_data['email_address'])) {
      throw new \Exception("Missing email_address key in \$member_data parameter.");
    }

    // First try to create the member, assuming they would not sign up if they already were.
    $url = "lists/$list/members";
    $params = ['status' => 'subscribed'] + $member_data;
    $response = $this->post($url, $params);
    if ($response->status == 200) {
      // Success.
      return TRUE;

    } elseif ($response->status == 400) {
      // They are already a member. Send Patch.
      unset($params['email_address']);
      $member_url = "$url/" . $this->emailMd5($member_data['email_address']);
      $result = $this->patch($member_url, $params);
      if ($result->status == 200) {
        return TRUE;
      }
    }

    // Oh no.
    return FALSE;
  }
}
