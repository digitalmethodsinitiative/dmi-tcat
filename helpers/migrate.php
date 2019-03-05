<?php

/*
 * Quite some significant changes have been introduced in the past couple of months.
 * This script will check whether everything is still up to date, and if not, what has to be changed.
 * Just run `php migrate.php` and it will guide you through the process.
 * You only need to run this script if you were running DMI-TCAT before the query manager was implemented (6 March 2014).
 * 
 * The most significant changes which this script checks include:
 *      config file has variables and constants
 *      easier way to manage queries and start different types of streams: move from querybins.php and followbins.php to query manager
 *      altered database definitions: type of user ids (19 Feb 2014), type of url_followed (17 Oct 2013), added field from_user_profile_image_url (17 Sep 2013), added lang (17 July 2013)
 *      extra indexes for faster analysis (17 Oct 2013, 14 Oct 2013, july 2013)
 *      addition of different tracking capabilities - track, follow, onepercent (18 December 2013)
 *      logs have been moved to dmi-tcat/logs to allow for systemwide logging (18 December 2013), proc directory so controller can work with pids (18 december)
 */

if ($argc < 1)
    exit(); // only run from command line

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../common/functions.php';
require_once __DIR__ . '/../capture/common/functions.php';

print "Were you running DMI-TCAT before the query manager was implemented (6 March 2014)? (yes/no)" . PHP_EOL;
$ans = trim(fgets(fopen("php://stdin", "r")));
if ($ans == 'no')
    die("You do not need to run this script." . PHP_EOL);
elseif ($ans !== 'yes')
    die('Abort' . PHP_EOL);
print "Please disable all DMI-TCAT related crontabs (e.g. dmi-tcat/capture/streaming/capture.php, dmi-tcat/capture/streaming/controller.php, dmi-tcat/helpers/urlexpand.sh)? Enter 'ok' when you are done." . PHP_EOL;
if (trim(fgets(fopen("php://stdin", "r"))) != 'ok')
    die('Abort' . PHP_EOL);
print "Kill all running php processes related to DMI-TCAT (e.g. dmi-tcat/capture/streaming/track.php)? Enter 'ok' when you are done." . PHP_EOL;
if (trim(fgets(fopen("php://stdin", "r"))) != 'ok')
    die('Abort' . PHP_EOL);
print "Make sure to backup all your data before continuing. E.g. cp -rp /var/www/dmi-tcat ~/dmi-tcat-backup; mysqldump -u $dbuser -p $database > ~/all.twittercapture.sql Enter 'ok' when you backed up all your data" . PHP_EOL;
if (trim(fgets(fopen("php://stdin", "r"))) != 'ok')
    die('Abort' . PHP_EOL);
print "New variables and constants have been added to dmi-tcat/config.php.example Make sure to update your config.php file. See https://github.com/digitalmethodsinitiative/dmi-tcat/wiki/Install-Guide#wiki-config for an explanation. Enter 'ok' when you are done." . PHP_EOL;
if (trim(fgets(fopen("php://stdin", "r"))) != 'ok')
    die('Abort' . PHP_EOL);
print "Set the appropriate permissions for dmi-tcat/analysis/cache, dmi-tcat/logs, and dmi-tcat/proc. See https://github.com/digitalmethodsinitiative/dmi-tcat/wiki/Install-Guide#wiki-config for an explanation. Enter 'ok' when you are done." . PHP_EOL;
if (trim(fgets(fopen("php://stdin", "r"))) != 'ok')
    die('Abort' . PHP_EOL);
print "Authentication and access have been modified too. Please follow the steps at https://github.com/digitalmethodsinitiative/dmi-tcat/wiki/Install-Guide#wiki-authentication-and-access Enter 'ok' when you are done." . PHP_EOL;
if (trim(fgets(fopen("php://stdin", "r"))) != 'ok')
    die('Abort' . PHP_EOL);

print PHP_EOL . "It's time to check your database definitions." . PHP_EOL;

$check = checkDbDefinitions();
if ($check)
    querybinsPhpToDb();
print PHP_EOL . "Migrate script complete. Now you are ready to test whether things are working. Please follow the steps at https://github.com/digitalmethodsinitiative/dmi-tcat/wiki/Install-Guide#wiki-run-stream" . PHP_EOL;

