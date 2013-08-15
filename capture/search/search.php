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

$bin_name = '';
$keywords = '';

if (empty($bin_name))
    die("bin_name not set\n");
if (empty($keywords))
    die("keywords not set\n");

$current_key = $looped = $tweets_success = $tweets_failed = $tweets_processed = 0;
$all_users = $all_tweet_ids = array();

// ----- connection -----
$dbh = pdo_connect();
create_bin($bin_name, $dbh);

search($keywords);

function search($keywords, $max_id = null) {
    global $twitter_keys, $current_key, $querybins, $path_local, $all_users, $all_tweet_ids, $bin_name, $tweets_success, $tweets_failed, $tweets_processed, $dbh;

    $tmhOAuth = new tmhOAuth(array(
                'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
                'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
                'token' => $twitter_keys[$current_key]['twitter_user_token'],
                'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
            ));
    $params = array(
        'q' => $keywords,
        'count' => 100,
    );
    if (isset($max_id))
        $params['max_id'] = $max_id;

    $code = $tmhOAuth->user_request(array(
        'method' => 'GET',
        'url' => $tmhOAuth->url('1.1/search/tweets'),
        'params' => $params
            ));

    if ($tmhOAuth->response['code'] == 200) {
        $data = json_decode($tmhOAuth->response['response'], true);
        $tweets = $data['statuses'];
        $search_metadata = $data['search_metadata'];
        $headers = $tmhOAuth->response['headers'];
        $ratelimitremaining = $headers['x-rate-limit-remaining'];
        $ratelimitreset = $headers['x-rate-limit-reset'];
        print "remaining $ratelimitremaining\n";

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
        } elseif (count($tweets) == 1)    // search exhausted    
            die("no more tweets found\n");

        print count($tweets) . " tweets found\n";
        foreach ($tweets as $tweet) {

            $t = Tweet::fromJSON(json_encode($tweet)); // @todo: dubbelop

            $all_users[] = $t->user->id;
            $all_tweet_ids[] = $t->id;
            $tweet_ids[] = $t->id;

            $saved = $t->save($dbh, $bin_name);

            if ($saved) {
                $tweets_success++;
            } else {
                $tweets_failed++;
            }

            $tweets_processed++;

            print ".";
        }
        print "\n";


        $max_id = min($tweet_ids);
        print "max id: " . $max_id . "\n";
        sleep(1);
        search($keywords, $max_id);
    } else {
        echo $tmhOAuth->response['response'];
    }
}

?>
