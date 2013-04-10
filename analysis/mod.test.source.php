<?php

	require_once './common/config.php';
	require_once './common/functions.php';

	validate_all_variables();

	$exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];

	$sql = "SELECT source FROM ".$esc['mysql']['dataset']."_tweets t WHERE ";
	$sql .= sqlSubset();

	//print $sql." - <br>";

	$sqlresults = mysql_query($sql);

	$sources = array();


	while ($res = mysql_fetch_assoc($sqlresults)) {

		if(!isset($sources[$res["source"]])) {
			$sources[$res["source"]] = 1;
		} else {
			$sources[$res["source"]]++;
		}
	}

	asort($sources);

	$sources = array_reverse($sources);

	//print_r($sources);

	foreach ($sources as $key => $value) {
		echo $key . "," . $value . "<br />";
	}

?>
