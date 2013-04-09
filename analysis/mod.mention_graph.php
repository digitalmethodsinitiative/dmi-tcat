<?php
require_once './common/config.php';
require_once './common/functions.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>Twitter Analytics</h1>

<?php
// => gexf
// => time

validate_all_variables();

$exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
$filename = $resultsdir . $esc['shell']['datasetname'] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . "_mentionGraph.gdf";

if (1 || !file_exists($filename)) {
//if(true) {

    $users = array();
    $usersinv = array();
    $edges = array();

    $cur = 0;
    $numresults = 10000;

    while ($numresults == 10000) {

        $sql = "SELECT m.from_user_name,m.to_user FROM " . $esc['mysql']['dataset'] . "_mentions m, ".$esc['mysql']['dataset']."_tweets t WHERE m.tweet_id = t.id AND ";
        $sql .= sqlSubset();
        $sql .= " LIMIT " . $cur . "," . $numresults;
//print $sql."<br>";
        $sqlresults = mysql_query($sql);

        while ($data = mysql_fetch_assoc($sqlresults)) {

            $data["from_user_name"] = strtolower($data["from_user_name"]);

            if (!isset($users[$data["from_user_name"]])) {

                $users[$data["from_user_name"]] = $arrayName = array('id' => count($usersinv), 'notweets' => 1);

                $usersinv[] = $data["from_user_name"];
            } else {

                $users[$data["from_user_name"]]["notweets"]++;
            }

            if (!isset($users[$data["to_user"]])) {

                $users[$data["to_user"]] = $arrayName = array('id' => count($usersinv), 'notweets' => 0);

                $usersinv[] = $data["to_user"];
            }

            $to = $users[$data["from_user_name"]]["id"] . "," . $users[$data["to_user"]]["id"];

            if (!isset($edges[$to])) {

                $edges[$to] = 1;
            } else {

                $edges[$to]++;
            }
        }

        $numresults = mysql_num_rows($sqlresults);
        $cur = $cur + $numresults;
    }


    $content = "nodedef>name VARCHAR,label VARCHAR,no_tweets INT\n";

    foreach ($users as $key => $value) {
        $content .= $value["id"] . "," . $key . "," . $value["notweets"] . "\n";
    }

    $content .= "edgedef>node1 VARCHAR,node2 VARCHAR,weight DOUBLE\n";

    foreach ($edges as $key => $value) {

        $content .= $key . "," . $value . "\n";
    }

    file_put_contents($filename, $content);
}

echo '<fieldset class="if_parameters">';

echo '<legend>Your File</legend>';

echo '<p><a href="' . str_replace("#", urlencode("#"), $filename) . '">' . $filename . '</a></p>';

echo '</fieldset>';
?>

    </body>
</html>
