<?php

require_once __DIR__ . '/geoPHP/geoPHP.inc'; // geoPHP library
require_once __DIR__ . '/../../common/constants.php'; // include constants file

error_reporting(E_ALL);
ini_set("max_execution_time", 0);       // capture script want unlimited execution time

function geophp_sane() {
    $sane = true;
    if (!geoPHP::geosInstalled()) {
        $msg = "geoPHP needs the GEOS and its PHP extension (please download it at: http://trac.osgeo.org/geos/)";
        $sane = false;
    } else {
        // Is the Digital Methods lab in Amsterdam? 
        $point_lng = 4.893346; $point_lat = 52.369042;
        $sw_lng = 4.768520; $sw_lat = 52.321629;
        $ne_lng = 5.017270; $ne_lat = 52.425129;
        $sane = coordinatesInsideBoundingBox($point_lng, $point_lat, $sw_lng, $sw_lat, $ne_lng, $ne_lat);
        if (!$sane) {
            $msg = "geoPHP/GEOS library seems broken. searching on area will not work";
        }
    }
    if (!$sane) {
        if (defined('CAPTURE')) {
            logit(CAPTURE . ".error.log", $msg);
        } else {
            logit("cli", $msg);
        }
    }
    return $sane;
}

function create_error_logs() {
    global $dbuser, $dbpass, $database, $hostname;
    $dbh = pdo_connect();

    $creating_tables_for_fresh_install = false;
    $sql = "SELECT * FROM information_schema.tables WHERE table_schema = '$database' AND table_name = 'tcat_error_gap'";
    $test = $dbh->prepare($sql);
    $test->execute();
    if ($test->rowCount() == 0) {
        $creating_tables_for_fresh_install = true;
    }

    $sql = 'create table if not exists tcat_error_ratelimit ( id bigint auto_increment, type varchar(32), start datetime not null, end datetime not null, tweets bigint not null, primary key(id), index(type), index(start), index(end) ) ' . MYSQL_ENGINE_OPTIONS;
    $h = $dbh->prepare($sql);
    $h->execute();

    $sql = 'create table if not exists tcat_error_gap ( id bigint auto_increment, type varchar(32), start datetime not null, end datetime not null, primary key(id), index(type), index(start), index(end) ) ' . MYSQL_ENGINE_OPTIONS;
    $h = $dbh->prepare($sql);
    $h->execute();

    /*
     * The tcat_status variable is utilised as generic keystore to record and track aspects of this TCAT installation.
     * This is not a configuration table. The configuration of TCAT is defined in config.php, though we may wish to allow dynamically configurable
     * options in the future and this table would suit such a purpose.
     * At the moment, this table is solely used by TCAT internally to store information such as wich upgrade steps have been executed, etc.
     */

    $sql = "CREATE TABLE IF NOT EXISTS tcat_status (
    `variable` varchar(32),
    `value` varchar(1024),
    PRIMARY KEY `variable` (`variable`),
            KEY `value` (`value`)
    ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET=utf8mb4";
    $create = $dbh->prepare($sql);
    $create->execute();


    $sql = "select value from tcat_status where variable = 'ratelimit_format_modified_at'";
    $test = $dbh->prepare($sql);
    $test->execute();
    if ($test->rowCount() == 0 && defined('CAPTURE')) {
        // We are actively registering ratelimits in the new gauge-style and store the timestamp of the start of this new behaviour
        // The purpose of this insert statemtn is for common/upgrade.php to know the exact time at which it can expect datetime insertion to be sane.
        $sql = "insert into tcat_status ( variable, value ) values ( 'ratelimit_format_modified_at', now() )";
        $insert = $dbh->prepare($sql);
        $insert->execute();
    }
    
    // When creating tables for a fresh install, set tcat_status variable to indicate we have up-to-date ratelimit, gap tables and are capturing in the proper timezone
    // Practically, the purpose of this insert statement is for common/upgrade.php to know we do not need to upgrade the above table.

    if ($creating_tables_for_fresh_install) {
        $sql = "insert into tcat_status ( variable, value ) values ( 'ratelimit_database_rebuild', 2 )";
        $insert = $dbh->prepare($sql);
        $insert->execute();
        $sql = "insert into tcat_status ( variable, value ) values ( 'tz_mentions_resync', 1 )";
        $insert = $dbh->prepare($sql);
        $insert->execute();
    }

    // Some GIT updates (such as fixes for important bug) may require an immediate restart of capture roles
    $sql = "select value from tcat_status where variable = 'retweetbug_fixed_since'";
    $test = $dbh->prepare($sql);
    $test->execute();
    if ($test->rowCount() == 0) {
        $sql = "insert into tcat_status ( variable, value ) values ( 'retweetbug_fixed_since', now() )";
        $insert = $dbh->prepare($sql);
        $insert->execute();
        controller_restart_roles();
    }

}

// Enclose identifier in backticks; escape backticks inside by doubling them.
function quoteIdent($field) {
    return "`" . str_replace("`", "``", $field) . "`";
}

// read the minute of the current hour without leading zero
function get_current_minute() {
    $minutes = ltrim(date("i", time()), '0');
    if ($minutes == '') {
        $minutes = '0';
    }
    return intval($minutes);
}

function create_bin($bin_name, $dbh = false) {

    if (strlen($bin_name) > 45) {
        throw new Exception("Bin name exceeds legal length of 45 characters");
    }

    try {

        $dbh = pdo_connect();

        $sql = "CREATE TABLE IF NOT EXISTS " . quoteIdent($bin_name . "_hashtags") . " (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tweet_id` bigint(20) NOT NULL,
            `created_at` datetime,
            `from_user_name` varchar(255),
            `from_user_id` bigint,
            `text` varchar(255),
            PRIMARY KEY (`id`),
                    KEY `created_at` (`created_at`),
                    KEY `tweet_id` (`tweet_id`),
                    KEY `text` (`text`),
                    KEY `from_user_name` (`from_user_name`)
            ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET=utf8mb4";
        $create_hashtags = $dbh->prepare($sql);
        $create_hashtags->execute();

        $sql = "CREATE TABLE IF NOT EXISTS " . quoteIdent($bin_name . "_withheld") . " (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tweet_id` bigint(20) NOT NULL,
            `user_id` bigint(20),
            `country` char(5),
            PRIMARY KEY (`id`),
                    KEY `user_id` (`user_id`),
                    KEY `tweet_id` (`tweet_id`),
                    KEY `country` (`country`)
            ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET=utf8mb4";

        $create_withheld = $dbh->prepare($sql);
        $create_withheld->execute();


        $sql = "CREATE TABLE IF NOT EXISTS " . quoteIdent($bin_name . "_places") . " (
            `id` varchar(32) NOT NULL,
            `tweet_id` bigint(20) NOT NULL,
            PRIMARY KEY (`id`, `tweet_id`)
            ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET=utf8mb4";

        $create_places = $dbh->prepare($sql);
        $create_places->execute();



        $sql = "CREATE TABLE IF NOT EXISTS " . quoteIdent($bin_name . "_mentions") . " (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tweet_id` bigint(20) NOT NULL,
            `created_at` datetime,
            `from_user_name` varchar(255),
            `from_user_id` bigint, 
            `to_user` varchar(255),
            `to_user_id` bigint,
            PRIMARY KEY (`id`),
                    KEY `created_at` (`created_at`),
                    KEY `tweet_id` (`tweet_id`),
                    KEY `from_user_name` (`from_user_name`),
                    KEY `from_user_id` (`from_user_id`),
                    KEY `to_user` (`to_user`),
                    KEY `to_user_id` (`to_user_id`)
            ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET=utf8mb4";

        $create_mentions = $dbh->prepare($sql);
        $create_mentions->execute();

        $sql = "CREATE TABLE IF NOT EXISTS " . quoteIdent($bin_name . "_tweets") . " (
            `id` bigint(20) NOT NULL,
                    `created_at` datetime NOT NULL,
                    `from_user_name` varchar(255) NOT NULL,
                    `from_user_id` bigint NOT NULL,
                    `from_user_lang` varchar(16),
                    `from_user_tweetcount` int(11),
                    `from_user_followercount` int(11),
                    `from_user_friendcount` int(11),
                    `from_user_listed` int(11),
                    `from_user_realname` varchar(255),
                    `from_user_utcoffset` int(11),
                    `from_user_timezone` varchar(255),
                    `from_user_description` varchar(255),
                    `from_user_url` varchar(2048),
                    `from_user_verified` bool DEFAULT false,
                    `from_user_profile_image_url` varchar(400),
                    `from_user_created_at` datetime,
                    `from_user_withheld_scope` varchar(32),
                    `from_user_favourites_count` int(11),
                    `source` varchar(512),
                    `location` varchar(64),
                    `geo_lat` float(10,6),
                    `geo_lng` float(10,6),
                    `text` text NOT NULL,
                    `retweet_id` bigint(20),
                    `retweet_count` int(11),
                    `favorite_count` int(11),
                    `to_user_id` bigint,
                    `to_user_name` varchar(255),
                    `in_reply_to_status_id` bigint(20),
                    `filter_level` varchar(6),
                    `lang` varchar(16),
                    `possibly_sensitive` tinyint(1),
                    `quoted_status_id` bigint,
                    `withheld_copyright` tinyint(1),
                    `withheld_scope` varchar(32),
                    PRIMARY KEY (`id`),
                    KEY `created_at` (`created_at`),
                    KEY `from_user_created_at` (`from_user_created_at`),
                    KEY `from_user_withheld_scope` (`from_user_withheld_scope`),
                    KEY `from_user_name` (`from_user_name`),
                    KEY `from_user_lang` (`from_user_lang`),
                    KEY `retweet_id` (`retweet_id`),
                    KEY `in_reply_to_status_id` (`in_reply_to_status_id`),
                    KEY `quoted_status_id` (`quoted_status_id`),
                    KEY `possibly_sensitive` (`possibly_sensitive`),
                    KEY `withheld_copyright` (`withheld_copyright`),
                    KEY `withheld_scope` (`withheld_scope`),
                    KEY `from_user_description` (`from_user_description`(32)),
                    KEY `text` (`text`(32))
                    ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET=utf8mb4";

	$create_tweets = $dbh->prepare($sql);
        $create_tweets->execute();

        $sql = "CREATE TABLE IF NOT EXISTS " . quoteIdent($bin_name . "_urls") . " (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tweet_id` bigint(20) NOT NULL,
            `created_at` datetime,
            `from_user_name` varchar(255),
            `from_user_id` bigint,
            `url` varchar(2048),
            `url_expanded` varchar(2048),
            `url_followed` varchar(4096),
            `domain` varchar(2048),
            `error_code` varchar(64),
            PRIMARY KEY (`id`),
                    KEY `tweet_id` (`tweet_id`),                
                    KEY `created_at` (`created_at`),
                    KEY `from_user_id` (`from_user_id`),
                    KEY `url_followed` (`url_followed`),
                    KEY `url_expanded` (`url_expanded`)
            ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET=utf8mb4";

        $create_urls = $dbh->prepare($sql);
        $create_urls->execute();

        $sql = "CREATE TABLE IF NOT EXISTS " . quoteIdent($bin_name . "_media") . " (
            `id` bigint(20) NOT NULL,
            `tweet_id` bigint(20) NOT NULL,
            `url` varchar(2048),
            `url_expanded` varchar(2048),
            `media_url_https` varchar(2048),
            `media_type` varchar(32),
            `photo_size_width` int(11),
            `photo_size_height` int(11),
            `photo_resize` varchar(32),
            `indice_start` int(11),
            `indice_end` int(11),
            PRIMARY KEY (`id`, `tweet_id`),
                    KEY `media_url_https` (`media_url_https`),
                    KEY `media_type` (`media_type`),
                    KEY `photo_size_width` (`photo_size_width`),
                    KEY `photo_size_height` (`photo_size_height`),
                    KEY `photo_resize` (`photo_resize`)
            ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET=utf8mb4";

        $create_media = $dbh->prepare($sql);
        $create_media->execute();
        $dbh = false;

        return TRUE;
    } catch (PDOException $e) {
        $errorMessage = $e->getCode() . ': ' . $e->getMessage();
        return $errorMessage;
    }
}

