<?php

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