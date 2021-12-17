#!/usr/bin/php5
<?php
// DMI-TCAT archive export
//
// Exports and deletes query bins from DMI-TCAT.
//
// Usage: archive_export.php [options] {queryBins...}
//
// ## Examples:
//
// Export queries and data for all query bins in TCAT. By default the export
// is saved in the "analysis/cache" directory under the DMI-TCAT install
// directory (usually: /var/www/dmi-tcat).
//
//     export.php -a
//
// Export queries and data for all inactive bins (active=0):
//
//     export.php -i
//
// Delete all exported bins:
//
//     export.php -d
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

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../capture/query_manager.php';
require_once __DIR__ . '/../analysis/common/config.php';      /* to get global variable $resultsdir */
require_once __DIR__ . '/../common/functions.php';
require_once __DIR__ . '/../capture/common/functions.php';

global $dbuser, $dbpass, $database, $hostname;

if (!env_is_cli()) {
    die("Please run this script only from the command-line.\n");
}

// Hackish way to ignore certain bins when archiving inactive bins
$ignoreArray = true;
$binsToIgnore = array('anti_europa_new', 'anti_europa_politici', '4chan', 'blackfriday', 'mh17_track',
    'gamergate', 'netneutrality', 'refugees_english', 'cop21', 'QAnon', 'antifa_new',
    'RuPauls_Drag_Race', 'Corona_virus', 'top_ten_users_1');

$total_time_start = microtime(true);

// Process command line
// Sets these variables:
//   $outfile - set if -o specified
//   $queryBins - array of query bin names (empty array means export all)

$prog = basename($argv[0]);

// Directory where the export file will be saved (if -o is not used)
$defaultOutputDir = realpathslash(__DIR__ . "/../analysis/$resultsdir");

$args = array();
$isAllBins = false;
$isAllInactiveBins = false;
$deleteBins = false;

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
            case 'i':
                $isAllInactiveBins = true;
                break;
            case 'd':
                $deleteBins = true;
                break;
            case 'h':
                print_help($prog, $defaultOutputDir);
                exit(0);
            default:
                die("$prog: usage error: unknown option: -$opt (-h for help)\n");
        }
    } else if ($argv[$i - 1] !== '-wt') {
        array_push($args, $argv[$i]);
    }
}

if ( $isAllBins && $isAllInactiveBins ) {
    die("Cannot use -a for All bins and -i for All Inactive bins at the same time (-h for help)\n");
}

// Either empty array or array of bins
$queryBins = ( $isAllBins || $isAllInactiveBins ) ? array() : $args;

if (!$isAllBins && !$isAllInactiveBins && count($queryBins) == 0) {
    print_help($prog, $defaultOutputDir);
    exit(0);
}

// Collect or validate query bins
if ($isAllBins) {
    // Export all of the query bins
    $queryBins = getAllbins();
    if (count($queryBins) == 0) {
        die("$prog: no query bins exist in this deployment of TCAT)\n");
    }
    sort($queryBins);
} else if ( $isAllInactiveBins ) {
    // HACKING
    $dbh = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8mb4", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($ignoreArray) {
        // Ignore certain bins!
        $tempBins = getAllInactiveBins($dbh);
        foreach ($tempBins as $bin) {
            if (!in_array($bin, $binsToIgnore)) {
                $queryBins[] = $bin;
            }
        }
    } else {
        // Export all inactive query bins
        $queryBins = getAllInactiveBins($dbh);
    }

    if (count($queryBins) == 0) {
        die("$prog: no inactive query bins exist in this deployment of TCAT)\n");
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

if (! isset($outfile)) {
    $storedir = $defaultOutputDir;
} else {
    if ( is_dir($outfile) ) {
        $storedir = $outfile;
    } else {
        die("$prog: error: must provide directory when using -o option\n");
    }
}

if (!is_writable($storedir)) {
   die("$prog: error: directory not writable: $storedir\n");
}

// Extract Error gaps and ratelimits and add to SQL archive
// TODO: Break into per bin queries; reduce data AND reduce memory usage
// Currently must hold object in order to write to each bin file

$dbh = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8mb4", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// tcat_error_gap table
$tcat_error_gaps = array();
$sql = "select * from tcat_error_gap";
$q = $dbh->prepare($sql);
$q->execute();
while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
    $obj = array();
    $obj['type'] = $row['type'];
    $obj['start'] = $row['start'];
    $obj['end'] = $row['end'];
    $tcat_error_gaps[] = $obj;
}
// tcat_error_ratelimit table
$tcat_error_ratelimits = array();
// Only export tweets > 0, i.e. tweets were limited (why do we store tweets = 0?)
$sql = "select * from tcat_error_ratelimit WHERE tweets > 0";
$q = $dbh->prepare($sql);
$q->execute();
while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
    $obj = array();
    $obj['type'] = $row['type'];
    $obj['start'] = $row['start'];
    $obj['end'] = $row['end'];
    $obj['tweets'] = $row['tweets'];
    $tcat_error_ratelimits[] = $obj;
}

