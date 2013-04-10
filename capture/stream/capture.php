<?php

// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);
include "config.php";


// ----- connection -----
dbconnect();
checktables();
connectsocket();


function connectsocket() {

	global $twitter_user,$twitter_pass,$querybins,$path_local;

	//echo "<pre>";

	logit("error.log","connecting to API socket");
	$pid = getmypid();
	file_put_contents($path_local . "logs/procinfo", $pid . "|" . time());

	$wait = 5000000;

	$tweetcounter = 0;
	$tweetbucket = array();
	
	// prepare queries
	
	$querylist = array();
	
	//print_r($querybins);
	
	foreach($querybins as $binname => $bin) {
		//echo $bin . "|";
		$queries = explode(",", $bin);
		foreach($queries as $query) {
			$querylist[$query] = preg_replace("/\'/", "", $query);
		}
		$querybins[$binname] = $queries;
	}
	
	
	
	//print_r($querylist);
	//print_r($querybins);
		
	$query = array("track" => implode(",", $querylist));
	
	
	
	//print_r($query);
	flush();
	
	
	// Twitter recommends 90 seconds timeout
	// https://dev.twitter.com/docs/streaming-apis/connecting
	$fp = fsockopen("ssl://stream.twitter.com", 443, $errno, $errstr, 90);		
	
	if(!$fp){
		
		logit("error.log","fsock error: " . $errstr . "(" . $errno . ")");
	
	} else {
	
		logit("error.log","connected - query: " . $query["track"]);
	
		$request = "POST /1.1/statuses/filter.json?" . http_build_query($query) . " HTTP/1.1\r\n";
		$request .= "Host: stream.twitter.com\r\n";
		$request .= "Authorization: Basic " . base64_encode($twitter_user . ':' . $twitter_pass) . "\r\n\r\n";
				
		fwrite($fp, $request);
		stream_set_timeout($fp, 90);
		
		$start = NULL;
        $timeout = 90; 					// timeout if idle (http://php.net/manual/en/function.feof.php) 
        $start = microtime(true);
        //echo $start;
        
        
        /* another attempt
        $streamarr = array($fp);
        $w = $e = null;

        while(stream_select($streamarr, $w, $e, 15) !== false && ) {
        	$json = fgets($fp);
        	print_r($json);
        	print_r($streamarr);
        	flush();
        	sleep(1);
        };
        exit;
        */

		while(!safe_feof($fp,$start) && (microtime(true) - $start) < $timeout) {

			$json = fgets($fp);



			//echo $json;
			//flush();
			
			$data = json_decode($json, true);
			
			if(isset($data["disconnect"])) {
				$discerror = implode(",",$data["disconnect"]);
			}
			
			if($data) {
				
				//if($tweetcounter >= 6) { exit; }
				//print_r($data);
				//flush();

				$tweetcounter++;
				
				$tweetbucket[] = $data;
				
				if(count($tweetbucket) == 100) {
					processtweets($tweetbucket);
					$tweetbucket = array();
				}
			}
		}
	
		logit("error.log","connection dropped or timed out - error " . $discerror);
		
		fclose($fp);
	}
}


function safe_feof($fp, &$start = NULL) {
	//global $start;
	$start = microtime(true); 
	return feof($fp);
}   


