<?php
namespace ArtfulRobot;

require( dirname(dirname(__FILE__)) . '/ArtfulRobot/autoload.php');

function handler1() {
    echo "hello\n";
}

set_exception_handler('\\ArtfulRobot\\handler1');
Debug::setFile('/tmp/debug-test');
Debug::loadProfile('file');
Debug::log("foo");

function thrower()
{
    throw new \Exception("foo!");
}

function der()
{
    trigger_error("bar");
}


thrower();


