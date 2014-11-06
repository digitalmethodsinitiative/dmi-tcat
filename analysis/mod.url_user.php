<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/Gexf.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics URL user co-occurence</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>Twitter Analytics - URL user co-occurence</h1>

        <?php
        validate_all_variables();

        $sql = "SELECT COUNT(LOWER(t.from_user_name)) AS frequency, LOWER(t.from_user_name) AS username, u.url_followed AS url, u.domain AS domain, u.error_code AS status_code FROM ";
        $sql .= $esc['mysql']['dataset'] . "_tweets t, " . $esc['mysql']['dataset'] . "_urls u ";
        $where = "t.id = u.tweet_id AND u.url_followed !='' AND ";
        $sql .= sqlSubset($where);
        $sql .= " GROUP BY u.url_followed, LOWER(t.from_user_name) ORDER BY frequency DESC";
        $sqlresults = mysql_query($sql);

        $content = "frequency, user, url, domain, status_code\n";
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $content .= $res['frequency'] . "," . $res['username'] . ",\"" . $res['url'] . "\"," . $res['domain'] . "," . $res['status_code'] . "\n";
            $urlUsernames[$res['url']][$res['username']] = $res['frequency'];
            $urlDomain[$res['url']] = $res['domain'];
            $urlStatusCode[$res['url']] = $res['status_code'];
        }
        $filename = get_filename_for_export("urlUser");
        file_put_contents($filename, chr(239) . chr(187) . chr(191) . $content);

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your spreadsheet (CSV) file</legend>';

        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';

        echo '</fieldset>';

	$userUniqueUrls = array(); $userTotalUrls = array();
	$urlUniqueUsers = array(); $urlTotalUsers = array();

        foreach ($urlUsernames as $url => $usernames) {
	    if (!isset($urlUniqueUsers[$url])) $urlUniqueUsers[$url] = 0;
	    if (!isset($urlTotalUsers[$url])) $urlTotalUsers[$url] = 0;
            foreach ($usernames as $username => $frequency) {
		if (!isset($userUniqueUrls[$username])) $userUniqueUrls[$username] = 0;
		if (!isset($userTotalUrls[$username])) $userTotalUrls[$username] = 0;
		$urlUniqueUsers[$url]++;
		$urlTotalUsers[$url] += $frequency;
		$userUniqueUrls[$username]++;
		$userTotalUrls[$username] += $frequency;
	    }
	}

        $gexf = new Gexf();
        $gexf->setTitle("URL-user " . $filename);
        $gexf->setEdgeType(GEXF_EDGE_UNDIRECTED);
        $gexf->setCreator("tools.digitalmethods.net");
        foreach ($urlUsernames as $url => $usernames) {
            foreach ($usernames as $username => $frequency) {
                $node1 = new GexfNode($url);
                $node1->addNodeAttribute("type", 'url', $type = "string");
                $node1->addNodeAttribute('shortlabel', $urlDomain[$url], $type = "string");
                $node1->addNodeAttribute('longlabel', $url, $type = "string");
                $node1->addNodeAttribute('status_code', $urlStatusCode[$url], $type = "string");
                $node1->addNodeAttribute('unique_users', $urlUniqueUsers[$url], $type = "integer");
                $node1->addNodeAttribute('total_users', $urlTotalUsers[$url], $type = "integer");
                $gexf->addNode($node1);
                $node2 = new GexfNode($username);
                $node2->addNodeAttribute("type", 'user', $type = "string");
                $node2->addNodeAttribute('shortlabel', $username, $type = "string");
                $node2->addNodeAttribute('longlabel', $username, $type = "string");
                $node2->addNodeAttribute('unique_urls', $userUniqueUrls[$username], $type = "integer");
                $node2->addNodeAttribute('total_urls', $userTotalUrls[$username], $type = "integer");

                $gexf->addNode($node2);
                $edge_id = $gexf->addEdge($node1, $node2, $frequency);
            }
        }

        $gexf->render();

        $filename = str_replace(".csv", ".gexf", $filename);
        file_put_contents($filename, $gexf->gexfFile);

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your network (GEXF) file</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
