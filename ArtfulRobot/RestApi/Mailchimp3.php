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

    $this->server = "https://$datacenter.api.mailchimp.com/3.0";

    // Set auth
    $this->setAuth('dummy-username', $this->api_key);
    $this->setJson(true, true);
    // 2015 Jan: certificates fail to validate.
    $this->setSslVerify(FALSE, 2);
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
   * Subscribe someone.
   *
   * @throws On failure, throws Exception with the title, detail fields from the mailchimp response.
   *
   * @param string $list List ID
   * @param array $member_data Must include email_address. Can include another array under merge_fields
   */
  public function subscribeToList($list, $member_data) {
    if (empty($member_data['email_address'])) {
      throw new \Exception("Missing email_address key in \$member_data parameter.");
    }
    /* Prepare a Mailchimp call like:
     * URL: .../lists/{listid}/members/{subscriber_hash}
     * $data = [
     *   'status' => 'subscribed',
     *   'email_address' => $contact['email'],
     *   'merge_fields' => [
     *     'FNAME' => $contact['first_name'],
     *     'LNAME' => $contact['last_name'],
     *     ],
     *   'interests' => [
     *    {interest_id_hash} => 0 | 1,
     *    ...
     *   ]
     * ];
     */

    $url = "/lists/$list/members/" . $this->emailMd5($member_data['email_address']);
    $params = ['status' => 'subscribed'] + $member_data;
    $response = $this->put($url, $params);
    if ($response->status != 200) {
      if (isset($response->body->title)) {
        $msg = $response->body->title;
        if (isset($response->body->detail)) {
          $msg .= " Detail: " . $response->body->detail;
        }
      }
      else {
        $msg = json_encode($response->body);
      }
      throw new \Exception($msg, $response->status);
    }

    return TRUE;
  }
  /**
   * Helper function to get all interest groups for a list.
   *
   * Returns an array keyed by the interest id (which is needed to PUT changes to a subscriber's interests), values is an array including
   *
   * - category_id
   * - category_title
   * - name (of interest)
   *
   * This is stupidly inefficient thanks to MC's API.
   */
  public function listInterests($list) {
    $interests = [];

    $categories_response = $this->get("lists/$list/interest-categories");
    if ($categories_response->status !== 200) {
      return $interests;
    }
    foreach ($categories_response->body->categories as $category) {
      $response = $this->get("lists/$list/interest-categories/$category->id/interests");
      if ($response->status !== 200) {
        // Strange.
        return $interests;
      }
      foreach ($response->body->interests as $interest) {
        $interests[$interest->id] = [
          'category_id' => $category->id,
          'category_title' => $category->title,
          'name' => $interest->name,
        ];
      }
    }
    return $interests;
  }
  /**
   * Perform a /batches POST request and sit and wait for the result.
   *
   * It quicker to run small ops directly for <15 items.
   *
   * @param array $batch. Example: [
   *    [ 'PUT', '/list/aabbccdd/member/aa112233', ['email_address' => 'foo@example.com', ... ] ],
   *    ...
   * ]
   * @param mixed $method multiple|batch if left NULL, batch is used if there are 15+ requests.
   *
   */
  public function batchAndWait(Array $batch, $method=NULL) {
    // This can take a long time...
    set_time_limit(0);
    if ($method === NULL) {
      // Automatically determine fastest method.
      $method = (count($batch) < 15) ? 'multiple' : 'batch';
    }
    elseif (!in_array($method, ['multiple', 'batch'])) {
      throw new InvalidArgumentException("Method argument must be mulitple|batch|NULL, given '$method'");
    }
    // Validate the batch operations.
    foreach ($batch as $i=>$request) {
      if (count($request)<2) {
        throw new InvalidArgumentException("Batch item $i invalid - at least two values required.");
      }
      if (!preg_match('/^get|post|put|patch|delete$/i', $request[0])) {
        throw new InvalidArgumentException("Batch item $i has invalid method '$request[0]'.");
      }
      if (substr($request[1], 0, 1) != '/') {
        throw new InvalidArgumentException("Batch item $i has invalid path should begin with /. Given '$request[1]'");
      }
    }
    // Choose method and submit.
    if ($method == 'batch') {
      // Submit a batch request and wait for it to complete.
      $batch_result = $this->makeBatchRequest($batch);
      do {
        sleep(3);
        $result = $this->get("/batches/{$batch_result->body->id}");
      } while ($result->body->status != 'finished');
      // Now complete.
      // Note: we have no way to check the errors. Mailchimp make a downloadable
      // .tar.gz file with one file per operation available, however PHP (as of
      // writing) has a bug (I've reported it
      // https://bugs.php.net/bug.php?id=72394) in its PharData class that
      // handles opening of tar files which means there's no way we can access
      // that info. So we have to ignore errors.
      return $result;
    }
    else {
      // Submit the requests one after another.
      foreach ($batch as $item) {
        $method = strtolower($item[0]);
        $path = $item[1];
        $data = isset($item[2]) ? $item[2] : [];
        try {
          $this->$method($path, $data);
        }
        catch (CRM_Mailchimp_RequestErrorException $e) {
          // Here we ignore exceptions from Mailchimp not because we want to,
          // but because we have no way of handling such errors when done for
          // 15+ items in a proper batch, so we don't handle them here either.
        }
      }
    }
  }
  /**
   * Sends a batch request.
   *
   * @param array batch array of arrays which contain three values: the method,
   * the path (e.g. /lists) and the data describing a set of requests.
   */
  public function makeBatchRequest(Array $batch) {
    $ops = [];
    foreach ($batch as $request) {
      $op = ['method' => strtoupper($request[0]), 'path' => $request[1]];
      if (!empty($request[2])) {
        if ($op['method'] == 'GET') {
          $op['params'] = $request[2];
        }
        else {
          $op['body'] = json_encode($request[2]);
        }
      }
      $ops []= $op;
    }
    $result = $this->post('/batches', ['operations' => $ops]);
    return $result;
  }
}
