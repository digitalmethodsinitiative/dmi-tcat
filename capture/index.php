<html>
<head>
	<title>DMI Twitter Analytics Status</title>

	<script type='text/javascript' src='../analysis/scripts/jquery-1.7.1.min.js'></script>
	<script type="text/javascript" src="../analysis/scripts/tablesorter/jquery.tablesorter.min.js"></script>

	<script type="text/javascript">

		$(document).ready(function() {
			$("#thetable").tablesorter();
		});

		function setArchive(_bin) {

			_answer = window.confirm("Are you sure that you want to stop capturing the '" + _bin + "' bin?");

			if(_answer == true) {
				alert("aiight!");
			}

		}

		function setModify(_bin,_keywords) {

			_answer = window.prompt("modify keywords?",_keywords);

			if(_answer == true) {
				alert("aiight!");
			}

		}

	</script>

	<style type="text/css">

		body,html { font-family:Arial, Helvetica, sans-serif; font-size:12px; }

		table { font-size:11px; }
		th { background-color: #ccc; padding:5px; }
		th.toppad { font-size:14px; text-align:left; padding-top:20px; background-color:#fff; }
		td { background-color: #eee; padding:5px; }
		.keywords { width:400px; }

	</style>


</head>

<body>

<h1>DMI Twitter Capture and Analysis Toolset - Capture Config</h1>

<?php

require_once '../analysis/common/config.php';
require_once '../analysis/common/functions.php';

$datasets = get_all_datasets();
//print_r($datasets);

$count = get_total_nr_of_tweets();
echo '' . number_format($count, 0, ",", ".") . ' tweets archived so far (and counting)<br /><br />';

echo 'add query bin';
echo '<div><form>form</form><div>';


$ordered_datasets = array();
foreach($datasets as $key => $set) {
	if (preg_match("/ytk_/", $key)) {
		$ordered_datasets["ytk imports"][$key] = $set;
	} elseif (preg_match("/user_/", $key)) {
		$ordered_datasets["user captures"][$key] = $set;
	} elseif(preg_match("/sample_/", $key)) {
		$ordered_datasets["one percent samples"][$key] = $set;
	} else {
		$ordered_datasets["keyword captures"][$key] = $set;
	}
}



echo '<table id="thetable">';

foreach($ordered_datasets as $groupname => $group) {


 	echo '<thead>';
	echo '<tr><th colspan="6" class="toppad">'.$groupname.'</th></tr>';
	echo '<tr>';
	echo '<th>querybin</th>';
	echo '<th class="keywords">queries</th>';
	echo '<th>no. tweets</th>';
	echo '<th>startdate</th>';
	echo '<th>enddate</th>';
	echo '<th></th>';
	echo '<th></th>';
	echo '</tr>';
 	echo '</thead>';
	echo '<tbody>';


    foreach ($group as $key => $set) {

		echo '<tr>';
 		echo '<td>'.$set["bin"].'</td>';
		echo '<td class="keywords">'.preg_replace("/,\s*/",", ",$set["keywords"]).'</td>';
		echo '<td>'.$set["notweets"].'</td>';
		echo '<td>'.$set['mintime'].'</td>';
		echo '<td>'.$set['maxtime'].'</td>';
		echo '<td><a href="" onclick="setModify(\''.$set["bin"].'\',\''.addslashes($set["keywords"]).'\'); return false;">modify</a></td>';
		echo '<td><a href="" onclick="setArchive(\''.$set["bin"].'\'); return false;">archive</a></td>';
		echo '</tr>';
    }

	echo '</tbody>';
}

echo '</table><br /><br />';

?>

</table>

</body>
</html>
