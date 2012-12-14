<?php

/*
 * This script polarizes tweets by looking for tweets containing a specific 'charged' host name.
 * The script will output a variety of stats as well as the polarized tweets.
 * 
 * @author "Erik Borra" <erik@digitalmethods.net>
 */

include_once('common/config.php');
include_once('common/functions.php');
db_connect($hostname, $dbuser, $dbpass, $database); 

$start = "2012-05-19 00:00:00";
$end = "2012-06-22 23:59:59";
$dataset = "z_501";
$dataname = "acta";
$datadir = "files";
$includeRetweets = TRUE;
$charges = loadCharges($datadir . '/' . $dataname . "_charges.csv");    // List of {host,charge}-combinations, where charge can be 'pro', 'con' or 'neutral'
//getTweetUrls($dataset, $start, $end);
polarizeTweets($dataset, $charges, $start, $end, $includeRetweets);

/*
 * Compare the tweeted hosts with the ones in our pro / con / neutral lists in files/$dataname_charges
 * Output stats and polarized tweets
 */

function polarizeTweets($dataset, $charges, $start, $end, $includeRetweets) {

    global $pro, $con, $neutral, $datadir, $dataname;
    $pros = $cons = $neutrals = $proTweetIds = $conTweetIds = $neutralTweetIds = array();

    // see whether hosts of specific leaning are found in specific tweet
    // @todo, it might be that a tweet contains URLs of different polarization, this will lead to the tweet appearing in both polarizations
    if ($includeRetweets)
        $file = file($datadir . '/' . $dataname . '_urls_all.csv');
    else
        $file = file($datadir . '/' . $dataname . '_urls_noRT.csv');
    // @todo: ge.com and e.g. engage.com
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
    $sql = "SELECT count(id) as count FROM ".$dataset."_tweets";
    if ($start != 0 && $end != 0)
        $sql .= " WHERE created_at >= '$start' AND created_at <= '$end'";
    if (!$includeRetweets)
        $sql .= " AND lower(text) NOT LIKE 'rt%'";  // @todo, also unique the texts (native retweet)
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
    print $tweetsWithUrls . " tweets with URLs (" . round(($tweetsWithUrls / $nrOfTweets) * 100, 2) . "%)\n";
    print "\n";
    print $hostCount . " distinct hosts found\n";
    // @todo, add count of number of hosts with charge
    print "$hostspros pro $dataname hosts found\n";
    print "$hostscons anti $dataname hosts found\n";
    print "$hostsneutrals neutral $dataname hosts found\n";
    print "$hoststotal polarized hosts in total\n";
    print "$hostspercentage% of hosts found in tweets could be used for charging\n";
    print "\n";

    print "$tweetspros pro $dataname tweets found\n";
    print "$tweetscons anti $dataname tweets found\n";
    print "$tweetsneutrals neutral $dataname tweets found\n";
    print "$tweetstotal polarized tweets in total\n";
    print "$tweetsUrlPercentage% of tweets with URLs were charged\n";
    print "$tweetsAllPercentage% of all tweets were charged\n";
    print "\n";

    // write host + frequency to file
    arsort($acvhosts);
    $out = "";
    foreach ($acvhosts as $a => $v)
        $out .= "$a,$v\n";
    file_put_contents($datadir . '/' . $dataname . ($includeRetweets ? "" : "_noRT") . '_hostsInTweets.csv', chr(239) . chr(187) . chr(191) . $out);

    // write host count and pole
    $out = "";
    arsort($acvpros);
    arsort($acvcons);
    arsort($acvneutrals);
    foreach ($acvpros as $host => $v)
        $out .= "$host,$v,pro\n";
    foreach ($acvcons as $host => $v)
        $out .= "$host,$v,con\n";
    foreach ($acvneutrals as $host => $v)
        $out .= "$host,$v,neutral\n";
    file_put_contents($datadir . '/' . $dataname . ($includeRetweets ? "" : "_noRT") . '_polarizedHosts.csv', chr(239) . chr(187) . chr(191) . $out);

    //  write tweets per pole
    getTweets($dataset, $conTweetIds, $dataname . ($includeRetweets ? "" : "_noRT") . "_conTweets");
    getTweets($dataset, $proTweetIds, $dataname . ($includeRetweets ? "" : "_noRT") . "_proTweets");
    getTweets($dataset, $neutralTweetIds, $dataname . ($includeRetweets ? "" : "_noRT") . "_neutralTweets");
    getNonPolarizedTweets($dataset, array_merge($conTweetIds, $proTweetIds, $neutralTweetIds), $dataname . ($includeRetweets ? "" : "_noRT") . "_nonPolarizedTweets");

    // get coword and word frequencies
    coword($datadir . "/" . $dataname . ($includeRetweets ? "" : "_noRT") . "_conTweets.csv");
    coword($datadir . "/" . $dataname . ($includeRetweets ? "" : "_noRT") . "_proTweets.csv");
    coword($datadir . "/" . $dataname . ($includeRetweets ? "" : "_noRT") . "_neutralTweets.csv");
    //simpleCoword($datadir . "/" . $dataname . ($includeRetweets ? "" : "_noRT") ."_nonPolarizedTweets.csv"); // will blow up ur computer
    
    
    /* calculate polarization */
    // load frequencies
    $pro = file($datadir . "/" . $dataname . ($includeRetweets ? "" : "_noRT") . "_proTweets_frequencies.csv");
    foreach ($pro as $p) {
        $e = explode(",", $p);
        $frequency['pro'][$e[0]] = trim($e[1]);
        $words[] = $e[0];
    }
    $con = file($datadir . "/" . $dataname . ($includeRetweets ? "" : "_noRT") . "_conTweets_frequencies.csv");
    foreach ($con as $c) {
        $e = explode(",", $c);
        $frequency['con'][$e[0]] = trim($e[1]);
        $words[] = $e[0];
    }
    $words = array_unique($words);
    $volume['pro'] = array_sum($frequency['pro']);
    $volume['con'] = array_sum($frequency['con']);
    $handle = fopen($datadir . "/" . $dataname . ($includeRetweets ? "" : "_noRT") . "_polarization.csv", "w");
    // calculate leaning, just as in Weber, Garimella and Borra (2012), equation 1
    foreach ($words as $word) {
        if (!isset($frequency['pro'][$word]))
            $frequency['pro'][$word] = 0;
        if (!isset($frequency['con'][$word]))
            $frequency['con'][$word] = 0;
        $leaning[$word] = ($frequency['pro'][$word] / $volume['pro']) + (2 / ($volume['pro'] + $volume['con'])) / (($frequency['pro'][$word] / $volume['pro']) + ($frequency['con'][$word] / $volume['con']) + (4 / ($volume['pro'] + $volume['con'])));
        fwrite($handle, $word . "," . $leaning[$word] . "\n");
    }
    fclose($handle);
}

