<?php

$connection = false;

db_connect($hostname, $dbuser, $dbpass, $database);

// catch parameters
if (isset($_GET['dataset']) && !empty($_GET['dataset']))
    $dataset = urldecode($_GET['dataset']);
else {
    $sql = "SELECT querybin FROM tcat_query_bins ORDER BY id LIMIT 1";
    $rec = mysql_query($sql);
    if ($res = mysql_fetch_assoc($rec))
        $dataset = $res['querybin'];
}
$datasets = get_all_datasets();
if (isset($_GET['query']) && !empty($_GET['query']))
    $query = urldecode($_GET['query']);
else
    $query = "";
if (isset($_GET['url_query']) && !empty($_GET['url_query']))
    $url_query = urldecode($_GET['url_query']);
else
    $url_query = "";
if (isset($_GET['geo_query']) && !empty($_GET['geo_query'])) {
    $geo_query = urldecode($_GET['geo_query']);
    if (preg_match("/[^\-\,\.0-9 ]/", $geo_query)) {
        die("<font size='+1' color='red'>The GEO polygon should contain only longitude latitude pairs (with dots inside for precision), seperated by a single whitespace, and after the pair a comma to mark the next point in the polygon.</font><br />Make the polygon end at the point where you started drawing it. Please see the provided example for the proper value of a WKT polygon.");
    }
} else {
    $geo_query = "";
}
if (isset($_GET['exclude']) && !empty($_GET['exclude']))
    $exclude = urldecode($_GET['exclude']);
else
    $exclude = "";
if (isset($_GET['from_source']) && !empty($_GET['from_source']))
    $from_source = urldecode($_GET['from_source']);
else
    $from_source = "";
if (isset($_GET['from_user_name']) && !empty($_GET['from_user_name']))
    $from_user_name = urldecode($_GET['from_user_name']);
else
    $from_user_name = "";
if (isset($_GET['samplesize']) && !empty($_GET['samplesize']))
    $samplesize = $_GET['samplesize'];
else
    $samplesize = "1000";
if (isset($_GET['minf']) && preg_match("/^\d+$/", $_GET['minf']) !== false)
    $minf = $_GET['minf'];
else
    $minf = 2;
if (isset($_GET['topu']) && preg_match("/^\d+$/", $_GET['topu']) !== false)
    $topu = $_GET['topu'];
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
    $keywordToTrack = trim(strtolower(urldecode($_GET['keywordToTrack'])));
else
    $keywordToTrack = "";

if (isset($_GET['from_user_lang']) && !empty($_GET['from_user_lang']))
    $from_user_lang = trim(strtolower($_GET['from_user_lang']));
else
    $from_user_lang = "";

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
$graph_resolution = "day";
if (isset($_GET['graph_resolution']) && !empty($_GET['graph_resolution'])) {
    if (array_search($_GET['graph_resolution'], array("minute", "hour")) !== false)
        $graph_resolution = $_GET['graph_resolution'];
}
$interval = "daily";
if (isset($_REQUEST['interval'])) {
    if (in_array($_REQUEST['interval'], array('hourly', 'daily', 'weekly', 'monthly', 'yearly', 'overall', 'custom')))
        $interval = $_REQUEST['interval'];
}
$outputformat = "csv";
if (isset($_REQUEST['outputformat'])) {
    if (in_array($_REQUEST['outputformat'], array('csv', 'tsv', 'gexf', 'gdf')))
        $outputformat = $_REQUEST['outputformat'];
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
    $filename = get_filename_for_export($what);

    generate($what, $filename);

    // redirect to file
    $location = str_replace("index.php", "", ANALYSIS_URL) . filename_to_url($filename);
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
    $where = "t.id = $table.tweet_id AND ";
    $sql .= sqlSubset($where);
    $sql .= " GROUP BY toget, datepart ORDER BY datepart ASC, count DESC";
    $rec = mysql_query($sql);
    $date = false;
    while ($res = mysql_fetch_assoc($rec)) {
        if ($res['count'] > $esc['shell']['minf']) {
            if (!empty($intervalDates))
                $date = groupByInterval($res['datepart']);
            else
                $date = $res['datepart'];
            if ($table == 'urls')
                $res['toget'] = validate($res['toget'], 'url');
            $results[$date][$res['toget']] = $res['count'];
        }
    }
    return $results;
}

