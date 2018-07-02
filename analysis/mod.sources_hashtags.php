<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/Gexf.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Source / hashtag co-occurence</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">



        </script>

    </head>

    <body>

        <h1>TCAT :: Source / hashtag co-occurence</h1>

        <?php

        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);
        $filename = get_filename_for_export("sourceHashtag", '', 'gexf');
        $collation = current_collation();

		//print_r($_GET);

        $sql = "SELECT LOWER(t.source COLLATE $collation) AS source, LOWER(h.text COLLATE $collation) AS hashtag FROM ";
        $sql .= $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
        $where = "t.id = h.tweet_id AND ";
        $sql .= sqlSubset($where);

        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {

			$res['source'] = preg_replace("/<.+>/U", "", $res['source']);
			$res['source'] = preg_replace("/[ \s\t]+/", " ", $res['source']);
			$res['source'] = trim($res['source']);

			if(!isset($sourcesHashtags[$res['source']][$res['hashtag']])) {
				 $sourcesHashtags[$res['source']][$res['hashtag']] = 0;
			}
			$sourcesHashtags[$res['source']][$res['hashtag']]++;
        }

        $gexf = new Gexf();
        $gexf->setTitle("source-hashtag " . $filename);
        $gexf->setEdgeType(GEXF_EDGE_UNDIRECTED);
        $gexf->setCreator("tools.digitalmethods.net");
        foreach ($sourcesHashtags as $source => $hashtags) {
            foreach ($hashtags as $hashtag => $frequency) {
                $node1 = new GexfNode($source);
                $node1->addNodeAttribute("type", 'source', $type = "string");
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
