<?php

require_once("geoPHP/geoPHP.inc"); // geoPHP library

error_reporting(E_ALL);

function pdo_connect() {
    global $dbuser, $dbpass, $database, $hostname;

    $dbh = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8", $dbuser, $dbpass);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $dbh;
}

function create_error_logs() {
    $dbh = pdo_connect();

    $sql = 'create table if not exists tcat_error_ratelimit ( id bigint auto_increment, type varchar(32), start datetime not null, end datetime not null, tweets bigint not null, primary key(id), index(type), index(start), index(end) )';
    $h = $dbh->prepare($sql);
    $h->execute();

    $sql = 'create table if not exists tcat_error_gap ( id bigint auto_increment, type varchar(32), start datetime not null, end datetime not null, primary key(id), index(type), index(start), index(end) )';
    $h = $dbh->prepare($sql);
    $h->execute();
}

// Enclose identifier in backticks; escape backticks inside by doubling them.
function quoteIdent($field) {
    return "`" . str_replace("`", "``", $field) . "`";
}

function create_bin($bin_name, $dbh = false) {
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
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8";

        $create_hashtags = $dbh->prepare($sql);
        $create_hashtags->execute();

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
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8";

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
                    `source` varchar(512),
                    `location` varchar(64),
                    `geo_lat` float(10,6),
                    `geo_lng` float(10,6),
                    `text` varchar(255) NOT NULL,
                    `retweet_id` bigint(20),
                    `retweet_count` int(11),
                    `favorite_count` int(11),
                    `to_user_id` bigint,
                    `to_user_name` varchar(255),
                    `in_reply_to_status_id` bigint(20),
                    `filter_level` varchar(6),
                    `lang` varchar(16),
                    PRIMARY KEY (`id`),
                    KEY `created_at` (`created_at`),
                    KEY `from_user_name` (`from_user_name`),
                    KEY `from_user_lang` (`from_user_lang`),
                    KEY `retweet_id` (`retweet_id`),
                    KEY `in_reply_to_status_id` (`in_reply_to_status_id`),
                    FULLTEXT KEY `from_user_description` (`from_user_description`),
                    FULLTEXT KEY `text` (`text`)
                    ) ENGINE=MyISAM DEFAULT CHARSET=utf8";

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
                    FULLTEXT KEY `url_followed` (`url_followed`),
                    KEY `url_expanded` (`url_expanded`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8";

        $create_urls = $dbh->prepare($sql);
        $create_urls->execute();
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
    `visible` BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (`id`),
    KEY `querybin` (`querybin`),
    KEY `type` (`type`),
    KEY `active` (`active`)
    ) ENGINE = MyISAM DEFAULT CHARSET = utf8";
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
    ) ENGINE = MyISAM DEFAULT CHARSET = utf8";
    $create = $dbh->prepare($sql);
    $create->execute();

    $sql = "CREATE TABLE IF NOT EXISTS tcat_query_phrases (
    `id` INT NOT NULL AUTO_INCREMENT,
    `phrase` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `phrase` (`phrase`)
    ) ENGINE = MyISAM DEFAULT CHARSET = utf8";
    $create = $dbh->prepare($sql);
    $create->execute();

    $sql = "CREATE TABLE IF NOT EXISTS tcat_query_users (
    `id` bigint NOT NULL AUTO_INCREMENT,
    `user_name` varchar(255),
    PRIMARY KEY `id` (`id`)
    ) ENGINE = MyISAM DEFAULT CHARSET = utf8";
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
    ) ENGINE = MyISAM DEFAULT CHARSET = utf8";
    $create = $dbh->prepare($sql);
    $create->execute();

    $sql = "CREATE TABLE IF NOT EXISTS tcat_query_bins_users (
    `id` INT NOT NULL AUTO_INCREMENT,
    `starttime` DATETIME NULL,
    `endtime` DATETIME NULL,
    `user_id` INT NULL,
    `querybin_id` INT NULL,
    PRIMARY KEY (`id`),
    KEY `starttime` (`starttime`),
    KEY `endtime` (`endtime`),
    KEY `user_id` (`user_id`),
    KEY `querybin_id` (`querybin_id`)
    ) ENGINE = MyISAM DEFAULT CHARSET = utf8";
    $create = $dbh->prepare($sql);
    $create->execute();
    $dbh = false;
}

/*
 * Record a ratelimit disturbance
 */

function ratelimit_record($ratelimit, $ex_start) {
    /* for debugging */
    logit("controller.log", "ratelimit_record() has been called");
    $dbh = pdo_connect();
    $sql = "insert into tcat_error_ratelimit ( type, start, end, tweets ) values ( :type, :start, :end, :ratelimit)";
    $h = $dbh->prepare($sql);
    $ex_start = toDateTime($ex_start);
    $ex_end = toDateTime(time());
    $type = CAPTURE;
    $h->bindParam(":type", $type, PDO::PARAM_STR);
    $h->bindParam(":start", $ex_start, PDO::PARAM_STR);
    $h->bindParam(":end", $ex_end, PDO::PARAM_STR);
    $h->bindParam(":ratelimit", $ratelimit, PDO::PARAM_INT);
    $h->execute();
    $dbh = false;
}

/*
 * Record a gap in the data
 */

