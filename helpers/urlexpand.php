#!/usr/bin/php5
<?php
/*
 * DMI-TCAT URL expander
 *
 * Resolves URLs inside tweets to their final location, if possible.
 * Based on original Python urlexpand.py
 *
 * TODO FUTURE IMPROVEMENTS
 *
 * = run from controller.php and use standard DMI-TCAT locking, logging and config mechanism
 * = implement child side caching of identical URLs already resolved
 * = contemplate/consider to *not* resolve Twitter status URLs. We know these URLs do not resolve to an external location
 *   but lose knowledge about a deleted tweets. These are the vast majority of tweets in all bins.
 * 
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../capture/query_manager.php';
require_once __DIR__ . '/../common/functions.php';
require_once __DIR__ . '/../capture/common/functions.php';

// make sure only one URL expander script is running
$thislockfp = script_lock('urlexpand');
if (!is_resource($thislockfp)) {
    logit("urlexpand.log", "urlexpand.php is already running, not starting a second instance");
    exit();
}

global $dbuser, $dbpass, $database, $hostname;

$threads = 8;                       // Number of processes to fork (= number of URL tables to process in parallel)
$timeout = 7000;                    // Curl request timeout
$child_sleep_normal = 350000;       // Child sleeps n milliseconds after any Curl request
$child_sleep_faster = 200000;       // Child sleeps n milliseconds after any Curl request for major websites

$fast_sites = array(
              'j.mp',
              'doubleclick.net',
              'ow.ly',
              'bit.ly',
              'goo.gl',
              'dld.bz',
              'tinyurl.com',
              'fp.me',
              'wp.me',
              'is.gd',
              'twitter.com'
            );


if (!env_is_cli()) {
    die("Please run this script only from the command-line.\n");
}

if (!function_exists('pcntl_fork')) {
    die("Please install and activate the pcntl PHP module to run this script.\n");
}

$tables_scan = array();
if (isset($argv[1])) {
    if (!preg_match("/_urls$/", $argv[1])) {
        die("Argument '" . $argv[1] . "' should be a table name ending with _urls\n");
    }
    $tables_scan[] = $argv[1];
} else {
    foreach (getAllBins() as $bin) { $tables_scan[] .= $bin . '_urls'; }
    print "Assembling list of viable tables (this may take a while..)\n";
    logit("urlexpand.log", "assembling list of viable tables");
}

// Prefilter tables which are not eligible for processing (because all URLs have been resolved)
$dbh = pdo_connect();
$tables = array();
foreach ($tables_scan as $table) {
    $sql = "SELECT DISTINCT url_expanded FROM `$table` WHERE (domain IS NULL OR domain = '')
                                         AND (error_code IS NULL OR error_code = '')
                                         AND (url_expanded != '' AND url_expanded IS NOT NULL) LIMIT 1";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    if ($rec->execute()) {
        if ($rec->rowCount() > 0) {
            $tables[] = $table;
        }
    }
}
$dbh = null;

print "Now starting resolve process\n";
logit("urlexpand.log", "starting resolve process");

$i = 0;
while ($i < count($tables)) {
    $child_pids = array();
    for ($t = 0; $t < $threads; $t++) {
        if ($i == count($tables)) { break; }
        $table = $tables[$i++];
        $pid = pcntl_fork();
        if ($pid == -1) {
            die("Could not fork. You are probably running this script in a restricted hosting environment.\n");
        }
        if ($pid) {
            // We are the parent
            $child_pids[] = $pid;
        } else {
            // We are the child
            $success = 0; $bad = 0;
            $dbh = pdo_connect();
            $sql = "SELECT DISTINCT url_expanded FROM `$table` WHERE (domain IS NULL OR domain = '')
                                                 AND (error_code IS NULL OR error_code = '')
                                                 AND (url_expanded != '' AND url_expanded IS NOT NULL)
                                                 ORDER BY RAND()";
            $rec = $dbh->prepare($sql);
            $rec->execute();
            if ($rec->execute() && $rec->rowCount() > 0) {
                print "Child thread now working on $table\n";
                while ($res = $rec->fetch()) {
                    $url = $res['url_expanded'];
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64; rv:57.0) Gecko/20100101 Firefox/57.0');
                    curl_setopt($ch, CURLOPT_VERBOSE, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                    curl_setopt($ch, CURLOPT_HEADER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $result = curl_exec($ch);
                    $error_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if (($error_code == 200 || $error_code == 301 || $error_code == 302) && preg_match('/Location: (.*)/', $result, $matches)) {
                        $url_followed = trim($matches[1]);
                    } else if ($error_code == 200) {
                        $url_followed = $url;
                    } else {
                        $url_followed = null;
                    }
                    if (is_null($url_followed)) {
                        // Failed to follow URL
                        $sql2 = "UPDATE `$table` SET error_code = :error_code WHERE url_expanded = :url_expanded";
                        $rec2 = $dbh->prepare($sql2);
                        $rec2->bindParam(":error_code", $error_code, PDO::PARAM_INT);
                        $rec2->bindParam(":url_expanded", $url, PDO::PARAM_STR);
                        $rec2->execute();
                        $bad++;
                        print "BAD '$url' ($error_code)\n";
                    } else {
                        // Parse host
                        $parse = parse_url($url_followed);
                        if ($parse !== FALSE) {
                            $sql2 = "UPDATE `$table` SET url_followed = :url_followed, domain = :domain, error_code = :error_code WHERE url_expanded = :url_expanded";
                        } else {
                            $sql2 = "UPDATE `$table` SET url_followed = :url_followed, error_code = :error_code WHERE url_expanded = :url_expanded";
                        }
                        $rec2 = $dbh->prepare($sql2);
                        $rec2->bindParam(":url_followed", $url_followed, PDO::PARAM_STR);
                        if ($parse !== FALSE) {
                            $rec2->bindParam(":domain", $parse["host"], PDO::PARAM_STR);
                        }
                        $rec2->bindParam(":error_code", $error_code, PDO::PARAM_INT);
                        $rec2->bindParam(":url_expanded", $url, PDO::PARAM_STR);
                        $rec2->execute();
                        print "OK '$url' -> '$url_followed'\n";
                        $success++;
                    }
                    $parse = parse_url($url);
                    if ($parse !== FALSE && in_array($parse["host"], $fast_sites)) {
                        usleep($child_sleep_faster);
                    } else {
                        usleep($child_sleep_normal);
                    }
                }
                $str = "$table handled with " . ($bad + $success) . " updates; $bad bad links and $success successful resolves.\n";
                print "$str\n";
                logit("urlexpand.log", $str);
                exit(0);
            } else {
                // Nothing to do for this table
                print "Child thread idle for table $table\n";
                $dbh = null;
                exit(0);
            }
        }
    }
    print "Main process forked $threads threads; now waiting for completion.\n";
    foreach ($child_pids as $pid) {
        pcntl_waitpid($pid, $status);
    }
}

print "Finished.\n";
logit("urlexpand.log", "finished for now");
exit(0);
