<?php
/*
* This file is part of the ebot PHP bot
*
* Released under the terms and conditions of the
* GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
*
* Main launching file
*
* $Id: ebot.php 33 2011-01-29 22:02:14Z tmichelbrink $
*
*/
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__));
$config = loadConfig($argv);
$ebot = new Ebot($config);
$ebot->startUp();


function loadConfig($argv) {
	if(isset($argv[1])) {
		$configFile = $argv[1];
	} else {
		$configFile = 'ebot_config.php';
	}
	if(is_readable($configFile)) {
		include($configFile);
		$config = new dbConfig();
	} else {
		$install = new EbotInstall;
		$results = $install->install($configFile);
		if(true == $results) {
			$this->loadConfig();
		} else {
			die();
		}
//		die("Configuration file ($configFile) not found!");
	}
	return $config;
}

function __autoload ($className) {
	$ds = DIRECTORY_SEPARATOR;
	$fileName = str_replace('_', $ds, $className) . '.php';

	$pathList = explode(PATH_SEPARATOR, get_include_path());
	$fullPathName = "";
	foreach($pathList as $path) {
		// account for path that may or may not have the / on the end
		if (substr($path,-1) != $ds) {
			$path .= $ds;
		}
		if (file_exists($path.$fileName)) {
			$fullPathName = $path;
			break;
		}
	}

	$fullPathName = 'classes/'.$fullPathName;
	$r = include_once($fullPathName.$fileName);
	if (!class_exists($className, false)) {
		eval("class $className {}");
		throw new Exception('Class: '.$className. ' not found');
	}
	return;
}

function clean($array, $key, $defaultValue = '') {
	if (isset($array[$key])) {
		$value = $array[$key];
		if (gettype($value) == "string") {
			return trim($value);
		}
		return $value;
	} else {
		return $defaultValue;
	}
} // end clean
