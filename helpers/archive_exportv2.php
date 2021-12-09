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

$time_start = microtime(true);

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
    if ($ignoreArray) {
        // Ignore certain bins!
        $tempBins = getAllInactiveBins();
        foreach ($tempBins as $bin) {
            if (!in_array($bin, $binsToIgnore)) {
                $queryBins[] = $bin;
            }
        }
    } else {
        // Export all inactive query bins
        $queryBins = getAllInactiveBins();
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

// Output file

$timestamp = date("Y-m-d_hi");

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
    } else if ($isAllInactiveBins) {
        $binAndType = 'TCAT_inactiveQueryBins';
    } else {
    	$binAndType = 'TCAT_queryBins';
    }

    $storedir = $defaultOutputDir;
    $filepart = $binAndType . '-' . $timestamp . '.sql';
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
fputs($fh, "-- Export DMI-TCAT: begin ($timestamp)\n");
if ($isAllBins) {
    fputs($fh, "-- All Query Bins:\n");
} else if ($isAllInactiveBins) {
    fputs($fh, "-- All Inactive Query Bins:\n");
} else {
    fputs($fh, "-- Query Bins:\n");
}
$n = 0;
foreach ($queryBins as $bin) {
  $n++;
  fputs($fh, "-- $n. $bin\n");
}
fputs($fh, "\n");
fclose($fh);

// Instantiate array for bins to be deleted
$exportedBins = array();
$totalTweets = 0;

// Export all named bins

