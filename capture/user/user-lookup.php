<?php

define('LOOKUP_SIZE', 100);

require_once '../../config.php';
require_once BASE_FILE . 'analysis/common/functions.php';

require BASE_FILE.'capture/common/tmhOAuth/tmhOAuth.php';
require BASE_FILE.'capture/common/tmhOAuth/tmhUtilities.php';
$tmhOAuth = new tmhOAuth(array(
            "consumer_key" => $twitter_consumer_key,
            "consumer_secret" => $twitter_consumer_secret,
            "user_token" => $twitter_user_token,
            "user_secret" => $twitter_user_token
        ));
$esc['mysql']['dataset'] = "penw"; // @todo, think of way on how to know what bin to do

function check_rate_limit($response) {
    $headers = $response['headers'];
    print 'x-ratelimit-remaining ' . print_r($headers['x-ratelimit-remaining'], 1) . "\n";
    if ($headers['x-ratelimit-remaining'] == 0) :
        $reset = $headers['x-ratelimit-remaining'];
        $sleep = time() - $reset;
        echo 'rate limited. reset time is ' . $reset . PHP_EOL;
        echo 'sleeping for ' . $sleep . ' seconds';
        sleep($sleep);
    endif;
}

// create table if not exist
$result = mysql_query("SHOW TABLES LIKE '" . $esc['mysql']['dataset'] . "_user'");
if (mysql_num_rows($result) == 0) {
    $sql = "CREATE TABLE `" . $esc['mysql']['dataset'] . "_user` (
            `twitter_id` int(10) unsigned NOT NULL,
            `id_str` varchar(1024) NOT NULL,
            `created_at` timestamp NOT NULL default '0000-00-00 00:00:00',
            `last_updated` timestamp NOT NULL default '0000-00-00 00:00:00',

            `name` varchar(80) NOT NULL,
            `screen_name` varchar(30) NOT NULL,
            `location` varchar(120) default NULL,
            `description` varchar(640) default NULL,
            `profile_image_url` varchar(400) NOT NULL,
            `url` varchar(100) default NULL,
            `protected` tinyint(1) default '0',
            `verified` tinyint(1) default '0',

            `followers_count` int(10) unsigned NOT NULL,
            `friends_count` int(10) unsigned NOT NULL,

            `favourites_count` int(10) unsigned NOT NULL,
            `statuses_count` int(10) unsigned default '0',
            `listed_count` int(10) unsigned default '0',  

            `profile_background_color` varchar(8) default NULL,
            `profile_text_color` varchar(8) default NULL,
            `profile_link_color` varchar(8) default NULL,
            `profile_sidebar_fill_color` varchar(8) default NULL,
            `profile_sidebar_border_color` varchar(8) default NULL,
            `profile_image_url` varchar(400) default NULL,
            `profile_image_url_https` varchar(400) default NULL,
            `profile_background_image_url` varchar(400) default NULL,
            `profile_background_image_url_https` varchar(400) default NULL,
            `profile_background_tile` varchar(5) default NULL,
            `profile_use_background_image` tinyint(1) default '0',
            `default_profile_image` varchar(400) default NULL,

            `utc_offset` int(10) default NULL,
            `time_zone` varchar(120) default NULL,
            `lang` char(2) default NULL,

            `default_profile` tinyint(1) default '0', 
            `geo_enabled` tinyint(1) default '0',
            `contributors_enabled` tinyint(1) default '0',
            `is_translator` tinyint(1) default '0',
            `notifications` tinyint(1) default '0',

            PRIMARY KEY  (`twitter_id`),
            UNIQUE KEY `screen_name` (`screen_name`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
    $rec = mysql_query($sql) or die(mysql_error());
}

// get ids
$sql = "SELECT DISTINCT(t.from_user_id) FROM " . $esc['mysql']['dataset'] . "_tweets t ";
$sql .= "LEFT JOIN " . $esc['mysql']['dataset'] . "_user u ON t.from_user_id = u.twitter_id ";
$sql .= "WHERE u.twitter_id IS NULL OR u.last_updated <= '" . strftime("%Y-%m-%d %H:%M:%S", date('U') - (86400 * 31)) . "'";
$rec = mysql_query($sql);
$ids = array();
while ($res = mysql_fetch_assoc($rec)) {
    $ids[] = $res['from_user_id'];
}

// lookup users
$paging = ceil(count($ids) / LOOKUP_SIZE);
$users = array();
for ($i = 0; $i < $paging; $i++) {
    print strftime("%Y-%m-%d %H:%M:%S",date('U'))." doing $i\n";
    $set = array_slice($ids, $i * LOOKUP_SIZE, LOOKUP_SIZE);

    $tmhOAuth->request('GET', $tmhOAuth->url('1.1/users/lookup'), array(
        'user_id' => implode(',', $set)
    ));
    //var_export($set);
var_dump($tmhOAuth->response);
    sleep(1);

    // check the rate limit
    check_rate_limit($tmhOAuth->response);

    if ($tmhOAuth->response['code'] == 200) {
        $data = json_decode($tmhOAuth->response['response'], true);
        //$users = array_merge($users, $data);
        foreach ($data as $user) {
            $sql = "REPLACE INTO " . $esc['mysql']['dataset'] . "_user ";
            $sql .= "(time_zone, created_at, last_updated, name, profile_background_image_url_https, profile_background_image_url, profile_link_color, notifications, twitter_id, listed_count, geo_enabled, screen_name, profile_use_background_image, followers_count, profile_text_color, url, lang, utc_offset, location, profile_sidebar_border_color, default_profile_image, is_translator, protected, favourites_count, description, profile_background_tile, statuses_count, profile_sidebar_fill_color, profile_image_url_https, verified, friends_count, profile_image_url, profile_background_color, contributors_enabled) ";
            $sql .= " VALUES ('" . mysql_real_escape_string($user['time_zone']) . "', '" . strftime("%Y-%m-%d %H:%M:%S", strtotime($user['created_at'])) . "', '" . strftime("%Y-%m-%d %H:%M:%S", date('U')) . "', '" . mysql_real_escape_string($user['name']) . "', '" . mysql_real_escape_string($user['profile_background_image_url_https']) . "', '" . mysql_real_escape_string($user['profile_background_image_url']) . "', '" . mysql_real_escape_string($user['profile_link_color']) . "', '" . mysql_real_escape_string($user['notifications']) . "', '" . mysql_real_escape_string($user['id']) . "', '" . mysql_real_escape_string($user['listed_count']) . "', '" . mysql_real_escape_string($user['geo_enabled']) . "', '" . mysql_real_escape_string($user['screen_name']) . "', '" . mysql_real_escape_string($user['profile_use_background_image']) . "', '" . mysql_real_escape_string($user['followers_count']) . "', '" . mysql_real_escape_string($user['profile_text_color']) . "', '" . mysql_real_escape_string($user['url']) . "', '" . mysql_real_escape_string($user['lang']) . "', '" . mysql_real_escape_string($user['utc_offset']) . "', '" . mysql_real_escape_string($user['location']) . "', '" . mysql_real_escape_string($user['profile_sidebar_border_color']) . "', '" . mysql_real_escape_string($user['default_profile_image']) . "', '" . mysql_real_escape_string($user['is_translator']) . "', '" . mysql_real_escape_string($user['protected']) . "', '" . mysql_real_escape_string($user['favourites_count']) . "', '" . mysql_real_escape_string($user['description']) . "', '" . mysql_real_escape_string($user['profile_background_tile']) . "', '" . mysql_real_escape_string($user['statuses_count']) . "', '" . mysql_real_escape_string($user['profile_sidebar_fill_color']) . "', '" . mysql_real_escape_string($user['profile_image_url_https']) . "', '" . mysql_real_escape_string($user['verified']) . "', '" . mysql_real_escape_string($user['friends_count']) . "', '" . mysql_real_escape_string($user['profile_image_url']) . "', '" . mysql_real_escape_string($user['profile_background_color']) . "', '" . mysql_real_escape_string($user['contributors_enabled']) . "')";
            mysql_query($sql) or die(mysql_error());
        }
    } else {
        echo $tmhOAuth->response['response'];
        break;
    }
}
?>

