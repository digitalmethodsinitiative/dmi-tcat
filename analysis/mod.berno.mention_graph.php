<?php

require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';

// TODO: test Follower vs. Friend Metrics

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Berno mention graph</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">


        </script>
    </head>

    <body>

        <h1>TCAT :: Berno mention graph</h1>

        <?php

        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        $users = array();
        $usersinv = array();
        $edges = array();

        $cur = 0;
        $numresults = 10000;

        while ($numresults == 10000) {

            $sql = "SELECT * FROM " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t ";
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

				// mentionning
                if (!isset($users[$data["from_user_name"]])) {
                    $users[$data["from_user_name"]] = $arrayName = array(
                    	'id' => count($usersinv),
                    	'nomentions' => 0,
                    	'notweets' => 0
					);

                    $usersinv[] = $data["from_user_name"];
                }

				// getting a mention
                if (!isset($users[$data["to_user"]])) {
                    $users[$data["to_user"]] = $arrayName = array(
                    	'id' => count($usersinv),
                    	'nomentions' => 1,
                    	'notweets' => 0
					);

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


		// --- iterate over all tweets to get all active users ---
		$sql = "SELECT * FROM " . $esc['mysql']['dataset'] . "_tweets t";
		//$where = "from_user_name='" . $checkuser . "' AND ";
		$sql .= sqlSubset();

		//echo $sql; exit;

		$tmpusers = array();

        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
			$data["from_user_name"] = strtolower($data["from_user_name"]);

			$users[$data["from_user_name"]]["notweets"]++;
			$users[$data["from_user_name"]]["user_tweetcount"] = $data["from_user_tweetcount"];
			$users[$data["from_user_name"]]["user_friendcount"] = $data["from_user_friendcount"];
			$users[$data["from_user_name"]]["user_followercount"] = $data["from_user_followercount"];
			$users[$data["from_user_name"]]["user_frienddivfollower"] = $data["from_user_followercount"] / $data["from_user_friendcount"];
			$users[$data["from_user_name"]]["user_friendminfollower"] = $data["from_user_followercount"] - $data["from_user_friendcount"];
			$users[$data["from_user_name"]]["user_listed"] = $data["from_user_listed"];
			$users[$data["from_user_name"]]["user_utcoffset"] = $data["from_user_utcoffset"];
		}


		// --- iterate over all URLs to create domain metrics ---
		$sql = "SELECT domain,from_user_name FROM " . $esc['mysql']['dataset'] . "_urls t";
		//$where = "from_user_name='" . $checkuser . "' AND ";
		$sql .= sqlSubset();

		//echo $sql;

		$tmpusers = array();

        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {

			$data["from_user_name"] = strtolower($data["from_user_name"]);

			if(!isset($tmpusers[$data["from_user_name"]])) {
				$tmpusers[$data["from_user_name"]] = array();
				$tmpusers[$data["from_user_name"]]["linkcount"] = 0;
				$tmpusers[$data["from_user_name"]]["domains"] = array();;
			}

			$tmpusers[$data["from_user_name"]]["linkcount"]++;
			$tmpusers[$data["from_user_name"]]["domains"][$data["domain"]] = true;
		}

		foreach($tmpusers as $key => $value) {
			$users[$key]["linkcount"] = $value["linkcount"];
			$users[$key]["domaincount"] = count($value["domains"]);
		}

		//print_r($data2); exit;


		//exit;
		//print_r($checkuser);


        $content = "nodedef>name VARCHAR,label VARCHAR,no_tweets INT,no_mentions INT,mentions_div_tweets DOUBLE,full_tweetcount INT,followercount INT,friendcount INT,listed INT,follower_div_friends DOUBLE,follower_min_friends INT,linkcount INT,domaincount INT,domaincount_div_linkcount DOUBLE,utcoffset INT\n";

        //print_r($users); exit;

        foreach ($users as $key => $value) {

			if(!isset($value["nomentions"])) { $value["nomentions"] = 0; }

            $content .= $value["id"] . "," .
            			$key . "," .
            			$value["notweets"] . "," .
            			$value["nomentions"] . "," .
            			($value["nomentions"] / $value["notweets"]) . "," .
            			$value["user_tweetcount"] . "," .
						$value["user_followercount"] . "," .
						$value["user_friendcount"] . "," .
						$value["user_listed"] . "," .
						$value["user_frienddivfollower"] . "," .
						$value["user_friendminfollower"] . "," .
						$value["linkcount"] . "," .
						$value["domaincount"] . "," .
						($value["domaincount"] / $value["linkcount"]) . "," .
            			$value["user_utcoffset"] . "\n";
        }


		// let's add more qualifications to the link
        $content .= "edgedef>node1 VARCHAR,node2 VARCHAR,weight DOUBLE,socialslope DOUBLE\n,directed BOOLEAN";

        foreach ($edges as $key => $value) {

			$ids = explode(",", $key);

			$from = $users[$usersinv[$ids[0]]]["user_followercount"];
			$to = $users[$usersinv[$ids[1]]]["user_followercount"];

			if($from == $to) {
				$slope = 0;
			}

			if($from > $to) {
				$slope = $from / $to;
				$slope = -$slope;
			}

			if($from < $to) {
				$slope = $to / $from;
			}

            $content .= $key . "," . $value . "," . $slope . ",true\n";
        }

        $filename = get_filename_for_export("mention","","gdf");
        file_put_contents($filename, $content);

        echo '<fieldset class="if_parameters">';

        echo '<legend>Your File</legend>';

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
