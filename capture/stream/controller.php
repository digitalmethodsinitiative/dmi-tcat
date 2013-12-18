<?php

// ----- only run from command line -----
if ($argc < 1)
    die;

include_once("../../config.php");
include "../../common/functions.php";        // load base functions file
include "../common/functions.php";           // load capture function file

$dbh = pdo_connect();
create_error_logs($dbh);
//$dbh = null;                                 // https://bugs.php.net/bug.php?id=62065

$roles = unserialize(CAPTUREROLES);

foreach ($roles as $role) {

     if (defined('IDLETIME')) {
          $idletime = IDLETIME;
     } else {
	     $idletime = 600;
     }

	$pid = 0;
	$running = false;
	if (file_exists(BASE_FILE . "proc/$role.procinfo")) {
	    $procfile = file_get_contents(BASE_FILE . "proc/$role.procinfo");

	    $tmp = explode("|", $procfile);
	    $pid = $tmp[0];
	    $last = $tmp[1];

	    // check if script with pid is still runnning
	    exec("ps -p " . $pid . " | wc -l", $out);
	    $out = trim($out[0]);
	    if ($out == 2)
          $running = true;

	    // check whether the process has been idle for too long
	    logit("controller.log", "script $role called - pid:" . $pid . "  idle:" . (time() - $last));
         if ($last < (time() - $idletime)) {

               // record confirmed gap
               gap_record($role, $last, time());

               if ($running) {
          
                   exec("kill -s SIGTERM " . $pid);

                   logit("controller.log", "script $role was idle for more than " . $idletime . " seconds - killing and starting");
                   mail($mail_to,"DMI-TCAT controller killed a process","script $role was idle for more than " . $idletime . " seconds - killing and starting");

                   sleep(2);

                   passthru("php " . BASE_FILE . "capture/stream/$role.php > /dev/null 2>&1 &");
                            
               }
         }
	}
	if (!$running) {

	    logit("controller.log", "script $role was not running - starting");

	    passthru("php " . BASE_FILE . "capture/stream/$role.php > /dev/null 2>&1 &");
	}

}

function logit($file, $message) {

    $file = BASE_FILE . "logs/" . $file;
    $message = date("Y-m-d H:i:s") . " " . $message . "\n";
    file_put_contents($file, $message, FILE_APPEND);
}

?>
