<?php
namespace ArtfulRobot;

/** Creates UTF-8 html emails with attachments and embedded images
 *
 * Example: simple text email
 *
 * $to = 'foo@example.com';
 * $subject = 'test';
 * $from = '"Your mate" <me@example.com>';
 * $message = 'blah...';
 * $return_path = 'blah...';
 *
 * $email = new \ArtfulRobot\Email($to, $subject, $message, $from, $return_path);
 * $email->send();
 *
 *
 *
 */
class Email
{
    protected $headers = array();
    protected $subject;
    protected $to;
    protected $reply_to;
    protected $from;
    protected $return_path;
    protected $body = array();
    protected $attachments = array();
    protected $uid;
    /**
     * holds value used in first param to mailer, typically PHP's mail()
     */
    protected $mailer_to;
    /**
     * holds value used in second param to mailer, typically PHP's mail()
     */
    protected $mailer_subject;
    /**
     * holds value used in third param to mailer, typically PHP's mail()
     */
    protected $mailer_body;
    /**
     * holds value used in fourth param to mailer, typically PHP's mail()
     */
    protected $mailer_headers;
    /**
     * holds value used in fifth param to mailer, typically PHP's mail().
     *
     * This string will be prefixed "-f " and can be used to set return path
     * on compatible systems.
     */
    protected $mailer_return_path;
    /**
     * Callback for actually sending the assembled email.
     */
    protected $mail_service = 'mail';

