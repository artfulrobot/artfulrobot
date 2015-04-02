<?php
use \ArtfulRobot;
/**
 * @file
 * Test LinkedIn Rest class.
 */

class LinkedInTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests a GET request using the httpbin service.
     */
    public function testGet()
    {
        $rest = new RestApi_LinkedIn();
        $response = $rest->get('/get', array('foo' => 'bar'));
        $this->assertEquals(200, $response->status);
    }

}

