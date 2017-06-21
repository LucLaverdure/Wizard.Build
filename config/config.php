<?php
	// define error level displayed
	//error_reporting(E_ERROR | E_WARNING | E_PARSE);
	error_reporting(-1);

	// set main path for files fetching
	global $main_path;
	$main_path = $_SERVER["DOCUMENT_ROOT"]."/"; // Must include trailing slash

	// recursively search directory(ies) for Controllers  and functions used in application 
	// (string delimited by comas without spaces)
	define('SEARCH_DIRECTORY_CONTROLLERS', 'webapp,admin');
	
	// filter file extensions for controllers and functions used in application
	define('FILE_FILTER_CONTROLLERS', 'php,php5');

	// cron job password
	define('CRON_JOB_PASSWORD', 'eedad41f42660e5cb7a4');
	
	/* protected server side session namespaces (string delimited by comas without spaces)
					 accessible via controller->setcache and controller->getcache
					 not accessible via controller->cacheform and not acccessible via controller->getModel */
	define('PROTECTED_UNIT', 'admin,private,system,login,lobby,password,pwd,user_id,.ga,PHPSESSID,.gat,.gid,q,ga,gat');
?>