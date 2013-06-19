<?php

// ----- only run from command line -----
if($argc < 1) die;

include_once("../../config.php");

$idletime = 300;

$pid = 0;
$running = false;
if(file_exists(BASE_FILE."capture/stream/logs/procinfo")) {
	$procfile = file_get_contents(BASE_FILE."capture/stream/logs/procinfo");

	$tmp = explode("|", $procfile);
	$pid = $tmp[0];
	$last = $tmp[1];

	// check if script with pid is still runnning
	exec("ps -p ".$pid." | wc -l",$out);
	$out = trim($out[0]);
	if($out == 2) 
		$running = true;

	// check whether the process has been idle for too long
	logit("controller.log","script called - pid:" . $pid . "  idle:" . (time() - $last));
	if($running && ( $last < time() - $idletime)) {

		exec("kill " . $pid);

		logit("controller.log","script was idle for more than " . $idletime . " seconds - killing and starting");

		sleep(2);

		passthru("php ".BASE_FILE."capture/stream/capture.php > /dev/null 2>&1 &");
	}
}
if(!$running) {

	logit("controller.log","script was not running - starting");

	passthru("php ".BASE_FILE."capture/stream/capture.php > /dev/null 2>&1 &");
}


function logit($file,$message) {

	$file = BASE_FILE."capture/stream/logs/" . $file;
	$message = date("Y-m-d H:i:s") . " " . $message . "\n";
	file_put_contents($file, $message, FILE_APPEND);
}

?>
