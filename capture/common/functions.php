<?php

function checktables() {

	global $querybins;

	$tables = array();

	$sql = "SHOW TABLES";
	$sqlresults = mysql_query($sql);

	while($data = mysql_fetch_row($sqlresults)) {
		$tables[] = $data[0];
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
			from_user_listed int(11) NOT NULL,
			from_user_realname varchar(64) NOT NULL,
			from_user_utcoffset int(11),
			from_user_timezone varchar(255),
			from_user_description varchar(255) NOT NULL,
			from_user_url varchar(2048),
			from_user_verified bool DEFAULT false,
			source varchar(255),
			location varchar(64),
			geo_lat float(10,6),
			geo_lng float(10,6),
			text varchar(255) NOT NULL,
			retweet_id bigint(20),
			retweet_count int(11),
			favorite_count int(11),
			to_user_id int(11),
			to_user_name varchar(255),
			in_reply_to_status_id bigint(20),
			filter_level varchar(6),
			lang varchar(16),
			PRIMARY KEY (id),
			KEY `created_at` (`created_at`),
			KEY `from_user_name` (`from_user_name`),
			KEY `retweet_id` (`retweet_id`),
			FULLTEXT KEY `from_user_description` (`from_user_description`),
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
			FULLTEXT KEY `url_followed` (`url_followed`),
			FULLTEXT KEY `url_expanded` (`url_expanded`),
			KEY `tweet_id` (`tweet_id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

			$sqlresults = mysql_query($sql) or die (mysql_error());
		}
	}
}

?>
