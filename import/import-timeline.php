<?php
if($argc<1) die; // only run from command line
include_once('../config.php');
include_once('../common/functions.php');
include_once('../capture/common/functions.php');

// specify the name of the bin here 
$bin_name = '';
// specify dir with the user timelines (json)
$dir = '';

if (empty($bin_name))
    die("bin_name not set\n");
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

function process_json_file_timeline($filepath, $dbh) {
    global $tweets_processed, $tweets_failed, $tweets_success,
    $valid_timeline, $empty_timeline, $invalid_timeline, $populated_timeline,
    $total_timeline, $all_tweet_ids, $all_users, $bin_name;

    $total_timeline++;

    $filestr = file_get_contents($filepath);
    
    // sylvester stores multiple json exports in the same file,
    // in order to decode it we will need to split it into its respective individual exports
    $jsons = explode("}][{", $filestr);
    print count($jsons)." jsons found\n";
    
    foreach ($jsons as $json) {
        if (substr($json, 0, 2) != "[{")
            $json = "[{" . $json;
        if (substr($json, -2) != "}]")
            $json = $json . "}]";
        $timeline = json_decode($json);
        
        if (is_array($timeline)) {
            $valid_timeline++;
            if (!empty($timeline)) {
                $populated_timeline++;
            } else {
                $empty_timeline++;
            }
        } else {
            $invalid_timeline++;
        }

        foreach ($timeline as $tweet) {

            $t = Tweet::fromJSON($tweet);

            $all_users[] = $t->user->id;
            $all_tweet_ids[] = $t->id;

            $saved = $t->save($dbh, $bin_name);

            if ($saved) {
                $tweets_success++;
            } else {
                $tweets_failed++;
            }

            $tweets_processed++;
        }
    }
}

print "\n\n\n\n";
print "Number of tweets: " . count($all_tweet_ids) . "\n";
print "Unique tweets: " . count(array_unique($all_tweet_ids)) . "\n";
print "Unique users: " . count(array_unique($all_users)) . "\n";

print "Processed $tweets_processed tweets!\n";
print "Failed storing $tweets_failed tweets!\n";
print "Succesfully stored $tweets_success tweets!\n";
print "\n";
print "Total number of timelines: $total_timeline\n";
print "Valid timelines: $valid_timeline\n";
print "Invalid timelines: $invalid_timeline\n";
print "Populated timelines: $populated_timeline\n";
print "Empty timelines: $empty_timeline\n";

?>
