<?php

if ($argc < 1)
    die; // only run from command line
// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);
include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../common/constants.php';
include_once __DIR__ . '/../../common/functions.php';
include_once __DIR__ . '/../common/functions.php';

require __DIR__ . '/../common/tmhOAuth/tmhOAuth.php';

// ----- connection -----
$dbh = pdo_connect();

$bin_name = "";
$max_num_friends = 100000;
$screen_names = array(); // an array of screen_names, leave empty if you want to retrieve users from an existing bin

if (empty($bin_name))
    die("bin_name not set\n");

$ratefree = $current_key = $looped = 0;

if (empty($screen_names)) {
    // retrieve users from a set of tweets
    $q = $dbh->prepare("SELECT from_user_name, max(from_user_friendcount) as m FROM " . $bin_name . "_tweets group by from_user_name order by m");
    if ($q->execute())
    	$res = $q->fetchAll(PDO::FETCH_ASSOC);
    foreach ($res as $r) {
        if ($r['m'] <= $max_num_friends)
            $screen_names[] = $r['from_user_name'];
    }
    //var_dump($screen_names); die;
}
if (empty($screen_names))
    die("no users found for querybin $bin_name\n");

// create relations table
TwitterRelations::create_relations_tables($dbh, $bin_name);

// retrieve friends
$starttime = date('U');
foreach ($screen_names as $screen_name) {
    get_friend_ids($screen_name);
}
print "started at " . strftime("%Y-%m-%d %H:%m:%S", $starttime);
print "ended at " . strftime("%Y-%m-%d %H:%m:%S", date('U'));

function get_friend_ids($screen_name, $cursor = -1) {
    print "getting friend ids of $screen_name\n";
    global $twitter_keys, $current_key, $ratefree, $bin_name, $dbh;

    $ratefree--;
    if ($ratefree < 1 || $ratefree % 10 == 0) {
        $keyinfo = getRESTKey($current_key, 'friends', 'ids');
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
        'cursor' => $cursor,
        'count' => 5000,
        'screen_name' => $screen_name,
        'stringify_ids' => true
    );

    $tmhOAuth->user_request(array(
        'method' => 'GET',
        'url' => $tmhOAuth->url('1.1/friends/ids'),
        'params' => $params
    ));

    if ($tmhOAuth->response['code'] == 200) {
        $data = json_decode($tmhOAuth->response['response'], true);
        $observed_at = strftime("%Y-%m-%d %H-%M-%S", date('U'));

        $friends = $data['ids'];
        $cursor = $data['next_cursor'];

        echo count($friends) . " friend ids found\n";

        // store in db
        $tr = new TwitterRelations($screen_name, $friends, "friend", $observed_at);
        $tr->save($dbh, $bin_name);

        // continue if there are still things to do
        if (!empty($cursor) && $cursor != -1) {
            sleep(1);
            get_friend_ids($screen_name, $cursor);
        }
    } else {
        $error_code = json_decode($tmhOAuth->response['response'])->errors[0]->code;
        if ($error_code == 130) {
            print "Twitter is over capacity, sleeping 5 seconds before retry\n";
            sleep(5);
            get_friend_ids($screen_name, $cursor);
        } elseif ($error_code == 88) {
            print "API key rate limit exceeded, sleeping 60 seconds before retry\n";
            sleep(60);
            get_friend_ids($screen_name, $cursor);
        } else {
            echo "\nAPI error: " . $tmhOAuth->response['response'] . "\n";
        }
    }
}

?>
