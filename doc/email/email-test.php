<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/utils/php/artfulrobot-email.php");

define("EMAIL_TO","hello@artfulrobot.com");
define("EMAIL_FROM","hello@artfulrobot.com");

$rarr = "\xe2\x86\x92";

if(mb_internal_encoding() != "UTF-8") echo "<strong>Warning: mb_internal_encoding is not UTF-8</strong><br/>";
// helpful: http://stackoverflow.com/questions/13416051/utf-8-character-encoding-for-email-headers-with-php#13416202

try {
$email = new ARL_Email(EMAIL_TO, "Test $rarr.");
$email->set_from(EMAIL_FROM);
$email->set_message_text("Hello\n\nThis is a test of encoding. A right-arrow should follow:\n$rarr\n\nAnd pls see message source for headers.\n\nGood bye.\n\n");
$email->set_header("X-ascii-short", "short ascii string");
$email->set_header("X-ascii-long", "0123456789 0123456789 0123456789 0123456789 0123456789 0123456789 0123456789");
$email->set_header("X-utf8", "right arrow $rarr");
$email->send();
echo "<p>Sent header encoding test</p>";

$email = new ARL_Email(EMAIL_TO, 'Plain text test at ' . date('H:i:s'));
$email->set_from(EMAIL_FROM);
$email->set_message_text("Hello\n\nThis is a test plain text email.\n\nGood bye.\n\n");
$email->send();
echo "<p>Sent plain text test</p>";

$email = new ARL_Email(EMAIL_TO, 'HTML test at ' . date('H:i:s'));
$email->set_from(EMAIL_FROM);
$email->set_message_html("<p>Hello</p><p>This is a test <strong style='background-color:yellow;'>html</strong> email.</p><p>Good bye.</p>
		<ul>
			<li>A list has lots of</li>
			<li>Whitespace</li>
		</ul>
		");
$email->send();
echo "<p>Sent html test</p>";

$email = new ARL_Email(EMAIL_TO, 'HTML test embedded image ' . date('H:i:s'));
$email->set_from(EMAIL_FROM);
$email->set_message_html("<p>Hello</p><img src='{img}' style='float:left;' /><p>This is a test <strong style='background-color:yellow;'>html</strong> email.</p><p>Good bye.</p>",
		array( 'img' => dirname(__FILE__) . '/img.jpg'));
$email->send();
echo "<p>Sent html with embedded image test</p>";

$email = new ARL_Email(EMAIL_TO, 'HTML test attached image ' . date('H:i:s'));
$email->set_from(EMAIL_FROM);
$email->set_message_html("<p>Hello</p><p>This is a test <strong style='background-color:yellow;'>html</strong> email with attachment.</p><p>Good bye.</p>");
$email->add_attachment(dirname(__FILE__) . '/img.jpg');
$email->send();
echo "<p>Sent html with attachment test</p>";
} catch (Exception $e) {
	echo "<div style='border-left:solid 10px red;padding-left:10px;background-color:#cce;'>Exception: " . $e->getMessage() . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
	exit;
}


echo "completed " . date('H:i:s');