function processtweets($tweetbucket) {
	
	global $querybins,$path_local;
	
	// we run through every bin to check whether the received tweets fit
	foreach($querybins as $binname => $queries) {
		
		$list_tweets = array();
		$list_hashtags = array();
		$list_urls = array();
		$list_mentions = array();
			
		// running through every single tweet	
		foreach($tweetbucket as $data) {
			
			// adding the expanded url to the tweets text to search in them like twiter does
			foreach($data["entities"]["urls"] as $url) {
				$data["text"] .= " [[" . $url["expanded_url"]."]]";
			}
			
			//$data["text"] = strtolower($data["text"]);
			
			
			$found = false;
			
			// we check for every query in the bin if they fit
			foreach($queries as $query) {
				
				$query = strtolower($query);
				
				$pass = false;
				
				// check for queries with two words, but go around quoted tweets
				if(preg_match("/ /",$query) && !preg_match("/\'/",$query)) {
					$tmplist = explode(" ", $query);
					
					$all = true;
					
					foreach($tmplist as $tmp) {
						if(!preg_match("/".$tmp."/i", $data["text"])) {
							$all = false;
							break;
						}
					}
					
					// only if all words are found 
					if($all == true) { $pass = true; }
					
				} else {
				
					// treet quoted queries as single words
					$query = preg_replace("/\'/","", $query);
				
					if(preg_match("/".$query."/i",$data["text"])) {
						$pass = true;
					}
				}
				
				// at the first fitting query, we break
				if($pass == true) {
					$found = true;
					$break;
				}
			}
			
			// if the tweet does not fit in the current bin, go to the next one
			if($found == false) { continue; }
			
			//from_user_lang 	from_user_tweetcount 	from_user_followercount 	from_user_realname
			$t = array();
			$t["id"] = $data["id_str"];
			$t["created_at"] = date("Y-m-d H:i:s",strtotime($data["created_at"]));
			$t["from_user_name"] = addslashes($data["user"]["screen_name"]);
			$t["from_user_id"] = $data["user"]["id"];
			$t["from_user_lang"] = $data["user"]["lang"];
			$t["from_user_tweetcount"] = $data["user"]["statuses_count"];
			$t["from_user_followercount"] = $data["user"]["followers_count"];
			$t["from_user_friendcount"] = $data["user"]["friends_count"];
			$t["from_user_realname"] = addslashes($data["user"]["name"]);
			$t["source"] = addslashes($data["source"]);
			$t["location"] = addslashes($data["user"]["location"]);
			$t["geo_lat"] = 0;
			$t["geo_lng"] = 0;
			if($data["geo"] != null) {
				$t["geo_lat"] = $data["geo"]["coordinates"][0];
				$t["geo_lng"] = $data["geo"]["coordinates"][1];
			}
			$t["text"] = addslashes($data["text"]);
			$t["to_user_id"] = $data["in_reply_to_user_id_str"];
			$t["to_user_name"] = addslashes($data["in_reply_to_screen_name"]);
			$t["in_reply_to_status_id"] = $data["in_reply_to_status_id_str"];
			
			$list_tweets[] = "('" . implode("','",$t) . "')";
			
			
			if(count($data["entities"]["hashtags"]) > 0) {
				foreach($data["entities"]["hashtags"] as $hashtag) {
					$h = array();
					$h["tweet_id"] = $t["id"];
					$h["created_at"] = $t["created_at"];
					$h["from_user_name"] = $t["from_user_name"];
					$h["from_user_id"] = $t["from_user_id"];
					$h["text"] = addslashes($hashtag["text"]);
	
					$list_hashtags[] = "('" . implode("','",$h) . "')";
				}
			}
			
			if(count($data["entities"]["urls"]) > 0) {
				foreach($data["entities"]["urls"] as $url) {
					$u = array();
					$u["tweet_id"] = $t["id"];
					$u["created_at"] = $t["created_at"];
					$u["from_user_name"] = $t["from_user_name"];
					$u["from_user_id"] = $t["from_user_id"];
					$u["url"] = $url["url"];
					$u["url_expanded"] = addslashes($url["expanded_url"]);
	
					$list_urls[] = "('" . implode("','",$u) . "')";
				}
			}
			
			if(count($data["entities"]["user_mentions"]) > 0) {
				foreach($data["entities"]["user_mentions"] as $mention) {
					$m = array();
					$m["tweet_id"] = $t["id"];
					$m["created_at"] = $t["created_at"];
					$m["from_user_name"] = $t["from_user_name"];
					$m["from_user_id"] = $t["from_user_id"];
					$m["to_user"] = $mention["screen_name"];
					$m["to_user_id"] = $mention["id_str"];
					
					$list_mentions[] = "('" . implode("','",$m) . "')";
				}
			}
		}
	
		// distribute tweets into bins

	
		if(count($list_tweets) > 0) {

			$sql = "INSERT IGNORE INTO ".$binname."_tweets (id,created_at,from_user_name,from_user_id,from_user_lang,from_user_tweetcount,from_user_followercount,from_user_friendcount,from_user_realname,source,location,geo_lat,geo_lng,text,to_user_id,to_user_name,in_reply_to_status_id) VALUES ". implode(",", $list_tweets);
		
			$sqlresults = mysql_query($sql);
			if(!$sqlresults) {
				logit("error.log","insert error: " . $sql);
			} else { 
				$pid = getmypid();
				file_put_contents($path_local . "logs/procinfo", $pid . "|" . time());
			}
		}
		
		if(count($list_hashtags) > 0) {

			$sql = "INSERT IGNORE INTO ".$binname."_hashtags (tweet_id,created_at,from_user_name,from_user_id,text) VALUES ". implode(",", $list_hashtags);
			
			$sqlresults = mysql_query($sql);
			if(!$sqlresults) { logit("error.log","insert error: " . $sql); }
		}
		
		if(count($list_urls) > 0) {
			
			$sql = "INSERT IGNORE INTO ".$binname."_urls (tweet_id,created_at,from_user_name,from_user_id,url,url_expanded) VALUES ". implode(",", $list_urls);
										
			$sqlresults = mysql_query($sql);
			if(!$sqlresults) { logit("error.log","insert error: " . $sql); }
		}
		
		if(count($list_mentions) > 0) {
			
			$sql = "INSERT IGNORE INTO ".$binname."_mentions (tweet_id,created_at,from_user_name,from_user_id,to_user,to_user_id) VALUES ". implode(",", $list_mentions);
							
			$sqlresults = mysql_query($sql);
			if(!$sqlresults) { logit("error.log","insert error: " . $sql); }
		}
	}
}