// Instantiate array for bins to be deleted
$exportedBins = array();
$totalTweets = 0;

$common_prep_time = (microtime(true) - $total_time_start)/60;

// Export all named bins

foreach ($queryBins as $bin) {
    $bin_time_start = microtime(true);
    print date("Y-m-d H:i:s").": Exporting query bin: $bin\n";

    // Check that bin exists
    $bintype = getBinType($bin);
    if ($bintype === false) {
        die("$prog: error: unknown query bin: $bin\n");
    }

    // Output file
    $timestamp = date("Y-m-d_hi");
    $binAndType = escapeshellcmd($bin) . '-' . $bintype;
    $filepart = $binAndType . '-' . $timestamp . '.sql';
    $filename = realpath($storedir) . DIRECTORY_SEPARATOR . str_replace(' ', '_', $filepart);

    // Cannot imagine either of these triggering with $timestamp
    if (file_exists($filename)) {
        die("$prog: error: file already exists: $filename\n");
    }
    if (file_exists($filename . '.gz')) {
        die("$prog: error: file already exists: ${filename}.gz\n");
    }

    $fh = fopen($filename, "a");
    fputs($fh, "-- Export DMI-TCAT: begin ($timestamp)\n");
    fputs($fh, "-- Export query bin: begin: ${bin} ($bintype)\n");
    fclose($fh);

    set_time_limit(0);

    $dbh = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8mb4", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //
    // Add the mysqldumps to the file
    //

    print date("Y-m-d H:i:s").": Beginning MySQL Dumps\n";
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
    
    $cmd = "$bin_mysqldump  --lock-tables=false --skip-add-drop-table --default-character-set=utf8mb4 -u$dbuser -h $hostname $database $string >> $filename";
    $mysqldump_result = system($cmd, $mysqldump_return);
    if ( $mysqldump_return !== 0 ) {
        die("$prog: mysqldump error: unable to export $bin, returned $mysqldump_return\n");
    } else if ( $mysqldump_result === false ) {
        die("$prog: mysqldump error: unable to export $bin, with result 'false'\n");
    }
    // Add sed command from export.php
    $cmd = "sed -i \"s/SQL_MODE='NO_AUTO_VALUE_ON_ZERO'/SQL_MODE='ALLOW_INVALID_DATES'/g\" $filename";
    system($cmd, $return);
    if ( $return !== 0 ) {
        die("$prog: sed error: unable to export $bin\n");
    }

    //
    // Add selective table entries to file
    //

    // Create object for metadata and deletion later
    $binObj = array();
    $binObj['name'] = $bin;
    $binObj['type'] = $bintype;

    print date("Y-m-d H:i:s").": Exporting bin specific TCAT data\n";

    // Reopen file
    $fh = fopen($filename, "a");
    fputs($fh, "\n");
    fputs($fh, "--\n");
    fputs($fh, "-- DMI-TCAT - Update TCAT tables\n");
    fputs($fh, "--\n");

    // Insert bin entry to tcat_query_bins
    $sql = "INSERT INTO tcat_query_bins ( querybin, `type`, active, access ) values ( " . $dbh->Quote($bin) . ", " . $dbh->Quote($bintype) . ", 0, 0 );";
    fputs($fh, $sql . "\n");

    // Collect bin periods
    $periods = array();
    $sql = "select starttime, endtime from tcat_query_bins_periods prd, tcat_query_bins b where prd.querybin_id = b.id AND b.querybin = :querybin";
    $q = $dbh->prepare($sql);
    $q->bindParam(':querybin', $bin, PDO::PARAM_STR);
    $q->execute();
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $obj = array();
        $obj['starttime'] = $starttime = $row['starttime'];
        $obj['endtime'] = $endtime = fix_endtime_if_not_ended($row['endtime']);
        $periods[] = $obj;
        $sql = "INSERT INTO tcat_query_bins_periods SET " .
            " querybin_id = ( select MAX(id) from tcat_query_bins ), " .
            " starttime = '$starttime', " .
            " endtime = '$endtime';";
        fputs($fh, $sql . "\n");
    }
    $binObj['periods'] = $periods;

    // Collect and insert user or phrase specific data
    if ($bintype == 'track') {
        print date("Y-m-d H:i:s").": Exporting phrase data\n";

        // Collect phrases
        $sql = "SELECT DISTINCT(p.phrase) as phrase FROM tcat_query_phrases p, tcat_query_bins_phrases bp, tcat_query_bins b
                                          WHERE p.id = bp.phrase_id AND bp.querybin_id = b.id AND b.querybin = :querybin";
        $q = $dbh->prepare($sql);
        $q->bindParam(':querybin', $bin, PDO::PARAM_STR);
        $q->execute();
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            // Insert phrases
            $phrase = $row['phrase'];
            $sql = "INSERT INTO tcat_query_phrases ( phrase ) values ( " . $dbh->Quote($phrase) . " );";
            fputs($fh, $sql . "\n");
        }

        // Collect phrase start and end times
        $phrase_times = array();
        $sql = "SELECT p.phrase as phrase, bp.starttime as starttime, bp.endtime as endtime FROM tcat_query_phrases p, tcat_query_bins_phrases bp, tcat_query_bins b
                                          WHERE p.id = bp.phrase_id AND bp.querybin_id = b.id AND b.querybin = :querybin";
        $q = $dbh->prepare($sql);
        $q->bindParam(':querybin', $bin, PDO::PARAM_STR);
        $q->execute();
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $obj = array();
            $obj['phrase'] = $phrase = $row['phrase'];
            $obj['starttime'] = $starttime = $row['starttime'];
            $obj['endtime'] = $endtime = fix_endtime_if_not_ended($row['endtime']);
            $phrase_times[] = $obj;
            // Insert phrase collection times
            $sql = "INSERT INTO tcat_query_bins_phrases SET " .
                " starttime = '$starttime', " .
                " endtime = '$endtime', " .
                " phrase_id = ( select MIN(id) from tcat_query_phrases where phrase = " . $dbh->Quote($phrase) . " ), " .
                " querybin_id = ( select MAX(id) from tcat_query_bins );";
            fputs($fh, $sql . "\n");
        }
        $binObj['phrases'] = $phrase_times;

        // Remove any phrases that exist as duplicates
        // Phrase collections times used MIN(id) and should thus be tied to one entry
        // Note: this could create overlapping time periods that are unlikely to otherwise exists; unsure how TCAT may handle that
        $sql = "DELETE FROM tcat_query_phrases where id not in ( select phrase_id from tcat_query_bins_phrases );";
        fputs($fh, $sql . "\n");

        // Collect captured tweets
        $sql = "select tweet_id, created_at, phrase from tcat_captured_phrases cp, tcat_query_phrases p, tcat_query_bins_phrases bp, tcat_query_bins b WHERE cp.phrase_id = p.id AND p.id = bp.phrase_id AND bp.querybin_id = b.id AND b.querybin = :querybin";
        $q = $dbh->prepare($sql);
        $q->bindParam(':querybin', $bin, PDO::PARAM_STR);
        $q->execute();
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            // Insert capture tweet_id tied to specific phrase
            $tweet_id = $row['tweet_id'];
            $created_at = $row['created_at'];
            $phrase = $row['phrase'];
            // Using INSERT IGNORE as it is possible to import this data into a TCAT that has captured the same tweet with the same phrase
            $sql = "INSERT IGNORE INTO tcat_captured_phrases SET " .
                " tweet_id = '$tweet_id', " .
                " created_at = '$created_at', " .
                " phrase_id = ( select id from tcat_query_phrases where phrase = " . $dbh->Quote($phrase) . " );";
            fputs($fh, $sql . "\n");
        }

    } else if ($bintype == 'follow') {
        print date("Y-m-d H:i:s").": Exporting user data\n";

        // Collect users
        $sql = "SELECT DISTINCT(u.id) as id FROM tcat_query_users u, tcat_query_bins_users bu, tcat_query_bins b
                                          WHERE u.id = bu.user_id AND bu.querybin_id = b.id AND b.querybin = :querybin";
        $q = $dbh->prepare($sql);
        $q->bindParam(':querybin', $bin, PDO::PARAM_STR);
        $q->execute();
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            // Insert user
            $user = $row['id'];
            $sql = "INSERT IGNORE INTO tcat_query_users ( id ) values ( $user );";
            fputs($fh, $sql . "\n");
        }

        // Collect user start and end times
        $user_times = array();
        $sql = "SELECT u.id as user_id, bu.starttime as starttime, bu.endtime as endtime FROM tcat_query_users u, tcat_query_bins_users bu, tcat_query_bins b
                                          WHERE u.id = bu.user_id AND bu.querybin_id = b.id AND b.querybin = :querybin";
        $q = $dbh->prepare($sql);
        $q->bindParam(':querybin', $bin, PDO::PARAM_STR);
        $q->execute();
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $obj = array();
            $obj['user_id'] = $user_id = $row['user_id'];
            $obj['starttime'] = $starttime = $row['starttime'];
            $obj['endtime'] = $endtime = fix_endtime_if_not_ended($row['endtime']);
            $user_times[] = $obj;
            // Insert user start and end times
            $sql = "INSERT INTO tcat_query_bins_users SET " .
                " starttime = '$starttime', " .
                " endtime = '$endtime', " .
                " user_id = ( select MIN(id) from tcat_query_users where id = " . $dbh->Quote($user_id) . " ), " .
                " querybin_id = ( select MAX(id) from tcat_query_bins );";
            fputs($fh, $sql . "\n");
        }
        $binObj['users'] = $user_times;
    }

    fputs($fh, "-- Export DMI-TCAT query bin: end: ${bin}\n");
    fputs($fh, "\n");

    // Add Error Data
    fputs($fh, "-- Export DMI-TCAT error gap and error ratelimit tables: begin\n");

    // Create new tables (if do not currently exist)
    $sql = "CREATE TABLE IF NOT EXISTS archive_groups ( id bigint auto_increment, group_id int NULL, querybin_id int NULL, import_date datetime not null, primary key(id), index(group_id), index(querybin_id), index(import_date) ) ;";
    fputs($fh, $sql . "\n");
    $sql = "CREATE TABLE IF NOT EXISTS archive_tcat_error_gap ( id bigint auto_increment, group_id int NULL, type varchar(32), start datetime not null, end datetime not null, primary key(id), index(group_id), index(type), index(start), index(end) ) ;";
    fputs($fh, $sql . "\n");
    $sql = "CREATE TABLE IF NOT EXISTS archive_tcat_error_ratelimit ( id bigint auto_increment, group_id int NULL, type varchar(32), start datetime not null, end datetime not null, tweets bigint not null, primary key(id), index(group_id), index(type), index(start), index(end) ) ;";
    fputs($fh, $sql . "\n");

    // Populate data
    // Grab and increment latest group_id
    // TODO Verify: This should work on mysql but... other versions may have issue
    //$sql = "select @group_id:=IFNULL(MAX(group_id), 0)+1 from archive_groups;";
    $sql = "SET @group_id = ( SELECT IFNULL(MAX(group_id), 0)+1 from archive_groups );";
    fputs($fh, $sql . "\n");

    // Add bin to archive_groups table
    $sql = "INSERT INTO archive_groups SET " .
        " group_id = @group_id, " .
        " querybin_id = ( select id from tcat_query_bins where querybin = " . $dbh->Quote($bin) . " ), " .
        " import_date = SYSDATE();";
    fputs($fh, $sql . "\n");

    // Populate archive_tcat_error_gap table
    foreach ($tcat_error_gaps as $gap) {
        $type = $gap['type'];
        $start = $gap['start'];
        $end = $gap['end'];
        $sql = "INSERT INTO archive_tcat_error_gap SET " .
            " group_id = @group_id, " .
            " type = '$type', " .
            " start = '$start', " .
            " end = '$end';";
        fputs($fh, $sql . "\n");
    }
    // Populate archive_tcat_error_ratelimit table
    foreach ($tcat_error_ratelimits as $limit) {
        $type = $limit['type'];
        $start = $limit['start'];
        $end = $limit['end'];
        $tweets = $limit['tweets'];
        $sql = "INSERT INTO archive_tcat_error_ratelimit SET " .
            " group_id = @group_id, " .
            " type = '$type', " .
            " start = '$start', " .
            " end = '$end', " .
            " tweets = '$tweets';";
        fputs($fh, $sql . "\n");
    }

    // Finish error data
    fputs($fh, "-- Export DMI-TCAT error gap and error ratelimit tables: end\n");
    fputs($fh, "\n");

    // Finish file

    fputs($fh, "-- Export DMI-TCAT: end\n");
    fclose($fh);

    /* Finally gzip the file */

    system("$bin_gzip $filename");

    print "Dump completed and saved on disk: $filename.gz\n";

    if (! isset($outfile)) {
        $url_destination = BASE_URL . 'analysis/' . $resultsdir . $filepart . '.gz';
        print "URL to download dump: $url_destination\n";
    }

    // Get number of tweets
    $sql = "SELECT count(id) AS count FROM " . $bin . "_tweets";
    $res = $dbh->prepare($sql);
    if ($res->execute() && $res->rowCount()) {
        $result = $res->fetch();
        $binObj['num_of_tweets'] = $result['count'];
    } else {
        $binObj['num_of_tweets'] = 0;
    }
    $totalTweets += $binObj['num_of_tweets'];
    print "Collected " . $binObj['num_of_tweets'] . " tweets for bin ". $bin ."\n";

    // Create Metadata file
    $git_info = getGitLocal();
    $bin_total_time = $common_prep_time + ((microtime(true) - $bin_time_start)/60);
    $export_json = array(
        'creation_date' => date("Y-m-d H:i:s"),
        'minutes_to_export' => $bin_total_time,
        'filename' =>  $filename,
        'current_git_info' => $git_info,
        'exported_bin' => $binObj,
    );

    $fp = fopen(str_replace('.sql', '', $filename).'.json', 'w');
    fwrite($fp, json_encode($export_json));
    fclose($fp);

    // Add bin object for deletion
    $exportedBins[] = $binObj;
}

