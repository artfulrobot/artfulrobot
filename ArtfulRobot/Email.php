<?php
namespace ArtfulRobot;

/** Creates UTF-8 html emails with attachments and embedded images
  */
class Email
{
	protected $headers;
	protected $subject;
	protected $to;
	protected $reply_to;
	protected $from;
	protected $return_path;
	protected $body=array();
	protected $attachments=array();
	protected $uid;
	protected $line_endings="\n";

	//function __construct($to=null, $subject=null, $message=null)/*{{{*/
	/** constructor
	  */
	function __construct($to=null, $subject=null, $message=null)
	{
		// init blank body parts
		$this->body = array('text'=>'','html'=>'');
		// create hash for boundaries
		$this->uid = md5(serialize($this->body) . time());
		// set up default headers
		$this->headers['MIME-Version']='1.0';
		$this->headers['Content-Type']= "multipart/mixed; boundary=\"\ArtfulRobot\Email-mixed-$this->uid\"";

		if ($to!==null) $this->setTo($to);
		if ($subject!==null) $this->setSubject($subject);
		if ($message!==null) $this->add_message($message);
		return $this;
	}/*}}}*/
	//public function setTo($to)/*{{{*/
	/** append 'to' address
	  */
	public function setTo($to)
	{
		$this->to[] = $to;
	}/*}}}*/
	//public function setFrom($from)/*{{{*/
	/** set from address */
	public function setFrom($from)
	{
		$this->from = $from;
	}/*}}}*/
	//public function setReturnPath($return_path)/*{{{*/
	/** set return path */
	public function setReturnPath($return_path)
	{
		$this->return_path = $return_path;
	}/*}}}*/
	//public function setLineEndings($line_endings)/*{{{*/
	/** configure line_endings
	  */
	public function setLineEndings($line_endings)
	{
		$this->line_endings = $line_endings;
	}/*}}}*/
	//public function setReplyTo($from)/*{{{*/
	/** set Reply-To field */
	public function setReplyTo($from)
	{
		$this->reply_to = $reply_to;
	}/*}}}*/
	//public function setSubject($subject)/*{{{*/
	/** set subject */
	public function setSubject($subject)
	{
		$this->subject = $subject;
	}/*}}}*/
	//public function setHeader($header, $value)/*{{{*/
	/** set header */
	public function setHeader($header, $value)
	{
		// don't overwrite stuff we need
		if ($header == 'Content-Type' || $header=='MIME-Version') return false;
		$this->headers[$header] = $value;
	}/*}}}*/
	//public function setMessageText( $message)/*{{{*/
	/** adds text message */
	public function setMessageText( $message)
	{
		$message = preg_replace('/(\r\n|\n|\r)$/m', "\r\n", $message);
		// let's always send utf8 and be done with it...
		$message = mb_convert_encoding($message, 'UTF-8', $this->mbDetectEncoding($message));
		$this->body['text'] = $message;
	}/*}}}*/
	//public function setMessageHtml( $message, $related_files )/*{{{*/
	/** adds html message
	  * 
	  * the related_files is an array(
	  *    "name" => filename
	  *    )
	  * 
	  * and the html message must include references like {name}
	  * 
	 */
	public function setMessageHtml( $message, $related_files=null )
	{
		$message = preg_replace('/(\r\n|\n|\r)$/m', "\r\n", $message);
		// let's always send utf8 and be done with it...
		$message = mb_convert_encoding($message, 'UTF-8', $this->mbDetectEncoding($message));

		$this->body['html'] = $message;
		$this->body['related'] = $related_files;
	}/*}}}*/
	// public function addAttachment( $filename ) /*{{{*/
	/** add attachment */
	public function addAttachment( $filename )
	{
		$filename = realpath($filename);
		if (!file_exists($filename)) throw new Exception("File $filename not found.");
		if (!is_readable($filename)) throw new Exception("File $filename not readable.");
		if (in_array($filename, $this->attachments)) return ;
		$this->attachments[] = $filename;
	}/*}}}*/
	public function getHeaders($include_to=FALSE)/*{{{*/
	{
		if ($include_to) $this->headers['To'] = implode(", ", $this->to);

		//define the headers we want passed. Note that they are separated with \r\n
		if ($this->from) 
		{
			$this->headers['From'] = $this->from;
			$this->headers['Sender']= $this->from;
		}
		if ($this->reply_to) $this->headers["Reply-To"] = $this->reply_to;

		return $this->headers;
	}/*}}}*/
	//public function getBody()/*{{{*/
	/** create body */
	public function getBody()
	{
		// if html email, make sure text is there as alternative.
		if ($this->body['html'] && ! $this->body['text'])
				$this->createTextFromHtml();
		elseif ($this->body['text'] && ! $this->body['html'])
				$this->createHtmlFromText();


		// email should be 70 characters max
		$this->body['html'] = wordwrap($this->body['html'], 70);
		$this->body['text'] = wordwrap($this->body['text'], 70);

		$uid =$this->uid;
		$body = 
			 "--\ArtfulRobot\Email-mixed-$uid\r\n"
			."Content-Type: multipart/alternative; boundary=\"\ArtfulRobot\Email-alt-$uid\"\r\n"
			."\r\n"
			."--\ArtfulRobot\Email-alt-$uid\r\n"
			."Content-Type: text/plain; charset=\"UTF-8\"\r\n"
			."Content-Transfer-Encoding: 8bit\r\n\r\n"
			.$this->body['text']
			."\r\n";

		// html
		if (empty($this->body['related']))
			$body .=
			 "--\ArtfulRobot\Email-alt-$uid\r\n"
			."Content-Type: text/html; charset=\"UTF-8\"\r\n"
			."Content-Transfer-Encoding: 8bit\r\n\r\n"
			.$this->body['html']
			."\r\n";
		else
		{
			$body .=
				 "\r\n--\ArtfulRobot\Email-alt-$uid\r\n"
				."Content-Type: multipart/related; boundary=\"\ArtfulRobot\Email-rel-$uid\"\r\n"
				."\r\n"
				."--\ArtfulRobot\Email-rel-$uid\r\n"
				."Content-Type: text/html; charset=\"UTF-8\"\r\n"
				."Content-Transfer-Encoding: 8bit\r\n"
				."\r\n";
			$html = $this->body['html'];
			foreach ($this->body['related'] as $name => $filename)
				$subs['{' . $name . '}'] = 'cid:\ArtfulRobot\Email-CID-'.$name;
			$body .= strtr($html, $subs)
				."\r\n";

			foreach ($this->body['related'] as $name => $filename)
				$body .=
				 "--\ArtfulRobot\Email-rel-$uid\r\n"
				. $this->attach($filename, $name)
				. "\r\n";

			$body .=
				 "--\ArtfulRobot\Email-rel-$uid--\r\n"
				. "\r\n";
			
		}
		$body .=
			 "--\ArtfulRobot\Email-alt-$uid--\r\n"
			."\r\n";

		if ($this->attachments)
		{
			//read the atachment file contents into a string,
			//encode it with MIME base64,
			//and split it into smaller chunks
			foreach ($this->attachments as $_)
				$body .= 
					 "--\ArtfulRobot\Email-mixed-$uid\r\n"
					 . $this->attach($_);
		}
		$body .= "--\ArtfulRobot\Email-mixed-$uid--\r\n";

		return $body;
	}/*}}}*/
	//public function send() /*{{{*/
	/** create and send email 
	 * 
	 * Thanks to Drupal for some of this code
	 */
	public function send()
	{
		// Return Path
		// if set with setReturnPath, this is used.
		// if set in headers as Return-Path, this is used.

		$return_path = (!empty($this->return_path)
				? $this->return_path
				: (!empty($this->headers['Return-Path'])
					? $this->headers['Return-Path']
					: null));

		// best to pass the return path in 5th arg of mail()
		// can't though if PHP in safe mode, or the -f flag is
		// hard-coded in the php.ini.
		if ($return_path)
		{
			if (ini_get('safe_mode') 
				 || strpos(ini_get('sendmail_path'), ' -f')!==false)
			{
				// can't use 5th param, put in headers (incase that works?!)
				$this->headers['Return-Path'] = $return_path;
				unset($return_path);
			}
			else unset($this->headers['Return-Path']);
		}

		 $mail_headers = '';
		 // prefer Drupal's mimeHeaderEncode if we have it
		 // no - inconsistent behaviour see http://api.drupal.org/api/drupal/includes%21unicode.inc/function/mimeHeaderEncode/7#comment-44358
		 if (function_exists('mimeHeaderEncode') && false)
		 {
			 $mail_subject = mimeHeaderEncode($this->subject);
			 foreach ($this->getHeaders() as $k=>$v)
				$mail_headers .= "$k: " . mimeHeaderEncode($v) . "\r\n";
		 }
		 else
		 {
			 $mail_subject = self::mimeHeaderEncode($this->subject);
			 foreach ($this->getHeaders() as $k=>$v)
				 $mail_headers .= self::mimeHeaderEncode("$k: $v") . "\r\n";
		 }

		 // Note: e-mail uses CRLF for line-endings. PHP's API requires LF
		 // on Unix and CRLF on Windows. Configure objects if needed with setLineEndings()
		 $mail_body = preg_replace('@\r?\n@', $this->line_endings, $this->getBody());

		 $mail_to = implode(", ", $this->to);

		 // file_put_contents('/tmp/arl-email', "$mail_to\n$mail_subject\n$mail_headers\n$mail_body");

		 if (isset($return_path)) {
			 //\ArtfulRobot\Debug::log("!! sending with -f");
			 $mail_result = mail(
					 $mail_to,
					 $mail_subject, 
					 $mail_body, 
					 $mail_headers, 
					 // Pass the Return-Path via sendmail's -f command.
					 '-f ' . $return_path
					 );
		 }
		 else {
			 //\ArtfulRobot\Debug::log("!! sending without -f");
			 // The optional $additional_parameters argument to mail() is not allowed
			 // if safe_mode is enabled. Passing any value throws a PHP warning and
			 // makes mail() return FALSE.
			 $mail_result = mail(
					 $mail_to,
					 $mail_subject, 
					 $mail_body, 
					 $mail_headers
					 );
		 }
		 return $mail_result;
	}/*}}}*/

