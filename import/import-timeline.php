<?php

include_once('../config.php');

// specify the name of the bin here (make sure to also add it toe querybins.php)
$bin_name = 'user_swedish_media';
// specify dir with the user timelines (json)
$dir = '/Users/erik/Sites/sylvester/user_swedish_media';


$dbh = new PDO("mysql:host=$hostname;dbname=twittercapture", $dbuser, $dbpass);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Tweet::create_bin($dbh, $bin_name); // @todo, refactor: extract checktables from capture/stream/capture.php into a common functions file
$all_files = glob("$dir/*.json");

global $tweets_processed, $tweets_failed, $tweets_success,
 $valid_timeline, $empty_timeline, $invalid_timeline, $populated_timeline,
 $total_timeline, $all_users, $all_tweet_ids;

$all_users = $all_tweet_ids = array();
$tweets_processed = $tweets_failed = $tweets_success = $valid_timeline = $empty_timeline = $invalid_timeline = $populated_timeline = $total_timeline = 0;
$count = count($all_files);
$c = $count;

for ($i = 0; $i < $count; ++$i) {
    $filepath = $all_files[$i];
    process_json_file_timeline($filepath, $dbh);
    print $c-- . "\n";
}

function process_json_file_timeline($filepath, $dbh) {
    global $tweets_processed, $tweets_failed, $tweets_success,
    $valid_timeline, $empty_timeline, $invalid_timeline, $populated_timeline,
    $total_timeline, $all_tweet_ids, $all_users, $bin_name;

    $total_timeline++;

    $filestr = file_get_contents($filepath);
    
    // sylvester stores multiple json exports in the same file,
    // in order to decode it we will need to split it into its respective individual exports
    $jsons = explode("}][{", $filestr);
    print count($jsons)." jsons found\n";
    
    foreach ($jsons as $json) {
        if (substr($json, 0, 2) != "[{")
            $json = "[{" . $json;
        if (substr($json, -2) != "}]")
            $json = $json . "}]";
        $timeline = json_decode($json);
        
        if (is_array($timeline)) {
            $valid_timeline++;
            if (!empty($timeline)) {
                $populated_timeline++;
            } else {
                $empty_timeline++;
            }
        } else {
            $invalid_timeline++;
        }

        foreach ($timeline as $tweet) {

            $t = Tweet::fromJSON($tweet);

            $all_users[] = $t->user->id;
            $all_tweet_ids[] = $t->id;

            $saved = $t->save($dbh, $bin_name);

            if ($saved) {
                $tweets_success++;
            } else {
                $tweets_failed++;
            }

            $tweets_processed++;
        }
    }
}

print "\n\n\n\n";
print "Number of tweets: " . count($all_tweet_ids) . "\n";
print "Unique tweets: " . count(array_unique($all_tweet_ids)) . "\n";
print "Unique users: " . count(array_unique($all_users)) . "\n";

print "Processed $tweets_processed tweets!\n";
print "Failed storing $tweets_failed tweets!\n";
print "Succesfully stored $tweets_success tweets!\n";
print "\n";
print "Total number of timelines: $total_timeline\n";
print "Valid timelines: $valid_timeline\n";
print "Invalid timelines: $invalid_timeline\n";
print "Populated timelines: $populated_timeline\n";
print "Empty timelines: $empty_timeline\n";

/**
 * 
 * Tweet entity
 * Based on Twitter API 1.1
 * 
 */
class Tweet {

    // Fields copied from a sample timeline provided by the Twitter API
    // @see https://dev.twitter.com/docs/api/1.1/get/statuses/mentions_timeline

    public $favorited;
    public $contributors;
    public $truncated;
    public $text;
    public $created_at;
    public $retweeted;
    public $retweeted_status;
    public $in_reply_to_status_id;
    public $in_reply_to_user_id_str;
    public $coordinates;
    public $in_reply_to_status_id_str;
    public $in_reply_to_screen_name;
    public $id_str;
    public $place;
    public $retweet_count;
    public $geo;
    public $source;
    public $id;
    public $possibly_sensitive;
    public $in_reply_to_user_id;
    public $user_mentions = array();
    public $hashtags = array();
    public $urls = array();
    public $user;