if ($isAllBins) {
    print "Number of query bins exported: " . count($queryBins) . "\n";
}

// LAST: Delete everything that was archived

if ($deleteBins) {
    foreach ($exportedBins as $bin) {
        $name = $bin['name'];
        $bintype = $bin['type'];
        print "Deleting query bin: $name\n";

        // Connect to database
        $dbh = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8mb4", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Collect bin ID
        $sql = "SELECT id FROM tcat_query_bins WHERE querybin=:querybinname";
        $q = $dbh->prepare($sql);
        $q->bindParam(':querybinname', $name, PDO::PARAM_STR);
        $q->execute();
        $row = $q->fetch(PDO::FETCH_ASSOC);
        $binID = $row['id'];

        // Collect existing tables
        $tables_in_db = array();
        $sql = "show tables";
        $q = $dbh->prepare($sql);
        $q->execute();
        while ($row = $q->fetch(PDO::FETCH_NUM)) {
            $tables_in_db[] = $row[0];
        }

        // Loop through and delete bin specific tables
        $tables = array('tweets', 'mentions', 'urls', 'hashtags', 'withheld', 'places', 'media');
        foreach ($tables as $table) {
            $tablename = "$name" . '_' . $table;
            if (in_array($tablename, $tables_in_db)) {
                print "Deleting table: $tablename\n";
                $sql = "DROP TABLE $tablename";
                $drop = $dbh->prepare($sql);
                $drop->execute();
            }
        }

        // Now to remove specific rows

        // Remove bin from tcat_query_bins
        $sql = "DELETE FROM tcat_query_bins where querybin=:querybinname";
        $drop = $dbh->prepare($sql);
        $drop->bindParam(':querybinname', $name, PDO::PARAM_STR);
        $drop->execute();

        // Remove rows from tcat_query_bins_periods associated with bin's ID
        $sql = "DELETE FROM tcat_query_bins_periods where querybin_id=:querybinid";
        $drop = $dbh->prepare($sql);
        $drop->bindParam(':querybinid', $binID, PDO::PARAM_INT);
        $drop->execute();

        if ($bintype == 'track') {
            // Remove rows from tcat_query_bins_phrases associated with bin's ID
            $sql = "DELETE FROM tcat_query_bins_phrases where querybin_id=:querybinid";
            $drop = $dbh->prepare($sql);
            $drop->bindParam(':querybinid', $binID, PDO::PARAM_INT);
            $drop->execute();

        } else if ($bintype == 'follow') {
            // Remove rows from tcat_query_bins_users associated with bin's ID
            $sql = "DELETE FROM tcat_query_bins_users where querybin_id=:querybinid";
            $drop = $dbh->prepare($sql);
            $drop->bindParam(':querybinid', $binID, PDO::PARAM_INT);
            $drop->execute();

        }

        // TODO: figure out how to remove specific rows from tables
        // May be used by multiple bins; if not connected with any remaining bins, remove
        // tcat_query_phrases
        // tcat_captured_phrases
        // tcat_query_users

        // Time bound; if do not overlap with remaining bin/phrase periods, remove
        // tcat_error_gap
        // tcat_error_ratelimit
    }
}

