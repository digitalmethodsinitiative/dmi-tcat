<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Mention sentiment graph</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">



        </script>

    </head>

    <body>

        <h1>TCAT :: Mention sentiment graph</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        // calculate sentiments per hashtag
        $sql = "SELECT s.positive, s.negative, t.from_user_name as user, t.id as tid FROM ".$esc['mysql']['dataset']."_sentiment s, ".$esc['mysql']['dataset']."_tweets t WHERE t.id = s.tweet_id";
        //print $sql."<br>";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {

            $word = $res['user'];
            $sn = $res['negative'];
            $sp = $res['positive'];
            $tid = $res['tid'];
            $sentimentAvg[$word][] = $sn;
            $sentimentAvg[$word][] = $sp;
            $sentimentAbs[$word][] = abs($sn);
            $sentimentAbs[$word][] = abs($sp);
        }

        // @todo weighted average -5 + 5
        // @todo weighted absolute numbers / extremity 0-5
        foreach ($sentimentAvg as $word => $sentiments) {
            $sentimentAvg[$word] = array_sum($sentiments) / (2 * count($sentiments)); // 2 * because each word gets a positive and a negative // @todo, where !0
            $sentimentMin[$word] = min($sentiments);
            $sentimentMax[$word] = max($sentiments);
            $sentimentMaxAbs[$word] = max($sentimentAbs[$word]);
            $sentimentDominant[$word] = "-1";
            if ($sentimentMax[$word] == $sentimentMin[$word])
                $sentimentDominant[$word] = "0";
            if ($sentimentMax[$word] > abs($sentimentMin[$word]))
                $sentimentDominant[$word] = "1";
        }

        // construct mention graph

        $users = array();
        $usersinv = array();
        $edges = array();

        $cur = 0;
        $numresults = 500000;

        //print_r($esc); exit;

        while ($numresults == 500000) {

            $sql = "SELECT m.from_user_name,m.to_user FROM " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t ";
            $where = "m.tweet_id = t.id AND ";
            $sql .= sqlSubset($where);
            $sql .= " LIMIT " . $cur . "," . $numresults;

            $numresults = 0;

            //print $sql."<br>";

            $rec = $dbh->prepare($sql);
            $rec->execute();
            while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
                $numresults++;

                $data["from_user_name"] = strtolower($data["from_user_name"]);
                $data["to_user"] = strtolower($data["to_user"]);

                if (!isset($users[$data["from_user_name"]])) {
                    $users[$data["from_user_name"]] = $arrayName = array('id' => count($usersinv), 'notweets' => 1, 'nomentions' => 0);
                    $usersinv[] = $data["from_user_name"];
                } else {
                    $users[$data["from_user_name"]]["notweets"]++;
                }

                if (!isset($users[$data["to_user"]])) {
                    $users[$data["to_user"]] = $arrayName = array('id' => count($usersinv), 'notweets' => 0, 'nomentions' => 1);
                    $usersinv[] = $data["to_user"];
                } else {
                    $users[$data["to_user"]]["nomentions"]++;
                }

                $to = $users[$data["from_user_name"]]["id"] . "," . $users[$data["to_user"]]["id"];

                if (!isset($edges[$to])) {
                    $edges[$to] = 1;
                } else {
                    $edges[$to]++;
                }
            }

            $cur = $cur + $numresults;
        }

        //print_r($users);

        $topusers = array();


        foreach ($users as $key => $user) {
            $topusers[$key] = $user["nomentions"];
        }

        arsort($topusers);

        if ($esc["shell"]["topu"] > 0) {
            $topusers = array_slice($topusers, 0, $esc["shell"]["topu"], true);
        }
        //print_r($topusers);

        $content = "nodedef>name VARCHAR,label VARCHAR,sentiment_dominant INT,no_tweets INT,no_mentions INT,sentiment_max INT,sentiment_min INT,sentiment_avg FLOAT, sentiment_max_absolute INT\n";
        foreach ($users as $key => $value) {
            if (isset($topusers[$key])) {
                $savg = $smin = $smax = $smaxa = 0;
                $sdom = "";
                if (isset($sentimentAvg[$key]))
                    $savg = $sentimentAvg[$key];
                if (isset($sentimentMin[$key]))
                    $smin = $sentimentMin[$key];
                if (isset($sentimentMax[$key]))
                    $smax = $sentimentMax[$key];
                if (isset($sentimentMaxAbs[$key]))
                    $smaxa = $sentimentMaxAbs[$key];
                if (isset($sentimentDominant[$key]))
                    $sdom = $sentimentDominant[$key];
                $content .= $value["id"] . "," . $key . ",$sdom," . $value["notweets"] . "," . $value["nomentions"] . "$savg,$smin,$smax,$smaxa\n";
            }
        }

        $content .= "edgedef>node1 VARCHAR,node2 VARCHAR,weight DOUBLE\n";
        foreach ($edges as $key => $value) {
            $tmp = explode(",", $key);
            if (isset($topusers[$usersinv[$tmp[0]]]) && isset($topusers[$usersinv[$tmp[1]]])) {
                $content .= $key . "," . $value . "\n";
            }
        }

        //echo $content;
        // add filename for top user filter  "_minDegreeOf".$esc['shell']['minf']
        $filename = get_filename_for_export("mention", "_Top-sentiment" . $esc['shell']['topu'], "gdf");
        file_put_contents($filename, $content);

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your File</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