    /**
     * Constructor with lots of optional shortcut params.
     *
     */
    function __construct($to=null, $subject=null, $message=null, $from=null, $return_path=null)
    {
        // init blank body parts
        $this->body = array('text'=>'','html'=>'');
        // create hash for boundaries
        $this->uid = md5(serialize($this->body) . time());
        // set up default headers
        $this->headers['MIME-Version']='1.0';
        $this->headers['Content-Type']= "multipart/mixed; boundary=\"ARE-mixed-$this->uid\"";

        if ($to!==null) {
            $this->setTo($to);
        }
        if ($subject!==null) {
            $this->setSubject($subject);
        }

        // set message checking for opening tag to determine if it's html.
        // if you want to send a text only message using < then don't pass this parameter
        // and use setMessageText() directly
        if ($message!==null) {
            if (strpos($message,'<')!==false) {
                $this->setMessageHtml($message);
            } else {
                $this->setMessageText($message);
            }
        }

        if ($from!==null) {
            $this->setFrom($from);
        }
        if ($return_path!==null) {
            $this->setReturnPath($return_path);
        }
        return $this;
    }
    /**
     * return 'to' address that has been set
     */
    public function getTo()
    {
        return $this->to;
    }
    /**
     * return 'from' address that has been set
     */
    public function getFrom()
    {
        return $this->from;
    }
    /**
     * return 'return_path' address that has been set
     */
    public function getReturnPath()
    {
        return $this->return_path;
    }
    /**
     * return 'reply_to' address that has been set
     */
    public function getReplyTo()
    {
        return $this->reply_to;
    }
    /**
     * return subject that has been set
     */
    public function getSubject()
    {
        return $this->subject;
    }
    /**
     * return text version of message.
     */
    public function getMessageText()
    {
        return empty($this->body['text']) ? null : $this->body['text'];
    }
    /**
     * return html body.
     */
    public function getMessageHtml()
    {
        return empty($this->body['html']) ? null : $this->body['html'];
    }
    /**
     * append 'to' address
     */
    public function setTo($to, $append=false)
    {
        if ($append) {
            $this->to[] = $to;
        } else {
            $this->to = array($to);
        }
    }
    /**
     * set from address
     */
    public function setFrom($from)
    {
        $this->from = $from;
    }
    /**
     * set return path
     */
    public function setReturnPath($return_path)
    {
        $this->return_path = $return_path;
    }
    /**
     * set Reply-To field
     */
    public function setReplyTo($from)
    {
        $this->reply_to = $reply_to;
    }
    /**
     * set subject
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
    }
    /**
     * Set an arbitrary header.
     *
     * Special headers are handled by their setter; some are disallowed.
     *
     */
    public function setHeader($header, $value)
    {
        switch ($header) {
        case 'Content-Type':
        case 'MIME-Version':
            // don't allow things we need to control.
            throw new \Exception("Setting $header directly is not allowed");
        case 'From':
            $this->setFrom($value);
            break;
        case 'To':
            $this->setTo($value);
            break;
        case 'ReturnPath':
            $this->setReturnPath($value);
            break;
        case 'ReplyTo':
            $this->setReplyTo($value);
            break;
        case 'Subject':
            $this->setSubject($value);
            break;
        default:
            $this->headers[$header] = $value;
        }
    }
    /**
     * Normally a UID is generated, but for testing
     * it's useful to be able to specify this.
     *
     */
    public function setUidSeed($seed)
    {
        $this->uid = md5($seed);
    }
    /**
     * Allow dependency injection to replace PHP's mail() function used to send mail.
     */
    public function setMailService($callback) {
        $this->mail_service = $callback;
    }
    /**
     * adds text message
     */
    public function setMessageText($message)
    {
        $message = $this->rfc822LineEndings($message);
        // let's always send utf8 and be done with it...
        $message = mb_convert_encoding($message, 'UTF-8', static::mbDetectEncoding($message));
        $this->body['text'] = $message;
    }
    /**
     * adds html message
     *
     * the related_files is an array(
     *    "name" => filename
     *    )
     *
     * and the html message must include references like {name}
     *
     */
    public function setMessageHtml($message, $related_files = null)
    {
        $message = $this->rfc822LineEndings($message);
        // let's always send utf8 and be done with it...
        $message = mb_convert_encoding($message, 'UTF-8', static::mbDetectEncoding($message));

        $this->body['html'] = $message;
        $this->body['related'] = $related_files;
    }
    /**
     * Add attachment
     *
     * Optionally pass a different filename - so you can rename the file
     * as seen by the email client.
     */
    public function addAttachment($filename, $rename=null)
    {
        $filename = realpath($filename);
        if (!file_exists($filename)) {
            throw new Exception("File $filename not found.");
        }
        if (!is_readable($filename)) {
            throw new Exception("File $filename not readable.");
        }
        if (isset($this->attachments[$filename])) {
            return;
        }
        $this->attachments[$filename] = $rename;
    }
    /**
     * Ensure line endings conform to RFC 822.
     */
    public function rfc822LineEndings($string)
    {
        // Although this regexp replaces \r\n with ... \r\n
        // this is more efficient than trying to identify \r and \n on their own.
        return preg_replace('@(\r\n|\n|\r)@', "\r\n", $string);
    }
    /**
     * create and send email
     *
     * Assemble the inputs to the mail service (typically PHP's mail() function).
     */
    public function send()
    {
        $this->assembleMailerInputs();

        $mail_service = $this->mail_service;

        if (isset($this->mailer_return_path)) {
            // Great, we can send the mail with a return path.
            $mail_result = $mail_service(
                $this->mailer_to,
                $this->mailer_subject,
                $this->mailer_body,
                $this->mailer_headers,
                // Pass the Return-Path via sendmail's -f command.
                '-f ' . $this->mailer_return_path
            );

        } else {
            // The optional $additional_parameters argument to mail() is not allowed
            // if safe_mode is enabled. Passing any value throws a PHP warning and
            // makes mail() return FALSE.
            $mail_result = $mail_service(
                $this->mailer_to,
                $this->mailer_subject,
                $this->mailer_body,
                $this->mailer_headers
            );
        }
        return $mail_result;
    }