// here further sqlSubset selection is constructed
function sqlSubset($where = NULL) {
    error_reporting(E_ALL);
    global $esc;
    $collation = current_collation();
    $sql = "";
    if (!empty($esc['mysql']['url_query']) && strstr($where, "u.") == false)
        $sql .= ", " . $esc['mysql']['dataset'] . "_urls u";
    $sql .= " WHERE ";
    if (!empty($where))
        $sql .= $where;
    if (!empty($esc['mysql']['from_user_name'])) {
        if (strstr($esc['mysql']['from_user_name'], "AND") !== false) {
            $subqueries = explode(" AND ", $esc['mysql']['from_user_name']);
            foreach ($subqueries as $subquery) {
                $sql .= "LOWER(t.from_user_name COLLATE $collation) = LOWER('" . $subquery . "' COLLATE $collation) AND ";
            }
        } elseif (strstr($esc['mysql']['from_user_name'], "OR") !== false) {
            $subqueries = explode(" OR ", $esc['mysql']['from_user_name']);
            $sql .= "(";
            foreach ($subqueries as $subquery) {
                $sql .= "LOWER(t.from_user_name COLLATE $collation) = LOWER('" . $subquery . "' COLLATE $collation) OR ";
            }
            $sql = substr($sql, 0, -3) . ") AND ";
        } else {
            $sql .= "LOWER(t.from_user_name COLLATE $collation) = LOWER('" . $esc['mysql']['from_user_name'] . "' COLLATE $collation) AND ";
        }
    }
    if (!empty($esc['mysql']['query'])) {
        if (strstr($esc['mysql']['query'], "AND") !== false) {
            $subqueries = explode(" AND ", $esc['mysql']['query']);
            foreach ($subqueries as $subquery) {
                $sql .= "LOWER(t.text COLLATE $collation) LIKE LOWER('%" . $subquery . "%' COLLATE $collation) AND ";
            }
        } elseif (strstr($esc['mysql']['query'], "OR") !== false) {
            $subqueries = explode(" OR ", $esc['mysql']['query']);
            $sql .= "(";
            foreach ($subqueries as $subquery) {
                $sql .= "LOWER(t.text COLLATE $collation) LIKE LOWER('%" . $subquery . "%' COLLATE $collation) OR ";
            }
            $sql = substr($sql, 0, -3) . ") AND ";
        } else {
            $sql .= "LOWER(t.text COLLATE $collation) LIKE LOWER('%" . $esc['mysql']['query'] . "%' COLLATE $collation) AND ";
        }
    }
    if (!empty($esc['mysql']['url_query'])) {
        if (strstr($where, "u.") == false)
            $sql .= " u.tweet_id = t.id AND ";
        if (strstr($esc['mysql']['url_query'], "AND") !== false) {
            $subqueries = explode(" AND ", $esc['mysql']['url_query']);
            foreach ($subqueries as $subquery) {
                $sql .= "(";
                $sql .= "(LOWER(u.url_followed COLLATE $collation) LIKE LOWER('%" . $subquery . "%' COLLATE $collation)) OR ";
                $sql .= "(LOWER(u.url_expanded COLLATE $collation) LIKE LOWER('%" . $subquery . "%' COLLATE $collation))";
                $sql .= ")";
                $sql .= " AND ";
            }
        } elseif (strstr($esc['mysql']['url_query'], "OR") !== false) {
            $subqueries = explode(" OR ", $esc['mysql']['url_query']);
            $sql .= "(";
            foreach ($subqueries as $subquery) {
                $sql .= "(";
                $sql .= "(LOWER(u.url_followed COLLATE $collation) LIKE LOWER('%" . $subquery . "%' COLLATE $collation)) OR ";
                $sql .= "(LOWER(u.url_expanded COLLATE $collation) LIKE LOWER('%" . $subquery . "%' COLLATE $collation))";
                $sql .= ")";
                $sql .= " OR ";
            }
            $sql = substr($sql, 0, -3) . ") AND ";
        } else {
            $subquery = $esc['mysql']['url_query'];
            $sql .= "(";
            $sql .= "(LOWER(u.url_followed COLLATE $collation) LIKE LOWER('%" . $subquery . "%' COLLATE $collation)) OR ";
            $sql .= "(LOWER(u.url_expanded COLLATE $collation) LIKE LOWER('%" . $subquery . "%' COLLATE $collation))";
            $sql .= ") AND ";
        }
    }
    if (!empty($esc['mysql']['geo_query']) && dbserver_has_geo_functions()) {

        $polygon = "POLYGON((" . $esc['mysql']['geo_query'] . "))";

        $polygonfromtext = "GeomFromText('" . $polygon . "')";
        $pointfromtext = "PointFromText(CONCAT('POINT(',t.geo_lng,' ',t.geo_lat,')'))";

        $sql .= " ( t.geo_lat != '0.00000' and t.geo_lng != '0.00000' and ST_Contains(" . $polygonfromtext . ", " . $pointfromtext . ") ";

        $sql .= " ) AND ";
    }

    if (!empty($esc['mysql']['from_source'])) {
        $sql .= "LOWER(t.source COLLATE $collation) LIKE LOWER('%" . $esc['mysql']['from_source'] . "%' COLLATE $collation) AND ";
    }
    if (!empty($esc['mysql']['exclude'])) {
        if (strstr($esc['mysql']['exclude'], "AND") !== false) {
            $subqueries = explode(" AND ", $esc['mysql']['exclude']);
            foreach ($subqueries as $subquery) {
                $sql .= "LOWER(t.text COLLATE $collation) NOT LIKE LOWER('%" . $subquery . "%' COLLATE $collation) AND ";
            }
        } elseif (strstr($esc['mysql']['exclude'], "OR") !== false) {
            $subqueries = explode(" OR ", $esc['mysql']['exclude']);
            $sql .= "(";
            foreach ($subqueries as $subquery) {
                $sql .= "LOWER(t.text COLLATE $collation) NOT LIKE LOWER('%" . $subquery . "%' COLLATE $collation) OR ";
            }
            $sql = substr($sql, 0, -3) . ") AND ";
        } else {
            $sql .= "LOWER(t.text COLLATE $collation) NOT LIKE LOWER('%" . $esc['mysql']['exclude'] . "%' COLLATE $collation) AND ";
        }
    }
    if (!empty($esc['mysql']['from_user_lang'])) {
        if (strstr($esc['mysql']['from_user_lang'], "AND") !== false) {
            $subqueries = explode(" AND ", $esc['mysql']['from_user_lang']);
            foreach ($subqueries as $subquery) {
                $sql .= "from_user_lang = '" . $subquery . "' AND ";
            }
        } elseif (strstr($esc['mysql']['from_user_lang'], "OR") !== false) {
            $subqueries = explode(" OR ", $esc['mysql']['from_user_lang']);
            $sql .= "(";
            foreach ($subqueries as $subquery) {
                $sql .= "from_user_lang = '" . $subquery . "' OR ";
            }
            $sql = substr($sql, 0, -3) . ") AND ";
        } else {
            $sql .= "from_user_lang = '" . $esc['mysql']['from_user_lang'] . "' AND ";
        }
    }
    $sql .= " t.created_at >= '" . $esc['datetime']['startdate'] . "' AND t.created_at <= '" . $esc['datetime']['enddate'] . "' ";
    //print $sql."<br>"; die;

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
    global $tsv, $network, $esc, $titles, $database, $interval, $outputformat;

    require_once("CSV.class.php");

    // initialize variables
    $tweets = $times = $from_user_names = $results = $urls = $urls_expanded = $hosts = $hashtags = array();
    $csv = new CSV($filename, $outputformat);
    $collation = current_collation();

    // determine interval
    $sql = "SELECT MIN(t.created_at) AS min, MAX(t.created_at) AS max FROM " . $esc['mysql']['dataset'] . "_tweets t ";
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
        $sql = "SELECT id,text COLLATE $collation as text,created_at,from_user_name COLLATE $collation as from_user_name FROM " . $esc['mysql']['dataset'] . "_tweets t ";
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
                    $group = strftime("%Y %u", strtotime($time));
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
        if ($what != "user-mention") {
            foreach ($results as $group => $things) {
                $counted_things = array_count_values($things);
                arsort($counted_things);
                $results[$group] = $counted_things;
            }
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
            } else {
                $q = "";
            }
            $csv->writeheader(array($titles[$what] . $q . " from " . $esc['date']["startdate"] . " to " . $esc['date']["enddate"]));
        }

        $csv->writeheader(array("date", "user", "mentions", "tweets"));
        foreach ($results as $group => $things) {
            foreach ($things as $thing => $count) {
                $csv->newrow();
                $csv->addfield($group);
                $csv->addfield($thing);
                $exp = explode(",", $count);    // unpack what we packed
                $csv->addfield($exp[0]);
                $csv->addfield($exp[1]);
                $csv->writerow();
            }
        }
        // write tsv output
    } elseif (in_array($what, $tsv) !== false) {

        ksort($results);

        // construct file
        if (isset($titles[$what])) {
            if (!empty($esc['shell']['query'])) {
                $q = " with search " . $esc['shell']['query'];
            } else {
                $q = "";
            }
            $csv->writeheader(array($titles[$what] . " for " . $esc['shell']['datasetname'] . $q . " from " . $esc['date']["startdate"] . " to " . $esc['date']["enddate"]));
        }

        if ($what == "urls")
            $csv->writeheader(array("date", "frequency", "tweetedurl"));
        elseif ($what == "hosts")
            $csv->writeheader(array("date", "frequency", "domain", "name"));
        else
            $csv->writeheader(array("date", "frequency", $what));
        foreach ($results as $group => $things) {
            arsort($things);
            foreach ($things as $thing => $count) {
                if (empty($thing))
                    continue;
                if ($count < $esc['shell']['minf'])
                    continue;
                $csv->newrow();
                $csv->addfield($group);
                $csv->addfield($count);
                $csv->addfield($thing);
                $csv->writerow();
            }
        }
    } else {
        die('no valid output format found');
    }

    $csv->close();
}

