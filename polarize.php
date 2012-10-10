<?php

/*
 * This script polarizes tweets by looking for tweets containing a specific 'charged' host name.
 * The script will output a variety of stats as well as the polarized tweets.
 * 
 * @author "Erik Borra" <erik@digitalmethods.net>
 */

include_once('common/config.php');
include_once('common/functions.php');
db_connect($db_host, $db_user, $db_pass, $db_name); // or die("could not connect to $db_name on $db_host");

$start = strtotime("19 May 2012");
$end = strtotime("22 June 2012");
$dataset = "z_501";
$dataname = "acta";
$datadir = "files";
$charges = loadCharges($datadir . '/' . $dataname . "_charges.csv");    // List of {host,charge}-combinations, where charge can be 'pro', 'con' or 'neutral'
getTweetUrls($dataset, $start, $end);

//polarizeTweets($dataset, $charges, $start, $end);

/*
 * Compare the tweeted hosts with the ones in our pro / con / neutral lists in files/$dataname_charges
 * Output stats and polarized tweets
 */

function polarizeTweets($dataset, $charges, $start, $end) {

    global $pro, $con, $neutral, $datadir, $dataname;
    $pros = $cons = $neutrals = $proTweetIds = $conTweetIds = $neutralTweetIds = array();

    // see whether hosts of specific leaning are found in specific tweet
    // @todo, it might be that a tweet contains URLs of different polarization, this will lead to the tweet appearing in both polarizations
    $file = file($datadir . '/' . $dataname . '_urls.csv');
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
    die;
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
    file_put_contents($datadir . '/' . $dataname . '_hostsInTweets.csv', $out);

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
    file_put_contents($datadir . '/' . $dataname . '_polarizedHosts.csv', $out);

    //  write tweets per pole
    getTweets($dataset, $conTweetIds, $dataname . "_conTweets");
    getTweets($dataset, $proTweetIds, $dataname . "_proTweets");
    getTweets($dataset, $neutralTweetIds, $dataname . "_neutralTweets");
    getNonPolarizedTweets($dataset, array_merge($conTweetIds, $proTweetIds, $neutralTweetIds), $dataname . "_nonPolarizedTweets");

    // get coword and word frequencies
    simpleCoword($datadir . "/" . $dataname . "_conTweets.csv");
    simpleCoword($datadir . "/" . $dataname . "_proTweets.csv");
    simpleCoword($datadir . "/" . $dataname . "_neutralTweets.csv");
    //simpleCoword($datadir . "/" . $dataname . "_nonPolarizedTweets.csv"); // will blow up ur computer
}

/*
 *  get tweets with URLs
 */

function getTweetUrls($dataset, $start = 0, $end = 0) {
    global $datadir, $dataname;
    if (!$start && !$end)
        $sql = "SELECT tweetid, tweetedurl, targeturl, tweetedhost, targethost FROM urls WHERE dbname = 'yourTwapperKeeper' AND tablename = '$dataset'";
    else {
        $sql = "SELECT u.tweetid, u.tweetedurl, u.targeturl, u.tweetedhost, u.targethost FROM urls u, $dataset t WHERE u.dbname = 'yourTwapperKeeper' AND u.tablename = '$dataset' AND u.tweetid = t.id";
        if ($start != 0 && is_int($start))
            $sql .= " AND t.time >= $start";
        if ($end != 0 && is_int($end))
            $sql .= " AND t.time <= $end";
    }
    // removes retweets
    $sql .= " AND lower(text) NOT LIKE 'rt%'";
    print $sql . "\n";
    $rec = mysql_query($sql);
    if ($rec) {
        $handle = fopen($datadir . "/" . $dataname . "_urls.csv", "w");
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
    $sql = "SELECT id,text FROM $dataset WHERE id NOT IN (" . implode(",", array_keys($tweetIds)) . ")";
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

function simpleCoword($datafile) {
    $nodes = $fromTo = array();

    if (($handle = fopen($datafile, "r")) === FALSE)
        die("could not open $datafile\n");
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $text = substr(stripslashes($data[1]), 0, -1); // remove quotes surrounding tweet
        $text = strtolower($text);         // lower text
        $text = html_entity_decode($text);
        $text = preg_replace("/https?:\/\/[^\s]*/", " ", $text); // remove URLs
        //$text = preg_replace("/[^#\w\d]/", " ", $text);    // remove non-words -> breaks on things like don't @todo

        $text = mb_str_split($text);    // remove non-words
        $text = implode(" - ", $text);

        $text = trim($text);          // trim
        $text = preg_replace("/[\s\t\n\r]+/", " ", $text);   // replace whitespace characters by single whitespace

        $words = explode(" ", $text);
        //$words = array_diff($words, $stopwords);  // @todo

        $frequency = array_count_values($words);
        $words = array_keys($frequency);
        for ($i = 0; $i < count($words); $i++) {
            $from = $words[$i];
            if (strlen($from) < 2)
                continue;           // remove words smaller than 2 chars
            $nodes[] = $from;
            for ($j = $i + 1; $j < count($words); $j++) {
                $to = $words[$j];
                if (strlen($to) < 2)
                    continue;           // remove words smaller than 2 chars
                $nodes[] = $to;

                if (!isset($fromTo[$from]) || !isset($fromTo[$from][$to]))   // init
                    $fromTo[$from][$to] = 0;

                $fromTo[$from][$to] += min($frequency[$words[$i]], $frequency[$words[$j]]); // add per tweet cooccurence
            }
        }
    }

    // various outputs
    $frequencies = array_count_values($nodes);
    arsort($frequencies);
    $out = "";
    foreach ($frequencies as $word => $freq)
        $out .= "$word,$freq\n";
    file_put_contents(str_replace(".csv", "_frequencies.csv", $datafile), $out);

    $out = "";
    foreach ($fromTo as $from => $tos) {
        foreach ($tos as $to => $freq) {
            if ($freq >= 2)   // @todo min coword frequency of two
                $out .= "$from,$to,$freq\n";
            else {
                unset($fromTo[$from][$to]);
                if (empty($fromTo[$from]))
                    unset($fromTo[$from]);
            }
        }
    }
    file_put_contents(str_replace(".csv", "_cowords.csv", $datafile), $out);

    $nodes = array_unique($nodes);
    file_put_contents(str_replace(".csv", "_cowords_network.gexf", $datafile), toGephi($fromTo, $nodes, $datafile, $frequencies));
}

function mb_str_split($string) {
    global $punctuation;
    $split = preg_split('/\b([\(\).,\-\',:!\?;"\{\}\[\]„“»«‘\r\n\.]*)/u', $string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $split = preg_split('/\b([' . implode("", $punctuation) . "]*)/u", $string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    return array_filter($split, 'filter');
}

function filter($val) {
    if (trim($val) != '') {
        return trim($val);
    }
    return false;
}

?>
