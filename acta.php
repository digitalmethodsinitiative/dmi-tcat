<?php

/*
 * This script polarizes tweets by looking for tweets containing a specific 'charged' host name.
 * The script will output a variety of stats as well as the polarized tweets.
 * 
 * @author "Erik Borra" <erik@digitalmethods.net>
 */

include_once('common/config.php');
include_once('common/functions.php');
db_connect($hostname, $db_user, $db_pass, $database); 

$start = strtotime("19 May 2012");
$end = strtotime("22 June 2012");
$dataset = "z_501";
$datadir = "files";
$charges = loadCharges("$datadir/acta_charges.csv");

//getTweetUrls($dataset, $start, $end);
polarizeTweets($dataset, $charges, $start, $end);

/*
 * Compare the tweeted hosts with the ones in our pro / con / neutral lists in acta_polarizingUrls.php
 * Output stats and polarized tweets
 */

function polarizeTweets($dataset, $charges, $start, $end) {

    global $pro, $con, $neutral, $datadir;
    $pros = $cons = $neutrals = $proTweetIds = $conTweetIds = $neutralTweetIds = array();

    // see whether hosts of specific leaning are found in specific tweet
    // @todo, it might be that a tweet contains URLs of different polarization, this will lead to the tweet appearing in both polarizations
    $file = file($datadir . '/acta_urls.csv');
    foreach ($file as $f) {
        $e = explode(",", $f);
        $host = strtolower(trim($e[4]));
        $hosts[] = $host;
        foreach ($charges['pro'] as $p) {
            if (strstr($host, $p) !== false) {
                $pros[] = $p;
                $proTweetIds[$e[0]] = $host;
            }
        }
        foreach ($charges['con'] as $c) {
            if (strstr($host, $c) !== false) {
                $cons[] = $c;
                $conTweetIds[$e[0]] = $host;
            }
        }
        foreach ($charges['neutral'] as $n) {
            if (strstr($host, $n) !== false) {
                $neutrals[] = $n;
                $neutralTweetIds[$e[0]] = $host;
            }
        }
    }

    /*
     * Time for stats
     */

    // get total nr of tweets
    $sql = "SELECT count(id) as count FROM $dataset";
    if ($start != 0 && $end != 0)
        $sql .= " WHERE time >= $start AND time <= $end";
    $rec = mysql_query($sql);
    $res = mysql_fetch_assoc($rec);
    $nrOfTweets = $res['count'];

    // count nr of hosts found in all tweets
    $acvhosts = array_count_values($hosts);
    $hostCount = count($acvhosts);
    $tweetsWithUrls = array_sum($acvhosts);

    // count nr of hosts per pole
    $acvpros = array_count_values($pros);
    $hostspros = count($acvpros);
    $acvcons = array_count_values($cons);
    $hostscons = count($acvcons);
    $acvneutrals = array_count_values($neutrals);
    $hostsneutrals = count($acvneutrals);
    $hoststotal = $hostspros + $hostscons + $hostsneutrals;
    $hostspercentage = round(($hoststotal / $hostCount) * 100, 2);

    // count nr of tweets per pole
    $tweetspros = array_sum($acvpros);
    $tweetscons = array_sum($acvcons);
    $tweetsneutrals = array_sum($acvneutrals);
    $tweetstotal = $tweetspros + $tweetscons + $tweetsneutrals;
    $tweetsUrlPercentage = round(($tweetstotal / $tweetsWithUrls) * 100, 2);
    $tweetsAllPercentage = round(($tweetstotal / $nrOfTweets) * 100, 2);

    /*
     * Time for printing
     */

    print "$nrOfTweets tweets in total\n";
    print ($nrOfTweets - $tweetsWithUrls) . " tweets without URLs\n";
    print $tweetsWithUrls . " tweets with URLs (".round(($tweetsWithUrls / $nrOfTweets) * 100, 2)."%)\n";
    print "\n";
    print $hostCount . " distinct hosts found in tweets\n";
    print "$hostspros pro acta hosts found\n";
    print "$hostscons anti acta hosts found\n";
    print "$hostsneutrals neutral acta hosts found\n";
    print "$hoststotal polarized hosts in total\n";
    print "$hostspercentage% of hosts found in tweets could be used for charging\n";
    print "\n";

    print "$tweetspros pro acta tweets found\n";
    print "$tweetscons anti acta tweets found\n";
    print "$tweetsneutrals neutral acta tweets found\n";
    print "$tweetstotal polarized tweets in total\n";
    print "$tweetsUrlPercentage% of tweets with URLs were charged\n";
    print "$tweetsAllPercentage% of all tweets were charged\n";
    print "\n";

    // write host + frequency to file
    arsort($acvhosts);
    $out = "";
    foreach ($acvhosts as $a => $v)
        $out .= "$a,$v\n";
    file_put_contents($datadir . '/acta_hosts.csv', $out);

    // write host count and pole
    $out = "";
    arsort($acvpros);
    arsort($acvcons);
    arsort($acvneutrals);
    foreach ($acvpros as $host => $v)
        $out .= "$host,$v,pro acta\n";
    foreach ($acvcons as $host => $v)
        $out .= "$host,$v,con acta\n";
    foreach ($acvneutrals as $host => $v)
        $out .= "$host,$v,neutral acta\n";
    file_put_contents($datadir . '/acta_polarizedHosts.csv', $out);
    die;
    //  write tweets per pole
    getTweets($dataset, $conTweetIds, "acta_conTweets");
    getTweets($dataset, $proTweetIds, "acta_proTweets");
    getTweets($dataset, $neutralTweetIds, "acta_neutralTweets");
    getNonPolarizedTweets($dataset, array_merge($conTweetIds, $proTweetIds, $neutralTweetIds), "acta_nonPolarizedTweets");
}

