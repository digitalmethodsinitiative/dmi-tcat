<?php

$connection = false;

db_connect($hostname, $dbuser, $dbpass, $database);
// catch parameters
if (isset($_GET['dataset']) && !empty($_GET['dataset']))
    $dataset = $_GET['dataset'];
else
    $dataset = "tibet";
if (isset($_GET['query']) && !empty($_GET['query']))
    $query = $_GET['query'];
else
    $query = "";
if (isset($_GET['exclude']) && !empty($_GET['exclude']))
    $exclude = $_GET['exclude'];
else
    $exclude = "";
if (isset($_GET['from_user_name']) && !empty($_GET['from_user_name']))
    $from_user_name = $_GET['from_user_name'];
else
    $from_user_name = "";
if (isset($_GET['samplesize']) && !empty($_GET['samplesize']))
    $samplesize = $_GET['samplesize'];
else
    $samplesize = "1000";
if (isset($_GET['minf']) && preg_match("/^\d+$/",$_GET['minf'])!==false)
    $minf = $_GET['minf'];
else
    $minf = 2;
if (isset($_GET['startdate']) && !empty($_GET['startdate']))
    $startdate = $_GET['startdate'];
else
    $startdate = strftime("%Y-%m-%d", date('U') - (86400 * 2));
if (isset($_GET['enddate']) && !empty($_GET['enddate']))
    $enddate = $_GET['enddate'];
else
    $enddate = strftime("%Y-%m-%d", date('U') - 86400);
$u_startdate = $u_enddate = 0;

if (isset($_GET['whattodo']) && !empty($_GET['whattodo']))
    $whattodo = $_GET['whattodo'];
else
    $whattodo = "";

if (isset($_GET['keywordToTrack']) && !empty($_GET['keywordToTrack']))
    $keywordToTrack = trim(strtolower($_GET['keywordToTrack']));
else
    $keywordToTrack = "";

if (isset($_GET['minimumCowordFrequencyOverall']))
    $minimumCowordFrequencyOverall = $_GET['minimumCowordFrequencyOverall'];
else
    $minimumCowordFrequencyOverall = 10;

if (isset($_GET['minimumCowordFrequencyOverall']))
    $minimumCowordFrequencyInterval = $_GET['minimumCowordFrequencyInterval'];
else
    $minimumCowordFrequencyInterval = 0;

if (isset($_GET['showvis']) && !empty($_GET['showvis']))
    $showvis = $_GET['showvis'];
else
    $showvis = "";

$interval = "daily";
if (isset($_REQUEST['interval'])) {
    if (in_array($_REQUEST['interval'], array('hourly', 'daily', 'weekly', 'monthly', 'yearly', 'overall', 'custom')))
        $interval = $_REQUEST['interval'];
}
// check custom interval
$intervalDates = array();
if ($interval == "custom" && isset($_REQUEST['customInterval'])) {
    $intervalDates = explode(';', $_REQUEST['customInterval']);
    $firstDate = $lastDate = false;
    foreach ($intervalDates as $k => $date) {
        $date = trim($date);
        if (empty($date))
            continue;
        $intervalDates[$k] = $date;
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $intervalDates[$k]))
            die("<font size='+1' color='red'>custom interval not in right format</font>: YYYY-MM-DD;YYYY-MM-DD;...;YYYY-MM-DD");
        if (!$firstDate)
            $firstDate = $date;
        $lastDate = $date;
    }

    if ($firstDate != $startdate)
        die("<font size='+1' color='red'>custom interval should have the same start date as the selection</font>");
    if ($lastDate > $enddate)
        die("<font size='+1' color='red'>custom interval should have the same end date as the selection</font>");
}


$keywords = array();
$esc = array();

// define punctuation symbols
$punctuation = array("\s", "\.", ",", "!", "\?", ":", ";", "\/", "\\", "#", "@", "&", "\^", "\$", "\|", "`", "~", "=", "\+", "\*", "\"", "'", "\(", "\)", "\]", "\[", "\{", "\}", "<", ">", "�");

// define the type of output
$tsv = array("hashtag", "mention", "retweet", "urls", "hosts", "user", "user-mention"); // these analyses will output tsv files
$network = array("coword", "interaction");   // these analyses will output network files

