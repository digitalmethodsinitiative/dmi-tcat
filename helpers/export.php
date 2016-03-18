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

if ($argc !== 3) {
    print "Please provide exactly two arguments to this script: the first one the name of your query bin, the second one either 'structure' or 'all'\n";
    print "All will export query phrases AND data, structure will export only query phrases and create an empty bin.\n";
    print "Example: php export.php flowers all\n";
    exit();
}

$bin_mysqldump = get_executable("mysqldump");
if ($bin_mysqldump === null) {
    die("The mysqldump binary appears to be missing. Did you install the MySQL client utilities?\n");
}
$bin_gzip = get_executable("gzip");
if ($bin_gzip === null) {
    die("The gzip binary appears to be missing. Please lookup this utility in your software repository.\n");
}

/* Convince system commands to use UTF-8 encoding */

setlocale(LC_ALL, 'en_US.UTF-8');
putenv('LC_ALL=en_US.UTF-8');
putenv('LANG=en_US.UTF-8');
putenv('LANGUAGE=en_US.UTF-8');
putenv('MYSQL_PWD=' . $dbpass);     /* this avoids having to put the password on the command-line */

$bin = $argv[1];
if (!isset($bin)) {
    die("Please specify a bin name.\n");
}
$bintype = getBinType($bin);

if ($bintype === false) {
    die("The query bin '$bin' could not be found!\n");
}

switch($argv[2]) {
    case "structure": {
        $export = 'queries';
        break;
    }
    case "all": {
        $export = 'all';
        break;
    }
    default: {
        die("Unrecognized export option.\n");
        break;
    }
}

$binforfile = escapeshellcmd($bin);       /* sanitize to filename */

$storedir = BASE_FILE . 'analysis/' . $resultsdir;              /* resultsdir is relative to the analysis/ folder */
$datepart = date("Y-m-d_h:i");
$filepart = str_replace(" ", "_", $binforfile . '-' . $bintype . '-' . $export . '-' . $datepart . '.sql');
$filename = $storedir . $filepart;

if (!is_writable($storedir)) {
    print "The store directory '$storedir' is not writable for the user you are currently running under!\n";
    print "You need to run this script as another user (ie. root or www-data) or change the directory permissions.\n";
    exit();
}

if (file_exists($filename)) {
    die("Archive file '$filename' already exists!\n");
}

set_time_limit(0);

$dbh = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8mb4", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$phrases = array();
$phrase_starttime = array(); $phrase_endtime = array();
$sql = "SELECT DISTINCT(p.phrase) as phrase, bp.starttime as starttime, bp.endtime as endtime FROM tcat_query_phrases p, tcat_query_bins_phrases bp, tcat_query_bins b
                                      WHERE p.id = bp.phrase_id AND bp.querybin_id = b.id AND b.querybin = :querybin";
$q = $dbh->prepare($sql);
$q->bindParam(':querybin', $bin, PDO::PARAM_STR);
$q->execute();
while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
    $phrases[] = $row['phrase'];
    $phrase_starttime[$row['phrase']] = $row['starttime'];
    $phrase_endtime[$row['phrase']] = $row['endtime'];
}

$users = array();
$users_starttime = array(); $users_endtime = array();
$sql = "SELECT DISTINCT(u.id) as id, bu.starttime as starttime, bu.endtime as endtime FROM tcat_query_users u, tcat_query_bins_users bu, tcat_query_bins b
                                      WHERE u.id = bu.user_id AND bu.querybin_id = b.id AND b.querybin = :querybin";
$q = $dbh->prepare($sql);
$q->bindParam(':querybin', $bin, PDO::PARAM_STR);
$q->execute();
while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
    $users[] = $row['id'];
    $users_starttime[$row['id']] = $row['starttime'];
    $users_endtime[$row['id']] = $row['endtime'];
}

$periods = array();
$sql = "select starttime, endtime from tcat_query_bins_periods prd, tcat_query_bins b where prd.querybin_id = b.id AND b.querybin = :querybin";
$q = $dbh->prepare($sql);
$q->bindParam(':querybin', $bin, PDO::PARAM_STR);
$q->execute();
while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
    $obj = array();
    $obj['starttime'] = $row['starttime'];
    $obj['endtime'] = $row['endtime'];
    $periods[] = $obj;
}

/* First run the mysqldump */