    public function __construct($obj = null) {
        foreach ($obj as $k => $v) {
            $this->{$k} = $v;
        }
    }

    public function __set($name, $value) {

        if (in_array($name, get_class_vars(get_class($this)))) {
            if (is_array($this->{$k})) {
                print 'array';
            } else {
                $this->{$name} = $value;
            }
        } elseif($name == "favorite_count" || $name == "lang") {
            //print $name ." not available as a database field\n";
            return;
        } else {
            throw new Exception("Trying to set non existing class property: $name");
        }
    }

    public static function fromJSON($json) {
        // Parse JSON when fed JSON string
        if (is_string($json)) {
            $object = json_decode($json);
        } else if (is_object($json)) {
            $object = $json;
        } else {
            throw new Exception('Invalid JSON input');
        }

        $urls = $object->entities->urls;
        $user_mentions = $object->entities->user_mentions;
        $hashtags = $object->entities->hashtags;
        unset($object->entities);

        $tweet = new self($object);
        $tweet->urls = $urls;
        $tweet->user_mentions = $user_mentions;
        $tweet->hashtags = $hashtags;

        return $tweet;
    }

    public function save(PDO $dbh, $bin_name) {

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

        $q->bindParam(':id', $this->id, PDO::PARAM_STR);
        $q->bindParam(':created_at', date("Y-m-d H:i:s", strtotime($this->created_at)), PDO::PARAM_STR);
        $q->bindParam(':from_user_name', $this->user->screen_name, PDO::PARAM_STR);
        $q->bindParam(':from_user_id', $this->user->id, PDO::PARAM_STR);
        $q->bindParam(':from_user_lang', $this->user->lang, PDO::PARAM_STR);
        $q->bindParam(':from_user_tweetcount', $this->user->statuses_count, PDO::PARAM_STR);
        $q->bindParam(':from_user_followercount', $this->user->followers_count, PDO::PARAM_INT);
        $q->bindParam(':from_user_friendcount', $this->user->friends_count, PDO::PARAM_INT);
        $q->bindParam(':from_user_realname', $this->user->name, PDO::PARAM_STR);
        $q->bindParam(':source', $this->source, PDO::PARAM_STR);
        $q->bindParam(':location', $this->user->location, PDO::PARAM_STR);
        $geo_lat = $this->geo ? (string) $this->geo->coordinates[0] : null;
        $geo_lng = $this->geo ? (string) $this->geo->coordinates[1] : null;
        $q->bindParam(':geo_lat', $geo_lat, PDO::PARAM_STR);
        $q->bindParam(':geo_lng', $geo_lng, PDO::PARAM_STR);
        $q->bindParam(':text', $this->text, PDO::PARAM_STR);
        $q->bindParam(':to_user_id', $this->in_reply_to_user_id, PDO::PARAM_STR);
        $q->bindParam(':to_user_name', $this->in_reply_to_screen_name, PDO::PARAM_STR);
        $q->bindParam(':in_reply_to_status_id', $this->in_reply_to_status_id, PDO::PARAM_STR);

        $saved_tweet = $q->execute();

        if ($this->hashtags) {
            foreach ($this->hashtags as $hashtag) {
                $q = $dbh->prepare("REPLACE INTO " . $bin_name . '_hashtags' . "
					(tweet_id, created_at, from_user_name, from_user_id, text) 
					VALUES (:tweet_id, :created_at , :from_user_name, :from_user_id, :text)");

                $q->bindParam(':tweet_id', $this->id, PDO::PARAM_STR);
                $q->bindParam(':created_at', date("Y-m-d H:i:s", strtotime($this->created_at)), PDO::PARAM_STR);
                $q->bindParam(':from_user_name', $this->user->screen_name, PDO::PARAM_STR);
                $q->bindParam(':from_user_id', $this->user->id, PDO::PARAM_STR);
                $q->bindParam(':text', $hashtag->text, PDO::PARAM_STR);

                $saved_hashtags = $q->execute();
            }
        }

        if ($this->urls) {
            foreach ($this->urls as $url) {
                $q = $dbh->prepare("REPLACE INTO " . $bin_name . '_urls' . "
					(tweet_id, created_at, from_user_name, from_user_id, url, url_expanded) 
					VALUES (:tweet_id, :created_at , :from_user_name, :from_user_id, :url, :url_expanded)");

                $q->bindParam(':tweet_id', $this->id, PDO::PARAM_STR);
                $q->bindParam(':created_at', date("Y-m-d H:i:s", strtotime($this->created_at)), PDO::PARAM_STR);
                $q->bindParam(':from_user_name', $this->user->screen_name, PDO::PARAM_STR);
                $q->bindParam(':from_user_id', $this->user->id, PDO::PARAM_STR);
                $q->bindParam(':url', $url->url, PDO::PARAM_STR);
                $q->bindParam(':url_expanded', $url->expanded_url, PDO::PARAM_STR);

                $saved_urls = $q->execute();
            }
        }

        if ($this->user_mentions) {
            foreach ($this->user_mentions as $mention) {
                $q = $dbh->prepare("REPLACE INTO " . $bin_name . '_mentions' . "
					(tweet_id, created_at, from_user_name, from_user_id, to_user, to_user_id) 
					VALUES (:tweet_id, :created_at , :from_user_name, :from_user_id, :to_user, :to_user_id)");

                $q->bindParam(':tweet_id', $this->id, PDO::PARAM_STR);
                $q->bindParam(':created_at', date("Y-m-d H:i:s", strtotime($this->created_at)), PDO::PARAM_STR);
                $q->bindParam(':from_user_name', $this->user->screen_name, PDO::PARAM_STR);
                $q->bindParam(':from_user_id', $this->user->id, PDO::PARAM_STR);
                $q->bindParam(':to_user', $mention->screen_name, PDO::PARAM_STR);
                $q->bindParam(':to_user_id', $mention->id, PDO::PARAM_STR);

                $saved_mentions = $q->execute();
            }
        }

        return $saved_tweet;
    }

    public static function create_bin(PDO $dbh, $bin_name) {

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
    }

}

// @todo, extract into other import file
class TwitterRelations {

    private $list;
    private $id;
    private $type;
    private $screen_name;
    private $observed_at;

    public function __construct($screen_name_or_id, array $relations, $type, $observed_at) {

        if (is_numeric($screen_name_or_id)) {
            $this->id = $screen_name_or_id;
        } else {
            $this->screen_name = $screen_name_or_id;
        }

        $this->list = array_unique($relations);
        $this->type = $type;
        $this->observed_at = $observed_at;
    }

    public function save(PDO $dbh, $bin_name) {
        if (!$this->id) {
            // Try to find this users id
            $q = $dbh->prepare("SELECT from_user_id FROM " . $bin_name . "_tweets " .
                    "WHERE from_user_name = :screen_name");

            $q->execute(array(':screen_name' => $this->screen_name));
            $result = $q->fetch(PDO::FETCH_OBJ);
            if ($result && $result->from_user_id) {
                $this->id = $result->from_user_id;
            } else {
                throw new Exception("No matching user id for `screen_name` = 
									$this->screen_name in table $bin_name found.");
            }
        }

        foreach ($this->list as $relation) {
            $q = $dbh->prepare(
                    "REPLACE INTO " . $bin_name . '_relations' . "
				(user1_id, user2_id, type, observed_at)
				VALUES 
				(:user1_id, :user2_id, :type, :observed_at);");

            $q->bindParam(":user1_id", $this->id, PDO::PARAM_INT);
            $q->bindParam(":user2_id", $relation, PDO::PARAM_INT);
            $q->bindParam(":type", $this->type, PDO::PARAM_STR);
            $q->bindParam(":observed_at", $this->observed_at, PDO::PARAM_STR);
            $q->execute();
        }
    }

    public static function create_relations_tables(PDO $dbh, $bin_name) {
        $sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_relations (
		user1_id int(11),
		user2_id int(11),
		type varchar(255),
		observed_at datetime,
		PRIMARY KEY (user1_id, user2_id, type)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8";

        if ($dbh->exec($sql)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

}

class UrlCollection implements IteratorAggregate {

    private $items = array();
    private $count = 0;

    public function getIterator() {
        return new Iterator($this->items);
    }

    public function add($object) {
        $this->items[$this->count++] = $object;
    }

}

?>