$titles = array(
    "hashtag" => "Hashtag frequency",
    "retweet" => "Retweet frequency",
    "user" => "User frequency",
    "mention" => "Mention (@username) frequency",
    "urls" => "URL frequency",
    "hosts" => "host frequency"
);


if (!empty($whattodo)) {
    if (in_array($whattodo, $tsv) !== false)
        get_file($whattodo);
}

// return the desired file
function get_file($what) {
    validate_all_variables();

    // get filename (this also validates the data)
    global $database;
    $filename = get_filename($what);

    generate($what, $filename);

    // redirect to file
    $location = str_replace("index.php", "", ANALYSIS_URL) . str_replace("#", "%23", $filename);
    if (defined('LOCATION'))
        $location = LOCATION . $location;
    header("Content-type: text/csv");
    header("Location: $location");
}

/*
 * @var $toget specifies fieldname
 * @var $table specifies table name
 */

function frequencyTable($table, $toget) {
    global $esc, $intervalDates;
    $results = array();
    $sql = "SELECT COUNT($table.$toget) AS count, $table.$toget AS toget, ";
    $sql .= sqlInterval();
    $sql .= "FROM " . $esc['mysql']['dataset'] . "_$table $table, " . $esc['mysql']['dataset'] . "_tweets t ";
    $sql .= "WHERE t.id = $table.tweet_id AND ";
    $sql .= sqlSubset();
    $sql .= " GROUP BY toget, datepart ORDER BY datepart ASC, count DESC";
    $rec = mysql_query($sql);
    $date = false;
    while ($res = mysql_fetch_assoc($rec)) {
        if ($res['count'] > $esc['shell']['minf']) {
            if (!empty($intervalDates))
                $date = groupByInterval($res['datepart']);
            else
                $date = $res['datepart'];
            $results[$date][$res['toget']] = $res['count'];
        }
    }
    return $results;
}

// here further sqlSubset selection is constructed
function sqlSubset($table = "t", $period = FALSE) {
    error_reporting(E_ALL);
    global $esc;
    $sql = "";
    if (!empty($esc['mysql']['from_user_name'])) {
        if (strstr($esc['mysql']['from_user_name'], "AND") !== false) {
            $subqueries = explode(" AND ", $esc['mysql']['from_user_name']);
            foreach ($subqueries as $subquery) {
                $sql .= "LOWER($table.from_user_name) LIKE '%" . $subquery . "%' AND ";
            }
        } elseif (strstr($esc['mysql']['from_user_name'], "OR") !== false) {
            $subqueries = explode(" OR ", $esc['mysql']['from_user_name']);
            $sql .= "(";
            foreach ($subqueries as $subquery) {
                $sql .= "LOWER($table.from_user_name) LIKE '%" . $subquery . "%' OR ";
            }
            $sql = substr($sql, 0, -3) . ") AND ";
        } else {
            $sql .= "LOWER($table.from_user_name) LIKE '%" . $esc['mysql']['from_user_name'] . "%' AND ";
        }
    }
    if (!empty($esc['mysql']['query'])) {
        if (strstr($esc['mysql']['query'], "AND") !== false) {
            $subqueries = explode(" AND ", $esc['mysql']['query']);
            foreach ($subqueries as $subquery) {
                $sql .= "LOWER($table.text) LIKE '%" . $subquery . "%' AND ";
            }
        } elseif (strstr($esc['mysql']['query'], "OR") !== false) {
            $subqueries = explode(" OR ", $esc['mysql']['query']);
            $sql .= "(";
            foreach ($subqueries as $subquery) {
                $sql .= "LOWER($table.text) LIKE '%" . $subquery . "%' OR ";
            }
            $sql = substr($sql, 0, -3) . ") AND ";
        } else {
            $sql .= "LOWER($table.text) LIKE '%" . $esc['mysql']['query'] . "%' AND ";
        }
    }
    if (!empty($esc['mysql']['exclude'])) {
        if (strstr($esc['mysql']['exclude'], "AND") !== false) {
            $subqueries = explode(" AND ", $esc['mysql']['exclude']);
            foreach ($subqueries as $subquery) {
                $sql .= "LOWER($table.text) NOT LIKE '%" . $subquery . "%' AND ";
            }
        } elseif (strstr($esc['mysql']['exclude'], "OR") !== false) {
            $subqueries = explode(" OR ", $esc['mysql']['exclude']);
            $sql .= "(";
            foreach ($subqueries as $subquery) {
                $sql .= "LOWER($table.text) NOT LIKE '%" . $subquery . "%' OR ";
            }
            $sql = substr($sql, 0, -3) . ") AND ";
        } else {
            $sql .= "LOWER($table.text) NOT LIKE '%" . $esc['mysql']['exclude'] . "%' AND ";
        }
    }
    if ($period === FALSE)
        $sql .= "$table.created_at >= '" . $esc['datetime']['startdate'] . "' AND $table.created_at <= '" . $esc['datetime']['enddate'] . "' ";
    else
        $sql .= $period;
    return $sql;
}

