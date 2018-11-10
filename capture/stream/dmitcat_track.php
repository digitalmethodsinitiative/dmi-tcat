<?php

// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);

define('CAPTURE', 'track');

// ----- includes -----
include __DIR__ . '/../../config.php';                  // load base config file
include __DIR__ . '/../../common/constants.php';               // load constants file
include __DIR__ . '/../../common/functions.php';        // load base functions file
include __DIR__ . '/../common/functions.php';           // load capture function file

require __DIR__ . '/../common/tmhOAuth/tmhOAuth.php';

// ----- only run from command line -----
if (!env_is_cli())
    die;

$thislockfp = script_lock(CAPTURE);
if (!is_resource($thislockfp)) {
    logit(CAPTURE . ".error.log", "script invoked but will not continue because a process is already holding the lock file.");
    die;          // avoid double execution of script
}

if (dbserver_has_utf8mb4_support() == false) {
    logit(CAPTURE . ".error.log", "DMI-TCAT requires at least MySQL version 5.5.3 - please upgrade your server");
    exit();
}

tracker_run();