function checkDbDefinitions() {
    global $dbuser, $dbpass, $database, $hostname;

    $statuses = array();
    $dbh = pdo_connect();
    $sql = "SHOW TABLES LIKE '%_tweets'";
    $rec = $dbh->prepare($sql);
    if ($rec->execute() && $rec->rowCount() > 0) {
        $querybins = $rec->fetchAll(PDO::FETCH_COLUMN);
        foreach ($querybins as $querybin) {
            $binname = str_replace("_tweets", "", $querybin);
            $status = checkTableDefinitions($binname);
            if (!empty($status))
                $statuses[$binname] = $status;
        }
    } else {
        print "You do not seem to have any query bins yet, aborting the script as there is nothing more to check." . PHP_EOL;
        return false;
    }
    if (empty($statuses)) {
        print "All you table definitions are OK" . PHP_EOL;
        return true;
    } else {
        print "Your database is outdated. Here is what you can do to fix it:" . PHP_EOL;
        foreach ($statuses as $binname => $status) {
            if (array_search("touserkey", $status) !== false || array_search("from_user_profile_image_url", $status) !== false || array_search("lang", $status) !== false || array_search("_urls_from_user_id", $status) !== false) {
                print "Your database definitions of query bin '$binname' are very outdated. You should dump your existing data, drop the old tables, create new tables with the new table definitions, and then import the data again." . PHP_EOL;
                print "Do you want this script to create an sql file with the right table format which you can then import? (yes/no)" . PHP_EOL;
                if (trim(fgets(fopen("php://stdin", "r"))) == 'yes') {
                    file_put_contents(getcwd() . "/" . $binname . ".twittercapture.sql", create_bin_def($binname));
                    $cmd = "mysqldump --no-create-info -c -h $hostname -u $dbuser -p$dbpass $database " . $binname . "_tweets " . $binname . "_hashtags " . $binname . "_mentions " . $binname . "_urls >> " . getcwd() . "/" . $binname . ".twittercapture.sql";
                    print "Dumping data for $binname" . PHP_EOL;
                    if (exec($cmd) !== false) {
                        print "The sql file has been created at " . getcwd() . "/" . $binname . ".twittercapture.sql" . PHP_EOL;
                    } else {
                        print "The following command failed:" . PHP_EOL . "$cmd" . PHP_EOL;
                        print "Enter 'ok' after you fixed the problem.";
                        if (trim(fgets(fopen("php://stdin", "r"))) != 'ok')
                            die('Abort' . PHP_EOL);
                    }
                    print "Now, drop the existing tables by running the following in mysql: " . PHP_EOL;
                    print "\tDROP TABLE " . $binname . "_tweets; DROP TABLE " . $binname . "_urls; DROP TABLE " . $binname . "_mentions; DROP TABLE " . $binname . "_hashtags;" . PHP_EOL;
                    print "Then, import the existing tables with the right definitions by running the following from your shell:" . PHP_EOL;
                    print "\tmysql -u $dbuser -p $database < " . getcwd() . "/" . $binname . ".twittercapture.sql" . PHP_EOL;
                    print "Now, you can remove the sql file again:" . PHP_EOL;
                    print "\t rm " . getcwd() . "/" . $binname . ".twittercapture.sql" . PHP_EOL;
                    print "Enter 'ok' when you are done." . PHP_EOL;
                    if (trim(fgets(fopen("php://stdin", "r"))) != 'ok')
                        die('Abort' . PHP_EOL);
                } else {
                    print "Do not forget to update your database definitions for $binname. Enter 'ok' to continue." . PHP_EOL;
                    if (trim(fgets(fopen("php://stdin", "r"))) != 'ok')
                        die('Abort' . PHP_EOL);
                }
            } elseif (array_search("url_followed", $status) !== false) {
                print $binname . "_urls does not have the right type for url_followed. Please excute the following sql command to fix the problem: " . PHP_EOL;
                print "ALTER TABLE " . $binname . "_urls MODIFY url_followed VARCHAR(4096);" . PHP_EOL;
                print "Did yo execute the command? (yes/no)" . PHP_EOL;
                if (trim(fgets(fopen("php://stdin", "r"))) != 'yes')
                    die('Abort' . PHP_EOL);
            } elseif (array_search("from_user_id", $status) !== false) {
                print $binname . " does not have the right type for user ids. Please execute the following sql commands to fix the problem:" . PHP_EOL;
                print "ALTER TABLE " . $binname . "_tweets MODIFY from_user_id BIGINT NOT NULL;" . PHP_EOL;
                print "ALTER TABLE " . $binname . "_tweets MODIFY to_user_id BIGINT;" . PHP_EOL;
                print "ALTER TABLE " . $binname . "_hashtags MODIFY from_user_id BIGINT;" . PHP_EOL;
                print "ALTER TABLE " . $binname . "_mentions MODIFY from_user_id BIGINT;" . PHP_EOL;
                print "ALTER TABLE " . $binname . "_mentions MODIFY to_user_id BIGINT;" . PHP_EOL;
                print "ALTER TABLE " . $binname . "_urls MODIFY from_user_id BIGINT;" . PHP_EOL;
                print "Did yo execute the commands? (yes/no)" . PHP_EOL;
                if (trim(fgets(fopen("php://stdin", "r"))) != 'yes')
                    die('Abort' . PHP_EOL);
                print "As user ids were previously not stored as bigint, keep in mind that users with id 2147483647 are those users for which the actual user id could not be stored previously." . PHP_EOL;
            }
            print PHP_EOL;
        }
        return true;
    }
    return false;
}