function create_admin() {
    $dbh = pdo_connect();
    $sql = "CREATE TABLE IF NOT EXISTS tcat_query_bins (
    `id` INT NOT NULL AUTO_INCREMENT,
    `querybin` VARCHAR(45) NOT NULL,
    `type` VARCHAR(10) NOT NULL,
    `active` BOOLEAN NOT NULL,
    `access` INT DEFAULT 0,
    `comments` VARCHAR(2048) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `querybin` (`querybin`),
    KEY `type` (`type`),
    KEY `active` (`active`)
    ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET = utf8mb4";
    $create = $dbh->prepare($sql);
    $create->execute();

    $sql = "CREATE TABLE IF NOT EXISTS tcat_query_bins_periods (
    `id` INT NOT NULL AUTO_INCREMENT,
    `querybin_id` INT NOT NULL,
    `starttime` DATETIME NULL,
    `endtime` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `querybin_id` (`querybin_id`),
    KEY `starttime` (`starttime`),
    KEY `endtime` (`endtime`)
    ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET = utf8mb4";
    $create = $dbh->prepare($sql);
    $create->execute();

    $sql = "CREATE TABLE IF NOT EXISTS tcat_query_phrases (
    `id` INT NOT NULL AUTO_INCREMENT,
    `phrase` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `phrase` (`phrase`)
    ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET = utf8mb4";
    $create = $dbh->prepare($sql);
    $create->execute();

    $sql = "CREATE TABLE IF NOT EXISTS tcat_query_users (
    `id` bigint NOT NULL,
    `user_name` varchar(255),
    PRIMARY KEY `id` (`id`)
    ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET = utf8mb4";
    $create = $dbh->prepare($sql);
    $create->execute();

    $sql = "CREATE TABLE IF NOT EXISTS tcat_query_bins_phrases (
    `id` INT NOT NULL AUTO_INCREMENT,
    `starttime` DATETIME NULL,
    `endtime` DATETIME NULL,
    `phrase_id` INT NULL,
    `querybin_id` INT NULL,
    PRIMARY KEY (`id`),
    KEY `starttime` (`starttime`),
    KEY `endtime` (`endtime`),
    KEY `phrase_id` (`phrase_id`),
    KEY `querybin_id` (`querybin_id`)
    ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET = utf8mb4";
    $create = $dbh->prepare($sql);
    $create->execute();

    $sql = "CREATE TABLE IF NOT EXISTS tcat_query_bins_users (
    `id` INT NOT NULL AUTO_INCREMENT,
    `starttime` DATETIME NULL,
    `endtime` DATETIME NULL,
    `user_id` BIGINT NULL,
    `querybin_id` INT NULL,
    PRIMARY KEY (`id`),
    KEY `starttime` (`starttime`),
    KEY `endtime` (`endtime`),
    KEY `user_id` (`user_id`),
    KEY `querybin_id` (`querybin_id`)
    ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET = utf8mb4";
    $create = $dbh->prepare($sql);
    $create->execute();

    $sql = "CREATE TABLE IF NOT EXISTS tcat_controller_tasklist (
    `id` BIGINT AUTO_INCREMENT,
    `task` VARCHAR(32) NOT NULL,
    `instruction` VARCHAR(255) NOT NULL,
    `ts_issued` timestamp DEFAULT current_timestamp,
    primary key(id) ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET = utf8mb4";
    $create = $dbh->prepare($sql);
    $create->execute();

    // 03/03/2015 Add comments column [fast auto-upgrade - reminder to remove]
    $query = "SHOW COLUMNS FROM tcat_query_bins";
    $rec = $dbh->prepare($query);
    $rec->execute();
    $columns = $rec->fetchAll(PDO::FETCH_COLUMN);
    $update = TRUE;
    foreach ($columns as $i => $c) {
        if ($c == 'comments') {
            $update = FALSE;
            break;
        }
    }
    if ($update) {
        // Adding new comments column to table tcat_query_bins
        $query = "ALTER TABLE tcat_query_bins ADD COLUMN `comments` varchar(2048) DEFAULT NULL";
        $rec = $dbh->prepare($query);
        $rec->execute();
    }

    // 31/05/2016 Add access column, remove visibility column [fast auto-upgrade - reminder to remove]
    $query = "SHOW COLUMNS FROM tcat_query_bins";
    $rec = $dbh->prepare($query);
    $rec->execute();
    $columns = $rec->fetchAll(PDO::FETCH_COLUMN);
    $update = FALSE;
    foreach ($columns as $i => $c) {
        if ($c == 'visible') {
            $update = TRUE;
            break;
        }
    }
    if ($update) {
        // Adding new columns to table tcat_query_bins
        $query = "ALTER TABLE tcat_query_bins ADD COLUMN `access` INT DEFAULT 0";
        $rec = $dbh->prepare($query);
        $rec->execute();
        $query = "UPDATE tcat_query_bins SET access = " . TCAT_QUERYBIN_ACCESS_OK . " where visible = TRUE";
        $rec = $dbh->prepare($query);
        $rec->execute();
        $query = "UPDATE tcat_query_bins SET access = " . TCAT_QUERYBIN_ACCESS_INVISIBLE . " where visible = FALSE";
        $rec = $dbh->prepare($query);
        $rec->execute();
        $query = "ALTER TABLE tcat_query_bins DROP COLUMN `visible`";
        $rec = $dbh->prepare($query);
        $rec->execute();

    }

    // 05/05/2016 Create a global lookup table to matching phrases to tweets
    // Thanks to this table we know how many (unique or non-unique) tweets were the result of querying the phrase.
    // This is used to estimate in (analysis/mod.ratelimits.php) how many tweets may have been ratelimited for bins associated with the phrase
    $sql = "CREATE TABLE IF NOT EXISTS tcat_captured_phrases (
    `tweet_id` BIGINT(20) NOT NULL,
    `phrase_id` BIGINT(20) NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`tweet_id`, `phrase_id`),
    KEY `created_at` (`created_at`) ) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET = utf8mb4";
    $create = $dbh->prepare($sql);
    $create->execute();

    $dbh = false;

}

/*
 * This function imports the MySQL timezone data neccessary to make the convert_tz() function work. On Debian/Ubuntu systems the timezone data is not
 * loaded by default, as is evident from the result of the following query, which unexpectedly is NULL:
 * SELECT convert_tz(now(), 'SYSTEM', 'UTC');
 *
 * Our function first tests (quickly) whether timezone data is available, and otherwise imports it.
 *
 * See also: http://stackoverflow.com/questions/9808160/mysql-time-zones
 */
function import_mysql_timezone_data() {
    global $dbuser, $dbpass, $database, $hostname;

    $dbh = pdo_connect();

    $sql = "SELECT convert_tz(now(), 'SYSTEM', 'UTC') as available";
    $test = $dbh->prepare($sql);
    $test->execute();
    if ($res = $test->fetch()) {
        if (array_key_exists('available', $res) && is_string($res['available'])) {
            return true;    // we already have the timezone data
        }
    }
    if (!file_exists('/usr/share/zoneinfo') || !is_executable('/usr/bin/mysql_tzinfo_to_sql')) {
        return false;       // we cannot import timezone data (unknown OS?)
    }

    // Connect to MySQL meta information database
    try {
        $dbh_mysql = new PDO("mysql:host=$hostname;dbname=mysql;charset=utf8mb4", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
    } catch (Exception $e) {
        if ($e->getCode() == 1044) {
            // Access denied (probably the connecting user does not have sufficient privileges)
            return false;
        }
    }
    $dbh_mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Run the MySQL tool to convert Unix timezones into MySQL format, read its output using popen()
    // and then execute its output as a query. We could opt for piping directly to the mysql command line tool,
    // but this is probably a bit more secure (no need to transfer passwords to the command-line)
    $cmdhandle = popen("/usr/bin/mysql_tzinfo_to_sql /usr/share/zoneinfo", "r");
    $query = "";
    while ($buf = fread($cmdhandle, 2048)) {
        $query .= $buf;
    }
    pclose($cmdhandle);
    $import = $dbh_mysql->prepare($query);
    $import->execute();
    return true;
}

/*
 * Record any ratelimit disturbance as it happened in the last minute
 */

function ratelimit_record($ratelimit) {
    $dbh = pdo_connect();
    $sql = "insert into tcat_error_ratelimit ( type, start, end, tweets ) values ( :type, date_sub(date_sub(now(), interval second(now()) second), interval 1 minute), date_sub(now(), interval second(now()) second), :ratelimit)";
    $h = $dbh->prepare($sql);
    $type = CAPTURE;
    $h->bindParam(":type", $type, PDO::PARAM_STR);
    $h->bindParam(":ratelimit", $ratelimit, PDO::PARAM_INT);
    $h->execute();
    $dbh = false;
}

/*
 * Zero non-existing ratelimit table rows backwards-in-time
 */

function ratelimit_holefiller($minutes) {
    if ($minutes <= 1) return;
    $dbh = pdo_connect();
    for ($i = 2; $i <= $minutes; $i++) {

        // test if a rate limit record already exists in the database, and if so: break

        $sql = "select count(*) as cnt from tcat_error_ratelimit where type = '" . CAPTURE . "' and 
                        start >= date_sub(date_sub(date_sub(now(), interval $i minute), interval second(date_sub(now(), interval $i minute)) second), interval 1 minute) and
                        end <= date_sub(date_sub(now(), interval " . ($i - 1) . " minute), interval second(date_sub(now(), interval " . ($i - 1) . " minute)) second)";
        $h = $dbh->prepare($sql);
        $h->execute();
        while ($res = $h->fetch()) {
            if (array_key_exists('cnt', $res) && $res['cnt'] > 0) {
                // finished
                $dbh = false;
                return;
            }
        }

        // fill in the hole

        $sql = "insert into tcat_error_ratelimit ( type, start, end, tweets ) values ( :type, date_sub(date_sub(date_sub(now(), interval $i minute), interval second(date_sub(now(), interval $i minute)) second), interval 1 minute), date_sub(date_sub(now(), interval " . ($i - 1) . " minute), interval second(date_sub(now(), interval " . ($i - 1) . " minute)) second), 0)";
        $h = $dbh->prepare($sql);
        $type = CAPTURE;
        $h->bindParam(":type", $type, PDO::PARAM_STR);
        $h->execute();

    }
    $dbh = false;
}

/*
 * Record a gap in the data
 */

function gap_record($role, $ustart, $uend) {
    if ($uend <= $ustart) {
        return FALSE;
    }
    // A less than IDLETIME gap doesn't make sense te record, because we assume IDLETIME seconds to be a legitimate timeframe
    // up to which we don't expect data from Twitter
    $gap_in_seconds = $uend - $ustart;
    if (!defined('IDLETIME')) {
        define('IDLETIME', 600);
    }
    if (!defined('IDLETIME_FOLLOW')) {
        define('IDLETIME_FOLLOW', IDLETIME);
    }
    if ($role == 'follow') {
        $idletime = IDLETIME_FOLLOW;
    } else {
        $idletime = IDLETIME;
    }
    if ($role == 'follow' && $gap_in_seconds < IDLETIME_FOLLOW ||
        $role != 'follow' && $gap_in_seconds < IDLETIME) {
        return FALSE;
    }
    $dbh = pdo_connect();

    $sql = "select 1 from tcat_error_gap where type = :role and start = FROM_UNIXTIME(:start)";
    $h = $dbh->prepare($sql);
    $h->bindParam(":role", $role, PDO::PARAM_STR);
    $h->bindParam(":start", $ustart, PDO::PARAM_STR);
    $h->execute();
    if ($h->execute() && $h->rowCount() > 0) {
        // Extend an existing gap record
        $sql = "update tcat_error_gap set end = FROM_UNIXTIME(:end) where type = :role and start = FROM_UNIXTIME(:start)";
    } else {
        // Insert a new gap record
        $sql = "insert into tcat_error_gap ( type, start, end ) values ( :role, FROM_UNIXTIME(:start), FROM_UNIXTIME(:end) )";
    }
    $h = $dbh->prepare($sql);
    $h->bindParam(":role", $role, PDO::PARAM_STR);
    $h->bindParam(":start", $ustart, PDO::PARAM_STR);
    $h->bindParam(":end", $uend, PDO::PARAM_STR);
    $h->execute();
}

/*
 * Inform administrator of ratelimit problems
 */

function ratelimit_report_problem() {
    $dbh = pdo_connect();
    if (defined('RATELIMIT_MAIL_HOURS') && RATELIMIT_MAIL_HOURS > 0) {
        $sql = "select count(*) as cnt from tcat_status where variable = 'email_ratelimit' and value > (now() - interval " . RATELIMIT_MAIL_HOURS . " hour)";
        $rec = $dbh->prepare($sql);
        if ($rec->execute() && $rec->rowCount() > 0) {
            if ($row = $rec->fetch()) {
                if (isset($row['cnt']) && $row['cnt'] == 0) {
                    /* send e-mail and register time of the action */
                    $sql = "delete from tcat_status where variable = 'email_ratelimit'";
                    $rec = $dbh->prepare($sql);
                    $rec->execute();
                    $sql = "insert into tcat_status ( variable, value ) values ( 'email_ratelimit', now() )";
                    $rec = $dbh->prepare($sql);
                    $rec->execute();
                    global $mail_to;
                    mail($mail_to, 'DMI-TCAT rate limit has been reached (server: ' . getHostName() . ')', 'The script running the ' . CAPTURE . ' query has hit a rate limit while talking to the Twitter API. Twitter is not allowing you to track more than 1% of its total traffic at any time. This means that the number of tweets exceeding the barrier are being dropped. Consider reducing the size of your query bins and reducing the number of terms and users you are tracking.' . "\n\n" .
                            'This may be a temporary or a structural problem. Please look at the webinterface for more details. Rate limit statistics on the website are historic, however. Consider this message indicative of a current issue. This e-mail will not be repeated for at least ' . RATELIMIT_MAIL_HOURS . ' hours.', 'From: no-reply@dmitcat');
                }
            }
        }
    }
}

function toDateTime($unixTimestamp) {
    return date("Y-m-d H:i:s", intval($unixTimestamp));
}

/*
 * Inform controller a task wants to update its queries 
 */

function web_reload_config_role($role) {
    $dbh = pdo_connect();
    $sql = "CREATE TABLE IF NOT EXISTS tcat_controller_tasklist ( id bigint auto_increment, task varchar(32) not null, instruction varchar(255) not null, ts_issued timestamp default current_timestamp, primary key(id) )";
    $h = $dbh->prepare($sql);
    if (!$h->execute())
        return false;
    $sql = "INSERT INTO tcat_controller_tasklist ( task, instruction ) VALUES ( :role, 'reload')";
    $h = $dbh->prepare($sql);
    $h->bindParam(":role", $role, PDO::PARAM_STR);
    return $h->execute();
}

/*
 * Inform controller we want TCAT to auto-upgrade
 */

function tcat_autoupgrade() {
    $dbh = pdo_connect();
    $sql = "CREATE TABLE IF NOT EXISTS tcat_controller_tasklist ( id bigint auto_increment, task varchar(32) not null, instruction varchar(255) not null, ts_issued timestamp default current_timestamp, primary key(id) )";
    $h = $dbh->prepare($sql);
    if (!$h->execute())
        return false;
    $sql = "INSERT INTO tcat_controller_tasklist ( task, instruction ) VALUES ( 'tcat', 'upgrade')";
    $h = $dbh->prepare($sql);
    return $h->execute();
}


/*
 * Acquire a lock as script $script
 * If test is true, only test if the lock could be gained, but do not hold on to it (this is how we test if a script is running)
 * Returns true on a lock success (in test), false on failure and a lock filepointer if really locking
 */

function script_lock($script, $test = false) {
    $lockfile = __DIR__ . "/../../proc/$script.lock";

    if (!file_exists($lockfile)) {
        touch($lockfile);
    }
    $lockfp = fopen($lockfile, "r+");

    if (flock($lockfp, LOCK_EX | LOCK_NB)) {  // acquire an exclusive lock
        ftruncate($lockfp, 0);      // truncate file
        fwrite($lockfp, "Locked task '$script' on: " . date("D M d, Y G:i") . "\n");
        fflush($lockfp);            // flush output
        if ($test) {
            flock($lockfp, LOCK_UN);
            fclose($lockfp);
            unlink($lockfile);
            return true;
        }
        return $lockfp;
    } else {
        fclose($lockfp);
        return false;
    }
}

function logit($file, $message) {
    $message = date("Y-m-d H:i:s") . "\t" . $message . "\n";
    if ($file == "cli")
        print $message;
    else
        file_put_contents(__DIR__ . "/../../logs/" . $file, $message, FILE_APPEND);
}

/*
 * Returns the git status information for the local install
 */
function getGitLocal() {
    $gitcmd = 'git --git-dir ' . realpath(__DIR__ . '/../..') .  '/.git log --pretty=oneline -n 1';
    $gitlog = `$gitcmd`;
    $parse = rtrim($gitlog);
    if (preg_match("/^([a-z0-9]+)[\t ](.+)$/", $parse, $matches)) {
        $commit = $matches[1];
        $mesg = $matches[2];
        $gitcmd = 'git --git-dir ' . realpath(__DIR__ . '/../..') . '/.git rev-parse --abbrev-ref HEAD';
        $gitrev = `$gitcmd`;
        $branch = rtrim($gitrev);
        return array( 'branch' => $branch,
                      'commit' => $commit,
                      'mesg' => $mesg );
    }
    return false;
}

/*
 * Returns the git status information for the github repository
 * If compare_local_commit is set, we will not walk back the tree to count remarks about upgrades, etc.
 */
function getGitRemote($compare_local_commit = '', $branch = 'master') {

    if (!function_exists('curl_init')) return false;

    if (!defined('REPOSITORY_URL')) {
        $repository_url = 'https://github.com/digitalmethodsinitiative/dmi-tcat';
    } else {
        $repository_url = REPOSITORY_URL;
    }

    // Convert repository URL into a GitHub API URL

    $rep_prefix = 'https://github.com/';
    $api_prefix = 'https://api.github.com/repos/';

    if (substr($repository_url, 0, strlen($rep_prefix)) != $rep_prefix) {
        return false; // not a GitHub repository URL
    }
    $api_url = $api_prefix . substr($repository_url, strlen($rep_prefix));
    if (substr($api_url, -4) == '.git') {
        $api_url = substr($api_url, 0, strlen($api_url) - 4); // remove '.git'
    }
    $api_url .= '/commits?sha=' . urlencode($branch);

    // Request it

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'DMI-TCAT GIT remote version checker (contact us at https://github.com/digitalmethodsinitiative/dmi-tcat)');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);

    // Decode it

    $data = json_decode($output, true);
    if (! is_array($data)) {
        return false;
    }

    $commit = $mesg = $url = $date = null;
    $required = false;
    foreach ($data as $ent) {
        if (! is_array($ent)) {
            return false;
        }
        if ($commit === null) {
            $commit = $ent['sha'];
            $mesg = $ent['commit']['message'];
            $url = $ent['html_url'];
            $date = $ent['commit']['committer']['date'];
        }
        if ($ent['sha'] == $compare_local_commit) { break; }
        if (isset($ent['commit']['message'])) {
            if (stripos($ent['commit']['message'], '[required]') !== FALSE) {
                $required = true; break;
            }
        }
    }
    if ($commit === null) {
        return false;
    }
    return array( 'commit' => $commit,
                  'mesg' => $mesg,
                  'url' => $url,
                  'required' => $required,
                  'date' => $date,
                );
}