function gap_record($role, $ustart, $uend) {
    if ($uend <= $ustart) {
        return TRUE;
    }
    if (($uend - $ustart) < 15) {
        // a less than 15 second gap is usually the result of a software restart/reload
        // during that restart the tweet buffer is flushed and the gap is very tiny, therefore we ignore this
        return TRUE;
    }
    $dbh = pdo_connect();
    $sql = "insert into tcat_error_gap ( type, start, end ) values ( :role, :start, :end)";
    $h = $dbh->prepare($sql);
    $ustart = toDateTime($ustart);
    $uend = toDateTime($uend);
    $h->bindParam(":role", $role, PDO::PARAM_STR);
    $h->bindParam(":start", $ustart, PDO::PARAM_STR);
    $h->bindParam(":end", $uend, PDO::PARAM_STR);
    $h->execute();
}

/*
 * Inform administrator of ratelimit problems
 */

function ratelimit_report_problem() {
    if (defined('RATELIMIT_MAIL_HOURS') && RATELIMIT_MAIL_HOURS > 0) {
        $sql = "select count(*) as cnt from tcat_error_ratelimit where start > (now() - interval " . RATELIMIT_MAIL_HOURS . " hour)";
        $result = mysql_query($sql);
        if ($row = mysql_fetch_assoc($result)) {
            if (isset($row['cnt']) && $row['cnt'] == 0) {
                global $mail_to;
                mail($mail_to, 'DMI-TCAT rate limit has been reached', 'The script running the ' . CAPTURE . ' query has hit a rate limit while talking to the Twitter API. Twitter is not allowing you to track more than 1% of its total traffic at any time. This means that the number of tweets exceeding the barrier are being dropped. Consider reducing the size of your query bins and reducing the number of terms and users you are tracking.' . "\n\n" .
                        'This may be a temporary or a structural problem. Please look at the webinterface for more details. Rate limit statistics on the website are historic, however. Consider this message indicative of a current issue. This e-mail will not be repeated for at least ' . RATELIMIT_MAIL_HOURS . ' hours.', 'From: no-reply@dmitcat');
            }
        }
    }
}

