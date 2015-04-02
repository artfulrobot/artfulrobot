<?php
use \ArtfulRobot\RestApi;
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

class RestApiTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests a GET request using the httpbin service.
     */
    public function testGet()
    {
        $rest = new RestApi('http://httpbin.org');
        $rest->setJson(false, true);
        $response = $rest->get('/get', array('foo' => 'bar'));
        $this->assertEquals(200, $response->status);
        $this->assertObjectHasAttribute('args', $response->body);
        $this->assertInternalType('object', $response->body->args);
        $this->assertEquals('bar', $response->body->args->foo);
    }

    /**
     * Tests a PUT request using the httpbin service.
     */
    public function testPut()
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
    public function testPost()
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
    public function testDelete()
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
    public function testGetWithTemplating()
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

