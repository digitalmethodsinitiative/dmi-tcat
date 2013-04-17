<?php
if($argc < 1) die(); // only access from command line
include_once('../config.php');

# NOTE: twitter api normally gives back url and url_expanded. YourTwapperKeeper only has url. The url expansion script which needs to be run after this one needs url_expanded to work (has to do with optimalizations). Ergo: no analysis of link shortening servies on ytk_ datasets


$import_table = "z_39";
$bin_name = "ytk_climatechange";

// From this db
$archive_dbh = new PDO("mysql:host=$hostname;dbname=twapper", $dbuser, $dbpass);
$archive_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ... To this db
$dbhost_to = '';
$dbuser_to = '';
$dbpass_to = '';
$dbh = new PDO("mysql:host=$dbhost_to;dbname=twittercapture", $dbuser_to, $dbpass_to);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


// Old table, importing tweets from here
$query = $archive_dbh->prepare("SELECT * FROM ".$import_table);
$query->execute();

// Create new tables
$sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_hashtags (
	id int(11) NOT NULL AUTO_INCREMENT,
	tweet_id bigint(20),
	created_at datetime,
	from_user_name varchar(255),
	from_user_id int(11),
	`text` varchar(255),
	PRIMARY KEY (id),
	KEY `created_at` (`created_at`),
	KEY `tweet_id` (`tweet_id`),
	KEY `text` (`text`)
	) ENGINE=MyISAM  DEFAULT CHARSET=utf8";

$create_hash = $dbh->prepare($sql);
$create_hash->execute();

$sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_mentions (
	id int(11) NOT NULL AUTO_INCREMENT,
	tweet_id bigint(20),
	created_at datetime,
	from_user_name varchar(255),
	from_user_id int(11),
	to_user varchar(255),
	to_user_id int(11),
	PRIMARY KEY (id),
	KEY `created_at` (`created_at`),
	KEY `tweet_id` (`tweet_id`)
	) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

$create_mentions = $dbh->prepare($sql);
$create_mentions->execute();

$sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_tweets (
	id bigint(20) NOT NULL,
	created_at datetime,
	from_user_name varchar(255),
	from_user_id int(11),
	from_user_lang varchar(16),
	from_user_tweetcount int(11),
	from_user_followercount int(11),
	from_user_friendcount int(11),
	from_user_realname varchar(64),
	`source` varchar(255),
	`location` varchar(64),
	`geo_lat` float(10,6),
	`geo_lng` float(10,6), 
	`text` varchar(255),
	to_user_id int(11),
	to_user_name varchar(255),
	in_reply_to_status_id bigint(20),
	PRIMARY KEY (id),
	KEY `created_at` (`created_at`),
	FULLTEXT KEY `text` (`text`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$create_tweets = $dbh->prepare($sql);
$create_tweets->execute();

$sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_urls (
	id int(11) NOT NULL AUTO_INCREMENT,
	tweet_id bigint(20),
	created_at datetime,
	from_user_name varchar(255),
	from_user_id int(11),
	url varchar(255),
	url_expanded varchar(255),
	url_followed varchar(255),
	domain varchar(255),
	error_code varchar(64),
	PRIMARY KEY (id),
	KEY `created_at` (`created_at`),
	KEY `domain` (`domain`),
	KEY `url_followed` (`url_followed`),
	KEY `url_expanded` (`url_expanded`)
	) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";
			
$create_urls = $dbh->prepare($sql);
$create_urls->execute();

// insert old data in new tables
while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
	$txt = $row['text'];
	$hashtags = $urls = $mentions = $t = array();

	$t["id"] = $row['id'];
	$t["text"] = $txt;
	$t["created_at"] = date("Y-m-d H:i:s", strtotime($row["created_at"]));
	$t["from_user_name"] = $row['from_user'];
	$t["from_user_id"] = $row['from_user_id'];
	$t["from_user_lang"] = $row['iso_language_code'];
	$t["from_user_tweetcount"] = null;
	$t["from_user_followercount"] = null;
	$t["from_user_friendcount"] = null;
	$t["from_user_realname"] = null;
	$t["source"] = $row['source'];
	$t["location"] = null;
	$t["geo_lat"] = $row['geo_coordinates_0'];
	$t["geo_lng"] = $row['geo_coordinates_1'];
	$t["to_user_id"] = $row['to_user_id'];
	
	$t["to_user_name"] = ''; 
	if(preg_match("/^\B@([\p{L}\w\d_]+)/u",$txt,$matches))
		$t["to_user_name"] = $matches[1];
	$t["in_reply_to_status_id"] = null;
	
	// Parse hashtags
	if (preg_match_all("/\B#([\p{L}\w\d_]+)/u", $txt, $hash_matches)) {
		$hashtags = $hash_matches[1];
	}

	// Parse urls
	if (preg_match_all("/\b(https?:\/\/[^\s]+)/u", $txt, $url_matches)) {
		$urls = $url_matches[1];
	}

	// Parse user mentions
	if (preg_match_all("/\B@([\p{L}\w\d_]+)/u", $txt, $mention_matches)) {
		$mentions = $mention_matches[1];
	}

	if ($hashtags) { 

		foreach ($hashtags as $hashtag) {
			$h = array();
			$h['tweet_id'] = $t['id'];
			$h["created_at"] =  $t["created_at"];
			$h["from_user_name"] = $t["from_user_name"];
			$h["from_user_id"] = $t["from_user_id"];				
			$h["text"] = $hashtag;


			$q = $dbh->prepare("REPLACE INTO " . $bin_name . '_hashtags' . "
				(tweet_id, created_at, from_user_name, from_user_id, text) 
				VALUES (:tweet_id, :created_at , :from_user_name, :from_user_id, :text)");
    		
    		$q->bindParam(':tweet_id', $h['tweet_id'], PDO::PARAM_STR);
    		$q->bindParam(':created_at', $h['created_at'], PDO::PARAM_STR);
    		$q->bindParam(':from_user_name', $h['from_user_name'], PDO::PARAM_STR);
    		$q->bindParam(':from_user_id', $h['from_user_id'], PDO::PARAM_STR);
    		$q->bindParam(':text', $h['text'], PDO::PARAM_STR);
	
			$q->execute();    		

		}
	}

	if ($urls) {
		foreach ($urls as $url) {
			$u = array();
			$u['tweet_id'] = $t['id'];
			$u["created_at"] = $t['created_at'];
			$u["from_user_name"] = $t["from_user_name"];
			$u["from_user_id"] = $t["from_user_id"];				
			$u["url"] = $url;
			$u['url_expanded'] = $url;

			$q = $dbh->prepare("REPLACE INTO " . $bin_name . '_urls' . "
				(tweet_id, created_at, from_user_name, from_user_id, url, url_expanded) 
				VALUES (:tweet_id, :created_at , :from_user_name, :from_user_id, :url, :url_expanded)");
    		
    		$q->bindParam(':tweet_id', $u['tweet_id'], PDO::PARAM_STR);
    		$q->bindParam(':created_at', $u['created_at'], PDO::PARAM_STR);
    		$q->bindParam(':from_user_name', $u['from_user_name'], PDO::PARAM_STR);
    		$q->bindParam(':from_user_id', $u['from_user_id'], PDO::PARAM_STR);
    		$q->bindParam(':url', $u['url'], PDO::PARAM_STR);
    		$q->bindParam(':url_expanded', $u['url_expanded'], PDO::PARAM_STR);

			$q->execute();    

		}		
	}

	if ($mentions) {
		foreach ($mentions as $mention) {
			$m = array();
			$m["tweet_id"] = $t["id"];
			$m["created_at"] = $t["created_at"];
			$m["from_user_name"] = $t["from_user_name"];
			$m["from_user_id"] = $t["from_user_id"];
			$m["to_user"] = $mention;
			$m["to_user_id"] = null;

			$q = $dbh->prepare("REPLACE INTO " . $bin_name . '_mentions' . "
				(tweet_id, created_at, from_user_name, from_user_id, to_user, to_user_id) 
				VALUES (:tweet_id, :created_at , :from_user_name, :from_user_id, :to_user, :to_user_id)");
    		
    		$q->bindParam(':tweet_id', $m['tweet_id'], PDO::PARAM_STR);
    		$q->bindParam(':created_at', $m['created_at'], PDO::PARAM_STR);
    		$q->bindParam(':from_user_name', $m['from_user_name'], PDO::PARAM_STR);
    		$q->bindParam(':from_user_id', $m['from_user_id'], PDO::PARAM_STR);
    		$q->bindParam(':to_user', $m['to_user'], PDO::PARAM_STR);
    		$q->bindParam(':to_user_id', $m['to_user_id'], PDO::PARAM_STR);

			$q->execute();    
		}

	}

	$q = $dbh->prepare("REPLACE INTO " . $bin_name . '_tweets' . "
				(id, created_at,  from_user_name, from_user_id, from_user_lang, 
				from_user_tweetcount, from_user_followercount, from_user_friendcount, 
				from_user_realname, source, location, geo_lat, geo_lng, text, 
				to_user_id, to_user_name,in_reply_to_status_id) 
				VALUES 
				(:id, :created_at, :from_user_name, :from_user_id, :from_user_lang,
				:from_user_tweetcount, :from_user_followercount, :from_user_friendcount, 
				:from_user_realname, :source, :location, :geo_lat, :geo_lng, :text, 
				:to_user_id, :to_user_name, :in_reply_to_status_id) 
				;");
        
    $q->bindParam(':id', $t['id'], PDO::PARAM_STR);
    $q->bindParam(':created_at', $t['created_at'], PDO::PARAM_STR);
    $q->bindParam(':from_user_name', $t['from_user_name'], PDO::PARAM_STR);
    $q->bindParam(':from_user_id', $t['from_user_id'], PDO::PARAM_STR);
    $q->bindParam(':from_user_lang', $t['from_user_lang'], PDO::PARAM_STR);
    $q->bindParam(':from_user_tweetcount', $t['from_user_tweetcount'], PDO::PARAM_STR);
    $q->bindParam(':from_user_followercount', $t['from_user_followercount'], PDO::PARAM_INT);
    $q->bindParam(':from_user_friendcount', $t['from_user_friendcount'], PDO::PARAM_INT);
    $q->bindParam(':from_user_realname', $t['from_user_realname'], PDO::PARAM_STR);
    $q->bindParam(':source', $t['source'], PDO::PARAM_STR);
    $q->bindParam(':location', $t['location'], PDO::PARAM_STR);
    $q->bindParam(':geo_lat', $t['geo_lat'], PDO::PARAM_STR);
    $q->bindParam(':geo_lng', $t['geo_lng'], PDO::PARAM_STR);
    $q->bindParam(':text', $t['text'], PDO::PARAM_STR);
    $q->bindParam(':to_user_id', $t['to_user_id'], PDO::PARAM_STR);
    $q->bindParam(':to_user_name', $t['to_user_name'], PDO::PARAM_STR);
    $q->bindParam(':in_reply_to_status_id', $t['in_reply_to_status_id'], PDO::PARAM_STR);

    $q->execute();

}

?>
