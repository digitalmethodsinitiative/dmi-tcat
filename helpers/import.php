#!/usr/bin/php5
<?php

function env_is_cli() {
    return (!isset($_SERVER['SERVER_SOFTWARE']) && (php_sapi_name() == 'cli' || (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0)));
}

require_once("../config.php");

require_once(BASE_FILE . '/capture/query_manager.php');
require_once(BASE_FILE . '/analysis/common/config.php');      /* to get global variable $resultsdir */
require_once(BASE_FILE . '/common/functions.php');
require_once(BASE_FILE . '/capture/common/functions.php');

global $dbuser, $dbpass, $database, $hostname;

if (!env_is_cli()) {
    if (defined("ADMIN_USER") && ADMIN_USER != "" && (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] != ADMIN_USER)) {
        die("Go away, you evil hacker!\n");
    } else {
        die("Please run this script only from the command-line.\n");
    }
}

if ($argc !== 2) {
    print "Please provide exactly one argument to this script: the file location of your export dump, ending with .gz\n";
    exit();
}

$bin_mysql = get_executable("mysql");
if ($bin_mysql=== null) {
    die("The mysql binary appears to be missing. Did you install the MySQL client utilities?\n");
}
if (!function_exists('gzopen')) {
    die("Apparantly your PHP installation does not have the zlib extension installed. You will need to install/enable it to continue.\n");
}
$bin_zcat = get_executable("zcat");
if ($bin_zcat === null) {
    die("The zcat binary appears to be missing. Please lookup this utility in your software repository.\n");
}

$file = $argv[1];

/* We extract the query bin name from the dump itself */

if (!is_readable($file)) {
    die("Could not open '$file' for reading.\n");
}

$fh = gzopen("$file", "r") or die ("Cannot open gzipped '$file' for reading. Perhaps you are trying to open an uncompressed dump?\n");
$bin = '';
while ($line = fgets($fh)) {
    if (preg_match("/^-- Table structure for table `(.*)_tweets`/", $line, $matches)) {
        $bin = $matches[1]; break;
    }
    if (preg_match("/^INSERT INTO tcat_query_bins \( querybin, `type`, active, visible \) values \( '(.*?)',/", $line, $matches)) {
        $bin = $matches[1]; break;
    }
}
fclose($fh);
if ($bin == '') {
    die("I did not recognize '$file' as a TCAT export.\n");
}

print "Recognized query bin '$bin'.\n";

$bintype = getBinType($bin);
if ($bintype !== false) {
    die("The query bin '$bin' already exists. Will not override. You may want to rename the existing query bin through the TCAT administration panel.\n");
}

print "Now importing...\n";

/* Convince system commands to use UTF-8 encoding */

setlocale(LC_ALL, 'en_US.UTF-8');
putenv('LC_ALL=en_US.UTF-8');
putenv('LANG=en_US.UTF-8');
putenv('LANGUAGE=en_US.UTF-8');
putenv('MYSQL_PWD=' . $dbpass);     /* this avoids having to put the password on the command-line */

$cmd = "$bin_zcat $file | $bin_mysql --default-character-set=utf8mb4 -u$dbuser -h $hostname $database";
system($cmd);

print "Import completed and queries added to TCAT.\n";

function get_executable($binary) {
    $where = `which $binary`;
    $where = trim($where);
    if (!is_string($where) || !file_exists($where)) {
        return null;
    }
    return $where;
}