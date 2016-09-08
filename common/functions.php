<?php

function dbconnect() {
        global $hostname,$database,$dbuser,$dbpass,$db;
        $db = mysql_connect($hostname,$dbuser,$dbpass) or die("Database error");
        mysql_select_db($database, $db);
        mysql_set_charset('utf8mb4',$db);
        mysql_query("set sql_mode='ALLOW_INVALID_DATES'");
}

function dbclose() {
        global $db;
        mysql_close($db);
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

function is_admin(){
    // Allow ADMIN_USER to be an array so multiple users can be "admin"
    if(defined("ADMIN_USER") && is_array(ADMIN_USER) && sizeof(ADMIN_USER) > 0){
        return (isset($_SERVER['PHP_AUTH_USER']) && in_array($_SERVER['PHP_AUTH_USER'], ADMIN_USER));
    }
    return (defined("ADMIN_USER") && ADMIN_USER != "" && isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER'] == ADMIN_USER);
}

?>