// does some cleanup of data types
function validate($what, $how) {
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
            if (preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $what))
                break;
            if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $what))
                $what = "2011-11-14";
            break;
        // if date is not in yyyymmdd format, set enddate to end of current day
        case "enddate":
            $now = date('U');
            if (preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $what))
                break;
            if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $what)) // TODO, should never be more than 'now'
                $what = "2011-11-15";
            break;
        // escape shell cmd chars
        case "shell":
            $what = preg_replace("/[\/ ]/", "_", $what);
            $what = str_replace("#", "HT", $what);
            if (strlen($what) > 100) {
                $what = escapeshellcmd(substr($what, 0, 100)) . "...";
            } else {
                $what = escapeshellcmd($what);
            }
            break;
        // escape non-mysql chars
        case "mysql":
            $what = preg_replace("/[\[\]]/", "", $what);
            $what = mysql_real_escape_string($what);
            break;
        case "frequency":
            $what = preg_replace("/[^\d]/", "", $what);
            break;
        case "url":
            $what = str_replace('"', '%22', $what);
            break;
        case "outputformat":
            if (!in_array($what, array('csv', 'tsv', 'gexf', 'gdf')))
                $what = 'csv';
            break;
        default:
            break;
    }
    return $what;
}

function decodeAndFlatten($text) {
    return preg_replace("/[\r\t\n]/", " ", html_entity_decode($text));
}

