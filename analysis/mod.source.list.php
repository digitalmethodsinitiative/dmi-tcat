<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/CSV.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Source list</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">



        </script>

    </head>

    <body>

        <h1>TCAT :: Source list</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        $collation = current_collation();
        $filename = get_filename_for_export("source.list");
        $csv = new CSV($filename, $outputformat);

        // tweets per source
        $sql = "SELECT t.source COLLATE $collation as source, count(t.id) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY datepart, source";
        $array = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $array[$res['datepart']][$res['source']] = $res['count'];
        }

        // retweets per source
        $sql = "SELECT count(t.retweet_id) as count, t.source COLLATE $collation as source, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND retweet_id != 0 AND retweet_id != ''";
        $sql .= "GROUP BY datepart, source";
        $retweets = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $retweets[$res['datepart']][$res['source']] = $res['count'];
        }

        // replies per source
        $sql = "SELECT count(t.in_reply_to_status_id) as count, t.source COLLATE $collation as source, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND in_reply_to_status_id != 0 AND in_reply_to_status_id != ''";
        $sql .= "GROUP BY datepart, source";
        $replies = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $replies[$res['datepart']][$res['source']] = $res['count'];
        }

        // hashtags per source
        $sql = "SELECT t.source COLLATE $collation as source, count(h.text COLLATE $collation) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND h.tweet_id = t.id ";
        $sql .= "GROUP BY datepart, source";
        //print $sql . "<br>";
        flush();
        $hashtags = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $hashtags[$res['datepart']][$res['source']] = $res['count'];
        }

        // tweets with hashtags, per source
        $sql = "SELECT t.source COLLATE $collation as source, count(distinct(h.tweet_id)) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND h.tweet_id = t.id ";
        $sql .= "GROUP BY datepart, source";
        //print $sql . "<br>";
        flush();
        $tweetsWithhashtags = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $tweetsWithhashtags[$res['datepart']][$res['source']] = $res['count'];
        }

        // urls per source
        $sql = "SELECT t.source COLLATE $collation as source, count(u.url_followed) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND u.tweet_id = t.id ";
        $sql .= "GROUP BY datepart, source";
        $links = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $links[$res['datepart']][$res['source']] = $res['count'];
        }

        // tweets with urls, per source
        $sql = "SELECT t.source COLLATE $collation as source, count(distinct(u.tweet_id)) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND u.tweet_id = t.id ";
        $sql .= "GROUP BY datepart, source";
        //print $sql . "<br>";
        flush();
        $tweetsWithLinks = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $tweetsWithLinks[$res['datepart']][$res['source']] = $res['count'];
        }

        // mentions per source
        $sql = "SELECT t.source COLLATE $collation as source, count(m.to_user) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND m.tweet_id = t.id ";
        $sql .= "GROUP BY datepart, source";
        $mentions = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $mentions[$res['datepart']][$res['source']] = $res['count'];
        }

        // tweets with mentions, per source
        $sql = "SELECT t.source COLLATE $collation as source, count(distinct(m.tweet_id)) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND m.tweet_id = t.id ";
        $sql .= "GROUP BY datepart, source";
        //print $sql . "<br>";
        flush();
        $tweetsWithMentions = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $tweetsWithMentions[$res['datepart']][$res['source']] = $res['count'];
        }

        $csv->writeheader(explode(',', "date,source,tweets,retweets,replies,total nr of hashtags,nr of tweets with hashtags,total nr of URLs,nr of tweets with URLs,total nr of mentions,nr of tweets with mentions"));
        foreach ($array as $date => $sources) {
            foreach ($sources as $source => $tweetcount) {
                $csv->newrow();
                $csv->addfield($date);
                $csv->addfield($source);
                $csv->addfield($tweetcount);
                if (isset($retweets[$date][$source]))
                    $csv->addfield($retweets[$date][$source]);
                else
                    $csv->addfield(0);
                if (isset($replies[$date][$source]))
                    $csv->addfield($replies[$date][$source]);
                else
                    $csv->addfield(0);
                if (isset($hashtags[$date][$source]))
                    $csv->addfield($hashtags[$date][$source]);
                else
                    $csv->addfield(0);
                if (isset($tweetsWithhashtags[$date][$source]))
                    $csv->addfield($tweetsWithhashtags[$date][$source]);
                else
                    $csv->addfield(0);
                if (isset($links[$date][$source]))
                    $csv->addfield($links[$date][$source]);
                else
                    $csv->addfield(0);
                if (isset($tweetsWithLinks[$date][$source]))
                    $csv->addfield($tweetsWithLinks[$date][$source]);
                else
                    $csv->addfield(0);
                if (isset($mentions[$date][$source]))
                    $csv->addfield($mentions[$date][$source]);
                else
                    $csv->addfield(0);
                if (isset($tweetsWithMentions[$date][$source]))
                    $csv->addfield($tweetsWithMentions[$date][$source]);
                else
                    $csv->addfield(0);
                $csv->writerow();
            }
        }

        $csv->close();

        echo '<fieldset class="if_parameters">';
        echo '<legend>User stats</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