function getActivePhrases() {
    $dbh = pdo_connect();
    $sql = "SELECT DISTINCT(p.phrase) FROM tcat_query_phrases p, tcat_query_bins_phrases bp, tcat_query_bins b
                                      WHERE bp.endtime = '0000-00-00 00:00:00' AND p.id = bp.phrase_id
                                            AND bp.querybin_id = b.id AND b.type != 'geotrack' AND b.active = 1
                                            AND ( b.access = " . TCAT_QUERYBIN_ACCESS_OK . " or b.access = " . TCAT_QUERYBIN_ACCESS_WRITEONLY . " or b.access = " . TCAT_QUERYBIN_ACCESS_INVISIBLE . ")";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    $results = $rec->fetchAll(PDO::FETCH_COLUMN);
    foreach ($results as $k => $v)
        $results[$k] = trim(preg_replace("/'/", "", $v));
    $results = array_unique($results);
    $dbh = false;
    return $results;
}

/*
 * What type is a bin (track, geotrack, follow, onepercent)
 */

function getBinType($binname, $dbh = null) {
    $dbh_parm = is_null($dbh) ? false : true;
    if (!$dbh_parm) {
        $dbh = pdo_connect();
    }
    $sql = "SELECT querybin, `type` FROM tcat_query_bins WHERE querybin = :querybin";
    $rec = $dbh->prepare($sql);
    $rec->bindParam(':querybin', $binname);
    $rec->execute();
    if ($rec->execute() && $rec->rowCount() > 0) {
        while ($res = $rec->fetch()) {
            // This enforces a case-sensitive comparison without having to explicitely detect the collation any tables (utf8 or utf8mb4)
            if ($res['querybin'] == $binname) {
                return $res['type'];
            }
        }
    }
    if (!$dbh_parm) {
        $dbh = false;
    }
    return false;
}

/*
 * How many, if any, geobins are active?
 */

function geobinsActive() {
    $dbh = pdo_connect();
    $sql = "SELECT COUNT(*) AS cnt FROM tcat_query_bins WHERE `type` = 'geotrack' and active = 1 and ( access = " . TCAT_QUERYBIN_ACCESS_OK . " or access = " . TCAT_QUERYBIN_ACCESS_WRITEONLY . " or access = " . TCAT_QUERYBIN_ACCESS_INVISIBLE . " )";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    $results = $rec->fetchAll(PDO::FETCH_COLUMN);
    foreach ($results as $k => $v) {
        if ($k == 'cnt' && $v > 0) {
            return true;
        }
    }
    $dbh = false;
    return false;
}

/*
 * Does a longitude, latitude coordinate pair fit into a geobox (defined in the order southwest, northeast)?
 */

function coordinatesInsideBoundingBox($point_lng, $point_lat, $sw_lng, $sw_lat, $ne_lng, $ne_lat) {
    $boxwkt = 'POLYGON((' . $sw_lng . ' ' . $sw_lat . ', '
            . $sw_lng . ' ' . $ne_lat . ', '
            . $ne_lng . ' ' . $ne_lat . ', '
            . $ne_lng . ' ' . $sw_lat . ', '
            . $sw_lng . ' ' . $sw_lat . '))';
    $pointwkt = 'POINT(' . $point_lng . ' ' . $point_lat . ')';
    $geobox = geoPHP::load($boxwkt, 'wkt');
    $geopoint = geoPHP::load($pointwkt, 'wkt');
    return $geobox->contains($geopoint);
}

// Get the area of a specific geophrase
function geoPhraseArea($geophrase) {
    $box = explode(",", $geophrase);
    $sw_lng = $box[0]; $sw_lat = $box[1]; $ne_lng = $box[2]; $ne_lat = $box[3];
    $boxwkt = 'POLYGON((' . $sw_lng . ' ' . $sw_lat . ', '
            . $sw_lng . ' ' . $ne_lat . ', '
            . $ne_lng . ' ' . $ne_lat . ', '
            . $ne_lng . ' ' . $sw_lat . ', '
            . $sw_lng . ' ' . $sw_lat . '))';
    $geobox = geoPHP::load($boxwkt, 'wkt');
    return $geobox->area();
}

/*
 * Create a full location query string from multiple coordinate 'phrases' (geobox phrases)
 */

function getActiveLocationsImploded() {
    $dbh = pdo_connect();
    $sql = "SELECT phrase FROM tcat_query_phrases p, tcat_query_bins_phrases bp, tcat_query_bins b
                                      WHERE bp.endtime = '0000-00-00 00:00:00' AND p.id = bp.phrase_id
                                            AND bp.querybin_id = b.id AND b.type = 'geotrack' AND b.active = 1
                                            AND ( b.access = " . TCAT_QUERYBIN_ACCESS_OK . " or b.access = " . TCAT_QUERYBIN_ACCESS_WRITEONLY . " or b.access = " . TCAT_QUERYBIN_ACCESS_INVISIBLE . ")";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    $results = $rec->fetchAll(PDO::FETCH_COLUMN);
    $locations = '';
    foreach ($results as $k => $v) {
        $locations .= $v . ",";
    }
    $locations = substr($locations, 0, -1);
    $dbh = false;
    return $locations;
}

/*
 * Explode a geo location query string to an associative array of arrays
 * 
 * (0) => array ( sw_lng => ..
 *                sw_lat => ..
 *                ne_lng => ..
 *                ne_lat => .. )
 * (1) => ..etc
 */

function getGeoBoxes($track) {
    $boxes = array();
    $exp = explode(",", $track);
    $latitude_expected = false;
    $sw = true;
    $box = array();
    foreach ($exp as $e) {
        if ($latitude_expected) {
            $lat = $e;
            if ($sw) {
                $box['sw_lng'] = $lng;
                $box['sw_lat'] = $lat;
                $sw = false;
            } else {
                $box['ne_lng'] = $lng;
                $box['ne_lat'] = $lat;
                $boxes[] = $box;
                $box = array();
                $sw = true;
            }
        } else {
            $lng = $e;
        }
        $latitude_expected = !$latitude_expected;
    }
    return $boxes;
}

function getActiveUsers() {
    $dbh = pdo_connect();
    $sql = "SELECT DISTINCT(u.id) FROM tcat_query_users u, tcat_query_bins_users bu WHERE bu.endtime = '0000-00-00 00:00:00' AND u.id = bu.user_id";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    $results = $rec->fetchAll(PDO::FETCH_COLUMN);
    foreach ($results as $k => $v)
        $results[$k] = trim($v);
    $results = array_unique($results);
    $dbh = false;
    return $results;
}

function getActiveTrackBins() {
    $dbh = pdo_connect();
    $sql = "SELECT b.querybin, p.phrase FROM tcat_query_bins b, tcat_query_phrases p, tcat_query_bins_phrases bp WHERE b.active = 1 AND bp.querybin_id = b.id AND bp.phrase_id = p.id AND bp.endtime = '0000-00-00 00:00:00' and ( b.access = " . TCAT_QUERYBIN_ACCESS_OK . " or b.access = " . TCAT_QUERYBIN_ACCESS_WRITEONLY . " or b.access = " . TCAT_QUERYBIN_ACCESS_INVISIBLE . " )";
    $rec = $dbh->prepare($sql);
    $querybins = array();
    if ($rec->execute() && $rec->rowCount() > 0) {
        while ($res = $rec->fetch()) {
            $querybins[$res['querybin']][$res['phrase']] = preg_replace("/'/", "", $res['phrase']);
        }
    }
    $dbh = false;
    return $querybins;
}

// This function returns a phrase_string:phrase_id associative array.

function getActivePhraseIds() {
    $dbh = pdo_connect();
    $sql = "SELECT p.phrase as phrase, p.id as id FROM tcat_query_bins b, tcat_query_phrases p, tcat_query_bins_phrases bp WHERE b.active = 1 AND bp.querybin_id = b.id AND bp.phrase_id = p.id AND bp.endtime = '0000-00-00 00:00:00' and ( b.access = " . TCAT_QUERYBIN_ACCESS_OK . " or b.access = " . TCAT_QUERYBIN_ACCESS_WRITEONLY . " or b.access = " . TCAT_QUERYBIN_ACCESS_INVISIBLE . " )";
    $rec = $dbh->prepare($sql);
    $phrase_ids = array();
    if ($rec->execute() && $rec->rowCount() > 0) {
        while ($res = $rec->fetch()) {
            $phrase_ids[trim(preg_replace("/'/", "", $res['phrase']))] = $res['id'];
        }
    }
    $dbh = false;
    return $phrase_ids;
}

function getActiveFollowBins() {
    $dbh = pdo_connect();
    $sql = "SELECT b.querybin, u.id AS uid FROM tcat_query_bins b, tcat_query_users u, tcat_query_bins_users bu WHERE b.active = 1 AND bu.querybin_id = b.id AND bu.user_id = u.id AND bu.endtime = '0000-00-00 00:00:00' and ( b.access = " . TCAT_QUERYBIN_ACCESS_OK . " or b.access = " . TCAT_QUERYBIN_ACCESS_WRITEONLY . " or b.access = " . TCAT_QUERYBIN_ACCESS_INVISIBLE . " )";
    $rec = $dbh->prepare($sql);
    $querybins = array();
    if ($rec->execute() && $rec->rowCount() > 0) {
        while ($res = $rec->fetch()) {
            $querybins[$res['querybin']][$res['uid']] = $res['uid'];
        }
    }
    $dbh = false;
    return $querybins;
}

function getActiveOnepercentBin() {
    $dbh = pdo_connect();
    $sql = "select querybin from tcat_query_bins where type = 'onepercent' and active = 1 and ( access = " . TCAT_QUERYBIN_ACCESS_OK . " or access = " . TCAT_QUERYBIN_ACCESS_WRITEONLY . " or access = " . TCAT_QUERYBIN_ACCESS_INVISIBLE . " )";
    $rec = $dbh->prepare($sql);
    $querybins = array();
    if ($rec->execute() && $rec->rowCount() > 0) {
        while ($res = $rec->fetch()) {
            $querybins[$res['querybin']] = '';      // no value neccessary
            break;     // only one bin should be act{ive
        }
    }
    $dbh = false;
    return $querybins;
}

function getActiveBins() {
    if (!defined('CAPTURE')) {
        // situation: import scripts
        $none = array();
        return $none;
    }
    if (CAPTURE == "track") {
        $querybins = getActiveTrackBins();
    } elseif (CAPTURE == "follow") {
        $querybins = getActiveFollowBins();
    } elseif (CAPTURE == "onepercent") {
        $querybins = getActiveOnepercentBin();
    }
    return $querybins;
}

function getAllBins() {
    $dbh = pdo_connect();
    $sql = "select querybin from tcat_query_bins";
    $rec = $dbh->prepare($sql);
    $querybins = array();
    if ($rec->execute() && $rec->rowCount() > 0) {
        while ($res = $rec->fetch()) {
            $querybins[] = $res['querybin'];
        }
    }
    $dbh = false;
    return $querybins;
}


function queryManagerBinExists($binname, $cronjob = false) {
    $dbh = pdo_connect();
    $rec = $dbh->prepare("SELECT id FROM tcat_query_bins WHERE querybin = :binname");
    $rec->bindParam(":binname", $binname, PDO::PARAM_STR);
    if ($rec->execute() && $rec->rowCount() > 0) { // check whether the table has already been imported
        $res = $rec->fetch();
        if ($cronjob == false) {
            print "The query bin '$binname' already exists. Are you sure you want to add tweets to '$binname'? (yes/no)" . PHP_EOL;
            if (trim(fgets(fopen("php://stdin", "r"))) != 'yes')
                die('Abort' . PHP_EOL);
        }
        return $res['id'];
    }
    return false;
}

