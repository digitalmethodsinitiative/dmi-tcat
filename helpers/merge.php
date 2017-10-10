#!/usr/bin/php5
<?php
// DMI-TCAT import with merge option
//
// Imports query bins exported by the DMI-TCAT export.php script into existing bins, importing only those tweets which do not exist already.
//
// MAJOR NOTICE: The tables in both bins are expected to be compatible (i.e. similar table structure, TCAT versions, collation, etc.)
// MAJOR NOTICE: This script *requires* a secondary database on your MySQL server, which may be completely empty, but should be accessible by the TCAT user defined by config.php
//
// SECONDARY NOTICE: If you wish to retain tcat_captured_phrases entries which existed in your source server, which is recommended, because the tcat_captured_phrases table
// may be used in the future, this is the procedure.
//
// 1) Create the secondary database, which may be empty
// 2) Dump and import the entire tcat_captured_phrases table from the source server into this secondary database
//    [ you could also import only the relevant tweet IDs but this will require manually crafted queries ]
// 3) This script will detect the presence of the tcat_captured_phrases table and use its contents
//
// Usage: merge.php twittercapture_temporary filename.sql.gz
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

if ($argc !== 3) {
    print "Please provide exactly TWO arguments to this script: first, a temporary MySQL database (which should be accessible by the MySQL user specified in config.php)\n";
    print "and second: the file location of your export dump, ending with .gz\n";
    exit();
}

$temp_db_name = $argv[1];

if ($temp_db_name == $database) {
    print "You have specified $database as your temporary MySQL database. This equals your regular MySQL database however!\n";
    print "The merge.php script requires you to create a separate, temporary MySQL database.\n";
    exit();
}

$temp_dbh = new PDO("mysql:host=$hostname;dbname=$temp_db_name;charset=utf8mb4", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
$temp_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$temp_dbh->query("set time_zone='+00:00'");
$tables = array( 'tcat_captured_phrases', 'tcat_controller_tasklist', 'tcat_error_gap', 'tcat_error_ratelimit', 'tcat_query_bins', 'tcat_query_bins_periods', 'tcat_query_bins_phrases', 'tcat_query_bins_users', 'tcat_query_phrases', 'tcat_query_users', 'tcat_status' );
foreach ($tables as $table) {
    $sql = "CREATE TABLE IF NOT EXISTS $temp_db_name.$table LIKE $database.$table";
    $query = $temp_dbh->prepare($sql);
    $query->execute();
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

$file = $argv[2];

/* We extract the query bin name from the dump itself */

if (!is_readable($file)) {
    die("Could not open '$file' for reading.\n");
}

$fh = gzopen("$file", "r") or die ("Cannot open gzipped '$file' for reading. Perhaps you are trying to open an uncompressed dump?\n");
$queryBins = array();
while ($line = fgets($fh)) {
    if (preg_match("/^-- Table structure for table `(.*)_tweets`/", $line, $matches)) {
        array_push($queryBins, $matches[1]);
    }
    if (preg_match("/^INSERT INTO tcat_query_bins \( querybin, `type`, active, access \) values \( '(.*?)',/", $line, $matches)) {
        array_push($queryBins, $matches[1]);
    }
}
fclose($fh);

$queryBins = array_unique($queryBins);

if (count($queryBins) == 0) {
    die("I did not recognize '$file' as a TCAT export.\n");
}

$binsExist = false;
foreach ($queryBins as $bin) {
    if (getBinType($bin) === false) {
        print "Query bin: $bin\n";
    } else {
        print "Query bin already exists: $bin\n";
        $binsExist = true;
    }
}

if (!$binsExist) {
    print "Error: query bin(s) do not exist already. You need to run import.php to import new bins.\n";
    die("You may want to rename the existing query bin through the TCAT administration panel.\n");
}

print "Now merging...\n";

/* Convince system commands to use UTF-8 encoding */

setlocale(LC_ALL, 'en_US.UTF-8');
putenv('LC_ALL=en_US.UTF-8');
putenv('LANG=en_US.UTF-8');
putenv('LANGUAGE=en_US.UTF-8');
putenv('MYSQL_PWD=' . $dbpass);     /* this avoids having to put the password on the command-line */

$cmd = "$bin_zcat $file | $bin_mysql --default-character-set=utf8mb4 -u$dbuser -h $hostname $temp_db_name";
system($cmd, $return_code);

if ($return_code == 0) {
    print "Import into temporary database completed.\n";
} else {
    print "There was a problem with importing data into the temporary database.\n";
    exit();
}

$dbh = pdo_connect();

foreach ($queryBins as $bin) {
    // TODO: this expects a modern table layout
    $exts = array ( 'hashtags', 'mentions', 'urls', 'media', 'places', 'withheld', 'tweets' );
    foreach ($exts as $ext) {
        if ($ext != 'tweets') {

            $fields = array();
            $sql = 'SHOW FULL COLUMNS FROM ' . $database . '.' . $bin . '_' . $ext;
            $rec = $dbh->prepare($sql);
            $rec->execute();
            $results = $rec->fetchAll();
            foreach ($results as $result) {
                if ($result['Field'] == 'id') { continue; }
                $fields[] = $result['Field'];
            }

            $sql = 'INSERT INTO ' . $database . '.' . $bin . '_' . $ext . ' ( ' . implode(',', $fields)  . ') SELECT ' . implode(',', $fields) . ' FROM ' . $temp_db_name . '.' . $bin . '_' . $ext . ' WHERE tweet_id NOT IN ( SELECT id FROM ' . $database . '.' . $bin . '_tweets )';
        } else if ($ext == 'tweets') {

            $fields = array();
            $sql = 'SHOW FULL COLUMNS FROM ' . $database . '.' . $bin . '_' . $ext;
            $rec = $dbh->prepare($sql);
            $rec->execute();
            $results = $rec->fetchAll();
            foreach ($results as $result) {
                $fields[] = $result['Field'];
            }

            $sql = 'INSERT INTO ' . $database . '.' . $bin . '_' . $ext . ' ( ' . implode(',', $fields)  . ') SELECT ' . implode(',', $fields) . ' FROM ' . $temp_db_name . '.' . $bin . '_' . $ext . ' WHERE id NOT IN ( SELECT id FROM ' . $database . '.' . $bin . '_' . $ext . ' )';
        }
        print "$sql\n";
        $q = $dbh->prepare($sql);
        $q->execute();
    }
}

$sql = 'INSERT INTO ' . $database . '.tcat_captured_phrases ( phrase_id, created_at ) SELECT phrase_id, created_at FROM ' . $temp_db_name . '.tcat_captured_phrases WHERE tweet_id NOT IN ( SELECT tweet_id FROM ' . $database . '.tcat_captured_phrases )';
print "$sql\n";
$q = $dbh->prepare($sql);
$q->execute();

function get_executable($binary) {
    $where = `which $binary`;
    $where = trim($where);
    if (!is_string($where) || !file_exists($where)) {
        return null;
    }
    return $where;
}

?>