// validate and escape all user input
// make sure that we have all the right types and values
// also make sure one cannot do a mysql injection attack
function validate_all_variables() {
    global $esc, $query, $url_query, $geo_query, $dataset, $exclude, $from_user_name, $from_source, $startdate, $enddate, $databases, $connection, $keywords, $database, $minf, $topu, $from_user_lang, $outputformat;

    $esc['mysql']['dataset'] = validate($dataset, "mysql");
    $esc['mysql']['query'] = validate($query, "mysql");
    $esc['mysql']['url_query'] = validate($url_query, "mysql");
    $esc['mysql']['geo_query'] = validate($geo_query, "mysql");
    $esc['mysql']['exclude'] = validate($exclude, "mysql");
    $esc['mysql']['from_source'] = validate($from_source, "mysql");
    $esc['mysql']['from_user_name'] = validate($from_user_name, "mysql");
    $esc['mysql']['from_user_lang'] = validate($from_user_lang, "mysql");

    $esc['shell']['dataset'] = validate($dataset, "shell");
    $esc['shell']['query'] = validate($query, "shell");
    $esc['shell']['url_query'] = validate($url_query, "shell");
    $esc['shell']['geo_query'] = validate($geo_query, "shell");
    $esc['shell']['exclude'] = validate($exclude, "shell");
    $esc['shell']['from_source'] = validate($from_source, "shell");
    $esc['shell']['from_user_name'] = validate($from_user_name, "shell");
    $esc['shell']['from_user_lang'] = validate($from_user_lang, "shell");
    $esc['shell']['datasetname'] = validate($dataset, "shell");

    $esc['shell']['minf'] = validate($minf, 'frequency');
    $esc['shell']['topu'] = validate($topu, 'frequency');

    $esc['shell']['outputformat'] = validate($outputformat, 'outputformat');

    $esc['date']['startdate'] = validate($startdate, "startdate");
    $esc['date']['enddate'] = validate($enddate, "enddate");

    if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $esc['date']['startdate']))
        $esc['datetime']['startdate'] = $esc['date']['startdate'] . " 00:00:00";
    else
        $esc['datetime']['startdate'] = $esc['date']['startdate'];
    if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $esc['date']['enddate']))
        $esc['datetime']['enddate'] = $esc['date']['enddate'] . " 23:59:59";
    else
        $esc['datetime']['enddate'] = $esc['date']['enddate'];
}