/*
 *  get tweets with URLs
 */

function getTweetUrls($dataset, $start = 0, $end = 0) {
    global $datadir;
    if (!$start && !$end)
        $sql = "SELECT tweetid, tweetedurl, targeturl, tweetedhost, targethost FROM urls WHERE dbname = 'yourTwapperKeeper' AND tablename = '$dataset'";
    else {
        $sql = "SELECT u.tweetid, u.tweetedurl, u.targeturl, u.tweetedhost, u.targethost FROM urls u, $dataset t WHERE u.dbname = 'yourTwapperKeeper' AND u.tablename = '$dataset' AND u.tweetid = t.id";
        if ($start != 0 && is_int($start))
            $sql .= " AND t.time >= $start";
        if ($end != 0 && is_int($end))
            $sql .= " AND t.time <= $end";
    }
    print $sql . "\n";
    $rec = mysql_query($sql);
    if ($rec) {
        $handle = fopen($datadir . "/acta_urls.csv", "w");
        while ($res = mysql_fetch_assoc($rec)) {
            fputcsv($handle, $res);
            print ".";
        }
        fclose($handle);
        print "\ndone\n";
    }
}

/*
 *  get specific tweets identified by their id
 */

function getTweets($dataset, $tweetIds, $name) {
    global $datadir;
    $sql = "SELECT id,text FROM $dataset WHERE id IN (" . implode(",", array_keys($tweetIds)) . ")";
    $rec = mysql_query($sql);
    if ($rec) {
        $handle = fopen($datadir . "/$name.csv", "w");
        while ($res = mysql_fetch_assoc($rec)) {
            $out = $res;
            $out['host'] = $tweetIds[$res['id']];
            fputcsv($handle, $out);
            print "_";
        }
        fclose($handle);
        print "\nwrote tweets\n";
    }
}

/*
 * get all the tweets for which we have no id (those which are not polarized)
 */

function getNonPolarizedTweets($dataset, $tweetIds, $name) {
    global $datadir;
    $sql = "SELECT id,text FROM $dataset WHERE id NOT IN (" . implode(",", array_keys($tweetIds)) . ")";
    $rec = mysql_query($sql);
    if ($rec) {
        $handle = fopen($datadir . "/$name.csv", "w");
        while ($res = mysql_fetch_assoc($rec)) {
            $out = $res;
            $out['host'] = $tweetIds[$res['id']];
            fputcsv($handle, $out);
            print "_";
        }
        fclose($handle);
        print "\nwrote tweets\n";
    }
}

/*
 * Read charges URL,{pro,con,neutral} from file
 */

function loadCharges($filename) {
    if (!file_exists($filename))
        die("$filename does not exist\n");
    $charges = array();
    $file = file($filename);
    foreach ($file as $f) {
        $s = explode(",", $f);
        $charges[trim($s[1])][] = trim($s[0]);
    }
    return $charges;
}

?>
