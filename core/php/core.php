<?php
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

//class ARL_Debug/*{{{*/
/** ARL_Debug class provides debugging facility
 *
 *  This may be used for all debugging needs. See doc.
 *
 *  It is used internally by all Artful Robot Libraries code.
 *  
 *  It is turned off by default.
 *
 *  Synopsis:
 *
 *  set_error_handler( array('ARL_Debug','handle_error') );
 *  set_exception_handler(array('ARL_Debug','handle_exception') );
 *
 *  ARL_Debug::running = true;
 *  ARL_Debug::running = true;
 */
class ARL_Debug
{
	static public    $errors_to_ignore ;
	static protected $running          = false; // class does nothing if not enabled.
	static protected $init 		   	   = false; // class is initialised
	static protected $top_only 		   = true; // drop all data unless TOP'ed
	static protected $dump_file		   = false; // just write to text file only
	static protected $error_log		   = false; // call error_log for each debug call (emergency debugging)
	static protected $stderr		   = false; // write error_log output to stderr (CLI debugging)
	static protected $silent 		   = 0; // whether to ever output to browser (or fetch)
	static protected $file 			   = 0; // whether to write to file
	static protected $format		   = 'html'; // no other formats supported yet
	static protected $slow             = 0.2; // seconds before something is considered slow
	static protected $preamble         = ''; // html for top of debug message.

	static protected $log              = array();
	static protected $last_parent      = 0;
	static protected $top_level        = 0;
	static protected $htmlspecialchars = 1; // by default; override on ARL_Debug::log()
	static protected $depth            = 1; // used by error_log for mo
	static protected $iniMemoryLimit;

