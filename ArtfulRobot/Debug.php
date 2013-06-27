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
 *  \ArtfulRobot\Debug::log("I need to use <b>html</b> in my message so I end with <html>");
 *  \ArtfulRobot\Debug::fatal("Something real bad happened");
 *  \ArtfulRobot\Debug::redirect("/");
 *
 *
 *  loadProfile sets a lot of stuff at once.
 *  All profiles include error_log on finish.
 *  online: file on finish only.
 *  debug: file for everything
 *  debug_important:  file for important only
 *  unsafeHTML: file everything, finish outputs html. Intercept redirect.
 *  cterm: output everything to colour terminal
 *  unsafe: output everything using echo in realtime. 
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

    
    // main use functions
    //public static function log( $msg, $vars=null )/*{{{*/
    /** Main logging method
     */
    public static function log( $msg, $vars=null )
    {
		static::$current = array();
        // figure out level
        $map = array(
            '->' => static::LEVEL_FINISH_REDIRECT,
            '$$' => static::LEVEL_FINISH_NATURAL,
            'XX' => static::LEVEL_FINISH_FATAL,
            '!!' => static::LEVEL_IMPORTANT,
            '>>' => static::LEVEL_STACK,
            '<<' => static::LEVEL_STACK);
        if (array_key_exists(static::$current['prefix'], $map)) {
            $level = $map[static::$current['prefix']];
        } else {
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
	public static function finish( $msg='Successful finish', $vars=null )//{{{
	{
        self::log( "$$ $msg", $vars);
	} //}}}
	public static function redirect($href, $http_response_code=303) // {{{
	{
        foreach (static::$functions[static::LEVEL_FINISH_REDIRECT] as $_) {
            if ($_ == 'serviceInterceptRedirect') {
                static::$redirect_preamble = "<a href='$href' style='margin-bottom:1em;color:#07f;display:block;' >Click link to continue:
                    $href</a>";
            }
        }

        // log it and take action (e.g. output_html)
        static::log( '->Redirect [' . $http_response_code. "] to $href ");

        // issue normal redirect if we're not intercepting it.
        if (!static::$opts['redirect_intercept']) {
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
	public static function fatal( $msg='fatal exit', $vars=null )//{{{
	{
        // log 
        self::log( "XX $msg", $vars);
        // we must now exit.
        exit;
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

		if ($errno & self::$opts['ignore_errors']) static::log($msg . " (Execution set to continue for this type of error)");
		else static::fatal($msg);
		/* Don't execute PHP internal error handler */
		return true;
	} // }}}
	// public static function handleException($exception) // {{{
	/** exception handler 
	 */
	public static function handleException($exception)
	{
        static::logException($exception);
        exit;// ??? xxx
	} // }}}
	public static function logException($exception)//{{{
	{
		self::log("XX Exception: "
                . ( $exception->getCode() 
                    ? '(code ' . $exception->getCode() . ')' 
                    : '' ) 
                . $exception->getMessage() , static::getTrace($exception->getTrace()));
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
    // other config/settings
	public static function loadProfile($profile )//{{{
	{
		// reset
    	static::$functions = array(
            1 => array(),
            2 => array(),
            3 => array(),
            4 => array(),
            5 => array(),
            6 => array(),
        );

        if ($profile == 'online') {
            // Don't do anything unless we have fatal error, 
            // in which case file and error_log
            static::setFatalServices('file','error_log');

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
            static::setRedirectServices('intercept_redirect','file','output_html');
            // templates can access log html at finish.
            static::setFinishServices('allow_html','file');

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
    public static function setSlow($slow=0.1)/*{{{*/
    {
        static::$opts['slow'] = $slow;
    }/*}}}*/
    public static function setFile($filename, $append=false)/*{{{*/
    {
        if (isset(static::$fh)) {
            static::log("Changing file to $filename");
            fclose(static::$fh);
            unset(static::$fh);
        }
        static::$opts['file'] = $filename;
        static::$opts['file_append'] = (bool) $append;
    }/*}}}*/
    // services
    protected static function serviceEcho()/*{{{*/
    {
        echo static::getText() . "\n";
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

        // fatal? Add a trace
        if (static::$current['prefix'] == 'XX') {
            static::$store[] = array(
                    'msg'=>'Backtrace:',
					'level' => self::LEVEL_ABNORMAL_EXIT,
                    'prefix'=>'backtrace',
                    'mem'=>'',
                    'depth'=>'',
                    'vars'=>static::getTrace() );
        }
    }/*}}}*/
    protected static function serviceAllowHtml()/*{{{*/
    {
        static::$opts['allow_get_html'] = true;
    }/*}}}*/
    protected static function serviceOutputHtml()/*{{{*/
    {
        // output html version of stored log
        echo static::getHtml();
    }/*}}}*/
    protected static function serviceInterceptRedirect()/*{{{*/
    {
        // dummy service, picked up by redirect() instead.
    }/*}}}*/
    // internals
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

            // fatal? Add a trace
            if (static::$current['prefix'] == 'XX') {
                foreach (static::getTrace() as $_) {
                    static::$current['text']['vars'] .= "\nBacktrace:{{{\n" . print_r($_,1) . "}}}";
                }
            }
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
            static::$depth--;
        }
    }/*}}}*/
    protected static function getTrace($trace=null)/*{{{*/
    {
		// remove ourselves from the trace.
		if (!$trace) $trace = debug_backtrace();

		$penultimate = false;
		while (!empty($trace[0]) && ($trace[0]['class'] == 'ArtfulRobot\Debug')) {
			$penultimate=array_shift($trace);
		}
		if($penultimate) {
			array_unshift($trace, $penultimate);
		}
		return $trace;
    }/*}}}*/
    protected static function setEndServices($new_services,$new_level)/*{{{*/
    {
        // validate services
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
.arl-debug-var-toggle:before { content: 'Show Vars'; font-size:10px; }
.arl-debug-vars.expanded .arl-debug-var-toggle:before { content: 'Hide Vars' }
.arl-debug-vars pre {display:none; background-color:#faf8f0;padding:0 7px;margin:0 6px 6px;border:solid 1px #aaa;border-radius:3px 3px 3px 3px;font-size:10px;color:#242;}
.arl-debug-vars.expanded>pre {display:block;}
.arl-debug-msg {cursor:pointer;}
.arl-debug-msg:hover {background-color:#e0f8f8;color:black;}
.arl-debug-selected { background-color:#e0f8f8;color:black;border-left:solid 6px #28f; box-shadow: -2px 4px 6px 2px;margin-left:0;}
</style>";

		$css = strtr($css, array(
					"\n"=>" ",// javascript can't handle new lines...
					'"' =>'\\"')); // need to quote "

		$html = "
<script>
function arlDebugUI() {
	if (!window.arlDebugCss) jQuery(\"$css\").appendTo('head');
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
	jqTag.onload = arlDebugUI;
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
                . (!empty($row['vars'])
                    ? "<div class='arl-debug-vars'><div class='arl-debug-var-toggle'></div><pre>"
                      . htmlspecialchars(print_r($row['vars'],1))
                      . "</pre></div>"
                    : '');
                
            if ($row['prefix'] == '>>') {
                $depth++;
                $line .= "<div class='arl-debug-stack-start'>";
            } elseif ($row['prefix'] == '<<') {
                $line .= "</div></div></div>"; // close self, and 2 outers.
                $depth--;
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
}/*}}}*/


