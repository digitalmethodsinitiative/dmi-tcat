<?php
require_once './common/config.php';
require_once './common/functions.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics :: Tweet stats</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>Twitter Analytics :: Tweet Stats</h1>

        <?php
// => gexf
// => time
        validate_all_variables();
// Output format: {dataset}_{query}_{startdate}_{enddate}_{from_user_name}_{output type}.{filetype}

        $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
        $filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . "_tweetStats.csv";

        $numtweets = $numlinktweets = $numTweetsWithHashtag = $numTweetsWithMentions = $numRetweets = $numReplies = array();

        // tweets in subset
        $sql = "SELECT count(distinct(t.id)) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " GROUP BY datepart ORDER BY datepart ASC";
        $sqlresults = mysql_query($sql);
        while ($data = mysql_fetch_assoc($sqlresults)) {
            $numtweets[$data['datepart']] = $data["count"];
        }

        // tweet containing links
        $sql = "SELECT count(distinct(t.id)) AS count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t ";
        $where = "u.tweet_id = t.id AND ";
        $sql .= sqlSubset($where);
        $sql .= " GROUP BY datepart ORDER BY datepart ASC";
        //print $sql."<Br>";
        $sqlresults = mysql_query($sql);
        if ($sqlresults && mysql_num_rows($sqlresults) > 0) {
            while ($res = mysql_fetch_assoc($sqlresults)) {
                $numlinktweets[$res['datepart']] = $res['count'];
            }
        }

        // number of tweets with hashtags
        $sql = "SELECT count(distinct(h.tweet_id)) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND t.id = h.tweet_id ";
        $sql .= " GROUP BY datepart ORDER BY datepart ASC";
        //print $sql . "<br>";
        $sqlresults = mysql_query($sql);
        if ($sqlresults && mysql_num_rows($sqlresults) > 0) {
            while ($data = mysql_fetch_assoc($sqlresults)) {
                $numTweetsWithHashtag[$data['datepart']] = $data["count"];
            }
        }

        // number of tweets with mentions
        $sql = "SELECT count(distinct(m.tweet_id)) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND t.id = m.tweet_id ";
        $sql .= " GROUP BY datepart ORDER BY datepart ASC";
        //print $sql . "<br>";
        $sqlresults = mysql_query($sql);
        if ($sqlresults && mysql_num_rows($sqlresults) > 0) {
            while ($data = mysql_fetch_assoc($sqlresults)) {
                $numTweetsWithMentions[$data['datepart']] = $data["count"];
            }
        }

        // number of retweets 
        $sql = "SELECT count(distinct(id)) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND retweet_id != '' AND retweet_id != '0'";
        $sql .= " GROUP BY datepart ORDER BY datepart ASC";
        //print $sql."<br>";
        $sqlresults = mysql_query($sql);
        if ($sqlresults && mysql_num_rows($sqlresults) > 0) {
            while ($data = mysql_fetch_assoc($sqlresults)) {
                $numretweets[$data['datepart']] = $data["count"];
            }
        }

        // number of replies 
        $sql = "SELECT count(distinct(id)) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND in_reply_to_status_id != ''";
        $sql .= " GROUP BY datepart ORDER BY datepart ASC";
        //print $sql."<br>";
        $sqlresults = mysql_query($sql);
        if ($sqlresults && mysql_num_rows($sqlresults) > 0) {
            while ($data = mysql_fetch_assoc($sqlresults)) {
                $numReplies[$data['datepart']] = $data["count"];
            }
        }

        echo '<fieldset class="if_parameters">';
        echo '<legend>Tweet stats </legend>';
        echo '<p>';
        print "<table><thead><th>Date</th><th>Number of tweets</th><th>Number of tweets with links</th><th>Number of tweets with hashtags</th><th>Number of tweets with mentions</th><th>Number of retweets</th><th>Number of replies</th></tr></thead><tbody>";
        foreach ($numtweets as $date => $tweetcount) {
            $linkcount = $hashtagcount = $mentioncount = $retweetcount = $replycount = 0;
            if (isset($numlinktweets[$date]))
                $linkcount = $numlinktweets[$date];
            if (isset($numTweetsWithHashtag[$date]))
                $hashtagcount = $numTweetsWithHashtag[$date];
            if (isset($numTweetsWithMentions[$date]))
                $mentioncount = $numTweetsWithMentions[$date];
            if (isset($numretweets[$date]))
                $retweetcount = $numretweets[$date];
            if (isset($numReplies[$date]))
                $replycount = $numReplies[$date];
            print "<tr><td>$date</td><td>$tweetcount</td><td>$linkcount</td><td>$hashtagcount</td><td>$mentioncount</td><td>$retweetcount</td><td>$replycount</td></tr>";
        }
        print "</tbody></table>";
        echo '</p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
