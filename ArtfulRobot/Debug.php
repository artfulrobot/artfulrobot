<?php
namespace ArtfulRobot;

/*
	Copyright 2007-2014 Rich Lott

This file is part of Artful Robot Libraries.

Artful Robot Libraries is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by the Free
Software Foundation, either version 3 of the License, or (at your option) any
later version.

Artful Robot Libraries is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
details.

You should have received a copy of the GNU General Public License along with
Artful Robot Libraries.  If not, see <http://www.gnu.org/licenses/>.

 */

//class Debug
/** Debug class provides debugging facility
 *
 *  This may be used for all debugging needs. See doc.
 *  It is used internally by all Artful Robot Libraries code.
 *  It is turned off by default.
 *
 *  It logs various levels of message, and can be configured to log each
 *  level of message to different services.
 *
 *  Services include immediate outputs like terminal (inc. colour terminals), html
 *  and to a file, but there is also a store service which captures log messages
 *  in a buffer to be later processed in one go by another service like email or
 *  output html.
 *
 *  There are functions for handling errors and exceptions.
 *
 *  Fatal errors will cause either an exit() or will call the configured exit callback
 *  function. This way you can have the debugger catch errors, do something useful
 *  like log to email, then pass execution back to something that can present the
 *  user with a nicer error page (they'll be thrilled).
 *
 *  Synopsis:
 *
 *  Using a debug profile:
 *      \ArtfulRobot\Debug::loadProfile(<profilename>);
 *
 *  Manually configuring
 *      \ArtfulRobot\Debug::setExceptionHandler();
 *      \ArtfulRobot\Debug::setErrorHandler();
 *      \ArtfulRobot\Debug::setServiceLevel('cterm', \ArtfulRobot\Debug::LEVEL_LOG);
 *
 *  Logging
 *      \ArtfulRobot\Debug::log("a line of log", $var);
 *      \ArtfulRobot\Debug::log(">> starting a group/function");
 *      \ArtfulRobot\Debug::log("a line of log", $var);
 *      \ArtfulRobot\Debug::log("<< ending a group/function - will get time in ms");
 *      \ArtfulRobot\Debug::log("!!an important line of log");
 *      \ArtfulRobot\Debug::log("I need to use <b>html</b> in my message so I end with <html>");
 *      \ArtfulRobot\Debug::fatal("Something real bad happened");
 *      \ArtfulRobot\Debug::redirect("/");
 *
 *
 *  loadProfile sets a lot of stuff at once.
 *  All profiles include error_log on finish.
 *  profile         error_reporting     continue_anyway      meaning
 *  online                                            file on fatal only.
 *  file_minimal    -1                  file important+
 *  debug           -1                  file for everything
 *  debug_important -1                  file for important only
 *  unsafe_html     -1                  file everything, finish outputs html. Intercept redirect.
 *  cterm           -1                  output everything to colour terminal
 *  unsafe_echo     -1                  output everything using echo in realtime.
 *
 */

class Debug
{
    const LEVEL_DISABLE        = 0;

    const LEVEL_FINISH_NATURAL = 1;
    const LEVEL_FINISH_FATAL   = 2;
    const LEVEL_FINISH_REDIRECT= 3;

    const LEVEL_IMPORTANT      = 4;
    const LEVEL_STACK          = 5;
    const LEVEL_LOG            = 6;

    const VARS_PRINT_R    = 1;
    const VARS_SERIALIZE  = 2;
    const VARS_JSON       = 3;
    // max handled
    protected static $on = 0;
    // services for each level
    protected static $functions = array(
            1 => array(),
            2 => array(),
            3 => array(),
            4 => array(),
            5 => array(),
            6 => array(),
        );
    protected static $opts = array(
            'text_depth' => false,
            'text_mem'   => false,
            'text_vars'  => true,
            'slow'       => 0.1,
            'redirect_intercept' => false,
            'allow_get_html' => false,
            'ignore_errors' => 0, // e.g. E_STRICT | E_NOTICE
            'file'       => '/tmp/debug-crash',
            'file_append' => false,
            );
    /** services offered */
    protected static $services = array(
            'echo'      => 'serviceEcho',
            'cterm'     => 'serviceColourTerminal',
            'error_log' => 'serviceErrorLog',
            'stderr'    => 'serviceStdErr',
            'store'     => 'serviceStore', // used for output_html
            'file'      => 'serviceFile',
            // the following enable certain behaviours
            'allow_html'         => 'serviceAllowHtml',
            'intercept_redirect' => 'serviceInterceptRedirect',
            // these should only be used for finish level stuff
            // they output in a batch; ones above are line by line.
            'output_html' => 'serviceOutputHtml',
            'mail'        => 'serviceMail',
            );

