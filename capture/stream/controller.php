<?php

// ----- only run from command line -----
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cgi-fcgi')
    die;

include_once("../../config.php");
include "../../common/functions.php";
include "../common/functions.php";

// make sure only one controller script is running
$thislockfp = script_lock('controller');
if (!is_resource($thislockfp)) {
    logit("controller.log", "controller.php already running, skipping this check");
    exit();
}

if (dbserver_has_utf8mb4_support() == false) {
    logit("controller.log", "DMI-TCAT requires at least MySQL version 5.5.3 - please upgrade your server");
    exit();
}

$dbh = pdo_connect();

$roles = unserialize(CAPTUREROLES);

// first gather all instructions sent by the webinterface to the controller (ie. the instruction queue)
$commands = array();
foreach ($roles as $role) {
    $commands[$role] = array();
}
if (array_key_exists('track', $commands) && geobinsActive() && geophp_sane()) {
    $geoActive = true;
} else {
    $geoActive = false;
}
$rec = $dbh->prepare("SHOW TABLES LIKE 'tcat_controller_tasklist'");
if ($rec->execute() && $rec->rowCount() > 0) {
    $sql = "select task, instruction from tcat_controller_tasklist order by id asc lock in share mode";
    foreach ($dbh->query($sql) as $row) {
        if ($geoActive && $row['task'] = 'geotrack') $row['task'] = 'track';
        if (!array_key_exists($row['task'], $commands)) {
            continue;
        }
        $commands[$row['task']][] = $row;
    }

    // do not leave any unknown tasks linger
    $sql = 'truncate tcat_controller_tasklist';
    $h = $dbh->prepare($sql);
    $res = $h->execute();
}

// now check for each role what needs to be done
foreach ($roles as $role) {

    $reload = false;

    if (!empty($commands[$role])) {
        foreach ($commands[$role] as $command) {
            logit("controller.log", "received instruction to execute '" . $command['instruction'] . "' for script $role");
            switch ($command['instruction']) {
                case "reload":
                    $reload = true;
                    break;
                default:
                    break;
            }
        }
    }

    if (defined('IDLETIME')) {
        $idletime = IDLETIME;
    } else {
        $idletime = 600;
    }

    $pid = 0;
    $last = 0;
    $running = false;
    if (file_exists(BASE_FILE . "proc/$role.procinfo")) {

        $procfile = read_procfile(BASE_FILE . "proc/$role.procinfo");
        $pid = $procfile['pid'];
        $last = $procfile['last'];
	    if ($pid == -1) exit();

        $running = (script_lock($role, true) !== true);
        
        if($running)
            logit("controller.log", "script $role is running with pid [" . $pid . "] and has been idle for " . (time() - $last) . " seconds");

        // check whether the process has been idle for too long
        $idled = ($last < (time() - $idletime)) ? true : false;

        if ($reload || $idled) {

            // record confirmed gap
            gap_record($role, $last, time());

            if ($running) {

                if ($reload) {
                    $restartmsg = "enforcing reload of config for $role";
                } else {
                    $restartmsg = "script $role was idle for more than " . $idletime . " seconds - killing and starting";
                }
                logit("controller.log", $restartmsg);

                if (function_exists('posix_kill')) {

                    // check whether the process was started by another user
                    posix_kill($pid, 0);
                    if (posix_get_last_error() == 1) {
                        logit("controller.log", "unable to kill $role, it seems to be running under another user\n");
                        exit();
                    }

                    // kill script $role
                    logit("controller.log", "sending a TERM signal to $role for $pid");
                    posix_kill($pid, SIGTERM);

                    // test whether the process really has been killed
                    $i = 0;
                    $sleep = 5;
                    // while we can still signal the pid
                    while (posix_kill($pid, 0)) {
                        logit("controller.log", "waiting for graceful exit of script $role with pid $pid");
                        // we need some time to allow graceful exit
                        sleep($sleep);
                        $i++;
                        if ($i == 3) {
                            $failmsg = "unable to kill script $role with pid $pid after " . ($sleep * $i) . " seconds";
                            logit("controller.log", $failmsg);
                            $failmsg = "hard kill of pid $pid";
                            logit("controller.log", $failmsg);
                            posix_kill($pid, SIGKILL);
                            break;
                        }
                    }

                } else {

                    logit("controller.log", "using system kill on pid $pid");
                    system("kill $pid");
                }

                // notify user via email when we restart an idle script
                if (!$reload && $idled && isset($mail_to) && trim($mail_to) != "")
                    mail($mail_to, "DMI-TCAT controller killed a process (server: " . getHostName() . ")" , $restartmsg, 'From: no-reply@dmitcat');

                if (script_lock($role, true) === true) {
                    // restart script

                    // a forked process may inherit our lock, but we prevent this.
                    flock($thislockfp, LOCK_UN); fclose($thislockfp);
                    passthru(PHP_CLI . " " . BASE_FILE . "capture/stream/dmitcat_$role.php > /dev/null 2>&1 &");
                    $thislockfp = script_lock('controller');
                }
            }
        }
    }
    if (!$running) {

        if (script_lock($role, true) === true) {
            logit("controller.log", "script $role was not running - starting");

            // record confirmed gap if we could measure it
            if ($last) {
                gap_record($role, $last, time());
            }

            // a forked process may inherit our lock, but we prevent this.
            flock($thislockfp, LOCK_UN); fclose($thislockfp);
            passthru(PHP_CLI . " " . BASE_FILE . "capture/stream/dmitcat_$role.php > /dev/null 2>&1 &");
            $thislockfp = script_lock('controller');
        }
    }
}

function read_procfile($filename) {
    $procfile = array(); $n = 0;
    for ( ;; ) {
        $contents = file_get_contents($filename);
    	if (isset($contents) && strlen($contents) > 0) {
          	$tmp = explode("|", $contents);
         	$procfile['pid'] = $tmp[0];
		    $procfile['last'] = $tmp[1];
		    break;
	    }
	    if (++$n == 4) {
		    logit("controller.log", "cannot read pid|time from $filename");
		    $procfile['pid'] = -1;
		    $procfile['last'] = -1;
		    break;
	    }
	    usleep(330000);
    }
    return $procfile;
}

?>
