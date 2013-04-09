<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/Gexf.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics :: Mention - Hashtags</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    </head>

    <body>

        <h1>Twitter Analytics :: Mention - Hashtags</h1>

        <?php
        chartTweetsPerDay();
        $counts = chartTweetsPerUser();
        $total = array_sum($counts);
        print "<br><b>Stats</b>:<br>$total tweets in data set<br>min " . min($counts) . " max " . max($counts) . " avg " . round(array_sum($counts) / count($counts)) . " median " . median($counts) . "<bR>";

        $offsetdate = '2012-02-15 00:00:00';
        $sql = "SELECT count(created_at) FROM user_ambtenaar20_reduced_tweets WHERE created_at >= '$offsetdate'";
        $sqlresults = mysql_query($sql);
        $res = mysql_fetch_array($sqlresults);
        print "nr of tweets since $offsetdate: " . $res[0] . " ( " . round((($res[0] * 100) / $total), 2) . "% )<br>";

        $startdate = '2012-01-01 00:00:00';
        $enddate = '2013-01-01 00:00:00';
        $sql = "SELECT count(created_at) FROM user_ambtenaar20_reduced_tweets WHERE created_at >= '$startdate' AND created_at < '$enddate'";
        $sqlresults = mysql_query($sql);
        $res = mysql_fetch_array($sqlresults);
        print "nr of tweets between $startdate and $enddate: " . $res[0] . " ( " . round((($res[0] * 100) / $total), 2) . "% )<br>";
        ?>
        <div id="chartTweetsPerUser" style="width: 900px; height: 500px;"></div>
        <div id="chartTweetsPerDay" style="width: 900px; height: 500px;"></div>

        <?php
        $sql = "SELECT from_user_name, COUNT(created_at) AS c, MIN(created_at) AS minc, MAX(created_at) AS maxc FROM user_ambtenaar20_reduced_tweets ";
        $sql .= "GROUP BY from_user_name ORDER BY minc DESC";

        $sqlresults = mysql_query($sql);
        print "<b>Users with more than 3000 tweets</b>:<br><table><tr><td>user</td><td>count</td><td>min</td><td>max</td></tr>";
        $discards = 0;
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $user = $res['from_user_name'];
            $count = $res['c'];
            $min = $res['minc'];
            $max = $res['maxc'];

            //if ($min > $offsetdate) {
            if ($count > 3000) { // heavy users
                print "<tr><td><a href='http://twitter.com/$user' target='_blank'>$user</a></td><td>$count</td><td>$min</td><td>$max</td></tr>";
                $discards+=$count;
            }
            //else    // just started tweeting
            //    print "<tr><td>$user</td><td>$count</td><td>$min</td><td>$max</td><td>0</td></tr>";
            //}
        }
        print "</table>";
        print "tweets involved in this list $discards<bR>";
        ?>
    </body>
</html>

<?php

function chartTweetsPerUser() {
    ?>

    <script type="text/javascript">
        google.load("visualization", "1", {packages:["corechart"]});
        google.setOnLoadCallback(drawChart);
        function drawChart() {
            var data = google.visualization.arrayToDataTable([
                ['user','number of tweets'],
    <?php
    $sql = "SELECT from_user_name,count(id) AS cid FROM user_ambtenaar20_reduced_tweets GROUP BY from_user_name ORDER BY cid DESC";
    $sqlresults = mysql_query($sql);
    while ($res = mysql_fetch_array($sqlresults)) {
        $counts[] = $res[1];
        print "['" . $res[0] . "'," . $res[1] . "],\n";
    }
    ?>
            ]);
            var options = {title: 'Tweets per user'};
            var chart = new google.visualization.LineChart(document.getElementById('chartTweetsPerUser'));
            chart.draw(data, options);
        }
    </script>
    <?php
    return $counts;
}

function chartTweetsPerDay() {
    ?>

    <script type="text/javascript">
        google.load("visualization", "1", {packages:["corechart"]});
        google.setOnLoadCallback(drawChart);
        function drawChart() {
            var data = google.visualization.arrayToDataTable([
                ['user','number of tweets'],
    <?php
    $sql = "SELECT DATE_FORMAT(created_at,'%Y-%m-%d') AS day, count(id) AS cid FROM user_ambtenaar20_reduced_tweets GROUP BY day ORDER BY day";
    $sqlresults = mysql_query($sql);
    while ($res = mysql_fetch_array($sqlresults)) {
        $counts[] = $res[1];
        print "['" . $res[0] . "'," . $res[1] . "],\n";
    }
    ?>
            ]);
            var options = {title: 'Tweets per day'};
            var chart = new google.visualization.LineChart(document.getElementById('chartTweetsPerDay'));
            chart.draw(data, options);
        }
    </script>

    <?php
}
?>