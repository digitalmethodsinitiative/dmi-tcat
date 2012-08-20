<?php

require_once './common/config.php';
require_once './common/functions.php';
	
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Twitter Tool</title>
	
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	
	<link rel="stylesheet" href="css/main.css" type="text/css" />
	
	<script type="text/javascript" language="javascript">
	
	
	
	</script>
		
</head>

<body>

<h1>Twitter Analytics</h1>

<table>
	

<form action="<?php echo $_SERVER["PHP_SELF"]; ?>">
	<input type="hidden" name="dataset" value="<?php echo $dataset; ?>" />
	<input type="hidden" name="query" value="<?php echo $query; ?>" />
	<input type="hidden" name="from_user" value="<?php echo $from_user; ?>" />
	<input type="hidden" name="startdate" value="<?php echo $startdate; ?>" />
	<input type="hidden" name="enddate" value="<?php echo $enddate; ?>" />
	<tr>
		<td>No. of tweets:</td>
		<td><input type="text" name="samplesize" value="<?php echo $samplesize; ?>" /></td>
	</tr>
	<tr>
		<td><input type="submit" value="create file" /></td>
	</tr>
</form>

</table>

<?php

if($samplesize > 0) {

	echo '<fieldset class="if_parameters">';

	echo '<legend>Your File</legend>';

	validate_all_variables();
	get_dataset_name();
	$exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
	$filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"]. "_" . $esc['shell']["from_user"] . "_".$samplesize."randomTweets.csv";	

	if(!file_exists($filename)) {

		$content = "time,created_at,from_user,text,source,profile_image_url\n";

		$sql = "SELECT * FROM " . $esc['mysql']['dataset'] . " WHERE ";
		if(!empty($esc['mysql']['from_user'])) {
			$subusers = explode(" OR ", $esc['mysql']['from_user']);
			$sql .= "(";
			for($i = 0; $i < count($subusers); $i++) {
				$subusers[$i] = "from_user = '" . $subusers[$i] . "'";
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
		$sql .= "time >= " . $esc['timestamp']['startdate'] . " AND time <= " . $esc['timestamp']['enddate']." ";
		$sql .= "ORDER BY RAND() LIMIT ".$samplesize;

		$sqlresults = mysql_query($sql);
		while($data = mysql_fetch_assoc($sqlresults)) {
			$mysqltime = date ("Y-m-d H:i:s", $data["time"]);
			$content .= $data["time"] . "," . $mysqltime . "," . $data["from_user"] . "," . validate($data["text"],"tweet") . "," . strip_tags(html_entity_decode($data["source"])) . "," . $data["profile_image_url"] . "\n";
		}
		
		file_put_contents($filename,$content);
	}

	echo '<p><a href="'.str_replace("#",urlencode("#"),str_replace("\"","%22",$filename)).'">' . $filename . '</a></p>';
	
	echo '</fieldset>';
}

?>

</body>
</html>