function logit($file,$message) {
	
	global $path_local;
	
	$file = $path_local . "logs/" . $file;
	$message = date("Y-m-d H:i:s") . " " . $message . "\n";
	file_put_contents($file, $message, FILE_APPEND);
}

function checktables() {
	
	global $querybins;
	
	$tables = array();
	
	$sql = "SHOW TABLES";
	$sqlresults = mysql_query($sql);
		
	while($data = mysql_fetch_assoc($sqlresults)) {
		$tables[] = $data["Tables_in_twittercapture"];
	}
	
	foreach($querybins as $bin => $content) {
		
		if(!in_array($bin . "_tweets", $tables)) {
		
			$sql = "CREATE TABLE " . $bin . "_hashtags (
			id int(11) NOT NULL AUTO_INCREMENT,
			tweet_id bigint(20) NOT NULL,
			created_at datetime NOT NULL,
			from_user_name varchar(255) NOT NULL,
			from_user_id int(11) NOT NULL,
			`text` varchar(255) NOT NULL,
			PRIMARY KEY (id),
			KEY `created_at` (`created_at`),
			KEY `tweet_id` (`tweet_id`),
			KEY `text` (`text`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8";
			
			$sqlresults = mysql_query($sql) or die (mysql_error());
			
			$sql = "CREATE TABLE " . $bin . "_mentions (
			id int(11) NOT NULL AUTO_INCREMENT,
			tweet_id bigint(20) NOT NULL,
			created_at datetime NOT NULL,
			from_user_name varchar(255) NOT NULL,
			from_user_id int(11) NOT NULL,
			to_user varchar(255) NOT NULL,
			to_user_id int(11) NOT NULL,
			PRIMARY KEY (id),
			KEY `created_at` (`created_at`),
			KEY `tweet_id` (`tweet_id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";
			
			$sqlresults = mysql_query($sql) or die (mysql_error());
			
			$sql = "CREATE TABLE " . $bin . "_tweets (
			id bigint(20) NOT NULL,
			created_at datetime NOT NULL,
			from_user_name varchar(255) NOT NULL,
			from_user_id int(11) NOT NULL,
			from_user_lang varchar(16) NOT NULL,
			from_user_tweetcount int(11) NOT NULL,
			from_user_followercount int(11) NOT NULL,
			from_user_friendcount int(11) NOT NULL,
			from_user_realname varchar(64) NOT NULL,
			`source` varchar(255) NOT NULL,
			`location` varchar(64) NOT NULL,
			`geo_lat` float(10,6) NOT NULL,
			`geo_lng` float(10,6) NOT NULL, 
			`text` varchar(255) NOT NULL,
			to_user_id int(11) NOT NULL,
			to_user_name varchar(255) NOT NULL,
			in_reply_to_status_id bigint(20) NOT NULL,
			PRIMARY KEY (id),
			KEY `created_at` (`created_at`),
			FULLTEXT KEY `text` (`text`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
			
			$sqlresults = mysql_query($sql) or die (mysql_error());
			
			$sql = "CREATE TABLE " . $bin . "_urls (
			id int(11) NOT NULL AUTO_INCREMENT,
			tweet_id bigint(20) NOT NULL,
			created_at datetime NOT NULL,
			from_user_name varchar(255) NOT NULL,
			from_user_id int(11) NOT NULL,
			url varchar(255) NOT NULL,
			url_expanded varchar(255) NOT NULL,
			url_followed varchar(255) NOT NULL,
			domain varchar(255) NOT NULL,
			error_code varchar(64) NOT NULL,
			PRIMARY KEY (id),
			KEY `created_at` (`created_at`),
			KEY `domain` (`domain`),
			KEY `url_followed` (`url_followed`),
			KEY `url_expanded` (`url_expanded`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";
					
			$sqlresults = mysql_query($sql) or die (mysql_error());		
		}
	}
}


?>
