<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/Gexf.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics bipartite hashtag - user graph</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

    </head>

    <body>

        <h1>Twitter Analytics bipartite hashtag - user graph</h1>

        <?php
        validate_all_variables();
// Output format: {dataset}_{query}_{startdate}_{enddate}_{from_user_name}_{output type}.{filetype}

        $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
        $filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . (isset($_GET['probabilityOfAssociation']) ? "_normalizedAssociationWeight" : "") . "_hashtagUser.gexf";

        include_once('common/Coword.class.php');
        $coword = new Coword;
        $coword->countWordOncePerDocument = FALSE;

        // get hashtag-user relations
        $sql = "SELECT LOWER(A.text) AS h1, LOWER(A.from_user_name) AS user, LOWER(t.from_user_lang) AS language, LOWER(t.location) AS location ";
        $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags A, " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
        $sql .= sqlSubset() . " AND ";
        $sql .= "LENGTH(A.text)>1 AND ";
        $sql .= "A.tweet_id = t.id ";

        $sqlresults = mysql_query($sql);
        $languages = $locations = array();
        while ($res = mysql_fetch_assoc($sqlresults)) {
            if (!isset($userHashtags[$res['user']][$res['h1']]))
                $userHashtags[$res['user']][$res['h1']] = 0;
            $userHashtags[$res['user']][$res['h1']]++;
            if (!isset($userCount[$res['user']]))
                $userCount[$res['user']] = 0;
            $userCount[$res['user']]++;
            if (!isset($hashtagCount[$res['h1']]))
                $hashtagCount[$res['h1']] = 0;
            $hashtagCount[$res['h1']]++;
            $languages[$res['user']] = $res['language'];
            $locations[$res['user']] = $res['location'];
        }

        $gexf = new Gexf();
        $gexf->setTitle("Hashtag - user " . $filename);
        $gexf->setEdgeType(GEXF_EDGE_UNDIRECTED);
        $gexf->setCreator("tools.digitalmethods.net");

        foreach ($userHashtags as $user => $hashtags) {
            foreach ($hashtags as $hashtag => $frequency) {
                $node1 = new GexfNode($user);
                $node1->addNodeAttribute("type", 'user', $type = "string");
                $node1->addNodeAttribute("userFrequency", $userCount[$user], $type = "int");
                $node1->addNodeAttribute("hashtagFrequency", 0, $type = "int");
                $node1->addNodeAttribute("language", $languages[$user], $type = "string");
                $node1->addNodeAttribute("location", $locations[$user], $type = "string");
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