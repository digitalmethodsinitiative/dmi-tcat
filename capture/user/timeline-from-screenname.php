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

$screennames_list = array(); // bin_name => screen_names


// ----- connection -----
$dbh = pdo_connect();
$ratefree = $current_key = $looped = 0;

foreach ($screennames_list as $bin_name => $screennames) {

    print "\n\nStarting $bin_name\n";

    print "\nGetting ids\n";

    $mapped = map_screen_names_to_ids($screennames);
    $users = array_values($mapped);

    print "found (" . count($users) . "/" . count($screennames) . "):";

    $user_ids = $users;
    $list_name = "";
    $type = 'follow';

    $querybin_id = queryManagerBinExists($bin_name);

    $ratefree = $current_key = $looped = 0;

    print "Creating bin $bin_name\n";
    create_bin($bin_name, $dbh);
    queryManagerCreateBinFromExistingTables($bin_name, $querybin_id, $type, $user_ids);

    $tweetQueue = new TweetQueue();
    print "Retrieving timelines\n";
    foreach ($user_ids as $user_id) {
        get_timeline($user_id, "user_id");
    }

    if ($tweetQueue->length() > 0) {
        $tweetQueue->insertDB();
        queryManagerSetPeriodsOnCreation($bin_name);
    }

    if ($type == 'follow') {
        /*
         * We want to be able to track our user ids in the future; therefore we must set the endtimes to NOW() for this particular set.
         * The reason: when TCAT is asked to start a bin via the User Interface, it starts those users who share a maximum endtime (i.e. the most recently used set).
         */
        $sql = "SELECT id FROM tcat_query_bins WHERE querybin = :bin_name";
        $rec = $dbh->prepare($sql);
        $rec->bindParam(":bin_name", $bin_name, PDO::PARAM_STR);
        if ($rec->execute() && $rec->rowCount() > 0) {
            if ($res = $rec->fetch()) {
                $querybin_id = $res['id'];
                $ids_as_string = implode(",", $user_ids);
                $sql = "UPDATE tcat_query_bins_users SET endtime = NOW() WHERE querybin_id = :querybin_id AND user_id in ( $ids_as_string );";
                $rec = $dbh->prepare($sql);
                $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
                $rec->execute();
            }
        }
    }

    // NOTE: This is certainly only for the TCAT 7 script, not something we always want to do, but it forces all follow bins to keep running
    $sql = "update tcat_query_bins_users set endtime = '0000-00-00 00:00:00'";
    $rec = $dbh->prepare($sql);
    $rec->execute();
}

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
        'include_rts' => 1,
        'tweet_mode' => 'extended',
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
                print $t->created_at . "\n";
                //print ".";
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