	//protected function attach($filename, $cid=null)/*{{{*/
	/** internal function to create attachment */
	protected function attach($filename, $cid=null)
	{
		if (!file_exists($filename)) throw new Exception("File $filename not found.");
		$mime = trim(shell_exec("file -bi " . escapeshellarg( $filename )));
		$file = basename($filename);
		$attachment = 
				 "Content-Type: $mime"
				.( $cid ? '' : ";\r\n name=\"$file\"" )
				. "\r\n"
				."Content-Transfer-Encoding: base64\r\n"
				.( $cid ? "Content-ID: <\ArtfulRobot\Email-CID-$cid>\r\n" 
						: "Content-Disposition: attachment;\r\n"
						 ." filename=\"$file\"\r\n")
				."\r\n"
				.chunk_split(base64_encode(file_get_contents($filename)))
				."\r\n";
		return $attachment;
	}/*}}}*/
	//protected function createTextFromHtml()/*{{{*/
	protected function createTextFromHtml()
	{
		$this->body['text'] = strip_tags(
			// convert brs to single \n
			preg_replace('@<br( */)?' . '>@i',"\n", 
				// convert end of block-level things to \n\n
				preg_replace('@</(p|div|li|ul|h[12345])>@i',"$0\n\n",
					// remove whitespace at start of lines before tag   
					preg_replace('@^\s+<@m','<', $this->body['html'])
			)));
	}/*}}}*/
	
