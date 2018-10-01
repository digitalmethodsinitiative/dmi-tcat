<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/Gexf.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Interaction graph</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Interaction graph</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        // NOTICE: this script does nested queries, therefore we do must use buffered queries

        $min_nr_of_nodes = $esc['shell']['minf'];

        global $collation;
        $collation = current_collation();

        // get all tweets which have in_reply_to_status_id set
        $sql = "SELECT t.id, created_at, from_user_name COLLATE $collation as from_user_name, text COLLATE $collation as text, in_reply_to_status_id, from_user_lang, from_user_tweetcount, from_user_followercount, from_user_friendcount, from_user_listed, source COLLATE $collation as source, geo_lng, geo_lat  FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND in_reply_to_status_id != '' ORDER BY id ";

        $paths = $path_locations = $indegree = $outdegree = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $id = $res['id'];
            $from_user_name = $res['from_user_name'];
            $text = $res['text'];
            $in_reply_to_status_id = $res['in_reply_to_status_id'];

            // store tweet info
            $tweets[$id]['from_user_name'] = $from_user_name;
            $tweets[$id]['text'] = $res['text'];
            $tweets[$id]['in_reply_to_status_id'] = $res['in_reply_to_status_id'];
            $tweets[$id]['created_at'] = $res['created_at'];
            $tweets[$id]['from_user_lang'] = $res['from_user_lang'];
            $tweets[$id]['from_user_tweetcount'] = $res['from_user_tweetcount'];
            $tweets[$id]['from_user_followercount'] = $res['from_user_followercount'];
            $tweets[$id]['from_user_friendcount'] = $res['from_user_friendcount'];
            $tweets[$id]['from_user_listed'] = $res['from_user_listed'];
            $tweets[$id]['source'] = $res['source'];

            // store link between tweets
            $links[] = array($id, $in_reply_to_status_id);
            if (!isset($indegree[$in_reply_to_status_id]))
                $indegree[$in_reply_to_status_id] = 0;
            $indegree[$in_reply_to_status_id]++;
            if (!isset($outdegree[$id]))
                $outdegree[$id] = 0;
            $outdegree[$id]++;

            // see whether link is part of a larger path
            // if in_reply_to_status_id already exists we just add the id which refers to original tweet
            // else we add link to path list and path_location library
            if (isset($path_locations[$in_reply_to_status_id])) {
                $path_location = $path_locations[$in_reply_to_status_id];
                $paths[$path_location][] = $id;
                $path_locations[$id] = $path_location;
                // if in_reply_to_status_id does not exist yet we add both
            } else {
                $paths[] = array($id, $in_reply_to_status_id);
                $curloc = count($paths) - 1;
                $path_locations[$id] = $curloc;
                $path_locations[$in_reply_to_status_id] = $curloc;
            }
        }

        // calculate how many nodes there are per path
        $paths_node_counts = $todo = array();
        foreach ($paths as $k => $nodes) {
            $paths_node_counts[$k] = count($nodes);
            if ($paths_node_counts[$k] >= $min_nr_of_nodes)
                $todo = array_merge($todo, $nodes);
        }

        // calculate distribution of path size
        $paths_node_counts_distribution = array_count_values($paths_node_counts);
        ksort($paths_node_counts_distribution);
        $paths_node_counts_distribution_tmp = $paths_node_counts_distribution;
        for ($i = 0; $i < $min_nr_of_nodes; $i++) {
            if (isset($paths_node_counts_distribution_tmp[$i]))
                unset($paths_node_counts_distribution_tmp[$i]);
        }

        // get roots
        $rootsnotfound = 0;
        $root_ids = array_diff($todo, array_keys($tweets));
        foreach ($root_ids as $root_id) {
            $tweet = getTweet($root_id);
            if ($tweet !== false) {
                $tweets[$root_id] = $tweet;
            } else {
                $rootsnotfound++;
            }
        }

        // discern type of networks
        foreach ($paths as $path) {
            // get path length
            $nr_of_nodes = count($path);

            if ($nr_of_nodes == count(array_diff($path, $todo)))
                continue;

            // locate node with max degree
            $path_indegrees = $path_outdegrees = array();
            foreach ($path as $id) {
                if (isset($indegree[$id]))
                    $path_indegrees[] = $indegree[$id];
                if (isset($outdegree[$id]))
                    $path_outdegrees[] = $outdegree[$id];
            }
            $path_max_indegree = max($path_indegrees);
            $path_max_outdegree = max($path_outdegrees);



            if ($nr_of_nodes - 1 == $path_max_indegree)
                $type = "star";
            elseif ($path_max_indegree <= 1 && $path_max_outdegree <= 1)
                $type = "chain";
            else
                $type = "other";

            //print "path " . implode(" , ", $path) . " has max path_degree " . $path_degree . " and path_length $path_length thus type $type<br>";

            foreach ($path as $id)
                $network_types[$id] = $type;

            if (!isset($network_types_stats[$type]))
                $network_types_stats[$type] = 0;
            $network_types_stats[$type]++;
        }

        // print stats
        foreach ($paths_node_counts_distribution as $length => $frequency) {
            print "Networks with $length nodes: $frequency<br>";
        }
        print "<br><b>Now analyzing only networks with at least $min_nr_of_nodes nodes</b><br><br>";
        print "Networks in analysis: " . array_sum($paths_node_counts_distribution_tmp) . "<br>";
        print "Networks in analysis without root: " . $rootsnotfound . "<bR>";
        print "<bR>";
        ksort($network_types_stats);
        foreach ($network_types_stats as $type => $count) {
            print "Networks of type $type: $count<br>";
        }
        flush();

        // @todo percentage of different structures (pure stars, pure chains, mixed structure). Ratio between average degree and number of nodes.

        $filename = get_filename_for_export("interactionGraph", "min" . $esc['shell']['minf'] . "nodes", "gexf");
        $gexf = new Gexf();
        $gexf->setTitle("interaction graph " . $filename);
        $gexf->setEdgeType(GEXF_EDGE_DIRECTED);
        $gexf->setCreator("tools.digitalmethods.net");

        // label: user, attribute: tweet
        // min, max, mean of what?? ditributions of nr of nodes in the network

        foreach ($links as $link) {
            $tweet_id = $link[0];
            $in_reply_to_status_id = $link[1];
            if (array_search($in_reply_to_status_id, $todo) === false && array_search($tweet_id, $todo) === false)
                continue;

            $node1 = new GexfNode($in_reply_to_status_id);
            // if node already exists, we want the attributes added that node instead
            $id = $gexf->nodeExists($node1);
            if ($id !== false && isset($gexf->nodeObjects[$id]))
                $node1 = $gexf->nodeObjects[$id];
            $node1->addNodeAttribute('tweet_id', $in_reply_to_status_id, 'string');
            if (!isset($tweets[$in_reply_to_status_id])) {
                $tweet = getTweet($in_reply_to_status_id);
                if ($tweet !== false) {
                    $node1->addNodeAttribute('created_at', $tweet['created_at'], 'string');
                    $node1->addNodeAttribute('tweet', $tweet['text'], 'string');
                    $node1->addNodeAttribute('user', $tweet['from_user_name'], 'string');
                    $node1->addNodeAttribute('from_user_lang', $tweet['from_user_lang'], 'string');
                    $node1->addNodeAttribute('from_user_tweetcount', $tweet['from_user_tweetcount'], 'integer');
                    $node1->addNodeAttribute('from_user_followercount', $tweet['from_user_followercount'], 'integer');
                    $node1->addNodeAttribute('from_user_friendcount', $tweet['from_user_friendcount'], 'integer');
                    $node1->addNodeAttribute('from_user_listed', $tweet['from_user_listed'], 'integer');
                    $node1->addNodeAttribute('geo_lng', $tweet['geo_lng'], 'integer');
                    $node1->addNodeAttribute('geo_lat', $tweet['geo_lat'], 'integer');
                    $node1->addNodeAttribute('source', $tweet['source'], 'string');
                }
            } else {
                $node1->addNodeAttribute('created_at', $tweets[$in_reply_to_status_id]['created_at'], 'string');
                $node1->addNodeAttribute('tweet', $tweets[$in_reply_to_status_id]['text'], 'string');
                $node1->addNodeAttribute('user', $tweets[$in_reply_to_status_id]['from_user_name'], 'string');
                $node1->addNodeAttribute('from_user_lang', $tweets[$in_reply_to_status_id]['from_user_lang'], 'string');
                $node1->addNodeAttribute('from_user_tweetcount', $tweets[$in_reply_to_status_id]['from_user_tweetcount'], 'integer');
                $node1->addNodeAttribute('from_user_followercount', $tweets[$in_reply_to_status_id]['from_user_followercount'], 'integer');
                $node1->addNodeAttribute('from_user_friendcount', $tweets[$in_reply_to_status_id]['from_user_friendcount'], 'integer');
                $node1->addNodeAttribute('from_user_listed', $tweets[$in_reply_to_status_id]['from_user_listed'], 'integer');
                $node1->addNodeAttribute('geo_lng', $tweet['geo_lng'], 'integer');
                $node1->addNodeAttribute('geo_lat', $tweet['geo_lat'], 'integer');
                $node1->addNodeAttribute('source', $tweets[$in_reply_to_status_id]['source'], 'string');
            }
            if (isset($indegree[$in_reply_to_status_id]))
                $node1->addNodeAttribute('indegree', $indegree[$in_reply_to_status_id], 'integer');
            if (isset($outdegree[$in_reply_to_status_id]))
                $node1->addNodeAttribute('outdegree', $outdegree[$in_reply_to_status_id], 'integer');

            if (isset($network_types[$in_reply_to_status_id]))
                $node1->addNodeAttribute('network_type', $network_types[$in_reply_to_status_id], 'string');
            $gexf->addNode($node1);

            $node2 = new GexfNode($tweet_id);
            // if node already exists, we want the attributes added that node instead
            $id2 = $gexf->nodeExists($node2);
            if ($id2 !== false && isset($gexf->nodeObjects[$id2]))
                $node2 = $gexf->nodeObjects[$id2];
            $node2->addNodeAttribute('tweet_id', $tweet_id, 'string');
            $node2->addNodeAttribute('created_at', $tweets[$tweet_id]['created_at'], 'string');
            $node2->addNodeAttribute('in_reply_to_status_id', $in_reply_to_status_id, 'string');
            $node2->addNodeAttribute('tweet', $tweets[$tweet_id]['text'], 'string');
            $node2->addNodeAttribute('user', $tweets[$tweet_id]['from_user_name'], 'string');
            $node2->addNodeAttribute('from_user_lang', $tweets[$tweet_id]['from_user_lang'], 'string');
            $node2->addNodeAttribute('from_user_tweetcount', $tweets[$tweet_id]['from_user_tweetcount'], 'integer');
            $node2->addNodeAttribute('from_user_followercount', $tweets[$tweet_id]['from_user_followercount'], 'integer');
            $node2->addNodeAttribute('from_user_friendcount', $tweets[$tweet_id]['from_user_friendcount'], 'integer');
            $node2->addNodeAttribute('from_user_listed', $tweets[$tweet_id]['from_user_listed'], 'integer');
            $node2->addNodeAttribute('geo_lng', $tweet['geo_lng'], 'integer');
            $node2->addNodeAttribute('geo_lat', $tweet['geo_lat'], 'integer');
            $node2->addNodeAttribute('source', $tweets[$tweet_id]['source'], 'string');
            if (isset($indegree[$tweet_id]))
                $node2->addNodeAttribute('indegree', $indegree[$tweet_id], 'integer');
            if (isset($outdegree[$tweet_id]))
                $node2->addNodeAttribute('outdegree', $outdegree[$tweet_id], 'integer');
            if (isset($network_types[$tweet_id]))
                $node2->addNodeAttribute('network_type', $network_types[$tweet_id], 'string');

            $gexf->addNode($node2);
            $edge_id = $gexf->addEdge($node2, $node1);
        }

        $gexf->render();

        file_put_contents($filename, $gexf->gexfFile);

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your File</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>

<?php

function getTweet($id) {
    global $esc;
    global $collation;
    global $dbh;
    $sql = "SELECT id, created_at, from_user_name COLLATE $collation as from_user_name, text COLLATE $collation as text, in_reply_to_status_id, from_user_lang, from_user_tweetcount, from_user_followercount, from_user_friendcount, from_user_listed, source COLLATE $collation as source FROM " . $esc['mysql']['dataset'] . "_tweets t WHERE id = $id";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    if ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
        return $res;
    } else {
        return false;
    }
}
?>
