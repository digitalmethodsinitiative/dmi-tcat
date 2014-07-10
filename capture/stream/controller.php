<?php

// ----- only run from command line -----
if (php_sapi_name() !== 'cli')
    exit();

include_once("../../config.php");
include "../../common/functions.php";
include "../common/functions.php";
include "../common/upgrades.php";

// check whether controller script is already running
if (!noduplicates('controller.php', TRUE)) {
    logit("controller.log", "controller.php already running, skipping this check");
    exit();
}

// check whether an upgrade is in progress
if (upgrade_locked()) {
    logit("controller.log", "an upgrade seems to be in progress, skipping check on trackers");
    exit();
}

$dbh = pdo_connect();

$roles = unserialize(CAPTUREROLES);

// first gather all instructions sent by the webinterface to the controller (ie. the instruction queue)
$commands = array();
foreach ($roles as $role) {
    $commands[$role] = array();
}
$rec = $dbh->prepare("SHOW TABLES LIKE 'tcat_controller_tasklist'");
if ($rec->execute() && $rec->rowCount() > 0) {
    $sql = "select task, instruction from tcat_controller_tasklist order by id asc lock in share mode";
    foreach ($dbh->query($sql) as $row) {
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

        $procfile = file_get_contents(BASE_FILE . "proc/$role.procinfo");

        $tmp = explode("|", $procfile);
        $pid = $tmp[0];
        $last = $tmp[1];

        $running = check_running_role($role);
        
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
                        if ($i == 10) {
                            $failmsg = "unable to kill script $role with pid $pid after " . ($sleep * $i) . " seconds";
                            logit("controller.log", $failmsg);
                            exit();
                        }
                    }
                } else {

                    logit("controller.log", "using system kill on pid $pid");
                    system("kill $pid");
                }

                // notify user via email when we restart an idle script
                if ($idled && isset($mail_to) && trim($mail_to) != "")
                    mail($mail_to, "DMI-TCAT controller killed a process", $restartmsg);

                if (noduplicates("dmitcat_$role.php")) {
                    // restart script
                    passthru(PHP_CLI . " " . BASE_FILE . "capture/stream/dmitcat_$role.php > /dev/null 2>&1 &");
                }
            }
        }
    }
    if (!$running) {

        if (noduplicates("dmitcat_$role.php")) {
            logit("controller.log", "script $role was not running - starting");

            // record confirmed gap if we could measure it
            if ($last) {
                gap_record($role, $last, time());
            }

            passthru(PHP_CLI . " " . BASE_FILE . "capture/stream/dmitcat_$role.php > /dev/null 2>&1 &");
        }
    }
}

/*
 * Check for existing invocations of a particular script to avoid duplicate executions
 * If boolean parameter single is set, one execution of script is allowed (useful for a self-check)
 * Returns FALSE if something is running, otherwise TRUE
 */

function noduplicates($script, $single_allowed = FALSE) {

    $cmd = "ps ax | grep -v grep | grep -v Ss | grep '$script'";
    $found = FALSE;

    // check whether script is already running
    $out = exec($cmd, $output);

    foreach ($output as $line) {

        $line = preg_replace("/^[\t ]*/", "", $line);
        $pid = preg_replace("/[\t ].*$/", "", $line);

        if (is_numeric($pid) && $pid > 0) {
            
            if ($found || $single_allowed == FALSE) {
                return FALSE;
            }

            $found = TRUE;
        }
    }

    return TRUE;
}

?>