function checkTableDefinitions($binname) {
    print "Checking table definitions for $binname" . PHP_EOL;
    $whattodo = array();
    $dbh = pdo_connect();
    $rec = $dbh->prepare("DESCRIBE " . $binname . "_tweets");
    if ($rec->execute()) {
        $res = $rec->fetchAll();
        $foundImageUrl = $foundLang = false;
        foreach ($res as $column) {
            if ($column['Field'] == "from_user_id") {
                if (strstr($column['Type'], 'bigint') === false) {
                    $whattodo[] = 'from_user_id';
                    print "\t" . $binname . " has the wrong type for from_user_id" . PHP_EOL;
                }
            }
            if ($column['Field'] == "from_user_profile_image_url")
                $foundImageUrl = true;
            if ($column['Field'] == "lang")
                $foundLang = true;
        }
        if (!$foundImageUrl) {
            $whattodo[] = 'from_user_profile_image_url';
            print "\t" . $binname . "_tweets does not have the column 'from_user_profile_image_url'" . PHP_EOL;
        }
        if (!$foundLang) {
            $whattodo[] = 'lang';
            print "\t" . $binname . "_tweets does not have the column 'lang'" . PHP_EOL;
        }
    }
    $rec = $dbh->prepare("DESCRIBE " . $binname . "_urls");
    if ($rec->execute()) {
        $res = $rec->fetchAll();
        foreach ($res as $column) {
            if ($column['Field'] == "url_followed") {
                if (strstr($column['Type'], 'varchar(4096)') === false) {
                    $whattodo[] = 'url_followed';
                    print "\t" . $binname . "_urls has the wrong type for url_followed" . PHP_EOL;
                }
            }
        }
    }
    // from_user_id on urls
    $sql = "SHOW INDEXES FROM " . $binname . "_urls";
    $rec = $dbh->prepare($sql);
    if ($rec->execute() && $rec->rowCount() > 0) {
        $res = $rec->fetchAll();
        $found = false;
        foreach ($res as $r) {
            if ($r['Column_name'] == 'from_user_id')
                $found = true;
        }
        if (!$found) {
            $whattodo[] = '_urls_from_user_id';
            print "\t" . $binname . "_urls does not have an index on from_user_id" . PHP_EOL;
        }
    }
    // to_user on mentions
    $sql = "SHOW INDEXES FROM " . $binname . "_mentions";
    $rec = $dbh->prepare($sql);
    if ($rec->execute() && $rec->rowCount() > 0) {
        $res = $rec->fetchAll();
        $found = false;
        foreach ($res as $r) {
            if ($r['Column_name'] == 'to_user')
                $found = true;
        }
        if (!$found) {
            $whattodo[] = 'touserkey';
            print "\t" . $binname . "_urls does not have an index on to_user" . PHP_EOL;
        }
    }

    return $whattodo;
}

