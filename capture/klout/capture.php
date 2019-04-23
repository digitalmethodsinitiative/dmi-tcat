<?php

// only run from command line
if ($argc < 1)
    die;

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../analysis/common/functions.php';
require_once __DIR__ . '/KloutAPIv2-PHP/KloutAPIv2.class.php';

while (1) {



    $datasets = array(); // list querybins for which to get user klout scores

    $errors = 0;

    foreach ($datasets as $dataset) {

        $esc['mysql']['dataset'] = $dataset;

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
          http_code smallint,
          PRIMARY KEY (id),
          KEY `from_user_name` (`from_user_name`),
          KEY `from_user_id` (`from_user_id`),
          KEY `kloutid` (`kloutid`),
          KEY `last_updated` (`last_updated`)
          ) ENGINE=TokuDB COMPRESSION=TOKUDB_LZMA  DEFAULT CHARSET=utf8";
          $sqlresults = mysql_query($sql) or die(mysql_error());
          }

// select user names for which we do not have a klout score yet or for which the klout score is outdated
        $sql = "SELECT DISTINCT(t.from_user_id), t.from_user_name, k.kloutid FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= "LEFT JOIN " . $esc['mysql']['dataset'] . "_klout k ON t.from_user_id = k.from_user_id ";
        $sql .= "WHERE (k.from_user_id IS NULL OR k.last_updated <= '" . strftime("%Y-%m-%d %H:%M:%S", date('U') - (86400 * 31)) . "')"; // @todo and http_code != 404 (spam acounts)
        //print $sql; die;
        
        $rec = mysql_query($sql);
        if (mysql_num_rows($rec) > 0) {
            print "Doing $dataset:\n";
            while ($res = mysql_fetch_assoc($rec)) {

                $kloutid = $kloutScore = $kloutDayChanges = $kloutWeekChanges = $kloutMonthChanges = $http_code = 0;
                $kloutTopics = array();

                $from_user_id = $res['from_user_id'];
                $from_user_name = $res['from_user_name'];
                $kloutid = $res['kloutid'];

                // klout score
                $klout = new KloutAPIv2($kloutapi_key); // http://klout.com/s/developers/v2
                $network = "twitter";
                if (empty($kloutid))
                    $kloutid = $klout->KloutIDLookupByID("tw", $from_user_id);
                /*
                 * var_export($klout);
                 * print "\n";
                 * var_export($kloutid);
                 * print "\n"; 
                 */
                $http_code = $klout->info['http_code'];
                print strftime("%Y-%m-%d %H:%M:%S", date('U')) . " - " . $from_user_name . " - " . var_export($kloutid, 1) . " - " . $http_code . "\n";
                if ($http_code != 200) {
                    if ($http_code == 403 || $http_code == 503 || $http_code == 504)
                        $errors++;
                    if ($errors > 2)
                        die("died because of " . $http_code . "\n");
                }

                if (!empty($kloutid)) { // @todo this assumes that on line 60 of KloutAPIv2-PHP/KloutAPIv2.class.php the following is added: if($ResultString === NULL) return $ResultString;
                    $userScore = json_decode($klout->KloutUserScore($kloutid));
                    $kloutScore = ceil($userScore->score);
                    $kloutDayChanges = $userScore->scoreDelta->dayChange;
                    $kloutWeekChanges = $userScore->scoreDelta->weekChange;
                    $kloutMonthChanges = $userScore->scoreDelta->monthChange;


                    /*
                      // topics
                      $result = $klout->KloutUserTopics($kloutid);
                      $topics = json_decode($result);

                      foreach ($topics as $topic) {
                      //$slug = $topic->slug;
                      //$imageUrl = $topic->imageUrl;
                      //$dislayName = $topic->displayName;
                      $kloutTopics[] = $topic->displayName;
                      }


                      // persons influencers / influencees
                      $result = $klout->KloutUserInfluence($kloutid);
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
                $insert = "REPLACE INTO " . $esc['mysql']['dataset'] . "_klout ";
                $insert .= "(from_user_id, from_user_name, kloutid, kloutscore, daychanges, weekchanges, monthchanges, topics, last_updated, http_code) ";
                $insert .= "VALUES ($from_user_id, '$from_user_name', '$kloutid', '$kloutScore','$kloutDayChanges','$kloutWeekChanges','$kloutMonthChanges','$kloutTopics','$lastUpdated', '$http_code')";
                //print $insert . "\n";
                mysql_query($insert) or die(mysql_error());
                usleep(50000); // max 20 calls per second
            }
        }
    }
    print "sleeping\n\n";
    sleep(2);
}
?>
