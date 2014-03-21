
<?php

// ----- only run from command line -----
if ($argc < 1)
    die;

// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);

define('CAPTURE', 'onepercent');

// ----- includes -----
include "../../config.php";                  // load base config file
include "../../common/functions.php";        // load base functions file
include "../common/functions.php";           // load capture function file
$path_local = BASE_FILE;

require BASE_FILE . 'capture/common/tmhOAuth/tmhOAuth.php';

// ----- connection -----
dbconnect();      // connect to database @todo, rewrite mysql calls with pdo
stream();

$last_insert_id = -1;

function stream() {

    global $twitter_consumer_key, $twitter_consumer_secret, $twitter_user_token, $twitter_user_secret, $querybins, $path_local, $lastinsert;

    logit(CAPTURE . ".error.log", "connecting to API socket");
    $pid = getmypid();
    $lastinsert = time();
    file_put_contents($path_local . "proc/" . CAPTURE . ".procinfo", $pid . "|" . time());

    $tweetbucket = array();

    $tmhOAuth = new tmhOAuth(array(
                'consumer_key' => $twitter_consumer_key,
                'consumer_secret' => $twitter_consumer_secret,
                'token' => $twitter_user_token,
                'secret' => $twitter_user_secret,
                'host' => 'stream.twitter.com',
            ));
    $tmhOAuth->request_settings['headers']['Host'] = 'stream.twitter.com';

    $networkpath = isset($GLOBALS["HOSTROLE"][CAPTURE]) ? $GLOBALS["HOSTROLE"][CAPTURE] : 'https://stream.twitter.com/';
    $method = $networkpath . '1.1/statuses/sample.json';
    $params = array('stall_warnings' => 'true');

    logit(CAPTURE . ".error.log", "connecting");
    $tmhOAuth->streaming_request('POST', $method, $params, 'streamCallback', array('Host' => 'stream.twitter.com'));

    // output any response we get back AFTER the Stream has stopped -- or it errors
    logit(CAPTURE . ".error.log", "stream stopped - error " . var_export($tmhOAuth, 1));

    logit(CAPTURE . ".error.log", "processing buffer before exit");
    processtweets($tweetbucket);
}

function streamCallback($data, $length, $metrics) {
    global $tweetbucket, $lastinsert;
    $now = time();
    $data = json_decode($data, true);
    if (isset($data["disconnect"])) {
        $discerror = implode(",", $data["disconnect"]);
        logit(CAPTURE . ".error.log", "connection dropped or timed out - error $discerror");
        logit(CAPTURE . ".error.log", "(debug) drump of result data on disconnect" . var_export($data, true));
    }
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
    global $path_local;

    $binname = 'onepercent';

    $list_tweets = array();
    $list_hashtags = array();
    $list_urls = array();
    $list_mentions = array();

    // running through every single tweet
    foreach ($tweetbucket as $data) {

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

        if (!array_key_exists('entities', $data)) {

            // unexpected/irregular tweet data
            if (array_key_exists('delete', $data)) {
                // a tweet has been deleted. @todo: process
                continue;
            }

            // this can get very verbose when repeated?
            //logit(CAPTURE . ".error.log", "irregular tweet data: " . var_export($data, 1));
            continue;
        }

        $t = array();
        $t["id"] = $data["id_str"];
        $t["created_at"] = date("Y-m-d H:i:s", strtotime($data["created_at"]));
        $t["from_user_name"] = mysql_real_escape_string($data["user"]["screen_name"]);
        $t["from_user_id"] = $data["user"]["id_str"];
        $t["from_user_lang"] = $data["user"]["lang"];
        $t["from_user_tweetcount"] = $data["user"]["statuses_count"];
        $t["from_user_followercount"] = $data["user"]["followers_count"];
        $t["from_user_friendcount"] = $data["user"]["friends_count"];
        $t["from_user_listed"] = $data["user"]["listed_count"];
        $t["from_user_realname"] = mysql_real_escape_string($data["user"]["name"]);
        $t["from_user_utcoffset"] = $data["user"]["utc_offset"];
        $t["from_user_timezone"] = mysql_real_escape_string($data["user"]["time_zone"]);
        $t["from_user_description"] = mysql_real_escape_string($data["user"]["description"]);
        $t["from_user_url"] = mysql_real_escape_string($data["user"]["url"]);
        $t["from_user_verified"] = $data["user"]["verified"];
        $t["from_user_profile_image_url"] = $data["user"]["profile_image_url"];
        $t["source"] = mysql_real_escape_string($data["source"]);
        $t["location"] = mysql_real_escape_string($data["user"]["location"]);
        $t["geo_lat"] = 0;
        $t["geo_lng"] = 0;
        if ($data["geo"] != null) {
            $t["geo_lat"] = $data["geo"]["coordinates"][0];
            $t["geo_lng"] = $data["geo"]["coordinates"][1];
        }
        $t["text"] = mysql_real_escape_string($data["text"]);
        $t["retweet_id"] = null;
        if (isset($data["retweeted_status"])) {
            $t["retweet_id"] = $data["retweeted_status"]["id_str"];
        }
        $t["to_user_id"] = $data["in_reply_to_user_id_str"];
        $t["to_user_name"] = mysql_real_escape_string($data["in_reply_to_screen_name"]);
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
                $h["text"] = mysql_real_escape_string($hashtag["text"]);

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
                $u["url_expanded"] = mysql_real_escape_string($url["expanded_url"]);

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

        $sql = "INSERT DELAYED IGNORE INTO " . $binname . "_tweets (id,created_at,from_user_name,from_user_id,from_user_lang,from_user_tweetcount,from_user_followercount,from_user_friendcount,from_user_listed,from_user_realname,from_user_utcoffset,from_user_timezone,from_user_description,from_user_url,from_user_verified,from_user_profile_image_url,source,location,geo_lat,geo_lng,text,retweet_id,to_user_id,to_user_name,in_reply_to_status_id,filter_level) VALUES " . implode(",", $list_tweets);
        $sqlresults = mysql_query($sql);
        if (!$sqlresults) {
            logit(CAPTURE . ".error.log", "insert error: " . $sql);
        } elseif (database_activity()) {
            $pid = getmypid();
            file_put_contents($path_local . "proc/" . CAPTURE . ".procinfo", $pid . "|" . time());
        }
    }

    if (count($list_hashtags) > 0) {

        $sql = "INSERT DELAYED IGNORE INTO " . $binname . "_hashtags (tweet_id,created_at,from_user_name,from_user_id,text) VALUES " . implode(",", $list_hashtags);

        $sqlresults = mysql_query($sql);
        if (!$sqlresults) {
            logit(CAPTURE . ".error.log", "insert error: " . $sql);
        }
    }

    if (count($list_urls) > 0) {

        $sql = "INSERT DELAYED IGNORE INTO " . $binname . "_urls (tweet_id,created_at,from_user_name,from_user_id,url,url_expanded) VALUES " . implode(",", $list_urls);

        $sqlresults = mysql_query($sql);
        if (!$sqlresults) {
            logit(CAPTURE . "error.log", "insert error: " . $sql);
        }
    }

    if (count($list_mentions) > 0) {

        $sql = "INSERT DELAYED IGNORE INTO " . $binname . "_mentions (tweet_id,created_at,from_user_name,from_user_id,to_user,to_user_id) VALUES " . implode(",", $list_mentions);

        $sqlresults = mysql_query($sql);
        if (!$sqlresults) {
            logit(CAPTURE . ".error.log", "insert error: " . $sql);
        }
    }
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
