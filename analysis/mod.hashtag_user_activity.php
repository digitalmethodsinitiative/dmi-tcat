<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/Gexf.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Hashtag user activity</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

    </head>

    <body>

        <h1>TCAT :: Hashtag user activity</h1>

        <?php
        validate_all_variables();

        // select nr of users in subset
        $sql = "SELECT count(id) AS count FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $rec = mysql_query($sql);
        if (mysql_num_rows($rec) > 0)
            $res = mysql_fetch_assoc($rec);
        else
            die('no data in selection');
        $nrOfTweets = $res['count'];

        $collation = 'utf8_bin';
        $is_utf8mb4 = false;
        $sql = "SHOW FULL COLUMNS FROM " . $esc['mysql']['dataset'] . "_hashtags";
        $sqlresults = mysql_query($sql);
        while ($res = mysql_fetch_assoc($sqlresults)) {
            if (array_key_exists('collation', $res) && $res['collation'] == 'utf8mb4_unicode_ci') { $is_utf8mb4 = true; break; }
        }
        if ($is_utf8mb4) $collation = 'utf8mb4_bin';

        // select nr of users in subset
        $sql = "SELECT count(distinct(from_user_name COLLATE $collation)) AS count FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $rec = mysql_query($sql);
        if (mysql_num_rows($rec) > 0)
            $res = mysql_fetch_assoc($rec);
        else
            die('no data in selection');
        $nrOfUsers = $res['count'];

        // get hashtag-user relations
        $sql = "SELECT LOWER(A.text COLLATE $collation) AS h1, LOWER(A.from_user_name COLLATE $collation) AS user ";
        $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags A, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset() . " AND ";
        $sql .= "LENGTH(A.text)>1 AND ";
        $sql .= "A.tweet_id = t.id ";

        $hashtagUsers = $hashtagCount = $hashtagMentions = $hashtagDistinctMentions = $hashtagUsers = $hashtagDistinctUsers = array();

        $sqlresults = mysql_query($sql);
        while ($res = mysql_fetch_assoc($sqlresults)) {
            if (!isset($hashtagUsers[$res['h1']][$res['user']]))
                $hashtagUsers[$res['h1']][$res['user']] = 0;
            $hashtagUsers[$res['h1']][$res['user']]++;
            if (!isset($hashtagCount[$res['h1']]))
                $hashtagCount[$res['h1']] = 0;
            $hashtagCount[$res['h1']]++;
        }
        foreach ($hashtagUsers as $hashtag => $users)
            $hashtagDistinctUsers[$hashtag] = count($users);

        // get hashtag mention relations
        $sql = "SELECT m.to_user COLLATE $collation AS u, h.text COLLATE $collation AS h1 FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t";
        $sql .= sqlSubset() . " AND ";
        $sql .= "h.tweet_id = m.tweet_id AND h.tweet_id = t.id";
        $rec = mysql_query($sql);
        while ($res = mysql_fetch_assoc($rec)) {
            if (!isset($hashtagMentions[$res['h1']][$res['u']]))
                $hashtagMentions[$res['h1']][$res['u']] = 0;
            $hashtagMentions[$res['h1']][$res['u']]++;
        }
        foreach ($hashtagMentions as $hashtag => $mentions) {
            $hashtagDistinctMentions[$hashtag] = count($mentions);
            $hashtagMentions[$hashtag] = array_sum($mentions);
        }

        // user-hashtag stats: hashtag, nr. of mentions, nr. of users participating (option: nr. of mentions)
        $contents = "hashtag,nr of tweets with hashtag, distinct users for hashtag, distinct mentions with hashtag, total mentions with hashtag, nr of tweets in selection, nr of users in selection\n";
        foreach ($hashtagCount as $hashtag => $count) {
            $contents .= "$hashtag,$count," . $hashtagDistinctUsers[$hashtag] . "," . (isset($hashtagDistinctMentions[$hashtag]) ? $hashtagDistinctMentions[$hashtag] : 0) . "," . (isset($hashtagMentions[$hashtag]) ? $hashtagMentions[$hashtag] : 0) . ",$nrOfTweets,$nrOfUsers\n";
        }

        $filename = get_filename_for_export("hashtagUserActivity", (isset($_GET['probabilityOfAssociation']) ? "_normalizedAssociationWeight" : ""));
        file_put_contents($filename, chr(239) . chr(187) . chr(191) . $contents);

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your GEXF File</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