// This function reads the current collation by using the hashtags table as a reference
function current_collation() {
    global $esc;
    $collation = 'utf8_bin';
    $is_utf8mb4 = false;
    $sql = "SHOW FULL COLUMNS FROM " . $esc['mysql']['dataset'] . "_hashtags";
    $sqlresults = mysql_query($sql);
    while ($res = mysql_fetch_assoc($sqlresults)) {
        if (array_key_exists('Collation', $res) && ($res['Collation'] == 'utf8mb4_unicode_ci' || $res['Collation'] == 'utf8mb4_general_ci')) {
            $is_utf8mb4 = true;
            break;
        }
    }
    if ($is_utf8mb4)
        $collation = 'utf8mb4_bin';
    if ($is_utf8mb4 == false) {
        // When the table has columns with collation of utf8 (as opposed to utf8mb4)
        // fall back the current connection character set to utf8 as well, otherwise queries with 'COLLATE utf8_bin' will fail.
        mysql_query("SET NAMES utf8");
    }
    return $collation;
}

// Output format: {dataset}-{startdate}-{enddate}-{query}-{exclude}-{from_user_name}-{from_user_lang}-{url_query}-{module_name}-{module_settings}-{hash}.{filetype}
function get_filename_for_export($module, $settings = "", $filetype = "csv") {
    global $resultsdir, $esc;

    $func_args = func_get_args();
    if (!isset($func_args[2]) && array_key_exists('outputformat', $esc['shell'])) {
        $filetype = $esc['shell']['outputformat'];
    }

    // get software vesion
    exec('git rev-parse --verify HEAD 2> /dev/null', $output);
    $hash = substr($output[0], 0, 10); // first 10 characters (instead of all 40) should be enough to have no collisions
    // construct filename
    $filename = $resultsdir;
    $filename .= $esc['shell']["datasetname"];
    $filename .= "-" . preg_replace("/[-: ]/", "", $esc['date']["startdate"]);
    $filename .= "-" . preg_replace("/[-: ]/", "", $esc['date']["enddate"]);
    $filename .= "-" . stripslashes($esc['shell']["query"]);
    $filename .= "-" . $esc['shell']["exclude"];
    $filename .= "-" . $esc['shell']["from_source"];
    $filename .= "-" . $esc['shell']["from_user_name"];
    $filename .= "-" . $esc['shell']["from_user_lang"];
    $filename .= "-" . $esc['shell']["url_query"];
    $filename .= "-" . str_replace(",", "_", str_replace(" ", "x", $esc['shell']["geo_query"]));
    $filename .= "-" . $module;
    $filename .= "-" . $settings;
    $filename .= "-" . $hash;   // sofware version
    $filename .= "." . $filetype;
    return $filename;
}