// define intervals for the data selection
function sqlInterval() {
    global $interval;
    switch ($interval) {
        case "hourly":
            return "DATE_FORMAT(t.created_at,'%Y-%m-%d %Hh') datepart ";
            break;
        case "weekly":
            return "DATE_FORMAT(t.created_at,'%Y %u') datepart ";
            break;
        case "monthly":
            return "DATE_FORMAT(t.created_at,'%Y-%m') datepart ";
            break;
        case "yearly":
            return "DATE_FORMAT(t.created_at,'%Y') datepart ";
            break;
        case "overall":
            return "DATE_FORMAT(t.created_at,'overall') datepart ";
            break;
        default:
            return "DATE_FORMAT(t.created_at,'%Y-%m-%d') datepart "; // default daily (also used for custom)
    }
}

// used for custom and 'overall' intervals
function groupByInterval($date) {
    global $intervalDates;
    $returnDate = false;
    foreach ($intervalDates as $intervalDate) {
        if ($date >= $intervalDate) {
            $returnDate = $intervalDate;
            $key = array_search($returnDate, $intervalDates);
            if ($key == count($intervalDates) - 1) // check whether it is last date
                $returnDate = $intervalDates[count($intervalDates) - 2] . " until " . $returnDate;
            else
                $returnDate = $returnDate . " until " . $intervalDates[$key + 1];
        }
    }
    return $returnDate;
}