    /**
     * getMailerInputs
     *
     * Assemble and return the inputs in a StdClass object - so they can be tested.
     */
    public function getMailerInputs()
    {
        $this->assembleMailerInputs();
        return (object) array(
            'return_path' => $this->mailer_return_path,
            'to' => $this->mailer_to,
            'mailer_headers' => $this->mailer_headers,
            'subject' => $this->mailer_subject,
            'body' => $this->mailer_body,
        );
    }
    /**
     * Assemble the inputs to the mail service (typically PHP's mail() function).
     *
     * Thanks to Drupal for some of this code
     */
    protected function assembleMailerInputs()
    {
        // Reset mailer params.
        $this->mailer_to = $this->mailer_subject = $this->mailer_body = $this->mailer_return_path = '';
        $this->mailer_headers = array();

        // take a copy of input headers - we mangle this.
        $headers = $this->headers;

        // compile To: header. This is kept separate because it's a separate field for the mailer.
        unset($headers['To']);
        $this->mailer_to = implode(', ', $this->to);

        // Set a From: header and Sender: if we have that.
        if ($this->from) {
            $headers['From'] = $this->from;
            // use the from address for Sender, too, unless that's set separately.
            if (empty($headers['Sender'])) {
                $headers['Sender']= $this->from;
            }
        }

        // Set Reply-To: header
        if ($this->reply_to) {
            $headers["Reply-To"] = $this->reply_to;
        }

        // Return Path
        // if set with setReturnPath, this is used.
        // otherwise, if set in headers as Return-Path, this is used.
        $this->mailer_return_path = (!empty($this->return_path)
            ? $this->return_path
            :   (!empty($headers['Return-Path'])
                ? $headers['Return-Path']
                : null)
        );
        // best to pass the return path in 5th arg of mail()
        // can't though if PHP in safe mode, or the -f flag is
        // hard-coded in the php.ini.
        if ($this->mailer_return_path) {
            if (ini_get('safe_mode')
                || strpos(ini_get('sendmail_path'), ' -f')!==false
            ) {
                // can't use 5th param, put in headers (incase that works?!)
                $headers['Return-Path'] = $this->mailer_return_path;
                $this->mailer_return_path = '';
            }
            else {
                // Remove it from headers so we can supply it as a sendmail -f option.
                unset($headers['Return-Path']);
            }
        }

        // Set Subject: header
        // Originally this code sought to use Drupal's mimeHeaderEncode if available but it turns
        // out to have inconsistent behaviour see http://api.drupal.org/api/drupal/includes%21unicode.inc/function/mimeHeaderEncode/7#comment-44358
        $this->mailer_subject = self::mimeHeaderEncode($this->subject);

        // Compile headers
        $this->mailer_headers = '';
        foreach ($headers as $k=>$v) {
            $this->mailer_headers .= self::mimeHeaderEncode("$k: $v") . "\r\n";
        }

        // Compile Body.
        $this->assembleBody();
    }