    // variable to hold message and vars during a log() call
    protected static $current;

    /** file handle for file ops */
	protected static $fh;

    /** used to keep stack depth */
	protected static $depth = 0;

    /** holds stack */
    protected static $stack = array();

    /** holds store*/
    protected static $store =array();

    /** holds text for serviceOutputHtml */
    protected static $redirect_preamble;

    /** allows pushProfile and popProfile to temporarily change debugging state */
    protected static $profile_stack=array();

    /** can use a function callback instead of exit. e.g. to give user nice(r) message */
    protected static $exit_callback=false;

    /** if we are exception handler, should we pass on to previous handler after processing? */
    protected static $rethrow_execptions=true;

    /** used by rethrow_exceptions */
    protected static $previous_exception_handler=null;

    // main use functions
    //public static function log( $msg, $vars=null )/*{{{*/
    /** Main logging method
     */
    public static function log( $msg, $vars=null )
    {
		static::$current = array();
        // figure out level
        $prefix = substr($msg,0,2);
        if (!$prefix) {
            $prefix = '';
        }
        $map = array(
            '->' => static::LEVEL_FINISH_REDIRECT,
            '$$' => static::LEVEL_FINISH_NATURAL,
            'XX' => static::LEVEL_FINISH_FATAL,
            '!!' => static::LEVEL_IMPORTANT,
            '>>' => static::LEVEL_STACK,
            '<<' => static::LEVEL_STACK);
        if (array_key_exists($prefix, $map)) {
            static::$current['prefix'] = $prefix;
            $level = $map[static::$current['prefix']];
        } else {
            $level = static::LEVEL_LOG;
            static::$current['prefix'] = '';
        }

        // do not respond if we're not used at this level
        if (!static::$functions[$level]) return;

        // store vars so other methods can access them.
        static::$current['msg'] = $msg;
        static::$current['vars'] = $vars;
        static::$current['level'] = $level;

        if ($level == static::LEVEL_STACK) {
            static::trackLevel();
        }

        // we need to do SOMETHING
        foreach (static::$functions[$level] as $_) {
            static::$_();
        }

        if ($level == static::LEVEL_STACK) {
            static::$depth += static::$current['depth'];
        }

        static::$current = array();
    }/*}}}*/
	public static function finish( $msg='Successful finish', $vars=null )//{{{
	{
        self::log( "$$ $msg", $vars);
	} //}}}
	public static function redirect($href, $http_response_code=303) // {{{
	{
        static::$redirect_preamble = '';
        foreach (static::$functions[static::LEVEL_FINISH_REDIRECT] as $_) {
            if ($_ == 'serviceInterceptRedirect') {
                static::$redirect_preamble = "<a href='$href' style='margin-bottom:1em;color:#07f;display:block;' >Click link to continue:
                    $href</a>";
            }
        }

        // log it and take action (e.g. output_html)
        static::log( '->Redirect [' . $http_response_code. "] to $href ");

        // issue normal redirect if we're not intercepting it.
        if (!static::$redirect_preamble) {
            if     ($http_response_code == '301' ) header($_SERVER["SERVER_PROTOCOL"] . " 301 Moved Permanently");
            elseif ($http_response_code == '302' ) header($_SERVER["SERVER_PROTOCOL"] . " 302 Found but, not the best URL to use.");
            elseif ($http_response_code == '303' ) header($_SERVER["SERVER_PROTOCOL"] . " 303 See other by GET");
            elseif ($http_response_code == '404' ) header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
            elseif ($http_response_code ) header($_SERVER["SERVER_PROTOCOL"] . " $http_response_code");

			header( "Location: $href");
        }
		/* we close the session because otherwise there's a slim chance that
		 * the redirected page will run and write its session before this one does.
		 */
		session_write_close();
		exit;
	} // }}}
	//public static function fatal( $msg='fatal exit', $vars=null )//{{{
    /** publicly accessible fatal method.
     *
     *  Mostly call this in place of exit() to ensure all finish level debug services
     *  are delivered.
     *
     *  Adds a backtrace automatically.
     *
     *  Will use exit_callback if provided.
     */
	public static function fatal( $msg='fatal exit', $vars=null )
	{
        error_log("ArtfulRobot\\Debug::fatal");
        // where to add backtrace?
        if ($vars === null) {
            $vars = static::getTrace();
        } else {
            $vars = array('variables'=>$vars, 'backtrace'=>static::getTrace() );
        }

        self::log( "XX $msg", $vars);
        self::exitNow($msg, $vars);
	} //}}}
	// public static function handleError($errno, $errstr, $errfile, $errline) // {{{
	/** error handler
	 */
	public static function handleError($errno, $errstr, $errfile, $errline)
	{
		// if error_reporting is turned off for this type of error, do nothing.
		// this is important as code may well want to turn errors off temporarily, or use @something()
		if (! (error_reporting() & $errno )) return;

		switch ($errno) {
            case E_USER_WARNING:
            case E_WARNING:
                $msg="!!Warning ";
                break;

            case E_USER_NOTICE:
            case E_NOTICE:
                $msg="!!Notice ";
                break;

            case E_STRICT:
                $msg="!!Strict ";
                break;
		}
        $msg.="Error [$errno] $errstr. <br />\n:sp +$errline $errfile<html>";

		if ($errno & self::$opts['ignore_errors']) {
            static::log($msg . " (Execution set to continue for this type of error)");

        } else {
            // fatal error. Restore error handler so we can't get in a loop
            restore_error_handler();
            static::fatal($msg);
        }
		/* Don't execute PHP internal error handler */
		return true;
	} // }}}
	// public static function handleException($exception) // {{{
	/** uncaught exception handler, logs and either rethrows or exits
     *
	 */
	public static function handleException($exception)
  {
    if (!(is_a($exception, 'Exception'))) {
      error_log("xxx Debug::handleException called with something that is not an Exception" . serialize($exception));
      $backtrace = debug_backtrace();

      file_put_contents('/tmp/' . date('Y-m-d-His') . '-handleException-backtrace', serialize($backtrace));
      file_put_contents('/tmp/' . date('Y-m-d-His') . '-handleException-error', serialize($exception));
    }
    self::logException($exception, false);

    // we should not attempt to handle further exceptions
    set_exception_handler(self::$previous_exception_handler);
    restore_error_handler();

    // now we either rethrow it...
    if (self::$rethrow_execptions) {
      throw($exception);
    }
    else {
      // ... or we need to exit
      self::exitNow('uncaught exception', $exception);
    }
  } // }}}
	//public static function logException($exception)//{{{
    /** log exception with backtrace at LEVEL_FINISH_FATAL but not itself fatal
     *  This will trigger all the fatal level services that are setup,
     *  If one of those causes an exit (e.g. outputHtml) then it WILL be fatal
     *
     *  This way on a dev environment this call will be fatal, and
     *  on a live one it might write a file but carry on to produce a friendly
     *  error message for users.
     */
	public static function logException($exception, $caught=true)
	{
        $message = "XX "
                . ($caught ? "Caught" : "Uncaught" )
                . "Exception: (backtrace follows) "
                . ( $exception->getCode()
                    ? '(code ' . $exception->getCode() . ')'
                    : '' )
                . $exception->getMessage();
		self::log( $message, static::getTrace($exception->getTrace()));
	} // }}}
    //public static function getHtml()/*{{{*/
    /** return html version of the log, so you can put it at the right place in a template.
     *
     *  This is only allowed by setting the serviceAllowHtml.
     *  e.g. you could setFinishServices('allow_html');
     *  and then when you call getHtml() you'll get the log so long as
     *  finish() has been called.
     *
     *  or you could setServiceLevel('allow_html','ALL') and then the log
     *  would be available before completion. However that's a bit odd because
     *  the log only really makes sense printed once.
     */
    public static function getHtml()
    {
        if (!static::$opts['allow_get_html']) {
            return '';
        }
        return static::generateHtml();
    }//}}}
    public static function getFile()/*{{{*/
    {
        return static::$opts['file'] ;
    }/*}}}*/
    // config: when to trigger services
    //public static function setServiceLevel($new_service,$new_level=self::LEVEL_DISABLE)/*{{{*/
    /** set what level a particular service kicks in at
     */
    public static function setServiceLevel($new_service,$new_level=self::LEVEL_DISABLE)
    {
        if (!isset(static::$services[$new_service])) {
            throw new \Exception("Debugger does not know '$new_service'. Knows only " . implode(', ',array_keys(static::$services)));
        }
        // convert service to method name
        $new_service =  static::$services[$new_service];
        $new_level = static::parseServiceLevel($new_level);

        if ($new_level == static::LEVEL_FINISH_NATURAL
            || $new_level == static::LEVEL_FINISH_FATAL
            || $new_level == static::LEVEL_FINISH_REDIRECT ) {
            // setting a finish thing
            throw new \Exception("Please use setRedirectServices, setFinishServices, setFinishServices instead");
        }

        // go through each level from 1 up.
        foreach (static::$functions as $level=>$functions) {
            if ($level>$new_level ) {
                // remove the service from this level
                static::$functions[$level] =
                    array_diff(static::$functions[$level], array($new_service));
            } else {
                // add the service in (if not already in)
                if (!in_array($new_service,static::$functions[$level])) {
                    if ($new_service=='serviceStore') {
                        // store must be executed first.
                        array_unshift( static::$functions[$level], $new_service);
                    } else {
                        static::$functions[$level][] = $new_service;
                    }
                }
            }
        }
    }/*}}}*/
    public static function setRedirectServices()/*{{{*/
    {
        static::setEndServices(func_get_args(),static::LEVEL_FINISH_REDIRECT);
    }/*}}}*/
    public static function setFatalServices()/*{{{*/
    {
        static::setEndServices(func_get_args(),static::LEVEL_FINISH_FATAL);
    }/*}}}*/
    public static function setFinishServices()/*{{{*/
    {
        static::setEndServices(func_get_args(),static::LEVEL_FINISH_NATURAL);
    }/*}}}*/
    public static function setExitCallback($callback=null)/*{{{*/
    {
        if ($callback && is_callable($callback)) {
            static::$exit_callback = $callback;
        } else {
            static::$exit_callback = false;
        }
    }/*}}}*/
    public static function setExceptionRethrow($rethrow=true)/*{{{*/
    {
        static::$rethrow_execptions = (bool) $rethrow;
    }/*}}}*/
    //public static function setExceptionHandler()/*{{{*/
    /** sets this up to handle exceptions
     *
     * Important to use this and not set_exception_handler directly
     * if you want to have Debug rethrow exceptions because
     * restore_exception_handler() does not work from within an
     * exception handler
     */
    public static function setExceptionHandler()
    {
        if (self::$previous_exception_handler !== null) {
            // we have already been called.
            return;
        }
        self::$previous_exception_handler = set_exception_handler(array('\\ArtfulRobot\\Debug','handleException'));

        // for compatibility's sake
        return self::$previous_exception_handler;
    }/*}}}*/
    //public static function setErrorHandler()/*{{{*/
    /** sets this up to handle errors
     *
     * This is not important, could use set_error_handler direct,
     * but provided because setExceptionHandler IS important,
     * so having a corresponding setErrorHandler might be clearer.
     */
    public static function setErrorHandler()
    {
        return set_error_handler(array('\\ArtfulRobot\\Debug','handleError'));
    }/*}}}*/
    // other config/settings
	public static function loadProfile($profile )//{{{
	{
        // take control of errors, exceptions
        self::setExceptionHandler();
        self::setErrorHandler();
		// reset
    	static::$functions = array(
            1 => array(),
            2 => array(),
            3 => array(),
            4 => array(),
            5 => array(),
            6 => array(),
        );

        // use all error reporting
        error_reporting(E_ALL | E_STRICT );

        if ($profile == 'online') {
            // Don't do anything unless we have fatal error,
            // in which case file and error_log
            static::setFatalServices('file','error_log');
            // ignore strict and notice errors
            error_reporting(E_ALL & ~E_STRICT & ~ E_NOTICE);

        } elseif ($profile == 'file') {
            static::setTextDepth();
            // ... write a file always for everything
            static::setServiceLevel('file', static::LEVEL_LOG);
            // ... only use error_log for crashes
            static::setFatalServices('file','error_log');
            static::setRedirectServices('file');
            static::setFinishServices('file');

        } elseif ($profile == 'file_minimal') {
            static::setTextDepth();
            // ... write a file always for everything important
            static::setServiceLevel('file', static::LEVEL_IMPORTANT);
            // ... only use error_log for crashes
            static::setFatalServices('file','error_log');
            static::setRedirectServices('file');
            static::setFinishServices('file');

        } elseif ($profile == 'unsafe_html') {
            // remember everything and output everything as HTML at end
            static::setServiceLevel('store', static::LEVEL_LOG);
            // write everything to a file
            static::setServiceLevel('file', static::LEVEL_LOG);
            // crashes result in error_log and html
            static::setFatalServices('file','error_log','output_html');
            // redirects are intercepted
            static::setRedirectServices('intercept_redirect','file','store','output_html');
            // templates can access log html at finish.
            static::setFinishServices('allow_html','store','file');

        } elseif ($profile == 'cterm') {
            // everything out to cterm
            static::setServiceLevel('cterm', 'ALL');
            static::setFatalServices('cterm');
            static::setRedirectServices('cterm');
            static::setFinishServices('cterm');

        } elseif ($profile == 'unsafe_echo') {
            // output everything as it happens
            static::setServiceLevel('echo', static::LEVEL_LOG);
            // crashes result in error_log as well
            static::setFatalServices('echo','error_log');
            static::setRedirectServices('echo');
            static::setFinishServices('echo');
        } else {
            throw new \Exception("unknown debug profile: $profile. Known: unsafe_echo, cterm, unsafe_html, file_minimal, file, online");
        }
	} // }}}
	public static function pushProfile($profile)//{{{
	{
        static::log("Debug::pushProfile('$profile')");
        $current = array('functions' => static::$functions);
        array_push(static::$profile_stack, $current);
        static::loadProfile($profile);
	} // }}}
	public static function popProfile()//{{{
	{
        static::log("Debug::popProfile");
        $current = array_pop(static::$profile_stack);
        if (!$current) {
            throw new \Exception("Debug::popProfile called on empty stack");
        }
        static::$functions = $current['functions'];
	} // }}}
    public static function setTextDepth($on=true)/*{{{*/
    {
        static::$opts['text_depth'] = (bool) $on;
    }/*}}}*/
    public static function setTextMem($on=true)/*{{{*/
    {
        static::$opts['text_mem'] = (bool) $on;
    }/*}}}*/
    public static function setTextVars($on=true)/*{{{*/
    {
        static::$opts['text_vars'] = (bool) $on;
    }/*}}}*/
    public static function setMailTo($mail_to)/*{{{*/
    {
        static::$opts['mail_to'] = $mail_to;
    }/*}}}*/
    public static function setSlow($slow=0.1)/*{{{*/
    {
        static::$opts['slow'] = $slow;
    }/*}}}*/
    public static function setFile($filename, $append=false)/*{{{*/
    {
        if (isset(static::$fh)) {
            static::log("Changing file to $filename");
            fclose(static::$fh);
            static::$fh=null;
        }
        static::$opts['file'] = $filename;
        static::$opts['file_append'] = (bool) $append;
    }/*}}}*/
    // services
    protected static function serviceEcho()/*{{{*/
    {
        echo static::getText() . "\n";
        flush();
    }/*}}}*/
    protected static function serviceColourTerminal()/*{{{*/
    {
        $deets = static::getText(false) ;
        echo "\033[1;30m" . $deets['depth'] . "\033[0m"
            ."\033[1;32m" . $deets['mem'] . "\033[0m"
            . ( static::$current['level'] <= static::LEVEL_IMPORTANT
              ? "\033[1;31m"
              : "\033[1;37m" )
            . trim($deets['msg']," \n\r") . "\033[0m"
            . $deets['mem'] . "\n"
            . ($deets['vars']
               ?  "\t" . str_replace("\n","\n\t", $deets['vars']). "\n"
               : "");
        flush();

    }/*}}}*/
    protected static function serviceErrorLog()/*{{{*/
    {
        error_log(static::getText());
    }/*}}}*/
    protected static function serviceStdErr()/*{{{*/
    {
        fputs(STDERR, static::getText()."\n");
    }/*}}}*/
    protected static function serviceFile()/*{{{*/
    {
		$fh =static::getFH();
        fwrite($fh, static::getText()."\n");
    }/*}}}*/
    protected static function serviceStore()/*{{{*/
    {
        // store this log
        static::$store[] = static::$current;
        // freeze vars if object because this might change
        if ( isset(static::$store['vars']) && is_object( static::$store['vars'] ) )
            static::$store['vars']  = serialize(static::$store['vars'] );

    }/*}}}*/
    protected static function serviceAllowHtml()/*{{{*/
    {
        static::$opts['allow_get_html'] = true;
    }/*}}}*/
    protected static function serviceOutputHtml()/*{{{*/
    {
        // output html version of stored log
        echo static::generateHtml();
    }/*}}}*/
    protected static function serviceInterceptRedirect()/*{{{*/
    {
        // dummy service, picked up by redirect() instead.
    }/*}}}*/
    protected static function serviceMail()/*{{{*/
    {
        if (empty( static::$opts['mail_to'] )) {
            error_log("Debug::serviceMail called without mail_to address!");
            return;
        }
        // output html version of stored log
        $email = new Email( static::$opts['mail_to'], "Debug log", static::generateHtml() );
        $email->send();

    }/*}}}*/
    // internals
	protected static function exitNow($msg='', $vars=null)//{{{
	{
        error_log("ArtfulRobot\\Debug::exitNow");
        // we must now exit.
        if ($fn=static::$exit_callback) {
            // do this by calling external callback
            $fn($msg, $vars);

        } else {
            // normal exit
            exit;
        }
	} //}}}
    protected static function getText( $implode=true )/*{{{*/
    {
        if (! isset(static::$current['text'])) {

            // else built text format
            static::$current['text'] = array();

            static::$current['text']['depth'] =
                static::$opts['text_depth']
                ? str_repeat( '-',  self::$depth) . '|'
                : '';

            static::$current['text']['mem'] =
                static::$opts['text_mem']
                ?   sprintf("%0.1fMb ", memory_get_usage()/1024/1024)
                : '';

            static::$current['text']['msg'] = static::$current['msg'];
			// strip <html> tag from end of msg - no use to us.
			if (substr(static::$current['msg'], -6)=="<html>") {
				static::$current['text']['msg'] =
					strip_tags(strtr(static::$current['msg'], array(
						'<html>' => '',
						'<strong>' => '**',
						'</strong>' => '**',
						'<br/>' => "\n",
						'<br>' => "\n",
						)));
			} else {
				static::$current['text']['msg'] = static::$current['msg'];
			}

			// prefix?
			if (static::$current['prefix'] == '>>') {
				static::$current['text']['msg'] .= ' {{{';
			} elseif (static::$current['prefix'] == '<<') {
				static::$current['text']['msg'] .= ' }}}';
			}

            if (! ($_ = static::$opts['text_vars'])) {
                $vars = '';
            } elseif ($_ == static::VARS_PRINT_R) {
                $vars = print_r(static::$current['vars'],1);
            } elseif ($_ == static::VARS_SERIALIZE) {
                $vars = serialize(static::$current['vars']);
            } elseif ($_ == static::VARS_JSON) {
                $vars = json_encode(static::$current['vars']);
            }
            static::$current['text']['vars'] = $vars ? "\nVars: {{{\n$vars }}}" : '';

        }
        if ($implode) return implode('', static::$current['text']) . "\n";
        return static::$current['text'];
    }/*}}}*/
    protected static function getFH()/*{{{*/
    {
        if (!isset(static::$fh)) {
			$filename =static::$opts['file'];
			if (file_exists($filename) && !is_writeable($filename)) {
				// turn off file debugging
				static::setServiceLevel('file');
				static::fatal("Could not write " . $filename);
			}

			// set group, owner RW, everyone else nothing
			@touch($filename);
			@chmod($filename, 0660);
			static::$fh = @fopen($filename,static::$opts['file_append'] ? 'a' : 'w');
			if (!static::$fh) {
				// turn off file debugging
				static::setServiceLevel('file');
				static::fatal("Could not write $filename");
			}
            fwrite(static::$fh,
                    "-------------------------------------------------------------------\n" .
                    "Debug log at " . date('H:i:s d M Y') . "\n\n");
        }
        return static::$fh;
    }/*}}}*/
    protected static function trackLevel()/*{{{*/
    {
        if (substr(static::$current['msg'],0,2)=='>>') {
            static::$stack[] = array(
                    'time' => microtime(true),
                    );
            static::$current['depth'] = 1;
        } else {
            $pop = array_pop(static::$stack);
            $time_diff = microtime(true) - $pop['time'];
            // submit extra log?
            static::$current['msg'] .= sprintf(' %0.3fs', $time_diff);
            if ($time_diff>static::$opts['slow']) {
                static::$current['level'] = static::LEVEL_IMPORTANT;
                static::$current['msg'] = "<< SLOW: " . substr(static::$current['msg'],2);
            }
            static::$current['depth'] = 0;
            static::$depth>0 && static::$depth--;
        }
    }/*}}}*/
    protected static function getTrace($trace=null)/*{{{*/
    {
        // @todo need to check for low memory and return array() if likely to cause crash with debug_backtrace
		// remove ourselves from the trace.
		if (!$trace) {
            $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
        }

		while (!empty($trace[0]['class']) && ($trace[0]['class'] == 'ArtfulRobot\Debug')) {
			array_shift($trace);
		}
		return $trace;
    }/*}}}*/
    protected static function setEndServices($new_services,$new_level)/*{{{*/
    {
        // validate services and ensure that outputHtml is the last thing.
        $outputHtml = false;


        if (in_array('output_html', $new_services)
            ||in_array('mail', $new_services)) {
            // remove store and put it at the start
            $new_services = array_diff($new_services, array('store'));
            // make store the first thing
            array_unshift($new_services, 'store');

            if (in_array('output_html', $new_services)) {
                $new_services = array_diff($new_services, array('output_html'));
                // append output_html as the last thing (it calls exit)
                array_push($new_services, 'output_html');
            }
        }

        foreach ($new_services as &$new_service) {
            if (!isset(static::$services[$new_service])) {
                throw new \Exception("Debugger does not know '$new_service'. Knows only " . implode(', ',array_keys(static::$services)));
            }
            // convert service to method name
            $new_service = static::$services[$new_service];
        }

        // erase all services for this finish level and replace
        // with those provided
        static::$functions[$new_level] = $new_services;

    }/*}}}*/
    protected static function parseServiceLevel($new_level)/*{{{*/
    {
        if (is_string($new_level)) {
            if ($new_level == 'ALL') $new_level = static::LEVEL_LOG;
            elseif ($new_level == '!!') $new_level = static::LEVEL_IMPORTANT;
            elseif ($new_level == '>>') $new_level = static::LEVEL_STACK;
            elseif ($new_level == 'XX') $new_level = static::LEVEL_ABNORMAL_EXIT;
            elseif ($new_level == '->') $new_level = static::LEVEL_REDIRECT;
            elseif ($new_level == '$$') $new_level = static::LEVEL_NATURAL_FINISH;
        }
        if (!(is_int($new_level) && $new_level>=0 && $new_level<=static::LEVEL_LOG))
            throw new Exception("Invalid level given. Levels are ALL !! >> XX -> $$ or use class constants LEVEL_LOG LEVEL_IMPORTANT LEVEL_STACK LEVEL_ABNORMAL_EXIT LEVEL_REDIRECT LEVEL_NATURAL_FINISH");
        return $new_level;
    }/*}}}*/
    protected static function generateHtml()/*{{{*/
    {

        // make html version of stored log
        // todo make it clear that this depends on serviceStore
		$css ="
<style>
.arl-debug {
	border:solid 1px red;
	padding:6px;
	background:#e8e8e8;
}
.arl-debug-important>.arl-debug-msg {
	margin:6px 0 0 6px;
	color:#800;
	border-left:solid 10px #d00;
	padding-left:6px;
}
.arl-debug-stack { margin:8px 0 0 6px;background-color:#faf8f0;border:solid 1px #bbb;border-radius:3px 3px 3px 3px; }
.arl-debug-stack-start { border:solid 1px white;padding-left:6px;border-radius:3px 3px 3px 3px;	background:#e8e8e8;border-top:none; }
.arl-debug-greyed { color:#888; }
.arl-debug-mem { float:right;margin-left:7px;color:#888; }
.arl-debug-var-toggle { cursor:pointer; }
.arl-debug-var-toggle:hover { color:#28f; }
.arl-debug-var-toggle:before { content: 'Show'; font-size:10px; }
.arl-debug-vars { background-color:#faf8f0;padding:0 7px;margin:0 6px 6px;border:solid 1px #aaa;border-radius:3px 3px 3px 3px;font-size:10px;color:#242;white-space:pre;}
.arl-debug-vars.expanded>.arl-debug-var-toggle:before { content: 'Hide';color:#bba; }
.arl-debug-vars pre {display:none; }
.arl-debug-vars.expanded>pre {display:block;}
.arl-debug-msg {cursor:pointer;}
.arl-debug-msg:hover {background-color:#e0f8f8;color:black;}
.arl-debug-selected { background-color:#e0f8f8;color:black;border-left:solid 6px #28f; box-shadow: -2px 4px 6px 2px rgba(0,0,0,0.4);margin-left:0;margin-bottom:1em;}
</style>";

		$css = strtr($css, array(
					"\n"=>" ",// javascript can't handle new lines...
					'"' =>'\\"')); // need to quote "

		$html = "
<script>
function arlDebugUI() {
	if (window.arlDebugCss) {
        return;
    }
    jQuery(\"$css\").appendTo('head');
	window.arlDebugCss = 1;
	var d=jQuery('.arl-debug');
	d.find('.arl-debug-var-toggle').unbind('click').click(function(){
		jQuery(this).parent().toggleClass('expanded');});
	d.find('.arl-debug-msg').unbind('click').click(function(e){
		e.stopPropagation();
		var p = jQuery(this).parent();
		if (p.hasClass('arl-debug-stackend')) {
			p = p.parent();
		}
		p.toggleClass('arl-debug-selected');});
}
if(typeof jQuery=='undefined') {
	var jqTag = document.createElement('script');
	jqTag.src = '//ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js';
	jqTag.onload = function(){jQuery(arlDebugUI);};
	document.getElementsByTagName('head')[0].appendChild(jqTag);
} else {
	jQuery(arlDebugUI);
}
</script>";

        $html .= "<div class='arl-debug' >"
            . static::$redirect_preamble;
        $depth = 0;
        foreach(static::$store as $row) {
			$class = array();
            if ($row['level']<=static::LEVEL_IMPORTANT) {
				$class[] = 'arl-debug-important';
			}

            if ($row['prefix'] == '<<') {
				$class[] = 'arl-debug-stackend';
            } elseif ($row['prefix'] == '>>') {
				$class[] = 'arl-debug-stack';
            } elseif ($row['level']==static::LEVEL_LOG) {
				$class[] = 'arl-debug-greyed';
            }
			$line = "<div class='" . implode(' ', $class) . "'><span class='arl-debug-msg'>";

			if (substr($row['msg'], -6)=="<html>") $line .= str_replace('<html>','',$row['msg']);
			else $line .= htmlspecialchars($row['msg']);

			$line .="</span>"
                . (isset($row['mem']) ? "<div class='arl-debug-mem'>$row[mem]</div>" : '')
                . (!empty($row['vars']) ?  self::varDumpHTML($row['vars']) : '');

            if ($row['prefix'] == '>>') {
                $depth++;
                $line .= "<div class='arl-debug-stack-start'>";
            } elseif ($row['prefix'] == '<<') {
                $line .= "</div></div></div>"; // close self, and 2 outers.
                if (--$depth<0) {
                    $depth=0;
                }
            } elseif ($row['prefix'] == 'backtrace') {
                $line .= str_repeat("</div></div></div>",$depth);
                $depth =0;
            } else {
                $line .= "</div>";
            }
            $html .= $line;
        }
        $html .= "</div>";

        return $html;
    }/*}}}*/
    protected static function varDumpHTML($var)/*{{{*/
    {
//        file_put_contents("/tmp/testdata", serialize($var));
        $var = print_r($var,1);
        if (strlen($var)>10000) {
            return "(" . sprintf('%0.1f',(strlen($var)/1024)) . "Kb)<div class='arl-debug-vars'><div class='arl-debug-var-toggle'></div><pre>"
            . htmlspecialchars($var) . "</pre></div>";
        }
        $var = explode("\n",$var);
        $html = "<div class='arl-debug-vars'><div class='arl-debug-var-toggle'></div><pre>";

        $i=0;
        $opened = 0;
        while ($var) {
            $line = array_shift($var);
            if (!$line) { continue; }

            if (preg_match('/^( *)([()])$/',$line,$matches)) {
                $i = strlen($matches[1]);
                if ($i % 4) {
                    // let's assume this indent is not valid
                    $html .= htmlspecialchars($line) . "\n";
                } elseif ($matches[2]=='(') {
                    $html .= "</pre><div class='arl-debug-vars'><div class='arl-debug-var-toggle'></div><pre>(\n";
                    $opened++;
                } else {
                    $html .= ")</pre></div>\n";
                    $opened--;
                }
            } else {
                $html .= htmlspecialchars(trim($line)) . "\n";
            }
        }
        $html .= "</pre></div>\n" ;
        if ($opened) {
            $html .= "<strong>unbalanced</strong>";
        }
        return $html;
    }/*}}}*/
}

