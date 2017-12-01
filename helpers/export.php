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
// Export queries and data for a bin with a custom MySQL WHERE query for the tweets table
//
//     export.php -wt "text LIKE '%apple%'" foo
//
// Export queries and data for a bin from a LOCAL analysis URL
//
//     export.php -url "URL"
//
// 
// Export queries and data for all query bins, saving it to the named file:
// 
//     export.php -o myexportfile.sql.gz
// 
// Show help message:
// 
//     export.php -h
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

// Process command line
// Sets these variables:
//   $outfile - set if -o specified
//   $queryBins - array of query bin names (empty array means export all)
//   $export - either 'all' or 'query'

$export = 'all'; // default
$where = '';     // default
$url = '';       // default

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
            case 'wt':
                if ($i + 1 < $argc) {
                    $where = $argv[$i + 1];
                } else {
                    die("The -wt option should be followed by a MySQL WHERE style query on the tweets table. Ex. -wt \"MATCH('text') CONTAINS ('apple')\"\n");
                }
                break;
            case 'url':
                if ($i + 1 < $argc) {
                    $url = $argv[$i + 1];
                } else {
                    die("The -url option should be followed by an analysis page URL from the local server. Ex. -url \"URL\"\n");
                }
                $i++;
                break;
            default:
                die("$prog: usage error: unknown option: -$opt (-h for help)\n");
                break;
        }
    } else if ($argv[$i - 1] !== '-wt') {
        array_push($args, $argv[$i]);
    }
}

if ($isAllBins && ($where !== '' || $url !== '')) {
    die("Sorry, using the -wt option, or the -url option is not compatible with exporting all bins.\n");
}

$queryBins = $isAllBins ? array() : $args;

if (!$isAllBins && count($queryBins) == 0 && $url == '') {
    print_help($prog, $defaultOutputDir);
    exit(0);
}

if ($url !== '' && count($queryBins) != 0) {
    die("When using the -url option, the querybin name will be extracted from the URL and should not be entered manually.\n");
}

// If provided with an URL, we retrieve the querybin name here
if ($url !== '') {
    $parse = parse_url($url);
    if (!array_key_exists('query', $parse)) {
        die("The provided URL appears to be malformed.\n");
    }
    parse_str($parse['query'], $params);
    if (!array_key_exists('dataset', $params)) {
        die("The dataset parameter is missing in the provided URL.\n");
    }
    // Pull in code from analysis/common/functions.php to validate and parse parameters
    $_GET = $params;        // should be safe as this is a CLI script
    require_once __DIR__ . '/../analysis/common/functions.php';
    validate_all_variables();
    list($uniqid, $tweet_cache) = create_tweet_cache();
    // store subset ids in cache
    $sql = "INSERT IGNORE INTO $tweet_cache SELECT t.id AS id FROM " . $esc['mysql']['dataset'] . "_tweets t ";
    $sql .= sqlSubset();
    $subset = $dbh->prepare($sql);
    $subset->execute();
    // TODO: cleanup
    $url = '';
    $queryBins = array( $params['dataset'] );
    $where = "id IN ( SELECT id FROM $tweet_cache )";
}

