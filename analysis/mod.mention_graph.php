<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Mention graph</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">



        </script>

    </head>

    <body>

        <h1>TCAT :: Mention graph</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);
        $collation = current_collation();

        $users = array();
        $usersinv = array();
        $edges = array();

        $cur = 0;
        $numresults = 500000;

		//print_r($esc); exit;

        while ($numresults == 500000) {

            $sql = "SELECT m.from_user_name COLLATE $collation as from_user_name, m.to_user COLLATE $collation as to_user FROM " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t ";
            $where = "m.tweet_id = t.id AND ";
            $sql .= sqlSubset($where);
            $sql .= " LIMIT " . $cur . "," . $numresults;

			//print $sql."<br>";

            $numresults = 0;

            $rec = $dbh->prepare($sql);
            $rec->execute();
            while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            
                $numresults++;

                $data["from_user_name"] = strtolower($data["from_user_name"]);
                $data["to_user"] = strtolower($data["to_user"]);

                if (!isset($users[$data["from_user_name"]])) {
                    $users[$data["from_user_name"]] = $arrayName = array('id' => count($usersinv), 'notweets' => 1,'nomentions' => 0);
                    $usersinv[] = $data["from_user_name"];
                } else {
                    $users[$data["from_user_name"]]["notweets"]++;
                }

                if (!isset($users[$data["to_user"]])) {
                    $users[$data["to_user"]] = $arrayName = array('id' => count($usersinv), 'notweets' => 0,'nomentions' => 1);
                    $usersinv[] = $data["to_user"];
                } else {
                    $users[$data["to_user"]]["nomentions"]++;
                }

                $to = $users[$data["from_user_name"]]["id"] . "," . $users[$data["to_user"]]["id"];

                if (!isset($edges[$to])) {
                    $edges[$to] = 1;
                } else {
                    $edges[$to]++;
                }
            }

            $cur = $cur + $numresults;

        }

		//print_r($users);

        $topusers = array();


		foreach ($users as $key => $user) {
			$topusers[$key] = $user["nomentions"];
		}

		arsort($topusers);

		if($esc["shell"]["topu"] > 0) {
			$topusers = array_slice($topusers,0,$esc["shell"]["topu"],true);
		}
		//print_r($topusers);


        $content = "nodedef>name VARCHAR,label VARCHAR,no_tweets INT,no_mentions INT\n";
        foreach ($users as $key => $value) {
        	if(isset($topusers[$key])) {
            	$content .= $value["id"] . "," . $key . "," . $value["notweets"] . "," . $value["nomentions"] . "\n";
            }
        }

        $content .= "edgedef>node1 VARCHAR,node2 VARCHAR,weight DOUBLE,directed BOOLEAN\n";
        foreach ($edges as $key => $value) {
			$tmp = explode(",", $key);
			if(isset($topusers[$usersinv[$tmp[0]]]) && isset($topusers[$usersinv[$tmp[1]]])) {
            	$content .= $key . "," . $value . ",true\n";
			}
        }

		//echo $content;

		// add filename for top user filter  "_minDegreeOf".$esc['shell']['minf']
        $filename = get_filename_for_export("mention","_Top".$esc['shell']['topu'],"gdf");
        file_put_contents($filename, $content);

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your File</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';

        ?>

    </body>
</html>
