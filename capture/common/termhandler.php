<?php

/*
 * This signal handler is installed by the capture scripts.
 */

function install_capture_signal_handlers() {

     // tick use required as of PHP 4.3.0
     declare(ticks = 1);

     // setup signal handlers
     pcntl_signal(SIGTERM, "capture_signal_handler_term");

}

function capture_signal_handler_term($signo) {

     global $exceeding, $ratelimit, $ex_start;

     logit(CAPTURE . ".error.log", "received TERM signal, writing rate limit information to database");

     if (isset($exceeding) && $exceeding == 1) {
          ratelimit_record($ratelimit, $ex_start);
     }

     logit(CAPTURE . ".error.log", "exiting now on TERM signal");
             
     exit;
     
}