// generates the datafiles, only used if the file does not exist yet
function generate($what, $filename) {
    global $tsv, $network, $esc, $titles, $database, $interval;

    // initialize variables
    $tweets = $times = $from_user_names = $results = $urls = $urls_expanded = $hosts = $hashtags = array();
    $file = "";

    // determine interval
    $sql = "SELECT MIN(created_at) AS min, MAX(created_at) AS max FROM " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
    $sql .= sqlSubset();
    //print $sql . "<bR>";
    $rec = mysql_query($sql);
    $res = mysql_fetch_assoc($rec);

    // get frequencies
    if ($what == "hashtag") {
        $results = frequencyTable("hashtags", "text");
    } elseif ($what == "urls") {
        $results = frequencyTable("urls", "url_followed");
    } elseif ($what == "hosts") {
        $results = frequencyTable("urls", "domain");
    } elseif ($what == "mention") {
        $results = frequencyTable("mentions", "to_user");
        // get other things        
    } else {
        // @todo, this could also use database grouping
        $sql = "SELECT id,text,created_at,from_user_name FROM " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
        $sql .= sqlSubset();

        // get slice and its min and max time
        $rec = mysql_query($sql);

        if ($rec && mysql_num_rows($rec) > 0) {
            while ($res = mysql_fetch_assoc($rec)) {
                $tweets[] = $res['text'];
                $ids[] = $res['id'];
                $times[] = $res['created_at'];
                $from_user_names[] = strtolower($res['from_user_name']);
            }
        }


        // extract desired things ($what) and group per interval
        foreach ($tweets as $key => $tweet) {
            $time = $times[$key];

            switch ($interval) {
                case "hourly":
                    $group = strftime("%Y-%m-%d %Hh", strtotime($time));
                    break;
                case "weekly":
                    $group = strftime("%Y-%m-%d %u", strtotime($time));
                    break;
                case "monthly":
                    $group = strftime("%Y-%m", strtotime($time));
                    break;
                case "yearly":
                    $group = strftime("%Y-%m", strtotime($time));
                    break;
                case "overall":
                    $group = "overall";
                    break;
                case "custom":
                    $group = groupByInterval(strftime("%Y-%m-%d", strtotime($time)));
                    break;
                default:
                    $group = strftime("%Y-%m-%d", strtotime($time)); // default daily
            }

            switch ($what) {
                //case "hashtag":
                //    foreach ($hashtags as $hashtag)
                //        $results[$group][] = $hashtag;
                //    break;
                //case "mention": // @todo, mentions might be taken from own table
                //    $stuff = get_replies($tweet);
                //    foreach ($stuff as $thing)
                //        $results[$group][] = $thing;
                //    break;
                case "user":
                    $results[$group][] = $from_user_names[$key];
                    break;

                case "user-mention":
                    $stuff = get_replies($tweet);
                    foreach ($stuff as $thing) {
                        $results[$group]['mentions'][] = $thing;
                    }
                    $results[$group]['users'][] = $from_user_names[$key];
//var_dump($results);
                    break;

                case "retweet":
                    $results[$group][] = $tweet; // TODO, write stemming function
                    break;
                //case "urls":
                //    if (isset($urls_expanded[$ids[$key]]))
                //        $results[$group][] = $urls_expanded[$ids[$key]];
                //    break;
                //case "hosts":
                //    if (isset($urls_expanded[$ids[$key]]))
                //        $results[$group][] = $hosts[$ids[$key]];
                //    break;
                default:
                    break;
            }
        }
        // count frequency of occurence of thing, per interval
        foreach ($results as $group => $things) {
            $counted_things = array_count_values($things);
            arsort($counted_things);
            $results[$group] = $counted_things;
        }
    }


    // network output for users
    if ($what == "user-mention") {
        foreach ($results as $group => $things) {
            $tmp_mentions = array_count_values($things['mentions']);
            $tmp_users = array_count_values($things['users']);
            $counted_things = array();
            // add all from_user_names 
            foreach ($tmp_users as $user => $count) {
                if (isset($tmp_mentions["@" . $user]))
                    $counted_things[$user] = $tmp_mentions["@" . $user] . "," . $count;
                else
                    $counted_things[$user] = "0," . $count;
            }
            // add all users which were replied but not in the set
            foreach ($tmp_mentions as $user => $count) {
                $user = str_replace("@", "", $user);
                if (!isset($counted_things[$user]))
                    $counted_things[$user] = $count . ",0";
            }
            ksort($counted_things);
            $results[$group] = $counted_things;
        }

        if (isset($titles[$what])) {
            if (!empty($esc['shell']['query'])) {
                $q = " with search " . $esc['shell']['query'];
            } else
                $q = "";
            $file = $titles[$what] . $q . " from " . $esc['date']["startdate"] . " to " . $esc['date']["enddate"] . "\n";
        } else
            $file = "";

        $file = "date,user,mentions,tweets\n";
        foreach ($results as $group => $things) {
            foreach ($things as $thing => $count) {
                $file .= "$group,$thing,$count\n";
            }
        }
        // write tsv output
    } elseif (in_array($what, $tsv) !== false) {



        ksort($results);

        // construct file
        if (isset($titles[$what])) {
            if (!empty($esc['shell']['query'])) {
                $q = " with search " . $esc['shell']['query'];
            } else
                $q = "";
            $file = $titles[$what] . " for " . $esc['shell']['datasetname'] . $q . " from " . $esc['date']["startdate"] . " to " . $esc['date']["enddate"] . "\n";
        } else
            $file = "";

        if ($what == "urls")
            $file .= "date,frequency,tweetedurl\n";
        elseif ($what == "hosts")
            $file .= "date,frequency,domain name\n";
        else
            $file .= "date,frequency,$what\n";
        foreach ($results as $group => $things) {
            arsort($things);
            foreach ($things as $thing => $count) {
                if (empty($thing))
                    continue;
                if ($count < $esc['shell']['minf'])
                    continue;
                if ($what == "retweet")
                    $thing = validate($thing, "tweet");
                $file .= "$group,$count,$thing\n";
            }
        }
    } else
        die('no valid output format found');

    if (!empty($file))
        ;
    #file_put_contents($filename, "\xEF\xBB\xBF" . $file);   // write BOM
    file_put_contents($filename, chr(239) . chr(187) . chr(191) . $file);   // write BOM
}

// constructs the filename and validates the variables
function get_filename($what) {
    global $resultsdir, $esc, $interval, $intervalDates;
    $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
    return $resultsdir . str_replace(" ", "_", $esc['shell']['datasetname']) . "_" . str_replace(" ", "-", $esc['shell']["query"]) . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . "_" . $what . "_min" . $esc['shell']['minf'] . "_groupedBy" . ucwords($interval) . ($interval == "custom" ? implode("_", $intervalDates) : "") . ".csv";
}