foreach ($queryBins as $bin) {
    print "Exporting query bin: $bin\n";

    $bintype = getBinType($bin);
    if ($bintype === false) {
        unlink($filename);
        die("$prog: error: unknown query bin: $bin\n");
    }

    // Create object for metadata and deletion later
    $binObj = array();
    $binObj['name'] = $bin;
    $binObj['type'] = $bintype;

    $fh = fopen($filename, "a");
    fputs($fh, "-- Export DMI-TCAT query bin: begin: ${bin} ($bintype)\n");
    fclose($fh);

    set_time_limit(0);

    $dbh = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8mb4", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Collect phrases
    $phrases = array();
    $sql = "SELECT DISTINCT(p.phrase) as phrase FROM tcat_query_phrases p, tcat_query_bins_phrases bp, tcat_query_bins b
                                          WHERE p.id = bp.phrase_id AND bp.querybin_id = b.id AND b.querybin = :querybin";
    $q = $dbh->prepare($sql);
    $q->bindParam(':querybin', $bin, PDO::PARAM_STR);
    $q->execute();
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $phrases[] = $row['phrase'];
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
        $obj['phrase'] = $row['phrase'];
        $obj['starttime'] = $row['starttime'];
        $obj['endtime'] = fix_endtime_if_not_ended($row['endtime']);
        $phrase_times[] = $obj;
    }

    // Collect users
    $users = array();
    $sql = "SELECT DISTINCT(u.id) as id FROM tcat_query_users u, tcat_query_bins_users bu, tcat_query_bins b
                                          WHERE u.id = bu.user_id AND bu.querybin_id = b.id AND b.querybin = :querybin";
    $q = $dbh->prepare($sql);
    $q->bindParam(':querybin', $bin, PDO::PARAM_STR);
    $q->execute();
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
      $users[] = $row['id'];
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
        $obj['user_id'] = $row['user_id'];
        $obj['starttime'] = $row['starttime'];
        $obj['endtime'] = fix_endtime_if_not_ended($row['endtime']);
        $user_times[] = $obj;
    }

    // Collect bin periods
    $periods = array();
    $sql = "select starttime, endtime from tcat_query_bins_periods prd, tcat_query_bins b where prd.querybin_id = b.id AND b.querybin = :querybin";
    $q = $dbh->prepare($sql);
    $q->bindParam(':querybin', $bin, PDO::PARAM_STR);
    $q->execute();
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $obj = array();
        $obj['starttime'] = $row['starttime'];
        $obj['endtime'] = fix_endtime_if_not_ended($row['endtime']);
        $periods[] = $obj;
    }

    // Collect tweet_id and phrase data from tcat_captured_phrases table
    $captured_phrases = array();
    $sql = "select tweet_id, created_at, phrase from tcat_captured_phrases cp, tcat_query_phrases p, tcat_query_bins_phrases bp, tcat_query_bins b WHERE cp.phrase_id = p.id AND p.id = bp.phrase_id AND bp.querybin_id = b.id AND b.querybin = :querybin";
    $q = $dbh->prepare($sql);
    $q->bindParam(':querybin', $bin, PDO::PARAM_STR);
    $q->execute();
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $obj = array();
        $obj['tweet_id'] = $row['tweet_id'];
        $obj['created_at'] = $row['created_at'];
        $obj['phrase'] = $row['phrase'];
        $captured_phrases[] = $obj;
    }

    //
    // Now run the mysqldumps
    //

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

    $cmd = "$bin_mysqldump  --lock-tables=false --skip-add-drop-table --default-character-set=utf8mb4 -u$dbuser -h $hostname $database $string | sed -e \"s/SQL_MODE='NO_AUTO_VALUE_ON_ZERO'/SQL_MODE='ALLOW_INVALID_DATES'/g\" >> $filename";
    system($cmd);

    // Now append the dump with TCAT table information
    // Please note we dump all meta-data (queries and phrases)

    $fh = fopen($filename, "a");

    fputs($fh, "\n");

    fputs($fh, "--\n");
    fputs($fh, "-- DMI-TCAT - Update TCAT tables\n");
    fputs($fh, "--\n");


    $sql = "INSERT INTO tcat_query_bins ( querybin, `type`, active, access ) values ( " . $dbh->Quote($bin) . ", " . $dbh->Quote($bintype) . ", 0, 0 );";
    fputs($fh, $sql . "\n");


    if ($bintype == 'track') {

        foreach ($phrases as $phrase) {
            $sql = "INSERT INTO tcat_query_phrases ( phrase ) values ( " . $dbh->Quote($phrase) . " );";
            fputs($fh, $sql . "\n");
        }

        foreach ($phrase_times as $phrase_time) {
            $phrase = $phrase_time['phrase'];
            $starttime = $phrase_time['starttime'];
            $endtime = $phrase_time['endtime'];
            $sql = "INSERT INTO tcat_query_bins_phrases SET " .
                   " starttime = '$starttime', " .
                   " endtime = '$endtime', " .
                   " phrase_id = ( select MIN(id) from tcat_query_phrases where phrase = " . $dbh->Quote($phrase) . " ), " .
                   " querybin_id = ( select MAX(id) from tcat_query_bins );";
            fputs($fh, $sql . "\n");
        }

        // we could have just now inserted duplicate phrases in the database, the next two queries resolve that problem
        // This update seems unnecessary since we already set the tcat_query_bins_phrases to MIN(id), but I may be missing something -Dale
        foreach ($phrases as $phrase) {
            $sql = "UPDATE tcat_query_bins_phrases as BP inner join tcat_query_phrases as P on BP.phrase_id = P.id set BP.phrase_id = ( select MIN(id) from tcat_query_phrases where phrase = " . $dbh->Quote($phrase) .  " ) where P.phrase = " . $dbh->Quote($phrase) . ';';
            fputs($fh, $sql . "\n");
        }

        $sql = "DELETE FROM tcat_query_phrases where id not in ( select phrase_id from tcat_query_bins_phrases );";
        fputs($fh, $sql . "\n");

        // With tcat_query_phrases cleaned up, we can now add tcat_captured_phrases assigning phrase_id to the new id
        foreach ($captured_phrases as $captured_phrase) {
            $tweet_id = $captured_phrase['tweet_id'];
            $created_at = $captured_phrase['created_at'];
            $phrase = $captured_phrase['phrase'];

            // Using INSERT IGNORE as it is possible to import this data into a TCAT that has captured the same tweet with the same phrase
            $sql = "INSERT IGNORE INTO tcat_captured_phrases SET " .
                " tweet_id = '$tweet_id', " .
                " created_at = '$created_at', " .
                " phrase_id = ( select id from tcat_query_phrases where phrase = " . $dbh->Quote($phrase) . " );";
            fputs($fh, $sql . "\n");
        }


    } else if ($bintype == 'follow') {

        foreach ($users as $user) {
            $sql = "INSERT IGNORE INTO tcat_query_users ( id ) values ( $user );";
            fputs($fh, $sql . "\n");
        }

        foreach ($user_times as $user_time) {
            $user_id = $user_time['user_id'];
            $starttime = $user_time['starttime'];
            $endtime = $user_time['endtime'];
            $sql = "INSERT INTO tcat_query_bins_users SET " .
                   " starttime = '$starttime', " .
                   " endtime = '$endtime', " .
                   " user_id = ( select MIN(id) from tcat_query_users where id = " . $dbh->Quote($user_id) . " ), " .
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

    // Add bin object to be used later
    $binObj['phrases'] = $phrase_times;
    $binObj['users'] = $user_times;
    $binObj['periods'] = $periods;
    $exportedBins[] = $binObj;
}

// Extract Error gaps and ratelimits and add to SQL archive

// Collect data
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

// Open file to write
$fh = fopen($filename, "a");
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

// Add bins to archive_groups table
foreach ($queryBins as $bin) {
    $import_date = date("Y-m-d H:i:s");
    $sql = "INSERT INTO archive_groups SET " .
        " group_id = @group_id, " .
        " querybin_id = ( select id from tcat_query_bins where querybin = " . $dbh->Quote($bin) . " ), " .
        " import_date = '$import_date';";
    fputs($fh, $sql . "\n");
}
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

if ($isAllBins) {
    print "Number of query bins exported: " . count($queryBins) . "\n";
}

if (! isset($outfile)) {
    $url_destination = BASE_URL . 'analysis/' . $resultsdir . $filepart . '.gz';
    print "URL to download dump: $url_destination\n";
}

// Create Metadata file
$git_info = getGitLocal();
$export_json = array(
  'creation_date' => date("Y-m-d H:i:s"),
  'total_tweets_exported' => $totalTweets,
  'minutes_to_export' => (microtime(true) - $time_start)/60,
  'filename' =>  $filename,
  'current_git_info' => $git_info,
  'exported_bins' => $exportedBins,
);

$fp = fopen(str_replace('.sql', '', $filename).'.json', 'w');
fwrite($fp, json_encode($export_json));
fclose($fp);

// LAST: Delete everything that was archived

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

print "Total Tweets Archived: " .$totalTweets. "\n";
$time_end = microtime(true);
$execution_time = ($time_end - $time_start)/60;
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

?>
