<?php

define('LOOKUP_SIZE', 100);

require 'tmhOAuth/tmhOAuth.php';
require 'tmhOAuth/tmhUtilities.php';
$tmhOAuth = new tmhOAuth(array(
"consumer_key"=> $twitter_consumer_key,
"consumer_secret"=> $twitter_consumer_secret,
"user_token"=> $twitter_user_token,
"user_secret"=> $twitter_user_token
));
$esc['mysql']['dataset'] = "penw"; // @todo, think of way on how to know what bin to do

function check_rate_limit($response) {
  $headers = $response['headers'];
  if ($headers['x-ratelimit-remaining'] == 0) :
    $reset = $headers['x-ratelimit-remaining'];
    $sleep = time() - $reset;
    echo 'rate limited. reset time is ' . $reset . PHP_EOL;
    echo 'sleeping for ' . $sleep . ' seconds';
    sleep($sleep);
  endif;
}

function checkTables() {
global $esc;
// create table if not exist
$result = mysql_query("SHOW TABLES LIKE '" . $esc['mysql']['dataset'] . "_user'");
if (mysql_num_rows($result) == 0) {

$sql = "CREATE TABLE `".$esc['mysql']['dataset']."_user` (
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
}

$select = "SELECT DISTINCT(from_user_id) FROM ".$esc['mysql']['dataset'] . "_tweets t "; // @todo where not in _user
$rec = mysql_query($select);
$ids = array();
while($res = mysql_fetch_assoc($rec)) {
	$ids[] = $res['from_user_id'];
}

// lookup users
$paging = ceil(count($ids) / LOOKUP_SIZE);
$users = array();
for ($i=0; $i < $paging ; $i++) {
  $set = array_slice($ids, $i*LOOKUP_SIZE, LOOKUP_SIZE);

  $tmhOAuth->request('GET', $tmhOAuth->url('1/users/lookup'), array(
    'user_id' => implode(',', $set)
  ));

  // check the rate limit
  check_rate_limit($tmhOAuth->response);

  if ($tmhOAuth->response['code'] == 200) {
    $data = json_decode($tmhOAuth->response['response'], true);
    //$users = array_merge($users, $data);

  } else {
    echo $tmhOAuth->response['response'];
    break;
  }

}

?>

