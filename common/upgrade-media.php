<?php

if ($argc < 1)
    die; // only run from command line
// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);

include_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../common/constants.php';
include __DIR__ . '/functions.php';
include __DIR__ . '/../capture/common/functions.php';
include __DIR__ . '/../capture/common/tmhOAuth/tmhOAuth.php';

if (dbserver_has_utf8mb4_support() == false) {
    die("DMI-TCAT requires at least MySQL version 5.5.3 - please upgrade your server\n");
}

$all_users = $all_tweet_ids = array();

// ----- connection -----
$dbh = pdo_connect();

$tweetQueue = new TweetQueue();

global $bin_name;

$bins = getAllBins();

foreach ($bins as $bin) {
    $table = '`' . $bin . '_urls`'; // bin != user input
    $idlist = array();
    $sql = "show columns from $table";
    $deprecated = 0;
    $rec = $dbh->prepare($sql);
    if ($rec->execute() && $rec->rowCount() > 0) {
        while ($res = $rec->fetch()) {
            if ($res['Field'] == 'url_is_media_upload') {
                $deprecated = 1; break;
            }
        }
    }
    if ($deprecated) {
        $sql = "select tweet_id from $table where url_is_media_upload = 1";
        $rec = $dbh->prepare($sql);
        if ($rec->execute() && $rec->rowCount() > 0) {
            while ($res = $rec->fetch()) {
                $idlist[] = $res['tweet_id'];
            }
        }
        if (!empty($idlist)) {
            print count($idlist) . " (possibly) deprecated media objects found in bin $bin\n";
            $bin_name = $bin;
            search($idlist);
        }
    }
}

function search($idlist) {
    global $twitter_keys, $current_key, $all_users, $all_tweet_ids, $bin_name, $dbh, $tweetQueue;

    $keyinfo = getRESTKey(0);
    $current_key = $keyinfo['key'];
    $ratefree = $keyinfo['remaining'];

    print "current key $current_key ratefree $ratefree\n";

    $tmhOAuth = new tmhOAuth(array(
                'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
                'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
                'token' => $twitter_keys[$current_key]['twitter_user_token'],
                'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
            ));

    // by hundred
    for ($i = 0; $i < sizeof($idlist); $i += 100) {

	    if ($ratefree <= 0 || $ratefree % 10 == 0) {
    		$keyinfo = getRESTKey($current_key);
    		$current_key = $keyinfo['key'];
    		$ratefree = $keyinfo['remaining'];
    		$tmhOAuth = new tmhOAuth(array(
                		'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
                		'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
                		'token' => $twitter_keys[$current_key]['twitter_user_token'],
                		'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
            		));
	    }

        $q = $idlist[$i];
        $n = $i + 1;
        while ($n < $i + 100) {
            if (!isset($idlist[$n])) break;
            $q .= "," . $idlist[$n];
            $n++;
        }

        $params = array(
            'id' => $q,
        );

        $code = $tmhOAuth->user_request(array(
            'method' => 'GET',
            'url' => $tmhOAuth->url('1.1/statuses/lookup'),
            'params' => $params
                ));

	    $ratefree--;

        if ($tmhOAuth->response['code'] == 200) {
            $data = json_decode($tmhOAuth->response['response'], true);

            if (is_array($data) && empty($data)) {
                // all tweets in set are deleted
                continue;
            }

            $tweets = $data;

            $tweet_ids = array();
            foreach ($tweets as $tweet) {

                $t = new Tweet();
                $t->fromJSON($tweet);
                if ($t->isInBin($bin_name)) {
                    // Delete old record of Tweet
                    $t->deleteFromBin($bin_name);
                    // And insert new record (with media data)
                    $all_users[] = $t->from_user_id;
                    $all_tweet_ids[] = $t->id;
                    $tweet_ids[] = $t->id;
                    $tweetQueue->push($t, $bin_name);
                }

                print ".";
            }
            sleep(1);
        } else {
            echo "Failure with code " . $tmhOAuth->response['response']['code'] . "\n";
            var_dump($tmhOAuth->response['response']['info']);
            var_dump($tmhOAuth->response['response']['error']);
            var_dump($tmhOAuth->response['response']['errno']);
            die();
        }

        $tweetQueue->insertDB();
    }
}
?>
