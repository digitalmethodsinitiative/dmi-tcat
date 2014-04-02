<?php

// ----- only run from command line -----
if ($argc < 1)
    die;

// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);

define('CAPTURE', 'track');

// ----- includes -----
include "../../config.php";                  // load base config file
include "../../common/functions.php";        // load base functions file
include "../common/functions.php";           // load capture function file

require BASE_FILE . 'capture/common/tmhOAuth/tmhOAuth.php';

// ----- connection -----
dbconnect();      // connect to database @todo, rewrite mysql calls with pdo

tracker_run();
