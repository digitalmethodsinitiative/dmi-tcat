<?php
require_once './common/config.php';
require_once './common/functions.php';

$variability = false;       // @todo used as hack for experiment in first issue mapping workshop
$uselocalresults = false;   // @todo used as hack for experiment in first issue mapping workshop
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics co-hashtags</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

    </head>

    <body>

        <h1>Twitter Analytics co-hashtags</h1>

        <?php
        validate_all_variables();
// Output format: {dataset}_{query}_{startdate}_{enddate}_{from_user_name}_{output type}.{filetype}

        $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
        $filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . (isset($_GET['probabilityOfAssociation']) ? "_normalizedAssociationWeight" : "") . "_hashtagCooc.gexf";

        include_once('common/Coword.class.php');
        $coword = new Coword;

        // get hashtag counts
        $sql = "SELECT LOWER(h.text) as h1, COUNT(LOWER(h.text)) as c ";
        $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= "WHERE h.tweet_id = t.id ";
        $sql .= "AND " . sqlSubset();
        $sql .= "GROUP BY h1";
        //print $sql . "<bR>";
        $sqlresults = mysql_query($sql);
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $frequencies[$res['h1']] = $res['c'];
        }

        // get user diversity per hasthag
        $sql = "SELECT LOWER(h.text) as h1, COUNT(t.from_user_id) as c, COUNT(DISTINCT(t.from_user_id)) AS d ";
        $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= "WHERE h.tweet_id = t.id ";
        $sql .= "AND " . sqlSubset();
        $sql .= "GROUP BY h1";
        //print $sql . "<bR>";
        $sqlresults = mysql_query($sql);
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $word = $res['h1'];
            $coword->usersForWord[$word] = $res['c'];
            $coword->distinctUsersForWord[$word] = $res['d'];
            $coword->userDiversity[$word] = round(($res['d'] / $res['c']) * 100, 2);
            $coword->wordFrequencyDividedByUniqueUsers[$word] = round($frequencies[$word] / $res['d'], 2);
            $coword->wordFrequencyMultipliedByUniqueUsers[$word] = $frequencies[$word] * $res['d'];
        }

        // do the actual job
        // get cowords
        $sql = "SELECT LOWER(A.text) AS h1, LOWER(B.text) AS h2 ";
        $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags A, " . $esc['mysql']['dataset'] . "_hashtags B, " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
        $sql .= sqlSubset() . " AND ";
        $sql .= "LENGTH(A.text)>1 AND LENGTH(B.text)>1 AND ";
        $sql .= "LOWER(A.text) < LOWER(B.text) AND A.tweet_id = t.id AND A.tweet_id = B.tweet_id ";
        $sql .= "ORDER BY h1,h2";

        $sqlresults = mysql_query($sql);
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $coword->addWord($res['h1'], 1);
            $coword->addWord($res['h2'], 1);
            $coword->addCoword($res['h1'], $res['h2'], 1);
        }
        file_put_contents($filename, $coword->getCowordsAsGexf($filename));

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your GEXF File</legend>';

        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>