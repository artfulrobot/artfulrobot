<?php

use \ArtfulRobot\Email;

class EmailTest extends \PHPUnit_Framework_TestCase {
   const
      EMAIL_WITH_NAME_TO   = '"Foo" <foo@example.com>',
      EMAIL_WITH_NAME_FROM = '"Bar" <bar@example.com>',
      EMAIL                = 'foo@example.com',
      TEST_SUBJECT         = 'Test Subject',
      BODY_TEXT            = "Text body\r\n\r\nGoes here",
      BODY_HTML            = '<p>Body is <strong>HTML</strong>.</p>';

    public function testAttachmentAttached()
    {
        $email = $this->getTestEmail();
        $email->addAttachment(dirname(__FILE__) . '/fixtures/1px.png');
        $mailer_inputs = $email->getMailerInputs();
        $this->assertContains("Content-Type: image/png; charset=binary;\r\n name=\"1px.png\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment;\r\n filename=\"1px.png\"\r\n",
            $mailer_inputs->body, "Unexpected or missing attachment headers.");
    }
    public function testAttachmentMulti()
    {
        $email = $this->getTestEmail();
        $email->addAttachment(dirname(__FILE__) . '/fixtures/1px.png');
        $email->addAttachment(dirname(__FILE__) . '/fixtures/textfile.txt');
        $mailer_inputs = $email->getMailerInputs();
        $this->assertContains("Content-Type: image/png; charset=binary;\r\n name=\"1px.png\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment;\r\n filename=\"1px.png\"\r\n",
            $mailer_inputs->body, "Unexpected or missing 1px.png attachment.");
        $this->assertContains("Content-Type: text/plain; charset=us-ascii;\r\n name=\"textfile.txt\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment;\r\n filename=\"textfile.txt\"\r\n",
            $mailer_inputs->body, "Unexpected or missing textfile.txt attachment.");
    }
    public function testAttachmentRenamedAttached()
    {
        $email = $this->getTestEmail();
        $email->addAttachment(dirname(__FILE__) . '/fixtures/1px.png','foo.png');
        $mailer_inputs = $email->getMailerInputs();
        $this->assertContains("Content-Type: image/png; charset=binary;\r\n name=\"foo.png\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment;\r\n filename=\"foo.png\"\r\n",
            $mailer_inputs->body, "Unexpected or missing attachment headers.");
    }
    public function testAttachmentRenamedUtf8Attached()
    {
        $email = $this->getTestEmail();
        $email->addAttachment(dirname(__FILE__) . '/fixtures/1px.png','f».png');
        $mailer_inputs = $email->getMailerInputs();
        $encoded = '=?UTF-8?B?ZsK7LnBuZw==?=';
        $this->assertContains("Content-Type: image/png; charset=binary;\r\n name=\"$encoded\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment;\r\n filename=\"$encoded\"\r\n",
            $mailer_inputs->body, "Unexpected or missing attachment headers.");
    }
    public function testConstructorSetsFields()
    {
        $email = $this->getTestEmail();
        $to = $email->getTo();
        $this->assertInternalType('array', $to);
        $this->assertContains(static::EMAIL_WITH_NAME_TO, $to);

        $this->assertEquals(static::TEST_SUBJECT, $email->getSubject(), "Subject mismatch");
        $this->assertEquals(static::EMAIL_WITH_NAME_FROM, $email->getFrom(), "From field mismatch");
        $this->assertEquals(static::EMAIL, $email->getReturnPath(), "Return path mismatch");

        $this->assertEquals(static::BODY_TEXT, $email->getMessageText(), "Text body mismatch");
    }
    public function testUtfConversion()
    {
        $email = $this->getTestEmail();
        $email->setMessageText("Latin1 GBP symbol \xa3 should get translated to UTF8");
        $email->setMessageHtml("Latin1 GBP symbol \xa3 should get translated to UTF8");
        $this->assertEquals("Latin1 GBP symbol \xc2\xa3 should get translated to UTF8", $email->getMessageText());
        $this->assertEquals("Latin1 GBP symbol \xc2\xa3 should get translated to UTF8", $email->getMessageHtml());
    }
    public function testSubjectCharacterEncoding()
    {
        $email = $this->getTestEmail();
        $email->setSubject("Latin1 GBP symbol \xa3");
        $mailer_inputs = $email->getMailerInputs();
        $this->assertEquals("Latin1 GBP symbol =?ISO-8859-1?B?ow==?=", $mailer_inputs->subject);
        $email->setSubject("UTF8 GBP symbol \xc2\xa3");
        $mailer_inputs = $email->getMailerInputs();
        $this->assertEquals("UTF8 GBP symbol =?UTF-8?B?wqM=?=", $mailer_inputs->subject);
    }
    public function testSendSimple()
    {
        $email = $this->getTestEmail();
        $this->assertTrue($email->send());
    }
    /**
     * Tests outputs
     */
    public function testOutputToMailer()
    {
        $email = $this->getTestEmail();
        $mailer_inputs = $email->getMailerInputs();
        $this->assertEquals(static::EMAIL_WITH_NAME_TO, $mailer_inputs->to);
        // Check that the From header got set correctly
        $this->assertTrue( strpos($mailer_inputs->mailer_headers, "From: " . static::EMAIL_WITH_NAME_FROM . "\r\n")!==FALSE);
        // Check that the Sender header was coppied from the From one.
        $this->assertTrue( strpos($mailer_inputs->mailer_headers, "Sender: " . static::EMAIL_WITH_NAME_FROM . "\r\n")!==FALSE);
        // Check return path
        $this->assertEquals(static::EMAIL, $mailer_inputs->return_path);
        // Check subject
        $this->assertEquals(static::TEST_SUBJECT, $mailer_inputs->subject);
        // Check that headers handled by the mailer are not also in the headers
        $this->assertEquals(0, preg_match('/^(Subject|To):/m', $mailer_inputs->mailer_headers) );
    }
    public function testSendWithAttachment()
    {
      $email = $this->getTestEmail();
      $email->addAttachment(__FILE__);
      $this->assertTrue($email->send());
    }
    public function testSendWithHtmlEmbeddedAttachment()
    {
      $email = $this->getTestEmail();
      $email->setMessageHtml("<p>You should see this <img src='{image}' alt='test' />.</p>", array( 'image' => __FILE__ ) );
      $this->assertTrue($email->send());
    }
    /**
     * @expectedException \ArtfulRobot\Exception
     */
    public function testAttachNonExistantFile()
    {
      $email = $this->getTestEmail();
      $email->addAttachment('/tmp/lets-guess-this-does-not-exist');
    }
    /** tests that the normal call to setTo() sets a single To address.
     *
     * This would be an expected behaviour, to do otherwise could end up
     * mailing lots of people by accident! #neverHappenedHonest.
     */
    public function testSingleToAddressChange()
    {
        $email = $this->getTestEmail();
        $email->setTo(static::EMAIL);
        $to = $email->getTo();
        $this->assertInternalType('array', $to);
        // check it does NOT contain the original to address
        $this->assertNotContains(static::EMAIL_WITH_NAME_TO, $to);
        // check it does contain the new one.
        $this->assertContains(static::EMAIL, $to);
    }

