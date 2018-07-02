<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/CSV.class.php';
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
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        $filename = get_filename_for_export("tweetStats");
        $csv = new CSV($filename, $outputformat);

        $numtweets = $numlinktweets = $numTweetsWithHashtag = $numTweetsWithMentions = $numTweetsWithMedia = $numRetweets = $numReplies = array();

        // tweets in subset
        $sql = "SELECT count(t.id) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " GROUP BY datepart ORDER BY datepart ASC";

        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            $numtweets[$data['datepart']] = $data["count"];
        }

        // tweet containing links
        $sql = "SELECT count(distinct(t.id)) AS count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t ";
        $where = "u.tweet_id = t.id AND ";
        $sql .= sqlSubset($where);
        $sql .= " GROUP BY datepart ORDER BY datepart ASC";

        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $numlinktweets[$res['datepart']] = $res['count'];
        }

        // number of tweets with hashtags
        $sql = "SELECT count(distinct(h.tweet_id)) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND t.id = h.tweet_id ";
        $sql .= " GROUP BY datepart ORDER BY datepart ASC";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            $numTweetsWithHashtag[$data['datepart']] = $data["count"];
        }

        // number of tweets with mentions
        $sql = "SELECT count(distinct(m.tweet_id)) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND t.id = m.tweet_id ";
        $sql .= " GROUP BY datepart ORDER BY datepart ASC";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            $numTweetsWithMentions[$data['datepart']] = $data["count"];
        }

        // number of tweets with media uploads
        // NOTICE: if the query itself contains a query by media-URL the sqlSubset() function will also join
        // on the media table, but under a different name (.med). In such a scenario, the query should obviously
        // yield 100% tweets with media
        $sql = "SELECT count(distinct(m.tweet_id)) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_media m, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND t.id = m.tweet_id ";
        $sql .= " GROUP BY datepart ORDER BY datepart ASC";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            $numTweetsWithMedia[$data['datepart']] = $data["count"];
        }

        // number of retweets 
        $sql = "SELECT count(t.id) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND retweet_id != '' AND retweet_id != '0'";
        $sql .= " GROUP BY datepart ORDER BY datepart ASC";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            $numretweets[$data['datepart']] = $data["count"];
        }

        // number of replies 
        $sql = "SELECT count(t.id) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND in_reply_to_status_id != ''";
        $sql .= " GROUP BY datepart ORDER BY datepart ASC";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            $numReplies[$data['datepart']] = $data["count"];
        }
        
        $csv->writeheader(array("Date", "Number of tweets", "Number of tweets with links", "Number of tweets with hashtags", "Number of tweets with mentions", "Number of tweets with media uploads", "Number of retweets", "Number of replies"));
        foreach ($numtweets as $date => $tweetcount) {
            $linkcount = $hashtagcount = $mentioncount = $retweetcount = $replycount = 0;
            if (isset($numlinktweets[$date]))
                $linkcount = $numlinktweets[$date];
            if (isset($numTweetsWithHashtag[$date]))
                $hashtagcount = $numTweetsWithHashtag[$date];
            if (isset($numTweetsWithMentions[$date]))
                $mentioncount = $numTweetsWithMentions[$date];
            if (isset($numTweetsWithMedia[$date]))
                $mediacount = $numTweetsWithMedia[$date];
            if (isset($numretweets[$date]))
                $retweetcount = $numretweets[$date];
            if (isset($numReplies[$date]))
                $replycount = $numReplies[$date];
            $csv->newrow();
            $csv->addfield($date);
            $csv->addfield($tweetcount);
            $csv->addfield($linkcount);
            $csv->addfield($hashtagcount);
            $csv->addfield($mentioncount);
            $csv->addfield($mediacount);
            $csv->addfield($retweetcount);
            $csv->addfield($replycount);
            $csv->writerow();
        }

        $csv->close();
        echo '<fieldset class="if_parameters">';

        echo '<legend>Tweet stats</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        
        
        
        ?>

    </body>
</html>