// does some cleanup of data types
function validate(&$what, $how) {
    $what = trim($what);
    switch ($how) {
        case "database":
            global $databases;
            $dbk = array_keys($databases);
            if (!in_array($what, $dbk)) {
                die('go away you evil hacker!');
            }
            break;
        // check whether it is a valid twapper keeper table name
        case "table":
            $what = preg_replace("/[^\d\w_]/", "", $what);
            break;
        // if date is not in yyyymmdd format, set startdate to 0
        case "startdate":
            if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $what))
                $what = "2011-11-14";
            break;
        // if date is not in yyyymmdd format, set enddate to end of current day
        case "enddate":
            $now = date('U');
            if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $what)) // TODO, should never be more than 'now'
                $what = "2011-11-15";
            break;
        // escape shell cmd chars
        case "shell":
            $what = escapeshellcmd($what);
            break;
        // escape non-mysql chars
        case "mysql":
            if(substr($what,0,1)=="[" && substr($what,-1)=="]") // allow for queries with spaces
                    $what = substr($what,1,-1);
            $what = mysql_real_escape_string($what);
            break;
        case "tweet":
            $what = str_replace('"', '""', stripslashes(html_entity_decode(preg_replace("/[\n\t\r\s,]+/msi", " ", $what)))); // @todo, escape of double quotes
            break;
        case "frequency":
            $what = preg_replace("/[^\d]/", "", $what);
            break;
        default:
            break;
    }
    return $what;
}

// make sure that we have all the right types and values
// also make sure one cannot do a mysql injection attack
function validate_all_variables() {
    global $esc, $query, $dataset, $exclude, $from_user_name, $startdate, $enddate, $databases, $connection, $keywords, $database, $minf;

    // validate and escape all user input
    $esc['mysql']['dataset'] = validate($dataset, "mysql");
    $esc['mysql']['query'] = validate($query, "mysql");
    $esc['mysql']['exclude'] = validate($exclude, "mysql");
    $esc['mysql']['from_user_name'] = validate($from_user_name, "mysql");

    $esc['shell']['dataset'] = validate($dataset, "mysql");
    $esc['shell']['query'] = validate($query, "mysql");
    $esc['shell']['exclude'] = validate($exclude, "mysql");
    $esc['shell']['from_user_name'] = validate($from_user_name, "mysql");

    $esc['shell']['datasetname'] = validate($dataset, "shell");

    $esc['shell']['minf'] = validate($minf, 'frequency');

    $esc['date']['startdate'] = validate($startdate, "startdate");
    $esc['date']['enddate'] = validate($enddate, "enddate");
    $esc['datetime']['startdate'] = $esc['date']['startdate'] . " 00:00:00";
    $esc['datetime']['enddate'] = $esc['date']['enddate'] . " 23:59:59";
}

// get all @replies in a message
// TODO, should an at-reply always start at the beginning?
function get_replies($msg) {
    global $punctuation;
    $mentions = array();
    // the first letter of an @reply is an @, the rest has no punctuation
    $msg = strtolower($msg);
    if (preg_match_all("/(@[^" . implode("|", $punctuation) . "]+)/", $msg, $matches)) {
        foreach ($matches[1] as $mention)
            $mentions[] = strtolower($mention);
    }
    //$mentions = array_unique($mentions); 	// todo: remove doubles per tweet
    return $mentions;
}

// get all hashtags in a message
function get_hash_tags($msg) {
    global $punctuation;
    $tags = array();
    // the first letter of a hashtag is a #, the rest has no punctuation
    $msg = strtolower($msg);
    if (preg_match_all("/(#[^" . implode("", $punctuation) . "]+)/", $msg, $matches)) {
        foreach ($matches[1] as $tag)
            $tags[] = strtolower($tag);
    }
    //$tags = array_unique($tags);	// todo: remove doubles per tweet
    return $tags;
}

