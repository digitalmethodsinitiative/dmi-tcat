<?php

include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../common/constants.php';
include __DIR__ . '/../../common/functions.php';
include __DIR__ . '/../common/functions.php';

// ----- only run from command line -----
if (!env_is_cli())
    die;

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

// We need the tcat_status table
   
create_error_logs();

// We need the tcat_captured_phrases table

create_admin();

// first gather all instructions sent by the webinterface to the controller (ie. the instruction queue)
$upgrade_requested = false;
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
        // first handle special tcat-wide instructions
        if ($row['task'] == 'tcat') {
            if ($row['instruction'] == 'upgrade') {
                $upgrade_requested = true;
                logit("controller.log", "running auto-update at user request");
            }
        } else {
            // then handle instructions per captrue role
            if ($geoActive && $row['task'] = 'geotrack') $row['task'] = 'track';
            if (!array_key_exists($row['task'], $commands)) {
                continue;
            }
            $commands[$row['task']][] = $row;
        }
    }

    // do not leave any unknown tasks linger
    $sql = 'truncate tcat_controller_tasklist';
    $h = $dbh->prepare($sql);
    $res = $h->execute();
}

if (!defined('AUTOUPDATE_ENABLED')) {
    define('AUTOUPDATE_ENABLED', false);
}
if (!defined('AUTOUPDATE_LEVEL')) {
    define('AUTOUPDATE_LEVEL', 'trivial');
}
if (AUTOUPDATE_ENABLED && $upgrade_requested == false) {
    $failure = false;
    // we will wait at least one day before pulling new code
    $git = getGitLocal();
    if (is_array($git)) {
        $remote = getGitRemote($git['commit'], $git['branch']);
        if (is_array($remote)) {
            $date_unix = strtotime($remote['date']);
            if ($git['commit'] == $remote['commit'] || $date_unix > time() - 3600 * 24) {
                logit("controller.log", "not yet executing auto-update, because the last commit is less than a day old");
                $failure = true;
            }
        } else {
            logit("controller.log", "auto-update not supported, because we cannot get the remote git information");
            $failure = true;
        }
    } else {
        logit("controller.log", "auto-update not supported, because we cannot get the local git information");
        $failure = true;
    }
    if ($failure == false) {
        // additionally we want to ensure only a single auto-update attempt is made per day
        $nomodifyfile = __DIR__ . '/../../nomodify.txt';
        if (!file_exists($nomodifyfile)) {
            // the nomodify file does not seem to exist
            logit("controller.log", "auto-update not supported, because the nomodify.txt file appears to be missing");
            $failure = true;
        }
    }
    if ($failure == false) {
        $modified = filectime($nomodifyfile);
        $minute_number_modified = date('i', $modified);
        $minute_number_now = date('i', time());
        $hour_number_modified = date('G', $modified);
        $hour_number_now = date('G', time());
        if ($hour_number_now == $hour_number_modified && $minute_number_now == $minute_number_modified) {
            $upgrade_requested = true;
        } else {
            $upgrade_requested = false;
        }
    }
}
if ($upgrade_requested) {
    // git pull
    if (!is_writable(__DIR__ . '/../../capture')) {
        logit("controller.log", "auto-update requested, but the cron user does not have the neccessary permissions to do a successful git pull");
        $skipupdate = true;
    } else {
        logit("controller.log", "now attempting auto-update with: git pull");
        chdir(__DIR__ . '/../..');
        system("git pull 2>&1 >/dev/null", $status);
        if ($status !== 0) {
            logit("controller.log", "auto-update was not successful. The command 'git pull' seems to have failed. Did you make any local TCAT modifications? Please investigate manually.");
            $skipupdate = true;
        }
    }
    // run upgrade.php
    logit("controller.log", "now attempting database auto-update by running: php upgrade.php");
    chdir(__DIR__ . '/../../common');
    $flag = '--au0';
    if (AUTOUPDATE_LEVEL == 'substantial') {
        $flag = '--au1';
    } elseif (AUTOUPDATE_LEVEL == 'expensive') {
        $flag = '--au2';
    }
    system("nohup php upgrade.php --non-interactive $flag", $status);
}

chdir(__DIR__);

if (is_url_expander_enabled()) {
    // Check if urlexpand.php is running.
    $running = (script_lock('urlexpand', true) !== true);
    if (!$running) {
        logit("controller.log", "starting URL expander");
        // a forked process may inherit our lock, but we prevent this.
        flock($thislockfp, LOCK_UN); fclose($thislockfp);
        passthru(PHP_CLI . " " . __DIR__ . "/../../helpers/urlexpand.php > /dev/null 2>&1 &");
        $thislockfp = script_lock('controller');
    }
}

// now check for each capture role what needs to be done
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
    if (file_exists(__DIR__ . "/../../proc/$role.procinfo")) {

        $procfile = read_procfile(__DIR__ . "/../../proc/$role.procinfo");
        $pid = $procfile['pid'];
        $last = $procfile['last'];
	    if ($pid == -1) exit();

        $running = (script_lock($role, true) !== true);
        
        if($running)
            logit("controller.log", "script $role is running with pid [" . $pid . "] and has been idle for " . (time() - $last) . " seconds");

        // check whether the process has been idle for too long
        $idled = ($last < (time() - $idletime)) ? true : false;

        if ($reload || $idled) {

            // record confirmed gap if we could measure it
            if ($last && gap_record($role, $last, time())) {
                logit("controller.log", "recording a data gap for script $role from '" . toDateTime($last) . "' to '" . toDateTime(time()) . "'");
            } else {
                logit("controller.log", "we have no information about previous running time of script $role - cannot record a gap");
            }

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
                    passthru(PHP_CLI . " " . __DIR__ . "/dmitcat_$role.php > /dev/null 2>&1 &");
                    $thislockfp = script_lock('controller');
                }
            }
        }
    }
    if (!$running) {

        if (script_lock($role, true) === true) {
            logit("controller.log", "script $role was not running - starting");

            // record confirmed gap if we could measure it
            if ($last && gap_record($role, $last, time())) {
                logit("controller.log", "recording a data gap for script $role from '" . toDateTime($last) . "' to '" . toDateTime(time()) . "'");
            } else {
                logit("controller.log", "we have no information about previous running time of script $role - cannot record a gap");
            }

            // a forked process may inherit our lock, but we prevent this.
            flock($thislockfp, LOCK_UN); fclose($thislockfp);
            passthru(PHP_CLI . " " . __DIR__ . "/dmitcat_$role.php > /dev/null 2>&1 &");
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
