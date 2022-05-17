#!/usr/bin/php5
<?php
/*
 * Script to quickly collect TCAT bin metadata and print the date of the most recent collected tweet from all bins.
 * Creates json object with info.
 * The api/bin-stats.php collects similar information. This aims for accuracy over speed and could be used to create snapshots via ansible or cron or whatever.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../common/functions.php';
require_once __DIR__ . '/../capture/common/functions.php';
require_once __DIR__ . '/../analysis/common/config.php';      /* to get global variable $resultsdir */

$storedir = __DIR__ . "/../analysis/$resultsdir";
$filename = $storedir . date("Y-m-d-His")."_TCAT_metadata.json";

if (!is_writable($storedir)) {
    die("Error: directory not writable: $storedir\n");
}

global $dbuser, $dbpass, $database, $hostname;
$dbh = refresh_dbh_connection(null, $hostname, $database, $dbuser, $dbpass);

$queryBins = getAllbins();

$last_tweet_date = null;
$totalTweets = 0;
$collectedBins = array();
foreach ($queryBins as $bin) {
    // Create object for metadata
    $binObj = array();
    $binObj['name'] = $bin;

    // Collect bin comments
    $sql = "SELECT id, type, active, access, comments FROM tcat_query_bins WHERE querybin=:querybinname";
    $q = $dbh->prepare($sql);
    $q->bindParam(':querybinname', $bin, PDO::PARAM_STR);
    $q->execute();
    $row = $q->fetch(PDO::FETCH_ASSOC);
    $binObj['bin_id'] = $row['id'];
    $binObj['type'] = $row['type'];
    $binObj['active_status'] = $row['active'];
    $binObj['access'] = $row['access'];
    $binObj['comments'] = $row['comments'];

    // Collect timestamp of first and last collected tweet
    $sql = "SELECT MIN(created_at) AS first_tweet, MAX(created_at) AS last_tweet FROM ". $bin ."_tweets";
    $q = $dbh->prepare($sql);
    if ($q->execute()) {
        $row = $q->fetch(PDO::FETCH_ASSOC);
        $binObj['first_collected_tweet_date'] = $row['first_tweet'];
        $binObj['last_collected_tweet_date'] = $row['last_tweet'];
    } else {
        $binObj['first_collected_tweet_date'] = null;
        $binObj['last_collected_tweet_date'] = null;
    }

    // Get number of tweets
    $sql = "SELECT count(id) AS count FROM " .$bin . "_tweets";
    $res = $dbh->prepare($sql);
    if ($res->execute()) {
        $result = $res->fetch();
        $binObj['num_of_tweets'] = $result['count'];
    } else {
        $binObj['num_of_tweets'] = 0;
    }
    $totalTweets += $binObj['num_of_tweets'];

    // Get phrases
    $sql = "SELECT phrase FROM tcat_query_phrases WHERE id IN ( SELECT phrase_id FROM tcat_query_bins_phrases WHERE querybin_id = :binid)";
    $res = $dbh->prepare($sql);
    $res->bindParam(':binid', $$binObj['bin_id'], PDO::PARAM_INT);
    $binObj['phrases'] = $phrases ? $phrases->fetchAll(\PDO::FETCH_COLUMN, 0) : null;
    // Or get users
    $sql = "SELECT id, user_name FROM tcat_query_users WHERE id IN ( SELECT user_id FROM tcat_query_bins_users WHERE querybin_id = :binid)";
    $res = $dbh->prepare($sql);
    $res->bindParam(':binid', $$binObj['bin_id'], PDO::PARAM_INT);
    $binObj['user_ids'] = array();
    $binObj['user_names'] = array();
    if ($res->execute()) {
        $row = $q->fetch(PDO::FETCH_ASSOC);
        $binObj['user_ids'][] = $row['id'];
        $binObj['user_names'][] = $row['user_name'];
    }

    // Add bin object for json file
    $collectedBins[] = $binObj;

    print "Bin $bin most recent tweet collected at ". strval($binObj['last_collected_tweet_date']) ."\n";

    // Check for most recent tweet
    if (strtotime($binObj['last_collected_tweet_date']) > $last_tweet_date) {
        $last_tweet_date = strtotime($binObj['last_collected_tweet_date']);
    }
}

$git_info = getGitLocal();
$export_json = array(
    'creation_date' => date("Y-m-d H:i:s"),
    'total_tweets' => $totalTweets,
    'most_recent_tweet' => date('Y-m-d H:i:s', $last_tweet_date),
    'filename' =>  $filename,
    'current_git_info' => $git_info,
    'bins' => $collectedBins,
);

$fp = fopen($filename, 'w');
fwrite($fp, json_encode($export_json));
fclose($fp);

print "JSON with TCAT metadata saved on disk: $filename\n";
print "Most recent tweet was collected at ".date('Y-m-d H:i:s', $last_tweet_date)."\n";

/**
 * Closes an existing database connection and establishes a new one.
 */
function refresh_dbh_connection($dbh, $hostname, $database, $dbuser, $dbpass, $buffered=false) {
    // setting an active database connection to null closes the connection
    $dbh = null;
    // create a new PDO connection
    $dbh = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8mb4", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($buffered) {
        // This bad-boy should reduce memory usage significantly
        $dbh->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    }

    return $dbh;
}
