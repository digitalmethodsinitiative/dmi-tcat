
<?php

// ----- only run from command line -----
if ($argc < 1)
    die;

// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);

// ----- includes -----
include "../../config.php";       // load base config file
include "../../querybins.php";      // load base config file
include "../../common/functions.php";    // load base functions file
include "../common/functions.php";     // load capture function file
$path_local = BASE_FILE . "capture/stream/";

require BASE_FILE . 'capture/common/tmhOAuth/tmhOAuth.php';

// ----- connection -----
dbconnect();      // connect to database
checktables();      // check whether all tables specified in querybins.php exist in the database
//connectsocket();
stream();

function stream() {

    global $twitter_consumer_key, $twitter_consumer_secret, $twitter_user_token, $twitter_user_secret, $querybins, $path_local, $lastinsert;

    logit("error.log", "connecting to API socket");
    $pid = getmypid();
	$lastinsert = time();
    file_put_contents($path_local . "logs/procinfo", $pid . "|" . time());

    $tweetbucket = array();

    // prepare queries
    $querylist = array();
    foreach ($querybins as $binname => $bin) {
        //echo $bin . "|";
        $queries = explode(",", $bin);
        foreach ($queries as $query) {
            $querylist[$query] = preg_replace("/\'/", "", $query);
        }
        $querybins[$binname] = $queries;
    }

    $params = array("track" => implode(",", $querylist));

    $tmhOAuth = new tmhOAuth(array(
                'consumer_key' => $twitter_consumer_key,
                'consumer_secret' => $twitter_consumer_secret,
                'token' => $twitter_user_token,
                'secret' => $twitter_user_secret,
            ));

    $method = 'https://stream.twitter.com/1/statuses/filter.json';

    logit("error.log", "connecting - query " . var_export($params, 1));
    $tmhOAuth->streaming_request('POST', $method, $params, 'streamCallback');

    // output any response we get back AFTER the Stream has stopped -- or it errors
    logit("error.log", "stream stopped - error " . var_export($tmhOAuth, 1));
}

function streamCallback($data, $length, $metrics) {
    global $tweetbucket,$lastinsert;
	$now = time();
    if (isset($data["disconnect"])) {
        $discerror = implode(",", $data["disconnect"]);
        logit("error.log", "connection dropped or timed out - error " . $discerror);
    }
    $data = json_decode($data, true);
    if ($data) {
        $tweetbucket[] = $data;
        if (count($tweetbucket) == 100 || $now > $lastinsert + 5) {
            processtweets($tweetbucket);
			$lastinsert = time();
            $tweetbucket = array();
        }
    }
}

// function receives a bucket of tweets, sorts them according to bins and inserts into DB
function processtweets($tweetbucket) { // @todo, should use tweet entity in capture/common/functions.php
    global $querybins, $path_local;

    // we run through every bin to check whether the received tweets fit
    foreach ($querybins as $binname => $queries) {
        $list_tweets = array();
        $list_hashtags = array();
        $list_urls = array();
        $list_mentions = array();

        // running through every single tweet
        foreach ($tweetbucket as $data) {

            // adding the expanded url to the tweets text to search in them like twiter does
            foreach ($data["entities"]["urls"] as $url) {
                $data["text"] .= " [[" . $url["expanded_url"] . "]]";
            }


            // we check for every query in the bin if they fit
            $found = false;

            foreach ($queries as $query) {

                $pass = false;

                // check for queries with two words, but go around quoted queries
                if (preg_match("/ /", $query) && !preg_match("/\'/", $query)) {
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

                    // treet quoted queries as single words
                    $query = preg_replace("/\'/", "", $query);

                    if (preg_match("/" . $query . "/i", $data["text"])) {
                        $pass = true;
                    }
                }

                // at the first fitting query, we break
                if ($pass == true) {
                    $found = true;
                    $break;
                }
            }

            // ["retweeted_status"]["id_str"] => retweet_id
            // => from_user_utcoffset int(11),
            // from_user_timezone varchar(255) NOT NULL,
            // from_user_verified
            // from_user_listed
            // from_user_description
            // from_user_url
            // if the tweet does not fit in the current bin, go to the next tweet
            if ($found == false) {
                continue;
            }

            //from_user_lang 	from_user_tweetcount 	from_user_followercount 	from_user_realname
            $t = array();
            $t["id"] = $data["id_str"];
            $t["created_at"] = date("Y-m-d H:i:s", strtotime($data["created_at"]));
            $t["from_user_name"] = addslashes($data["user"]["screen_name"]);
            $t["from_user_id"] = $data["user"]["id"];
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
            $t["filter_level"] = $data["filter_level"];

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
                logit("error.log", "insert error: " . $sql);
            } else {
                $pid = getmypid();
                file_put_contents($path_local . "logs/procinfo", $pid . "|" . time());
            }
        }

        if (count($list_hashtags) > 0) {

            $sql = "INSERT IGNORE INTO " . $binname . "_hashtags (tweet_id,created_at,from_user_name,from_user_id,text) VALUES " . implode(",", $list_hashtags);

            $sqlresults = mysql_query($sql);
            if (!$sqlresults) {
                logit("error.log", "insert error: " . $sql);
            }
        }

        if (count($list_urls) > 0) {

            $sql = "INSERT IGNORE INTO " . $binname . "_urls (tweet_id,created_at,from_user_name,from_user_id,url,url_expanded) VALUES " . implode(",", $list_urls);

            $sqlresults = mysql_query($sql);
            if (!$sqlresults) {
                logit("error.log", "insert error: " . $sql);
            }
        }

        if (count($list_mentions) > 0) {

            $sql = "INSERT IGNORE INTO " . $binname . "_mentions (tweet_id,created_at,from_user_name,from_user_id,to_user,to_user_id) VALUES " . implode(",", $list_mentions);

            $sqlresults = mysql_query($sql);
            if (!$sqlresults) {
                logit("error.log", "insert error: " . $sql);
            }
        }
    }
}

function logit($file, $message) {

    global $path_local;

    $file = $path_local . "logs/" . $file;
    $message = date("Y-m-d H:i:s") . " " . $message . "\n";
    file_put_contents($file, $message, FILE_APPEND);
}

function safe_feof($fp, &$start = NULL) {
    $start = microtime(true);
    return feof($fp);
}

?>
