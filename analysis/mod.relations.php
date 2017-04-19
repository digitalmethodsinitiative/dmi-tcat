<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
ini_set('memory_limit', '3G');
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Relation graph</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">



        </script>

    </head>

    <body>

        <h1>TCAT :: Relation graph</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);
        $collation = current_collation();

        if (empty($esc['mysql']['from_user_name']))
            die('<br><Br>please use a set of users in the from user field');
        //print "start";
        $sql = "SELECT user1_id, user2_id FROM " . $esc['mysql']['dataset'] . "_relations r, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND t.from_user_id = r.user1_id AND type = 'friend' GROUP BY user1_id, user2_id ORDER BY user1_id, user2_id";
        $q = $dbh->prepare($sql);
        $edges = array();
        if ($q->execute()) {
            while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
                $edges[$r['user1_id']][] = $r['user2_id'];
            }
        } else
            die('relations query failed');
        //print "edges loaded<br>";

        $allusers = $originals = array();
        foreach ($edges as $user1 => $users2) {
            $allusers[] = $user1;
            $originals[] = $user1;
            $acv = array_count_values($users2);
            unset($edges[$user1]);
            foreach ($acv as $v => $c) {
                $edges[$user1][$v] = $c;
                $allusers[] = $v;
            }
        }
        $allusers = array_unique($allusers);
        //print "edges recounted<br>";
        $sql = "SELECT id, screen_name FROM " . $esc['mysql']['dataset'] . "_users WHERE id IN ( " . implode(",", $allusers) . ")";
        $q = $dbh->prepare($sql);
        $usernames = array();
        if ($q->execute()) {
            $res = $q->fetchAll(PDO::FETCH_ASSOC);
            foreach ($res as $r) {
                $usernames[$r['id']] = $r['screen_name'];
            }
        } else
            die('users query failed');

        $sql = "SELECT from_user_id, from_user_name FROM " . $esc['mysql']['dataset'] . "_tweets WHERE id IN ( " . implode(",", $allusers) . ") group by from_user_id";
        $q = $dbh->prepare($sql);
        $originals_extended = array();
        if ($q->execute()) {
            $res = $q->fetchAll(PDO::FETCH_ASSOC);
            foreach ($res as $r) {
                $originals_extended[$r['from_user_id']] = $r['from_user_name'];
            }
        } else
            die('users lookup failed');
        unset($allusers);

        $csv = file('users_classified.csv'); // @todo, make more generic
        $classifications = array();
        for ($i = 1; $i < count($csv); $i++) {
            $e = explode("\t", $csv[$i]);
            $account = trim(strtolower($e[0]));
            $classifications[$account]['affiliation'] = $e[1];
            $classifications[$account]['list'] = $e[2];
        }

        $filename = get_filename_for_export("relations", "", "gdf");
        $handle = fopen($filename, "w");
        fwrite($handle, "nodedef>name VARCHAR,label VARCHAR, affiliation VARCHAR, list VARCHAR\n");
        foreach ($usernames as $id => $name) {
            $namel = strtolower($name);
            $affiliation = $list = "n/a";
            if (array_search($id, $originals) !== false) {
                $affiliation = "starting_points";
                $list = "starting_points";
            } elseif (isset($classifications[$namel])) {
                $affiliation = trim($classifications[$namel]['affiliation']);
                $list = trim($classifications[$namel]['list']);
            }
            fwrite($handle, $id . "," . $name . "," . $affiliation . "," . $list . "\n");
        }

        fwrite($handle, "edgedef>node1 VARCHAR,node2 VARCHAR,weight DOUBLE,directed BOOLEAN\n");
        foreach ($edges as $user1 => $users) {
            foreach ($users as $user2 => $weight) {
                fwrite($handle, $user1 . "," . $user2 . "," . $weight . ",true\n");
            }
        }
        fclose($handle);

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
