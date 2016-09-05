<?php

if ($argc < 1)
    die; // only run from command line

include_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../common/constants.php';
include_once __DIR__ . '/../common/functions.php';
include_once __DIR__ . '/../capture/common/functions.php';

// specify the name of the bin here 
$bin_name = '';
// specify dir with the user timelines (json)
$dir = '';

if (empty($bin_name))
    die("bin_name not set\n");

if (dbserver_has_utf8mb4_support() == false) {
    die("DMI-TCAT requires at least MySQL version 5.5.3 - please upgrade your server\n");
}

$querybin_id = queryManagerBinExists($bin_name);

$dbh = pdo_connect();
create_bin($bin_name, $dbh);
queryManagerCreateBinFromExistingTables($bin_name, $querybin_id, 'import gnip');

$all_files = glob("$dir/*");

$all_users = $all_tweet_ids = array();
$tweets_processed = $tweets_failed = $tweets_success = 0;
$count = count($all_files);
$c = $count;

for ($i = 0; $i < $count; ++$i) {
    $filepath = $all_files[$i];
    process_json_file_timeline($filepath, $dbh);
    print $c-- . "\n";
}

function process_json_file_timeline($filepath, $dbh) {
    print $filepath . "\n";
    global $tweets_processed, $tweets_failed, $tweets_success, $all_tweet_ids, $all_users, $bin_name;

    $tweetQueue = new TweetQueue();

    ini_set('auto_detect_line_endings', true);

    $handle = @fopen($filepath, "r");
    if ($handle) {
        while (($buffer = fgets($handle, 40960)) !== false) {
            $buffer = trim($buffer);
            if (empty($buffer))
                continue;
            $tweet = json_decode($buffer);

            $buffer = "";

            $t = Tweet::fromGnip($tweet);

            if ($t === false)
                continue;

            if (!$t->isInBin($bin_name)) {
                $all_users[] = $t->from_user_id;
                $all_tweet_ids[] = $t->id;

                $tweetQueue->push($t, $bin_name);
                
                if ($tweetQueue->length() > 100) {
                    $tweetQueue->insertDB();
                }

                $tweets_processed++;
            }

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

queryManagerSetPeriodsOnCreation($bin_name);

print "\n\n\n\n";
print "Number of tweets: " . count($all_tweet_ids) . "\n";
print "Unique tweets: " . count(array_unique($all_tweet_ids)) . "\n";
print "Unique users: " . count(array_unique($all_users)) . "\n";

print "Processed $tweets_processed tweets!\n";
//print "Failed storing $tweets_failed tweets!\n";
//print "Succesfully stored $tweets_success tweets!\n";
print "\n";
?>
