<?php
namespace ArtfulRobot;
/**
 * Class to help make sure things that should only happen once, do!
 *
 * Don't use this for security. The only advantage of this is that the tokens
 * are pretty random (time based md5) so you could fetch a bunch at once and
 * know that they were unique.
 *
 * Synopsis:
 *   <code>
 *   // generate a link
 *   $token = Onceler::newToken();
 *   echo "<a href='?do=something&oncelerToken=$token' >go</a>;
 *
 *   // check a link
 *   if (!Onceler::check_get()) unset($_GET['do']);
 *   if ($_GET['do'] == 'something') do_something();
 *   </code>
 *
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

	public static function check_get( $altKey='oncelerToken') {
		if (self::$done_get) throw new Exception("\ArtfulRobot\Onceler::check_get() called twice");

		self::$done_get = TRUE;
		return self::check($_GET, $altKey);
	}
	public static function check_post( $altKey='oncelerToken') {
		if (self::$done_post) throw new Exception("\ArtfulRobot\Onceler::check_post() called twice");

		self::$done_post = TRUE;
		return self::check($_POST, $altKey);
	}

	/** has token been used before?
	 *
	 * @var &array $source Source array
	 * @var string $key    key to find token in source array, e.g. oncelerToken
	 * @return bool|null true (not seen before), false (seen before), null (no token)
	 */
	public static function check( &$source, $key ) {
		if (! $key) throw new Exception("\ArtfulRobot\Onceler::check - no key given");
		if (! is_array($source)) throw new Exception("\ArtfulRobot\Onceler::check - source is not an array");
		$token =  isset($source[$key]) ? $source[$key] : '';
		// no token - return null;
		if (! $token ) {
			return NULL;
		}

    if (!isset($_SESSION['Onceler::spent_tokens'][$token])) {
      $_SESSION['Onceler::spent_tokens'] = [];
    }
		$spent_tokens = & $_SESSION['Onceler::spent_tokens'];

		if ( array_key_exists( $token, $spent_tokens ) ) {
			$source[$key] = FALSE;
			return FALSE;
		}
		// Was a new token, now spent.
		$spent_tokens[$token] = true;
		return TRUE;
	}

	/** Generate a token we've not used before
	 */
	public static function newToken() {
    do {
      self::$count++;
			$new = md5(++self::$count . 'NaCl' . time());
    } while (isset($_SESSION['Onceler::spent_tokens'][$new]));

		return $new;
	}
}

