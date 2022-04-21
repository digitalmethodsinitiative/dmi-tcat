<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics User Keyword Usage</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

    </head>

    <body>

        <h1>TCAT :: user keyword usage</h1>

        <?php
        validate_all_variables();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        // Validate keywords
        if ($esc['mysql']['keyword_query'] == "" ) {
            // No keywords? No good.
            // TODO: how to actually fail a mod?
            echo '<h2>Keywords are necessary for this analysis; please try again</h2>';
        }

        // REAL QUESTION: faster to filter data by all keywords, then loop and search for each keyword to tally results OR
        // filter all data multiple times by each keyword for result; depends on total number of tweets and size of subset!

        $collation = current_collation();

        // Start off SQL query
        $sql = "SELECT t.from_user_id, t.from_user_name, t.text ";
        $sql .= "FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $where = '';

        // Add keyword_query terms (currently unsure if AND should be supported) - borrowed from $esc['mysql']['query'] in functions
        // Also collect keywords
        $keyword_array = array();
        $count = 0;
        if (strstr($esc['mysql']['keyword_query'], "AND") !== false) {
            $subqueries = explode(" AND ", $esc['mysql']['keyword_query']);
            foreach ($subqueries as $subquery) {
                $where .= "LOWER(t.text COLLATE $collation) LIKE LOWER('%" . $subquery . "%' COLLATE $collation) AND ";

                $keyword_array[$subquery] = array(
                        'name' => 'k' . $count,
                        'label' => $subquery,
                );
                $count = $count + 1;
            }
        } elseif (strstr($esc['mysql']['keyword_query'], "OR") !== false) {
            $subqueries = explode(" OR ", $esc['mysql']['keyword_query']);
            $where .= "(";
            foreach ($subqueries as $subquery) {
                $where .= "LOWER(t.text COLLATE $collation) LIKE LOWER('%" . $subquery . "%' COLLATE $collation) OR ";

                $keyword_array[$subquery] = array(
                    'name' => 'k' . $count,
                    'label' => $subquery,
                );
                $count = $count + 1;
            }
            $where = substr($where, 0, -3) . ") AND ";
        } else {
            $where .= "LOWER(t.text COLLATE $collation) LIKE LOWER('%" . $esc['mysql']['keyword_query'] . "%' COLLATE $collation) AND ";

            $keyword_array[$esc['mysql']['keyword_query']] = array(
                'name' => 'k' . $count,
                'label' => $esc['mysql']['keyword_query'],
            );
        }

        // Include rest of parameters
        $sql .= sqlSubset($where);

        // Check handy work if desired
        print $sql . "<bR>";

        // Collect users
        $users_array = array();
        // Collect relationships
        $user_keyword_matches = array();

        // Send query
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {

            foreach($keyword_array as $keyword) {
                if (strpos($res['text'], $keyword['label']) !== false) {
                    // Relationship found
                    $relationship_label = $keyword_array[$keyword]['name'] . '-' . $res['from_user_id'];

                    if (!array_key_exists($res['from_user_id'], $users_array)) {
                        // add new user
                        $users_array[$res['from_user_id']] = array(
                                'name' => $res['from_user_id'],
                                'label' => $res['from_user_name'],
                        );
                    }

                    if (array_key_exists($relationship_label, $user_keyword_matches)) {
                        // increment if relationship already identified
                        $user_keyword_matches[$relationship_label]['weight'] += 1;
                    } else {
                        // add new relationship
                        $user_keyword_matches[$relationship_label] = array(
                            'node1' => $res['from_user_id'],
                            'node2' => $keyword_array[$keyword['label']]['name'],
                            'weight' => 1,
                        );

                    }
                }

            }
        }


        $filename = get_filename_for_export("user_keywords", $esc['shell']['keyword_query'], "gdf");

        $lookup = array();

        $fp = fopen($filename, 'w');

        fwrite($fp, chr(239) . chr(187) . chr(191));
        fwrite($fp, "nodedef> name VARCHAR,label VARCHAR\n");

        foreach($keyword_array as $keyword) {
            fwrite($fp, $keyword['name'] . "," . $keyword['label'] . "\n");
        }
        foreach($users_array as $user) {
            fwrite($fp, $user['name'] . "," . $user['label'] . "\n");
        }

        fwrite($fp, "edgedef> node1 VARCHAR,node2 VARCHAR,weight INT\n");

        foreach($user_keyword_matches as $match) {
            fwrite($fp, $match['node1'] . "," . $match['node2'] . "," . $match['weight'] . "\n");
        }

        fclose($fp);

        //file_put_contents($filename, $coword->getCowordsAsGexf($filename));

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your GEXF File</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
