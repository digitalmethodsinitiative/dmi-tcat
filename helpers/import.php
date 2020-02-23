#!/usr/bin/php5
<?php
// DMI-TCAT import
//
// Imports query bins exported by the DMI-TCAT export.php script.
//
// Usage: import.php filename.sql.gz
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

if ($binsExist) {
    print "Error: query bin(s) already exist. You need to run merge.php to import tweets into existing bins.\n";
    die("You may want to rename the existing query bin through the TCAT administration panel.\n");
}

print "Now importing...\n";

/* Convince system commands to use UTF-8 encoding */

setlocale(LC_ALL, 'en_US.UTF-8');
putenv('LC_ALL=en_US.UTF-8');
putenv('LANG=en_US.UTF-8');
putenv('LANGUAGE=en_US.UTF-8');
putenv('MYSQL_PWD=' . $dbpass);     /* this avoids having to put the password on the command-line */

/* Convert MyISAM storage definitions to TokuDB ones, and convert to utf8mb4 on-the-fly as well */ 
$engine_options = MYSQL_ENGINE_OPTIONS;
$hot_conversion = "sed -e 's/^  FULLTEXT KEY `from_user_description` (`from_user_description`),/  KEY `from_user_description` (`from_user_description`(32)),/g' | sed -e 's/^  FULLTEXT KEY `text` (`text`)/  KEY `text` (`text`(32))/g' | sed -e 's/^  FULLTEXT KEY `url_followed` (`url_followed`)/   KEY `url_followed` (`url_followed`(32))/g' | sed -e 's/^) ENGINE=MyISAM /) $engine_options /g' | sed -e 's/DEFAULT CHARSET=utf8;$/DEFAULT CHARSET=utf8mb4;/g'";

$cmd = "$bin_zcat $file | $hot_conversion | $bin_mysql --default-character-set=utf8mb4 -u$dbuser -h $hostname $database";
system($cmd, $return_code);

if ($return_code == 0) {
    print "Import completed and queries added to TCAT.\n";
} else {
    print "There was a problem with importing data into TCAT.\n";
}

function get_executable($binary) {
    $where = `which $binary`;
    $where = trim($where);
    if (!is_string($where) || !file_exists($where)) {
        return null;
    }
    return $where;
}

?>
