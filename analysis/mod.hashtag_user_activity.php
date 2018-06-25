<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/Gexf.class.php';
require_once __DIR__ . '/common/CSV.class.php';
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
        dataset_must_exist();
        $collation = current_collation();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);
        $filename = get_filename_for_export("hashtagUserActivity", (isset($_GET['probabilityOfAssociation']) ? "_normalizedAssociationWeight" : ""));
        $csv = new CSV($filename, $outputformat);

        // select nr of users in subset
        $sql = "SELECT count(t.id) AS count FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();

        $rec = $dbh->prepare($sql);
        $rec->execute();
        if ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $nrOfTweets = $res['count']; $rec = null;
        } else {
            die('no data in selection');
        }

        // select nr of users in subset
        $sql = "SELECT count(distinct(from_user_name COLLATE $collation)) AS count FROM " . $esc['mysql']['dataset'] . "_tweets t ";
	$sql .= sqlSubset();

        $rec = $dbh->prepare($sql);
        $rec->execute();
        if ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $nrOfUsers = $res['count']; $rec = null;
        } else {
            die('no data in selection');
        }

        // get hashtag-user relations
        $sql = "SELECT LOWER(A.text COLLATE $collation) AS h1, LOWER(A.from_user_name COLLATE $collation) AS user ";
        $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags A, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset() . " AND ";
        $sql .= "LENGTH(A.text)>1 AND ";
        $sql .= "A.tweet_id = t.id ";

        $hashtagUsers = $hashtagCount = $hashtagMentions = $hashtagDistinctMentions = $hashtagUsers = $hashtagDistinctUsers = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
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
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($hashtagMentions[$res['h1']][$res['u']]))
                $hashtagMentions[$res['h1']][$res['u']] = 0;
            $hashtagMentions[$res['h1']][$res['u']]++;
        }
        foreach ($hashtagMentions as $hashtag => $mentions) {
            $hashtagDistinctMentions[$hashtag] = count($mentions);
            $hashtagMentions[$hashtag] = array_sum($mentions);
        }

        // user-hashtag stats: hashtag, nr. of mentions, nr. of users participating (option: nr. of mentions)
        $csv->writeheader(array("hashtag", "nr of tweets with hashtag", "distinct users for hashtag", "distinct mentions with hashtag", "total mentions with hashtag", "nr of tweets in selection", "nr of users in selection"));
        foreach ($hashtagCount as $hashtag => $count) {
            $csv->newrow();
            $csv->addfield($hashtag);
            $csv->addfield($count);
            $csv->addfield($hashtagDistinctUsers[$hashtag]);
            $csv->addfield(isset($hashtagDistinctMentions[$hashtag]) ? $hashtagDistinctMentions[$hashtag] : 0);
            $csv->addfield(isset($hashtagMentions[$hashtag]) ? $hashtagMentions[$hashtag] : 0);
            $csv->addfield($nrOfTweets);
            $csv->addfield($nrOfUsers);
            $csv->writerow();
        }

        $csv->close();

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your CSV File</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
