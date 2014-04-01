<?php

error_reporting(E_ALL);
if(isset($argc) && $argc>1) {
    // tick use required as of PHP 4.3.0
    declare(ticks = 1);
    //setup signal handlers
    pcntl_signal(SIGTERM, "capture_signal_handler_term");
}

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

function create_bin($bin_name, $dbh = false) {
    $dbh = pdo_connect();
    $sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_hashtags (
		id int(11) NOT NULL AUTO_INCREMENT,
		tweet_id bigint(20) NOT NULL,
		created_at datetime,
		from_user_name varchar(255),
		from_user_id bigint,
		`text` varchar(255),
		PRIMARY KEY (id),
                KEY `created_at` (`created_at`),
		KEY `tweet_id` (`tweet_id`),
		KEY `text` (`text`),
                KEY `from_user_name` (`from_user_name`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8";

    $create_hashtags = $dbh->prepare($sql);
    $create_hashtags->execute();

    $sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_mentions (
		id int(11) NOT NULL AUTO_INCREMENT,
		tweet_id bigint(20) NOT NULL,
		created_at datetime,
		from_user_name varchar(255),
		from_user_id bigint, 
		to_user varchar(255),
		to_user_id bigint,
		PRIMARY KEY (id),
                KEY `created_at` (`created_at`),
		KEY `tweet_id` (`tweet_id`),
                KEY `from_user_name` (`from_user_name`),
                KEY `from_user_id` (`from_user_id`),
                KEY `to_user` (`to_user`),
                KEY `to_user_id` (`to_user_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

    $create_mentions = $dbh->prepare($sql);
    $create_mentions->execute();

    $sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_tweets (
		id bigint(20) NOT NULL,
                created_at datetime NOT NULL,
                from_user_name varchar(255) NOT NULL,
                from_user_id bigint NOT NULL,
                from_user_lang varchar(16),
                from_user_tweetcount int(11),
                from_user_followercount int(11),
                from_user_friendcount int(11),
                from_user_listed int(11),
                from_user_realname varchar(255),
                from_user_utcoffset int(11),
                from_user_timezone varchar(255),
                from_user_description varchar(255),
                from_user_url varchar(2048),
                from_user_verified bool DEFAULT false,
                from_user_profile_image_url varchar(400),
                source varchar(512),
                location varchar(64),
                geo_lat float(10,6),
                geo_lng float(10,6),
                text varchar(255) NOT NULL,
                retweet_id bigint(20),
                retweet_count int(11),
                favorite_count int(11),
                to_user_id bigint,
                to_user_name varchar(255),
                in_reply_to_status_id bigint(20),
                filter_level varchar(6),
                lang varchar(16),
                PRIMARY KEY (id),
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

    $sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_urls (
		id int(11) NOT NULL AUTO_INCREMENT,
		tweet_id bigint(20) NOT NULL,
		created_at datetime,
		from_user_name varchar(255),
		from_user_id bigint,
		url varchar(2048),
		url_expanded varchar(2048),
		url_followed varchar(4096),
		domain varchar(2048),
		error_code varchar(64),
		PRIMARY KEY (id),
                KEY `tweet_id` (`tweet_id`),                
                KEY `created_at` (`created_at`),
                KEY `from_user_id` (`from_user_id`),
                FULLTEXT KEY `url_followed` (`url_followed`),
                KEY `url_expanded` (`url_expanded`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

    $create_urls = $dbh->prepare($sql);
    $create_urls->execute();
    $dbh = false;
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

/*
 * Force a task to reload it configuration, should be called by the controller process
 */

function controller_reload_config_role($role) {

    if (!check_running_role($role)) {
        return FALSE;
    }

    if (file_exists(BASE_FILE . "proc/$role.procinfo")) {

        $procfile = file_get_contents(BASE_FILE . "proc/$role.procinfo");

        $tmp = explode("|", $procfile);
        $pid = $tmp[0];
        $last = $tmp[1];

        if (is_numeric($pid) && $pid > 0) {

            logit("controller.log", "controller_reload_config_role: enforcing reload of config for $role");

            // check whether the process was started by another user
            posix_kill($pid, 0);
            if (posix_get_last_error() == 1) {
                logit("controller.log", "unable to kill $role, it seems to be running under another user\n");
                return FALSE;
            }

            // kill script with pid $pid
            logit("controller.log", "controller_reload_config_role: sending a TERM signal to $role for $pid");
            posix_kill($pid, SIGTERM);

            // test whether the process really has been killed
            $i = 0;
            $sleep = 5;
            // while we can still signal the pid
            while (posix_kill($pid, 0)) {
                logit("controller.log", "controller_reload_config_role: waiting for graceful exit of script $role with pid $pid");
                // we need some time to allow graceful exit
                sleep($sleep);
                $i++;
                if ($i == 10) {
                    $failmsg = "controller_reload_config_role: unable to kill script $role with pid $pid after " . ($sleep * $i) . " seconds";
                    logit("controller.log", $failmsg);
                    return FALSE;
                }
            }

            logit("controller.log", "controller_reload_config_role: starting new instance of $role script");

            // this command starts the capture task as a detached process and report back its pid
            $cmd = PHP_CLI . " " . BASE_FILE . "capture/stream/$role.php > /dev/null & echo $!";
            $pid = shell_exec($cmd);

            return $pid;
        }
    }

    return FALSE;
}

function logit($file, $message) {
    $file = BASE_FILE . "logs/" . $file;
    $message = date("Y-m-d H:i:s") . " " . $message . "\n";
    file_put_contents($file, $message, FILE_APPEND);
}

function getActivePhrases() {
    $dbh = pdo_connect();
    $sql = "SELECT DISTINCT(p.phrase) FROM tcat_query_phrases p, tcat_query_bins_phrases bp WHERE bp.endtime = '0000-00-00 00:00:00' AND p.id = bp.phrase_id";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    $results = $rec->fetchAll(PDO::FETCH_COLUMN);
    foreach ($results as $k => $v)
        $results[$k] = trim(preg_replace("/'/", "", $v));
    $results = array_unique($results);
    $dbh = false;
    return $results;
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

?>
