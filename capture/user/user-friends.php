<?php

if ($argc < 1)
    die; // only run from command line

    
// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);
include_once "../../config.php";
include_once BASE_FILE . '/common/functions.php';
include_once BASE_FILE . '/capture/common/functions.php';

require BASE_FILE . '/capture/common/tmhOAuth/tmhOAuth.php';

// ----- connection -----
$dbh = pdo_connect();

$bin_name = "";
$screen_names = array(); // an array of screen_names, leave empty if you want to retrieve users from an existing bin

if (empty($bin_name))
    die("bin_name not set\n");

$current_key = $looped = 0;

if(empty($screen_names)) {
	// retrieve users from a set of tweets
	$q = $dbh->prepare("SELECT DISTINCT(from_user_name) FROM " . $bin_name . "_tweets");
	if ($q->execute()) {
	    $screen_names = $q->fetchAll(PDO::FETCH_COLUMN, 0);
	}
}
if (empty($screen_names))
    die("no users found for querybin $bin_name\n");

// create relations table
TwitterRelations::create_relations_tables($dbh, $bin_name);

// retrieve friends
$starttime = date('U');
foreach ($screen_names as $screen_name) {
    get_friends($screen_name);
}
print "started at " . strftime("%Y-%m-%d %H:%m:%S", $starttime);
print "ended at " . strftime("%Y-%m-%d %H:%m:%S", date('U'));

function get_friends($screen_name, $cursor = -1) {
    print "getting friends of $screen_name\n";
    global $twitter_keys, $current_key, $bin_name, $dbh;

    $tmhOAuth = new tmhOAuth(array(
                'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
                'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
                'token' => $twitter_keys[$current_key]['twitter_user_token'],
                'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
            ));
    $params = array(
        'cursor' => $cursor,
        'skip_status' => true,
        'count' => 200,
        'include_user_entities' => false,
        'screen_name' => $screen_name
    );

    $tmhOAuth->user_request(array(
        'method' => 'GET',
        'url' => $tmhOAuth->url('1.1/friends/list'),
        'params' => $params
    ));

    if ($tmhOAuth->response['code'] == 200) {
        $data = json_decode($tmhOAuth->response['response'], true);
        $observed_at = strftime("%Y-%m-%d %H-%M-%S", date('U'));

        $friends = $data['users'];
        $cursor = $data['next_cursor'];

        echo count($friends) . " users found\n";

        // store in db
        $tr = new TwitterRelations($screen_name, $friends, "friend", $observed_at);
        $tr->save($dbh, $bin_name);

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

        // continue if there are still things to do
        if (!empty($cursor) && $cursor != -1) {
            sleep(1);
            get_friends($screen_name, $cursor);
        }
    } else {
        $error_code = json_decode($tmhOAuth->response['response'])->errors[0]->code;
        if ($error_code == 130) {
            print "Twitter is over capacity, sleeping 5 seconds before retry\n";
            sleep(5);
            get_friends($screen_name, $cursor);
        } elseif ($error_code == 88) {
            print "API key rate limit exceeded, sleeping 60 seconds before retry\n";
            sleep(60);
            get_friends($screen_name, $cursor);
        } else {
            echo "\nAPI error: " . $tmhOAuth->response['response'] . "\n";
        }
    }
}

?>
