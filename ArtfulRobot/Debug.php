<?php
namespace ArtfulRobot;

/*
	Copyright 2007-2011 Rich Lott 

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
 *
 *  It is used internally by all Artful Robot Libraries code.
 *  
 *  It is turned off by default.
 *
 *  Synopsis:
 *
 *  set_error_handler( array('\ArtfulRobot\Debug','handleError') );
 *  set_exception_handler(array('\ArtfulRobot\Debug','handleException') );
 *
 *  \ArtfulRobot\Debug::setServiceLevel('cterm', \ArtfulRobot\Debug::LEVEL_LOG);
 *  \ArtfulRobot\Debug::log("a line of log", $var);
 *  \ArtfulRobot\Debug::log(">> starting a group/function");
 *  \ArtfulRobot\Debug::log("a line of log", $var);
 *  \ArtfulRobot\Debug::log("<< ending a group/function - will get time in ms");
 *  \ArtfulRobot\Debug::log("!!an important line of log");
 *  \ArtfulRobot\Debug::fatal("Something real bad happened");
 *  \ArtfulRobot\Debug::redirect("/");
 */
class Debug
{
    const LEVEL_LOG       = 1;
    const LEVEL_STACK     = 2;
    const LEVEL_IMPORTANT = 3;
    const LEVEL_FINISH    = 4;

    const VARS_PRINT_R    = 1;
    const VARS_SERIALIZE  = 2;
    const VARS_JSON       = 3;
    // max handled
    static protected $on = 0;
    // services for each level
    static protected $functions = array(
            1 => array(),
            2 => array(),
            3 => array(),
            4 => array(),
        );
    static protected $opts = array(
            'text_depth' => false,
            'text_mem'   => false,
            'text_vars'  => true,
            'slow'       => 0.1,
            'fatal_exit' => true,
            'redirect_intercept' => false,
            'ignore_errors' => 0, // e.g. E_STRICT | E_NOTICE
            'file'       => '/tmp/debug-crash',
            );
    /** services offered */
    static protected $services = array(
            'echo'      => 'serviceEcho',
            'cterm'     => 'serviceColourTerminal',
            'error_log' => 'serviceErrorLog',
            'stderr'    => 'serviceStdErr',
            'store'     => 'serviceStore',
            // these should only be used for fatal
            'outputHtml'=> 'serviceOutputHtml',
            'file'      => 'serviceFile',
            'mail'      => 'serviceMail',
            );

    // variable to hold message and vars during a log() call
    static protected $current;

    /** file handle for file ops */
	static protected $fh;

    /** used to keep stack depth */
	static protected $depth = 0;

    /** holds stack */
    static protected $stack = array();

    /** holds store*/
    static protected $store =array();

    /** holds text for serviceOutputHtml */
    static protected $redirect_preamble;

