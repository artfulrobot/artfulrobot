<?php
/**
 * Class to help make sure things that should only happen once, do!
 * Synopsis:
<code>
// generate a link
$token = ARL_Onceler::new_token();
echo "<a href='?do=something&oncelerToken=$token' >go</a>;

// check a link (unsets _GET if token has been seen before
ARL_Onceler::check_get();
if ($_GET['do'] == 'something') do_something();
</code>

 * Note: this does not check that the token was created by ARL_Onceler
 * just that it has not been seen before. You do not need to use new_token
 * to generate tokens.
 */
class ARL_Onceler
{
	/** runtime counter for generating multiple
	 * new tokens
	 */
	private static $count=0;
	/** runtime flag - we must not be called twice
	  */
	private static $done_get =false;
	private static $done_post=false;

	public static function check_get( $altKey='oncelerToken') 
	{ 	
		if (self::$done_get) throw new Exception("ARL_Onceler::check_get() called twice");

		self::$done_get = true;
		return self::check($_GET,  $altKey);;
	}
	public static function check_post( $altKey='oncelerToken') 
	{ 	
		if (self::$done_post) throw new Exception("ARL_Onceler::check_post() called twice");

		self::$done_post = true;
		return self::check($_POST,  $altKey);;
	}

	/** reset source to empty array if token seen before
	 *
	 * @var &array $source Source array
	 * @var string $key    key to find token in source array, e.g. oncelerToken
	 */ 
	public static function check( &$source, $key )
	{
		if (! $key) throw new Exception("ARL_Onceler::check - no key given");
		if (! is_array($source)) throw new Exception("ARL_Onceler::check - source is not an array");
		$token = ARL_Array::value($key, $source);
		// no token - no action, assume bad
		if (! $token )
		{
			ARL_Debug::log("TOP Warning: ARL_Onceler::check - no token at key $key");
			return false;
		}

		$spent_tokens = & ARL_Array::reference('ARL_Onceler::spent_tokens', $_SESSION, array() );
		if ( array_key_exists( $token, $spent_tokens ) )
		{
			ARL_Debug::log("TOP ARL_Onceler: recognised spent token, resetting source token.");
			$source[$key] = false;
			return false;
		}
		error_log("TOP setting new token $token");
		// new token
		$spent_tokens[$token] = true;
		return true;
	}

	/** Generate a token we've not used before
	 */
	public static function new_token()
	{
		$spent_tokens = ARL_Array::reference('ARL_Onceler::spent_tokens', $_SESSION, array() );
		
		while ( array_key_exists( 
			$new = md5(++self::$count . 'NaCl' . time()),
			$spent_tokens) ) self::$count++ ;

		return $new;
	}
}
?>