function queryManagerCreateBinFromExistingTables($binname, $querybin_id, $type, $queries = array()) {
    $dbh = pdo_connect();

    // select start and end of dataset
    // note: this is information may not be available when this function is being called, see queryManagerSetPeriodsOnCreation()
    $sql = "SELECT min(created_at) AS min, max(created_at) AS max FROM " . $binname . "_tweets";
    $rec = $dbh->prepare($sql);
    if (!$rec->execute() || !$rec->rowCount())
        die("could not find " . $binname . "_tweets" . PHP_EOL);
    $res = $rec->fetch();
    $starttime = $res['min'];
    $endtime = $res['max'];

    // create bin in query manager
    if ($querybin_id === false)
        $querybin_id = queryManagerCreateBin($binname, $type, $starttime, $endtime, 0);

    // retrieve users from timeline capture
    if (($type == 'timeline' || $type == "import timeline") && empty($queries)) {
        $rec = $dbh->prepare("SELECT DISTINCT(from_user_id) FROM " . $binname . "_tweets");
        if ($rec->execute() && $rec->rowCount() > 0) {
            $queries = $rec->fetchAll(PDO::FETCH_COLUMN);
        }
    }

    if ($type == 'track' || $type == 'search' || $type == "import ytk" || $type == "import track") // insert phrases
        queryManagerInsertPhrases($querybin_id, $queries, $starttime, $endtime);
    elseif ($type == 'follow' || $type == 'timeline' || $type == 'import timeline') {// insert users
        queryManagerInsertUsers($querybin_id, $queries, $starttime, $endtime);
    }
}

function queryManagerCreateBin($binname, $type, $starttime = "0000-00-00 00:00:00", $endtime = "0000-00-00 00:00:00", $active = 0) {
    $dbh = pdo_connect();
    // create querybin in database
    $sql = "INSERT IGNORE INTO tcat_query_bins (querybin,type,active) VALUES (:binname, :type, :active)";
    $rec = $dbh->prepare($sql);
    $rec->bindParam(":binname", $binname, PDO::PARAM_STR);
    $rec->bindParam(":type", $type, PDO::PARAM_STR);
    $rec->bindParam(":active", $active, PDO::PARAM_BOOL);
    if (!$rec->execute() || !$rec->rowCount())
        die("failed to insert $binname\n");
    $querybin_id = $dbh->lastInsertId();

    // insert querybin period
    $sql = "INSERT INTO tcat_query_bins_periods (querybin_id,starttime,endtime) VALUES (:querybin_id, :starttime, :endtime)";
    $rec = $dbh->prepare($sql);
    $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
    $rec->bindParam(":starttime", $starttime, PDO::PARAM_STR);
    $rec->bindParam(":endtime", $endtime, PDO::PARAM_STR);
    if (!$rec->execute() || !$rec->rowCount())
        die("could not insert period for $binname with id $querybin_id\n");
    $dbh = false;
    return $querybin_id;
}

/*
 * In CLI scripts such as search.php, we typically create the query bin and their associated phrases before we start capturing.
 * This has become neccessary because we want to fill the tcat_captured_phrases table as we progress (matching a captured tweet id to a specific phrase id).
 * Because we would later like to have the full period information (start and end time) available, we must call the queryManagerSetOnCreation() function
 * after having filled the bin with data.
 */
function queryManagerSetPeriodsOnCreation($binname, $queries = array()) {
    $dbh = pdo_connect();
    $querybin_id = queryManagerBinExists($binname, true);
    if ($querybin_id !== false) {
        print "[debug] querybin_id = $querybin_id\n";
        /* first update the period information for the entire query bin */
        $sql = "SELECT min(created_at) AS starttime, max(created_at) AS endtime FROM " . quoteIdent($binname . "_tweets");
        $rec = $dbh->prepare($sql);
        $starttime = $endtime = false;
        if ($rec->execute() || $rec->rowCount()) {
            $res = $rec->fetch();
            $starttime = $res['starttime'];
            $endtime = $res['endtime'];
            if (gettype($starttime) == "NULL" || gettype($endtime) == "NULL") { return; }
            $sql = "UPDATE tcat_query_bins_periods SET starttime = :starttime, endtime = :endtime WHERE querybin_id = :querybin_id";
            print "[debug] $sql ($querybin_id, $starttime, $endtime)\n";
            $rec = $dbh->prepare($sql);
            $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
            $rec->bindParam(":starttime", $starttime, PDO::PARAM_STR);
            $rec->bindParam(":endtime", $endtime, PDO::PARAM_STR);
            $rec->execute();
        }
        if ($starttime !== false && $endtime !== false) {

            if (is_array($queries) && count($queries) > 0) {

                // This is a bin of type search or track

                foreach ($queries as $query) {
                    $trimquery = trim($query);
                    /* get the phrase id for this query */
                    $phrase_id = false;
                    $sql = "SELECT TQBP.phrase_id as phrase_id FROM tcat_query_bins_phrases TQBP INNER JOIN tcat_query_phrases TQP ON TQP.id = TQBP.phrase_id WHERE TQBP.querybin_id = :querybin_id AND TQP.phrase = :phrase";
                    print "[debug] $sql ($querybin_id, $query)\n";
                    $rec = $dbh->prepare($sql);
                    $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
                    $rec->bindParam(":phrase", $trimquery, PDO::PARAM_STR);
                    if ($rec->execute() && $rec->rowCount() > 0) {
                        if ($res = $rec->fetch()) {
                            $phrase_id = $res['phrase_id'];
                        }
                    } else {
                        // DEBUG
                        print "[debug] NO RESULTS!\n";
                    }
                    /* lookup the specific starttime and endtime inside tcat_captured_phrases table */
                    $p_starttime = $p_endtime = false;
                    if ($phrase_id !== false) {
                        $sql = "SELECT min(created_at) as starttime, max(created_at) as endtime from tcat_captured_phrases where phrase_id = :phrase_id";
                        print "[debug] $sql ($phrase_id)\n";
                        $rec = $dbh->prepare($sql);
                        $rec->bindParam(":phrase_id", $phrase_id, PDO::PARAM_INT);
                        if ($rec->execute() && $rec->rowCount() > 0) {
                            if ($res = $rec->fetch()) {
                                $p_starttime = $res['starttime'];
                                $p_endtime = $res['endtime'];
                            }
                        }
                    }
                    /* If we do not have fine-granularity period information from the tcat_captured_phrases table, use information from entire bin */
                    $use_starttime = (is_string($p_starttime) && strlen($p_starttime) > 0) ? $p_starttime : $starttime;
                    $use_endtime = (is_string($p_endtime) && strlen($p_endtime) > 0) ? $p_endtime : $endtime;
                    /* update the period information for this phrase */
                    $sql = "UPDATE tcat_query_bins_phrases SET starttime = :starttime, endtime = :endtime WHERE querybin_id = :querybin_id AND phrase_id = :phrase_id";
                    print "[debug] $sql ($use_starttime, $use_endtime, $querybin_id, $phrase_id)\n";
                    $rec = $dbh->prepare($sql);
                    $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
                    $rec->bindParam(":phrase_id", $phrase_id, PDO::PARAM_INT);
                    $rec->bindParam(":starttime", $use_starttime, PDO::PARAM_STR);
                    $rec->bindParam(":endtime", $use_endtime, PDO::PARAM_STR);
                    $rec->execute();
                }

            } else {

                $type = getBinType($binname);
                if ($type == 'follow' || $type == 'timeline') {
    
                    // This is a bin of type search or track

                    $user_ids = array();
                    $sql = "SELECT DISTINCT(user_id) AS user_id FROM tcat_query_bins_users WHERE querybin_id = :querybin_id AND user_id IS NOT NULL";
                    $rec = $dbh->prepare($sql);
                    $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
                    if ($rec->execute()) {
                        while ($res = $rec->fetch()) {
                               $user_ids[] = $res['user_id'];
                        }
                    }
                    foreach ($user_ids as $user_id) {
                        $sql = "SELECT min(created_at) AS starttime, max(created_at) AS endtime FROM " . quoteIdent($binname . "_tweets") . " WHERE from_user_id = :user_id";
                        $rec = $dbh->prepare($sql);
$rec->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                        $starttime = $endtime = false;
                        if ($rec->execute() && $rec->rowCount()) {
                            $res = $rec->fetch();
                            $starttime = $res['starttime'];
                            $endtime = $res['endtime'];
                            $sql = "UPDATE tcat_query_bins_users SET starttime = :starttime, endtime = :endtime WHERE querybin_id = :querybin_id AND user_id = :user_id";
                            $rec = $dbh->prepare($sql);
                            $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
                            $rec->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                            $rec->bindParam(":starttime", $starttime, PDO::PARAM_STR);
                            $rec->bindParam(":endtime", $endtime, PDO::PARAM_STR);
                            $rec->execute();
                        }
                    }
                }
            }
        }
    }
}

function queryManagerInsertPhrases($querybin_id, $phrases, $starttime = "0000-00-00 00:00:00", $endtime = "0000-00-00 00:00:00") {
    $dbh = pdo_connect();
    foreach ($phrases as $phrase) {
        $phrase = trim($phrase);
        if (empty($phrase))
            continue;

        // Check if the phrase is known in the tables; in which case we will re-use the id.

        $phrase_id = null;
        $sql = "SELECT * FROM tcat_query_phrases WHERE phrase = :phrase";
        $rec = $dbh->prepare($sql);
        $rec->bindParam(":phrase", $phrase, PDO::PARAM_STR);
        if (!$rec->execute()) 
            die("failed to read from tcat_query_phrases (phrase '$phrase': $sql)\n");
        if ($rec->rowCount() > 0) {
            $result = $rec->fetch(PDO::FETCH_OBJ);
            if ($result) {
                $phrase_id = $result->id;
            }
        }
        if (is_null($phrase_id)) {
            $sql = "INSERT IGNORE INTO tcat_query_phrases (phrase) VALUES (:phrase)";
            $rec = $dbh->prepare($sql);
            $rec->bindParam(':phrase', $phrase, PDO::PARAM_STR); //
            if (!$rec->execute() || !$rec->rowCount())
                die("failed to insert phrase $phrase\n");
            $phrase_id = $dbh->lastInsertId();
        }

        /*
         * We do not insert data into the tcat_query_bins_phrases table if an entry for this user and querybin already exists. 
         * TODO: a future improvement could be to parse $starttime and $endtime, and, if they don't overlap, create a whole new entry in the table
         */

        $sql = "SELECT * FROM tcat_query_bins_phrases WHERE querybin_id = :querybin_id and phrase_id = :phrase_id";
        $rec = $dbh->prepare($sql);
        $rec->bindParam(":phrase_id", $phrase_id, PDO::PARAM_INT);
        $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
        if (!$rec->execute())
            die("failed to read from tcat_query_bins_phrases (phrase_id $phrase_id, querybin_id $querybin_id: $sql)\n");
        if ($rec->rowCount() > 0) {
            // DEBUG
            print "SKIPPING (DEBUG)\n";
            continue;
        }

        $sql = "INSERT INTO tcat_query_bins_phrases (phrase_id,querybin_id,starttime,endtime) VALUES (:phrase_id, :querybin_id, :starttime, :endtime)";
        $rec = $dbh->prepare($sql);
        $rec->bindParam(":phrase_id", $phrase_id, PDO::PARAM_INT);
        $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
        $rec->bindParam(":starttime", $starttime, PDO::PARAM_STR);
        $rec->bindParam(":endtime", $endtime, PDO::PARAM_STR);
        if (!$rec->execute() || !$rec->rowCount())
            die("could not insert into tcat_query_bins_phrases $sql\n");
    }
    $dbh = false;
}

function queryManagerInsertUsers($querybin_id, $users, $starttime = "0000-00-00 00:00:00", $endtime = "0000-00-00 00:00:00") {
    $dbh = pdo_connect();
    foreach ($users as $user_id) {
        $user_id = trim($user_id);
        if (empty($user_id))
            continue;
        /*
         * We do not insert data into the table if an entry for this user and querybin already exists. 
         * TODO: a future improvement could be to parse $starttime and $endtime, and, if they don't overlap, create a whole new entry in the table
         */
        $sql = "SELECT * FROM tcat_query_bins_users WHERE querybin_id = :querybin_id and user_id = :user_id";
        $rec = $dbh->prepare($sql);
        $rec->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
        if (!$rec->execute())
            die("failed to read from tcat_query_bins_users (user_id $user_id, querybin_id $querybin_id: $sql)\n");
        if ($rec->rowCount() > 0) {
            continue;
        }
        $sql = "INSERT IGNORE INTO tcat_query_users (id) VALUES (:user_id)";
        $rec = $dbh->prepare($sql);
        $rec->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        if (!$rec->execute())
            die("failed to insert user_id $user_id: $sql\n");
        $sql = "INSERT INTO tcat_query_bins_users (user_id,querybin_id,starttime,endtime) VALUES (:user_id, :querybin_id, :starttime, :endtime)";
        $rec = $dbh->prepare($sql);
        $rec->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
        $rec->bindParam(":starttime", $starttime, PDO::PARAM_STR);
        $rec->bindParam(":endtime", $endtime, PDO::PARAM_STR);
        if (!$rec->execute() || !$rec->rowCount())
            die("could not insert into tcat_query_bins_users $sql\n");
    }
    $dbh = false;
}

/*
 * This signal handler is installed by the capture scripts.
 */

function capture_flush_buffer() {

    global $capturebucket;

    if (is_array($capturebucket) && is_callable('processtweets')) {
        logit(CAPTURE . ".error.log", "flushing the capture buffer");
        $copy = $capturebucket;         // avoid any parallel processing by copy and then empty
        $capturebucket = array();

        processtweets($copy);
    } else {
        logit(CAPTURE . ".error.log", "failed to flush capture buffer");
    }
}

function capture_signal_handler_term($signo) {

    logit(CAPTURE . ".error.log", "received TERM signal");

    capture_flush_buffer();

    logit(CAPTURE . ".error.log", "exiting now on TERM signal");

    exit(0);
}

