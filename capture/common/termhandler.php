<?php

/*
 * This signal handler is installed by the capture scripts.
 */
// tick use required as of PHP 4.3.0
declare(ticks = 1);
// setup signal handlers
pcntl_signal(SIGTERM, "capture_signal_handler_term");

function capture_flush_buffer() {

    global $tweetbucket;

    if (is_array($tweetbucket) && is_callable('processtweets')) {
        logit(CAPTURE . ".error.log", "flushing the capture buffer");
        $copy = $tweetbucket;         // avoid any parallel processing by copy and then empty
        $tweetbucket = array();

        processtweets($copy);
    } else {
        logit(CAPTURE . ".error.log", "failed to flush capture buffer");
    }
}

function capture_signal_handler_term($signo) {

    global $exceeding, $ratelimit, $ex_start;

    logit(CAPTURE . ".error.log", "received TERM signal");

    capture_flush_buffer();

    logit(CAPTURE . ".error.log", "writing rate limit information to database");

    if (isset($exceeding) && $exceeding == 1) {
        ratelimit_record($ratelimit, $ex_start);
    }

    logit(CAPTURE . ".error.log", "exiting now on TERM signal");

    exit(0);
}
