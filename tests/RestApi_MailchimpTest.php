<?php
use \ArtfulRobot\RestApi_Mailchimp3;

// this file must set the global var $mailchimp_api_key;
require_once dirname(__FILE__) . '/fixtures/mailchimp-credentials.php';
/**
 * @file
 * This set of test checks the Get/Put/Post/Delete functions of the RestApi class.
 *
 * It does this using the httpbin.org service which echoes back (in JSON)
 * whatever you send to it.
 *
 * Each test fires {foo:'bar'} and checks that this is received in
 * the response.
 */

class RestApi_Mailchimp3Test extends \PHPUnit_Framework_TestCase
{
    protected $mailchimp_config;

    /** URL to test list, set in testGetTestList() */
    protected $url_list;

    /** URL to test member in test list, set in testGetTestList() */
    protected $url_member;


    public function setup()
    {
        global $mailchimp_config;
        $this->mailchimp_config = $mailchimp_config;

        $this->url_list = "lists/{$this->mailchimp_config['test_list_id']}";
        $this->url_member = "$this->url_list/members/"
            . $this->getApi()->emailMd5($this->mailchimp_config['test_email']);
    }
    /**
     * Returns a RestApi_Mailchimp3 object.
     */
    protected function getApi()
    {
        global $mailchimp_config;
        $api = new RestApi_Mailchimp3($mailchimp_config);
        return $api;
    }
    /**
     * Test that our test list exists and that our test subscriber is not in it.
     */
    public function testGetTestList()
    {
        $api = $this->getApi();
        $result = $api->get($this->url_list);
        $this->assertEquals(200, $result->status);
        $this->assertDeleteSucceeds([204,404]);
    }
    /**
     * Make a delete call, check that the status code returned is one of the given ones.
     *
     * Typically 204, 404
     *
     */
    protected function assertDeleteSucceeds($expected_status_codes)
    {
        // Ensure our test address is not in the test list.
        // 404 (not found) and 204 (no content) is fine.
        $api = $this->getApi();
        $result = $api->delete($this->url_member);
        $this->assertContains($result->status, $expected_status_codes);
    }

    /**
     * Test that we can subscribe (and delete) someone from  a list.
     * @depends testGetTestList
     */
    public function testSubscribe()
    {
        $api = $this->getApi();

        // Subscribe test address
        $params = ['email_address' => $this->mailchimp_config['test_email'], 'status' => 'subscribed', 'merge_fields' => [ 'FNAME' => 'Foo', 'LNAME' => 'Bar' ]];
        $result = $api->post($this->url_list . "/members", $params);
        $this->assertEquals(200, $result->status);

    }

    /**
     * Test that we can unsubscribe someone.
     * @depends testSubscribe
     *
     */
    public function testUnsubcribe()
    {
        $api = $this->getApi();

        // Unsubscribe test address
        $params = ['status' => 'unsubscribed'];
        $result = $api->patch($this->url_member, $params);
        $this->assertEquals(200, $result->status);

        // finally, delete the test member.
        $this->assertDeleteSucceeds([204]);
    }

    /**
     * Tests a PUT request using the httpbin service.
     */
    public function xtestPut()
    {
        $rest = new RestApi('http://httpbin.org');
        $rest->setJson();
        $response = $rest->put('/put', array('foo' => 'bar'));
        $this->assertEquals(200, $response->status);
        $this->assertObjectHasAttribute('args', $response->body);
        $this->assertInternalType('object', $response->body->json);
        $this->assertEquals('bar', $response->body->json->foo);
    }

    /**
     * Tests a POST request using the httpbin service.
     */
    public function xtestPost()
    {
        $rest = new RestApi('http://httpbin.org');
        $rest->setJson();
        $response = $rest->post('/post', array('foo' => 'bar'));
        $this->assertEquals(200, $response->status);
        $this->assertObjectHasAttribute('args', $response->body);
        $this->assertInternalType('object', $response->body->json);
        $this->assertEquals('bar', $response->body->json->foo);
    }

    /**
     * Tests a DELETE request using the httpbin service.
     */
    public function xtestDelete()
    {
        $rest = new RestApi('http://httpbin.org');
        $rest->setJson();
        $response = $rest->delete('/delete', array('foo' => 'bar'));
        $this->assertEquals(200, $response->status);
        $this->assertObjectHasAttribute('args', $response->body);
        $this->assertInternalType('object', $response->body->json);
        $this->assertEquals('bar', $response->body->json->foo);
    }

    /**
     * Tests a GET request using the httpbin service.
     */
    public function xtestGetWithTemplating()
    {
        $rest = new RestApi('http://httpbin.org');
        $rest->setJson(false, true);
        $response = $rest->get(array('/%something', array('%something' => 'get')), array('foo' => 'bar'));
        $this->assertEquals(200, $response->status);
        $this->assertObjectHasAttribute('args', $response->body);
        $this->assertInternalType('object', $response->body->args);
        $this->assertEquals('bar', $response->body->args->foo);
    }
}

