#!/usr/bin/php5
<?php
//
// DMI-TCAT MyISAM to TokuDB bin conversion script
//
// Does not execute any SQL statement at this time, but outputs SQL instructions to the console,
// so the end user can execute those at her convenience *AND* perform a sanity check.
// 
// Usage: convert-myisam-to-tokudb.php {queryBins...}
// 

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../capture/query_manager.php';
require_once __DIR__ . '/../analysis/common/config.php';      /* to get global variable $resultsdir */
require_once __DIR__ . '/../common/functions.php';
require_once __DIR__ . '/../capture/common/functions.php';

global $dbuser, $dbpass, $database, $hostname;

if (!env_is_cli()) {
    die("Please run this script only from the command-line.\n");
}

$prog = basename($argv[0]);

if ($argc == 1) {
    die("Usage: convert-myisam-to-tokudb.php {queryBins...}");
}

for ($i = 1; $i < $argc; $i++) {
    $bin = $argv[$i];
    $sql = "ALTER TABLE $bin" . "_tweets DISABLE KEYS";
    print $sql . ";\n";
    $sql = "ALTER TABLE $bin" . "_tweets DROP INDEX `from_user_description`";
    print $sql . ";\n";
    $sql = "ALTER TABLE $bin" . "_tweets DROP INDEX `text`";
    print $sql . ";\n";
    $sql = "ALTER TABLE $bin" . "_urls DISABLE KEYS";
    print $sql . ";\n";
    $sql = "ALTER TABLE $bin" . "_urls DROP INDEX `url_followed`";
    print $sql . ";\n";
    $exts = array( 'tweets', 'urls', 'mentions', 'hashtags', 'media', 'places', 'withheld' );
    foreach ($exts as $ext) {
        $sql = "ALTER TABLE $bin" . "_$ext ENGINE=TokuDB COMPRESSION=TOKUDB_LZMA";
        print $sql . ";\n";
    }
    $sql = "ALTER TABLE $bin" . "_tweets ADD INDEX `from_user_description` (`from_user_description`(32))";
    print $sql . ";\n";
    $sql = "ALTER TABLE $bin" . "_tweets ADD INDEX `text` (`text`(32))";
    print $sql . ";\n";
    $sql = "ALTER TABLE $bin" . "_urls ADD INDEX `url_followed` (`url_followed`(32))";
    print $sql . ";\n";
    $sql = "ALTER TABLE $bin" . "_tweets ENABLE KEYS";
    print $sql . ";\n";
    $sql = "ALTER TABLE $bin" . "_urls ENABLE KEYS";
    print $sql . ";\n";
}
