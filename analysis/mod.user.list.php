<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/CSV.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: User list</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">



        </script>

    </head>

    <body>

        <h1>TCAT :: User list</h1>

        <?php

        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        $collation = current_collation();
        $filename = get_filename_for_export("user.list");
        $csv = new CSV($filename, $outputformat);

        // tweets per user
        $sql = "SELECT t.from_user_id,t.from_user_name COLLATE $collation as from_user_name,t.from_user_lang,t.from_user_tweetcount,t.from_user_followercount,t.from_user_friendcount,t.from_user_listed,t.from_user_utcoffset,t.from_user_verified,count(t.id) as tweetcount, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY datepart, from_user_id";
        $array = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $array[$res['datepart']][$res['from_user_name']] = $res;
        }

        // retweets per user
        $sql = "SELECT count(t.retweet_id) as count, t.from_user_name COLLATE $collation as from_user_name, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND retweet_id != 0 AND retweet_id != ''";
        $sql .= "GROUP BY datepart, from_user_name";
        $retweets = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $retweets[$res['datepart']][$res['from_user_name']] = $res['count'];
        }

        // mentioning per user
        $sql = "SELECT m.from_user_name COLLATE $collation as from_user_name, count(m.from_user_name COLLATE $collation) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND m.tweet_id = t.id ";
        $sql .= "GROUP BY datepart, from_user_name";
        //print $sql . "<br>";
        flush();

        $mentioning = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $mentioning[$res['datepart']][$res['from_user_name']] = $res['count'];
        }

        // mentioned per user
        $sql = "SELECT m.to_user COLLATE $collation as to_user, count(m.to_user COLLATE $collation) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND m.tweet_id = t.id ";
        $sql .= "GROUP BY datepart, to_user";
        //print $sql . "<br>";
        flush();
        $mentioned = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $mentioned[$res['datepart']][$res['to_user']] = $res['count'];
        }

        // hashtags per user
        $sql = "SELECT h.from_user_name COLLATE $collation as from_user_name, count(h.from_user_name COLLATE $collation) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND h.tweet_id = t.id ";
        $sql .= "GROUP BY datepart, from_user_name";
        //print $sql . "<br>";
        flush();
        $hashtags = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $hashtags[$res['datepart']][$res['from_user_name']] = $res['count'];
        }

        // tweets with hashtags, per user
        $sql = "SELECT h.from_user_name COLLATE $collation as from_user_name, count(distinct(h.tweet_id)) as count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND h.tweet_id = t.id ";
        $sql .= "GROUP BY datepart, h.from_user_name";
        //print $sql . "<br>";
        flush();
        $tweetsWithhashtags = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $tweetsWithhashtags[$res['datepart']][$res['from_user_name']] = $res['count'];
        }

        $csv->writeheader(explode(',', "date,from_user_id,from_user_name,from_user_lang,from_user_tweetcount (all time user queries),from_user_followercount,from_user_friendcount,from_user_listed,from_user_utcoffset,from_user_verified,tweets in data set,retweets by user, mentioning,mentioned,total nr of hashtags,nr of tweets with hashtags"));
        foreach ($array as $date => $user_array) {
            foreach ($user_array as $user => $a) {
                $csv->newrow();
                $csv->addfield($date);
                $csv->addfield($a["from_user_id"]);
                $csv->addfield($a["from_user_name"]);
                $csv->addfield($a["from_user_lang"]);
                $csv->addfield($a["from_user_tweetcount"]);
                $csv->addfield($a["from_user_followercount"]);
                $csv->addfield($a["from_user_friendcount"]);
                $csv->addfield($a["from_user_listed"]);
                $csv->addfield($a["from_user_utcoffset"]);
                $csv->addfield($a["from_user_verified"]);
                $csv->addfield($a["tweetcount"]);
                if (isset($retweets[$date][$user]))
                    $csv->addfield($retweets[$date][$user]);
                else
                    $csv->addfield(0);
                if (isset($mentioning[$date][$user]))
                    $csv->addfield($mentioning[$date][$user]);
                else
                    $csv->addfield(0);
                if (isset($mentioned[$date][$user]))
                    $csv->addfield($mentioned[$date][$user]);
                else
                    $csv->addfield(0);
                if (isset($hashtags[$date][$user]))
                    $csv->addfield($hashtags[$date][$user]);
                else
                    $csv->addfield(0);
                if (isset($tweetsWithhashtags[$date][$user]))
                    $csv->addfield($tweetsWithhashtags[$date][$user]);
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
