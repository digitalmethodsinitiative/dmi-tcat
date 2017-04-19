<?php

/*
 * get full user objects from https://dev.twitter.com/rest/reference/get/users/lookup
 * skip tweets, just make separate user table
 */

if ($argc < 1)
    die; // only run from command line

    /* ----- params ----- */
set_time_limit(0);
error_reporting(E_ALL);
include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../common/constants.php';
include_once __DIR__ . '/../../common/functions.php';
include_once __DIR__ . '/../common/functions.php';

require __DIR__ . '/../common/tmhOAuth/tmhOAuth.php';

/* ----- connection ----- */
$dbh = pdo_connect();

//$bin_name = "lijsttrekker_aanvallers";
$bin_name = "lijsttrekkers_likers"; 
$max_num_friends = 10000;
$user_ids = array();

if (empty($bin_name))
    die("bin_name not set\n");

$ratefree = $current_key = $looped = 0;

if (empty($user_ids)) {
    // retrieve users from a set of tweets
    //$q = $dbh->prepare("SELECT user1_id, user2_id FROM " . $bin_name . "_relations WHERE user1_id IN (SELECT distinct(from_user_id) FROM " . $bin_name . "_tweets WHERE from_user_friendcount <= $max_num_friends)");
    $q = $dbh->prepare("SELECT user1_id, user2_id FROM " . $bin_name . "_relations WHERE user1_id");
    if ($q->execute()) {
        $res = $q->fetchAll(PDO::FETCH_ASSOC);
        foreach ($res as $r) {
            $user_ids[] = $r['user1_id'];
            $user_ids[] = $r['user2_id'];
        }
    }
}
$user_ids = array_unique($user_ids);

if (empty($user_ids))
    die("no users found for querybin $bin_name\n");
else
    print "found " . count($user_ids) . "\n";

TwitterUsers::create_users_table($dbh, $bin_name);

// select batches of 100 users
// retrieve friends
$starttime = date('U');

while (count($user_ids) > 0) {
    $batch = array_splice($user_ids, 0, 100);
    get_user_info($batch);
    print count($user_ids) . " users remaining\n";
}
print "started at " . strftime("%Y-%m-%d %H:%m:%S", $starttime);
print "ended at " . strftime("%Y-%m-%d %H:%m:%S", date('U'));

function get_user_info($ids) {
    print "getting " . count($ids) . " user infos\n";
    global $twitter_keys, $current_key, $ratefree, $bin_name, $dbh;

    $ratefree--;
    if ($ratefree < 1 || $ratefree % 10 == 0) {
        $keyinfo = getRESTKey($current_key, 'users', 'lookup');
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
        'user_id' => implode(",", $ids),
        'include_entities' => false,
    );

    $tmhOAuth->user_request(array(
        'method' => 'GET',
        'url' => $tmhOAuth->url('1.1/users/lookup'),
        'params' => $params
    ));

    if ($tmhOAuth->response['code'] == 200) {
        $data = json_decode($tmhOAuth->response['response'], true);
        $observed_at = strftime("%Y-%m-%d %H-%M-%S", date('U'));

        echo count($data) . " users retrieved\n";

        // store in db
        $tr = new TwitterUsers($data, $observed_at);
        $tr->save($dbh, $bin_name);
    } else {
        $error_code = json_decode($tmhOAuth->response['response'])->errors[0]->code;
        if ($error_code == 130) {
            print "Twitter is over capacity, sleeping 5 seconds before retry\n";
            sleep(5);
            get_user_info($ids);
        } elseif ($error_code == 88) {
            print "API key rate limit exceeded, sleeping 60 seconds before retry\n";
            sleep(60);
            get_user_info($ids);
        } else {
            echo "\nAPI error: " . $tmhOAuth->response['response'] . "\n";
        }
    }
}

?>