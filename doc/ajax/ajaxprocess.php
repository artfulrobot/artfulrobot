<?php
require_once '/var/www/webdevshared/rl_core/php/lib.rl_core.php';
require_once dirname(__FILE__) . "/../../ajax/php/artfulrobot-ajax.php";
class CMS
{
	function user_in_group() { return true; }
}

class Ajax_test extends ARL_Ajax_Module
{
	function run_module()
	{
		$this->response->html = "Boo";
	}
}

ARL_Ajax_Request::process();
