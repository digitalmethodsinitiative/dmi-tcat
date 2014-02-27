<?php

if ($argc < 1)
    die; // only run from command line
// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);
include_once "../../config.php";
include_once BASE_FILE . "/querybins.php";
include_once BASE_FILE . '/common/functions.php';
include_once BASE_FILE . '/capture/common/functions.php';

require BASE_FILE . 'capture/common/tmhOAuth/tmhOAuth.php';
// ----- connection -----
$dbh = pdo_connect();

$user_ids = array();
$bin_name = "";
$list_name = "";

// instead of specying usernames, you can also fetch usernames from a specific list in the database
if (!empty($list_name)) {
    $q = $dbh->prepare("SELECT list_id FROM " . $bin_name . "_lists WHERE list_name = '" . $list_name . "'");
    if ($q->execute()) {
        $list_id = $q->fetchAll(PDO::FETCH_COLUMN, 0);
        $list_id = $list_id[0];
        $q = $dbh->prepare("SELECT user_id FROM penw_lists_membership WHERE list_id = $list_id");
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

$current_key = $looped = 0;

create_bin($bin_name, $dbh);

foreach ($user_ids as $user_id) {
    get_timeline($user_id);
}

function get_timeline($user_id, $max_id = null) {
    print "doing $user_id\n";
    global $twitter_keys, $current_key, $looped, $querybins, $bin_name, $dbh;

    $tmhOAuth = new tmhOAuth(array(
                'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
                'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
                'token' => $twitter_keys[$current_key]['twitter_user_token'],
                'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
            ));
    $params = array(
        'user_id' => $user_id, // you can use user_id or screen_name here
        'count' => 200,
        'trim_user' => false,
        'exclude_replies' => false,
        'contributor_details' => true,
        'include_rts' => 1
    );

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

        // check rate limiting
        $headers = $tmhOAuth->response['headers'];
        $ratelimitremaining = $headers['x-rate-limit-remaining'];
        $ratelimitreset = $headers['x-rate-limit-reset'];
        print "remaining API requests: $ratelimitremaining\n";

        if ($ratelimitremaining == 0) {
            $current_key++;
            print "next key $current_key\n";
            if ($current_key >= count($twitter_keys)) {
                $current_key = 0;
                $looped = 1;
                print "resetting key to 0\n";
            } elseif ($current_key == 0 && $looped == 1) {
                if (count($tweets) > 1)
                    $looped = 0;
                else {
                    print "looped over all keys but still can't get new tweets, sleeping\n";
                    sleep(5);
                }
            }
        }

        // store in db
        $tweet_ids = array();
        foreach ($tweets as $tweet) {
            $t = Tweet::fromJSON(json_encode($tweet)); // @todo: dubbelop
            $tweet_ids[] = $t->id;
            $saved = $t->save($dbh, $bin_name);
            print ".";
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
        get_timeline($user_id, $max_id);
    } else {
        $error_code = json_decode($tmhOAuth->response['response'])->errors[0]->code;
        if ($error_code == 130) {
            print "Twitter is over capacity, sleeping 5 seconds before retry\n";
            sleep(5);
            get_timeline($user_id, $max_id);
        } elseif ($error_code == 88) {
            print "API key rate limit exceeded, sleeping 60 seconds before retry\n";
            sleep(60);
            get_timeline($user_id, $max_id);
        } else {
            echo "\nAPI error: " . $tmhOAuth->response['response'] . "\n";
        }
    }
}

?>
