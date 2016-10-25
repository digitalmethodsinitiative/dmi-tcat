<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/Gexf.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Language / hashtag co-occurence</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">



        </script>

    </head>

    <body>

        <h1>TCAT :: Language / hashtag co-occurence</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);
        $filename = get_filename_for_export("languageHashtag", '', 'gexf');

        //print_r($_GET);

        $collation = current_collation();

        $sql = "SELECT LOWER(t.from_user_lang) AS language, LOWER(h.text COLLATE $collation) AS hashtag FROM ";
        $sql .= $esc['mysql']['dataset'] . "_tweets t, " . $esc['mysql']['dataset'] . "_hashtags h ";
        $where = "t.id = h.tweet_id AND ";
        $sql .= sqlSubset($where);

        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {

            //print_r($res); exit;

            $res['language'] = preg_replace("/<.+>/U", "", $res['language']);
            $res['language'] = preg_replace("/[ \s\t]+/", " ", $res['language']);
            $res['language'] = trim($res['language']);

            if (!isset($languagesHashtags[$res['language']][$res['hashtag']])) {
                $languagesHashtags[$res['language']][$res['hashtag']] = 0;
            }
            $languagesHashtags[$res['language']][$res['hashtag']]++;
        }

        $gexf = new Gexf();
        $gexf->setTitle("from_user_lang-hashtag " . $filename);
        $gexf->setEdgeType(GEXF_EDGE_UNDIRECTED);
        $gexf->setCreator("tools.digitalmethods.net");
        foreach ($languagesHashtags as $language => $hashtags) {
            foreach ($hashtags as $hashtag => $frequency) {
                $node1 = new GexfNode($language);
                $node1->addNodeAttribute("type", 'from_user_lang', $type = "string");
                $gexf->addNode($node1);
                $node2 = new GexfNode($hashtag);
                $node2->addNodeAttribute("type", 'hashtag', $type = "string");
                $gexf->addNode($node2);
                $edge_id = $gexf->addEdge($node1, $node2, $frequency);
            }
        }

        $gexf->render();

        file_put_contents($filename, $gexf->gexfFile);

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your network (GEXF) file</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
