<?php
require_once './common/config.php';
require_once './common/functions.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Tweet stats</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Tweet stats</h1>

        <?php
        validate_all_variables();

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
        
        $content = "Date,Number of tweets,Number of tweets with links,Number of tweets with hashtags,Number of tweets with mentions,Number of retweets,Number of replies\n";
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
            $content .= "$date,$tweetcount,$linkcount,$hashtagcount,$mentioncount,$retweetcount,$replycount\n";
        }

        $filename = get_filename_for_export("tweetStats");
        file_put_contents($filename,chr(239) . chr(187) . chr(191) . $content);
        echo '<fieldset class="if_parameters">';

        echo '<legend>Tweet stats</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        
        
        
        ?>

    </body>
</html>
