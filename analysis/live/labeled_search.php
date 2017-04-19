<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/functions.php';
require_once __DIR__ . '/../../api/lib/tcat_util.php';
require_once 'functions.php';

require_once 'kandidaten.php';

// % Matched

// nr. tweets
// nr. partij
// per partij: zelf verklaard

foreach ($zelfverklaard as $k => $v) {
    if (!array_key_exists($k, $kandidaten)) {
        $kandidaten[$k] = 'zelfverklaard ' . $v;
    }
}

foreach ($spindoctors as $k => $v) {
    if (!array_key_exists($k, $kandidaten)) {
        $kandidaten[$k] = 'spindoctor ' . $v;
    }
}

foreach ($manueel as $k => $v) {
    if (!array_key_exists($k, $kandidaten)) {
        $kandidaten[$k] = 'manueel ' . $v;
    }
}

foreach ($apptivisten as $k => $v) {
    if (!array_key_exists($k, $kandidaten)) {
        $kandidaten[$k] = 'apptivist ' . $v;
    }
}

foreach ($politiek_journalist as $k => $v) {
    if (!array_key_exists($k, $kandidaten)) {
        $kandidaten[$k] = $v;
    }
}

foreach ($kandidaten as $k => $v) {
    if (!preg_match("/^spindoctor/", $v) && !preg_match("/^zelfverklaard/", $v) &&
        !preg_match("/^verdacht/", $v) && !preg_match("/^politiek_journalist/", $v) &&
        !preg_match("/^manueel/", $v) && !preg_match("/^apptivist/", $v)) {
        $kandidaten[$k] = 'kandidaat ' . $v;
    }
}


error_reporting(E_ALL);

$dbh = pdo_connect();

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title><?= $dataset ?> - TCAT live</title>
    </head>
    <body>
<?php
    echo "<h1>Current dataset: $dataset</h1>";
    echo "<h3>Query for tweets from kandidaten</h3>";
?>
     <form>
<?php
    echo '<select id="ipt_dataset" name="dataset" class="form-control">';

    $ordered_datasets = array();
    foreach ($datasets as $key => $set) {
        $ordered_datasets["other"][$key] = $set;
    }
    ksort($ordered_datasets);

    $count = 0;
    foreach ($ordered_datasets as $groupname => $group) {

        echo '<optgroup label="' . $groupname . '">';

        foreach ($group as $key => $set) {

            $v = ($key == $dataset) ? 'selected="selected"' : "";

            echo '<option value="' . $key . '" ' . $v . '>' . $set["bin"] . ' --- ' . number_format($set["notweets"], 0, ",", ".") . ' tweets from ' . $set['mintime'] . ' to ' . $set['maxtime'] . '</option>';
            $count += $set['notweets'];
        }

        echo '</optgroup>';
    }

    echo "</select> ";
?>
        <p></p>
        Free text:<br>
        <p></p> 
        <input type="text" name="text"><br>
        <p></p> 
        <input type="submit" value="Submit">
        <p></p> 
    </form> 
<?php
    $total = $subtotal_loltweeters = $subtotal_kandidaten = $subtotal_spindoctors = $subtotal_politiek_journalisten = $subtotal_zelfverklaard = $subtotal_nvt = 0;
    $userstats = array();
    $finegrained_counts = array();
    $buffer = '';
    if (is_string($_GET['text']) && strlen($_GET['text']) > 0) {

        $text = $_GET['text'];

    } else {

        $text = null;

    }

        // First query to find lol-only tweeters

        $sql = "SELECT distinct(from_user_name) FROM $dataset" . "_tweets WHERE retweet_id = 835953943285022720 OR text like 'RT @LoesjeNL:%' OR text like 'RT @DeSpeld:%' AND created_at >= '2017-02-26 19:00:00' AND created_at <= '2017-02-26 23:00:00'";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        $lollers = array();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $lollers[$res['from_user_name']] = 1;
//            print "DEBUG SET " . $res['from_user_name'] . " " . $res['text'] . "<br>";
        }
        $sql = "SELECT distinct(from_user_name) FROM $dataset" . "_tweets WHERE retweet_id != 835953943285022720 AND text not like 'RT @LoesjeNL:%' AND text not like 'RT @DeSpeld:%' AND created_at >= '2017-02-26 19:00:00' AND created_at <= '2017-02-26 23:00:00'";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            if (array_key_exists($res['from_user_name'], $lollers)) {
//                print "DEBUG UNSET " . $res['from_user_name'] . "<br>";
                unset($lollers[$res['from_user_name']]);
            }
        }

        // Default query

        //$sql = "SELECT from_user_name, created_at, text FROM $dataset" . "_tweets WHERE text LIKE :text COLLATE utf8mb4_bin";
        if (is_null($text)) {
            $sql = "SELECT from_user_name, created_at, text FROM $dataset" . "_tweets WHERE created_at >= '2017-02-26 19:00:00' AND created_at <= '2017-02-26 23:00:00'";
        } else {
            $sql = "SELECT from_user_name, created_at, text FROM $dataset" . "_tweets WHERE text LIKE '%" . $text . "%' AND created_at >= '2017-02-26 19:00:00' AND created_at <= '2017-02-26 23:00:00'";
        }
        //print "<pre>$sql - param $text</pre>";
        // TODO: escape
        $rec = $dbh->prepare($sql);
