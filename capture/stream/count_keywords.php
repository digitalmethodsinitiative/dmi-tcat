<?php

include_once('ini.php');

list($count_q,$count_qb) = count_bins($querybins);
print "Active: $count_q queries, $count_qb bins\n";
list($count_q,$count_qb) = count_bins($queryarchives);
print "Archived: $count_q queries, $count_qb bins\n";

function count_bins($query_bins) { 
	$count_q = 0;
	foreach($query_bins as $bin => $queries) {
		$count_q+=count(explode(",",$queries));
	}
	return array($count_q,count($query_bins));
}


?>
