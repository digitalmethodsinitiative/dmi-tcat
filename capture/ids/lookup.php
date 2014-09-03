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


// DEFINE LOOKUP PARAMETERS HERE

$bin_name = 'emoji';       // name of the bin
$idfile = 'idfile';        // path to the input file name. the file must contain only a tweet ID on every line
$type = 'lookup';          // specify 'lookup'

if (empty($bin_name))
    die("bin_name not set\n");
if (empty($idfile))
    die("idfile not set\n");

$querybin_id = queryManagerBinExists($bin_name);
$idlist = preg_split('/\R/', file_get_contents($idfile));
if (!is_array($idlist) || empty($idlist)) {
    die("idfile invalid\n");
}

$all_users = $all_tweet_ids = array();

// ----- connection -----
$dbh = pdo_connect();
create_bin($bin_name, $dbh);

$tweetQueue = new TweetQueue();

search($idlist);

queryManagerCreateBinFromExistingTables($bin_name, $querybin_id, 'import tweetset');

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

                $all_users[] = $t->from_user_id;
                $all_tweet_ids[] = $t->id;
                $tweet_ids[] = $t->id;

                $tweetQueue->push($t, $bin_name);

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
