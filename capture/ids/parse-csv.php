<?php

include_once __DIR__ . '/../../common/functions.php';

if (!env_is_cli()) die();

// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);

if ($argc > 1) {
    $input_filename = $argv[1];
    $output_filename = $argv[2];
} else {
    print "You did not specify enough parameters. This script requires exactly two parameters: your input datafile and your output id file.\n";
    print "The input file name is a pointer to a CSV file, which may be tab- or comma-delimited.\n";
    print "The output file name is a pointer to a flat file which can be read by import.php, to insert the Tweets into TCAT.\n";
    print "Usage:\n";
    print "php parse-csv.php <input csv file> <output ids file>\n";
    print "Example:\n";
    print "php parse-csv.php climatechange-tweets.csv lookup-ids.txt\n";
    exit(1);
}

if (!file_exists($input_filename)) {
    print "The input data file '$input_filename' does not exist or is not readable. Exiting.\n";
    exit(1);
}

if (file_exists($output_filename)) {
    print "The output file '$output_filename' already exists. I will not overwrite it. Exiting.\n";
    exit(1);
}

// Attempt to auto-recognize file format

$input_file = fopen($input_filename, "r");
$header = trim(fgets($input_file));
$commas = substr_count($header, ","); $tabs = substr_count($header, "\t");
if ($commas == 0 && $tabs == 0) {
    print "Your data file does not look like a CSV file. Does it have a header line? Exiting.\n";
    exit(1);
}
if ($commas > $tabs) {
    $delimiter = ",";
} else {
    $delimiter = "\t";
}

// Attempt to recognize tweet fields
$suggested_column = null;
$first_id = null;

$fields = fgetcsv($input_file, 0, $delimiter);
for ($f = 0; $f < count($fields); $f++) {
    $value = $fields[$f];
    // Look for something like this: http://twitter.com/sunit/status/129951691670949888
    // We are using the . (any) character here to match the username non-greedy, as it may contain non-alphanumeric UTF-8 symbols
    if (preg_match("#https?://[w.]*?twitter.com/.*?/status/([[:digit:]]*)#", $value, $matches)) {
        $first_id = $matches[1];
        $suggested_column = $f;
        print "Found Twitter status links in data file, at column " . ($f+1) . "\n";
    }
}
if (is_null($suggested_column)) {
    print "Your input file was not recognized as a valid CSV file containing references to tweets. Sorry.\n";
    exit(1);
}

$ids = array();
$ids[] = $first_id;
$missing_urls = 0;

// Read the rest of the file and verify we have the correct column

$line = 2;

while ($fields = fgetcsv($input_file, 0, $delimiter)) {
    $line++;
    if (count($fields) <= $suggested_column) {
        print "Malformed data file at line $line. Column " . ($f+1) . " cannot be found. Exiting.\n";
        exit(1);
    }
    if (preg_match("#https?://[w.]*?twitter.com/.*?/status/([[:digit:]]*)#", $fields[$suggested_column], $matches)) {
        $ids[] = $matches[1];
    } else {
        print "Warning: missing tweet link URL at line $line.\n";
        $missing_urls++;
    }
}

if (count($ids) != $line - 1) {
    print "Warning: mismatch in the number of tweets in the input and output file, because not all data rows contained tweet links.\n";
    $mismatch = ($line - 1) - count($ids);
    print "$mismatch tweets are missing.\n";
}

file_put_contents($output_filename, implode("\n", $ids));

print "Finished.\n";
print "Now edit the first part of lookup.php with the appropriate parameters and run that script.\n";

exit();

