<?php

$connection = false;

db_connect($hostname, $dbuser, $dbpass, $database);
// catch parameters
if (isset($_GET['dataset']) && !empty($_GET['dataset']))
    $dataset = $_GET['dataset']; else
    $dataset = "globalwarming";
if (isset($_GET['query']) && !empty($_GET['query']))
    $query = $_GET['query']; else
    $query = "";
if (isset($_GET['exclude']) && !empty($_GET['exclude']))
    $exclude = $_GET['exclude']; else
    $exclude = "";
if (isset($_GET['from_user']) && !empty($_GET['from_user']))
    $from_user = $_GET['from_user']; else
    $from_user = "";
if (isset($_GET['samplesize']) && !empty($_GET['samplesize']))
    $samplesize = $_GET['samplesize']; else
    $samplesize = "1000";
if (isset($_GET['minf']) && !empty($_GET['minf']))
    $minf = $_GET['minf']; else
    $minf = 2;
if (isset($_GET['startdate']) && !empty($_GET['startdate']))
    $startdate = $_GET['startdate']; else
    $startdate = strftime("%Y-%m-%d", date('U') - 86400);
if (isset($_GET['enddate']) && !empty($_GET['enddate']))
    $enddate = $_GET['enddate']; else
    $enddate = strftime("%Y-%m-%d", date('U'));
$u_startdate = $u_enddate = 0;

if (isset($_GET['whattodo']) && !empty($_GET['whattodo']))
    $whattodo = $_GET['whattodo']; else
    $whattodo = "";

$keywords = array();
$esc = array();

// TODO, include switch for database
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

    // if the file does not exist yet, generate it
    if (!$cacheresults || !file_exists($filename))
        generate($what, $filename);

    // redirect to file
    $location = str_replace("index.php", "", $_SERVER['PHP_SELF']) . str_replace("#", "%23", $filename);
    if (defined('LOCATION'))
        $location = LOCATION . $location;
    header("Content-type: text/csv");
    header("Location: $location");
}

