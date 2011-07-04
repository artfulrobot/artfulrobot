<?php
class ARL_Debug
{
	static public    $errors_to_ignore ;
	static protected $running = true;
	static protected $init 		   	   = false; // class is initialised
	static protected $toponly 		   = true; // drop all data unless TOP'ed
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

		if ( ! $reset) self::log('TOP Start');
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
		if ((!$myTopSetting) && self::$toponly) return true; // if toponly set, ignore everything else 
		// }}}

		$newRowId = sizeof( self::$log );
		self::$log[] = array( 'top'=>0, 'time'=>self::getmicrotime(), 'class'=>'normal', 'scope'=>'', 'message'=>'', 'vars'=>'','parent'=>self::$last_parent,'lastChild'=>0 );
		$newRow = & self::$log[$newRowId];
		$newRow['top'] = $myTopSetting;



		if ($applyhtmlspecialchars) $newRow['message']=htmlspecialchars(trim($t));
		else $newRow['message']=trim($t);

		$newRow['message'] .= sprintf(' <span class="right" >%0.1fMb used %0.0f%%</span>', memory_get_usage()/1024/1024 , 100*memory_get_usage()/self::$iniMemoryLimit);


		// figure out scope {{{
		$tmp='';
		$backtrace = debug_backtrace();
		if ($backtrace = isgiven($backtrace,1))
		{
			$tmp=isgiven($backtrace,'function');
			if ( $tmp!='debug' ) $tmp= isgiven($backtrace,'class')	. isgiven($backtrace,'type') .$tmp ;
		}
		$newRow['scope'] = $tmp;
		unset($backtrace); // conserve memory? }}}

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

		// prepare vars html {{{
		if ( $vars !==null )
		{
			ob_start();                    // Start output buffering
			var_dump( $vars );
			$vars = htmlspecialchars(ob_get_contents()); // Get the contents of the buffer
			ob_end_clean();                // End buffering and discard
			$newRow['vars'] = $vars;
		} // }}}

		if ( $turnItOff ) self::$running = false;  

		return true;
	}//}}}
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
		if	   ($command == 'not silent' ){ self::$silent = 0;debug('Debugging set ' . $command ); return true ; }
		elseif ($command == 'silent' )    { self::$silent = 1;debug('Debugging set ' . $command ); return true ; }
		elseif ($command == 'not stderr' )   { self::$stderr = 0;debug('Debugging set ' . $command ); return true ; }
		elseif ($command == 'stderr' ) { self::$stderr = 1;debug('Debugging set ' . $command ); return true ; }
		elseif ($command == 'not error_log' )   { self::$error_log = 0;debug('Debugging set ' . $command ); return true ; }
		elseif ($command == 'error_log' ) { self::$error_log = 1;debug('Debugging set ' . $command ); return true ; }
		elseif ($command == 'toponly' )   { self::$toponly = 1;debug('Debugging set ' . $command ); return true ; }
		elseif ($command == 'not toponly' ) { self::$toponly = 0;debug('Debugging set ' . $command ); return true ; }
		elseif ($command == 'not file' )  { self::$file = 0;debug('Debugging set ' . $command ); return true ; }
		elseif ($command == 'set slow' )  { self::$slow = $args;debug('Debugging set slow to ' . $args ); return true ; }
		elseif ($command == 'on' && self::$running==false )  	  { self::$running = true;debug('<<Debugger turned ON');  return true ; }
		elseif ($command == 'off')
		{
			if ( self::$running )  	  
			{  
				debug('>>Debugger turned OFF');
				self::$running = false;  
				return 'on';
			}
			else
			{
				return 'off';
			}
		}
		elseif ($command == 'file' )      
		{ 
			if ($args) 
			{
				// ensure timestamp is in there (somewhere)
				// otherwise too much rik of overwriting others.
				if (strpos($args,'%d')===false) $args = "%d_$args";
				self::$file = $args;
			}
			else self::$file = 1;

			debug('TOP Debugging set ' . $command . ' to ' . self::$file); 
			return true ; 
		}
		elseif ($command == 'get_file' )      
		{ 
			return self::$file;
		}

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
			elseif ($command =='fetch_full') return "$head$chunk</body></html>";
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
	//static public function fatal( $t='!!myexit called',$vars=null,$applyhtmlspecialchars=true ) // {{{
	/** fatal errors, equivalent to exit() 
	 */
	static public function fatal( $t='!!myexit called',$vars=null,$applyhtmlspecialchars=true ) 
	{
		// get out of all debug depths so that the last message is always immediately visible.
		if (!self::$running) self::$running=true;//must be ON for this to work!
		if (self::$toponly) self::$toponly = false; // turn this off

		self::log('!! Whooooah there!');
		self::log( "TOP{ $t" ,null,$applyhtmlspecialchars);

		// make table for backtrace
		$cols=explode('|','file|line|function|class');

		$tmp = "<table border=\"1\"><tr>";
		foreach($cols as $f) $tmp.= "<th>$f</th>";
		$backtrace = debug_backtrace();
		foreach ($backtrace as $row)
		{
			$tmp .= "<tr>";
			foreach($cols as $f) $tmp.= "<td>" . htmlspecialchars(isgiven($row,$f)) . "</td>";
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
	// static public function handle_exception($errno, $errstr, $errfile, $errline) // {{{
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
			$backtrace = $exception->getBacktrace();
			foreach ($backtrace as $row)
			{
				$tmp .= "<tr>";
				foreach($cols as $f) $tmp.= "<td>" . htmlspecialchars(isgiven($row,$f)) . "</td>";
				$tmp .= "</tr>";
			}
			$tmp .= "</table>";
		}
		self::log("TOP Uncaught Exception: ". ( $exception->getCode() ? '(code ' . $exception->getCode() . ')' : '' ) . $exception->getMessage() , $exception->getTraceAsString());
		self::fatal("FATAL: Uncaught exception");
	} // }}}

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
			if ($row['top'] && ! self::$toponly) 
			{
				$top .= strtr( $li, array(
					'%debugid'=>"",
					'%reveal' =>"<button class=\"command\" onclick=\"debugReveal($key)\" >Locate in log</button>" ));
			}
		}
		while ($depth--) $html .= '</li></ol>';

		if ($top && ! self::$toponly) $top = "<h1>Attention</h1><ol>$top</ol>\n<h1>Full Log</h1>";
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
}