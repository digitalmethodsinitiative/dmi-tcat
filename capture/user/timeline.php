<?php

if ($argc < 1)
    die; // only run from command line
// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);
include_once "../../config.php";
include_once BASE_FILE . '/common/functions.php';
include_once BASE_FILE . '/capture/common/functions.php';

require BASE_FILE . 'capture/common/tmhOAuth/tmhOAuth.php';
// ----- connection -----
$dbh = pdo_connect();

$user_ids = array(); // provide an array of user ids or screen names
$bin_name = "";
$list_name = "";
$type = 'timeline'; // specify 'timeline' if you want this to be a standalone bin, or 'follow' if you want to be able to continue tracking these users later on via BASE_URL/capture/index.php


if (!empty($list_name)) { // instead of specying usernames, you can also fetch usernames from a specific list in the database
    $q = $dbh->prepare("SELECT list_id FROM " . $bin_name . "_lists WHERE list_name = :list_name");
    $q->bindParam(":list_name", trim($list_name), PDO::PARAM_STR);
    if ($q->execute()) {
        $list_id = $q->fetchAll(PDO::FETCH_COLUMN, 0);
        $list_id = $list_id[0];
        $q = $dbh->prepare("SELECT user_id FROM " . $bin_name . "_lists_membership WHERE list_id = :list_id");
        $q->bindParam(":list_id", $list_id, PDO::PARAM_INT);
        if ($q->execute()) {
            $user_ids = $q->fetchAll(PDO::FETCH_COLUMN, 0);
        }
    }
}
// you can also retrieve the users from a set of tweets
if (empty($user_ids)) {
    $q = $dbh->prepare("SELECT DISTINCT(from_user_name) FROM " . $bin_name . "_tweets");
    if ($q->execute()) {
        $user_ids = $q->fetchAll(PDO::FETCH_COLUMN, 0);
    }
}

if (empty($bin_name))
    die("bin_name not set\n");
if (empty($user_ids))
    die("user_ids not set\n");

$querybin_id = queryManagerBinExists($bin_name);

$ratefree = $current_key = $looped = 0;

create_bin($bin_name, $dbh);

$tweetQueue = new TweetQueue();

foreach ($user_ids as $user_id) {
    if (is_int($user_id))
        get_timeline($user_id, "user_id");
    else
        get_timeline($user_id, "screen_name");
}

if ($tweetQueue->length() > 0) {
    $tweetQueue->insertDB();
}

queryManagerCreateBinFromExistingTables($bin_name, $querybin_id, $type);

function get_timeline($user_id, $type, $max_id = null) {
    print "doing $user_id\n";
    global $twitter_keys, $current_key, $ratefree, $looped, $bin_name, $dbh, $tweetQueue;

    $ratefree--;
    if ($ratefree < 1 || $ratefree % 10 == 0) {
	$keyinfo = getRESTKey($current_key, 'statuses', 'user_timeline');
	$current_key = $keyinfo['key'];
	$ratefree = $keyinfo['remaining'];
    }

    $tmhOAuth = new tmhOAuth(array(
                'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
                'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
                'token' => $twitter_keys[$current_key]['twitter_user_token'],
                'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
            ));
    $params = array(
        'count' => 200,
        'trim_user' => false,
        'exclude_replies' => false,
        'contributor_details' => true,
        'include_rts' => 1
    );

    if ($type == "user_id")
        $params['user_id'] = $user_id;
    else
        $params['screen_name'] = $user_id;

    if (isset($max_id))
        $params['max_id'] = $max_id;

    $tmhOAuth->user_request(array(
        'method' => 'GET',
        'url' => $tmhOAuth->url('1.1/statuses/user_timeline'),
        'params' => $params
    ));

    //var_export($params); print "\n";

    if ($tmhOAuth->response['code'] == 200) {
        $tweets = json_decode($tmhOAuth->response['response'], true);

        // store in db
        $tweet_ids = array();
        foreach ($tweets as $tweet) {
            $t = new Tweet();
            $t->fromJSON($tweet);
            $tweet_ids[] = $t->id;
            if (!$t->isInBin($bin_name)) {
                $tweetQueue->push($t, $bin_name);
                print ".";
                if ($tweetQueue->length() > 100)
                    $tweetQueue->insertDB();
            }
        }

        if (!empty($tweet_ids)) {
            print "\n";
            if (count($tweet_ids) <= 1) {
                print "no more tweets found\n\n";
                return false;
            }
            $max_id = min($tweet_ids);
            print "max id: " . $max_id . "\n";
        } else {
            print "0 tweets found\n\n";
            return false;
        }
        sleep(1);
        get_timeline($user_id, $type, $max_id);
    } else {
        $error_code = json_decode($tmhOAuth->response['response'])->errors[0]->code;
        if ($error_code == 130) {
            print "Twitter is over capacity, sleeping 5 seconds before retry\n";
            sleep(5);
            get_timeline($user_id, $type, $max_id);
        } elseif ($error_code == 88) {
            print "API key rate limit exceeded, sleeping 60 seconds before retry\n";
            sleep(60);
            get_timeline($user_id, $type, $max_id);
        } else {
            echo "\nAPI error: " . $tmhOAuth->response['response'] . "\n";
        }
    }
}

?>