print "Total Tweets Archived: " .$totalTweets. "\n";
$time_end = microtime(true);
$execution_time = ($time_end - $total_time_start)/60;
print "Total Execution Time: " .$execution_time. " Mins\n";

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

function fix_endtime_if_not_ended($endtime) {
    // Apparently this bin has not been stopped; archiving in a hurry?
    // Set endtime to now
    if ($endtime == "0000-00-00 00:00:00") {
        return date("Y-m-d H:i:s");
    } else {
        return $endtime;
    }
}

function print_help($prog, $defaultOutputDir) {
    echo "Usage: $prog [options] {queryBins...}\n";
    echo "Options:\n";
    echo "  -a                   export all existing bins\n";
    echo "  -o file              output file (default: automatically generated)\n";
    echo "  -h                   show this help message\n";
    echo "If no queryBins are named and the -a option is not used, this help message is displayed.\n";
    echo "Default output file is a .sql.gz file in $defaultOutputDir\n";
    echo "Caution: query bin names are case sensitive.\n";
}

// Function exists in capture.common.functions, but can uncomment here to run as script on older versions of TCAT lacking this function
// function getAllInactiveBins($dbh) {
//
//     $sql = "select querybin from tcat_query_bins where active = 0";
//     $rec = $dbh->prepare($sql);
//     $querybins = array();
//     if ($rec->execute() && $rec->rowCount() > 0) {
//         while ($res = $rec->fetch()) {
//             $querybins[] = $res['querybin'];
//         }
//     }
//     return $querybins;
// }


?>
