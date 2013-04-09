<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/Gexf.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics :: Mention - Hashtags</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>Twitter Analytics :: Mention - Hashtags</h1>

        <?php
// => gexf
// => time

        validate_all_variables();

        $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
        $filename = $resultsdir . $esc['shell']['datasetname'] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . "_mentionHashtags.gexf";


        $sql = "SELECT m.to_user AS user, LOWER(h.text) AS hashtag FROM ";
        $sql .= $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t, " . $esc['mysql']['dataset'] . "_hashtags h WHERE ";
        $sql .= "t.id = m.tweet_id AND m.tweet_id = h.tweet_id AND ";
        $sql .= "LENGTH(h.text)>1 AND ";
        $sql .= sqlSubset();
        //print $sql."<Br>";

        $sqlresults = mysql_query($sql);
        while ($res = mysql_fetch_assoc($sqlresults)) {
            if (!isset($userHashtags[$res['user']][$res['hashtag']]))
                $userHashtags[$res['user']][$res['hashtag']] = 0;
            $userHashtags[$res['user']][$res['hashtag']]++;
            if (!isset($userCount[$res['user']]))
                $userCount[$res['user']] = 0;
            $userCount[$res['user']]++;
            if (!isset($hashtagCount[$res['hashtag']]))
                $hashtagCount[$res['hashtag']] = 0;
            $hashtagCount[$res['hashtag']]++;
        }

        $gexf = new Gexf();
        $gexf->setTitle("Hashtag - mentions " . $filename);
        $gexf->setEdgeType(GEXF_EDGE_UNDIRECTED);
        $gexf->setCreator("tools.digitalmethods.net");

        foreach ($userHashtags as $user => $hashtags) {
            foreach ($hashtags as $hashtag => $frequency) {
                $node1 = new GexfNode($user);
                $node1->addNodeAttribute("type", 'user', $type = "string");
                $node1->addNodeAttribute("userFrequency", $userCount[$user], $type = "int");
                $node1->addNodeAttribute("hashtagFrequency", 0, $type = "int");
                $gexf->addNode($node1);
                $node2 = new GexfNode($hashtag);
                $node2->addNodeAttribute("type", 'hashtag', $type = "string");
                $node2->addNodeAttribute("userFrequency", 0, $type = "int");
                $node2->addNodeAttribute("hashtagFrequency", $hashtagCount[$hashtag], $type = "int");
                $gexf->addNode($node2);
                $edge_id = $gexf->addEdge($node1, $node2, $frequency);
            }
        }

        $gexf->render();

        file_put_contents($filename, $gexf->gexfFile);

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your GEXF File</legend>';

        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