function map_screen_names_to_ids($screen_names = array()) {
    global $twitter_keys;
    $map = array();
    // We must do the lookups in chunks of 100 screen names
    $limit = 100;
    for ($i = 0; $i < count($screen_names); $i += $limit) {
        $request = count($screen_names) - $i;
        if ($request > 100) { $request = 100; }
        $subset = array_slice($screen_names, $i, $request);
        $query = implode(",", $subset);
        $keyinfo = getRESTKey(0, 'users', 'lookup');
        $current_key = $keyinfo['key'];
        $ratefree = $keyinfo['remaining'];

        $tmhOAuth = new tmhOAuth(array(
                                'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
                                'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
                                'token' => $twitter_keys[$current_key]['twitter_user_token'],
                                'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
                        ));
        $params = array(
                'screen_name' => $query,
        );

        $tmhOAuth->user_request(array(
                'method' => 'GET',
                'url' => $tmhOAuth->url('1.1/users/lookup'),
                'params' => $params
        ));

        if ($tmhOAuth->response['code'] == 200) {
                $users = json_decode($tmhOAuth->response['response'], true);
                foreach ($users as $user) {
                        $map[$user['screen_name']] = $user['id_str'];
                }
        }
    }
    return $map;
}

function map_ids_to_screen_names($ids = array()) {
    global $twitter_keys;
    $map = array();
    // We must do the lookups in chunks of 100 ids
    $limit = 100;
    for ($i = 0; $i < count($ids); $i += $limit) {
        $request = count($ids) - $i;
        if ($request > 100) { $request = 100; }
        $subset = array_slice($ids, $i, $request);
        $query = implode(",", $subset);
        $keyinfo = getRESTKey(0, 'users', 'lookup');
        $current_key = $keyinfo['key'];
        $ratefree = $keyinfo['remaining'];

        $tmhOAuth = new tmhOAuth(array(
                                'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
                                'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
                                'token' => $twitter_keys[$current_key]['twitter_user_token'],
                                'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
                        ));
        $params = array(
                'user_id' => $query,
        );

        $tmhOAuth->user_request(array(
                'method' => 'GET',
                'url' => $tmhOAuth->url('1.1/users/lookup'),
                'params' => $params
        ));

        if ($tmhOAuth->response['code'] == 200) {
                $users = json_decode($tmhOAuth->response['response'], true);
                foreach ($users as $user) {
                        $map[$user['id_str']] = $user['screen_name'];
                }
        }
    }
    return $map;
}

/**
 * 
 * Tweet entity
 * Based on Twitter API 1.1
 * 
 */
class Tweet {

    public $id;
    public $id_str;
    public $created_at;
    public $from_user_name;
    public $from_user_id;
    public $from_user_lang;
    public $from_user_tweetcount;
    public $from_user_followercount;
    public $from_user_friendcount;
    public $from_user_listed;
    public $from_user_realname;
    public $from_user_utcoffset;
    public $from_user_timezone;
    public $from_user_description;
    public $from_user_url;
    public $from_user_verified;
    public $from_user_profile_image_url;
    public $from_user_created_at;
    public $from_user_withheld_scope;
    public $from_user_favourites_count;
    public $source;
    public $location;
    public $geo_lat;
    public $geo_lng;
    public $text;
    public $retweet_id = null;
    public $retweet_count;
    public $favorite_count;
    public $to_user_id;
    public $to_user_name;
    public $in_reply_to_status_id;
    public $quoted_status_id;
    public $filter_level;
    public $lang;
    public $possibly_sensitive;
    public $truncated;
    public $place_ids;
    public $places;
    public $withheld_in_countries;              // not used as tweet database field
    public $from_user_withheld_in_countries;    // not used as tweet database field
    public $withheld_copyright;
    public $withheld_scope;
    public $contributors;
    public $retweeted;
    public $retweeted_status;
    public $coordinates;
    public $in_reply_to_status_id_str;
    public $in_reply_to_screen_name;
    public $place;
    public $geo;
    public $in_reply_to_user_id;
    public $user_mentions = array();
    public $hashtags = array();
    public $urls = array();
    public $media = array();

    // TODO: import media entities in fromGnip()
    // TODO: investigate how Gnip returns quoted tweets: http://support.gnip.com/sources/twitter/data_format.html
    public static function fromGnip($json) {
        // Parse JSON when fed JSON string
        if (is_string($json)) {
            $object = json_decode($json);
        } else if (is_object($json)) {
            $object = $json;
        } else {
            throw new Exception("Invalid JSON input:\n[[$json]]\n");
        }

        $t = new self(null);
        if (!isset($object->id))
            return false;

        $t->id = preg_replace("/tag:.*:/", "", $object->id);
        $t->id_str = $t->id;
        $t->created_at = $object->postedTime;
        $t->text = $object->body;
        if (isset($object->actor->location))
            $t->location = $object->actor->location->displayName;

        $t->from_user_name = $object->actor->preferredUsername;
        $t->from_user_id = str_replace("id:twitter.com:", "", $object->actor->id);
        $t->from_user_url = $object->actor->link;
        $t->from_user_lang = $object->actor->languages[0];
        $t->from_user_tweetcount = $object->actor->statusesCount;
        $t->from_user_followercount = $object->actor->followersCount;
        $t->from_user_friendcount = $object->actor->friendsCount;
        $t->from_user_favourites_count = $object->actor->favoritesCount;
        $t->from_user_realname = $object->actor->displayName;
        $t->from_user_listed = $object->actor->listedCount;
        $t->from_user_utcoffset = $object->actor->utcOffset;
        $t->from_user_timezone = $object->actor->twitterTimeZone;
        $t->from_user_description = $object->actor->summary;
        $t->from_user_profile_image_url = $object->actor->image;
        $t->from_user_verified = $object->actor->verified;
        $t->from_user_timezone = null;
        if (isset($object->twitterTimeZone))
            $t->from_user_timezone = $object->twitterTimeZone;

        $t->source = $object->generator->displayName;
        if (isset($object->geo) && $object->geo->type == "Point") {
            $t->geo->coordinates[0] = $object->geo->coordinates[0];
            $t->geo->coordinates[1] = $object->geo->coordinates[1];
        }

        $t->in_reply_to_user_id = null; // @todo
        $t->in_reply_to_screen_name = null; // @todo
        $t->in_reply_to_status_id = null; // @todo

        $t->retweet_count = $object->retweetCount;
        if ($t->retweet_count != 0 && isset($object->object)) {
            $t->retweet_id = preg_replace("/tag:.*:/", "", $object->object->id);
        }
        if (isset($object->twitter_filter_level)) {
            $t->filter_level = $object->twitter_filter_level;
        } else {
            $t->filter_level = 'none';
        }
        if (isset($object->twitter_entities->twitter_lang))
            $t->lang = $object->twitter_entities->twitter_lang;

        $t->favorite_count = $object->favoritesCount;

        // @todo: support for setting places in Gnip import
        $t->place_ids = array();
        $t->places = array();
        // @todo: support extended media entities in Gnip import, and setting photo_size_xy, and media_typ
        $t->urls = $object->twitter_entities->urls;
        $t->user_mentions = $object->twitter_entities->user_mentions;
        $t->hashtags = $object->twitter_entities->hashtags;

        if (count($t->user_mentions) > 0) {
            $t->in_reply_to_user_id_str = $t->user_mentions[0]->id_str;
            $t->in_reply_to_screen_name = $t->user_mentions[0]->screen_name;
        }

        if (isset($object->user_withheld->withheld_in_countries)) {
            $t->withheld_in_countries = $object->user_withheld->withheld_in_countries;
        } else {
            $t->withheld_in_countries = array();
        }
        if (isset($object->status_withheld->withheld_in_countries)) {
            $t->from_user_withheld_in_countries = $object->status_withheld->withheld_in_countries;
        } else {
            $t->from_user_withheld_in_countries = array();
        }

        $t->fromComplete();

        return $t;
    }

    // Map Twitter API result object to our database table format
    public function fromJSON($data) {
        $this->id = $data["id_str"];
        $this->created_at = date("Y-m-d H:i:s", strtotime($data["created_at"]));
        $this->from_user_name = $data["user"]["screen_name"];
        $this->from_user_id = $data["user"]["id_str"];
        $this->from_user_lang = $data["user"]["lang"];
        $this->from_user_tweetcount = $data["user"]["statuses_count"];
        $this->from_user_followercount = $data["user"]["followers_count"];
        $this->from_user_friendcount = $data["user"]["friends_count"];
        $this->from_user_listed = $data["user"]["listed_count"];
        $this->from_user_realname = $data["user"]["name"];
        $this->from_user_utcoffset = $data["user"]["utc_offset"];
        $this->from_user_timezone = $data["user"]["time_zone"];
        $this->from_user_description = $data["user"]["description"];
        $this->from_user_url = $data["user"]["url"];
        $this->from_user_verified = $data["user"]["verified"];
        $this->from_user_profile_image_url = $data["user"]["profile_image_url"];
        $this->from_user_created_at = date("Y-m-d H:i:s", strtotime($data["user"]["created_at"]));
        if (isset($data["user"]["withheld_scope"])) {
            $this->from_user_withheld_scope = $data["user"]["withheld_scope"];
        }
        $this->from_user_favourites_count = $data["user"]["favourites_count"];
        $this->source = $data["source"];
        $this->location = $data["user"]["location"];
        /* Deprecated field will now store non-boolean value */
        $this->truncated = -1;
        $this->geo_lat = null;
        $this->geo_lng = null;
        if ($data["geo"] != null) {
            $this->geo_lat = $data["geo"]["coordinates"][0];
            $this->geo_lng = $data["geo"]["coordinates"][1];
        }

        if (!isset($data["text"])) {
            /* running in extended context, text field does not exist in JSON response (default for REST API) */
            $full_text = $data["full_text"];
        } else if (!array_key_exists('extended_tweet', $data)) {
            /* running in compatibility mode BUT the 'extended_tweet' JSON field is not available. this means the tweet is <= 140 characters */
            $full_text = $data["text"];
        } else {
            /*
             * Running in compatibility mode AND the 'extended_tweet' JSON field is available. this means the tweet is > 140 characters and
             * Twitter has put all relevant metadata in a separate hierarchy.
             *
             * See: https://developer.twitter.com/en/docs/tweets/tweet-updates section 'Compatibility mode JSON rendering'
             * and: https://github.com/digitalmethodsinitiative/dmi-tcat/issues/311)
             *
             */
            $full_text = $data["extended_tweet"]["full_text"];
            $data["entities"] = $data["extended_tweet"]["entities"];
            if (array_key_exists("extended_entities", $data["extended_tweet"])) {
                $data["extended_entities"] = $data["extended_tweet"]["extended_entities"];
            }
        }
        
        $store_text = $full_text;

        if (isset($data["retweeted_status"])) {

            /*
             * Incorporate full retweet text from retweeted_status to cope with possible truncated due to character limit.
             * This fix makes the stored text more closely resemble the tweet a shown to the end-user.
             * See the discussions here: https://github.com/digitalmethodsinitiative/dmi-tcat/issues/74
             * See the discussions here: https://github.com/digitalmethodsinitiative/dmi-tcat/issues/363
             *
             * WARNING: this procedure invalidates character indices for usernames inside the tweet text (i.e. where does the username does show up)
             * That data is currently discarded by TCAT however.
             */

            // Determine the full, untruncated retweet text using a similar mechanism as used for non-retweets
            if (!isset($data["retweeted_status"]["text"])) {
                /* running in extended context */
                $retweet_text = $data["retweeted_status"]["full_text"];

                /* add the retweeting user to the user mentions */
                if (!empty($data["retweeted_status"]["entities"]["user_mentions"])) {
                    array_unshift($data["retweeted_status"]["entities"]["user_mentions"], $data["entities"]["user_mentions"][0]);
                } else {
                    $data["retweeted_status"]["entities"]["user_mentions"] = $data["entities"]["user_mentions"];
                }
                /* pull the entities up from the retweeted status */
                $data["entities"] = $data["retweeted_status"]["entities"];


            } else if (!array_key_exists('extended_tweet', $data["retweeted_status"])) {
                /* running in compatibility mode BUT the 'extended_tweet' JSON field is not available. this means the retweet is <= 140 characters */
                $retweet_text = $data["retweeted_status"]["text"];
            } else {
                /* Running in compatibility mode AND the 'extended_tweet' JSON field is available. this means the retweet is > 140 characters */
                $retweet_text = $data["retweeted_status"]["extended_tweet"]["full_text"];

                /* add the retweeting user to the user mentions */
                if (!empty($data["retweeted_status"]["extended_tweet"]["entities"]["user_mentions"])) {
                    array_unshift($data["retweeted_status"]["extended_tweet"]["entities"]["user_mentions"], $data["entities"]["user_mentions"][0]);
                } else {
                    $data["retweeted_status"]["extended_tweet"]["entities"]["user_mentions"] = $data["entities"]["user_mentions"];
                }
                /* pull the entities up from the retweeted status */
                $data["entities"] = $data["retweeted_status"]["extended_tweet"]["entities"];


            }

            $store_text = "RT @" . $data["retweeted_status"]["user"]["screen_name"] . ": " . $retweet_text;

            /*
             * CAVEAT: if the RT text is > 280 characters, the final segment of the original tweet could contain an entity.
             * The Twitter API will remove that entity from the main tweet and store it only in the retweeted_status hierarchy.
             * On the other hand, the main tweet hierarchy will contain one mention more, namely the retweeted user.
             * We do not at the moment import the entities from the retweeted status hierarchy (although that could be argued for)
             * because 1) they are not visible to the end-user, 2) it would complicate entity processing even more.
             */
        }

        $this->text = $store_text;

        $this->retweet_id = null;
        if (isset($data["retweeted_status"])) {
            $this->retweet_id = $data["retweeted_status"]["id_str"];
        }
        $this->retweet_count = $data["retweet_count"];
        $this->favorite_count = $data["favorite_count"];
        $this->to_user_id = $data["in_reply_to_user_id_str"];
        $this->to_user_name = $data["in_reply_to_screen_name"];
        $this->in_reply_to_status_id = $data["in_reply_to_status_id_str"];
        if (isset($data["quoted_status_id_str"])) {
            $this->quoted_status_id = $data["quoted_status_id_str"];
        } else {
            $this->quoted_status_id = null;
        }
        if (isset($data['filter_level'])) {
            $this->filter_level = $data["filter_level"];
        } else {
            $this->filter_level = 'none';
        }
        $this->lang = $data["lang"];
        if (isset($data['possibly_sensitive'])) {
            $this->possibly_sensitive = $data["possibly_sensitive"];
        } else {
            $this->possibly_sensitive = null;
        }
        $this->place_ids = array();
        $this->places = array();
        if (isset($data["place"]) && isset($data["place"]["id"])) {
            // at this point in time a tweet seems to be limited to a single place
            $this->place_ids[] = $data["place"]["id"];
            // places is made on fromComplete()
        }
        if (isset($data["withheld_copyright"])) {
            $this->withheld_copyright = $data["withheld_copyright"];
        } else {
            $this->withheld_copyright = null;
        }
        if (isset($data["withheld_scope"])) {
            $this->withheld_scope = $data["withheld_scope"];
        } else {
            $this->withheld_scope = null;
        }

        // tweet data (arrays) to object conversion
        // a tweet text can contain multiple URLs, and multiple media URLs can be packed into a single link inside the tweet
        // all other link data is available under entities->urls
        // by concatenating this information we do not get duplicates
        $urls = array();
        foreach ($data["entities"]["urls"] as $url) {
            $u = $url;
            $u['url_expanded'] = $u["expanded_url"];
            unset($u["expanded_url"]);
            $u['url_is_media_upload'] = 0;          // deprecated attribute
            $urls[] = $u;
        }

        // Extract image data

        $search_image_array = null;
        if (array_key_exists('extended_entities', $data) &&
            array_key_exists('media', $data["extended_entities"])) {
            $search_image_array = $data['extended_entities']['media'];
        } else if (!array_key_exists('extended_entities', $data) &&
                   array_key_exists('entities', $data) &&
                   array_key_exists('media', $data["entities"])) {
            // Extract the photo data from the media[] array (which contains only a single item)
            // At this moment only the Search API does not return extended_entities
            $search_image_array = $data['entities']['media'];
        }

        $media = array();

        // Store the image data in the _media table

        if (is_array($search_image_array)) {
            foreach ($search_image_array as $e) {
                $m = array();
                $m["id"] = $e["id_str"];
                $m["tweet_id"] = $this->id;      // link media object to Tweet
                $m["url"] = $e["url"];
                $m["url_expanded"] = $e["expanded_url"];
                $m["media_url_https"] = $e["media_url_https"];
                $m['media_type'] = $e['type'];
                if (isset($e['sizes']['large'])) {
                    $m['photo_size_width'] = $e['sizes']['large']['w'];
                    $m['photo_size_height'] = $e['sizes']['large']['h'];
                    $m['photo_resize'] = $e['sizes']['large']['resize'];
                } else {
                    $m['photo_size_width'] = null;
                    $m['photo_size_height'] = null;
                    $m['photo_resize'] = null;
                }
                if (isset($e['indices'])) {
                    $m['indice_start'] = $e['indices'][0];
                    $m['indice_end'] = $e['indices'][1];
                } else {
                    $m['indice_start'] = null;
                    $m['indice_end'] = null;
                }
                $media[] = $m;
            }
        }


        // The JSON double encode/decode trick to convert a PHP object to a nested associative array is described here:
        // https://stackoverflow.com/a/16111687

        $this->urls = json_decode(json_encode($urls, FALSE));
        $this->media = json_decode(json_encode($media, FALSE));
        $this->user_mentions = json_decode(json_encode($data["entities"]["user_mentions"]), FALSE);
        $this->hashtags = json_decode(json_encode($data["entities"]["hashtags"]), FALSE);
        if (isset($data["withheld_in_countries"])) {
            $this->withheld_in_countries = json_decode(json_encode($data["withheld_in_countries"]), FALSE);
        } else {
            $this->withheld_in_countries = array();
        }
        if (isset($data["user"]["withheld_in_countries"])) {
            $this->from_user_withheld_in_countries = json_decode(json_encode($data["user"]["withheld_in_countries"]), FALSE);
        } else {
            $this->from_user_withheld_in_countries = array();
        }

        $this->fromComplete();
    }

