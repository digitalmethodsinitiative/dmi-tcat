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

$screennames = array("fvdemocratie","thierrybaudet","THiddema","Susan_teunissen","PFrentrop","SusanStolze","Gert_Reedijk","YerRamautarsing","ZlataBrouwer","caroladieudonne","godertvanassen","GeertJeelof","luke_boltjes","Sander_O_Boon","Astriddegroot70","HemmieKerklingh","hvelzing","loekvanwely");  // provide an array of screen names
// ----- connection -----
$dbh = pdo_connect();
$ratefree = $current_key = $looped = 0;

foreach ($screennames as $screenname) {
    get_user_id($screenname);
}

print "found (" . count($users) . "/" . count($screennames) . "):";
foreach ($users as $user => $id) {
    print $id . ",";
}
print "\n";

function get_user_id($screenname) {
    print ".";
    global $twitter_keys, $current_key, $ratefree, $looped, $bin_name, $dbh, $users;
    $found = false;

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
        'count' => 1,
        'trim_user' => false,
        'exclude_replies' => false,
        'contributor_details' => false,
        'include_rts' => 0,
        'tweet_mode' => 'extended',
    );

    $params['screen_name'] = $screenname;

    $tmhOAuth->user_request(array(
        'method' => 'GET',
        'url' => $tmhOAuth->url('1.1/statuses/user_timeline'),
        'params' => $params
    ));

    if ($tmhOAuth->response['code'] == 200) {
        $tweets = json_decode($tmhOAuth->response['response'], true);

        // store in db
        $tweet_ids = array();
        foreach ($tweets as $tweet) {
            $t = new Tweet();
            $t->fromJSON($tweet);
            $user_id = $t->from_user_id;
            //print "found $user_id\n";
            $users[$screenname] = $user_id;
            $found = true;
            break;
        }
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
    if (!$found)
        print "no id found for $screenname\n";
}
