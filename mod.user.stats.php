<?php
require_once './common/config.php';
require_once './common/functions.php';

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics :: User stats</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>Twitter Analytics :: User Stats</h1>

        <?php
// => gexf
// => time
        validate_all_variables();
// Output format: {dataset}_{query}_{startdate}_{enddate}_{from_user_name}_{output type}.{filetype}

        $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
        $filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . "_userStats.csv";
        $filename_locations = str_replace("userStats","locations",$filename);
        $filename_languages = str_replace("userStats","languages",$filename);
        
        // number of users
        $sql = "SELECT count(distinct(from_user_id)) as count FROM " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
        $sql .= sqlSubset();
        //print $sql . "<br>";
        $sqlresults = mysql_query($sql);
        $data = mysql_fetch_assoc($sqlresults);
        $numusers = $data["count"];
        
        // tweets per user
        $sql = "SELECT count(distinct(id)) AS count, from_user_id FROM " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY from_user_id";
        //print $sql . "<br>";
        $sqlresults = mysql_query($sql);
        $array = array();
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $array[$res['from_user_id']] = $res['count'];
        }
        if (!empty($array)) {  // @todo initialize these vars
            $stats['tweets_per_user']['min'] = min($array);
            $stats['tweets_per_user']['max'] = max($array);
            $stats['tweets_per_user']['avg'] = round(average($array), 2);
            $stats['tweets_per_user']['median'] = median($array);
        }

        // users per day
        $sql = "SELECT count(distinct(from_user_id)) AS count, DATE_FORMAT(t.created_at,'%Y-%m-%d') day FROM " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY day";
        //print $sql . "<br>";
        $sqlresults = mysql_query($sql);
        $array = array();
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $array[$res['day']] = $res['count'];
        }
        if (!empty($array)) {   // @todo initialize these vars
            $stats['users_per_day']['min'] = min($array);
            $stats['users_per_day']['max']= max($array);
            $stats['users_per_day']['avg']= round(average($array), 2);
            $stats['users_per_day']['median']= median($array);
        }
        
        $sql = "SELECT count(distinct(u.url)) AS count, u.from_user_id FROM ". $esc['mysql']['dataset'] . "_urls u, ".$esc['mysql']['dataset']."_tweets t WHERE ";
        $sql .= "t.id = u.tweet_id AND ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY from_user_id";
        //print $sql."<br>";
        $sqlresults = mysql_query($sql);
        $array = array();
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $array[$res['from_user_id']] = $res['count'];
        }
        if (!empty($array)) {   // @todo initialize these vars
            $stats['urls_per_user']['min'] = min($array);
            $stats['urls_per_user']['max']= max($array);
            $stats['urls_per_user']['avg']= round(average($array), 2);
            $stats['urls_per_user']['median']= median($array);
        }
        
        // select latest user info
        $sql = "SELECT max(created_at), from_user_id, from_user_followercount, from_user_friendcount, from_user_tweetcount FROM ".$esc['mysql']['dataset']."_tweets t WHERE ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY from_user_id";
        //print $sql."<bR>";
        $sqlresults = mysql_query($sql);
        $array = array();
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $array['followercount'][$res['from_user_id']] = $res['from_user_followercount'];
            $array['friendcount'][$res['from_user_id']] = $res['from_user_friendcount'];
            $array['tweetcount'][$res['from_user_id']] = $res['from_user_tweetcount'];
        }
        if (!empty($array)) {   // @todo initialize these vars
            $stats['followercount']['min'] = min($array['followercount']);
            $stats['followercount']['max']= max($array['followercount']);
            $stats['followercount']['avg']= round(average($array['followercount']), 2);
            $stats['followercount']['median']= median($array['followercount']);
            $stats['friendcount']['min'] = min($array['friendcount']);
            $stats['friendcount']['max']= max($array['friendcount']);
            $stats['friendcount']['avg']= round(average($array['friendcount']), 2);
            $stats['friendcount']['median']= median($array['friendcount']);
            $stats['tweetcount']['min'] = min($array['tweetcount']);
            $stats['tweetcount']['max']= max($array['tweetcount']);
            $stats['tweetcount']['avg']= round(average($array['tweetcount']), 2);
            $stats['tweetcount']['median']= median($array['tweetcount']);
        }
        
        // @todo: aantal retweets
        
        $content = "what,min,max,avg,median\n";
        foreach($stats as $what => $stat) {
            $content.="$what,".$stat['min'].",".$stat['max'].",".$stat['avg'].",".$stat['median']."\n";
        }
        
        file_put_contents($filename,  chr(239) . chr(187) . chr(191) . $content);

        echo '<fieldset class="if_parameters">';
        echo '<legend>User stats</legend>';
        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        
        // interface language, user-defined location
        $sql = "SELECT max(created_at), from_user_id, from_user_lang, location FROM ".$esc['mysql']['dataset']."_tweets t WHERE ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY from_user_id";
        $sqlresults = mysql_query($sql);
        $locations = $languages = array();
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $locations[] = $res['location'];
            $languages[] = $res['from_user_lang'];
        }
        
        $locations = array_count_values($locations);
        arsort($locations);
        $contents = "location,frequency\n";
        foreach($locations as $location => $frequency)
               $contents .= preg_replace("/[\r\n\s\t,]+/im"," ",trim($location)).",$frequency\n";
        
        file_put_contents($filename_locations,  chr(239) . chr(187) . chr(191) . $contents);

        echo '<fieldset class="if_parameters">';
        echo '<legend>Locations </legend>';
        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename_locations)) . '">' . $filename_locations . '</a></p>';
        echo '</fieldset>';
        
        $languages = array_count_values($languages);
        arsort($languages);
        $contents = "language,frequency\n";
        foreach($languages as $language => $frequency)
               $contents .= preg_replace("/[\r\n\s\t]+/","",$language).",$frequency\n";
        
        file_put_contents($filename_languages,  chr(239) . chr(187) . chr(191) . $contents);

        echo '<fieldset class="if_parameters">';
        echo '<legend>Languages </legend>';
        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename_languages)) . '">' . $filename_languages . '</a></p>';
        echo '</fieldset>';
        
        ?>

    </body>
</html>