    // maps the users, mentions and hashtags data in the object to their database table fields
    // this function must be called at the end of the fromJSON/fromGnip and other from-functions
    function fromComplete() {

        if ($this->hashtags) {
            foreach ($this->hashtags as $hashtag) {
                $hashtag->tweet_id = $this->id;
                $hashtag->created_at = $this->created_at;
                $hashtag->from_user_name = $this->from_user_name;
                $hashtag->from_user_id = $this->from_user_id;
            }
        }

        if ($this->user_mentions) {
            foreach ($this->user_mentions as $mention) {
                $mention->tweet_id = $this->id;
                $mention->created_at = $this->created_at;
                $mention->from_user_name = $this->from_user_name;
                $mention->from_user_id = $this->from_user_id;
                $mention->to_user = $mention->screen_name;
                $mention->to_user_id = $mention->id_str;
            }
        }

        if ($this->urls) {
            foreach ($this->urls as $url) {
                $url->tweet_id = $this->id;
                $url->created_at = $this->created_at;
                $url->from_user_name = $this->from_user_name;
                $url->from_user_id = $this->from_user_id;
                $url->url_followed = null;
                $url->domain = null;
                $url->error_code = null;
            }
        }

        if ($this->withheld_in_countries || $this->from_user_withheld_in_countries) {
            $list = array();
            foreach ($this->withheld_in_countries as $country) {
                $row = new stdClass;
                $row->tweet_id = $this->id;
                $row->user_id = null;
                $row->country = $country;
                $list[] = $row;
            }
            foreach ($this->from_user_withheld_in_countries as $country) {
                $row = new stdClass;
                $row->tweet_id = $this->id;
                $row->user_id = $this->from_user_id;
                $row->country = $country;
                $list[] = $row;
            }
            $this->withheld_in_countries = $list;       // this array will populate the _withheld table
        }

        if (is_array($this->place_ids)) {
            $list = array();
            foreach ($this->place_ids as $place_id) {
                $row = new stdClass;
                $row->id = $place_id;
                $row->tweet_id = $this->id;
                $list[] = $row;
            }
            $this->places = $list;                      // this array will populate the _places table
        }
    }

    // checks whether this Tweet is in a particular bin in the database
    function isInBin($bin_name) {
        $dbh = pdo_connect();
        $query = "SELECT EXISTS(SELECT 1 FROM " . quoteIdent($bin_name . "_tweets") . " WHERE id = " . $this->id . ")";
        $test = $dbh->prepare($query);
        $test->execute();
        $row = $test->fetch();
        $dbh = null;
        return $row[0];
    }

    // delete a Tweet from a bin
    function deleteFromBin($bin_name) {
        $dbh = pdo_connect();
        $query = "DELETE FROM " . quoteIdent($bin_name . "_tweets") . " WHERE id = " . $this->id;
        $run = $dbh->prepare($query);
        $run->execute();
        $exts = array ( 'hashtags', 'mentions', 'urls', 'places', 'withheld', 'media' );
        foreach ($exts as $ext) {
            $query = "SHOW TABLES LIKE '" . $bin_name . '_' . $ext . "'";
            $run = $dbh->prepare($query);
            $run->execute();
            if ($run->rowCount() > 0) {
                $query = "DELETE FROM " . quoteIdent($bin_name . '_' . $ext) . " WHERE tweet_id = " . $this->id;
                $run = $dbh->prepare($query);
                $run->execute();
            }
        }
        $dbh = null;
    }

}

class TweetQueue {

    public $binColumnsCache;       // contains table structure of all active bins */
    public $queue;
    public $option_replace;
    public $option_delayed;
    public $option_ignore;

    function setoption($option, $value) {
        if ($option == 'replace') {
            $this->option_replace = $value;
        }
        if ($option == 'delayed') {
            $this->option_delayed = $value;
        }
        if ($option == 'ignore') {
            $this->option_ignore = $value;
        }
    }

    function cacheBin($bin) {
        $dbh = pdo_connect();
        $tables = array('tweets', 'mentions', 'urls', 'hashtags', 'withheld', 'places', 'media');
        foreach ($tables as $table) {
            $sql = "show columns from $bin" . "_$table";
            $rec = $dbh->prepare($sql);
            try {
                $rec->execute();
            } catch (PDOException $e) {
                // table does not exist, make an empty cache struct
                $this->binColumnsCache[$bin][$table] = array();
                continue;
            }
            $results = $rec->fetchAll(PDO::FETCH_COLUMN);
            $this->binColumnsCache[$bin][$table] = array_values($results);
        }
        $dbh = null;
    }

    function hasCached($bin) {
        return isset($this->binColumnsCache[$bin]);
    }

    // Log a PDOException using logit
    function reportPDOError($e, $table) {
        $errorMessage = $e->getCode() . ': ' . $e->getMessage();
        $printMessage = "insert into table $table failed with '$errorMessage'";
        if (defined('CAPTURE')) {
            logit(CAPTURE . ".error.log", $printMessage);
        } else {
            logit("cli", $printMessage);
        }
    }

    function __construct() {
        $this->queue = array();
        $this->option_replace = true;
        $this->option_delayed = false;
        $this->option_ignore = false;
        // cache the table structure of all active bins
        $this->binColumnsCache = array();
        $querybins = getActiveBins();
        if (is_array($querybins) && !empty($querybins)) {
            foreach (array_keys($querybins) as $bin) {
                $this->cacheBin($bin);
            }
        }
    }

    function push($tweet, $bin_name) {
        $obj = array('bin_name' => $bin_name,
            'tweet' => $tweet);
        $this->queue[] = $obj;
    }

    function length() {
        return count($this->queue);
    }

    function headMultiInsert($bin_name, $table_extension, $rowcount) {
        if ($rowcount == 0)
            return '';
        $statement = ($this->option_replace) ? 'REPLACE ' : 'INSERT ';
        $statement .= ($this->option_delayed) ? 'DELAYED ' : '';
        $statement .= ($this->option_ignore && !$this->option_replace) ? 'IGNORE ' : '';        // never combine REPLACE INTO with IGNORE
        $statement .= "INTO " . $bin_name . "_" . $table_extension . " ( ";
        $fields = $this->binColumnsCache[$bin_name][$table_extension];
        $first = true;
        foreach ($fields as $f) {
            // for these tables the 'id' field is ignored, because it is not explicitely inserted, but is an auto_increment
            if ($f == 'id' && ($table_extension == 'mentions' || $table_extension == 'hashtags' || $table_extension == 'urls' || $table_extension == 'withheld'))
                continue;
            if (!$first) {
                $statement .= ",";
            } else {
                $first = false;
            }
            $statement .= $f;
        }
        $statement .= " ) VALUES ";
        // add placeholder marks
        $count = count($this->binColumnsCache[$bin_name][$table_extension]);
        if ($count == 0)
            return '';     // unknown table

        // for these tables, again discount the 'id' field
        if ($table_extension == 'mentions' || $table_extension == 'hashtags' || $table_extension == 'urls' || $table_extension == 'withheld')
            $count--;
        $statement .= rtrim(str_repeat("( " . rtrim(str_repeat("?,", $count), ',') . " ),", $rowcount), ',');
        return $statement;
    }