// All\n query bins

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

    //
    // Now run the mysqldumps. If we are using the where option, we follow a different logic, because we are subsetting.
    //

    $tables_in_db = array();
    $sql = "show tables";
    $q = $dbh->prepare($sql);
    $q->execute();
    while ($row = $q->fetch(PDO::FETCH_NUM)) {
        $tables_in_db[] = $row[0];
    }

   if ($where !== '') {

        // First dump the tweets table using our custom query

        $tweets_tablename = "$bin" . '_tweets';
        $where_option = str_replace('"', "'", $where);
        $cmd = "$bin_mysqldump --lock-tables=false --skip-add-drop-table --default-character-set=utf8mb4 -w \"$where\" -u$dbuser -h $hostname $database $tweets_tablename | sed -e \"s/SQL_MODE='NO_AUTO_VALUE_ON_ZERO'/SQL_MODE='ALLOW_INVALID_DATES'/g\"  >> $filename";
        system($cmd);

        // Now dump the records of related tables if they reference those tweets

        $string = '';
        $tables = array('mentions', 'urls', 'hashtags', 'withheld', 'places', 'media');
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

        $cmd = "$bin_mysqldump --lock-tables=false --skip-add-drop-table --default-character-set=utf8mb4 -w \"tweet_id in ( SELECT id FROM $tweets_tablename WHERE $where )\" -u$dbuser -h $hostname $database $string | sed -e \"s/SQL_MODE='NO_AUTO_VALUE_ON_ZERO'/SQL_MODE='ALLOW_INVALID_DATES'/g\" >> $filename";
        system($cmd);
    } else {

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
            $cmd = "$bin_mysqldump  --lock-tables=false --skip-add-drop-table --default-character-set=utf8mb4 -u$dbuser -h $hostname $database $string | sed -e \"s/SQL_MODE='NO_AUTO_VALUE_ON_ZERO'/SQL_MODE='ALLOW_INVALID_DATES'/g\" >> $filename";
        } else {
            $cmd = "$bin_mysqldump --lock-tables=false --skip-add-drop-table --no-data --default-character-set=utf8mb4 -u$dbuser -h $hostname $database $string | sed -e \"s/SQL_MODE='NO_AUTO_VALUE_ON_ZERO'/SQL_MODE='ALLOW_INVALID_DATES'/g\"  >> $filename";
        }
        system($cmd);
    }

    // Now append the dump with TCAT table information
    // Please note we dump all meta-data (queries and phrases), even if we exported a subset using the -wt option

    $fh = fopen($filename, "a");

    fputs($fh, "\n");

    fputs($fh, "--\n");
    fputs($fh, "-- DMI-TCAT - Update TCAT tables\n");
    fputs($fh, "--\n");

    if ($where !== '') {
        if (isset($tweet_cache)) {
            // Export via URL
            $cleaned = $params;
            unset($cleaned['whattodo']);
            unset($cleaned['graph_resolution']);
            unset($cleaned['outputformat']);
            unset($cleaned['fulltext']);
            $comments = 'This is a read-only subset of the bin on ' . $parse['host'] . ' with query parameters ' . substr(var_export($cleaned, true), 6);
            $sql = "INSERT INTO tcat_query_bins ( querybin, `type`, active, access, comments ) values ( " . $dbh->Quote($bin) . ", 'other', 0, 0, " . $dbh->Quote($comments) . ");";
        } else {
            // Custom export with WHERE query
            $sql = "INSERT INTO tcat_query_bins ( querybin, `type`, active, access, comments ) values ( " . $dbh->Quote($bin) . ", 'other', 0, 0, 'This is a read-only custom subset of an original bin (on a different server).' );";
        }
        fputs($fh, $sql . "\n");
    } else {
        $sql = "INSERT INTO tcat_query_bins ( querybin, `type`, active, access ) values ( " . $dbh->Quote($bin) . ", " . $dbh->Quote($bintype) . ", 0, 0 );";
        fputs($fh, $sql . "\n");
    }

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

/* The destruction of the temporary memory cache table occurs here */
/* TODO: implement robust cleanup method */
if (isset($tweet_cache)) {
    $sql = "DROP TABLE $tweet_cache";
    try {
        $drop = $dbh->prepare($sql);
        $drop->execute();
    } catch (PDOException $Exception) {
        /* Fall-back to using disk table */
        pdo_error_report($Exception);
    }
}

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
    echo "  -a                   export all existing bins\n";
    echo "  -d                   export query phrases AND data (default)\n";
    echo "  -s                   export structure: query pharases only, no data\n";
    echo "  -o file              output file (default: automatically generated)\n";
    echo "  -wt WHERE            MySQL WHERE query for the tweets table. Ex. -wt \"MATCH('text') CONTAINS ('apple')\"\n";
    echo "  -url URL             Export subset based on analysis page URL. Ex. -url \"URL\"\n";
    echo "  -h                   show this help message\n";
    echo "If no queryBins are named and the -a option is not used, this help message is displayed.\n";
    echo "Default output file is a .sql.gz file in $defaultOutputDir\n";
    echo "Caution: query bin names are case sensitive.\n";
}

?>