// get listing of all datasets
// @todo add groups in select form
function get_all_datasets() {

    global $querybins, $queryarchives; // defined in php.ini of twitter capture
// include ytk imported tables as they are not in php.ini
    $tables = array();
    $select = "SHOW TABLES";
    $rec = mysql_query($select);
    while ($res = mysql_fetch_row($rec)) {
        if (preg_match("/^(ytk_.*?)_tweets$/", $res[0], $match)) {
            $querybins[$match[1]] = $match[1];
        } elseif (preg_match("/(user_.*?)_tweets$/", $res[0], $match)) {
            $querybins[$match[1]] = $match[1];
        } elseif (preg_match("/(sample_.*?)_tweets$/", $res[0], $match)) {
            $querybins[$match[1]] = $match[1];
        }
    }

    $datasets = array();

    foreach ($querybins as $bin => $keywords) {

        // get nr of results per table
        $sql2 = "SELECT count(id) AS notweets,MIN(created_at) AS min,MAX(created_at) AS max  FROM " . $bin . "_tweets";
        $rec2 = mysql_query($sql2);
        if ($rec2 && mysql_num_rows($rec2) > 0) {
            $res2 = mysql_fetch_assoc($rec2);
            $row['bin'] = $bin;
            $row['notweets'] = $res2['notweets'];
            $row['mintime'] = $res2['min'];
            $row['maxtime'] = $res2['max'];
            $row['keywords'] = $keywords;
            // return datasets
            $datasets[$bin] = $row;
        }
    }
    foreach ($queryarchives as $bin => $keywords) {
        // get nr of results per table
        $sql2 = "SELECT count(id) AS notweets,MIN(created_at) AS min,MAX(created_at) AS max  FROM " . $bin . "_tweets";
        $rec2 = mysql_query($sql2);
        if ($rec2 && mysql_num_rows($rec2) > 0) {
            $res2 = mysql_fetch_assoc($rec2);
            $row['bin'] = $bin;
            $row['notweets'] = $res2['notweets'];
            $row['mintime'] = $res2['min'];
            $row['maxtime'] = $res2['max'];
            $row['keywords'] = $keywords;
            // return datasets
            $datasets[$bin] = $row;
        }
    }
    asort($datasets);
    return $datasets;
}

function get_total_nr_of_tweets() {
    $select = "SHOW TABLES";
    $rec = mysql_query($select);
    $count = 0;
    while ($res = mysql_fetch_row($rec)) {
        if (preg_match("/_tweets$/", $res[0], $match)) {
            $sql = "SELECT COUNT(id) FROM " . $res[0];
            $rec2 = mysql_query($sql);
            $res2 = mysql_fetch_row($rec2);
            $count += $res2[0];
        }
    }
    return $count;
}

function xml_escape($stuff) {
    return str_replace("&", "&amp;", str_replace("'", "&quot;", str_replace('"', "'", strip_tags($stuff))));
}

// connect to the database
function db_connect($db_host, $db_user, $db_pass, $db_name) {
    global $connection;
    $connection = mysql_connect($db_host, $db_user, $db_pass);
    if (!mysql_select_db($db_name, $connection))
        die("could not connect");
    if (!mysql_set_charset('utf8', $connection)) {
        echo "Error: Unable to set the character set.\n";
        exit;
    }
}

// number median ( number arg1, number arg2 [, number ...] )
// number median ( array numbers )
// taken from http://php.net/manual/en/ref.math.php
function median() {
    $args = func_get_args();

    switch (func_num_args()) {
        case 0:
            trigger_error('median() requires at least one parameter', E_USER_WARNING);
            return false;
            break;

        case 1:
            $args = array_pop($args);
        // fallthrough

        default:
            if (!is_array($args)) {
                trigger_error('median() requires a list of numbers to operate on or an array of numbers', E_USER_NOTICE);
                return false;
            }

            sort($args);

            $n = count($args);
            $h = intval($n / 2);

            if ($n % 2 == 0) {
                $median = ($args[$h] + $args[$h - 1]) / 2;
            } else {
                $median = $args[$h];
            }

            break;
    }

    return $median;
}

function average($arr) {
    if (!count($arr))
        return 0;

    return array_sum($arr) / count($arr);
}

// @todo, double check whether this is alright
function variance($array) {
    $array_count = count($array);
    foreach ($array as $k => $v)
        $array_square[$k] = pow($array[$k], 2);
    $variance = array_sum($array_square) / $array_count;
    return $variance;
}

function stdev($array) {
    $stdev = sqrt(variance($array));
    return $stdev;
}

?>
