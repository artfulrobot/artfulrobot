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
   * @param bool $set_pending_on_fail. This handles the case that you try to
   * subscribe someone who previously unsubscribed. Mailchimp won't let you do
   * that. The default, FALSE, means that an exception is thrown if you try to do this
   * Alternatively, TRUE means that if this happens it will try again to update
   * mailchimp by submitting the same changes and setting the status to 'pending', which
   * will generate an email sent from Mailchimp directly to the person that they have to
   * opt-into before they're actually subscribed.
   */
  public function subscribeToList($list, $member_data, $set_pending_on_fail=FALSE) {
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
    if ($response->status == 200) {
      // Great, job done.
      return TRUE;
    }

    // Collect error message title, if poss.
    $msg = $response->body->title ?? json_encode($response->body);

    if ($msg == 'Member In Compliance State' && $set_pending_on_fail) {
      // They've previously unsubscribed.
      $params['status'] = 'pending';
      $response = $this->patch($url, $params);

      if ($response->status == 200) {
        // OK, we're done.
        return TRUE;
      }
      // That failed, too.

      // Pick up the latest error.
      $msg .= " **and after trying to PATCH to 'pending' status**: " . ($response->body->title ?? json_encode($response->body));
    }

    // Append all details we can to the message.
    if (isset($response->body->detail)) {
      $msg .= " Detail: " . $response->body->detail;
    }
    throw new \Exception($msg, $response->status);
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
   * @param callback|NULL $progress
   *
   */
  public function batchAndWait(Array $batch, $method=NULL, $progress=NULL) {
    // This can take a long time...
    set_time_limit(0);

    $c = count($batch);
    if ($method === NULL) {
      // Automatically determine fastest method.
      $method = ($c < 15) ? 'multiple' : 'batch';
    }
    elseif (!in_array($method, ['multiple', 'batch'])) {
      throw new InvalidArgumentException("Method argument must be mulitple|batch|NULL, given '$method'");
    }

    if (!is_callable($progress)) {
      // NOOP function so we can call progress.
      $progress = function ($_) {};
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

      $progress("Submitting batch of $c changes.");
      $batch_result = $this->makeBatchRequest($batch);
      $finished = FALSE;
      do {
        sleep(5);
        $result = $this->get("/batches/{$batch_result->body->id}");

        $finished = $result->body->status == 'finished';
        $progress(($result->body->finished_operations ?? '?') . "/$c changes complete.");

      } while (!$finished);
      $progress("$c changes complete.");
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
      $done = 0;
      foreach ($batch as $item) {
        $done++;
        $progress("Submitting $done / $c changes.");
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
  /**
   * Update mailchimp records for given list.
   *
   * - downloads members 1000 at a time
   * - passes each member into an update callback
   * - if callback returns updates, store it
   * - if any updates create a batch request with them.
   *
   * @param string $list_id
   * @param callback $callback
   * @param array|NULL $options. Optional options array. The following are defaults:
   * - batch:       2000 - how many to load at once?
   * - status:      ['subscribed', 'pending'] - filters members to map
   * - wait:        FALSE  use batchAndWait or just use makeBatchRequest?
   * - progress:    NULL|callback called with progress of batch operations (unimplemented idea)
   * - test_batch:  FALSE limit processing to one batch for testing.
   * - offset:      optionally start mid-way through.
   * - fields:      optional 'A comma-separated list of fields to return.
   *                Reference parameters of sub-objects with dot notation.' useful to
   *                fetch only what you want from the API which may be faster.
   *                e.g. 'total_items,members.email_address,members.status,members.merge_fields'
   *                is about 7x faster than not specifying it.
   *                MUST CONTAIN total_items,members.email_address !!
   */
  public function mapListMembers($list_id, $callback, $options =  NULL) {
    if ($options === NULL) {
      $options = [];
    }
    $options += [
      'batch'      => 2000,
      'status'     => 'subscribed,pending',
      'wait'       => FALSE,
      'progress'   => NULL,
      'test_batch' => FALSE,
      'offset'     => 0,
      'fields'     => NULL,
    ];
    $progress = is_callable($options['progress']) ? $options['progress'] : function ($_) {};
    if ($options['limit']) {
      $progress("Using limit: will only process $options[batch] records.");
    }
    if ($options['fields']) {
      // Validate fields.
      $_ = explode(',' , $options['fields']);
      if (!(in_array('total_items', $_) && in_array('members.email_address', $_))) {
        throw new \InvalidArgumentException("fields option to mapListMembers MUST contain total_items and members.email_address");
      }
    }

    $done = 0;
    $offset = (int) $options['offset'];
    $all_updates = [];
    $progress("Processing list: $list_id (statuses: $options[status])");
    $total = 'unknown';

    do {
      $progress("Processed $offset/$total members; " . count($all_updates) . " updates pending; loading next $options[batch]...");
      $get_params = ['offset' => $offset, 'count' => $options['batch'], 'status' => $options['status']];
      if ($options['fields']) {
        // Limit fields we fetch, if option given.
        $get_params['fields'] = $options['fields'];
      }
      $members_batch = $this->get("/lists/$list_id/members", $get_params)->body;
      $total = $members_batch->total_items;
      $offset += count($members_batch->members);
      $progress("Loaded    $offset/$total members; " . count($all_updates) . " updates pending; processing last batch...");
      foreach ($members_batch->members as $member) {
        $_ = $callback($member);
        if ($options['test_batch']) {
          $progress("Test batch: processing $member->email_address resulted in update: " . json_encode($_, JSON_PRETTY_PRINT));
        }
        if ($_) {
          $all_updates[] = $_;
        }
      }
    } while ((!$options['test_batch']) && ($offset < $members_batch->total_items));

    $result = NULL;
    if ($all_updates) {
      if ($options['wait']) {
        $progress("Processed all $offset members; submitting and waiting on " . count($all_updates) . " updates...");
        $this->batchAndWait($all_updates);
        $progress("Processed all $offset members and completed " . count($all_updates) . " updates");
      }
      else {
        $progress("Processed all $offset members; submitting " . count($all_updates) . " updates...");
        $result = $this->makeBatchRequest($all_updates);
        if (!empty($result->body->_links)) {
          unset($result->body->_links);
        }
        $progress("Processed all $offset members; submitted " . count($all_updates) . " updates.");
      }
    }
    else {
      $progress("Processed all $offset members; nothing to update.");
    }

    return $result;
  }
  public function batchStats() {
    $todo = 0;
    $doing = 0;
    $doing_done = 0;
    $done = 0;
    $result = $this->get('/batches')->body;
    $batches = [];
    foreach ($result->batches as $batch) {
      $batches[$batch->id] = ['status' => $batch->status, 'total_operations' => $batch->total_operations, 'finished_operations' => $batch->finished_operations,
        'submitted_at' => $batch->submitted_at, 'completed_at' => $batch->completed_at];

      if ($batch->status != 'finished') {
        $doing += (int) $batch->total_operations;
        $doing_done += (int) $batch->finished_operations;
      }
      $todo += (int) $batch->total_operations;
      $done += (int) $batch->finished_operations;
    }
    return [
      'doing' => $doing,
      'doing_done' => $doing_done,
      'done' => $done,
      'todo' => $todo,
      'percent_done' => number_format($doing_done*100/$doing, 1) . '%',
      'batches' => $batches,
    ];
  }
}
