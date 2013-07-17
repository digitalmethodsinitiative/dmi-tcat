<?php
require_once './common/config.php';
require_once './common/functions.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics :: User list</title>

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
        $filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . "_userList.csv";

        // tweets per user
        $sql = "SELECT t.from_user_id,t.from_user_name,t.from_user_lang,t.from_user_tweetcount,t.from_user_followercount,t.from_user_friendcount,t.from_user_listed,t.from_user_utcoffset,t.from_user_verified,count(distinct(t.id)) AS count FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        //$sql = "SELECT count(distinct(t.id)) AS count, t.from_user_id FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY from_user_id";
        //print $sql . "<br>";
        flush();
        $sqlresults = mysql_query($sql);
        $array = array();
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $array[$res['from_user_name']] = $res;
        }

        // mentioning per user
        $sql = "SELECT m.from_user_name, count(m.from_user_name) as count FROM " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND m.tweet_id = t.id ";
        $sql .= "GROUP BY from_user_name";
        //print $sql . "<br>";
        flush();
        
        $rec = mysql_query($sql);
        $mentioning = array();
        while ($res = mysql_fetch_assoc($rec)) {
            $mentioning[$res['from_user_name']] = $res['count'];
        }
        
        // mentioned per user
        $sql = "SELECT m.to_user, count(m.to_user) as count FROM " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND m.tweet_id = t.id ";
        $sql .= "GROUP BY to_user";
        //print $sql . "<br>";
        flush();
        $rec = mysql_query($sql);
        $mentioned = array();
        while ($res = mysql_fetch_assoc($rec)) {
            $mentioned[$res['to_user']] = $res['count'];
        }

        // hashtags per user
        $sql = "SELECT h.from_user_name, count(h.from_user_name) as count FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND h.tweet_id = t.id ";
        $sql .= "GROUP BY from_user_name";
        //print $sql . "<br>";
        flush();
        $rec = mysql_query($sql);
        $hashtags = array();
        while ($res = mysql_fetch_assoc($rec)) {
            $hashtags[$res['from_user_name']] = $res['count'];
        }

        // tweets with hashtags, per user
        $sql = "SELECT h.from_user_name, count(distinct(h.tweet_id)) as count FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND h.tweet_id = t.id ";
        $sql .= "GROUP BY h.from_user_name";
        //print $sql . "<br>";
        flush();
        $rec = mysql_query($sql);
        $tweetsWithhashtags = array();
        while ($res = mysql_fetch_assoc($rec)) {
            $tweetsWithhashtags[$res['from_user_name']] = $res['count'];
        }


        //print_r($array);
// nr. of hashtags used (in addition to follower & friend count)
        $content = "from_user_id,from_user_name,from_user_lang,from_user_tweetcount (all time user queries),from_user_followercount,from_user_friendcount,from_user_listed,from_user_utcoffset,from_user_verified,tweets in data set,mentioning,mentioned,total nr of hashtags,nr of tweets with hashtags\n";
        foreach ($array as $user => $a) {
            $content .= $a["from_user_id"] . "," . $a["from_user_name"] . "," . $a["from_user_lang"] . "," . $a["from_user_tweetcount"] . "," . $a["from_user_followercount"] . "," . $a["from_user_friendcount"] . "," . $a["from_user_listed"] . "," . $a["from_user_utcoffset"] . "," . $a["from_user_verified"] . "," . $a["count"];
            $content .= ",";
            if (isset($mentioning[$user]))
                $content .= $mentioning[$user];
            else
                $content .= 0;
            $content .= ",";
            if (isset($mentioned[$user]))
                $content .= $mentioned[$user];
            else
                $content .= 0;
            $content .= ",";
            if (isset($hashtags[$user]))
                $content .= $hashtags[$user];
            else
                $content .= 0;
            $content .= ",";
            if (isset($tweetsWithhashtags[$user]))
                $content .= $tweetsWithhashtags[$user];
            else
                $content .= 0;
            $content .= "\n";
        }

        file_put_contents($filename, chr(239) . chr(187) . chr(191) . $content);

        echo '<fieldset class="if_parameters">';
        echo '<legend>User stats</legend>';
        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