    /** create body */
    protected function assembleBody()
    {
        // if html email, make sure text is there as alternative.
        if ($this->body['html'] && ! $this->body['text']) {
            $this->createTextFromHtml();
        } elseif ($this->body['text'] && ! $this->body['html']) {
            $this->createHtmlFromText();
        }

        // email should be 70 characters max
        $body_html = wordwrap($this->body['html'], 70, "\r\n");
        $body_text = wordwrap($this->body['text'], 70, "\r\n");

        $uid =$this->uid;
        $body =
            "--ARE-mixed-$uid\r\n"
            ."Content-Type: multipart/alternative;\r\n\tboundary=\"ARE-alt-$uid\"\r\n"
            ."\r\n"
            ."--ARE-alt-$uid\r\n"
            ."Content-Type: text/plain; charset=\"UTF-8\"\r\n"
            ."Content-Transfer-Encoding: 8bit\r\n\r\n"
            .$body_text
            ."\r\n";

        // html
        if (empty($this->body['related'])) {
            $body .=
                "--ARE-alt-$uid\r\n"
                ."Content-Type: text/html; charset=\"UTF-8\"\r\n"
                ."Content-Transfer-Encoding: 8bit\r\n\r\n"
                .$body_html
                ."\r\n";

        } else {
            $body .=
                "\r\n--ARE-alt-$uid\r\n"
                ."Content-Type: multipart/related; boundary=\"ARE-rel-$uid\"\r\n"
                ."\r\n"
                ."--ARE-rel-$uid\r\n"
                ."Content-Type: text/html; charset=\"UTF-8\"\r\n"
                ."Content-Transfer-Encoding: 8bit\r\n"
                ."\r\n";
            $html = $body_html;
            foreach ($this->body['related'] as $name => $filename) {
                $subs['{' . $name . '}'] = 'cid:ARE-CID-'.$name;
            }
            $body .= strtr($html, $subs)
                ."\r\n";

            foreach ($this->body['related'] as $name => $filename) {
                $body .= "--ARE-rel-$uid\r\n"
                        . $this->attach($filename, $name)
                        . "\r\n";
            }

            $body .= "--ARE-rel-$uid--\r\n"
                    . "\r\n";

        }
        $body .= "--ARE-alt-$uid--\r\n"
                ."\r\n";

        if ($this->attachments) {
            //read the atachment file contents into a string,
            //encode it with MIME base64,
            //and split it into smaller chunks
            foreach ($this->attachments as $filename=>$rename) {
                $body .= "--ARE-mixed-$uid\r\n"
                    . $this->attach($filename, null, $rename);
            }
        }
        $body .= "--ARE-mixed-$uid--\r\n";

        $this->mailer_body = $body;
    }
    /**
     * internal function to create attachment
     */
    protected function attach($filename, $cid=null, $rename=null)
    {
        if (!file_exists($filename)) {
            throw new Exception("File $filename not found.");
        }
        $mime = trim(shell_exec("file -bi " . escapeshellarg( $filename )));
        $file = basename($filename);
        if ($rename === null) {
            $rename = $file;
        }
        
        mb_internal_encoding( "UTF-8");
        $rename = mb_encode_mimeheader($rename);
        $attachment =
            "Content-Type: $mime"
            .( $cid ? '' : ";\r\n name=\"$rename\"" )
            . "\r\n"
            ."Content-Transfer-Encoding: base64\r\n"
            .( $cid ? "Content-ID: <ARE-CID-$cid>\r\n" 
            : "Content-Disposition: attachment;\r\n"
            ." filename=\"$rename\"\r\n")
            ."\r\n"
            .chunk_split(base64_encode(file_get_contents($filename)))
            ."\r\n";
        return $attachment;
    }
    protected function createTextFromHtml()
    {
        $this->body['text'] = $this->rfc822LineEndings( strip_tags(
            // convert brs to single \n
            preg_replace('@<br( */)?' . '>@i',"\n",
            // convert end of block-level things to \n\n
            preg_replace('@</(p|div|li|ul|h[12345])>@i',"$0\n\n",
            // remove whitespace at start of lines before tag
            preg_replace('@^\s+<@m','<', $this->body['html'])
        ))));
    }
    protected function createHtmlFromText()
    {
        // add <br/> at line endings
        $html = str_replace("\r\n", "<br />\r\n", $this->body['text']);

        // Make links into links.
        $html = preg_replace('@
                (https?://)   # $1
                (             # $2
                    (?:\w+?\.)+
                    (?:[a-z]{2,5})
                    (?:/[^?< \t\n]*)?
                )
                ([?]\S+)?     # $3
                @x',"<a href='$1$2$3'\r\n >$2</a>",
            $html);

        $this->body['html'] = $html;
    }

    static public function mbDetectEncoding( $string, $detect_order=null )
    {
        /** php's native mbDetectEncoding is riddled with bugs.
         * see the comments for the online documentation for proof
         *
         * one bug in mbDetectEncoding reported in 2005 
         * http://uk2.php.net/manual/en/function.mb-detect-encoding.php#55228
         * and still present in 2009:
         * example, e-acute (byte value 233 in Latin1) mbDetectEncoding will tell you
         * it's UTF-8. The work around is to append an ASCII character at the end of the string.
         * then it works properly. 
         *
         * mbDetectEncoding also detects a string
         * with (e.g.) 149 in as Latin1 when Latin1 is undefined for 128-159.
         * (could be cp1252)
         * 

         * confusion arrises in definitions of detection
         * Q1: is this string a valid encoding-X string?
         *		Note: most UTF-8 byte sequences validate against ISO-8859-1 (Latin1)
         * 		because that charset only excludes the range 128-159
         * Q2: what's the minimal charset we can represent the string in?

         * Q2 is because UTF8 encumbers various processes, 
         * so if we can avoid this overhead/complication
         * we usually want to.
         *

         * The w3 regexp fails (causes php a segfault) on strings >~3.5kb !
         * 

         Unit Test
         $a="just plain old text." ; 
        $encoding = mb_detect_encrl($a);
        echo "ASCII test : " .  ( ( $encoding == 'ASCII' ) ? 'pass' : 'FAIL:' . $encoding) . "<br />";
        $a="caf" . chr(233); 
        $encoding = mb_detect_encrl($a);
        echo "ISO-8859-1 test : " .  ( ( $encoding == 'ISO-8859-1' ) ? 'pass' : 'FAIL: '.$encoding ) . "<br />";
        $a="caf" . chr(195) . chr(169);
        $encoding = mb_detect_encrl($a);
        echo "UTF-8 test : " .  ( ( $encoding == 'UTF-8' ) ? 'pass' : 'FAIL:' . $encoding) . "<br />";
        $a="bullet" . chr(149); // outside of Latin1
        $encoding = mb_detect_encrl($a);
        echo "Windows-1252 test : " .  ( ( $encoding == 'WINDOWS-1252' ) ? 'pass' : 'FAIL:' . $encoding) . "<br />";

        $a="caf" . chr(195) . chr(169) . chr(169); //invalid utf8 \xC3\xA9\xA9 should show up as ISO-8859-1
        $encoding = mb_detect_encrl($a);
        echo "binary/invalid UTF8 test : detected as $encoding. " .  ( ( $encoding == 'ISO-8859-1' ) ? 'pass' : 'FAIL:' . $encoding) . "<br />";


         */

        // coding check
        if ($detect_order!==null) throw new Exception("mbDetectEncoding does not take detect_order, unlike php's native function.");
        // first, is it ASCII?
        if (mb_check_encoding( $string, 'ASCII' )) return 'ASCII';

        // now, is it valid utf8 and does it need to be?
        if (
            //first, does it need to be UTF-8? (this is the faster of the 2 regexps so we do it first)
            //source: http://uk2.php.net/manual/en/function.mb-detect-encoding.php#68607
            preg_match("%(?:
            [\xC2-\xDF][\x80-\xBF]        # non-overlong 2-byte
            |\xE0[\xA0-\xBF][\x80-\xBF]               # excluding overlongs
            |[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}      # straight 3-byte
            |\xED[\x80-\x9F][\x80-\xBF]               # excluding surrogates
            |\xF0[\x90-\xBF][\x80-\xBF]{2}    # planes 1-3
            |[\xF1-\xF3][\x80-\xBF]{3}                  # planes 4-15
            |\xF4[\x80-\x8F][\x80-\xBF]{2}    # plane 16
        )+%xs", $string) )
        {

            if ( mb_detect_encoding($string,'UTF-8') === 'UTF-8' ) {
                if (
                    // now is it valid?
                    // From http://w3.org/International/questions/qa-forms-utf-8.html
                    preg_match("%^(?:(?>				 # the ?> means the subpattern will not be recursed into IMPORTANT
                    [\x09\x0A\x0D\x20-\x7E]            # ASCII
                    | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
                    |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
                    | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
                    |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
                    |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
                    | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
                    |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
                ))*$%xs", $string)
                ) {
                    return 'UTF-8';
                }
            }
        }

        // other encodings are trickier. For our purposes we're likely to get windows 1252.
        // I'll "detect" this by looking for non-Latin1 characters
        if ( preg_match("/[\x80-\x9F]/", $string) ) return "WINDOWS-1252";

        // ok let's call it Latin1 now
        return "ISO-8859-1";
    }
    static public function mimeHeaderEncode($data)
    {
        $encoding = self::mbDetectEncoding($data);

        if ($encoding == 'ASCII') {
            return str_replace("\n","\r\n ",wordwrap($data,70));
        }

        // http://uk1.php.net/mb_encode_mimeheader says that we need to set internal encoding to 
        // the same as is being used here.
        $orig_encoding = mb_internal_encoding();
        mb_internal_encoding($encoding);
        $encoded = mb_encode_mimeheader($data,$encoding);
        // restore original encoding
        mb_internal_encoding($orig_encoding);
        return $encoded;
    }

}

// vim: sw=4 ts=4
