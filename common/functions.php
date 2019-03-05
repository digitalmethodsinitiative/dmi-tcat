<?php

function pdo_connect() {
    global $dbuser, $dbpass, $database, $hostname;

    $dbh = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8mb4", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbh->query("set time_zone='+00:00'");

    return $dbh;
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


/*
 * Helper function to restart all active capture roles via the controller and optionally wait a minute to ensure the tracking is refreshed
 */
function controller_restart_roles($logtarget = "cli", $wait = false) {
    global $logtarget;
    $dbh = pdo_connect();
    $roles = unserialize(CAPTUREROLES);
    foreach ($roles as $role) {
        logit($logtarget, "Restarting active capture role: $role");
        $query = "INSERT INTO tcat_controller_tasklist ( task, instruction ) values ( '$role', 'reload' )";
        $rec = $dbh->prepare($query);
        $rec->execute();
    }
    if ($wait) {
        /* TODO: more intelligent wait procedure by checking if roles have attained a new PID */
        sleep(90);
    }
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

/**
 * Can a phrase be found inside a tweet? This function is a wrapper around a regular expression which interprets
 * the concept of a word boundary in a way that makes sense for tweets.
 */
function tweet_contains_phrase($text, $keyword) {
    $keyword = mb_strtolower($keyword);
    $text = mb_strtolower($text);
    $quoted = preg_quote($keyword);
    if (mb_eregi("^$quoted$", $text) || mb_eregi("^$quoted\W", $text) || mb_eregi("\W$quoted$", $text) ||
        mb_eregi("\W$quoted\W", $text)) {
        /*
         * NOTICE: With regards to hashtags and mentions. As '@' and '#' are considered non-word characters by the regular expression engine, the
         * 'globalwarming' will match in '#globalwarming' as documented (in the FAQ).
         * 'America' will match in '@america' as should be documented (but has been the historical behaviour? TODO: RESEARCH).
         * 'global' will NOT match 'globalwarming', as documented.
         * 'Love!' will however match 'Love!?sick' and we accept that.
         *
         * We could opt to manually look for whitespaces etc. but this is very error prone due to UTF8(MB4) issues. We'd basically be designing
         * our own word boundary scheme. If someone now immediately follows up 'Love!' with a heart emojij for example, our current check would
         * include that tweet (as is desired, because an emojij is normally seen as a distinct entity) but if we'd look for whitespaces and comma's
         * etc. it would actually fail on that content.
         */
        return TRUE;
    } else if ((substr($keyword, 0, 1) == '#' || substr($keyword, 0, 1) == '@') &&
        mb_eregi("$quoted\W", $text)) {
        /*
         * The check works because we know our keyword STARTS with '#' or '@' and therefore we cannot match some substring of a different hashtag
         * or twitter handle.
         */
        return TRUE;
    }
    return FALSE;
}

/**
 *  Is the URL expander enabled?
 */
function is_url_expander_enabled() {
    if (defined('ENABLE_URL_EXPANDER')) {
        return ENABLE_URL_EXPANDER;
    } else {
        /*
         * ENABLE_URL_EXPANDER is not set as config variable. We attempt to learn via the tcat_status table whether
         * or not we should run it. The URL expander used to be a separate Python script. If this script was enabled
         * (via cron) it will be in the tcat_status table.
         */
        $dbh = pdo_connect();
        $sql = "select * from tcat_status where variable = 'enable_url_expander' and value = 'true';";
        $rec = $dbh->prepare($sql);
        if ($rec->execute() && $rec->rowCount() > 0) {
            return true;
        }
        return false;
    }
}

/*
 * Create a temporary memory-based cache table to store tweets.
 */
function create_tweet_cache() {
    global $dbh;
    $uniqid = substr(md5(uniqid("", true)), 0, 15);
    $tweet_cache = "tcat_cache_memory_$uniqid";
    $sufficient_memory = TRUE;
    $sql = "SHOW VARIABLES WHERE Variable_name = 'tmp_table_size'";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    $res = $rec->fetch(PDO::FETCH_ASSOC);
    if ($res['Value'] < 1024 * 1024 * 1024 * 3) {
        $sufficient_memory = FALSE;
        // We need to ensure we have are allowed to create big temporary tables, whether in memory or not
        try {
            $sql = "SET GLOBAL tmp_table_size = 1024 * 1024 * 1024 * 3";
            $q = $dbh->prepare($sql);
            $q->execute();
        } catch (PDOException $Exception) {
            pdo_error_report($Exception);
        }
    } else {
        $sql = "SHOW VARIABLES WHERE Variable_name = 'max_heap_table_size'";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        $res = $rec->fetch(PDO::FETCH_ASSOC);
        if ($res['Value'] < 1024 * 1024 * 1024 * 3) {
            $sufficient_memory = FALSE;
        }
    }
    // NOTICE (TODO: configurable): for safety we always chose on disk temporary tables now
    $sufficient_memory = FALSE;
    if ($sufficient_memory) {
        $sql = "CREATE TEMPORARY TABLE $tweet_cache (id BIGINT PRIMARY KEY) ENGINE=Memory";
    } else {
        $sql = "CREATE TEMPORARY TABLE $tweet_cache (id BIGINT PRIMARY KEY) ENGINE=TokuDB COMPRESSION=TOKUDB_LZMA";
    }
    try {
        $create = $dbh->prepare($sql);
        $create->execute();
    } catch (PDOException $Exception) {
        /* Fall-back to using disk table */
        pdo_error_report($Exception);
        $sql = "CREATE TEMPORARY TABLE $tweet_cache (id BIGINT PRIMARY KEY) ENGINE=TokuDB COMPRESSION=TOKUDB_LZMA";
        $create = $dbh->prepare($sql);
        $create->execute();
    }
    return array($uniqid, $tweet_cache);
}

?>
