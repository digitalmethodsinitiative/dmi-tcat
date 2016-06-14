#!/usr/bin/php5
<?php
// DMI-TCAT export
// 
// Exports query bins (with or with captured data) from DMI-TCAT.
// 
// Usage: export.php [options] {queryBins...}
// 
// ## Examples:
// 
// Export queries and data for all query bins in TCAT. By default the export
// is saved in the "analysis/cache" directory under the DMI-TCAT install
// directory (usually: /var/www/dmi-tcat).
// 
//     export.php -a
// 
// Export structure (i.e. queries only, no data) for all query bins in TCAT:
// 
//     export.php -s
// 
// Export queries and data for the query bin "foobar":
// 
//     export.php foobar
// 
// Export queries and data for the three query bins "foo", "bar" and "baz":
// 
//     export.php foo bar baz
// 
// Export queries and data for all query bins, saving it to the named file:
// 
//     export.php -o myexportfile.sql.gz
// 
// Show help message:
// 
//     export.php -h
// 

function env_is_cli() {
    return (!isset($_SERVER['SERVER_SOFTWARE']) && (php_sapi_name() == 'cli' || (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0)));
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../capture/query_manager.php';
require_once __DIR__ . '/../analysis/common/config.php';      /* to get global variable $resultsdir */
require_once __DIR__ . '/../common/functions.php';
require_once __DIR__ . '/../capture/common/functions.php';

global $dbuser, $dbpass, $database, $hostname;

if (!env_is_cli()) {
    if (defined("ADMIN_USER") && ADMIN_USER != "" && (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] != ADMIN_USER)) {
        die("Go away, you evil hacker!\n");
    } else {
        die("Please run this script only from the command-line.\n");
    }
}

// Process command line
// Sets these variables:
//   $outfile - set if -o specified
//   $queryBins - array of query bin names (empty array means export all)
//   $export - either 'all' or 'query'

$export = 'all'; // default

$prog = basename($argv[0]);

// Directory where the export file will be saved (if -o is not used)
$defaultOutputDir = realpathslash(__DIR__ . "/../analysis/$resultsdir");

$args = array(); $isAllBins = false;
for ($i = 1; $i < $argc; $i++) {
    if (substr($argv[$i], 0, 1) == '-') {
        $opt = substr($argv[$i], 1);
        switch ($opt) {
            case 'o':
                $i++;
                if ($argc <= $i) {
                    die(" $prog: usage error: missing option argument for -$opt\n");
                }
                $outfile = $argv[$i];
                break;
            case 'a':
                $isAllBins = true;
                break;
            case 'd':
                $export = 'all';
                break;
            case 's':
                $export = 'query';
                break;
            case 'h':
                print_help($prog, $defaultOutputDir);
                exit(0);
                break;
            default:
                die("$prog: usage error: unknown option: -$opt (-h for help)\n");
                break;
        }
    } else {
        array_push($args, $argv[$i]);
    }
}

$queryBins = $isAllBins ? array() : $args;

if (!$isAllBins && count($queryBins) == 0) {
    print_help($prog, $defaultOutputDir);
    exit(0);
}

// All query bins

if ($isAllBins) {
    // Export all of the query bins

    $queryBins = getAllbins();
    if (count($queryBins) == 0) {
        die("$prog: no query bins exist in this deployment of TCAT)\n");
    }
    sort($queryBins);
} else {
    // Check query bin names are valid

    foreach ($queryBins as $bin) {
        $bintype = getBinType($queryBins[0]);
        if ($bintype === false) {
            die("$prog: error: unknown query bin: $queryBins[0]\n");
        }
    }
}



// Execute

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

// Output file

$timestamp = date("Y-m-d_h:i");

if (! isset($outfile)) {
    // Generate a filename in $defaultOutputDir

    if (count($queryBins) == 1) {
        $bintype = getBinType($queryBins[0]);
        if ($bintype === false) {
            die("$prog: error: unknown query bin: $queryBins[0]\n");
        }
        $binAndType = escapeshellcmd($queryBins[0]) . '-' . $bintype;
    } else if ($isAllBins) {
    	$binAndType = 'TCAT_allQueryBins';
    } else {
    	$binAndType = 'TCAT_queryBins';
    }

    $storedir = $defaultOutputDir;
    $filepart = $binAndType . '-' . $export . '-' . $timestamp . '.sql';
    $filename = $storedir . str_replace(' ', '_', $filepart);
} else {
    // Use the filename specified from the command line

    // Extract the directory name into $storedir
    $p = (substr($outfile, 0, 1) === '/') ? $outfile :
          getcwd() . '/' . $outfile;
    $storedir = dirname($p);

    // Remove .gz suffix (if any) since it will be appended when file is gzipped
    $filename = preg_replace("/\.gz$/", "", $p);
}

