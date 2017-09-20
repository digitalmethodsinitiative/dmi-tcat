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

function env_is_cli() {
    return (!isset($_SERVER['SERVER_SOFTWARE']) && (php_sapi_name() == 'cli' || (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0)));
}

function is_admin(){

    if (env_is_cli()) {
        // On the command-line, there is no notion of admin
        return true;
    }

    if(defined("ADMIN_USER") && ADMIN_USER != "")
    {
        $admin_users = @unserialize(ADMIN_USER);

        // Support the old config style where ADMIN_USER can be a single string
        if($admin_users === false){
            $admin_users = array(ADMIN_USER);
        }

        // If there are no users set in ADMIN_USER then everyone is an admin
        if(count($admin_users) == 0 || count($admin_users) == 1 && $admin_users[0] == ''){
          return true;
        }

        return (isset($_SERVER['PHP_AUTH_USER']) && in_array($_SERVER['PHP_AUTH_USER'], $admin_users));
    }

    // If ADMIN_USER is empty so everyone is an admin
    return true;
}

/**
 * Validates a given list of keywords, as entered as a parameter in capture/search/search.php for example
 */
function validate_capture_phrases($keywords) {
    $illegal_chars = array( "\t", "\n", ";", "(", ")" );
    foreach ($illegal_chars as $c) {
        if (strpos($keywords, $c) !== FALSE) {
            return FALSE;
        }
    }
    return TRUE;
}


?>