    public function testMultipleToAddresses()
    {
        $email = $this->getTestEmail();
        $email->setTo(static::EMAIL, true);
        $to = $email->getTo();
        $this->assertInternalType('array', $to);
        $this->assertContains(static::EMAIL, $to);
        $this->assertContains(static::EMAIL_WITH_NAME_TO, $to);
    }

    /**
     * All line endings must be translated to \r\n
     */
    public function testLineEndingsConversion()
    {
        $email = $this->getTestEmail();
        $result = $email->rfc822LineEndings("Proper\r\nFollowed by Mac\rFollowed by *nix\nFollowed by something odd\n\rAdjacent1\r\n\nAdjacent 2\r\n\rAdjacent 3\r\r\nAdjacent 4\n\r\nMultiple 1\r\n\r\nMultiple 2\r\rMultiple 3\n\n" );
        $this->assertEquals("Proper\r\nFollowed by Mac\r\nFollowed by *nix\r\nFollowed by something odd\r\n\r\nAdjacent1\r\n\r\nAdjacent 2\r\n\r\nAdjacent 3\r\n\r\nAdjacent 4\r\n\r\nMultiple 1\r\n\r\nMultiple 2\r\n\r\nMultiple 3\r\n\r\n", $result );
    }

    /**
     * All line endings must be translated to \r\n
     */
    public function testLineEndingsConversionInTextBody()
    {
        $email = $this->getTestEmail();
        $email->setMessageText("Hello\n\nHow are you?");
        $body = $email->getMessageText();
        $this->assertEquals( "Hello\r\n\r\nHow are you?", $body);
    }
    /**
     * All line endings must be translated to \r\n
     */
    public function testLineEndingsConversionInHtmlBody()
    {
        $email = $this->getTestEmail();
        $email->setMessageHtml("<p>Hello\n\nHow are you?</p>");
        $body = $email->getMessageHtml();
        $this->assertEquals( "<p>Hello\r\n\r\nHow are you?</p>", $body);
    }

