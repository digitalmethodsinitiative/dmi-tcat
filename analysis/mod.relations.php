<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/Gexf.class.php';
ini_set('memory_limit', '10G');
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics relations</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>Twitter Analytics :: relations</h1>

        <?php
        validate_all_variables();
// Output format: {dataset}_{query}_{startdate}_{enddate}_{from_user_name}_{output type}.{filetype}

        $extra = file('/home/erik/relations_names.csv');
        foreach ($extra as $e) {
            $ex = explode(",", $e);
            $extra_screennames[$ex[0]] = $ex[2];
            $extra_names[$ex[0]] = $ex[1];
            $extra_descriptions[$ex[0]] = $ex[3];
        }

        $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
        $filename_relations = $resultsdir . $esc['shell']["datasetname"] . "_relations.gexf";
        $filename_friends = str_replace("_relations", "_friends", $filename_relations);
        $filename_followers = str_replace("_relations", "_followers", $filename_relations);


        $sql = "SELECT user1_id, user2_id, type FROM ";
        $sql .= $esc['mysql']['dataset'] . "_relations r "; // todo, instead of just joining on user1_id and limiting on tweets.created_at, we might als want/need to limit on r.observed_at
        //print $sql . " <br>";

        $sqlresults = mysql_query($sql);

        $gexf = new Gexf();
        $gexf->setTitle("Friend and follower relations " . $filename_relations);
        $gexf->setEdgeType(GEXF_EDGE_DIRECTED);
        $gexf->setCreator("tools.digitalmethods.net");
        while ($res = mysql_fetch_assoc($sqlresults)) {
            if (isset($extra_screennames[$res['user1_id']])) {
                $label1 = trim($extra_screennames[$res['user1_id']]);
                $name1 = trim($extra_names[$res['user1_id']]);
                $desc1 = trim($extra_descriptions[$res['user1_id']]);
            } else
                $label1 = $res['user1_id'];
            if (isset($extra_screennames[$res['user2_id']])) {
                $label2 = trim($extra_screennames[$res['user2_id']]);
                $name2 = trim($extra_names[$res['user2_id']]);
                $desc2 = trim($extra_descriptions[$res['user2_id']]);
            } else
                $label2 = $res['user2_id'];
            if ($res['type'] == "friend") {
                $node1 = new GexfNode($label1);
                if (!preg_match("/^\d+$/", $label1)) {
                    $node1->addNodeAttribute('name', $name1);
                    $node1->addNodeAttribute('description', $desc1);
                }
                $node1->addNodeAttribute("type", 'start', $type = "string");
                $gexf->addNode($node1);
                $node2 = new GexfNode($label2);
                if (!preg_match("/^\d+$/", $label2)) {
                    $node2->addNodeAttribute('name', $name2);
                    $node2->addNodeAttribute('description', $desc2);
                }
                $node2->addNodeAttribute("type", 'friend', $type = "string");
                $gexf->addNode($node2);
                $edge_id = $gexf->addEdge($node1, $node2, 1);
                //@todo add edge attribute 'observed_at'
            } elseif ($res['type'] == "follower") {
                $node1 = new GexfNode($label2);
                if (!preg_match("/^\d+$/", $label2)) {
                    $node1->addNodeAttribute('name', $name2);
                    $node1->addNodeAttribute('description', $desc2);
                }
                $node1->addNodeAttribute("type", 'follower', $type = "string");
                $gexf->addNode($node1);
                $node2 = new GexfNode($label1);
                if (!preg_match("/^\d+$/", $label1)) {
                    $node2->addNodeAttribute('name', $name1);
                    $node2->addNodeAttribute('description', $desc1);
                }
                $node2->addNodeAttribute("type", 'start', $type = "string");
                $gexf->addNode($node2);
                $edge_id = $gexf->addEdge($node1, $node2, 1);
                //@todo add edge attribute 'observed_at'
            }
        }

        $gexf->render();

        file_put_contents($filename_relations, $gexf->gexfFile);

        echo '<fieldset class="if_parameters">';
        echo '<legend>Friends + follower network</legend>';
        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename_relations)) . '">' . $filename_relations . '</a></p>';
        echo '</fieldset>';



        $sql = "SELECT user1_id, user2_id FROM ";
        $sql .= $esc['mysql']['dataset'] . "_relations r WHERE ";
        $sql .= "r.type = 'friend'"; // todo, instead of just joining on user1_id and limiting on tweets.created_at, we might als want/need to limit on r.observed_at
        //print $sql . " <br>";

        $sqlresults = mysql_query($sql);

        $gexf = new Gexf();
        $gexf->setTitle("Friend relations " . $filename_friends);
        $gexf->setEdgeType(GEXF_EDGE_DIRECTED);
        $gexf->setCreator("tools.digitalmethods.net");
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $node1 = new GexfNode($res['user1_id']);
            $node1->addNodeAttribute("type", 'start', $type = "string");
            $gexf->addNode($node1);
            $node2 = new GexfNode($res['user2_id']);
            $node2->addNodeAttribute("type", 'friend', $type = "string");
            $gexf->addNode($node2);
            $edge_id = $gexf->addEdge($node1, $node2, 1);
            //@todo add edge attribute 'observed_at'
        }

        $gexf->render();

        file_put_contents($filename_friends, $gexf->gexfFile);

        echo '<fieldset class="if_parameters">';
        echo '<legend>Friends network</legend>';
        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename_friends)) . '">' . $filename_friends . '</a></p>';
        echo '</fieldset>';



        $sql = "SELECT user1_id, user2_id FROM ";
        $sql .= $esc['mysql']['dataset'] . "_relations r WHERE ";
        $sql .= "r.type = 'follower'"; // todo, instead of just joining on user1_id and limiting on tweets.created_at, we might als want/need to limit on r.observed_at
        //print $sql . " <br>";

        $sqlresults = mysql_query($sql);

        $gexf = new Gexf();
        $gexf->setTitle("Follower relations " . $filename_followers);
        $gexf->setEdgeType(GEXF_EDGE_DIRECTED);
        $gexf->setCreator("tools.digitalmethods.net");
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $node1 = new GexfNode($res['user1_id']);
            $node1->addNodeAttribute("type", 'start', $type = "string");
            $gexf->addNode($node1);
            $node2 = new GexfNode($res['user2_id']);
            $node2->addNodeAttribute("type", 'friend', $type = "string");
            $gexf->addNode($node2);
            $edge_id = $gexf->addEdge($node1, $node2, 1);
            //@todo add edge attribute 'observed_at'
        }

        $gexf->render();

        file_put_contents($filename_followers, $gexf->gexfFile);

        echo '<fieldset class="if_parameters">';
        echo '<legend>Follower network</legend>';
        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename_followers)) . '">' . $filename_followers . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
