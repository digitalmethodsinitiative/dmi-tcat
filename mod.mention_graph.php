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
$filename = $resultsdir . $esc['shell']['datasetname'] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"]. "_" . $esc['shell']["from_user_name"] . "_mentionGraph.gdf";	

if(1 || !file_exists($filename)) {
//if(true) {

	$users = array();
	$usersinv = array();
	$edges = array();

	$cur = 0;
	$results = 10000;   //@todo, explain to user

	while($results == 10000) {

		$sql = "SELECT from_user_name,text FROM " . $esc['mysql']['dataset'] . "_tweets WHERE ";
		if(!empty($esc['mysql']['from_user_name'])) {
			$subusers = explode(" OR ", $esc['mysql']['from_user_name']);
			$sql .= "(";
			for($i = 0; $i < count($subusers); $i++) {
				$subusers[$i] = "from_user_name = '" . $subusers[$i] . "'";
			}
			$sql .= implode(" OR ", $subusers);
			$sql .= ") AND ";
		}
		if(!empty($esc['mysql']['query'])) {
			$subqueries = explode(" AND ", $esc['mysql']['query']);
			foreach($subqueries as $subquery) {
				$sql .= "text LIKE '%" . $subquery . "%' AND ";
			}
		}
		if(!empty($esc['mysql']['exclude'])) 
			$sql .= "text NOT LIKE '%" . $esc['mysql']['exclude'] . "%' AND ";
		$sql .= "created_at >= '" . $esc['datetime']['startdate'] . "' AND created_at <= '" . $esc['datetime']['enddate']."' ";
		$sql .= "LIMIT ".$cur.",".$results;
	
		$sqlresults = mysql_query($sql);

		while($data = mysql_fetch_assoc($sqlresults)) {
		
			$data["from_user_name"] = strtolower($data["from_user_name"]);
			$data["text"] = strtolower($data["text"]);
			
		
			if(!isset($users[$data["from_user_name"]])) {
							
				$users[$data["from_user_name"]] = $arrayName = array('id' => count($usersinv), 'notweets' => 1);
				
				$usersinv[] = $data["from_user_name"];
				
			} else {
					
				$users[$data["from_user_name"]]["notweets"]++;
			}
		
			// process mentions in tweet
			preg_match_all("/@(.+?)\W/", $data["text"], $out);
			
			foreach ($out[1] as $mention) {
					
				if(!isset($users[$mention])) {
							
					$users[$mention] = $arrayName = array('id' => count($usersinv), 'notweets' => 0);
				
					$usersinv[] = $mention;
				}
				
				$to = $users[$data["from_user_name"]]["id"] . "," . $users[$mention]["id"];
				
				if(!isset($edges[$to])) {
					
					$edges[$to] = 1;
				} else {
					
					$edges[$to]++;
				}
			}
		
		}
		
		$results =  mysql_num_rows($sqlresults);
		$cur = $cur + $results;
	}
	
	
	$content = "nodedef>name VARCHAR,label VARCHAR,no_tweets INT\n";
		
	foreach($users as $key => $value) {
		$content .= $value["id"] . "," . $key . "," . $value["notweets"] . "\n";
	}
	
	$content .= "edgedef>node1 VARCHAR,node2 VARCHAR,weight DOUBLE\n";
		
	foreach($edges as $key => $value) {
	
		$content .= $key . "," . $value . "\n";
	}
	
	file_put_contents($filename,$content);
}

echo '<fieldset class="if_parameters">';

echo '<legend>Your File</legend>';

echo '<p><a href="'.str_replace("#",urlencode("#"),$filename).'">' . $filename . '</a></p>';

echo '</fieldset>';

?>

</body>
</html>