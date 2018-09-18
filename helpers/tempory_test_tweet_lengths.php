<?php

/*
 * This is a test script to investigate average the lengths of captured retweets inside follow bins after applying a fix for issue #329.
 *
 * Edit the subqueries in $date_compare_old and $date_compare_new to define the time periods to compare.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../common/functions.php';
require_once __DIR__ . '/../common/constants.php';
require_once __DIR__ . '/../capture/common/functions.php';

global $dbh;
$dbh = pdo_connect();

$date_compare_old = "created_at >= '2018-09-16 12:00:00' AND created_at <= '2018-09-17 12:00:00'";
$date_compare_new = "created_at > '2018-09-16 12:00:00'";

$data = getActiveFollowBins();
$followbins = array_keys($data);
$old_averages = 0;
$new_averages = 1;
foreach ($followbins as $bin) {
    $table = $bin . '_tweets';
    $sql = "SELECT AVG(CHAR_LENGTH(text)) AS avg FROM `$table` WHERE retweet_id IS NOT NULL AND $date_compare_old";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    $results = $rec->fetch(PDO::FETCH_ASSOC);
    $old_avg = floor($results['avg']);
    $old_averages += $results['avg'];
    $sql = "SELECT AVG(CHAR_LENGTH(text)) AS avg FROM `$table` WHERE retweet_id IS NOT NULL AND $date_compare_new";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    $results = $rec->fetch(PDO::FETCH_ASSOC);
    $new_avg = floor($results['avg']);
    $new_averages += $results['avg'];
    print "$bin: $old_avg vs $new_avg\n";
}
$old_averages /= count($followbins);
$new_averages /= count($followbins);
print "Overall averages: " . floor($old_averages) . " vs " . floor($new_averages) . "\n";
