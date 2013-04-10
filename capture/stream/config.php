<?php

include_once('../../config.php');
include_once('../../querybins.php');

$path_local = dirname(__FILE__);

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