function querybinsPhpToDb() {
    print "Migrating query bin definitions to new query manager." . PHP_EOL;
    create_admin();
    if (file_exists('../querybins.php')) {
        print "importing from querybins.php" . PHP_EOL;
        include __DIR__ . '/../querybins.php';
        if (isset($querybins))
            binsToDb($querybins, 'track');
        $querybins = false;
    }
    if (file_exists('../followbins.php')) {
        print "importing from followbins.php" . PHP_EOL;
        include __DIR__ . '/../followbins.php';
        if (isset($querybins))
            binsToDb($querybins, 'follow');
        $querybins = false;
    }
    if (file_exists('../querybins.php')) {
        print "importing query archives" . PHP_EOL;
        include __DIR__ . '/../querybins.php';
        if (isset($queryarchives))
            binsToDb($queryarchives, 'track');
    }

    // retrieve other tables
    $dbh = pdo_connect();

    $rec = $dbh->prepare("SELECT querybin FROM tcat_query_bins");
    $existingTables = array();
    if ($rec->execute() && $rec->rowCount() > 0)
        $existingTables = $rec->fetchAll(PDO::FETCH_COLUMN);

    $rec = $dbh->prepare("SHOW TABLES LIKE '%_tweets'");
    if ($rec->execute() && $rec->rowCount() > 0) {
        print "Checking to see whether other querybins need to be imported" . PHP_EOL;
        $otherbins = $onepercentbins = $userbins = array();
        while ($res = $rec->fetch()) {
            $binname = str_replace("_tweets", "", $res[0]);
            if (array_search($binname, $existingTables) === false) {
                $phrases = "";
                if (strstr($binname, "user_") !== false) {
                    $sql = "SELECT DISTINCT(from_user_id) FROM " . $binname . "_tweets";
                    $rec2 = $dbh->prepare($sql);
                    if ($rec2->execute() && $rec2->rowCount() > 0)
                        $phrases = implode(",", $rec2->fetchAll(PDO::FETCH_COLUMN));
                    $userbins[$binname] = $phrases;
                } elseif (strstr($binname, "sample_")) {
                    $onepercentbins[$binname] = $phrases;
                } else {
                    $otherbins[$binname] = $phrases;
                }
            }
        }
        if (!empty($userbins))
            binsToDb($userbins, "follow");
        if (!empty($onepercentbins))
            binsToDb($onepercentbins, "onepercent");
        if (!empty($otherbins))
            binsToDb($otherbins, "other");
    }
    print "Moved querybins successfully" . PHP_EOL . PHP_EOL;
    print "Now verify whether all looks fine in the query manager (BASE_URL/capture/index.php) and in the analysis interface (BASE_URL/analysis/index.php). If it all checks out, you can remove dmi-tcat/querybins.php and dmi-tcat/followbins.php. Enter 'ok' when done." . PHP_EOL;
    if (trim(fgets(fopen("php://stdin", "r"))) != 'ok')
        die('Abort' . PHP_EOL);
    return true;
}

function binsToDb($stuff, $type) {
    $dbh = pdo_connect();
    foreach ($stuff as $binname => $queries) {
        $binname = trim($binname);
        
        $rec2 = $dbh->prepare("SELECT id FROM tcat_query_bins WHERE querybin = '$binname'");
        if ($rec2->execute() && $rec2->rowCount() > 0) { // check whether the table has already been imported
            print "$binname already exists in the query manager, skipping it's import" . PHP_EOL;
            continue;
        }

        // select start and end of dataset
        $sql = "SELECT min(created_at) AS min, max(created_at) AS max FROM " . $binname . "_tweets";
        $rec = $dbh->prepare($sql);
        if (!$rec->execute() || !$rec->rowCount())
            die("could not find " . $binname . "_tweets" . PHP_EOL);
        $res = $rec->fetch();
        $starttime = $res['min'];
        $endtime = $res['max'];
        $active = 0;
        if (strtotime($endtime) > strtotime(strftime("%Y-%m-%d 00:00:00", date('U')))) { // see whether it is still active
            $endtime = "0000:00:00 00:00:00";
            $active = 1;
        }

        $querybin_id = queryManagerCreateBin($binname, $type, $starttime, $endtime, $active);

        $queries = explode(",", $queries);

        // insert phrases
        if ($type == 'track')
            queryManagerInsertPhrases($querybin_id, $queries, $starttime, $endtime);

        // insert users
        if ($type == 'follow')
            queryManagerInsertUsers($querybin_id, $queries, $starttime, $endtime);
    }
    $dbh = false;
}

function create_bin_def($bin_name) {
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
		) ENGINE=TokuDB COMPRESSION=TOKUDB_LZMA  DEFAULT CHARSET=utf8;" . PHP_EOL;
    $sql .= "CREATE TABLE IF NOT EXISTS " . $bin_name . "_mentions (
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
		) ENGINE=TokuDB COMPRESSION=TOKUDB_LZMA  DEFAULT CHARSET=utf8;" . PHP_EOL;
    $sql .= "CREATE TABLE IF NOT EXISTS " . $bin_name . "_tweets (
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
                ) ENGINE=TokuDB COMPRESSION=TOKUDB_LZMA DEFAULT CHARSET=utf8;" . PHP_EOL;
    $sql .= "CREATE TABLE IF NOT EXISTS " . $bin_name . "_urls (
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
		) ENGINE=TokuDB COMPRESSION=TOKUDB_LZMA  DEFAULT CHARSET=utf8;" . PHP_EOL;
    return $sql;
}

?>
