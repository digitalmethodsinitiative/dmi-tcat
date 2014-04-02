<?php

// ----- only run from command line -----
if ($argc < 1)
    die;

// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);

define('CAPTURE', 'onepercent');

// ----- includes -----
include "../../config.php";                  // load base config file
include "../../common/functions.php";        // load base functions file
include "../common/functions.php";           // load capture function file
global $path_local;
$path_local = BASE_FILE;

require BASE_FILE . 'capture/common/tmhOAuth/tmhOAuth.php';

// ----- connection -----
dbconnect();      // connect to database @todo, rewrite mysql calls with pdo

tracker_start();
