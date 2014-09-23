<?php

function dbconnect() {
        global $hostname,$database,$dbuser,$dbpass,$db;
        $db = mysql_connect($hostname,$dbuser,$dbpass) or die("Database error");
        mysql_select_db($database, $db);
        mysql_set_charset('utf8mb4',$db);
}

function dbclose() {
        global $db;
        mysql_close($db);
}

function dbserver_has_utf8mb4_support() {
    global $hostname,$database,$dbuser,$dbpass;
    $dbt = new PDO("mysql:host=$hostname;dbname=$database", $dbuser, $dbpass);
    $version = $dbt->getAttribute(PDO::ATTR_SERVER_VERSION);
    if (preg_match("/([0-9]*)\.([0-9]*)\.([0-9]*)/", $version, $matches)) {
        $maj = $matches[1]; $min = $matches[2]; $upd = $matches[3];
        if ($maj >= 5 && $min >= 5 && $upd >= 3) {
            return true;
        }
    }
    return false;
}

?>