function filename_to_url($filename) {
    return str_replace("\\", "%5c", str_replace("[", "%5b", str_replace("]", "%5d", str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)))));
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
function get_all_datasets() {
    global $dataset;
    $dbh = pdo_connect();
    $rec = $dbh->prepare("SELECT id, querybin, type, active, comments FROM tcat_query_bins WHERE visible = TRUE ORDER BY LOWER(querybin)");
    $datasets = array();
    if ($rec->execute() && $rec->rowCount() > 0) {
        while ($res = $rec->fetch()) {
            $row = array();
            $row['bin'] = $res['querybin'];
            $row['type'] = $res['type'];
            $row['active'] = $res['active'];
            $row['comments'] = $res['comments'];
            $rec2 = $dbh->prepare("SELECT count(t.id) AS notweets, MAX(t.created_at) AS max  FROM " . $res['querybin'] . "_tweets t ");
            if ($rec2->execute() && $rec2->rowCount() > 0) {
                $res2 = $rec2->fetch();
                $row['notweets'] = $res2['notweets'];
                $row['maxtime'] = $res2['max'];
            }
            $rec3 = $dbh->prepare("SELECT starttime AS min FROM tcat_query_bins b, tcat_query_bins_periods bp WHERE b.querybin = '" . $res['querybin'] . "' AND b.id = bp.querybin_id");
            if ($rec3->execute() && $rec3->rowCount() > 0) {
                $res3 = $rec3->fetch();
                $row['mintime'] = $res3['min'];
            }
            $row['keywords'] = "";
            if ($dataset == $row['bin']) {
                $rec2 = $dbh->prepare("SELECT distinct(p.phrase) FROM tcat_query_bins_phrases bp, tcat_query_phrases p WHERE bp.querybin_id = " . $res['id'] . " AND bp.phrase_id = p.id ORDER BY LOWER(p.phrase)");
                if ($rec2->execute() && $rec2->rowCount() > 0) {
                    $res2 = $rec2->fetchAll(PDO::FETCH_COLUMN);
                    $row['keywords'] = implode(", ", $res2);
                } elseif (in_array($row['type'], array("follow", "timeline"))) {
                    $rec2 = $dbh->prepare("SELECT distinct(t.from_user_name) FROM tcat_query_bins_users bu, " . $res['querybin'] . "_tweets t WHERE bu.querybin_id = " . $res['id'] . " AND bu.user_id = t.from_user_id ORDER BY LOWER(t.from_user_name)");
                    if ($rec2->execute() && $rec2->rowCount() > 0) {
                        $res2 = $rec2->fetchAll(PDO::FETCH_COLUMN);
                        $row['keywords'] = implode(", ", $res2);
                    }
                }
            }
            $datasets[$row['bin']] = $row;
        }
    }
    return $datasets;
}

