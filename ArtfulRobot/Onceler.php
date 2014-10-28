<?php
/**
 * Class to help make sure things that should only happen once, do!
 * Synopsis:
<code>
// generate a link
$token = \ArtfulRobot\\ArtfulRobot\Onceler::newToken();
echo "<a href='?do=something&oncelerToken=$token' >go</a>;

// check a link 
if (!\ArtfulRobot\\ArtfulRobot\Onceler::check_get()) unset($_GET['do']);
if ($_GET['do'] == 'something') do_something();
</code>

 * Note: this does not check that the token was created by \ArtfulRobot\Onceler
 * just that it has not been seen before. You do not need to use new_token
 * to generate tokens.
 */
class Onceler
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
		if (self::$done_get) throw new Exception("\ArtfulRobot\Onceler::check_get() called twice");

		self::$done_get = true;
		return self::check($_GET,  $altKey);;
	}
	public static function check_post( $altKey='oncelerToken') 
	{ 	
		if (self::$done_post) throw new Exception("\ArtfulRobot\Onceler::check_post() called twice");

		self::$done_post = true;
		return self::check($_POST,  $altKey);;
	}

	/** has token been used before?
	 *
	 * @var &array $source Source array
	 * @var string $key    key to find token in source array, e.g. oncelerToken
	 * @return bool|null true (not seen before), false (seen before), null (no token)
	 */ 
	public static function check( &$source, $key )
	{
		if (! $key) throw new Exception("\ArtfulRobot\Onceler::check - no key given");
		if (! is_array($source)) throw new Exception("\ArtfulRobot\Onceler::check - source is not an array");
		$token = \ArtfulRobot\Utils::arrayValue($key, $source);
		// no token - return null;
		if (! $token )
		{
			\ArtfulRobot\Debug::log("!! Warning: \ArtfulRobot\Onceler::check - no token at key $key returning null");
			return null;
		}

		$spent_tokens = & \ArtfulRobot\Utils::arrayReference('\ArtfulRobot\Onceler::spent_tokens', $_SESSION, array() );
		if ( array_key_exists( $token, $spent_tokens ) )
		{
			\ArtfulRobot\Debug::log("!! \ArtfulRobot\Onceler: recognised spent token, resetting source token.");
			$source[$key] = false;
			return false;
		}
		//error_log("!! setting new token $token");
		// new token
		$spent_tokens[$token] = true;
		return true;
	}

	/** Generate a token we've not used before
	 */
	public static function newToken()
	{
		$spent_tokens = \ArtfulRobot\Utils::arrayReference('\ArtfulRobot\Onceler::spent_tokens', $_SESSION, array() );
		
		while ( array_key_exists( 
			$new = md5(++self::$count . 'NaCl' . time()),
			$spent_tokens) ) self::$count++ ;

		return $new;
	}
}

