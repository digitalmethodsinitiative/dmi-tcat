<?php

// ----- only run from command line -----
if ($argc < 1)
    exit();

include_once("../../config.php");
include "../../common/functions.php";
include "../common/functions.php";


// check whether controller script is already running
$out = exec("ps aux | grep 'php controller.php' | grep -v grep | grep -v stream | wc -l");
if ($out > 1) {
    logit("controller.log", "controller.php already running, skipping this check");
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

    if (!empty($commands[$role])) {
        foreach ($commands[$role] as $command) {
            logit("controller.log", "received instruction to execute '" . $command['instruction'] . "' for script $role");
            switch ($command['instruction']) {
                case "reload":
                    controller_reload_config_role($role);
                    break;
                default:
                    break;
            }
        }
        continue;
    }

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

        logit("controller.log", "script $role called - pid:" . $pid . "  idle:" . (time() - $last));

        // check whether the script with pid is running by checking whether it is possible to send it a signal
        $running = posix_kill($pid, 0);

        // running as another user
        if (posix_get_last_error() == 1)
            $running = TRUE;

        // check whether the process has been idle for too long
        if ($last < (time() - $idletime)) {

            // record confirmed gap
            gap_record($role, $last, time());

            if ($running) {
                $restartmsg = "script $role was idle for more than " . $idletime . " seconds - killing and starting";
                logit("controller.log", $restartmsg);

                // check whether the process was started by another user
                posix_kill($pid, 0);
                if (posix_get_last_error() == 1) {
                    logit("controller.log", "unable to kill $role, it seems to be running under another user\n");
                    exit();
                }

                // kill script $role
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

                // notify user via email
                if (isset($mail_to) && trim($mail_to) != "")
                    mail($mail_to, "DMI-TCAT controller killed a process", $restartmsg);

                // restart script
                passthru(PHP_CLI . " " . BASE_FILE . "capture/stream/$role.php > /dev/null 2>&1 &");
            }
        }
    }
    if (!$running) {

        logit("controller.log", "script $role was not running - starting");

        passthru(PHP_CLI . " " . BASE_FILE . "capture/stream/$role.php > /dev/null 2>&1 &");
    }
}
?>
