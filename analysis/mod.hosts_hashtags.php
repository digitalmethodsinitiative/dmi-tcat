<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/Gexf.class.php';
require_once __DIR__ . '/common/CSV.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Host hashtag co-occurence</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Host hashtag co-occurence</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);
        $filename = get_filename_for_export("hostHashtag");
        $csv = new CSV($filename, $outputformat);

        $collation = current_collation();

        $sql = "SELECT COUNT(LOWER(h.text COLLATE $collation)) AS frequency, LOWER(h.text COLLATE $collation) AS hashtag, u.domain COLLATE $collation AS domain FROM ";
        $sql .= $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t ";
        $where = "t.id = h.tweet_id AND h.tweet_id = u.tweet_id AND u.url_followed !='' AND ";
        $sql .= sqlSubset($where);
        $sql .= " GROUP BY u.domain COLLATE $collation, LOWER(h.text COLLATE $collation) ORDER BY frequency DESC";
        //print $sql." - <br>";

        $rec = $dbh->prepare($sql);
        $rec->execute();
        $csv->writeheader(array("frequency", "hashtag", "domain"));
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $csv->newrow();
            $csv->addfield($res['frequency']);
            $csv->addfield($res['hashtag']);
            $csv->addfield($res['domain']);
            $csv->writerow();
            $urlHashtags[$res['domain']][$res['hashtag']] = $res['frequency'];
        }
        $csv->close();

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your spreadsheet (CSV) file</legend>';

        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';

        echo '</fieldset>';



        $gexf = new Gexf();
        $gexf->setTitle("URL-hashtag " . $filename);
        $gexf->setEdgeType(GEXF_EDGE_UNDIRECTED);
        $gexf->setCreator("tools.digitalmethods.net");
        foreach ($urlHashtags as $url => $hashtags) {
            foreach ($hashtags as $hashtag => $frequency) {
                $node1 = new GexfNode($url);
                $node1->addNodeAttribute("type", 'host', $type = "string");
                $gexf->addNode($node1);
                $node2 = new GexfNode($hashtag);
                $node2->addNodeAttribute("type", 'hashtag', $type = "string");
                $gexf->addNode($node2);
                $edge_id = $gexf->addEdge($node1, $node2, $frequency);
            }
        }

        $gexf->render();

        $filename = get_filename_for_export("hostHashtag", '', 'gexf');
        file_put_contents($filename, $gexf->gexfFile);

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your network (GEXF) file</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