function get_total_nr_of_tweets() {
    $select = "SHOW TABLES LIKE '%_tweets'";
    $rec = mysql_query($select);
    $count = 0;
    while ($res = mysql_fetch_row($rec)) {
        $sql = "SELECT COUNT(id) FROM " . $res[0];
        $rec2 = mysql_query($sql);
        if ($rec2) {
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
    if (!mysql_set_charset('utf8mb4', $connection)) {
        echo "Error: Unable to set the character set.\n";
        exit;
    }
    mysql_query("set sql_mode='ALLOW_INVALID_DATES'");
}

function dbserver_has_geo_functions() {
    // the analysis frontend currently uses the mysql_* functions
    $version = mysql_get_server_info();
    if (preg_match("/([0-9]*)\.([0-9]*)\.([0-9]*)/", $version, $matches)) {
        $maj = $matches[1];
        $min = $matches[2];
        $upd = $matches[3];
        if ($maj >= 5 && $min >= 6 && $upd >= 1) {
            return true;
        }
    }
    return false;
}

function pdo_connect() {
    global $dbuser, $dbpass, $database, $hostname;

    $dbh = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8mb4", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $dbh;
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

function array_firstQuartile($array) {    //get the first quartile of an array
    $count = count($array);
    sort($array);
    $n = $count * 0.25;
    if (ceil($n) == $n)
        return ($array[$n - 1] + $array[$n]) / 2;
    else
        return $array[ceil($n) - 1];
}

function array_thirdQuartile($array) {    //get the third quartile value of an array
    $count = count($array);
    sort($array);
    $n = $count * 0.75;
    if (ceil($n) == $n)
        return ($array[$n - 1] + $array[$n]) / 2;
    else
        return $array[ceil($n) - 1];
}

function array_truncatedMean($array, $fraction = 0.25) {
    $count = count($array);
    sort($array);
    $first = $count * $fraction;
    if (ceil($first) != $first)
        $first = ceil($first) - 1;
    $last = $count * (1 - $fraction);
    if (ceil($last) != $last)
        $last = ceil($last) - 1;

    $array_truncated = array_slice($array, $first, ($last - $first));
    $avg = round(average($array_truncated), 2);
    return $avg;
}

function stats_summary($array) {
    $stats = array();
    $stats['min'] = min($array);
    $stats['max'] = max($array);
    $stats['avg'] = round(average($array), 2);
    $stats['median'] = median($array);
    $stats['Q1'] = array_firstQuartile($array);
    $stats['Q3'] = array_thirdQuartile($array);
    $stats['truncatedMean'] = array_truncatedMean($array);
    return $stats;
}

function detect_mention_type($text, $user) {
    $mention_type = 'mention';

    $slangs = array('RT @', 'rt @', 'MT @', 'via @', '"@');
    $work = ' ' . str_replace(array("\r", "\t", "\n"), " ", $text);

    foreach ($slangs as $slang) {
        $match = ' ' . $slang . $user;
        if (mb_strpos($work, $match) !== false) {
            $mention_type = 'retweet';
            break;
        }
    }

    return $mention_type;
}

function sentiment_graph() {

    if (!sentiment_exists())
        return;

    $sent_html = '<fieldset class="if_parameters">
            <legend>Average sentiment detected</legend>
            <div id="if_panel_linegraph_sentiments">';
    $sent_html .= '<script type="text/javascript">';

    $avgs = sentiment_avgs();
    $r = count($avgs);

    $sent_html .= "var sdata = new google.visualization.DataTable()
        sdata.addColumn('string', 'Date');
        sdata.addColumn('number', 'Positive');
        sdata.addColumn('number', 'Negative');
        sdata.addColumn('number', 'Positive subjective');
        sdata.addColumn('number', 'Negative subjective');
        sdata.addRows($r);";

    $counter = 0;
    foreach ($avgs as $key => $sentiment) {
        $sent_html .= "sdata.setValue(" . $counter . ", 0, '" . $key . "');";
        if (isset($sentiment[0]))
            $sent_html .= "sdata.setValue(" . $counter . ", 1, " . $sentiment[0] . ");";
        if (isset($sentiment[1]))
            $sent_html .= "sdata.setValue(" . $counter . ", 2, " . $sentiment[1] . ");";
        if (isset($sentiment[2]))
            $sent_html .= "sdata.setValue(" . $counter . ", 3, " . $sentiment[2] . ");";
        if (isset($sentiment[3]))
            $sent_html .= "sdata.setValue(" . $counter . ", 4, " . $sentiment[3] . ");";
        $counter++;
    }

    $sent_html .= "var schart = new google.visualization.LineChart(document.getElementById('if_panel_linegraph_sentiments'));
    schart.draw(sdata, {width:1000, height:360, colors:['lightblue','pink','#3366cc','#dc3912'], fontSize:9, hAxis:{slantedTextAngle:90, slantedText:true}, chartArea:{left:50,top:10,width:850,height:300}});";

    $sent_html .= '</script>';

    $sent_html .= '<div class="txt_desc"><br /></div></fieldset>';

    echo $sent_html;
}

function sentiment_exists() {
    global $esc;
    $select = "SHOW TABLES LIKE '" . $esc['mysql']['dataset'] . '_sentiment' . "'";
    $rec = mysql_query($select);
    if (mysql_num_rows($rec) > 0)
        return TRUE;
    return FALSE;
}

function sentiment_avgs() {
    global $esc, $period;
    $avgs = array();

    // all sentiments
    $sql = "SELECT avg(s.positive) as pos, avg(s.negative) as neg, ";
    if ($period == "day") // @todo
        $sql .= "DATE_FORMAT(t.created_at,'%Y.%d.%m') datepart ";
    else
        $sql .= "DATE_FORMAT(t.created_at,'%d. %H:00h') datepart ";
    $sql .= "FROM " . $esc['mysql']['dataset'] . "_tweets t, ";
    $sql .= $esc['mysql']['dataset'] . "_sentiment s ";
    $sql .= sqlSubset("t.id = s.tweet_id AND ");
    $sql .= "GROUP BY datepart ORDER BY t.created_at";

    $rec = mysql_query($sql);
    while ($res = mysql_fetch_assoc($rec)) {
        $neg = $res['neg'];
        $pos = $res['pos'];
        $avgs[$res['datepart']][0] = (float) $pos;
        $avgs[$res['datepart']][1] = (float) abs($neg);
    }

    // only subjective
    $sql = "SELECT avg(s.positive) as pos, avg(s.negative) as neg, ";
    if ($period == "day") // @todo
        $sql .= "DATE_FORMAT(t.created_at,'%Y.%d.%m') datepart ";
    else
        $sql .= "DATE_FORMAT(t.created_at,'%d. %H:00h') datepart ";
    $sql .= "FROM " . $esc['mysql']['dataset'] . "_tweets t, ";
    $sql .= $esc['mysql']['dataset'] . "_sentiment s ";
    $sql .= sqlSubset("t.id = s.tweet_id AND (s.positive != 1 AND s.negative != 1) AND ");
    $sql .= "GROUP BY datepart ORDER BY t.created_at";

    $rec = mysql_query($sql);
    while ($res = mysql_fetch_assoc($rec)) {
        $neg = $res['neg'];
        $pos = $res['pos'];
        $avgs[$res['datepart']][2] = (float) $pos;
        $avgs[$res['datepart']][3] = (float) abs($neg);
    }

    // only dateparts
    $sql = "SELECT ";
    if ($period == "day") // @todo
        $sql .= "DATE_FORMAT(t.created_at,'%Y.%d.%m') datepart ";
    else
        $sql .= "DATE_FORMAT(t.created_at,'%d. %H:00h') datepart ";
    $sql .= "FROM " . $esc['mysql']['dataset'] . "_tweets t ";
    $sql .= sqlSubset();
    $sql .= "GROUP BY datepart";

    // initialize with empty dates
    $curdate = strtotime($esc['datetime']['startdate']);
    while ($curdate < strtotime($esc['datetime']['enddate'])) {
        $thendate = ($period == "day") ? $curdate + 86400 : $curdate + 3600;
        $tmp = ($period == "day") ? strftime("%Y.%d.%m", $curdate) : strftime("%d. %H:%M", $curdate) . "h";
        if (!isset($avgs[$tmp])) {
            $avgs[$tmp] = array();
        }
        $curdate = $thendate;
    }

    return $avgs;
}

?>