if (!is_writable($storedir)) {
   die("$prog: error: directory not writable: $storedir\n");
}
if (file_exists($filename)) {
    die("$prog: error: file already exists: $filename\n");
}
if (file_exists($filename . '.gz')) {
    die("$prog: error: file already exists: ${filename}.gz\n");
}

// Start file

$fh = fopen($filename, "a");
fputs($fh, "-- Export DMI-TCAT: begin ($timestamp) $export\n");
fputs($fh, ($isAllBins) ? "-- All Query Bins:\n" : "-- Query Bins:\n");
$n = 0;
foreach ($queryBins as $bin) {
  $n++;
  fputs($fh, "-- $n. $bin\n");
}
fputs($fh, "\n");
fclose($fh);

// Export all named bins

foreach ($queryBins as $bin) {
    print "Exporting query bin: $bin\n";

$bintype = getBinType($bin);
if ($bintype === false) {
    unlink($filename);
    die("$prog: error: unknown query bin: $bin\n");
}

$fh = fopen($filename, "a");
fputs($fh, "-- Export DMI-TCAT query bin: begin: ${bin} ($bintype)\n");
fclose($fh);

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
    unlink($filename);
    die("$prog: internal error: no tables for bin: $bin (check case is correct)\n");
}

if ($export == "all") {
    $cmd = "$bin_mysqldump --skip-add-drop-table --default-character-set=utf8mb4 -u$dbuser -h $hostname $database $string >> $filename";
} else {
    $cmd = "$bin_mysqldump --skip-add-drop-table --no-data --default-character-set=utf8mb4 -u$dbuser -h $hostname $database $string >> $filename";
}
system($cmd);

/* Now append the dump with TCAT table information */

$fh = fopen($filename, "a");

fputs($fh, "\n");

fputs($fh, "--\n");
fputs($fh, "-- DMI-TCAT - Update TCAT tables\n");
fputs($fh, "--\n");

$sql = "INSERT INTO tcat_query_bins ( querybin, `type`, active, access ) values ( " . $dbh->Quote($bin) . ", " . $dbh->Quote($bintype) . ", 0, 0 );";
fputs($fh, $sql . "\n");

if ($bintype == 'track') {

    // Notice: We do not export information from the tcat_captured_phrases table here. Because we allow adding of phrasing and querybins to an existing tcat installation,
    //         the phrase IDs will change and it would not be safe to simply copy the data (phrase id <-> phrase text would no longer match)

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

fputs($fh, "-- Export DMI-TCAT query bin: end: ${bin}\n");
fputs($fh, "\n");
fclose($fh);

} // foreach ($bin in $queryBins)

// Finish file

$fh = fopen($filename, "a");
fputs($fh, "-- Export DMI-TCAT: end\n");
fclose($fh);

/* Finally gzip the file */

system("$bin_gzip $filename");

print "Dump completed and saved on disk: $filename.gz\n";

if ($isAllBins) {
    print "Number of query bins exported: " . count($queryBins) . "\n";
}

if (! isset($outfile)) {
    $url_destination = BASE_URL . 'analysis/' . $resultsdir . $filepart . '.gz';
    print "URL to download dump: $url_destination\n";
}

function get_executable($binary) {
    $where = `which $binary`;
    $where = trim($where);
    if (!is_string($where) || !file_exists($where)) {
        return null;
    }
    return $where;
}

function realpathslash($path) {
    return rtrim(realpath($path), '/') . '/';
}

function print_help($prog, $defaultOutputDir) {
    echo "Usage: $prog [options] {queryBins...}\n";
    echo "Options:\n";
    echo "  -a       export all existing bins\n";
    echo "  -d       export query phrases AND data (default)\n";
    echo "  -s       export structure: query pharases only, no data\n";
    echo "  -o file  output file (default: automatically generated)\n";
    echo "  -h       show this help message\n";
    echo "If no queryBins are named and the -a option is not used, this help message is displayed.\n";
    echo "Default output file is a .sql.gz file in $defaultOutputDir\n";
    echo "Caution: query bin names are case sensitive.\n";
}

?>