    function insertDB() {
        // insert all Tweets into the database and empty the queue

        $dbh = pdo_connect();

        // make a list of all bins in the queue and count the number of placeholders needed per insert query
        $binlist = array();
        foreach ($this->queue as $obj) {
            $bin_name = $obj['bin_name'];
            if (isset($binlist[$bin_name])) {
                $binlist[$bin_name]['tweets']++;
                $binlist[$bin_name]['hashtags'] += count($obj['tweet']->hashtags);
                $binlist[$bin_name]['urls'] += count($obj['tweet']->urls);
                $binlist[$bin_name]['mentions'] += count($obj['tweet']->user_mentions);
                $binlist[$bin_name]['withheld'] += count($obj['tweet']->withheld_in_countries);
                $binlist[$bin_name]['places'] += count($obj['tweet']->places);
                $binlist[$bin_name]['media'] += count($obj['tweet']->media);
                continue;
            }
            if (!$this->hasCached($bin_name))
                $this->cacheBin($bin_name);

            $binlist[$bin_name] = array('tweets' => 1,
                        'hashtags' => count($obj['tweet']->hashtags),
                        'urls' => count($obj['tweet']->urls),
                        'mentions' => count($obj['tweet']->user_mentions),
                        'withheld' => count($obj['tweet']->withheld_in_countries),
                        'places' => count($obj['tweet']->places),
                        'media' => count($obj['tweet']->media)
                    );
        }

        // process the queue bin by bin

        foreach ($binlist as $bin_name => $counts) {
            // first prepare the multiple insert statements for tweets, mentions, hashtags, urls, withheld, places
            $statement = array();
            $extensions = array('tweets', 'mentions', 'hashtags', 'urls', 'withheld', 'places', 'media');
            foreach ($extensions as $ext) {
                $statement[$ext] = $this->headMultiInsert($bin_name, $ext, $counts[$ext]);
            }
            $tweeti = 1;
            $tweetq = $dbh->prepare($statement['tweets']);
            $hashtagsi = 1;
            $hashtagsq = $dbh->prepare($statement['hashtags']);
            $urlsi = 1;
            $urlsq = $dbh->prepare($statement['urls']);
            $mentionsi = 1;
            $mentionsq = $dbh->prepare($statement['mentions']);
            $withheldi = 1;
            $withheldq = $dbh->prepare($statement['withheld']);
            $placesi = 1;
            $placesq = $dbh->prepare($statement['places']);
            $mediai = 1;
            $mediaq = $dbh->prepare($statement['media']);

            // go now and iterate the queue item by item
            foreach ($this->queue as $obj) {
                if ($obj['bin_name'] !== $bin_name)
                    continue;

                $t = $obj['tweet'];
                // read the tweets table structure from cache
                $fields = $this->binColumnsCache[$bin_name]['tweets'];
                foreach ($fields as $f) {
                    $tweetq->bindParam($tweeti++, $t->$f);
                }

                // and the other tables

                if ($t->hashtags) {
                    $fields = $this->binColumnsCache[$bin_name]['hashtags'];
                    foreach ($t->hashtags as $hashtag) {
                        foreach ($fields as $f) {
                            if ($f == 'id')
                                continue;
                            $hashtagsq->bindParam($hashtagsi++, $hashtag->$f);
                        }
                    }
                }

                if ($t->user_mentions) {
                    $fields = $this->binColumnsCache[$bin_name]['mentions'];
                    foreach ($t->user_mentions as $mention) {
                        foreach ($fields as $f) {
                            if ($f == 'id')
                                continue;
                            $mentionsq->bindParam($mentionsi++, $mention->$f);
                        }
                    }
                }

                if ($t->urls) {
                    $fields = $this->binColumnsCache[$bin_name]['urls'];
                    foreach ($t->urls as $url) {
                        foreach ($fields as $f) {
                            if ($f == 'id')
                                continue;
                            $urlsq->bindParam($urlsi++, $url->$f);
                        }
                    }
                }

                if ($statement['withheld'] !== '') {
                    if ($t->withheld_in_countries && !empty($t->withheld_in_countries) && !empty($this->binColumnsCache[$bin_name]['withheld'])) {
                        $fields = $this->binColumnsCache[$bin_name]['withheld'];
                        foreach ($t->withheld_in_countries as $withheld) {
                            foreach ($fields as $f) {
                                if ($f == 'id')
                                    continue;
                                $withheldq->bindParam($withheldi++, $withheld->$f);
                            }
                        }
                    }
                }
                if ($statement['places'] !== '') {
                    if ($t->places && !empty($t->places) && !empty($this->binColumnsCache[$bin_name]['places'])) {
                        $fields = $this->binColumnsCache[$bin_name]['places'];
                        foreach ($t->places as $place) {
                            foreach ($fields as $f) {
                                $placesq->bindParam($placesi++, $place->$f);
                            }
                        }
                    }
                }
                if ($statement['media'] !== '') {
                    if ($t->media && !empty($t->media) && !empty($this->binColumnsCache[$bin_name]['media'])) {
                        $fields = $this->binColumnsCache[$bin_name]['media'];
                        foreach ($t->media as $media) {
                            foreach ($fields as $f) {
                                $mediaq->bindParam($mediai++, $media->$f);
                            }
                        }
                    }
                }

            }

            // finaly insert the tweets and other data
            if ($statement['tweets'] !== '') {
                try {
                    $tweetq->execute();
                } catch (PDOException $e) {
                    $errInfo = $dbh->errorInfo();
                    if ($errInfo[0] == '42S02' || $errInfo[0] == '1146') {
                        // 42S02: SQLSTATE[42S02]: Base table or view not found: 1146
                        logit(CAPTURE . ".error.log", "table $bin_name" . "_tweets went missing. This is expected behavior if a live upgrade is in progress.");
                        $failure = false;
                        for ($retries = 0; $retries < 3; $retries++) {
                            sleep(1); $failure = false;
                            try { $tweetq->execute(); } catch (PDOException $e) {
                                $failure = true;
                            }
                            if ($failure == false) {
                                break;
                            }
                        }
                        if ($failure) {
                            $this->reportPDOError($e, $bin_name . '_tweets');
                        } else {
                            logit(CAPTURE . ".error.log", "table $bin_name" . "_tweets is back. Queue has been flushed.");
                        }
                    } else {
                        $this->reportPDOError($e, $bin_name . '_tweets');
                    }
                }
            }
            if ($statement['hashtags'] !== '') {
                try {
                    $hashtagsq->execute();
                } catch (PDOException $e) {
                    $this->reportPDOError($e, $bin_name . '_hashtags');
                }
            }
            if ($statement['urls'] !== '') {
                try {
                    $urlsq->execute();
                } catch (PDOException $e) {
                    $this->reportPDOError($e, $bin_name . '_urls');
                }
            }
            if ($statement['mentions'] !== '') {
                try {
                    $mentionsq->execute();
                } catch (PDOException $e) {
                    $this->reportPDOError($e, $bin_name . '_mentions');
                }
            }
            if ($statement['withheld'] !== '') {
                try {
                    $withheldq->execute();
                } catch (PDOException $e) {
                    $this->reportPDOError($e, $bin_name . '_withheld');
                }
            }
            if ($statement['places'] !== '') {
                try {
                    $placesq->execute();
                } catch (PDOException $e) {
                    $this->reportPDOError($e, $bin_name . '_places');
                }
            }
            if ($statement['media'] !== '') {
                try {
                    $mediaq->execute();
                } catch (PDOException $e) {
                    $this->reportPDOError($e, $bin_name . '_media');
                }
            }

            if (defined('CAPTURE') && database_activity($dbh)) {
                $pid = getmypid();
                file_put_contents(__DIR__ . "/../../proc/" . CAPTURE . ".procinfo", $pid . "|" . time());
            }
        }

        $dbh = null;
        $this->queue = array();
    }

}

class TwitterRelations {

    private $list;
    private $id;
    private $type;
    private $screen_name;
    private $observed_at;

    public function __construct($screen_name_or_id, $relations, $type, $observed_at) {

        if (is_numeric($screen_name_or_id)) {
            $this->id = $screen_name_or_id;
        } else {
            $this->screen_name = $screen_name_or_id;
        }

        $this->users = $relations;
        $this->type = $type;
        $this->observed_at = $observed_at;
    }

