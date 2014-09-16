<?php

// ----- only run from command line -----
if (php_sapi_name() !== 'cli')
    die;

// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);

define('CAPTURE', 'follow');

// ----- includes -----
include "../../config.php";                  // load base config file
include "../../common/functions.php";        // load base functions file
include "../common/functions.php";           // load capture function file

require BASE_FILE . 'capture/common/tmhOAuth/tmhOAuth.php';

$thislockfp = script_lock(CAPTURE);
if (!is_resource($thislockfp)) {
    logit(CAPTURE . ".error.log", "script invoked but will not continue because a process is already holding the lock file.");
    die;          // avoid double execution of script
}

// ----- connection -----
dbconnect();      // connect to database @todo, rewrite mysql calls with pdo

tracker_run();