    public function testWordWrapText()
    {
      $email = $this->getTestEmail();
      $email->setMessageText(str_repeat("1234 ", 17));

      // input is not wrapped until the message is prepared for sending.
      // so we can't just test getMessageText()
      $mailer_inputs = $email->getMailerInputs();
      foreach (explode("\r\n", $mailer_inputs->body) as $_) {
        $this->assertLessThan(79, strlen($_), "This body line is too long: $_");
      }
    }
    public function testWordWrapHtml()
    {
      $email = $this->getTestEmail();
      $email->setMessageHtml(str_repeat("<p>1234 </p>", 17));

      // input is not wrapped until the message is prepared for sending.
      // so we can't just test getMessageText()
      $mailer_inputs = $email->getMailerInputs();
      foreach (explode("\r\n", $mailer_inputs->body) as $_) {
        $this->assertLessThan(79, strlen($_), "This body line is too long: $_");
      }
    }
    public function testTextVersionCreatedFromHtml()
    {
      $email = $this->getTestEmail();
      $email->setMessageHtml(static::BODY_HTML);
      // text version should only be created when it doesn't exist.
      $email->setMessageText('');
      $mailer_inputs = $email->getMailerInputs();
      $this->assertRegExp('/Body is HTML./', $mailer_inputs->body, "Body does not appear to contain text version of html");
    }
    public function testHtmlVersionCreatedFromText()
    {
      $email = $this->getTestEmail();
      $mailer_inputs = $email->getMailerInputs();
      $this->assertRegExp('@Text body<br />\r\n<br />@', $mailer_inputs->body, "Body does not appear to contain text version of html");
    }
    public function testSeparateTextAndHtmlVersions()
    {
      $email = $this->getTestEmail();
      $email->setMessageHtml(static::BODY_HTML);
      $mailer_inputs = $email->getMailerInputs();
      $this->assertRegExp('/' . preg_quote(static::BODY_TEXT,'/') . '/', $mailer_inputs->body, "text version of body missing");
      $this->assertRegExp('/' . preg_quote(static::BODY_HTML,'/') . '/', $mailer_inputs->body, "html version of body missing");
    }
    /**
     * returns a new Email object populated with test strings.
     */
    protected function getTestEmail($body = null) {
        if ($body === null) {
          $body = static::BODY_TEXT;
        }
        $email = new Email(static::EMAIL_WITH_NAME_TO, static::TEST_SUBJECT, $body, static::EMAIL_WITH_NAME_FROM, static::EMAIL);
        // mock PHP's mail functionality so we can't actually send anything.
        $email->setMailService(function() { return true;});
        return $email;
    }

}

// vim: sw=4 ts=4