    public function save(PDO $dbh, $bin_name) {
        if (!$this->id) {
            // Try to find this users id
            $q = $dbh->prepare("SELECT from_user_id FROM " . $bin_name . "_tweets " .
                    "WHERE from_user_name = :screen_name");

            $q->execute(array(':screen_name' => $this->screen_name));
            $result = $q->fetch(PDO::FETCH_OBJ);
            if ($result && $result->from_user_id) {
                $this->id = $result->from_user_id;
            } else {
                throw new Exception("No matching user id for `screen_name` =  {$this->screen_name} in table $bin_name found.");
            }
        }

        foreach ($this->users as $user) {
            $q = $dbh->prepare(
                    "INSERT INTO " . $bin_name . '_relations' . "
				(user1_id, user1_name, type, observed_at, user2_id, user2_name, user2_realname)
				VALUES 
				(:user1_id, :user1_name, :type, :observed_at, :user2_id, :user2_name, :user2_realname);");
            $q->bindParam(":user1_id", $this->id, PDO::PARAM_INT); // @otod id_str?
            $q->bindParam(":user1_name", $this->screen_name, PDO::PARAM_STR);
            $q->bindParam(":type", $this->type, PDO::PARAM_STR);
            $q->bindParam(":observed_at", $this->observed_at, PDO::PARAM_STR);
            $q->bindParam(':user2_id', $user['id_str'], PDO::PARAM_STR);
            $q->bindParam(':user2_name', $user['screen_name'], PDO::PARAM_STR);
            $q->bindParam(':user2_realname', $user['name'], PDO::PARAM_STR);
            $q->execute();
        }
    }

    public static function create_relations_tables(PDO $dbh, $bin_name) {
        $sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_relations (
		user1_id bigint NOT NULL,
                user1_name varchar(255) NOT NULL,		
                type varchar(255),
		observed_at datetime,
                user2_id bigint NOT NULL,
		user2_name varchar(255) NOT NULL,
                user2_realname varchar(255),
		KEY `user1_id` (`user1_id`), 
                KEY `user2_id` (`user2_id`)
		) " . MYSQL_ENGINE_OPTIONS . " DEFAULT CHARSET=utf8mb4";

        if ($dbh->exec($sql)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

}

class UrlCollection implements IteratorAggregate {

    private $items = array();
    private $count = 0;

    public function getIterator() {
        return new Iterator($this->items);
    }

    public function add($object) {
        $this->items[$this->count++] = $object;
    }

}

// This function takes a one-dimensional array with sets of the following data: tweet_id, phrase_id, created_at
// It inserts this data into the MySQL database using a multi-insert statement
function insert_captured_phrase_ids($captured_phrase_ids) {
    global $dbuser, $dbpass, $database, $hostname;
    if (empty($captured_phrase_ids)) return;
    $dbh = pdo_connect();

    // construct insert SQL

    $moresets = 0;
    if (count($captured_phrase_ids) > 3) {
        $moresets = count($captured_phrase_ids) / 3 - 1;
    }
    if (defined('USE_INSERT_DELAYED') && USE_INSERT_DELAYED) {
        $extra = 'DELAYED';
    } else {
        $extra = '';
    }
    $sql = "INSERT $extra IGNORE INTO tcat_captured_phrases ( tweet_id, phrase_id, created_at ) VALUES ( ?, ?, ? )" . str_repeat(", (?, ?, ?)", $moresets);
    $h = $dbh->prepare($sql);
    for ($i = 0; $i < count($captured_phrase_ids); $i++) {
        // bindParam() expects its first parameter ( index of the ? placeholder ) to start with 1
        if ($i % 3 == 2)  {
            $h->bindParam($i + 1, $captured_phrase_ids[$i], PDO::PARAM_STR);
        } else {
            $h->bindParam($i + 1, $captured_phrase_ids[$i], PDO::PARAM_INT);
        }
    }
    $h->execute();

    $dbh = false;

}

/*
 * Start a tracking process
 */

function tracker_run() {
    global $dbuser, $dbpass, $database, $hostname, $tweetQueue;

    // We need the tcat_status table
       
    create_error_logs();

    // We need the tcat_captured_phrases table

    create_admin();

    $tweetQueue = new TweetQueue();
    $tweetQueue->setoption('replace', false);
    if (defined('USE_INSERT_DELAYED') && USE_INSERT_DELAYED) {
        $tweetQueue->setoption('delayed', true);
    }
    if (defined('DISABLE_INSERT_IGNORE') && DISABLE_INSERT_IGNORE) {
        $tweetQueue->setoption('ignore', false);
    } else {
        $tweetQueue->setoption('ignore', true);
    }

    if (!defined("CAPTURE")) {

        /* logged to no file in particular, because we don't know which one. this should not happen. */
        error_log("tracker_run() called without defining CAPTURE. have you set up config.php ?");
        die();
    }

    $roles = unserialize(CAPTUREROLES);
    if (!in_array(CAPTURE, $roles)) {
        /* incorrect script execution, report back error to user */
        error_log("tracker_run() role " . CAPTURE . " is not configured to run");
        die();
    }

    // log execution environment
    $phpstring = phpversion() . " in mode " . php_sapi_name() . " with extensions ";
    $extensions = get_loaded_extensions();
    $first = true;
    foreach ($extensions as $ext) {
        if ($first) {
            $first = false;
        } else {
            $phpstring .= ',';
        }
        $phpstring .= "$ext";
    }
    $phpstring .= " (ini file: " . php_ini_loaded_file() . ")";
    logit(CAPTURE . ".error.log", "running php version $phpstring");

    // install the signal handler
    if (function_exists('pcntl_signal')) {

        // tick use required as of PHP 4.3.0
        declare(ticks = 1);

        // See signal method discussion:
        // http://darrendev.blogspot.nl/2010/11/php-53-ticks-pcntlsignal.html

        logit(CAPTURE . ".error.log", "installing term signal handler for this script");

        // setup signal handlers
        pcntl_signal(SIGTERM, "capture_signal_handler_term");
    } else {

        logit(CAPTURE . ".error.log", "your php installation does not support signal handlers. graceful reload will not work");
    }

    // sanity check for geo bins functions
    if (geophp_sane()) {
        logit(CAPTURE . ".error.log", "geoPHP library is fully functional");
    } elseif (geobinsActive()) {
        logit(CAPTURE . ".error.log", "refusing to track until geobins are stopped or geo is functional");
        exit(1);
    } else {
        logit(CAPTURE . ".error.log", "geoPHP functions are not yet available, see documentation for instructions");
    }

    global $rl_current_record, $rl_registering_minute;
    global $last_insert_id;
    global $tracker_started_at;

    $rl_current_record = 0;                                 // how many tweets have been ratelimited this MINUTE?
    $rl_registering_minute = get_current_minute();          // what is the minute we are registering (as soon as the current minute differs from this, we insert our record in the database)
    $last_insert_id = -1;                                   // needed to make INSERT DELAYED work, see the function database_activity()
    $tracker_started_at = time();                           // the walltime when this script was started

    global $twitter_consumer_key, $twitter_consumer_secret, $twitter_user_token, $twitter_user_secret, $lastinsert;

    $pid = getmypid();
    logit(CAPTURE . ".error.log", "started script " . CAPTURE . " with pid $pid");

    $lastinsert = time();
    $procfilename = __DIR__ . "/../../proc/" . CAPTURE . ".procinfo";
    if (file_put_contents($procfilename, $pid . "|" . time()) === FALSE) {
        logit(CAPTURE . ".error.log", "cannot register capture script start time (file \"$procfilename\" is not WRITABLE. make sure the proc/ directory exists in your webroot and is writable by the cron user)");
        die();
    }

    $networkpath = isset($GLOBALS["HOSTROLE"][CAPTURE]) ? $GLOBALS["HOSTROLE"][CAPTURE] : 'https://stream.twitter.com/';

    // prepare queries
    if (CAPTURE == "track") {

        // check for geolocation bins
        $locations = geobinsActive() ? getActiveLocationsImploded() : false;

        // assemble query
        $querylist = getActivePhrases();
        if (empty($querylist) && !geobinsActive()) {
            logit(CAPTURE . ".error.log", "empty query list, aborting!");
            return;
        }
        $method = $networkpath . '1.1/statuses/filter.json';
        $track = implode(",", $querylist);
        $params = array();
        if (geobinsActive()) {
            $params['locations'] = $locations;
        }
        if (!empty($querylist)) {
            $params['track'] = $track;
        }
    } elseif (CAPTURE == "follow") {
        $querylist = getActiveUsers();
        if (empty($querylist)) {
            logit(CAPTURE . ".error.log", "empty query list, aborting!");
            return;
        }
        $method = $networkpath . '1.1/statuses/filter.json';
        $params = array("follow" => implode(",", $querylist));
    } elseif (CAPTURE == "onepercent") {
        $method = $networkpath . '1.1/statuses/sample.json';
        $params = array('stall_warnings' => 'true');
    }

    logit(CAPTURE . ".error.log", "connecting to API socket");
    $tmhOAuth = new tmhOAuth(array(
                'consumer_key' => $twitter_consumer_key,
                'consumer_secret' => $twitter_consumer_secret,
                'token' => $twitter_user_token,
                'secret' => $twitter_user_secret,
                'host' => 'stream.twitter.com',
            ));
    $tmhOAuth->request_settings['headers']['Host'] = 'stream.twitter.com';

    if (CAPTURE == "track" || CAPTURE == "follow") {
        logit(CAPTURE . ".error.log", "connecting - query " . var_export($params, 1));
    } elseif (CAPTURE == "onepercent") {
        logit(CAPTURE . ".error.log", "connecting to sample stream");
    }

    $capturebucket = array();
    $tmhOAuth->streaming_request('POST', $method, $params, 'tracker_streamCallback', array('Host' => 'stream.twitter.com'));

    // output any response we get back AFTER the Stream has stopped -- or it errors
    logit(CAPTURE . ".error.log", "stream stopped - error " . var_export($tmhOAuth, 1));

    logit(CAPTURE . ".error.log", "processing buffer before exit");
    processtweets($capturebucket);
}

/*
 * Stream callback function
 */

function tracker_streamCallback($data, $length, $metrics) {
    global $capturebucket, $lastinsert;

    if (defined('ENABLE_JSON_DUMP') && ENABLE_JSON_DUMP) {
        file_put_contents(BASE_FILE . 'logs/stream.dump.json', $data . ",\n", FILE_APPEND|LOCK_EX);
    }

    $now = time();
    $data = json_decode($data, true);

    if ($data) {

        if (array_key_exists('disconnect', $data)) {
            $discerror = implode(",", $data["disconnect"]);
            logit(CAPTURE . ".error.log", "connection dropped or timed out - error " . $discerror);
            logit(CAPTURE . ".error.log", "(debug) dump of result data on disconnect" . var_export($data, true));
            return;             // exit will take place in the previous function
        }

        if (array_key_exists('warning', $data)) {
            // Twitter sent us a warning
            $code = $data['warning']['code'];
            $message = $data['warning']['message'];
            if ($code === 'FALLING_BEHIND') {
                $full = $data['warning']['percent_full'];
                logit(CAPTURE . ".error.log", "twitter api warning received: ($code) $message [percentage full $full]");
            } else {
                logit(CAPTURE . ".error.log", "twitter api warning received: ($code) $message");
            }
        }

        // handle rate limiting at intervals of a single minute

        global $rl_current_record, $rl_registering_minute;
        global $tracker_started_at;

        $current = 0;
        $current_minute = get_current_minute();

        // we keep a a counter of the nr. of tweets rate limited and reset it at intervals of one minute

        // read possible rate limit information from Twitter

        if (array_key_exists('limit', $data) && isset($data['limit']['track'])) {
            $current = $data['limit'][CAPTURE];
            // we have a new rate limit, grow the record
            $rl_current_record += $current;
        } else {
            // when no new rate limits occur, sustain our current record
            $current = $rl_current_record;
        }

        if ($rl_registering_minute != $current_minute) {

            // the current minute is no longer our registering minute; we have to record our ratelimit information in the database

            if ($current_minute == 0 && $rl_registering_minute < 59 ||
                $current_minute > 0 && $current_minute < $rl_registering_minute ||
                $current_minute > $rl_registering_minute + 1) {
                // there was a more than 1 minute silence (i.e. a response from Curl took longer than our 1 minute interval, thus we need to fill in zeros backwards in time)
                $tracker_running = round((time() - $tracker_started_at) / 60);
                ratelimit_holefiller($tracker_running);
            }

            $rl_registering_minute = $current_minute;

            // we now have rate limit information for the last minute
            ratelimit_record($rl_current_record);
            if ($rl_current_record > 0) {
                ratelimit_report_problem();
                $rl_current_record = 0;
            }

        }

        if (array_key_exists('limit', $data)) {
            unset($data['limit']);
        }


        if (empty($data))
            return;   // sometimes we only get rate limit info

        $capturebucket[] = $data;
        if (count($capturebucket) == 100 || $now > $lastinsert + 5) {
            processtweets($capturebucket);
            $lastinsert = time();
            $capturebucket = array();
        }
    }
}

/*
 * Process tweet function
 */

function processtweets($capturebucket) {

    global $tweetQueue;

    $querybins = getActiveBins();
    $phrase_ids = getActivePhraseIds();

    // cache bin types
    $bintypes = array();
    foreach ($querybins as $binname => $queries) $bintypes[$binname] = getBinType($binname);

    $captured_phrase_ids = array();

    // running through every single tweet
    foreach ($capturebucket as $data) {

        if (!array_key_exists('entities', $data)) {

            // unexpected/irregular tweet data
            if (array_key_exists('delete', $data)) {
                // a tweet has been deleted. @todo: process
                continue;
            }

            // this can get very verbose when repeated?
            logit(CAPTURE . ".error.log", "irregular tweet data received.");
            continue;
        }

        // we run through every bin to check whether the received tweets fit
        foreach ($querybins as $binname => $queries) {

            $geobin = (isset($bintypes[$binname]) && $bintypes[$binname] == 'geotrack');

            if ($geobin && (!array_key_exists('geo_enabled', $data['user']) || $data['user']['geo_enabled'] !== true)) {
                // in geobins, process only geo tweets
                continue;
            }

            $found = false;

            // Create a Tweet object from the raw JSON

            $tweet = new Tweet();
            $tweet->fromJSON($data);

            if (CAPTURE == "track") {

                // we check for every query in the bin if they fit

                foreach ($queries as $query => $track) {

                    if ($geobin) {

                        $boxes = getGeoBoxes($track);

                        // look for geolocation matches

                        /*
                         * Some notes on geolocation tracking
                         *
                         * Geolocation tracking is done inside the capture role: track
                         * Geolocation query bins have a special type: geotrack
                         * Geolocation phrases have a specific format: 
                         *             = these phrases are a chain of geoboxes defined as 4 comma separated values (sw long, sw lat, ne long, ne lat)
                         *             = multiple world areas can thus be defined per bin
                         *
                         * Fetching (from Twitter)
                         *
                         * 1) Twitter will give us all the tweets which have excplicit GPS coordinates inside one of our queried areas.
                         * 2) Additionaly Twitter give us those tweets with a user 'place' definition. A place (i.e. Paris) is itself a (set of) gps polygons
                         *    Twitter returns the tweets if one of these place polygons covers the same area as our geo boxes.  
                         *
                         * And matching (by us)
                         *
                         * 1) These tweets will be put in the bin if the coordinate pair (longitude, latitude) fits in any one of the defined geoboxes in the bin.
                         * 2) These tweets will be put in the bin if the geobox is _not_ completely subsumed by the place (example: the place is France and the geobox is Paris), but the geobox does overlap the place polygon or the geobox subsumes the place polygon.
                         *
                         */

                        if ($data["geo"] != null) {
                            $tweet_lat = $data["geo"]["coordinates"][0];
                            $tweet_lng = $data["geo"]["coordinates"][1];

                            // does the tweet geo data fit in on of the boxes?

                            foreach ($boxes as $box) {
                                if (coordinatesInsideBoundingBox($tweet_lng, $tweet_lat, $box['sw_lng'], $box['sw_lat'], $box['ne_lng'], $box['ne_lat'])) {
                                    // logit(CAPTURE . ".error.log", "(debug) tweet with lng $tweet_lng and lat $tweet_lat versus (sw: " . $box['sw_lng'] . "," . $box['sw_lat'] . " ne: " . $box['ne_lng'] . "," . $box['ne_lat'] . ") matched to be inside the area");
                                    $found = true;
                                    break;
                                } else {
                                    // logit(CAPTURE . ".error.log", "(debug) tweet with lng $tweet_lng and lat $tweet_lat versus (sw: " . $box['sw_lng'] . "," . $box['sw_lat'] . " ne: " . $box['ne_lng'] . "," . $box['ne_lat'] . ") falls outside the area");
                                }
                            }
                        } else { 

                            // this is a gps tracking query, but the tweet has no gps geo data
                            // Twitter may have matched this tweet based on the user-defined location data
                           
                            if (array_key_exists('place', $data) && is_array($data['place']) && array_key_exists('bounding_box', $data['place'])) {

                                // Make a geoPHP object of the polygon(s) defining the place, by using a WKT (well-known text) string
                                $wkt = 'POLYGON(';
                                $polfirst = true;
                                foreach ($data['place']['bounding_box']['coordinates'] as $p => $pol) {
                                    if ($polfirst) {
                                        $polfirst = false;
                                    } else {
                                        $wkt .= ', ';
                                    }
                                    $wkt .= '(';
                                    $first = true;
                                    $first_lng = 0;
                                    $first_lat = 0;
                                    foreach ($data['place']['bounding_box']['coordinates'][$p] as $i => $coords) {
                                        $point_lng = $coords[0];
                                        $point_lat = $coords[1];
                                        if ($first) {
                                            $first = false;
                                            $first_lng = $point_lng;
                                            $first_lat = $point_lat;
                                        } else {
                                            $wkt .= ', ';
                                        }
                                        $wkt .= $point_lng . ' ' . $point_lat;
                                    }
                                    // end where we started
                                    $wkt .= ', ' . $first_lng . ' ' . $first_lat;
                                    $wkt .= ')';
                                }
                                $wkt .= ')';
                                $place = geoPHP::load($wkt, 'wkt');

                                // iterate over geoboxes in our track
                                // place should not spatially contain our box, but it should overlap with it
                                foreach ($boxes as $box) {
                                    // 'POLYGON((x1 y1, x1 y2, x2 y2, x2 y1, x1 y1))'
                                    $boxwkt = 'POLYGON((' . $box['sw_lng'] . ' ' . $box['sw_lat'] . ', '
                                            . $box['sw_lng'] . ' ' . $box['ne_lat'] . ', '
                                            . $box['ne_lng'] . ' ' . $box['ne_lat'] . ', '
                                            . $box['ne_lng'] . ' ' . $box['sw_lat'] . ', '
                                            . $box['sw_lng'] . ' ' . $box['sw_lat'] . '))';
                                    $versus = geoPHP::load($boxwkt, 'wkt');
                                    $contains = $place->contains($versus);
                                    $boxcontains = $versus->contains($place);
                                    $overlaps = $place->overlaps($versus);
                                    if (!$contains && ($boxcontains || $overlaps)) {
                                        // logit(CAPTURE . ".error.log", "place polygon $wkt allies with geobox $boxwkt");
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                        }

                        if ($found) {
                            // found = true causes the tweet to be inserted into the database 
                            // store phrase data (in this case a geobox definition) 
                            $captured_phrase_ids[] = $data['id_str'];
                            $captured_phrase_ids[] = $phrase_ids[$query];
                            $captured_phrase_ids[] = date("Y-m-d H:i:s", strtotime($data["created_at"]));
                            continue;
                        }
                    } else {

                        // look for keyword matches

                        $pass = false;

                        // check for queries with more than one word, but go around quoted queries
                        if (preg_match("/ /", $query) && !preg_match("/'/", $query)) {
                            $tmplist = explode(" ", $query);

                            $all = true;

                            foreach ($tmplist as $tmp) {
                                if (tweet_contains_phrase($data["text"], $tmp) == FALSE) {
                                    $all = false;
                                    break;
                                }
                            }

                            // only if all words are found
                            if ($all == true) {
                                $pass = true;
                            }
                        } else {

                            // treat quoted queries as single words
                            $query = preg_replace("/'/", "", $query);
                            if (tweet_contains_phrase($data["text"], $query)) {
                                $pass = true;
                            }
                        }

                        // at the first fitting query, we set found to true (to indicate we should insert the tweet into the database)
                        // we also register the fact this keyword query has been matched
                        if ($pass == true) {
                            $found = true;
                            $captured_phrase_ids[] = $data['id_str'];
                            $captured_phrase_ids[] = $phrase_ids[$query];
                            $captured_phrase_ids[] = date("Y-m-d H:i:s", strtotime($data["created_at"]));
                        }
                    }
                }
            } elseif (CAPTURE == "follow") {

                // we check for every query in the bin if they fit
                $found = in_array($data["user"]["id"], $queries) ? TRUE : FALSE;
            } elseif (CAPTURE == "onepercent") {

                // always match in onepercent
                $found = true;
            }

            // if the tweet does not fit in the current bin, go to the next tweet
            if ($found == false) {
                continue;
            }

            $tweetQueue->push($tweet, $binname);
        }
    }
    $tweetQueue->insertDB();
    insert_captured_phrase_ids($captured_phrase_ids);
    return TRUE;
}

function safe_feof($fp, &$start = NULL) {
    $start = microtime(true);
    return feof($fp);
}

function database_activity($dbh) {
    global $last_insert_id;
    if (defined('USE_INSERT_DELAYED') && USE_INSERT_DELAYED) {
        // when using DELAYED INSERT, the LAST_INSERT_ID() function is unreliable
        // we make use of the delayed_writes status variable instead
        $query = "select VARIABLE_VALUE from information_schema.GLOBAL_STATUS where VARIABLE_NAME = 'delayed_writes'";
    } else {
        // we explicitely use the MySQL function last_insert_id
        // we don't want any PHP caching of insert id's()
        $query = "SELECT LAST_INSERT_ID()";
    }

    $sth = $dbh->prepare($query);
    $sth->execute();
    $lid = $sth->fetchColumn();
    if ($lid === FALSE || $lid === 0) {
        return FALSE;
    }
    if ($lid !== $last_insert_id) {
        // update the value
        $last_insert_id = $lid;
        return TRUE;
    }
    return FALSE;
}

// REST API key swapping functions.
// Uses the global $twitter_keys
// This function may cause a sleep when no key is available
function getRESTKey($current_key, $resource = 'statuses', $query = 'lookup') {
    global $twitter_keys;

    $start_key = $current_key;
    $remaining = getRemainingForKey($current_key, $resource, $query);
    if ($remaining) {
        logit("cli", "key: " . $current_key . "\t" . "remaining requests:" . $remaining);
        return array('key' => $current_key, 'remaining' => $remaining);
    }
    do {
        $current_key++;
        if ($current_key >= count($twitter_keys)) {
            $current_key = 0;
        }
        if ($current_key == $start_key)
            sleep(180);
        $remaining = getRemainingForKey($current_key, $resource, $query);
    } while ($remaining == 0);

    logit("cli", "key: " . $current_key . "\t" . "requests remaining:" . $remaining);
    return array('key' => $current_key, 'remaining' => $remaining);
}

function getRemainingForKey($current_key, $resource = 'statuses', $query = 'lookup') {
    global $twitter_keys;

    // rate limit test
    $tmhOAuth = new tmhOAuth(array(
                'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
                'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
                'token' => $twitter_keys[$current_key]['twitter_user_token'],
                'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
            ));
    $params = array(
        'resources' => $resource,
    );

    $code = $tmhOAuth->user_request(array(
        'method' => 'GET',
        'url' => $tmhOAuth->url('1.1/application/rate_limit_status'),
        'params' => $params
            ));

    if ($tmhOAuth->response['code'] == 200) {
        $data = json_decode($tmhOAuth->response['response'], true);
        return $data['resources'][$resource]["/$resource/$query"]['remaining'];
    } else {
        if ($tmhOAuth->response['code'] != 429) // 419 is too many requests
            logit("cli", "Warning: API key $current_key got response code " . $tmhOAuth->response['code']);
        return 0;
    }
}

?>
