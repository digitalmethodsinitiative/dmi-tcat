<?php

$procfile = file_get_contents(getcwd() . "/logs/procinfo");

$tmp = explode("|", $procfile);
$pid = $tmp[0];
$last = $tmp[1];

exec("pgrep php", $proclist, $return); 							// apache2 if script is started by Apache

//print_r($proclist);

//echo "hi" . $pid;

if(in_array($pid,$proclist)) {

	echo "process " . $pid . " running<br />";

	exec("kill " . $pid);
	
	echo "stopped";
	
} else {
	
	echo "process not running";
	//start
}

?>