function toDateTime($unixTimestamp) {
    return date("Y-m-d H:m:s", $unixTimestamp);
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
 * This function returns TRUE if there is an active capture script for role.
 */

function check_running_role($role) {

    if (!defined('CAPTUREROLES')) {
        logit("controller.log", "check_running_role: You do not seem to have CAPTUREROLES defined in your config.php");
        return FALSE;
    }

    $roles = unserialize(CAPTUREROLES);

    if (!in_array($role, $roles)) {
        logit("controller.log", "check_running_role: $role not defined in CAPTUREROLES");
        return FALSE;
    }

    // is the appropriate script running?
    if (file_exists(BASE_FILE . "proc/$role.procinfo")) {

        $procfile = file_get_contents(BASE_FILE . "proc/$role.procinfo");

        $tmp = explode("|", $procfile);
        $pid = $tmp[0];
        $last = $tmp[1];

        if (is_numeric($pid) && $pid > 0) {

            if (function_exists('posix_kill')) {

                // check whether the pid is running by checking whether it is possible to send the process a signal
                $running = posix_kill($pid, 0);

                // running as another user
                if (posix_get_last_error() == 1)
                    $running = TRUE;

                if ($running)
                    return TRUE;

            } else {

                exec("ps -p $pid", $output);
                $running = (count($output) > 1) ? TRUE : FALSE;

                if ($running)
                    return TRUE;

            }
        }
       
         
        logit("controller.log", "check_running_role: no running $role script (pid $pid seems dead)");
 
    }

    logit("controller.log", "check_running_role: no running $role script found");

    return FALSE;
}

function logit($file, $message) {
    $file = BASE_FILE . "logs/" . $file;
    $message = date("Y-m-d H:i:s") . " " . $message . "\n";
    file_put_contents($file, $message, FILE_APPEND);
}

function getActivePhrases() {
    $dbh = pdo_connect();
    $sql = "SELECT DISTINCT(p.phrase) FROM tcat_query_phrases p, tcat_query_bins_phrases bp, tcat_query_bins b
                                      WHERE bp.endtime = '0000-00-00 00:00:00' AND p.id = bp.phrase_id
                                            AND bp.querybin_id = b.id AND b.type != 'geotrack' AND b.active = 1"; 
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

function getBinType($binname) {
    $dbh = pdo_connect();
    $sql = "SELECT `type` FROM tcat_query_bins WHERE querybin = :querybin";
    $rec = $dbh->prepare($sql);
    $rec->bindParam(':querybin', $binname);
    $rec->execute();
    $results = $rec->fetchAll(PDO::FETCH_COLUMN);
    foreach ($results as $k => $v) {
        return $v;
    }
    $dbh = false;
    return false;
}

/*
 * How many, if any, geobins are active?
 */

function geobinsActive() {
    $dbh = pdo_connect();
    $sql = "SELECT COUNT(*) AS cnt FROM tcat_query_bins WHERE `type` = 'geotrack' and active = 1";
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

/*
 * Create a full location query string from multiple coordinate 'phrases' (geobox phrases)
 */

function getActiveLocationsImploded() {
    $dbh = pdo_connect();
    $sql = "SELECT phrase FROM tcat_query_phrases p, tcat_query_bins_phrases bp, tcat_query_bins b
                                      WHERE bp.endtime = '0000-00-00 00:00:00' AND p.id = bp.phrase_id
                                            AND bp.querybin_id = b.id AND b.type = 'geotrack' AND b.active = 1"; 
    $rec = $dbh->prepare($sql);
    $rec->execute();
    $results = $rec->fetchAll(PDO::FETCH_COLUMN);
    $locations = '';
    foreach ($results as $k => $v) {
        $locations .= $v.",";
    }
    $locations = substr($locations,0,-1);
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
    $sql = "SELECT b.querybin, p.phrase FROM tcat_query_bins b, tcat_query_phrases p, tcat_query_bins_phrases bp WHERE b.active = 1 AND bp.querybin_id = b.id AND bp.phrase_id = p.id AND bp.endtime = '0000-00-00 00:00:00'";
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

function getActiveFollowBins() {
    $dbh = pdo_connect();
    $sql = "SELECT b.querybin, u.id AS uid FROM tcat_query_bins b, tcat_query_users u, tcat_query_bins_users bu WHERE b.active = 1 AND bu.querybin_id = b.id AND bu.user_id = u.id AND bu.endtime = '0000-00-00 00:00:00'";
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
    $sql = "select querybin from tcat_query_bins where type = 'onepercent' and active = 1";
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

function queryManagerBinExists($binname) {
    $dbh = pdo_connect();
    $rec = $dbh->prepare("SELECT id FROM tcat_query_bins WHERE querybin = :binname");
    $rec->bindParam(":binname", $binname, PDO::PARAM_STR);
    if ($rec->execute() && $rec->rowCount() > 0) { // check whether the table has already been imported
        $res = $rec->fetch();
        print "The query bin '$binname' already exists. Are you sure you want to add tweets to '$binname'? (yes/no)" . PHP_EOL;
        if (trim(fgets(fopen("php://stdin", "r"))) != 'yes')
            die('Abort' . PHP_EOL);
        return $res['id'];
    }
    return false;
}

function queryManagerCreateBinFromExistingTables($binname, $querybin_id, $type, $queries = array()) {
    $dbh = pdo_connect();

    // select start and end of dataset
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

function queryManagerInsertPhrases($querybin_id, $phrases, $starttime = "0000-00-00 00:00:00", $endtime = "0000-00-00 00:00:00") {
    $dbh = pdo_connect();
    foreach ($phrases as $phrase) {
        $phrase = trim($phrase);
        if (empty($phrase))
            continue;
        $sql = "INSERT IGNORE INTO tcat_query_phrases (phrase) VALUES (:phrase)";
        $rec = $dbh->prepare($sql);
        $rec->bindParam(':phrase', $phrase, PDO::PARAM_STR); //
        if (!$rec->execute() || !$rec->rowCount())
            die("failed to insert phrase $phrase\n");
        $phrase_id = $dbh->lastInsertId();
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

    global $tweetbucket;

    if (is_array($tweetbucket) && is_callable('processtweets')) {
        logit(CAPTURE . ".error.log", "flushing the capture buffer");
        $copy = $tweetbucket;         // avoid any parallel processing by copy and then empty
        $tweetbucket = array();

        processtweets($copy);
    } else {
        logit(CAPTURE . ".error.log", "failed to flush capture buffer");
    }
}

function capture_signal_handler_term($signo) {

    global $exceeding, $ratelimit, $ex_start;

    logit(CAPTURE . ".error.log", "received TERM signal");

    capture_flush_buffer();

    logit(CAPTURE . ".error.log", "writing rate limit information to database");

    if (isset($exceeding) && $exceeding == 1) {
        ratelimit_record($ratelimit, $ex_start);
    }

    logit(CAPTURE . ".error.log", "exiting now on TERM signal");

    exit(0);
}

/**
 * 
 * Tweet entity
 * Based on Twitter API 1.1
 * 
 */
class Tweet {

    // Fields copied from a sample timeline provided by the Twitter API
    // @see https://dev.twitter.com/docs/api/1.1/get/statuses/mentions_timeline

    public $favorited;
    public $contributors;
    public $truncated;
    public $text;
    public $created_at;
    public $retweeted;
    public $retweeted_status;
    public $in_reply_to_status_id;
    public $coordinates;
    public $in_reply_to_status_id_str;
    public $in_reply_to_screen_name;
    public $place;
    public $retweet_count;
    public $geo;
    public $source;
    public $id;
    public $id_str;
    public $possibly_sensitive;
    public $in_reply_to_user_id;
    public $user_mentions = array();
    public $hashtags = array();
    public $urls = array();
    public $user;
    public $retweet_id = null;
    public $favorite_count;
    public $filter_level;
    public $timezone;
    public $lang;

    public function __construct($obj = null) {
        if (isset($obj)) {
            foreach ($obj as $k => $v) {
                $this->{$k} = $v;
            }
        }
    }

    public function __set($name, $value) {

        if (in_array($name, get_class_vars(get_class($this)))) {
            if (is_array($this->{$k})) {
                print 'array';
            } else {
                $this->{$name} = $value;
            }
        } elseif ($name == "_id") {
            $this->id = $value;
        } elseif ($name == "_id_str") {
            $this->id = $value;
        } elseif ($name == "in_reply_to_user_id_str") {
            $this->in_reply_to_user_id_str = $value;
        } elseif ($name == "random_number" || $name == "withheld_scope" || $name == "status" || $name == "withheld_in_countries" || $name == "withheld_copyright") {
            print $name . "=" . $value . " not available as a database field\n";
            return;
        } elseif ($name == "metadata") {
            return;
        } else {
            print "Trying to set non existing class property: $name=$value\n";
            //throw new Exception("Trying to set non existing class property: $name");
        }
    }

    public static function fromJSON($json) {
        // Parse JSON when fed JSON string
        if (is_string($json)) {
            $object = json_decode($json);
        } else if (is_object($json)) {
            $object = $json;
        } else {
            throw new Exception('Invalid JSON input');
        }

        $urls = $object->entities->urls;
        $user_mentions = $object->entities->user_mentions;
        $hashtags = $object->entities->hashtags;
        unset($object->entities);

        $tweet = new self($object);
        $tweet->urls = $urls;
        $tweet->user_mentions = $user_mentions;
        $tweet->hashtags = $hashtags;

        return $tweet;
    }

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
        $t->timezone = null;
        if (isset($object->twitterTimeZone))
            $t->timezone = $object->twitterTimeZone;

        $t->user = new StdClass();
        $t->user->screen_name = $object->actor->preferredUsername;
        $t->user->id = str_replace("id:twitter.com:", "", $object->actor->id);
        $t->user->url = $object->actor->link;
        $t->user->lang = $object->actor->languages[0];
        $t->user->statuses_count = $object->actor->statusesCount;
        $t->user->followers_count = $object->actor->followersCount;
        $t->user->friends_count = $object->actor->friendsCount;
        $t->user->name = $object->actor->displayName;
        if (isset($object->actor->location))
            $t->user->location = $object->actor->location->displayName;
        $t->user->listed_count = $object->actor->listedCount;
        $t->user->utcoffset = $object->actor->utcOffset;
        $t->user->timezone = $object->actor->twitterTimeZone;
        $t->user->description = $object->actor->summary;
        $t->user->from_user_profile_image_url = $object->actor->image;
        $t->user->verified = $object->actor->verified;

        $t->source = $object->generator->displayName; // @todo, is this right?
        $t->geo->coordinates[0] = null; // @todo
        $t->geo->coordinates[1] = null; // @todo

        $t->in_reply_to_user_id = null; // @todo
        $t->in_reply_to_screen_name = null; // @todo
        $t->in_reply_to_status_id = null; // @todo

        $t->retweet_count = $object->retweetCount;
        if ($t->retweet_count != 0 && isset($object->object)) {
            $t->retweet_id = preg_replace("/tag:.*:/", "", $object->object->id);
        }
        $t->filter_level = $object->twitter_filter_level;
        if (isset($object->twitter_entities->twitter_lang))
            $t->lang = $object->twitter_entities->twitter_lang;

        $t->favorite_count = $object->favoritesCount;


        $t->urls = $object->twitter_entities->urls;
        $t->user_mentions = $object->twitter_entities->user_mentions;
        $t->hashtags = $object->twitter_entities->hashtags;

        return $t;
    }

    public function save(PDO $dbh, $bin_name) {

        $q = $dbh->prepare("REPLACE INTO " . $bin_name . '_tweets' . "
                        (id, created_at,  from_user_name, from_user_id, from_user_lang, 
                        from_user_tweetcount, from_user_followercount, from_user_friendcount, 
                        from_user_realname, source, location, geo_lat, geo_lng, text, 
                        to_user_id, to_user_name,in_reply_to_status_id, 
                        from_user_listed, from_user_utcoffset, from_user_timezone, from_user_description,from_user_url,from_user_verified,
                        retweet_id,retweet_count,favorite_count,filter_level,lang,from_user_profile_image_url) 
                        VALUES 
                        (:id, :created_at, :from_user_name, :from_user_id, :from_user_lang,
                        :from_user_tweetcount, :from_user_followercount, :from_user_friendcount, 
                        :from_user_realname, :source, :location, :geo_lat, :geo_lng, :text, 
                        :to_user_id, :to_user_name, :in_reply_to_status_id,
                        :from_user_listed, :from_user_utcoffset, :from_user_timezone, :from_user_description, :from_user_url, :from_user_verified,
                        :retweet_id, :retweet_count, :favorite_count, :filter_level,:lang,:from_user_profile_image_url
                        ) 
                        ;");
        //var_export($this);
        $q->bindParam(':id', $this->id_str, PDO::PARAM_STR); //
        $date = date("Y-m-d H:i:s", strtotime($this->created_at));
        $q->bindParam(':created_at', $date, PDO::PARAM_STR); //
        $q->bindParam(':from_user_name', $this->user->screen_name, PDO::PARAM_STR); //
        $q->bindParam(':from_user_id', $this->user->id_str, PDO::PARAM_STR);    //
        $q->bindParam(':from_user_lang', $this->user->lang, PDO::PARAM_STR); //
        $q->bindParam(':from_user_tweetcount', $this->user->statuses_count, PDO::PARAM_STR); //
        $q->bindParam(':from_user_followercount', $this->user->followers_count, PDO::PARAM_INT); //
        $q->bindParam(':from_user_friendcount', $this->user->friends_count, PDO::PARAM_INT); //
        $q->bindParam(':from_user_realname', $this->user->name, PDO::PARAM_STR); //
        $q->bindParam(':source', $this->source, PDO::PARAM_STR); //
        $q->bindParam(':location', $this->user->location, PDO::PARAM_STR); //
        $geo_lat = $this->geo ? (string) $this->geo->coordinates[0] : 'null'; //
        $geo_lng = $this->geo ? (string) $this->geo->coordinates[1] : 'null'; //
        $q->bindParam(':geo_lat', $geo_lat, PDO::PARAM_STR); //
        $q->bindParam(':geo_lng', $geo_lng, PDO::PARAM_STR); //
        $q->bindParam(':text', $this->text, PDO::PARAM_STR); //
        $q->bindParam(':to_user_id', $this->in_reply_to_user_id_str, PDO::PARAM_STR); //
        $q->bindParam(':to_user_name', $this->in_reply_to_screen_name, PDO::PARAM_STR); //
        $q->bindParam(':in_reply_to_status_id', $this->in_reply_to_status_id_str, PDO::PARAM_STR); //

        $q->bindParam(':from_user_listed', $this->user->listed_count, PDO::PARAM_INT); //
        $q->bindParam(':from_user_utcoffset', $this->user->utcoffset, PDO::PARAM_STR); //  
        $q->bindParam(':from_user_timezone', $this->user->timezone, PDO::PARAM_STR); //   
        $q->bindParam(':from_user_description', $this->user->description, PDO::PARAM_STR); //
        $q->bindParam(':from_user_url', $this->user->url, PDO::PARAM_STR); //     
        $q->bindParam(':from_user_profile_image_url', $this->user->profile_image_url, PDO::PARAM_STR);
        $q->bindParam(':from_user_verified', $this->user->verified, PDO::PARAM_STR); //
        $retweet_id = $this->retweeted_status ? (string) $this->retweeted_status->id_str : null;
        $q->bindParam(':retweet_id', $retweet_id, PDO::PARAM_STR); //    
        $q->bindParam(':retweet_count', $this->retweet_count, PDO::PARAM_STR); // 
        $q->bindParam(':favorite_count', $this->favorite_count, PDO::PARAM_STR); //
        $q->bindParam(':filter_level', $this->filter_level, PDO::PARAM_STR); //
        $q->bindParam(':lang', $this->lang, PDO::PARAM_STR); //

        $saved_tweet = $q->execute();
        // if tweet already exists, do not update hashtags, mentions, urls. As they have no unique constraint, it would just add extra info. _tweets has id as its unique primary key
        if ($q->rowCount() > 1) {   // The affected-rows count makes it easy to determine whether REPLACE only added a row or whether it also replaced any rows: Check whether the count is 1 (added) or greater (replaced).
            return $q->rowCount();
        }

        if ($this->hashtags) {
            foreach ($this->hashtags as $hashtag) {
                $q = $dbh->prepare("REPLACE INTO " . $bin_name . '_hashtags' . "
                                        (tweet_id, created_at, from_user_name, from_user_id, text) 
                                        VALUES (:tweet_id, :created_at , :from_user_name, :from_user_id, :text)");

                $q->bindParam(':tweet_id', $this->id_str, PDO::PARAM_STR);
                $date = date("Y-m-d H:i:s", strtotime($this->created_at));
                $q->bindParam(':created_at', $date, PDO::PARAM_STR);
                $q->bindParam(':from_user_name', $this->user->screen_name, PDO::PARAM_STR);
                $q->bindParam(':from_user_id', $this->user->id_str, PDO::PARAM_STR);
                $q->bindParam(':text', $hashtag->text, PDO::PARAM_STR);

                $saved_hashtags = $q->execute();
            }
        }

        if ($this->urls) {
            foreach ($this->urls as $url) {
                $q = $dbh->prepare("REPLACE INTO " . $bin_name . '_urls' . "
                                        (tweet_id, created_at, from_user_name, from_user_id, url, url_expanded) 
                                        VALUES (:tweet_id, :created_at , :from_user_name, :from_user_id, :url, :url_expanded)");

                $q->bindParam(':tweet_id', $this->id_str, PDO::PARAM_STR);
                $date = date("Y-m-d H:i:s", strtotime($this->created_at));
                $q->bindParam(':created_at', $date, PDO::PARAM_STR);
                $q->bindParam(':from_user_name', $this->user->screen_name, PDO::PARAM_STR);
                $q->bindParam(':from_user_id', $this->user->id_str, PDO::PARAM_STR);
                $q->bindParam(':url', $url->url, PDO::PARAM_STR);
                $q->bindParam(':url_expanded', $url->expanded_url, PDO::PARAM_STR);

                $saved_urls = $q->execute();
            }
        }

        if ($this->user_mentions) {
            foreach ($this->user_mentions as $mention) {
                $q = $dbh->prepare("REPLACE INTO " . $bin_name . '_mentions' . "
                                        (tweet_id, created_at, from_user_name, from_user_id, to_user, to_user_id) 
                                        VALUES (:tweet_id, :created_at , :from_user_name, :from_user_id, :to_user, :to_user_id)");

                $q->bindParam(':tweet_id', $this->id_str, PDO::PARAM_STR);
                $date = date("Y-m-d H:i:s", strtotime($this->created_at));
                $q->bindParam(':created_at', $date, PDO::PARAM_STR);
                $q->bindParam(':from_user_name', $this->user->screen_name, PDO::PARAM_STR);
                $q->bindParam(':from_user_id', $this->user->id_str, PDO::PARAM_STR);
                $q->bindParam(':to_user', $mention->screen_name, PDO::PARAM_STR);
                $q->bindParam(':to_user_id', $mention->id_str, PDO::PARAM_STR);

                $saved_mentions = $q->execute();
            }
        }

        return $saved_tweet;
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
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8";

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

/*
 * Start a tracking process
 */

function tracker_run() {

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
  logit(CAPTURE . ".error.log",  "running php version $phpstring");

  // install the signal handler
  if (function_exists('pcntl_signal')) {

      // tick use required as of PHP 4.3.0
      declare(ticks = 1);

      // See signal method discussion:
      // http://darrendev.blogspot.nl/2010/11/php-53-ticks-pcntlsignal.html

      logit(CAPTURE . ".error.log",  "installing term signal handler for this script");

      // setup signal handlers
      pcntl_signal(SIGTERM, "capture_signal_handler_term");

  } else {

      logit(CAPTURE . ".error.log",  "your php installation does not support signal handlers. graceful reload will not work");

  }

  global $ratelimit, $exceeding, $ex_start, $last_insert_id;

  $ratelimit = 0;     // rate limit counter since start of script
  $exceeding = 0;     // are we exceeding the rate limit currently?
  $ex_start = 0;      // time at which rate limit started being exceeded
  $last_insert_id = -1;

  global $twitter_consumer_key, $twitter_consumer_secret, $twitter_user_token, $twitter_user_secret, $lastinsert;

  $pid = getmypid();
  logit(CAPTURE . ".error.log", "started script " . CAPTURE . " with pid $pid");

  $lastinsert = time();
  $procfilename = BASE_FILE . "proc/" . CAPTURE . ".procinfo";
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
  }
  elseif (CAPTURE == "follow") {
      $querylist = getActiveUsers();
      if (empty($querylist)) {
          logit(CAPTURE . ".error.log", "empty query list, aborting!");
          return;
      }
      $method = $networkpath . '1.1/statuses/filter.json';
      $params = array("follow" => implode(",", $querylist));
  }
  elseif (CAPTURE == "onepercent") {
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

  $tweetbucket = array();
  $tmhOAuth->streaming_request('POST', $method, $params, 'tracker_streamCallback', array('Host' => 'stream.twitter.com'));

  // output any response we get back AFTER the Stream has stopped -- or it errors
  logit(CAPTURE . ".error.log", "stream stopped - error " . var_export($tmhOAuth, 1));

  logit(CAPTURE . ".error.log", "processing buffer before exit");
  processtweets($tweetbucket);

}

/*
 * Stream callback function
 */

function tracker_streamCallback($data, $length, $metrics) {
    global $tweetbucket, $lastinsert;
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
                // @todo: avoid writing this on every callback
                logit(CAPTURE . ".error.log", "twitter api warning received: ($code) $message [percentage full $full]");
            } else {
                logit(CAPTURE . ".error.log", "twitter api warning received: ($code) $message");
            }
        }

        // handle rate limiting
        if (array_key_exists('limit', $data)) {
            global $ratelimit, $exceeding, $ex_start;
            if (isset($data['limit'][CAPTURE])) {
                $current = $data['limit'][CAPTURE];
                if ($current > $ratelimit) {
                    // currently exceeding rate limit
                    if (!$exceeding) {
                        // new disturbance!
                        $ex_start = time();
                        ratelimit_report_problem();
                        // logit(CAPTURE . ".error.log", "you have hit a rate limit. consider reducing your query bin sizes");
                    }
                    $ratelimit = $current;
                    $exceeding = 1;

                    if (time() > ($ex_start + RATELIMIT_SILENCE * 6)) {
                        // every half an hour (or: heartbeat x 6), record, but keep the exceeding flag set
                        ratelimit_record($ratelimit, $ex_start);
                        $ex_start = time();
                    }
                } elseif ($exceeding && time() < ($ex_start + RATELIMIT_SILENCE)) {
                    // we are now no longer exceeding the rate limit
                    // to avoid flip-flop we only reset our values after the minimal heartbeat has passed
                    // store rate limit disturbance information in the database
                    ratelimit_record($ratelimit, $ex_start);
                    $ex_start = 0;
                    $exceeding = 0;
                }
            }
            unset($data['limit']);
        }

        $tweetbucket[] = $data;
        if (count($tweetbucket) == 100 || $now > $lastinsert + 5) {
            processtweets($tweetbucket);
            $lastinsert = time();
            $tweetbucket = array();
        }
    }
}

/*
 * Process tweet function
 */

function processtweets($tweetbucket) {

    if (CAPTURE == "track") {
        $querybins = getActiveTrackBins();
    } elseif (CAPTURE == "follow") {
        $querybins = getActiveFollowBins();
    } elseif (CAPTURE == "onepercent") {
        $querybins = getActiveOnepercentBin();
    }

    // we run through every bin to check whether the received tweets fit
    foreach ($querybins as $binname => $queries) {
	create_bin($bin_name);
        $list_tweets = array();
        $list_hashtags = array();
        $list_urls = array();
        $list_mentions = array();

        $geobin = (getBinType($binname) == 'geotrack');

        // running through every single tweet
        foreach ($tweetbucket as $data) {

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

            if ($geobin && (!array_key_exists('geo_enabled', $data['user']) || $data['user']['geo_enabled'] !== true)) {
	        // in geobins, process only geo tweets
		continue;
	    }

            $found = false;

            if (CAPTURE == "track") {

                // we check for every query in the bin if they fit

                foreach ($queries as $query => $track) {

                    if ($geobin) {

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
			 *    Twitter returns the tweets if one of these place polygons coverts the same area as our geo boxes.  
                         *
                         * And matching (by us)
			 *
                         * 1) These tweets will be put in the bin if the coordinate pair (longitude, latitude) fits in any one of the defined geoboxes in the bin.
                         * 2) These tweets will be put in the bin if the geobox is _not_ completely subsumed by the place (example: the place is France and the geobox is Paris), but the geobox does overlap the place polygon or the geobox subsumes the place polygon.
                         *
    `                    */

                        if ($data["geo"] != null) {
                            $tweet_lat = $data["geo"]["coordinates"][0];
                            $tweet_lng = $data["geo"]["coordinates"][1];
                            if (!preg_match("/[^\-0-9,\.]/", $track)) {
                                $boxes = getGeoBoxes($track);
                                if (!empty($boxes)) {

                                    // does the tweet geo data fit in on of the boxes?

                                    foreach ($boxes as $box) {
                                        if (coordinatesInsideBoundingBox($tweet_lng, $tweet_lat, $box['sw_lng'], $box['sw_lat'], $box['ne_lng'], $box['ne_lat'])) {
                                            logit(CAPTURE . ".error.log", "(debug) tweet with lng $tweet_lng and lat $tweet_lat versus (sw: " . $box['sw_lng'] . "," . $box['sw_lat'] . " ne: " . $box['ne_lng'] . "," . $box['ne_lat'] . ") matched to be inside the area");
                                            $found = true; break;
                                        } else {
                                            logit(CAPTURE . ".error.log", "(debug) tweet with lng $tweet_lng and lat $tweet_lat versus (sw: " . $box['sw_lng'] . "," . $box['sw_lat'] . " ne: " . $box['ne_lng'] . "," . $box['ne_lat'] . ") falls outside the area");
                                        }
                                    }

                                }
                            }
                        } else if (!preg_match("/[^\-0-9,\.]/", $track)) {

                            $boxes = getGeoBoxes($track);
                            if (!empty($boxes)) {

                                // this is a gps tracking query, but the tweet has no gps geo data
                                // Twitter may have matched this tweet based on the user-defined location data

                                if (array_key_exists('place', $data) && array_key_exists('bounding_box', $data['place'])) {

                                    // Make a geoPHP object of the polygon(s) defining the place, by using a WKT (well-known text) string
                                    $wkt = 'POLYGON(';
                                    $polfirst = true;
                                    foreach ($data['place']['bounding_box']['coordinates'] as $p => $pol) {
                                        if ($polfirst) { $polfirst = false; } else { $wkt .= ', '; }
                                        $wkt .= '(';
                                        $first = true;
                                        $first_lng = 0; $first_lat = 0;
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
                                    $place = geoPHP::load($wkt,'wkt');

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
                                                logit(CAPTURE . ".error.log", "place polygon $wkt allies with geobox $boxwkt");
                                                $found = true; break;
                                        }

                                    }
                                    
                                }
                            }
                        }
     
                        if ($found) { break; }

                    } else {

                        // look for keyword matches

			$pass = false;

			// check for queries with more than one word, but go around quoted queries
			if (preg_match("/ /", $query) && !preg_match("/'/", $query)) {
			    $tmplist = explode(" ", $query);

			    $all = true;

			    foreach ($tmplist as $tmp) {
				if (!preg_match("/" . $tmp . "/i", $data["text"])) {
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

			    if (preg_match("/" . $query . "/i", $data["text"])) {
				$pass = true;
			    }
			}

			// at the first fitting query, we break
			if ($pass == true) {
			    $found = true;
			    break;
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

            $t = array();
            $t["id"] = $data["id_str"];
            $t["created_at"] = date("Y-m-d H:i:s", strtotime($data["created_at"]));
            $t["from_user_name"] = addslashes($data["user"]["screen_name"]);
            $t["from_user_id"] = $data["user"]["id_str"];
            $t["from_user_lang"] = $data["user"]["lang"];
            $t["from_user_tweetcount"] = $data["user"]["statuses_count"];
            $t["from_user_followercount"] = $data["user"]["followers_count"];
            $t["from_user_friendcount"] = $data["user"]["friends_count"];
            $t["from_user_listed"] = $data["user"]["listed_count"];
            $t["from_user_realname"] = addslashes($data["user"]["name"]);
            $t["from_user_utcoffset"] = $data["user"]["utc_offset"];
            $t["from_user_timezone"] = addslashes($data["user"]["time_zone"]);
            $t["from_user_description"] = addslashes($data["user"]["description"]);
            $t["from_user_url"] = addslashes($data["user"]["url"]);
            $t["from_user_verified"] = $data["user"]["verified"];
            $t["from_user_profile_image_url"] = $data["user"]["profile_image_url"];
            $t["source"] = addslashes($data["source"]);
            $t["location"] = addslashes($data["user"]["location"]);
            $t["geo_lat"] = 0;
            $t["geo_lng"] = 0;
            if ($data["geo"] != null) {
                $t["geo_lat"] = $data["geo"]["coordinates"][0];
                $t["geo_lng"] = $data["geo"]["coordinates"][1];
            }
            $t["text"] = addslashes($data["text"]);
            $t["retweet_id"] = null;
            if (isset($data["retweeted_status"])) {
                $t["retweet_id"] = $data["retweeted_status"]["id_str"];
            }
            $t["to_user_id"] = $data["in_reply_to_user_id_str"];
            $t["to_user_name"] = addslashes($data["in_reply_to_screen_name"]);
            $t["in_reply_to_status_id"] = $data["in_reply_to_status_id_str"];
            $t["filter_level"] = '';
            if (isset($data["filter_level"])) {
                $t["filter_level"] = $data["filter_level"];
            }

            $list_tweets[] = "('" . implode("','", $t) . "')";


            if (count($data["entities"]["hashtags"]) > 0) {
                foreach ($data["entities"]["hashtags"] as $hashtag) {
                    $h = array();
                    $h["tweet_id"] = $t["id"];
                    $h["created_at"] = $t["created_at"];
                    $h["from_user_name"] = $t["from_user_name"];
                    $h["from_user_id"] = $t["from_user_id"];
                    $h["text"] = addslashes($hashtag["text"]);

                    $list_hashtags[] = "('" . implode("','", $h) . "')";
                }
            }

            if (count($data["entities"]["urls"]) > 0) {
                foreach ($data["entities"]["urls"] as $url) {
                    $u = array();
                    $u["tweet_id"] = $t["id"];
                    $u["created_at"] = $t["created_at"];
                    $u["from_user_name"] = $t["from_user_name"];
                    $u["from_user_id"] = $t["from_user_id"];
                    $u["url"] = $url["url"];
                    $u["url_expanded"] = addslashes($url["expanded_url"]);

                    $list_urls[] = "('" . implode("','", $u) . "')";
                }
            }

            if (count($data["entities"]["user_mentions"]) > 0) {
                foreach ($data["entities"]["user_mentions"] as $mention) {
                    $m = array();
                    $m["tweet_id"] = $t["id"];
                    $m["created_at"] = $t["created_at"];
                    $m["from_user_name"] = $t["from_user_name"];
                    $m["from_user_id"] = $t["from_user_id"];
                    $m["to_user"] = $mention["screen_name"];
                    $m["to_user_id"] = $mention["id_str"];

                    $list_mentions[] = "('" . implode("','", $m) . "')";
                }
            }
        }

        // distribute tweets into bins
        if (count($list_tweets) > 0) {

            $sql = "INSERT IGNORE INTO " . $binname . "_tweets (id,created_at,from_user_name,from_user_id,from_user_lang,from_user_tweetcount,from_user_followercount,from_user_friendcount,from_user_listed,from_user_realname,from_user_utcoffset,from_user_timezone,from_user_description,from_user_url,from_user_verified,from_user_profile_image_url,source,location,geo_lat,geo_lng,text,retweet_id,to_user_id,to_user_name,in_reply_to_status_id,filter_level) VALUES " . implode(",", $list_tweets);

            $sqlresults = mysql_query($sql);
            if (!$sqlresults) {
                logit(CAPTURE . ".error.log", "insert error: " . $sql);
            } elseif (database_activity()) {
                $pid = getmypid();
                file_put_contents(BASE_FILE . "proc/" . CAPTURE . ".procinfo", $pid . "|" . time());
            }
        }

        if (count($list_hashtags) > 0) {

            $sql = "INSERT IGNORE INTO " . $binname . "_hashtags (tweet_id,created_at,from_user_name,from_user_id,text) VALUES " . implode(",", $list_hashtags);

            $sqlresults = mysql_query($sql);
            if (!$sqlresults) {
                logit(CAPTURE . ".error.log", "insert error: " . $sql);
            }
        }

        if (count($list_urls) > 0) {

            $sql = "INSERT IGNORE INTO " . $binname . "_urls (tweet_id,created_at,from_user_name,from_user_id,url,url_expanded) VALUES " . implode(",", $list_urls);

            $sqlresults = mysql_query($sql);
            if (!$sqlresults) {
                logit(CAPTURE . ".error.log", "insert error: " . $sql);
            }
        }

        if (count($list_mentions) > 0) {

            $sql = "INSERT IGNORE INTO " . $binname . "_mentions (tweet_id,created_at,from_user_name,from_user_id,to_user,to_user_id) VALUES " . implode(",", $list_mentions);

            $sqlresults = mysql_query($sql);
            if (!$sqlresults) {
                logit(CAPTURE . ".error.log", "insert error: " . $sql);
            }
        }
    }
    return TRUE;
}

function safe_feof($fp, &$start = NULL) {
    $start = microtime(true);
    return feof($fp);
}

function database_activity() {
    global $last_insert_id;
    // we explicitely use the MySQL function last_insert_id
    // we don't want any PHP caching of insert id's()
    $results = mysql_query("SELECT LAST_INSERT_ID()");
    if (!$results) {
        return FALSE;
    }
    $row = mysql_fetch_row($results);
    $lid = $row[0];
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


?>
