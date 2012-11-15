<?php
require_once("./artfulrobot-email.php");

$email = new ARL_Email('artfulrobot@gmail.com', 'test at ' . date('H:i:s'));
$email->set_from('hello@artfulrobot.com');
//$email->add_attachment( '/var/www/peopleandplanet.org/format/101010animated.gif');
$email->set_message_html(
		"<p>Hello</p>" .
		"<p>This is an <strong>html</strong> email</p>" 
		);
if (0) {
$email->add_message_html(
		"<p>Hello</p>" .
		"<img src='{test}' />" .
		"<p>This is an <strong>html</strong> email</p>" 
		, array( 'test' => dirname(__FILE__) . '/img.jpg'));
}
$email->send();
echo "completed " . date('H:i:s');

