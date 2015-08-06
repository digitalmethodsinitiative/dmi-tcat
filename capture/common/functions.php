<?php

require_once("geoPHP/geoPHP.inc"); // geoPHP library

error_reporting(E_ALL);
ini_set("max_execution_time", 0);       // capture script want unlimited execution time

function pdo_connect() {
    global $dbuser, $dbpass, $database, $hostname;

    $dbh = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8mb4", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $dbh;
}

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
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4";

        $create_hashtags = $dbh->prepare($sql);
        $create_hashtags->execute();

        $sql = "CREATE TABLE IF NOT EXISTS " . quoteIdent($bin_name . "_withheld") . " (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tweet_id` bigint(20) NOT NULL,
            `user_id` bigint(20),
            `country` char(5),
            PRIMARY KEY (`id`),
                    KEY `user_id` (`user_id`),
                    KEY `tweet_id` (`user_id`),
                    KEY `country` (`country`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4";

        $create_withheld = $dbh->prepare($sql);
        $create_withheld->execute();


        $sql = "CREATE TABLE IF NOT EXISTS " . quoteIdent($bin_name . "_places") . " (
            `id` varchar(32) NOT NULL,
            `tweet_id` bigint(20) NOT NULL,
            PRIMARY KEY (`id`, `tweet_id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4";

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
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4";

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
                    `text` varchar(255) NOT NULL,
                    `retweet_id` bigint(20),
                    `retweet_count` int(11),
                    `favorite_count` int(11),
                    `to_user_id` bigint,
                    `to_user_name` varchar(255),
                    `in_reply_to_status_id` bigint(20),
                    `filter_level` varchar(6),
                    `lang` varchar(16),
                    `possibly_sensitive` tinyint(1),
                    `truncated` tinyint(1),
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
                    KEY `possibly_sensitive` (`possibly_sensitive`),
                    KEY `withheld_copyright` (`withheld_copyright`),
                    KEY `withheld_scope` (`withheld_scope`),
                    FULLTEXT KEY `from_user_description` (`from_user_description`),
                    FULLTEXT KEY `text` (`text`)
                    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4";

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
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4";

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
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4";

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
    `visible` BOOLEAN DEFAULT TRUE,
    `comments` VARCHAR(2048) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `querybin` (`querybin`),
    KEY `type` (`type`),
    KEY `active` (`active`)
    ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4";
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
    ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4";
    $create = $dbh->prepare($sql);
    $create->execute();

    $sql = "CREATE TABLE IF NOT EXISTS tcat_query_phrases (
    `id` INT NOT NULL AUTO_INCREMENT,
    `phrase` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `phrase` (`phrase`)
    ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4";
    $create = $dbh->prepare($sql);
    $create->execute();

    $sql = "CREATE TABLE IF NOT EXISTS tcat_query_users (
    `id` bigint NOT NULL AUTO_INCREMENT,
    `user_name` varchar(255),
    PRIMARY KEY `id` (`id`)
    ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4";
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
    ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4";
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
    ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4";
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

    $dbh = false;

}

/*
 * Record a ratelimit disturbance
 */

function ratelimit_record($ratelimit, $ex_start) {
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
 * Acquire a lock as script $script
 * If test is true, only test if the lock could be gained, but do not hold on to it (this is how we test if a script is running)
 * Returns true on a lock success (in test), false on failure and a lock filepointer if really locking
 */

function script_lock($script, $test = false) {
    $lockfile = BASE_FILE . "proc/$script.lock";

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
        file_put_contents(BASE_FILE . "logs/" . $file, $message, FILE_APPEND);
}

/*
 * Returns the git status information for the local install
 */
function getGitLocal() {
    $gitcmd = 'git --git-dir ' . BASE_FILE . '.git log --pretty=oneline -n 1';
    $gitlog = `$gitcmd`;
    $parse = rtrim($gitlog);
    if (preg_match("/^([a-z0-9]+)[\t ](.+)$/", $parse, $matches)) {
        $commit = $matches[1];
        $mesg = $matches[2];
        $gitcmd = 'git --git-dir ' . BASE_FILE . '.git rev-parse --abbrev-ref HEAD';
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
        $repository_url = 'https://api.github.com/repos/digitalmethodsinitiative/dmi-tcat/commits';
    } else {
        $repository_url = REPOSITORY_URL;
    }
    $repository_url .= '?sha=' . $branch;
    $ch = curl_init($repository_url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'DMI-TCAT GIT remote version checker (contact us at https://github.com/digitalmethodsinitiative/dmi-tcat)');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($output, true);
    $commit = $mesg = $url = null;
    $required = false;
    foreach ($data as $ent) {
        if ($commit === null) {
            $commit = $ent['sha'];
            $mesg = $ent['commit']['message'];
            $url = $ent['html_url'];
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
                );
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
        $this->geo_lat = null;
        $this->geo_lng = null;
        if ($data["geo"] != null) {
            $this->geo_lat = $data["geo"]["coordinates"][0];
            $this->geo_lng = $data["geo"]["coordinates"][1];
        }
        if (isset($data["retweeted_status"])) {
            $this->text = "RT @" . $data["retweeted_status"]["user"]["screen_name"] . " " . $data["retweeted_status"]["text"];
        }
	else {
            $this->text = $data["text"];
       	}
        $this->retweet_id = null;
        if (isset($data["retweeted_status"])) {
            $this->retweet_id = $data["retweeted_status"]["id_str"];
        }
        $this->retweet_count = $data["retweet_count"];
        $this->favorite_count = $data["favorite_count"];
        $this->to_user_id = $data["in_reply_to_user_id_str"];
        $this->to_user_name = $data["in_reply_to_screen_name"];
        $this->in_reply_to_status_id = $data["in_reply_to_status_id_str"];
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
        $this->truncated = $data["truncated"];
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
                    $this->reportPDOError($e, $bin_name . '_tweets');
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
                file_put_contents(BASE_FILE . "proc/" . CAPTURE . ".procinfo", $pid . "|" . time());
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
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4";

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

    global $tweetQueue;
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

    // cache bin types
    $bintypes = array();
    foreach ($querybins as $binname => $queries) $bintypes[$binname] = getBinType($binname);

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
                            break;
                        }
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

            $tweet = new Tweet();
            $tweet->fromJSON($data);
            $tweetQueue->push($tweet, $binname);
        }
    }
    $tweetQueue->insertDB();
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