/*
 *  get tweets with URLs
 */

function getTweetUrls($dataset, $start = 0, $end = 0) {
    global $datadir, $dataname, $includeRetweets;

    if ($includeRetweets && !$start && !$end)
        $sql = "SELECT tweetid, tweetedurl, targeturl, tweetedhost, targethost FROM urls WHERE dbname = 'yourTwapperKeeper' AND tablename = '$dataset'";
    else {
        $sql = "SELECT u.tweetid, u.tweetedurl, u.targeturl, u.tweetedhost, u.targethost FROM urls u, $dataset t WHERE u.dbname = 'yourTwapperKeeper' AND u.tablename = '$dataset' AND u.tweetid = t.id";

        if ($start != 0 && is_int($start))
            $sql .= " AND t.created_at >= '$start'";
        if ($end != 0 && is_int($end))
            $sql .= " AND t.created_at <= '$end'";
    }

    // list all tweets
    $rec = mysql_query($sql);
    if ($rec) {
        $handle = fopen($datadir . "/" . $dataname . "_urls_all.csv", "w");
        while ($res = mysql_fetch_assoc($rec)) {
            fputcsv($handle, $res);
            print ".";
        }
        fclose($handle);
        print "\ndone\n";
    }

    if (!$includeRetweets)   // remove identical tweets and tweets starting with 'rt'
        $sql .= " AND lower(text) NOT LIKE 'rt%' GROUP BY t.text";
    $rec = mysql_query($sql);
    if ($rec) {

        $handle = fopen($datadir . "/" . $dataname . "_urls" . ($includeRetweets ? "" : "_noRT") . ".csv", "w");
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
    $sql = "SELECT id, text FROM $dataset WHERE id IN (" . implode(", ", array_keys($tweetIds)) . ")";
    $rec = mysql_query($sql);
    if ($rec) {
        $handle = fopen($datadir . "/$name.csv", "w");
        while ($res = mysql_fetch_assoc($rec)) {
            $out = $res;
            $out['host'] = $tweetIds[$res['id']];
            fputcsv($handle, $out);
            //print "_";
        }
        fclose($handle);
        print "wrote tweets\n";
    }
}

/*
 * get all the tweets for which we have no id (those which are not polarized)
 */

function getNonPolarizedTweets($dataset, $tweetIds, $name) {
    global $datadir;
    $sql = "SELECT id, text FROM $dataset WHERE id NOT IN (" . implode(", ", array_keys($tweetIds)) . ")";
    $rec = mysql_query($sql);
    if ($rec) {
        $handle = fopen($datadir . "/$name.csv", "w");
        while ($res = mysql_fetch_assoc($rec)) {
            fputcsv($handle, $res);
            //print "_";
        }
        fclose($handle);
        print "wrote tweets\n";
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

/*
 * Simple coword calculation, all in memory
 */

function coword($datafile) {

    print "getting cowords $datafile\n";

    include_once("common/Coword.class.php");

    $coword = new Coword;
    $coword->setHashtags_are_separate_words(TRUE);
    if (($handle = fopen($datafile, "r")) === FALSE)
        die("could not open $datafile\n");
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $coword->addDocument($data[1]);
    }
    $coword->iterate();

    file_put_contents(str_replace(".csv", "_frequencies.csv", $datafile), chr(239) . chr(187) . chr(191) . $coword->getWordsAsCsv());
    file_put_contents(str_replace(".csv", "_cowords.csv", $datafile), chr(239) . chr(187) . chr(191) . $coword->getCowordsAsCsv());
    file_put_contents(str_replace(".csv", "_cowords_network.gexf", $datafile), chr(239) . chr(187) . chr(191) . $coword->getCowordsAsGexf($datafile));
}

?>
