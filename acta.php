<?php
include_once('common/config.php');
include_once('common/functions.php');
db_connect($db_host,$db_user,$db_pass,$db_name);// or die("could not connect to $db_name on $db_host");
include_once('acta_polarizingUrls.php');

//getTweetUrls();
getTopHosts();

function getTopHosts() {

	global $pro, $con, $neutral;
	$pros = $cons = $neutrals = $proTweetIds = $conTweetIds = $neutralTweetIds = array();

	// see whether hosts of specific leaning are found in specific tweet
	// @todo, it might be that a tweet contains URLs of different polarization, this will lead to a tweet in both polarizations
	$file = file('files/acta_urls.csv');
	foreach($file as $f) {
		$e = explode(",",$f);
		$host = strtolower(trim($e[4]));
		$hosts[] = $host;
		foreach($pro as $p) {
			if(strstr($host,$p)!==false) {
				$pros[] = $p;
				$proTweetIds[$e[0]] = $host;
			}
		}
		foreach($con as $c) {
			if(strstr($host,$c)!==false) {
				$cons[] = $c;
				$conTweetIds[$e[0]] = $host;
			}
		}
		foreach($neutral as $n) {
			if(strstr($host,$n)!==false) {
				$neutrals[] = $n;
				$neutralTweetIds[$e[0]] = $host;
			}
		}
	}
	
	// count nr of hosts found in all tweets
	$acv = array_count_values($hosts);
	arsort($acv);
	$out = "";
	foreach($acv as $a => $v) $out .= "$a,$v\n";
	file_put_contents('files/acta_hosts.csv',$out);
	print count($acv)." hosts found\n";
	print array_sum($acv)." urls found\n";
	
	// count nr of hosts per pole
	$acvpros = array_count_values($pros);
	print count($acvpros)." pro acta hosts found\n";
	print array_sum($acvpros)." pro acta tweets found\n";
	$acvcons = array_count_values($cons);
	print count($acvcons)." anti acta hosts found\n";
	print array_sum($acvcons)." anti acta tweets found\n";
	$acvneutrals = array_count_values($neutrals);
	print count($acvneutrals)." neutral acta hosts found\n";
	print array_sum($acvneutrals)." neutral acta tweets found\n";
	
	// write host count and pole
	$out = "";
	arsort($acvpros);
	arsort($acvcons);
	arsort($acvneutrals);
	foreach($acvpros as $host => $v) $out .= "$host,$v,pro acta\n";
	foreach($acvcons as $host => $v) $out .= "$host,$v,con acta\n";
	foreach($acvneutrals as $host => $v) $out .= "$host,$v,neutral acta\n";
	file_put_contents('files/acta_polarizedHosts.csv',$out);
	
	//  write tweets per pole
	getTweets($conTweetIds,"acta_conTweets");
	getTweets($proTweetIds,"acta_proTweets");
	getTweets($neutralTweetIds,"acta_neutralTweets");
}

function getTweetUrls() {
	$sql = "SELECT tweetid, tweetedurl, targeturl, tweetedhost, targethost FROM urls WHERE dbname = 'yourTwapperKeeper' AND tablename = 'z_501'";
	print $sql."\n";
	$rec = mysql_query($sql);
	if($rec) {
		$handle = fopen("files/acta_urls.csv","w");
		while($res = mysql_fetch_assoc($rec)) {
			fputcsv($handle,$res);
			print ".";		
		}
		fclose($handle);
		print "\ndone\n";
	}
}

function getTweets($tweetIds,$name) {
	$sql = "SELECT id,text FROM z_501 WHERE id IN (".implode(",",array_keys($tweetIds)).")";
	$rec = mysql_query($sql);
	if($rec) {
		$handle = fopen("files/$name.csv","w");
		while($res = mysql_fetch_assoc($rec)) {
			$out = $res;
			$out['host'] = $tweetIds[$res['id']];
			fputcsv($handle,$out);
			print "_";
		}
		fclose($handle);
		print "\nwrote tweets\n";
	}
}


?>