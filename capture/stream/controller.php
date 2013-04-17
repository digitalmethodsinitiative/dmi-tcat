<?php
if($argc<1) die; // only run from command line
include_once('config.php');

$idletime = 150; 

$procfile = file_get_contents(BASE_FILE."capture/stream/logs/procinfo");

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
		
		passthru("php ".BASE_FILE."capture/stream/capture.php > /dev/null 2>&1 &");
	}
	
} else {

	logit("controller.log","script was not running - starting");
	
	passthru("php ".BASE_FILE."capture/stream/capture.php > /dev/null 2>&1 &");
}


function logit($file,$message) {
	
	$file = BASE_FILE."capture/stream/logs/" . $file;
	$message = date("Y-m-d H:i:s") . " " . $message . "\n";
	file_put_contents($file, $message, FILE_APPEND);
}

?>
