<?php

include_once('/var/www/dmi-tcat/config.php');
include_once('/var/www/dmi-tcat/querybins.php');

$path_local = getcwd()."/";

// +++++ database connection functions +++++

function dbconnect() {
	global $hostname,$database,$dbuser,$dbpass,$db;
	$db = mysql_connect($hostname,$dbuser,$dbpass) or die("Database error");
	mysql_select_db($database, $db);
	mysql_set_charset('utf8',$db);
}

function dbclose() {
	global $db;
	mysql_close($db);
}
	
?>