$tables_in_db = array();
$sql = "show tables";
$q = $dbh->prepare($sql);
$q->execute();
while ($row = $q->fetch(PDO::FETCH_NUM)) {
    $tables_in_db[] = $row[0];
}

$string = '';
$tables = array('tweets', 'mentions', 'urls', 'hashtags', 'withheld', 'places', 'media');
foreach ($tables as $table) {
    $tablename = "$bin" . '_' . $table;
    if (in_array($tablename, $tables_in_db)) {
        $string .= $tablename . ' ';
    }
}

if ($string == '') {
    die("Empty bin name would dump the complete database. Exiting!\n");
}

if ($export == "all") {
    $cmd = "$bin_mysqldump --default-character-set=utf8mb4 -u$dbuser -h $hostname $database $string > $filename";
} else {
    $cmd = "$bin_mysqldump --no-data --default-character-set=utf8mb4 -u$dbuser -h $hostname $database $string > $filename";
}
system($cmd);

/* Now append the dump with TCAT table information */

$fh = fopen($filename, "a");

fputs($fh, "\n");

fputs($fh, "--\n");
fputs($fh, "-- DMI-TCAT - Update TCAT tables\n");
fputs($fh, "--\n");

$sql = "INSERT INTO tcat_query_bins ( querybin, `type`, active, visible ) values ( " . $dbh->Quote($bin) . ", " . $dbh->Quote($bintype) . ", 0, 1 );";
fputs($fh, $sql . "\n");

if ($bintype == 'track') {

    foreach ($phrases as $phrase) {
        $sql = "INSERT INTO tcat_query_phrases ( phrase ) values ( " . $dbh->Quote($phrase) . " );";
        fputs($fh, $sql . "\n");
    }

    foreach ($phrases as $phrase) {
        $starttime = $phrase_starttime[$phrase];
        $endtime = $phrase_endtime[$phrase];
        $sql = "INSERT INTO tcat_query_bins_phrases SET " .
               " starttime = '$starttime', " .
               " endtime = '$endtime', " .
               " phrase_id = ( select MIN(id) from tcat_query_phrases where phrase = " . $dbh->Quote($phrase) . " ), " .
               " querybin_id = ( select MAX(id) from tcat_query_bins );";
        fputs($fh, $sql . "\n");
    }

    // we could have just now inserted duplicate phrases in the database, the next two queries resolve that problem

    foreach ($phrases as $phrase) {
        $sql = "UPDATE tcat_query_bins_phrases as BP inner join tcat_query_phrases as P on BP.phrase_id = P.id set BP.phrase_id = ( select MIN(id) from tcat_query_phrases where phrase = " . $dbh->Quote($phrase) .  " ) where P.phrase = " . $dbh->Quote($phrase) . ';';
        fputs($fh, $sql . "\n");
    }

    $sql = "DELETE FROM tcat_query_phrases where id not in ( select phrase_id from tcat_query_bins_phrases );";
    fputs($fh, $sql . "\n");


} else if ($bintype == 'follow') {

    foreach ($users as $user) {
        $sql = "INSERT IGNORE INTO tcat_query_users ( id ) values ( $user );";
        fputs($fh, $sql . "\n");
    }

    foreach ($users as $user) {
        $starttime = $users_starttime[$user];
        $endtime = $users_endtime[$user];
        $sql = "INSERT INTO tcat_query_bins_users SET " .
               " starttime = '$starttime', " .
               " endtime = '$endtime', " .
               " user_id = $user, " .
               " querybin_id = ( select MAX(id) from tcat_query_bins );";
        fputs($fh, $sql . "\n");
    }

}

foreach ($periods as $prd) {
    $starttime = $prd['starttime'];
    $endtime = $prd['endtime'];
    $sql = "INSERT INTO tcat_query_bins_periods SET " .
           " querybin_id = ( select MAX(id) from tcat_query_bins ), " .
           " starttime = '$starttime', " .
           " endtime = '$endtime';";
    fputs($fh, $sql . "\n");
}

fclose($fh);

/* Finally gzip the file */

system("$bin_gzip $filename");

print "Dump completed and saved on disk: $filename.gz\n";
$url_destination = BASE_URL . 'analysis/' . $resultsdir . $filepart . '.gz';
print "URL to download dump: $url_destination\n";

function get_executable($binary) {
    $where = `which $binary`;
    $where = trim($where);
    if (!is_string($where) || !file_exists($where)) {
        return null;
    }
    return $where;
}