    static public function log( $msg, $vars=null )/*{{{*/
    {
        // figure out level
        switch (static::$current['prefix'] = substr($msg,0,2)) {
            case 'XX':
            case '->':
                // Fatal or Redirect; either way, finished.
                $level = static::LEVEL_FINISH;
                break;
            case '!!':
                // Important
                $level = static::LEVEL_IMPORTANT;
                break;
            case '<<':
            case '>>':
                // Stack
                $level = static::LEVEL_STACK;
                break;
            default:
                // General
                $level = static::LEVEL_LOG;
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
    static public function setServiceLevel($new_service,$new_level)/*{{{*/
    {
        if (!isset(static::$services[$new_service])) {
            throw new \Exception("Debugger does not know '$new_service'. Knows only " . implode(', ',array_keys(static::$services)));
        }
        // convert service to method name
        $new_service = static::$services[$new_service];

        foreach (static::$functions as $level=>$functions) {
            if ($level<$new_level ) {
                // remove the service from this level
                static::$functions[$level] = 
                    array_diff(static::$functions[$level], array($new_service));
            } else {
                // add the service in (if not already in)
                if (!in_array($new_service,static::$functions[$level])) {
                    if ($new_service=='serviceStore') {
                        // store must go first.
                        array_unshift( static::$functions[$level], $new_service);
                    } else {
                        static::$functions[$level][] = $new_service;
                    }
                }
            }
        }
    }/*}}}*/
    static public function setTextDepth($on=true)/*{{{*/
    {
        static::$opts['text_depth'] = (bool) $on;
    }/*}}}*/
    static public function setTextMem($on=true)/*{{{*/
    {
        static::$opts['text_mem'] = (bool) $on;
    }/*}}}*/
    static public function setTextVars($on=true)/*{{{*/
    {
        static::$opts['text_vars'] = (bool) $on;
    }/*}}}*/
    static public function setSlow($slow=0.1)/*{{{*/
    {
        static::$opts['slow'] = $slow;
    }/*}}}*/
    static public function setFile($filename)/*{{{*/
    {
        if (isset(static::$fh)) {
            static::log("Changing file to $filename");
            fclose(static::$fh);
            unset(static::$fh);
        }
        static::$opts['file'] = $filename;
    }/*}}}*/
    static public function setRedirectIntercept($y=true)/*{{{*/
    {
        static::$opts['redirect_intercept'] = (bool) $y;
    }/*}}}*/
    static public function setFatalExit($y=true)/*{{{*/
    {
        static::$opts['fatal_exit'] = (bool) $y;
    }/*}}}*/
	static public function fatal( $msg, $vars=null )//{{{
	{
        //log a FINISH level message
        self::log( "XX $msg", $vars);

        // what to do now?
        if (static::$opts['fatal_exit']) exit;
        // what now? todo
        return; 
	} //}}}
	static public function redirect($href, $http_response_code=false) // {{{
	{
        if (static::$opts['redirect_intercept']) {
			static::$redirect_preamble = "<a href='$href' style='margin-bottom:1em;color:#07f;display:block;' >Click link to continue:
				$href</a>";
        }

        //log a FINISH level message
        self::log( "->$href");

        // issue normal redirect if we're not intercepting it.
        if (!static::$opts['redirect_intercept']) {
            if     ($http_response_code == '301' ) header($_SERVER["SERVER_PROTOCOL"] . " 301 Moved Permanently");
            elseif ($http_response_code == '302' ) header($_SERVER["SERVER_PROTOCOL"] . " 302 Found but, not the best URL to use!");
            elseif ($http_response_code == '404' ) header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
            elseif ($http_response_code ) header($_SERVER["SERVER_PROTOCOL"] . " $http_response_code");

			/* we close the session because otherwise there's a slim chance that 
			 * the redirected page will run and write its session before this one does.
			 */
			session_write_close();
			header( "Location: $href");
            exit;
        }
        exit;
	} // }}}
	// static public function handleError($errno, $errstr, $errfile, $errline) // {{{
	/** error handler 
	 */
	static public function handleError($errno, $errstr, $errfile, $errline)
	{
		// if error_reporting is turned off for this type of error, do nothing.
		// if (! (error_reporting() & $errno )) return; 

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
        $msg.="Error [$errno] $errstr, on line $errline in file $errfile";

		if ($errno & self::$opts['ignore_errors']) static::log($msg . " (Execution set to continue for this type of error)");
		else self::fatal($msg);
		/* Don't execute PHP internal error handler */
		return true;
	} // }}}
	// static public function handleException($exception) // {{{
	/** exception handler 
	 */
	static public function handleException($exception)
	{
        static::logException($exception);
        exit;// ??? xxx
	} // }}}
	static public function logException($exception)//{{{
	{
		self::log("XX Exception: "
                . ( $exception->getCode() 
                    ? '(code ' . $exception->getCode() . ')' 
                    : '' ) 
                . $exception->getMessage() , $exception->getTrace());
	} // }}}
	static public function loadProfile($profile )//{{{
	{
        if ($profile == 'online') {
            // ... write a file only if we fail (fatal|redirect)
            static::setServiceLevel('file', static::LEVEL_FINISH);
            // ... and put something in error log, too
            static::setServiceLevel('error_log', static::LEVEL_FINISH);

        } elseif ($profile == 'debug') {
            static::setTextDepth();
            // ... write a file always for everything
            static::setServiceLevel('file', static::LEVEL_LOG);
            // ... only use error_log for crashes
            static::setServiceLevel('error_log', static::LEVEL_FINISH);

        } elseif ($profile == 'debug_important') {
            // ... write a file always for everything
            static::setServiceLevel('file', static::LEVEL_IMPORTANT);
            // ... only use error_log for crashes
            static::setServiceLevel('error_log', static::LEVEL_FINISH);

        } elseif ($profile == 'unsafe') {
            // directly output to screen

            // remember everything and output everything
            static::setServiceLevel('store', static::LEVEL_LOG);
            static::setServiceLevel('echo', static::LEVEL_LOG);
            // crashes result in error_log and html
            static::setServiceLevel('error_log', static::LEVEL_FINISH);
            static::setServiceLevel('outputHtml', static::LEVEL_FINISH);
        } else {
            throw new \Exception("unknown debug profile: $profile");
        }


	} // }}}

    static protected function serviceEcho()/*{{{*/
    {
        echo static::getText() . "\n";
    }/*}}}*/
    static protected function serviceColourTerminal()/*{{{*/
    {
        $deets = static::getText(false) ;
        echo "\033[1;30m" . $deets['depth'] . "\033[0m"
            ."\033[1;32m" . $deets['mem'] . "\033[0m"
            . ( static::$current['level'] >= static::LEVEL_IMPORTANT 
              ? "\033[1;31m"
              : "\033[1;37m" )
            . $deets['msg'] . "\033[0m"
            .$deets['mem'] . "\n";

    }/*}}}*/
    static protected function serviceErrorLog()/*{{{*/
    {
        error_log(static::getText());
    }/*}}}*/
    static protected function serviceStdErr()/*{{{*/
    {
        fputs(STDERR, static::getText()."\n");
    }/*}}}*/
    static protected function serviceFile()/*{{{*/
    {
        fwrite(static::getFH(), static::getText()."\n");
    }/*}}}*/
    static protected function serviceStore()/*{{{*/
    {
        // store this log
        static::$store[] = static::$current;
        // freeze vars if object because this might change
        if ( is_object( static::$store['vars'] ) )
            static::$store['vars']  = serialize(static::$store['vars'] );

        // fatal? Add a trace
        if (static::$current['prefix'] == 'XX') {
            static::$store[] = array(
                    'msg'=>'Backtrace:',
                    'prefix'=>'backtrace',
                    'mem'=>'',
                    'depth'=>'',
                    'vars'=>debug_backtrace() );
        }
    }/*}}}*/
    static protected function serviceOutputHtml()/*{{{*/
    {
        // output html version of stored log
        // todo make it clear that this depends on serviceStore

        $html = "<div style='border:solid 1px red;padding:0.5em;' >"
            . static::$redirect_preamble;
        $depth = 0;
        foreach(static::$store as $row) {


            if ($row['level']>=static::LEVEL_IMPORTANT) {
                $line = "<div style='color:#800;border-left:solid 10px #d00;padding-left:8px;'>";   
            } elseif ($row['level']==static::LEVEL_LOG) {
                $line = "<div style='color:#888;'>";   
            } else {
                $line = "<div>"; 
            }
            $line .= htmlspecialchars($row['msg'])
                . "<div style='float:right;margin-left:1em;color:#888;'>$row[mem]</div>"
                . ($row['vars']
                    ? "<div style='border:dashed 1px #888;color:#888;'><pre>"
                      . htmlspecialchars(print_r($row['vars'],1))
                      . "</pre></div>"
                    : '');
                
            if ($row['prefix'] == '>>') {
                $depth++;
                $line .= "<div style='border:solid 1px #eee;padding-left:1em;margin-bottom:0.3em;'>";
            } elseif ($row['prefix'] == '<<') {
                $line .= "</div></div>";
                $depth--;
            } elseif ($row['prefix'] == 'backtrace') {
                $line .= str_repeat("</div></div>",$depth);
                $depth =0;
            } else {
                $line .= "</div>";
            }
            $html .= $line;
        }
        $html .= "</div>";

        echo $html;
    }/*}}}*/
    static protected function getText( $implode=true )/*{{{*/
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

            static::$current['text']['msg'] = static::$current['msg'] . "\n";

            if (! ($_ = static::$opts['text_vars'])) {
                $vars = '';
            } elseif ($_ == static::VARS_PRINT_R) {
                $vars = print_r(static::$current['vars'],1);
            } elseif ($_ == static::VARS_SERIALIZE) {
                $vars = serialize(static::$current['vars']);
            } elseif ($_ == static::VARS_JSON) {
                $vars = json_encode(static::$current['vars']);
            }
            static::$current['text']['vars'] = $vars;

            // fatal? Add a trace
            if (static::$current['prefix'] == 'XX') {
                foreach (debug_backtrace() as $_) {
                    static::$current['text']['vars'] .= " Backtrace:\n" . print_r($_,1) . "\n";
                }
            }
        }
        if ($implode) return implode('', static::$current['text']);
        return static::$current['text'];
    }/*}}}*/
    static protected function getFH()/*{{{*/
    {
        if (!isset(static::$fh)) {
            unlink(static::$opts['file']);
            static::$fh = fopen(static::$opts['file'],'a');
            fwrite(static::$fh, "Debug log at " . date('H:i:s d M Y') . "\n\n");
        }
        return static::$fh;
    }/*}}}*/
    static protected function trackLevel()/*{{{*/
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
                static::$current['msg'] = "SLOW: " .  static::$current['msg']; 
            }
            static::$current['depth'] = 0;
            static::$depth--;
        }
    }/*}}}*/
}/*}}}*/


