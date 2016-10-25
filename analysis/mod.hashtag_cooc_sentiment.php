<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';

$variability = false;       // @todo used as hack for experiment in first issue mapping workshop
$uselocalresults = false;   // @todo used as hack for experiment in first issue mapping workshop
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Co-hashtags sentiments</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

    </head>

    <body>

        <h1>TCAT :: Co-hashtags sentiments</h1>

        <?php
        validate_all_variables();
        $collation = current_collation();
        if (empty($esc['shell']['minf']))
            $esc['shell']['minf'] = 4;

        include_once __DIR__ . '/common/Coword.class.php';
        $coword = new Coword;
        $coword->countWordOncePerDocument = FALSE;

        // get user diversity per hasthag
        $sql = "SELECT LOWER(h.text COLLATE $collation) as h1, COUNT(t.from_user_id) as c, COUNT(DISTINCT(t.from_user_id)) AS d ";
        $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
        $where = "h.tweet_id = t.id AND ";
        $sql .= sqlSubset($where);
        $sql .= "GROUP BY h1";

        //print $sql . "<bR>";

        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $word = $res['h1'];
            $coword->distinctUsersForWord[$word] = $res['d'];
            $coword->userDiversity[$word] = round(($res['d'] / $res['c']) * 100, 2);
            $coword->wordFrequency[$word] = $res['c'];
            $coword->wordFrequencyDividedByUniqueUsers[$word] = round($res['c'] / $res['d'], 2);
            $coword->wordFrequencyMultipliedByUniqueUsers[$word] = $res['c'] * $res['d'];
        }

        // calculate sentiments per hashtag
        // min, max, avg
        $sql = "SELECT s.positive, s.negative, h.text COLLATE $collation AS hashtag, h.tweet_id as tid FROM " . $esc['mysql']['dataset'] . "_sentiment s, " . $esc['mysql']['dataset'] . "_hashtags h WHERE h.tweet_id = s.tweet_id";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $word = $res['hashtag'];
            $sn = $res['negative'];
            $sp = $res['positive'];
            $tid = $res['tid'];
            $sentimentAvg[$word][] = $sn;
            $sentimentAvg[$word][] = $sp;
            $sentimentAbs[$word][] = abs($sn);
            $sentimentAbs[$word][] = abs($sp);
            //$sentimentWeighted[$word]['negative'][$sn] = $tid;
            //$sentimentWeighted[$word]['positive'][$sp] = $tid;
            //$sentimentWeightedAbs[$word][abs($sn)] = $tid;
            //$sentimentWeightedAbs[$word][$sp] = $tid;
        }
        // @todo weighted average -5 + 5
        // @todo weighted absolute numbers / extremity 0-5
        foreach ($sentimentAvg as $word => $sentiments) {
            $coword->sentimentAvg[$word] = array_sum($sentiments) / (2 * count($sentiments)); // 2 * because each word gets a positive and a negative  // @todo, where !0
            $coword->sentimentMin[$word] = min($sentiments);
            $coword->sentimentMax[$word] = max($sentiments);
            $coword->sentimentMaxAbs[$word] = max($sentimentAbs[$word]);
            $coword->sentimentDominant[$word] = -1;
            if ($coword->sentimentMax[$word] == $coword->sentimentMin[$word])
                $coword->sentimentDominant[$word] = 0;
            if ($coword->sentimentMax[$word] > abs($coword->sentimentMin[$word]))
                $coword->sentimentDominant[$word] = 1;
        }

        // do the actual job
        // get cowords
        $sql = "SELECT LOWER(A.text COLLATE $collation) AS h1, LOWER(B.text COLLATE $collation) AS h2 ";
        $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags A, " . $esc['mysql']['dataset'] . "_hashtags B, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset() . " AND ";
        $sql .= "LENGTH(A.text)>1 AND LENGTH(B.text)>1 AND ";
        $sql .= "LOWER(A.text COLLATE $collation) < LOWER(B.text COLLATE $collation) AND A.tweet_id = t.id AND A.tweet_id = B.tweet_id ";
        $sql .= "ORDER BY h1,h2";
        //print $sql."<br>";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $coword->addWord($res['h1']);
            $coword->addWord($res['h2']);
            $coword->addCoword($res['h1'], $res['h2'], 1);
        }

        unset($coword->words); // as we are adding words manually the frequency would be messed up
        if ($esc['shell']['minf'] > 0 && !($esc['shell']['topu'] > 0)) {
            $coword->applyMinFreq($esc['shell']['minf']);
            //$coword->applyMinDegree($esc['shell']['minf']);	// Berno: method no longer in use, remains unharmed
            $filename = get_filename_for_export("hashtagCooc", (isset($_GET['probabilityOfAssociation']) ? "_normalizedAssociationWeight" : "") . "_sentiment_minFreqOf" . $esc['shell']['minf'], "gexf");
        } elseif ($esc['shell']['topu'] > 0) {
            $coword->applyTopUnits($esc['shell']['topu']);
            $filename = get_filename_for_export("hashtagCooc", (isset($_GET['probabilityOfAssociation']) ? "_normalizedAssociationWeight" : "") . "_sentiment_Top" . $esc['shell']['topu'], "gexf");
        } else {
            $filename = get_filename_for_export("hashtagCooc", (isset($_GET['probabilityOfAssociation']) ? "_normalizedAssociationWeight" : "") . "_sentiment", "gexf");
        }


        file_put_contents($filename, $coword->getCowordsAsGexf($filename));

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your GEXF File</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
