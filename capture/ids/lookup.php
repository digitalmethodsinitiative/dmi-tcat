<?php

if ($argc < 1)
    die; // only run from command line
// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);
include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../common/constants.php';
include_once __DIR__ . '/../../common/functions.php';
include_once __DIR__ . '/../../capture/common/functions.php';

require __DIR__ . '/../../capture/common/tmhOAuth/tmhOAuth.php';


// DEFINE LOOKUP PARAMETERS HERE

$bin_name = '';            // name of the bin
$idfile = '';              // path to the input file name. the file must contain only a tweet ID on every line
$type = 'lookup';          // specify 'lookup'

if (empty($bin_name))
    die("bin_name not set\n");
if (empty($idfile))
    die("idfile not set\n");

if (dbserver_has_utf8mb4_support() == false) {
    die("DMI-TCAT requires at least MySQL version 5.5.3 - please upgrade your server\n");
}

$querybin_id = queryManagerBinExists($bin_name);
$idlist = preg_split('/\R/', file_get_contents($idfile));
if (!is_array($idlist) || empty($idlist)) {
    die("idfile invalid\n");
}
if (file_exists($idfile . ".inaccessible")) {
    print "A file called " . $idfile . ".inaccessible" . " already exists on disk. We need this file to store information. Please back up this file or remove it manually. Exiting.\n";
    exit(1);
}
$count_fullset = count($idlist);

$all_users = $all_tweet_ids = array();

// ----- connection -----
$dbh = pdo_connect();
create_bin($bin_name, $dbh);

/* filter out the ids already in the database */
$ids_in_db = array();
for ($i = 0; $i < sizeof($idlist); $i += 3000) {
    $query = "select id from $bin_name" . '_tweets where id in ( ' . $idlist[$i];
    $n = $i + 1;
    while ($n < $i + 3000 && $n < sizeof($idlist)) {
        if (!array_key_exists($n, $idlist) || !is_numeric($idlist[$n])) break;
        $query .= ", " . $idlist[$n];
        $n++;
    }
    $query .= ")";
    $rec = $dbh->prepare($query);
    $rec->execute();
    $results = $rec->fetchAll(PDO::FETCH_COLUMN);
    foreach ($results as $f => $v) {
        $ids_in_db[] = $v;
    }
}
$orig_size = count($idlist);
$idlist = array_diff($idlist, $ids_in_db);
$idlist = array_values($idlist);			// re-index is needed
$new_size = count($idlist);
$count_existed = 0;
$count_missing = 0;
if ($new_size < $orig_size) {
    $count_existed = $orig_size - $new_size;
    print "skipping $count_existed tweets from id list because they are already in our database\n";
}

$tweetQueue = new TweetQueue();

queryManagerCreateBinFromExistingTables($bin_name, $querybin_id, 'import tweetset');

search($idlist);

queryManagerSetPeriodsOnCreation($bin_name);

$retries = 0;

print "\n" . str_repeat("=", 83) . "\n\n";

printf("Total number of tweets in your ID file:                                    %8d\n", $count_fullset);
printf("Number of new tweets inserted into the bin:                                %8d\n", $count_fullset - $count_existed - $count_missing);
printf("Number of tweets skipped because they already existed in bin:              %8d\n", $count_existed);
printf("Number of tweets skipped because they are no longer accessible:            %8d\n", $count_missing);

if (file_exists($idfile . ".inaccessible")) {
    printf("The ids of tweets who can not (or no longer) be accessed have been written to file: " . $idfile . ".inaccessible" . "\n");
}

function search($idlist) {
    global $twitter_keys, $current_key, $all_users, $all_tweet_ids, $bin_name, $idfile, $dbh, $tweetQueue, $count_missing;

    $keyinfo = getRESTKey(0);
    $current_key = $keyinfo['key'];
    $ratefree = $keyinfo['remaining'];

    print "\ncurrent key $current_key ratefree $ratefree\n";

    $tmhOAuth = new tmhOAuth(array(
                'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
                'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
                'token' => $twitter_keys[$current_key]['twitter_user_token'],
                'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
            ));

    // by hundred
    for ($i = 0; $i < sizeof($idlist); $i += 100) {

	    if ($ratefree <= 0 || $ratefree % 10 == 0) {
            print "\n";
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

        $verify = array();      // used to track missing tweets
        $q = $idlist[$i];
        $verify[] = $q;
        $n = $i + 1;
        while ($n < $i + 100) {
            if (!isset($idlist[$n])) break;
            $q .= "," . $idlist[$n];
            $verify[] = $idlist[$n];
            $n++;
        }

        $params = array(
            'id' => $q,
            'tweet_mode' => 'extended',
        );

        $code = $tmhOAuth->user_request(array(
            'method' => 'GET',
            'url' => $tmhOAuth->url('1.1/statuses/lookup'),
            'params' => $params
                ));

	    $ratefree--;

        $reset_connection = false;

        if ($tmhOAuth->response['code'] == 200) {
            $data = json_decode($tmhOAuth->response['response'], true);

            if (is_array($data) && empty($data)) {
                // all tweets in set are deleted
                $count_missing += count($verify);
                if (file_exists($idfile . ".inaccessible")) {
                    file_put_contents($idfile . ".inaccessible", "\n" . implode("\n", $verify), FILE_APPEND);
                } else {
                    file_put_contents($idfile . ".inaccessible", implode("\n", $verify), FILE_APPEND);
                }
                continue;
            }

            $tweets = $data;
            $tweet_ids = array();

            foreach ($tweets as $tweet) {

                $t = new Tweet();
                $t->fromJSON($tweet);
                if (!$t->isInBin($bin_name)) {

                    $all_users[] = $t->from_user_id;
                    $all_tweet_ids[] = $t->id;
                    $tweet_ids[] = $t->id;
                    
                    $tweetQueue->push($t, $bin_name);

                }
                print ".";
            }
            $diff = array_diff($verify, $tweet_ids);
            $count_missing += count($diff);
            if (count($diff) > 0) {
                if (file_exists($idfile . ".inaccessible")) {
                    file_put_contents($idfile . ".inaccessible", "\n" . implode("\n", $diff), FILE_APPEND);
                } else {
                    file_put_contents($idfile . ".inaccessible", implode("\n", $diff), FILE_APPEND);
                }
            }
            sleep(1);
            $retries = 0;   // reset retry counter on success
        } else if ($retries < 4 && $tmhOAuth->response['code'] == 503) {
            /* this indicates problems on the Twitter side, such as overcapacity. we slow down and retry the connection */
            print "!";
            sleep(7);
            $i--;  // rewind
            $retries++;
            $reset_connection = true;
        } else if ($retries < 4) {
            print "\n"; 
            print "Failure with code " . $tmhOAuth->response['response']['code'] . "\n";
            var_dump($tmhOAuth->response['response']['info']);
            var_dump($tmhOAuth->response['response']['error']);
            var_dump($tmhOAuth->response['response']['errno']);
            print "The above error may not be permanent. We will sleep and retry the request.\n";
            sleep(7);
            $i--;  // rewind
            $retries++;
            $reset_connection = true;
        } else {
            print "\n";
            print "Permanent error when querying the Twitter API. Please investigate the error output. Now stopping.\n";
            exit(1);
        }

        if ($reset_connection) {
            $tmhOAuth = new tmhOAuth(array(
                        'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
                        'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
                        'token' => $twitter_keys[$current_key]['twitter_user_token'],
                        'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
                    ));
            $reset_connection = false;
        } else {
            $tweetQueue->insertDB();
        }

    }
}
?>
