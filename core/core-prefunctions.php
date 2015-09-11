<?php
	if (!IN_DREAMFORGERY) die();

	function q($arg_number='all') {
		if ($arg_number=='all')
			return (isset($_GET['q'])) ? $_GET['q'] : '/';
		elseif (is_numeric($arg_number)) {
			$array = explode('/', q());
			if (isset($array[$arg_number]))
				return $array[$arg_number];
		}
		return '';
	}

	function inpath($url) {
		$url = preg_quote($url, '/');
		$url = str_replace('\*', '(.*)',$url);
		$url = preg_match('/^'.$url.'$/', q());
		return $url;
	}

	function elog($data) {
		global $mainpath;
		$log_file = $mainpath."logs/errors.log";
		$fh = @fopen($log_file, 'a');
		@fwrite($fh, $data."\n");
		@fclose($fh);
	}

	function form_cache($form_name, $default_values = array()) {
		// set form array if not created
		if (!isset($_SESSION[$form_name])) $_SESSION[$form_name] = array();

		// set default values
		foreach ($default_values as $key => $var) {
			if (!isset($_SESSION[$form_name][$key])) {
				$_SESSION[$form_name][$key] = $var;
			}
		}

		// override previous values when form is posted
		foreach ($_REQUEST as $key => $var) {
			$_SESSION[$form_name][$key] = $var;
		}

		// return form cache
		return $_SESSION[$form_name];
	}

	function validate_email($email) {
		$isValid = true;
		$atIndex = strrpos($email, "@");
		if (is_bool($atIndex) && !$atIndex) {
			$isValid = false;
		} else {
			$domain = substr($email, $atIndex+1);
			$local = substr($email, 0, $atIndex);
			$localLen = strlen($local);
			$domainLen = strlen($domain);
			if ($localLen < 1 || $localLen > 64) {
				// local part length exceeded
				$isValid = false;
			} else if ($domainLen < 1 || $domainLen > 255) {
				// domain part length exceeded
				$isValid = false;
			} else if ($local[0] == '.' || $local[$localLen-1] == '.') {
				// local part starts or ends with '.'
				$isValid = false;
			} else if (preg_match('/\\.\\./', $local)) {
				// local part has two consecutive dots
				$isValid = false;
			} else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
				// character not valid in domain part
				$isValid = false;
			} else if (preg_match('/\\.\\./', $domain)) {
				// domain part has two consecutive dots
				$isValid = false;
			} else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))) {
				// character not valid in local part unless 
				// local part is quoted
				if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\","",$local))) {
					$isValid = false;
				}
			}
			if ($isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A"))) {
				// domain not found in DNS
				$isValid = false;
			}
		}
		return $isValid;
	}

	function redirect($url) {
		header("Location: ". $url);
		die();
	}

	function t($text) {
		return $text;
	}
?>