	//protected function createHtmlFromText()/*{{{*/
	protected function createHtmlFromText()
	{
		$this->body['html'] = preg_replace('/(\r\n|\n|\r)/','<br />',$this->body['text']);
	}/*}}}*/

	static public function mbDetectEncoding( $string, $detect_order=null ) // {{{
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
		if ($detect_order!==null) throw new Exception(" mb_detect_encrl does not take detect_order, unlike php's native function.");
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

			if ( mbDetectEncoding($string,'UTF-8') === 'UTF-8' )
			{
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
			) return 'UTF-8';
			}
		}

		// other encodings are trickier. For our purposes we're likely to get windows 1252.
		// I'll "detect" this by looking for non-Latin1 characters
		if ( preg_match("/[\x80-\x9F]/", $string) ) return "WINDOWS-1252";

		// ok let's call it Latin1 now
		return "ISO-8859-1";
	} // }}}
	static public function mimeHeaderEncode($data)/*{{{*/
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
	}/*}}}*/

}

if (!function_exists('mail_rl')) {
function mail_rl($to,$subject,$message,$fakeFrom='', $headers='',$opts='') // {{{
{
	/** mail_rl( to, subject, message, fakeFrom, headers, options )
	  * wrapper for mail() that deals with utf-8 encoding of subject and
	  * spoofing the from address
	  */
	debug('>> called');
	debug('details', array('To:'=>$to, 'Subject:'=>$subject, 'Faked From:'=>$fakeFrom, 'Other Headers:'=>$headers, 'Options'=>$opts, 'Message'=>$message));
	if ($fakeFrom)
	{
		if (! $headers) $headers = "From: $fakeFrom\r\nSender: $fakeFrom";
		else $headers = "From: $fakeFrom\r\nSender: $fakeFrom\r\n$headers\r\n";
		/* 2010-04-22 removed this as it stopped working! fusemail was dropping mail sent this way. 
		if (! $opts) $opts = '-f<>';
		else $opts .= ' -f<>';
		*/
	}
	// ensure subject is properly encoded
	$encoding = mbDetectEncoding_rl($subject);
	$subject = mb_encode_mimeheader($subject,$encoding);
//	this does something like: 
//	$subject = "=?UTF-8?B?".base64_encode(mb_convert_encoding($subject,'UTF-8'))		."?=\n";


	// check encoding of body
	$encoding = mbDetectEncoding_rl($message,null,1);
	// any non-ascii encodings go to utf8
	if ($encoding !='ASCII') 
	{
		$message = mb_convert_encoding( $message, 'UTF-8', $encoding);
		// now is utf8
		if ( ! preg_match('/^Content-type.*UTF/i', $headers) )
		{
				if ($headers) $headers .= "\r\n";
				$headers .= 'MIME-Version: 1.0' . "\r\n" . 'Content-type: text/plain; charset=UTF-8' . "\r\n";
		}
	}

	$retVal = mail($to, $subject, $message, $headers, $opts);
	debug('<<', $retVal);
	return $retVal;
} // }}}
}
