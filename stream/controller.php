<?php

$idletime = 150; 

$procfile = file_get_contents("/var/www/twitter/capture/stream/logs/procinfo");

$tmp = explode("|", $procfile);
$pid = $tmp[0];
$last = $tmp[1];

//echo "no updates in " . (time() - $last) . " seconds<br />";

exec("pgrep php", $proclist, $return); 							// apache2 if script is started by Apache

logit("controller.log","script called - pid:" . $pid . "  idle:" . (time() - $last));

if(in_array($pid,$proclist)) {

	//echo "process " . $pid . " running";

	if($last < time() - $idletime) {
		
		exec("kill " . $pid);
		
		logit("controller.log","script was idle for more than " . $idletime . " seconds - killing and starting");
		
		sleep(16);
		
		passthru("php /var/www/twitter/capture/stream/capture.php > /dev/null 2>&1 &");
	}
	
} else {

	logit("controller.log","script was not running - starting");
	
	passthru("php /var/www/twitter/capture/stream/capture.php > /dev/null 2>&1 &");
}


function logit($file,$message) {
	
	$file = "/var/www/twitter/capture/stream/logs/" . $file;
	$message = date("Y-m-d H:i:s") . " " . $message . "\n";
	file_put_contents($file, $message, FILE_APPEND);
}

?>