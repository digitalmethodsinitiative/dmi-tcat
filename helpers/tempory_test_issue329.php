<?php

/*
 * This is a test script to investigate the average length and total nr. of captured retweets inside follow bins after applying a fix for issue #329.
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
$date_compare_new = "created_at > '2018-09-16 12:00:00' AND created_at <= '2018-09-18 12:00:00'";

$data = getActiveFollowBins();
$followbins = array_keys($data);
$old_averages = 0; $old_totals = 0;
$new_averages = 0; $new_totals = 0;
foreach ($followbins as $bin) {
    $table = $bin . '_tweets';
    $sql = "SELECT AVG(CHAR_LENGTH(text)) AS avg FROM `$table` WHERE retweet_id IS NOT NULL AND $date_compare_old";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    $results = $rec->fetch(PDO::FETCH_ASSOC);
    $old_avg = floor($results['avg']);
    $old_averages += $results['avg'];

    $sql = "SELECT COUNT(id) AS cnt FROM `$table` WHERE retweet_id IS NOT NULL AND $date_compare_old";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    $results = $rec->fetch(PDO::FETCH_ASSOC);
    $old_total += $results['cnt'];
    $old_totals += $results['cnt'];

    $sql = "SELECT AVG(CHAR_LENGTH(text)) AS avg FROM `$table` WHERE retweet_id IS NOT NULL AND $date_compare_new";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    $results = $rec->fetch(PDO::FETCH_ASSOC);
    $new_avg = floor($results['avg']);
    $new_averages += $results['avg'];

    $sql = "SELECT COUNT(id) AS cnt FROM `$table` WHERE retweet_id IS NOT NULL AND $date_compare_new";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    $results = $rec->fetch(PDO::FETCH_ASSOC);
    $new_total += $results['cnt'];
    $new_totals += $results['cnt'];

    print "$bin lengths: $old_avg vs $new_avg\n";
    print "$bin totals: $old_total vs $new_total\n";
}
$old_averages /= count($followbins);
$new_averages /= count($followbins);

print "Overall average length: old " . floor($old_averages) . " vs new " . floor($new_averages) . "\n";
print "Overall total count: old $old_totals vs new $new_totals\n";
