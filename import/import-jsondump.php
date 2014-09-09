<?php

if ($argc < 1)
    die; // only run from command line
include_once('../config.php');
include_once('../common/functions.php');
include_once('../capture/common/functions.php');

// specify the name of the bin here 
$bin_name = '';
// specify dir with the user timelines (json)
$dir = '';
// set type of dump ('import follow' or 'import track')
$type = 'import track';
// if 'import track', specify keywords for which data was captured
$queries = array();

if (empty($bin_name))
    die("bin_name not set\n");
$querybin_id = queryManagerBinExists($bin_name);

$dbh = pdo_connect();
create_bin($bin_name, $dbh);

$all_files = glob("$dir/*.json");

global $tweets_processed, $tweets_failed, $tweets_success,
 $valid_timeline, $empty_timeline, $invalid_timeline, $populated_timeline,
 $total_timeline, $all_users, $all_tweet_ids;

$all_users = $all_tweet_ids = array();
$tweets_processed = $tweets_failed = $tweets_success = $valid_timeline = $empty_timeline = $invalid_timeline = $populated_timeline = $total_timeline = 0;
$count = count($all_files);
$c = $count;

for ($i = 0; $i < $count; ++$i) {
    $filepath = $all_files[$i];
    process_json_file_timeline($filepath, $dbh);
    print $c-- . "\n";
}

queryManagerCreateBinFromExistingTables($bin_name, $querybin_id, $type, $queries);

function process_json_file_timeline($filepath, $dbh) {
    global $tweets_processed, $tweets_failed, $tweets_success,
    $valid_timeline, $empty_timeline, $invalid_timeline, $populated_timeline,
    $total_timeline, $all_tweet_ids, $all_users, $bin_name;

    $tweetQueue = new TweetQueue();

    $total_timeline++;

    ini_set('auto_detect_line_endings', true);

    $handle = @fopen($filepath, "r");
    if ($handle) {
        while (($buffer = fgets($handle, 40960)) !== false) {
            $tweet = json_decode($buffer);
            //var_export($tweet); print "\n\n";
            $buffer = "";

            $t = new Tweet();
            $t->fromJSON($tweet);
            $tweetQueue->push($tweet, $bin_name);
            if ($tweetQueue->length() > 100) {
                $tweetQueue->insertDB();
            }

            $all_users[] = $t->user->id;
            $all_tweet_ids[] = $t->id;

            $tweets_processed++;

            print ".";
        }
        if (!feof($handle)) {
            echo "Error: unexpected fgets() fail\n";
        }
        fclose($handle);
    }

    if ($tweetQueue->length() > 0) {
        $tweetQueue->insertDB();
    }
}

print "\n\n\n\n";
print "Number of tweets: " . count($all_tweet_ids) . "\n";
print "Unique tweets: " . count(array_unique($all_tweet_ids)) . "\n";
print "Unique users: " . count(array_unique($all_users)) . "\n";

print "Processed $tweets_processed tweets!\n";
//print "Failed storing $tweets_failed tweets!\n";
//print "Succesfully stored $tweets_success tweets!\n";
print "\n";
print "Total number of timelines: $total_timeline\n";
print "Valid timelines: $valid_timeline\n";
print "Invalid timelines: $invalid_timeline\n";
print "Populated timelines: $populated_timeline\n";
print "Empty timelines: $empty_timeline\n";
?>