	static private function init($reset = 0) // {{{
	{
		if (self::$init) return;
		self::$init = true;

		self::$errors_to_ignore = E_NOTICE + E_STRICT + E_WARNING;
		// figure out memory limit in bytes
		$memLimit = ini_get('memory_limit');
		$number = preg_replace('/[^0-9.]/','',$memLimit);
		$multiplier = strtoupper(preg_replace('/[0-9.]/','',$memLimit));
		if ($multiplier == 'K') $multiplier = 1024;
		elseif ($multiplier == 'M') $multiplier = 1024*1024;
		else $multiplier = 1;
		self::$iniMemoryLimit = $number * $multiplier;


		if ( ! $reset) 
		{
			$state = self::$running;
			self::$running = true;
			self::log('TOP Start');
			self::$running = $state;
		}
	} // }}}
	static public function set_dump_file($dump_file=null) // {{{
	{
		$dump_file = ($dump_file?$dump_file:null);

		if ($dump_file===null) 
		{
			self::$dump_file = null;
			return;
		}

		if (!file_exists(dirname($dump_file))) throw new Exception(
				"Directory does not exist: '$dump_file' ");
	
		self::$dump_file = $dump_file;

		// backup old logs so we can reuse the file.
		if (file_exists(self::$dump_file))
		{
			$i=1;
			while(file_exists(self::$dump_file . ".$i")) $i++;
			rename(self::$dump_file, self::$dump_file . ".$i");
		}
	} // }}}
	static public function log($t, $vars=null, $applyhtmlspecialchars=null)   //{{{
	{
		self::init();
		// debugger can be turned off.
		if ( ! self::$running ) return true;

	/*
	static $lastTime=0;
	if ( substr($t, 0,3) != 'TOP') return true;
	if ($lastTime==0) $lastTime = self::getmicrotime();
	echo sprintf('%0.2f', self::getmicrotime() - $lastTime) . htmlspecialchars($t) . "<br />";
	$lastTime = self::getmicrotime();
	return true;
	 */
		// if we're filling up the memory too much, (todo: write out to file and ) clear. 
		$limit = 0.6;
		if ( $turnItOff = (memory_get_usage()/self::$iniMemoryLimit >$limit) )
			$t = "TOP XXX Memory usage exceeded $limit, debugger turned off! XXX";


		if (self::$error_log) 
		{
			$error_log_msg = sprintf("Mem: %0.1f/%0.1fMb ", memory_get_usage()/1024/1024, self::$iniMemoryLimit/1024/1024 )
				. str_repeat( '-',  self::$depth) . '|'
				. $t . ($vars!==null?" vars follow:\n". serialize($vars):'');
			error_log( $error_log_msg );
			if (self::$stderr) fwrite(STDERR, $error_log_msg);
			unset($error_log_msg);
		}
		if ($applyhtmlspecialchars===null) $applyhtmlspecialchars = self::$htmlspecialchars;


		if (self::$dump_file)
		{
			if ($vars) {
				ob_start(); var_dump( $vars ); $vars = (ob_get_contents()); ob_end_clean();
			}

			file_put_contents(self::$dump_file, 
					strtr($t ,array('>>'=>'{{{','<<'=>'}}}'))
					."\n". 
					($vars===null?'': 
						"\t" . str_replace("\n","\n\t",$vars) 
						."\n")
				,FILE_APPEND);
			// exit here 
			return;
		}

		// prepare vars html {{{
		if ( $vars !==null )
		{
			ob_start();                    // Start output buffering
			var_dump( $vars );
			$vars = htmlspecialchars(ob_get_contents()); // Get the contents of the buffer
			ob_end_clean();                // End buffering and discard
		} 
		// }}}

		// parse initial keyword {{{
		$startBlock	=(substr($t,0,2)=='>>');
		$endBlock	=(substr($t,0,2)=='<<');
		$vimportant =(substr($t,0,2)=='!!');
		$important 	=(substr($t,0,1)=='!');
		$dull	 	=(substr($t,0,3)=='...');
		$topStart 	=(substr($t,0,4)=='TOP{');
		$topEnd 	=(substr($t,0,4)=='TOP}');
		$top	 	=(substr($t,0,3)=='TOP');
		$encaps	 	=(substr($t,0,2)=='<>');

		// remove keywords
		if ($startBlock || $endBlock || $vimportant || $encaps )
			$t=substr($t,2);
		elseif ($topStart || $topEnd )
			$t=substr($t,4);
		elseif ($dull || $top )
			$t=substr($t,3);
		elseif ($important) $t=substr($t,1);

		// }}}

		// figure out top setting. {{{ 
		$top_level = & self::$top_level;
		if ($topStart) $top_level++;
		if ($topEnd && $top_level) $top_level--;
		$myTopSetting = $top || $top_level;  
		if ((!$myTopSetting) && self::$top_only) return true; // if top_only set, ignore everything else 
		// }}}

		$newRowId = sizeof( self::$log );
		self::$log[] = array( 'top'=>0, 'time'=>self::getmicrotime(), 'class'=>'normal', 'scope'=>'', 'message'=>'', 'vars'=>'','parent'=>self::$last_parent,'lastChild'=>0 );
		$newRow = & self::$log[$newRowId];
		$newRow['top'] = $myTopSetting;
		$newRow['vars'] = $vars;

		if ($applyhtmlspecialchars) $newRow['message']=htmlspecialchars(trim($t));
		else $newRow['message']=trim($t);

		$newRow['message'] .= sprintf(' <span class="right" >%0.1fMb used %0.0f%%</span>', memory_get_usage()/1024/1024 , 100*memory_get_usage()/self::$iniMemoryLimit);


		// figure out scope {{{
		$tmp='';
		$trace = debug_backtrace();
		while ($backtrace=array_shift($trace))
		{
			$scope=ARL_Array::value('function',$backtrace);
			$class=ARL_Array::value('class',$backtrace);
			if ($class) $scope = $class . ARL_Array::value('type',$backtrace)  . $scope;
			if ($scope == 'debug' || $scope == 'ARL_Debug::log') continue;
			break;
		}
		$newRow['scope'] = $scope;
		//}}}

		// hierarchy and classes {{{
		if ($endBlock) // return false if << before >> (used by myexit)
		{
			if ($newRow['parent']==0) return false;
			// find parent of our parent.
			$tmp                  = & self::$log[$newRow['parent']];
			$tmp['lastChild']     = $newRowId;
			self::$last_parent = $tmp['parent'];
			$newRow['class']      = 'scope_end';
			if (self::$error_log) {
				$error_log_msg = sprintf("Mem: %0.1f/%0.1fMb ", memory_get_usage()/1024/1024, self::$iniMemoryLimit/1024/1024 )
					. str_repeat( '-',  self::$depth) . '|'
					. "last line in $tmp";
				error_log( $error_log_msg );
				if (self::$stderr) fwrite(STDERR, $error_log_msg);
				unset($error_log_msg);
			}
			unset($tmp); // unlink reference
			self::$depth--;

		}
		elseif ($startBlock)
		{
			self::$depth++;
			$newRow['class']      = 'ooo';
			self::$last_parent = $newRowId; //following calls will be children of this one.
			if (! $newRow['scope']) $newRow['scope'] = 'Scope: ' . $newRow['message'];
		}
		elseif ($vimportant ) $newRow['class'] = 'ooo';
		elseif($important   ) $newRow['class'] = 'oh';
		elseif($dull        ) $newRow['class'] = 'shhh';
		// }}}


		if ( $turnItOff ) self::$running = false;  

		return true;
	}//}}}
	static public function set_on($v=true)   //{{{
	{
		self::$running = (bool) $v;
		self::init();
	}//}}}
	static public function set_silent($v)   //{{{
	{
		self::init();
		self::$silent = (bool) $v;
	}//}}}
	static public function set_error_log($v)   //{{{
	{
		self::init();
		self::$error_log = (bool) $v;
	}//}}}
	static public function set_top_only($v)   //{{{
	{
		self::init();
		self::$top_only = (bool) $v;
	}//}}}
	static public function set_stderr($v)   //{{{
	{
		self::init();
		self::$stderr = (bool) $v;
	}//}}}
	static public function set_file($v)   //{{{
	{
		self::init();
		if (! $v) 
		{
			self::$file = false;
			self::log("TOP set_file OFF");
		}
		elseif ($v===true || $v===1) 
		{
			self::$file = true;
			self::log("TOP set_file ON");
		}
		else
		{
			// ensure timestamp is in there (somewhere)
			// otherwise too much rik of overwriting others.
			if (strpos($v,'%d')===false) $v = "%d_$v";
			self::$file = $v;
			self::log("TOP set_file ON, naming files: $v");
		}
	}//}}}
	//static public function fatal( $t='!!myexit called',$vars=null,$applyhtmlspecialchars=true ) // {{{
	/** fatal errors, equivalent to exit() 
	 */
	static public function fatal( $t='!!myexit called',$vars=null,$applyhtmlspecialchars=true ) 
	{
		// get out of all debug depths so that the last message is always immediately visible.
		if (!self::$running) self::$running=true;//must be ON for this to work!
		if (self::$top_only) self::$top_only = false; // turn this off

		self::log('!! Whooooah there!');
		self::log( "TOP{ $t" ,null,$applyhtmlspecialchars);

		// make table for backtrace
		$cols=explode('|','file|line|function|class');

		$tmp = "<table border=\"1\"><tr>";
		foreach($cols as $f) $tmp.= "<th>$f</th>";

		if (self::$dump_file) 
		{
			ob_start(); debug_print_backtrace(); $backtrace = (ob_get_contents()); ob_end_clean();
			self::log("FATAL: Backtrace:\n\t" . str_replace("\n","\n\t", $backtrace));
			exit();
		}
		$backtrace = debug_backtrace();

		foreach ($backtrace as $row)
		{
			$tmp .= "<tr>";
			foreach($cols as $f) $tmp.= "<td>" . htmlspecialchars(ARL_Array::value($f,$row)) . "</td>";
			$tmp .= "</tr>";
		}
		$tmp .= "</table>";

		self::log("TOP}Backtrace table: $tmp",null,false);
		while (self::log('<<')) {}; // (will fail if not self::$running)

		self::legacy_api( 'print_full' );	
		exit();
	} //}}}
	static public function redirect_and_exit($href, $http_response_code=false) // {{{
	{
		self::init();
		// just sends location header and exits, but does so in a debuggable way!
		self::log("TOP redirect_and_exit() called to: '$href'");
		if     ($http_response_code == '301' ) header($_SERVER["SERVER_PROTOCOL"] . " 301 Moved Permanently");
		elseif ($http_response_code == '302' ) header($_SERVER["SERVER_PROTOCOL"] . " 302 Found but, not the best URL to use!");
		elseif ($http_response_code == '404' ) header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
		elseif ($http_response_code ) header($_SERVER["SERVER_PROTOCOL"] . " $http_response_code");

		if ( self::$silent ) 
		{
			/* we close the session because otherwise there's a slim chance that 
			 * the redirected page will run and write its session before this one does.
			 */
			session_write_close();
			header( "Location: $href");
			echo "Redirecting to <a href=\"$href\" >$href</a>";
			self::fatal();
		}
		else 
		{
			// debugging is on, present user nice message instead of just the debug stuff
			self::$preamble = "<a href='$href' class='redirectlink' >Debugger intercepted redirect. Click to continue<br />
				Link: $href</a>";
			self::fatal("TOP would redirect to: <a href=\"$href\">$href</a>.</p>",null,false);
		}
		// end of all script execution.
	} // }}}
	// static public function handle_error($errno, $errstr, $errfile, $errline) // {{{
	/** error handler 
	 */
	static public function handle_error($errno, $errstr, $errfile, $errline)
	{
		self::init();
		// if error_reporting is turned off for this type of error, do nothing.
		if (! (error_reporting() & $errno )) return; 

		switch ($errno) {
		case E_USER_ERROR:
		case E_ERROR:
			$msg="TOP Fatal Error [$errno] $errstr, on line $errline in file $errfile";
			break;

		case E_USER_WARNING:
		case E_WARNING:
			$msg="TOP Warning Error [$errno] $errstr, on line $errline in file $errfile";
			break;

		case E_USER_NOTICE:
		case E_NOTICE:
			$msg="TOP Notice Error [$errno] $errstr, on line $errline in file $errfile";
			break;

		case 2048: // E_STRICT
			$msg="TOP Strict Error [$errno] $errstr, on line $errline in file $errfile";
			break;

		default:
			$msg= "TOP Unknown error type: [$errno] $errstr<br />\n";
			break;
		}
		if ($errno & self::$errors_to_ignore) self::log($msg . " (Execution set to continue for this type of error)");
		else self::fatal($msg);
		/* Don't execute PHP internal error handler */
		return true;
	} // }}}
	// static public function handle_exception($exception) // {{{
	/** exception handler 
	 */
	static public function handle_exception($exception)
	{
		self::init();
		// make table for backtrace
		$cols=explode('|','file|line|function|class');

		if(0){
			$tmp = "<table border=\"1\"><tr>";
			foreach($cols as $f) $tmp.= "<th>$f</th>";
			$backtrace = $exception->getTrace();
			foreach ($backtrace as $row)
			{
				$tmp .= "<tr>";
				foreach($cols as $f) $tmp.= "<td>" . htmlspecialchars(ARL_Array::value($f,$row)) . "</td>";
				$tmp .= "</tr>";
			}
			$tmp .= "</table>";
		}
		self::log("TOP Uncaught Exception: ". ( $exception->getCode() ? '(code ' . $exception->getCode() . ')' : '' ) . $exception->getMessage() , $exception->getTraceAsString());
		self::fatal("FATAL: Uncaught exception");
	} // }}}
	// static public function backtrace() // {{{
	/** insert backtrace
	  * @param $exception Exception|string message
	 */
	static public function backtrace($exception=null)
	{
		self::init();

		if ($exception instanceof Exception) 
		{
			$backtrace = debug_backtrace();
			$row = array(
				'line' => $exception->getLine(),
				'file' => $exception->getFile());
			array_unshift($backtrace,$row);
			$message = $exception->getMessage();
		}
		else 
		{
			$backtrace = $exception->getTrace();
			$message = $exception;
		}
		if (! $message) $message = "Backtrace";

		// make table for backtrace
		$cols=explode('|','file|line|function|class');

		$tmp = "<table border=\"1\"><tr>";
		foreach($cols as $f) $tmp.= "<th>$f</th>";
		foreach ($backtrace as $row)
		{
			$tmp .= "<tr>";
			foreach($cols as $f) $tmp.= "<td>" . htmlspecialchars(ARL_Array::value($f,$row)) . "</td>";
			$tmp .= "</tr>";
		}
		$tmp .= "</table>";

		self::log("TOP $message".$tmp,$backtrace,false);
	} // }}}
	static public function legacy_api( $command , $args=false ) // {{{
	{
		self::init();
	/*
		commands:
		1. fetch - return chunk of html  if not debug[silent] \_  both of these write a full 
		2. print - output full html page if debug[silent]     /   html file if debug[file] set
		3. [not ]silent set debug[silent]
		4. [not ]file   set debug[file]
		   if args given, this is the filename used.
		   %d in the filename is replaced with the timestamp
		   if no %d, it is pre-pended.
		5. off | on - start/stop debugging. Future calls to debug do nothing.
		   off returns the previous value
		6. get_file returns the value of file, e.g. 0|1|filename
	 */

		$command = strtolower($command);
		if	   ($command == 'not silent' ){ self::set_silent(false); return true ; }
		elseif ($command == 'silent' )    { self::set_silent(true);  return true ; }
		elseif ($command == 'not stderr' ){ self::set_stderr(false); return true ; }
		elseif ($command == 'stderr' )    { self::set_stderr(true);  return true ; }
		elseif ($command == 'not file' )  { self::set_file(false); return true ; }
		elseif ($command == 'file' )      { self::set_file($args); return true ; }
		elseif ($command == 'not error_log' )   { self::set_error_log(false); return true ; }
		elseif ($command == 'error_log' ) { self::set_error_log(true); return true ; }
		elseif ($command == 'top_only' )   { self::set_top_only(true); return true ; }
		elseif ($command == 'not top_only' )     { self::set_top_only(false); return true ; }
		elseif ($command == 'set slow' )  { self::$slow = $args;debug('Debugging set slow to ' . $args ); return true ; }
		elseif ($command == 'on' )  	  { self::set_on(true); return 'on';}
		elseif ($command == 'off' )  	  { self::set_on(false); return 'off';}
		elseif ($command == 'file' )      { self::set_file($args); return true; }
		elseif ($command == 'get_file' )  { return self::$file; }

		// if we're turned off, do nothing more. xxx really?
		//	if (! self::$running ) return;

		if (self::$format == 'html' || 1)
		{
			$chunk = self::report_html();
			$timestamp = explode(" ",microtime()); 
			$timestamp= substr($timestamp[0],1,3); // microseconds to 2dp 0.1234 => .12
			$timestamp = date('Y-m-d\\TH.i.s') . $timestamp;
			// append part of session id
			if (session_id()) $timestamp .= '_' . substr(session_id(),0,4);

			if (is_string(self::$file)) $filename = 
				strtr( strtr(self::$file,'/=?&\\:;, ','_')
				, array( '%d' => $timestamp ));

			else $filename = $timestamp . strtr($_SERVER['REQUEST_URI'],'/=?&\\:;, ','_');

			if (self::$file) // {{{
			{
				$f = fopen( $_SERVER['DOCUMENT_ROOT'] . "/logs/$filename.html" ,'w' );
				fwrite ($f, self::template($filename, $chunk));
				fclose( $f);
			} // }}}
			if (self::$silent) return ;
			if ($command =='fetch') return $chunk;
			elseif ($command =='fetch_full') return "$chunk</body></html>";
			elseif ($command == 'print' ) echo self::template('', $chunk); 
			elseif ($command == 'print_full' ) echo self::template('', $chunk); 
		}
		else
		{
			self::$log='removed';
			var_dump(self);
			exit("Format not known");
		}
	}//}}}

