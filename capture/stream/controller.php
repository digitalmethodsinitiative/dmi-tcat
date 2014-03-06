<?php

// ----- only run from command line -----
if ($argc < 1)
    die;

include_once("../../config.php");
include "../../common/functions.php";        // load base functions file
include "../common/functions.php";           // load capture function file

$dbh = pdo_connect();
create_error_logs($dbh);

$roles = unserialize(CAPTUREROLES);

$commands = array();
foreach ($roles as $role) {
    $commands[$role] = array();
}

// first handle all instruction sent by the webinterface to the controller (ie. the instruction queue)
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

foreach ($roles as $role) {

    if (!empty($commands[$role])) {
        foreach ($commands[$role] as $command) {
            logit("controller.log", "received instruction to execute '" . $command['instruction'] . "' for script $role");
            switch ($command['instruction']) {
                case "reload": {
                        // reload configuration for a task
                        logit("controller.log", $command['instruction'] . " $role");
                        controller_reload_config_role($role);
                        break;
                    }
                default: {
                        break;
                    }
            }
        }
        continue;
    }
    
    if (defined('IDLETIME')) {
        $idletime = IDLETIME;
    } else {
        $idletime = 600;
    }

    if ($role === 'follow') {
        // DMI tcat verdubbelde idletime voor follow,
        // dit omdat 's nachts herhaadelijk het script wordt gerestart
        $idletime *= 10;
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

                $res = exec("kill -s SIGTERM $pid");

                logit("controller.log", "script $role was idle for more than " . $idletime . " seconds - killing and starting");
                mail($mail_to, "DMI-TCAT controller killed a process", "script $role was idle for more than " . $idletime . " seconds - killing and starting had result $res");

                sleep(6);       // we need some time to allow graceful exit

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
