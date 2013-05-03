<?php

// only run from command line
if ($argc < 1)
    die;

require_once '../../config.php';
require_once BASE_FILE . 'analysis/common/functions.php';
require_once 'KloutAPIv2-PHP/KloutAPIv2.class.php';

$klout = new KloutAPIv2($kloutapi_key);
$network = "twitter";

// @todo, think of way on how to know what bin to do
$esc['mysql']['dataset'] = "penw";

// create table if not exist
$result = mysql_query("SHOW TABLES LIKE '" . $esc['mysql']['dataset'] . "_klout'");
if (mysql_num_rows($result) == 0) {
    $sql = "CREATE TABLE " . $esc['mysql']['dataset'] . "_klout (
			id int(11) NOT NULL AUTO_INCREMENT,
			last_updated datetime NOT NULL,
			from_user_name varchar(255) NOT NULL,
			from_user_id int(11) NOT NULL,
			kloutid bigint,
                        kloutscore FLOAT NOT NULL,
                        daychanges FLOAT NOT NULL,
                        weekchanges FLOAT NOT NULL,
                        monthchanges FLOAT NOT NULL,
                        topics VARCHAR(255),
			PRIMARY KEY (id),
			KEY `from_user_name` (`from_user_name`),
			KEY `from_user_id` (`from_user_name`),
			KEY `kloutid` (`kloutid`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8";
    $sqlresults = mysql_query($sql) or die(mysql_error());
}

// select user names for which we do not have a klout score yet or for which the klout score is outdated
$sql = "SELECT DISTINCT(t.from_user_id), t.from_user_name, k.kloutid FROM " . $esc['mysql']['dataset'] . "_tweets t ";
$sql .= "LEFT JOIN " . $esc['mysql']['dataset'] . "_klout k ON t.from_user_id = k.from_user_id ";
$sql .= "WHERE k.from_user_id IS NULL OR k.last_updated <= '" . strftime("%Y-%m-%d %H:%M:%S", date('U') - (86400 * 31)) . "'";

$rec = mysql_query($sql);
while ($res = mysql_fetch_assoc($rec)) {

    $kloutid = $kloutScore = $kloutDayChanges = $kloutWeekChanges = $kloutMonthChanges = 0;
    $kloutTopics = array();

    $from_user_id = $res['from_user_id'];
    $from_user_name = $res['from_user_name'];
    $kloutid = $res['kloutid'];

    // klout score
    if (empty($kloutid))
        $kloutid = $klout->KloutIDLookupByName($network, $from_user_name);
    print $from_user_name . " - " . var_export($kloutid, 1) . "\n";


    if (!empty($kloutid)) { // @todo this assumes that on line 60 of KloutAPIv2-PHP/KloutAPIv2.class.php the following is added: if($ResultString === NULL) return $ResultString;

        $result = $klout->KloutUserInfluence($kloutid);

        $kloutScore = ceil($klout->KloutScore($kloutid));
        $kloutDayChanges = $klout->KloutScoreChanges($kloutid, "day");
        $kloutWeekChanges = $klout->KloutScoreChanges($kloutid, "week");
        $kloutMonthChanges = $klout->KloutScoreChanges($kloutid, "month");

        // topics
        $result = $klout->KloutUserTopics($kloutid);
        $topics = json_decode($result);

        foreach ($topics as $topic) {
            //$slug = $topic->slug;
            //$imageUrl = $topic->imageUrl;
            //$dislayName = $topic->displayName;
            $kloutTopics[] = $topic->displayName;
        }

        /*
          // persons influencers / influencees
          $influencers = json_decode($result);
          foreach ($influencers->myInfluencers as $influencer):
          $handle = $klout->KloutUser($influencer->entity->payload->kloutId);
          $handle = json_decode($handle);
          $handle = $handle->nick;
          $score = ceil($influencer->entity->payload->score->score);
          endforeach;

          foreach ($influencers->myInfluencees as $influencer):
          $handle = $klout->KloutUser($influencer->entity->payload->kloutId);
          $handle = json_decode($handle);
          $handle = $handle->nick;
          $score = ceil($influencer->entity->payload->score->score);
          endforeach;

          $influencersCount = $influencers->myInfluencersCount;
          $infuenceesCount = $influencers->myInfluenceesCount;
         * 
         */
    } else
        $kloutid = 0;
    
    $kloutTopics = mysql_real_escape_string(implode(", ", $kloutTopics));

    $lastUpdated = strftime("%Y-%m-%d %H:%M:%S", date('U'));
    $sql2 = "SELECT id FROM " . $esc['mysql']['dataset'] . "_klout k WHERE from_user_name = '" . mysql_real_escape_string($from_user_name) . "'";
    $rec2 = mysql_query($sql2);
    if ($rec2 && mysql_num_rows($rec2) > 0) {
        $res2 = mysql_fetch_assoc($rec2);
        $update = "UPDATE " . $esc['mysql']['dataset'] . "_klout ";
        $update .= "SET from_user_id = $from_user_id, from_user_name = '" . mysql_real_escape_string($from_user_name) . "', kloutid = '$kloutid', kloutscore = '$kloutScore', daychanges = '$kloutDayChanges', weekchanges = '$kloutWeekChanges', monthchanges = '$kloutMonthChanges', topics = '$kloutTopics', last_updated = '$lastUpdated'";
        $update .= "WHERE id = " . $res2['id'];
        print $update . "\n";
        mysql_query($update); // @todo error handling
    } else {
        $insert = "INSERT INTO " . $esc['mysql']['dataset'] . "_klout ";
        $insert .= "(from_user_id, from_user_name, kloutid, kloutscore, daychanges, weekchanges, monthchanges, topics, last_updated) ";
        $insert .= "VALUES ($from_user_id, '$from_user_name', '$kloutid', '$kloutScore','$kloutDayChanges','$kloutWeekChanges','$kloutMonthChanges','$kloutTopics','$lastUpdated')";
        print $insert . "\n";
        mysql_query($insert); // @todo error handling
    }
}
?>