	static private function report_html( ) // {{{
	{
		self::init();

		// make log
		$last_parent =0 ; $depth =1;

		//	return generic_array_to_table( self::$log ); 

		$html = '<ol>'; $top = '';
		$ends = array();
		foreach (self::$log as $key=>$row)
		{
			if ($key == 0) continue;

			if ($row['vars']) 
			{
				$arrow 		= "&rarr;";
				$clickable  = ' clickable';
				$dataVars 	= "<pre style=\"display:none\" class=\"data\" >{$row['vars']}</pre>";
				$dataClick	= "onclick=\"debugToggle(this,1)\" ";
			}
			else $clickable = $arrow = $dataClick = $dataVars = '';

			if ($row['lastChild'])
			{
				// section start

				// took how long?
				$took = self::$log[$row['lastChild']]['time'] - $row['time'];
				$took = sprintf("<span class=\"right %s\">%0.2fs</span>", 
					($took>self::$slow?'slow':''),
					$took);

				$li = "<li %debugid class=\"$row[class]\" >"
					. '<h2 onclick="debugToggle(this,1,1)">&rarr;'
					. $row['scope'] . ' %reveal'
					. "$took</h2>";

				$li .= "<div class='start' ><p class=\"normal$clickable\" $dataClick >$arrow<em>Start:</em> <strong>$row[message]</strong></p>$dataVars</div>";

				if (self::$log[$row['lastChild']]['vars']) 
				{
					$tmp1 = "&rarr;";
					$tmp2 = "<pre style=\"display:none\" class=\"data\" >" . self::$log[$row['lastChild']]['vars']."</pre>";
					$tmp3 = "onclick=\"debugToggle(this,1)\" ";
				}
				else $tmp1=$tmp2=$tmp3='';
				$clickable = ($tmp1?' clickable':'');
				$ends[$depth] = "<div class='end' ><p class=\"normal$clickable\" $tmp3 >$tmp1<em>End&nbsp;&nbsp;:</em> " 
					. self::$log[$row['lastChild']]['message']
					."</p>$tmp2</div>";

				$li .= "<ol style=\"display:none\" >";
				$last_parent = $key;
				$depth++;
			}
			elseif ( $row['class'] == 'scope_end' )
			{
				// this closes a section
				$li = "<li  %debugid ><p class=\"$row[class]$clickable\" $dataClick>$arrow$row[message] %reveal</p>$dataVars</li>"
					. "</ol>";
				$depth--;
				$li .= $ends[$depth] . "</li>";

				$last_parent = $row['parent'];
			}
			else
			{
				$li = "<li  %debugid ><p class=\"$row[class]$clickable\" $dataClick>$arrow$row[message] %reveal</p>$dataVars</li>";
			}

			$html .= strtr( $li, array(
				'%debugid'=>"id=\"debug_id_$key\" ",
				'%reveal' =>(  $row['parent']
				?"<button class=\"command\" onclick=\"debugRevealParents(this,event)\" >Highlight Parents</button>" 
				:'')));
			if ($row['top'] && ! self::$top_only) 
			{
				$top .= strtr( $li, array(
					'%debugid'=>"",
					'%reveal' =>"<button class=\"command\" onclick=\"debugReveal($key)\" >Locate in log</button>" ));
			}
		}
		while ($depth--) $html .= '</li></ol>';

		if ($top && ! self::$top_only) $top = "<h1>Attention</h1><ol>$top</ol>\n<h1>Full Log</h1>";
		$html = "<div class=\"debug\">$top$html</div>";

		$html = <<<EOF
<script type="text/javascript" >
window.debugToggle = function (obj, parentHops, heading)
{
	var t1 = obj;
	for (;parentHops>0;parentHops--) t1 = t1.parentNode;
	x=t1;
	t1 = t1.lastChild;
	if (heading) t1 = t1.previousSibling;

	if (t1.style['display']=='none')
	{
		var remove = (obj.innerHTML.substr(0,1) == '&')?5:1;
		obj.innerHTML = '&darr;' + obj.innerHTML.substr(remove) ;
		t1.style['display']='block';
	}
	else 
	{
		var remove = (obj.innerHTML.substr(0,1) == '&')?5:1;
		obj.innerHTML = '&rarr;' + obj.innerHTML.substr(remove) ;
		t1.style['display']='none';
	}
}
window.debugReveal = function(id) // {{{
	{
		// reveal
		var i = document.getElementById( 'debug_id_' + id );
		if (! i) return;

		for ( var t = 0; t<4 ; t++ )
		{
			setTimeout( function(){ i.style['backgroundColor']='red'; } , 200*t );
			setTimeout( function(){ i.style['backgroundColor']='';} , 200*t + 100);
		}

		var p = i;
		while (p.className!='debug')
		{
			p.style['display'] = 'block';
			p = p.parentNode;
		}

		window.location.replace('#debug_id_' + id);

	} // }}}

window.debugRevealParents =	function(p,e) // {{{
	{
		var bg = 'unknown';

		while (p.className!='debug' && p.tagName!='LI') { p = p.parentNode; }

		if (p.firstChild.style && p.firstChild.style['backgroundColor']) bg = '';
		else bg = '#af9';

		while (p.className!='debug' )
		{
			if (p.tagName=='LI') p.firstChild.style['backgroundColor'] = bg;
			p = p.parentNode;
		}
		e.stopPropagation();
	} // }}}
</script>
$html
EOF;
		return $html;
	}//}}}
	static private function getmicrotime(){ //{{{
		list($usec, $sec) = explode(" ",microtime()); 
		return ((float)$usec + (float)$sec); 
/*$_starttime = self::getmicrotime();
echo "finished! Took " . ( self::getmicrotime() - $_starttime ) ."s";*/
	}// }}} 
	static private function template($title,$html,$full=false)/*{{{*/
	{
		$css = <<<HTML
	<style type='text/css' >
/* debug box {{{ */
div.debug {
	display:block;
	text-align:left;
	font-family: "Bitstream Vera Sans Mono",fixed,courier;
	font-size:small;
	color:black;
	background:#eef;
}
div.debug p { margin:0 }
div.debug h1 { margin:0 0 0.5em 0; font-size:1.2em; font-weight:bold; background-image:none; color:white; background-color:#447;font-family:sans-serif; }
div.debug h2 { 
	margin:0; font-size:1.1em; font-weight:bold; cursor:pointer; background-image:none; color:white; background-color:#447;font-family:sans-serif;  
	-moz-border-radius-topleft: 4px;
	-moz-border-radius-topright: 4px;
	-moz-border-radius-bottomleft: 0;
	-moz-border-radius-bottomright: 0;
}
div.debug h2:hover { color:#66f; } 
div.debug div.start,
div.debug div.end
{ margin:0; padding-left:0.5em; font-size:1em; font-weight:bold; background-image:none; color:white; background-color:#447;font-family:sans-serif; }
div.debug div.start p.normal,
div.debug div.end p.normal { color:white; }
.redirectlink {
	text-decoration:none;
	cursor:pointer;
	display:block;
	margin:14px 0;
	padding:14px;
	font-size:18px;
	background-color:#447;
	color:white;
	font-family:sans;
}
.redirectlink:hover { color:#ddf;}


div.debug pre { margin:0 1em ;  background-color:#bbe; border:dotted 1px #88d;}
div.debug li p.normal { font-weight:normal;color:black; }
div.debug li p.ooo { font-weight:bold;color:#a00; }
div.debug li p.oh { font-weight:bold;color:#0a0; }
div.debug li p.shhh { font-weight:normal;color:#999}
div.debug li span.right { position:absolute; font-weight:normal;right:1em;}
div.debug li.ooo {  }
div.debug li li { background-color:#def;  }
div.debug li li li { background-color:#cef; }
div.debug li li li li { background-color:#bef; }
div.debug li li li li li { background-color:#bee; }
div.debug li li li li li li { background-color:#bed; }
div.debug li li li li li li li { background-color:#bec; }
div.debug span.slow { color:red;  }
div.debug p.clickable { cursor:pointer;  }
div.debug p.clickable:hover { color:#66f;  }
div.debug button.command { display:inline; margin:0;padding:1px; border:solid 1px transparent;font-size:xx-small; color:#88d; }
div.debug button.command:hover { border:solid 1px #aaf; color:#66f; background-color:#ccf; }
/* }}} */
	</style>
HTML;
		$html = self::$preamble . $html;
		if ($full) return <<<EOF
<?xml version="1.0" encoding="ISO-8859-1" ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<title>Log page $title</title>
	<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
	$css
</head>
<body>
$html
</body>
</html>
EOF;
		else return $css . $html;
	}/*}}}*/
}/*}}}*/
//class ARL_Array/*{{{*/
/** ARL_Array provides various functions for dealing with arrays
  */
class ARL_Array
{
	// static public function value( $key, &$array, $default=null, $create_if_missing=false)/*{{{*/
	/** return value from an array for given key, or default.
	 *  
	 *  @param string $key
	 *  @param array &$array reference to array
	 *  @param mixed $default defaults to null
	 *  @param bool $create_if_missing 
	 *  @return mixed
	 */
	static public function value( $key, &$array, $default=null, $create_if_missing=false)
	{
		if (! is_array($array)) 
		{
			trigger_error( "ARL_Array::value called with something other than an array",E_USER_NOTICE);
			return null;
		}
		if (array_key_exists($key, $array)) return $array[$key];
		if ($create_if_missing) $array[$key] = $default;
		return $default;
	}/*}}}*/
	// static public function reference( $key, &$array, $default=null )/*{{{*/
	/** return reference to an array for given key, initialising default value if necessary.
	 *  
	 *  @param string $key
	 *  @param array &$array reference to array
	 *  @param mixed $default defaults to null
	 *  @return mixed
	 */
	static public function & reference( $key, &$array, $default=null)
	{
		if (! is_array($array)) throw new Exception( "ARL_Array::reference called with something other than an array");
		if (!array_key_exists($key, $array)) $array[$key] = $default;
		return $array[$key];
	}/*}}}*/
	//public static function value_recursive( $keys, &$array, $default=null, $create_if_missing=false)/*{{{*/
	/** return value from an array nested key array, or default.
	 *  
	 *  @param array $keys 
	 *  @param array &$array reference to array
	 *  @param mixed $default defaults to null
	 *  @param bool $create_if_missing 
	 *  @return mixed
	 */
	public static function value_recursive( $keys, &$array, $default=null, $create_if_missing=false)
	{
		if (! is_array($array)) throw new Exception( "ARL_Array::value_recursive called with something other than an array");

		$ptr = &$array;

		$parent_keys = $keys;
		$child_key = array_pop($parent_keys);

		while (isset($ptr) && count($parent_keys))
		{
			$key = array_shift($parent_keys);
			if (array_key_exists($key, $ptr)) 
			{
				$ptr = &$ptr[$key];
				if (! is_array($ptr)) throw new Exception(
					"ARL_Array::value_recursive failed, something in the chain is not an array.");
			}
			else unset($ptr);
		}
		if (! $create_if_missing) 
		{
			if (! isset($ptr)) return $default;
			else return self::value($child_key, $ptr, $default, false);
		}

		// create_if_missing is required
		// chain exists.
		if (isset($ptr))
			return self::value($child_key, $ptr, $default, true);

		// chain failed
		$ptr = &$array;
		foreach (array_slice($keys,0,-1) as $key)
		{
			if (! array_key_exists($key, $ptr))
				$ptr[$key] = array();
			$ptr = &$ptr[$key];
		}
		$ptr[$child_key] = $default;
			
		return $default;
	}/*}}}*/

	// public static function tokenise_search_string( $search_text )/*{{{*/
	/** tokenise a search string into an array, preserving phrases in quotes as individual tokens
	 *  
	 *  this taken from http://www.php.net/manual/en/function.strtok.php#94463
	 */
	public static function tokenise_search_string( $search_text )
	{
		$tokens = array();
		$token = strtok($search_text, ' ');
		while ($token) 
		{
			// find double quoted tokens
			if (substr($token,0,1)=='"')
				$token = substr($token,1) . ' ' . strtok('"'); 
			// find single quoted tokens
			elseif (substr($token,0,1)=="'")
				$token = substr($token,1) . ' ' . strtok("'"); 

			$tokens[] = $token;
			$token = strtok(' ');
		}
		return $tokens;
	}/*}}}*/
}/*}}}*/
//class ARL_Object/*{{{*/
/** ARL_Object provides various functions for dealing with objects
  */
class ARL_Object
{
	// static public function property( $property, $object, $default=null, $create_if_missing=false)/*{{{*/
	/** return given property of object, or default if not exists.
	 *  
	 *  @param string $property
	 *  @param object $object
	 *  @param mixed $default defaults to null
	 *  @param bool $create_if_missing 
	 *  @return mixed
	 */
	static public function property( $property, $object, $default=null, $create_if_missing=false)
	{
		if (! is_object($object)) throw new Exception( "ARL_Object::value called with something other than an object");
		if (array_key_exists($property, $object)) return $object->$property;
		if ($create_if_missing) $object->$property = $default;
		return $default;
	}/*}}}*/
	// static public function property_reference( $property, &$object, $default=null )/*{{{*/
	/** return reference to an object's property, initialising default value if necessary.
	 *  
	 *  @param string $property
	 *  @param object $object
	 *  @param mixed $default defaults to null
	 *  @return mixed
	 */
	static public function & property_reference( $property, &$object, $default=null)
	{
		if (! is_object($object)) throw new Exception( "ARL_Object::reference called with something other than an object");
		if (!array_key_exists($property, $object)) $object->$property = $default;
		return $object->$property;
	}/*}}}*/
}/*}}}*/
