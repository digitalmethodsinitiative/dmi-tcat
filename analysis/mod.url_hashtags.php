<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/Gexf.class.php';
require_once __DIR__ . '/common/CSV.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: URL hashtag co-occurence</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: URL hashtag co-occurence</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);
        $collation = current_collation();
        $filename = get_filename_for_export("urlHashtag");
        $csv = new CSV($filename, $outputformat);

        $sql = "SELECT COUNT(LOWER(h.text COLLATE $collation)) AS frequency, LOWER(h.text COLLATE $collation) AS hashtag, u.url_followed AS url, u.domain AS domain, u.error_code AS status_code FROM ";
        $sql .= $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t ";
        $where = "t.id = h.tweet_id AND h.tweet_id = u.tweet_id AND u.url_followed !='' AND ";
        $sql .= sqlSubset($where);
        $sql .= " GROUP BY u.url_followed, LOWER(h.text COLLATE $collation) ORDER BY frequency DESC";
        //print $sql." - <br>";

        $rec = $dbh->prepare($sql);
        $rec->execute();
        $csv->writeheader(array("frequency", "hashtag", "url", "domain", "status_code"));
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $csv->newrow();
            $csv->addfield($res['frequency']);
            $csv->addfield($res['hashtag']);
            $csv->addfield($res['url']);
            $csv->addfield($res['domain']);
            $csv->addfield($res['status_code']);
            $csv->writerow();
            $urlHashtags[$res['url']][$res['hashtag']] = $res['frequency'];
            $urlDomain[$res['url']] = $res['domain'];
            $urlStatusCode[$res['url']] = $res['status_code'];
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
                $node1->addNodeAttribute("type", 'url', $type = "string");
                $node1->addNodeAttribute('shortlabel', $urlDomain[$url], $type = "string");
                $node1->addNodeAttribute('status_code', $urlStatusCode[$url], $type = "string");
                $gexf->addNode($node1);
                $node2 = new GexfNode($hashtag);
                $node2->addNodeAttribute("type", 'hashtag', $type = "string");
                $node2->addNodeAttribute('shortlabel', $hashtag, $type = "string");
                $gexf->addNode($node2);
                $edge_id = $gexf->addEdge($node1, $node2, $frequency);
            }
        }

        $gexf->render();

        $filename = get_filename_for_export("urlHashtag", '', 'gexf');
        file_put_contents($filename, $gexf->gexfFile);

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your network (GEXF) file</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
