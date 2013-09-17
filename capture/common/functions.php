<?php
error_reporting(E_ALL);

function checktables() {

    global $querybins;

    $tables = array();

    $sql = "SHOW TABLES LIKE '%_tweets'";
    $sqlresults = mysql_query($sql);

    while ($data = mysql_fetch_row($sqlresults)) {
        $tables[] = $data[0];
    }

    foreach ($querybins as $bin => $content) {
        if (!in_array($bin . "_tweets", $tables)) {
            $dbh = pdo_connect();
            create_bin($bin,$dbh);
        }
    }
}

function pdo_connect() {
    global $dbuser, $dbpass, $database, $hostname;

    $dbh = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8", $dbuser, $dbpass);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $dbh;
}

function create_bin($bin_name, $dbh) {

    $sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_hashtags (
		id int(11) NOT NULL AUTO_INCREMENT,
		tweet_id bigint(20) NOT NULL,
		created_at datetime,
		from_user_name varchar(255),
		from_user_id int(11),
		`text` varchar(255),
		PRIMARY KEY (id),
                KEY `created_at` (`created_at`),
		KEY `tweet_id` (`tweet_id`),
		KEY `text` (`text`),
                KEY `from_user_name` (`from_user_name`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8";

    $create_hashtags = $dbh->prepare($sql);
    $create_hashtags->execute();

    $sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_mentions (
		id int(11) NOT NULL AUTO_INCREMENT,
		tweet_id bigint(20) NOT NULL,
		created_at datetime,
		from_user_name varchar(255),
		from_user_id int(11),
		to_user varchar(255),
		to_user_id int(11),
		PRIMARY KEY (id),
                KEY `created_at` (`created_at`),
		KEY `tweet_id` (`tweet_id`),
                KEY `from_user_name` (`from_user_name`),
                KEY `to_user` (`to_user`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

    $create_mentions = $dbh->prepare($sql);
    $create_mentions->execute();

    $sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_tweets (
		id bigint(20) NOT NULL,
                created_at datetime NOT NULL,
                from_user_name varchar(255) NOT NULL,
                from_user_id int(11) NOT NULL,
                from_user_lang varchar(16),
                from_user_tweetcount int(11),
                from_user_followercount int(11),
                from_user_friendcount int(11),
                from_user_listed int(11),
                from_user_realname varchar(255),
                from_user_utcoffset int(11),
                from_user_timezone varchar(255),
                from_user_description varchar(255),
                from_user_url varchar(2048),
                from_user_verified bool DEFAULT false,
                from_user_profile_image_url varchar(400),
                source varchar(512),
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
                KEY `from_user_lang` (`from_user_lang`),
                KEY `retweet_id` (`retweet_id`),
                KEY `in_reply_to_status_id` (`in_reply_to_status_id`),
                FULLTEXT KEY `from_user_description` (`from_user_description`),
                FULLTEXT KEY `text` (`text`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8";

    $create_tweets = $dbh->prepare($sql);
    $create_tweets->execute();

    $sql = "CREATE TABLE IF NOT EXISTS " . $bin_name . "_urls (
		id int(11) NOT NULL AUTO_INCREMENT,
		tweet_id bigint(20) NOT NULL,
		created_at datetime,
		from_user_name varchar(255),
		from_user_id int(11),
		url varchar(2048),
		url_expanded varchar(2048),
		url_followed varchar(2048),
		domain varchar(2048),
		error_code varchar(64),
		PRIMARY KEY (id),
                KEY `tweet_id` (`tweet_id`),                
                KEY `created_at` (`created_at`),
                FULLTEXT KEY `url_followed` (`url_followed`),
                KEY `url_expanded` (`url_expanded`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

    $create_urls = $dbh->prepare($sql);
    $create_urls->execute();
}

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
    public $retweet_id = null;
    public $favorite_count;
    public $filter_level;
    public $timezone;
    public $lang;

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
        } elseif ($name == "_id") {
            $this->id = $value;
        } elseif ($name == "random_number" || $name == "withheld_scope" || $name == "status" || $name == "withheld_in_countries" || $name == "withheld_copyright") {
            print $name . "=" . $value . " not available as a database field\n";
            return;
        } elseif ($name == "metadata") {
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
			to_user_id, to_user_name,in_reply_to_status_id, 
                        from_user_listed, from_user_utcoffset, from_user_timezone, from_user_description,from_user_url,from_user_verified,
                        retweet_id,retweet_count,favorite_count,filter_level,lang) 
			VALUES 
			(:id, :created_at, :from_user_name, :from_user_id, :from_user_lang,
			:from_user_tweetcount, :from_user_followercount, :from_user_friendcount, 
			:from_user_realname, :source, :location, :geo_lat, :geo_lng, :text, 
			:to_user_id, :to_user_name, :in_reply_to_status_id,
                        :from_user_listed, :from_user_utcoffset, :from_user_timezone, :from_user_description, :from_user_url, :from_user_verified,
                        :retweet_id, :retweet_count, :favorite_count, :filter_level,:lang
                        ) 
			;");
        //var_export($this);
        $q->bindParam(':id', $this->id, PDO::PARAM_STR); //
        $date = date("Y-m-d H:i:s", strtotime($this->created_at));
        $q->bindParam(':created_at', $date, PDO::PARAM_STR); //
        $q->bindParam(':from_user_name', $this->user->screen_name, PDO::PARAM_STR); //
        $q->bindParam(':from_user_id', $this->user->id, PDO::PARAM_STR);    //
        $q->bindParam(':from_user_lang', $this->user->lang, PDO::PARAM_STR); //
        $q->bindParam(':from_user_tweetcount', $this->user->statuses_count, PDO::PARAM_STR); //
        $q->bindParam(':from_user_followercount', $this->user->followers_count, PDO::PARAM_INT); //
        $q->bindParam(':from_user_friendcount', $this->user->friends_count, PDO::PARAM_INT); //
        $q->bindParam(':from_user_realname', $this->user->name, PDO::PARAM_STR); //
        $q->bindParam(':source', $this->source, PDO::PARAM_STR); //
        $q->bindParam(':location', $this->user->location, PDO::PARAM_STR); //
        $geo_lat = $this->geo ? (string) $this->geo->coordinates[0] : 'null'; //
        $geo_lng = $this->geo ? (string) $this->geo->coordinates[1] : 'null'; //
        $q->bindParam(':geo_lat', $geo_lat, PDO::PARAM_STR); //
        $q->bindParam(':geo_lng', $geo_lng, PDO::PARAM_STR); //
        $q->bindParam(':text', $this->text, PDO::PARAM_STR); //
        $q->bindParam(':to_user_id', $this->in_reply_to_user_id, PDO::PARAM_STR); //
        $q->bindParam(':to_user_name', $this->in_reply_to_screen_name, PDO::PARAM_STR); //
        $q->bindParam(':in_reply_to_status_id', $this->in_reply_to_status_id, PDO::PARAM_STR); //

        $q->bindParam(':from_user_listed', $this->user->listed_count, PDO::PARAM_INT); //
        $q->bindParam(':from_user_utcoffset', $this->user->utcoffset, PDO::PARAM_STR); //  
        $q->bindParam(':from_user_timezone', $this->user->timezone, PDO::PARAM_STR); //   
        $q->bindParam(':from_user_description', $this->user->description, PDO::PARAM_STR); //
        $q->bindParam(':from_user_url', $this->user->url, PDO::PARAM_STR); //     
        $q->bindParam(':from_user_verified', $this->user->verified, PDO::PARAM_STR); //
        $retweet_id = $this->retweeted_status ? (string) $this->retweeted_status->id_str : null;
        $q->bindParam(':retweet_id', $retweet_id, PDO::PARAM_STR); //    
        $q->bindParam(':retweet_count', $this->retweet_count, PDO::PARAM_STR); // 
        $q->bindParam(':favorite_count', $this->favorite_count, PDO::PARAM_STR); //
        $q->bindParam(':filter_level', $this->filter_level, PDO::PARAM_STR); //
        $q->bindParam(':lang', $this->lang, PDO::PARAM_STR); //


        $saved_tweet = $q->execute();

        if ($this->hashtags) {
            foreach ($this->hashtags as $hashtag) {
                $q = $dbh->prepare("REPLACE INTO " . $bin_name . '_hashtags' . "
					(tweet_id, created_at, from_user_name, from_user_id, text) 
					VALUES (:tweet_id, :created_at , :from_user_name, :from_user_id, :text)");

                $q->bindParam(':tweet_id', $this->id, PDO::PARAM_STR);
                $date = date("Y-m-d H:i:s", strtotime($this->created_at));
                $q->bindParam(':created_at', $date, PDO::PARAM_STR);
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
                $date = date("Y-m-d H:i:s", strtotime($this->created_at));
                $q->bindParam(':created_at', $date, PDO::PARAM_STR);
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
                $date = date("Y-m-d H:i:s", strtotime($this->created_at));
                $q->bindParam(':created_at', $date, PDO::PARAM_STR);
                $q->bindParam(':from_user_name', $this->user->screen_name, PDO::PARAM_STR);
                $q->bindParam(':from_user_id', $this->user->id, PDO::PARAM_STR);
                $q->bindParam(':to_user', $mention->screen_name, PDO::PARAM_STR);
                $q->bindParam(':to_user_id', $mention->id, PDO::PARAM_STR);

                $saved_mentions = $q->execute();
            }
        }

        return $saved_tweet;
    }

}

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
		user1_id int(11) NOT NULL,
		user2_id int(11) NOT NULL,
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
