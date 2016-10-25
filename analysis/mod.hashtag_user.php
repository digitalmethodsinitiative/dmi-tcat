<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/Gexf.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Bipartite hashtag - user graph</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

    </head>

    <body>

        <h1>TCAT :: Bipartite hashtag - user graph</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        $filename = get_filename_for_export("hashtagUser", (isset($_GET['probabilityOfAssociation']) ? "_normalizedAssociationWeight" : ""), "gexf");

        include_once __DIR__ . '/common/Coword.class.php';
        $coword = new Coword;
        $coword->countWordOncePerDocument = FALSE;

        $collation = current_collation();

        // get hashtag-user relations
        $sql = "SELECT LOWER(A.text COLLATE $collation) AS h1, LOWER(A.from_user_name COLLATE $collation) AS user, LOWER(t.from_user_lang) AS language, LOWER(t.location COLLATE $collation) AS location, t.from_user_timezone AS timezone, t.from_user_utcoffset AS utcoffset ";
        $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags A, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset() . " AND ";
        $sql .= "LENGTH(A.text)>1 AND ";
        $sql .= "A.tweet_id = t.id ";

        $languages = $locations = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
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
            $from_user_timezone[$res['user']] = $res['timezone'];
            $from_user_utcoffset[$res['user']] = $res['utcoffset'];
        }

        $gexf = new Gexf();
        $gexf->setTitle("Hashtag - user " . $filename);
        $gexf->setEdgeType(GEXF_EDGE_UNDIRECTED);
        $gexf->setCreator("tools.digitalmethods.net");

        foreach ($userHashtags as $user => $hashtags) {
            foreach ($hashtags as $hashtag => $frequency) {
                $node1 = new GexfNode($user);
		        $node1->id = md5('n-user_'.$user);
                $node1->addNodeAttribute("type", 'user', $type = "string");
                $node1->addNodeAttribute("userFrequency", $userCount[$user], $type = "int");
                $node1->addNodeAttribute("hashtagFrequency", 0, $type = "int");
                $node1->addNodeAttribute("language", $languages[$user], $type = "string");
                $node1->addNodeAttribute("location", $locations[$user], $type = "string");
                $node1->addNodeAttribute("from_user_utcoffset", $from_user_utcoffset[$user], $type = "string");
                $node1->addNodeAttribute("from_user_timezone", $from_user_timezone[$user], $type = "string");
                $gexf->addNode($node1);
                $node2 = new GexfNode($hashtag);
		        $node2->id = md5('n-hashtag_'.$hashtag);
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

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