// generates the datafiles, only used if the file does not exist yet
function generate($what, $filename) {
    global $tsv, $network, $esc, $titles, $database;

    // initialize variables
    $tweets = $times = $from_users = $results = array();
    $file = "";
    $mintime = 1000000000000;
    $maxtime = 0;

    // build query
    $sql = "SELECT id,text,created_at,from_user FROM " . $esc['mysql']['dataset'] . "_tweets WHERE ";
    if (!empty($esc['mysql']['from_user'])) {
        $subusers = explode(" OR ", $esc['mysql']['from_user']);
        $sql .= "(";
        for ($i = 0; $i < count($subusers); $i++) {
            $subusers[$i] = "from_user = '" . $subusers[$i] . "'";
        }
        $sql .= implode(" OR ", $subusers);
        $sql .= ") AND ";
    }
    if (!empty($esc['mysql']['query'])) {
        $subqueries = explode(" AND ", $esc['mysql']['query']);
        foreach ($subqueries as $subquery) {
            $sql .= "text LIKE '%" . $subquery . "%' AND ";
        }
    }
    if (!empty($esc['mysql']['exclude']))
        $sql .= "text NOT LIKE '%" . $esc['mysql']['exclude'] . "%' AND ";
    $sql .= "time >= " . $esc['timestamp']['startdate'] . " AND time <= " . $esc['timestamp']['enddate'];

    // get slice and its min and max time
    $rec = mysql_query($sql);

    if ($rec && mysql_num_rows($rec) > 0) {
        while ($res = mysql_fetch_assoc($rec)) {
            $tweets[] = $res['text'];
            $ids[] = $res['id'];
            $times[] = $res['time'];
            $from_users[] = strtolower($res['from_user']);
        }
    }
    $mintime = min($times);
    $maxtime = max($times);

    // determine whether we should display intervals as days or hours
    if ($maxtime - $mintime < 86400 * 2) // if smaller than 2 days we'll do hours
        $interval = "hours";
    else
        $interval = "days";

    // urls
    if ($what == "urls" || $what == "hosts") {
        $sql2 = "SELECT u.tweetid, u.targeturl, u.targethost FROM urls u, " . $esc['mysql']['dataset'] . "_tweets t WHERE u.tweetid = t.id AND u.tablename = '" . $esc['mysql']['dataset'] . "_tweets' AND ";
        if (!empty($esc['mysql']['from_user'])) {
            $subusers = explode(" OR ", $esc['mysql']['from_user']);
            $sql2 .= "(";
            for ($i = 0; $i < count($subusers); $i++) {
                $subusers[$i] = "t.from_user = '" . $subusers[$i] . "'";
            }
            $sql2 .= implode(" OR ", $subusers);
            $sql2 .= ") AND ";
        }
        if (!empty($esc['mysql']['query'])) {
            $subqueries = explode(" AND ", $esc['mysql']['query']);
            foreach ($subqueries as $subquery) {
                $sql2 .= "t.text LIKE '%" . $subquery . "%' AND ";
            }
        }
        if (!empty($esc['mysql']['exclude']))
            $sql2 .= "t.text NOT LIKE '%" . $esc['mysql']['exclude'] . "%' AND ";
        $sql2 .= "t.time >= " . $esc['timestamp']['startdate'] . " AND t.time <= " . $esc['timestamp']['enddate'];

        $rec = mysql_query($sql2);
        while ($res = mysql_fetch_assoc($rec)) {
            $urls[$res['tweetid']] = $res['targeturl'];
            $hosts[$res['tweetid']] = $res['targethost'];
        }
    }

    // extract desired things ($what) and group per interval
    foreach ($tweets as $key => $tweet) {
        $time = $times[$key];
//		$tweet = mb_convert_encoding($tweet, 'ISO-8859-1','latin1');
//var_dump(iconv_get_encoding('all'));
//die;

        if ($interval == "days")
            $group = time_to_day($time);
        elseif ($interval == "hours")
            $group = time_to_hour($time);
        elseif ($interval == "months")
            $group = time_to_month($time);

        switch ($what) {
            case "hashtag":
                $stuff = get_hash_tags($tweet);
                foreach ($stuff as $thing)
                    $results[$group][] = $thing;
                break;
            case "mention":
                $stuff = get_replies($tweet);
                foreach ($stuff as $thing)
                    $results[$group][] = $thing;
                break;
            case "user":
                $results[$group][] = $from_users[$key];
                break;

            case "user-mention":
                $stuff = get_replies($tweet);
                foreach ($stuff as $thing) {
                    $results[$group]['mentions'][] = $thing;
                }
                $results[$group]['users'][] = $from_users[$key];
                break;

            case "retweet":
                $results[$group][] = $tweet; // TODO, write stemming function
                break;
            case "urls":
                if (isset($urls[$ids[$key]]))
                    $results[$group][] = $urls[$ids[$key]];
                break;
            case "hosts":
                if (isset($urls[$ids[$key]]))
                    $results[$group][] = $hosts[$ids[$key]];
                break;
            default:
                break;
        }
    }


    if ($what == "user-mention") {
        foreach ($results as $group => $things) {
            $tmp_mentions = array_count_values($things['mentions']);
            $tmp_users = array_count_values($things['users']);
            $counted_things = array();
            // add all from_users 
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
        // count frequency of occurence of thing, per interval
        foreach ($results as $group => $things) {
            $counted_things = array_count_values($things);
            arsort($counted_things);
            $results[$group] = $counted_things;
        }

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
            foreach ($things as $thing => $count) {
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
    file_put_contents($filename, "\xEF\xBB\xBF" . $file);
}

// formats time as days
function time_to_day($time) {
    return strftime("%Y-%m-%d", $time);
}

// formats time as hours
function time_to_hour($time) {
    return strftime("%Y-%m-%d %H" . "h", $time);
}

// formats time as months
function time_to_month($time) {
    return strftime("%Y-%m-%d", $time);
}

// constructs the filename and validates the variables
function get_filename($what) {
    global $resultsdir, $esc;
    get_dataset_name();
    $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
    return $resultsdir . str_replace(" ", "_", $esc['shell']['datasetname']) . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user"] . "_" . $what . "_min" . $esc['shell']['minf'] . ".csv";
}

function get_dataset_name() {
    global $esc, $database, $databases;

    mysql_select_db($databases[$database]);

    $id = preg_replace("/[^\d]/", "", $esc['shell']["dataset"]);

    $sql = "SELECT keyword FROM archives WHERE id = $id";
    $rec = mysql_query($sql);
    $res = mysql_fetch_row($rec);

    $esc['shell']['datasetname'] = $res[0];
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
            $what = mysql_real_escape_string($what);
            break;
        case "tweet":
            $what = stripslashes(html_entity_decode(preg_replace("/[\n\t\r\s,]+/msi", " ", $what)));
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
    global $esc, $query, $dataset, $exclude, $from_user, $startdate, $enddate, $databases, $connection, $keywords, $database, $minf;

    // validate and escape all user input
    $esc['mysql']['dataset'] = validate($dataset, "mysql");
    $esc['mysql']['query'] = validate($query, "mysql");
    $esc['mysql']['exclude'] = validate($exclude, "mysql");
    $esc['mysql']['from_user'] = validate($from_user, "mysql");

    $esc['shell']['dataset'] = validate($dataset, "mysql");
    $esc['shell']['query'] = validate($query, "mysql");
    $esc['shell']['exclude'] = validate($exclude, "mysql");
    $esc['shell']['from_user'] = validate($from_user, "mysql");

    $esc['shell']['minf'] = validate($minf, 'frequency');

    $esc['date']['startdate'] = validate($startdate, "startdate");
    $esc['date']['enddate'] = validate($enddate, "enddate");
    $esc['timestamp']['startdate'] = $esc['date']['startdate'] . " 00:00:00";
    $esc['timestamp']['enddate'] = $esc['date']['enddate'] . " 23:59:59";
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

// get listing of all databases
function get_all_datasets() {

    global $querybins; // defined in php.ini of twitter capture

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
    return $datasets;
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
}

?>