//        $rec->bindParam(':text', '%' . $text . '%', PDO::PARAM_STR);
        $rec->execute();
        $loltweeters_after_filter = array();                // someone is only a loltweeter if otherwise category: n.v.t.


        $unique_users = array();

        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $lower = strtolower($res['from_user_name']);
            $key = '';
            $found = false;
            foreach ($kandidaten as $kan => $aff) {
                $kan_lower = strtolower($kan);
                //print "<pre>$kan_lower vs. $lower</pre><br>";
                if ($lower == $kan_lower) {
                    $key = $kan;
                    $found = true; break;
                }
            }
            $partij = strtolower($kandidaten[$key]);
            $buffer .= "<tr>"; 
            $buffer .= "<td>" . $res['from_user_name'] . "</td>";
            if ($found) {
                $buffer .= "<td>" . $kandidaten[$key] . "</td>";
            } else {
                $buffer .= "<td>n.v.t.</td>";
            }
            $buffer .= "<td>" . $res['created_at'] . "</td>";
            $buffer .= "<td>" . $res['text'] . "</td>";
            $buffer .= "</tr>";

            $total++;
            if ($found) {
                if (preg_match("/^kandidaat/", $kandidaten[$key])) {
                    $subtotal_kandidaten++;
                } else if (preg_match("/^spindoctor/", $kandidaten[$key])) {
                    $subtotal_spindoctors++;
                } else if (preg_match("/^politiek_journalist/", $kandidaten[$key])) {
                    $subtotal_politiek_journalisten++;
                } else if (preg_match("/^zelfverklaard/", $kandidaten[$key])) {
                    $subtotal_zelfverklaard++;
                } else if (preg_match("/^manueel/", $kandidaten[$key])) {
                    $subtotal_manueel++;
                } else if (preg_match("/^apptivist/", $kandidaten[$key])) {
                    $subtotal_apptivist++;
                }
            } else {
                if (array_key_exists($res['from_user_name'], $lollers)) {
                    $loltweeters_after_filter[$res['from_user_name']] = 1;
                    $subtotal_loltweeters++;
                    if (!array_key_exists('loltweeter', $finegrained_counts)) {
                        $finegrained_counts['loltweeter'] = 1;
                    } else {
                        $finegrained_counts['loltweeter'] += 1;
                    }
                    continue;
                } else {
                    $subtotal_nvt++;
                }
            }
            if (!array_key_exists($kandidaten[$key], $finegrained_counts)) {
                $finegrained_counts[$kandidaten[$key]] = 1;
            } else {
                $finegrained_counts[$kandidaten[$key]] += 1;
            }
            
            if (!array_key_exists($res['from_user_name'], $unique_users)) {
/*
                $fields = explode(' ', $kandidaten[$key]);
                if (count($fields) < 2) {
                    $role = $kandidaten[$key];
                    $role = '';
                } else {
                    $aff = $fields[0];
                    $aff = $fields[1];
                }
*/
                if (!array_key_exists($kandidaten[$key], $userstats)) {
                    $userstats[$kandidaten[$key]] = 1;
                } else {
                    $userstats[$kandidaten[$key]] += 1;
                }
                $unique_users[$res['from_user_name']] = 1;
            }

        }

?>
    <table>
    <tr><td>Queried</td><td><?php echo $dataset; ?></td></tr>
    <tr><td>Queried for</td><td><?php echo $_GET['text']; ?></td></tr>
    <tr><td>Nr. of tweets</td><td><?php echo $total; ?></td></tr>
    <tr><td>Nr. of tweets (geen definitie)</td><td><?php echo $subtotal_nvt;?></td></tr>
    <tr><td>Nr. of tweets (zelfverklaard)</td><td><?php echo $subtotal_zelfverklaard;?></td></tr>
    <tr><td>Nr. of tweets (kandidaten)</td><td><?php echo $subtotal_kandidaten;?></td></tr>
    <tr><td>Nr. of tweets (spindoctors)</td><td><?php echo $subtotal_spindoctors;?></td></tr>
    <tr><td>Nr. of tweets (politiek journalisten)</td><td><?php echo $subtotal_politiek_journalisten;?></td></tr>
    <tr><td>Nr. of tweets (manueel)</td><td><?php echo $subtotal_manueel;?></td></tr>
    <tr><td>Nr. of tweets (apptivisten)</td><td><?php echo $subtotal_apptivist;?></td></tr>
    <tr><td>Nr. of tweets (lol tweets)</td><td><?php echo $subtotal_loltweeters;?></td></tr>
    <tr><td>Nr. of lol tweet users</td><td><?php echo count($loltweeters_after_filter); ?></td></tr>
    <p></p>
    <tr><td>(scroll down for detailed tables)</td></tr>
    <p</p>
    </table>
    <p></p>
    <table>
    <tr><th>user</th><th>partij</th><th>created_at</th><th>text</th></tr>
<?php
        print $buffer;
?>
    </table>
    <table>
    <p></p>
    <tr><th>role</th><th>affiliation</th><th>tweet count</th><th>unique user count</th><th>avg. tweets per user</th></tr>
    <?php
        arsort($finegrained_counts);
        foreach ($finegrained_counts as $aff => $count) {
            $unique_user_count = $userstats[$aff];
            if ($aff == '') { $aff = 'n.v.t.'; }
            $fields = explode(' ', $aff);
            if (count($fields) < 2) {
                if ($aff == 'loltweeter') {
                    print "<tr><td>$aff</td><td></td><td>$count</td><td>" . count($loltweeters_after_filter) . "</td><td>" . round($count / count($loltweeters_after_filter), 2) .  "</td></tr>";
                } else {
                    print "<tr><td>$aff</td><td></td><td>$count</td><td>$unique_user_count</td><td>" . round($count / $unique_user_count, 2) . "</td></tr>";
                }
            } else {
                print "<tr><td>" . $fields[0] . "</td><td>" . $fields[1]  . "</td><td>$count</td><td>$unique_user_count</td><td>" . round($count / $unique_user_count, 2) . "</td></tr>";
            }
        }

    ?>
    </table>
    </body>
</html>

