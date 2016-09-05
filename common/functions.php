<?php

/* TODO: remove this function. */
function dbconnect() {
	return true;
/*
        global $hostname,$database,$dbuser,$dbpass,$db;
        $db = mysql_connect($hostname,$dbuser,$dbpass) or die("Database error");
        mysql_select_db($database, $db);
        mysql_set_charset('utf8mb4',$db);
        mysql_query("set sql_mode='ALLOW_INVALID_DATES'");
*/
}

/* TODO: remove this function. */
function dbclose() {
	return true;
/*
        global $db;
        mysql_close($db);
*/
}

function dbserver_has_utf8mb4_support() {
    global $hostname,$database,$dbuser,$dbpass;
    $dbt = new PDO("mysql:host=$hostname;dbname=$database", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
    $version = $dbt->getAttribute(PDO::ATTR_SERVER_VERSION);
    if (preg_match("/([0-9]*)\.([0-9]*)\.([0-9]*)/", $version, $matches)) {
        $maj = $matches[1]; $min = $matches[2]; $upd = $matches[3];
        if ($maj > 5 || ($maj >= 5 && $min >= 5 && $upd >= 3)) {
            return true;
        }
    }
    return false;
}

?>
