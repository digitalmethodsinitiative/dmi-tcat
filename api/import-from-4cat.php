<?php
/**
 * 4CAT import script
 *
 * Can be called from a remote server. Will download the JSON data from the
 * provided URL and run the 'import json dump' script on it to create a query
 * bin. Returns an JSON object. When succesful, the JSON object will have a
 * `success` key (always `true`), `output` (the output of the import script)
 * and `url` (a URL to the created query bin). When not succesful, the JSON
 * object will have a `success` key (always `false`) and an `error` key
 * containing the error string.
 *
 * Should be called via POST with the following form values:
 * - `url` - Location of dump file to import
 * - `name` - Query bin name
 * - `query` - The original query as entered in 4CAT
 *
 * NOTE: You may need to increase your TIMEOUT parameters in order to accept
 * large json files from 4CAT (in apache.conf for example).
 *
 * To not just accept any file from anyone, you can configure an access token
 * in config.php. If the `token` POST value does not match
 * $GLOBALS['import_token'], the script exits with an error.
 */

/**
 * Exit with a JSON-wrapped error message
 *
 * @param string $error  Error message
 */
function exit_with_json_error($error) {
    error_log("import-4cat exit error: ".strval($error));
    echo json_encode(['success' => false, 'error' => $error]);
    exit(1);
}

header('Content-type: application/json');
$config_file = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'config.php';

if(!file_exists($config_file)) {
    exit_with_json_error('Need a configuration file, but none found; exiting.');
}

include_once $config_file;

// check and verify input
$url = isset($_POST['url']) ? $_POST['url'] : false;
$token = isset($_POST['token']) ? $_POST['token'] : false;
$name = isset($_POST['name']) ? $_POST['name'] : '';
$query = isset($_POST['query']) ? $_POST['query'] : '';
$have_token_if_needed = !isset($_GLOBALS['import_token']) || !$GLOBALS['import_token'] || $GLOBALS['import_token'] == $token;

if(!$url || !$have_token_if_needed) {
    exit_with_json_error('Need a valid download URL and import token, but none were provided; exiting.');
}

// come up with some kind of name that works for the bin
if(!$name) {
    $name = '4CAT import at '.date('c');
}

$name = preg_replace('/[^0-9a-zA-Z_ -]/siU', '', $name);
$name = str_replace(' ', '_', $name);
$name = str_replace('-', '_', $name);
$name = strtolower($name);

// make sure there is a temporary folder to store the file in
$temp_dir = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."analysis".DIRECTORY_SEPARATOR."cache".DIRECTORY_SEPARATOR;
// $temp_dir = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'temp';
if(!is_dir($temp_dir)) {
    mkdir($temp_dir);
}

if(!is_dir($temp_dir)) {
    exit_with_json_error('Could not create temporary folder; exiting.');
}

// figure out where to temporarily store the file
$index = 1;
$basepath = $temp_dir.DIRECTORY_SEPARATOR.'4cat-import-temp';
$temp_path = $basepath.'.json';

while(file_exists($temp_path)) {
    $temp_path = $basepath.'-'.$index.'.json';
    $index += 1;
}

error_log("URL to download: ".$url);

// Using curl to download file
set_time_limit(0);
// Open temporary file
$fp = fopen ($temp_path, 'w+');
// Replace spaces with %20 if needed
$ch = curl_init(str_replace(" ","%20",$url));
// CURL settings
// curl timeout in second; apache.conf timeout seems more often the culprit
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
// Could be used if there are https issues
//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
// Set temp file to be used by curl
curl_setopt($ch, CURLOPT_FILE, $fp);
// May not be needed here, but could help downloading other files
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
// Get curl response, close connect, and close file
curl_exec($ch);
if(curl_error($ch)) {
    // return error if necessary
    exit_with_json_error('Unable to download file, exiting: '. curl_error($ch));
}
curl_close($ch);
fclose($fp);

// Works on files smaller than 2 gigs
//if (!file_put_contents($temp_path, file_get_contents($url))) {
//    exit_with_json_error('Unable to download file, exiting');
//}

// call import-jsondump.php
$import_script = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'import'.DIRECTORY_SEPARATOR.'import-jsondump.php';
if(!file_exists($import_script)) {
    exit_with_json_error('Import script not found, exiting');
}

// here we unfortunately need to include some code from TCAT proper
// we check if the bin already exists, and if so, change the name to
// create a new one
include_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../common/constants.php';
include_once __DIR__ . '/../common/functions.php';
include_once __DIR__ . '/../capture/common/functions.php';

$index = 1;
$base_name = $name;
while(queryManagerBinExists($name, true) !== false) {
    $name = $base_name.'_'.$index;
    $index += 1;
}

ob_start();
$argc = 2; // not used, but ensures the import script doesn't die
error_log("Values: bin_name=".$name." query=".$query);
$GLOBALS['import-settings'] = [
    'type' => 'import 4cat',
    'bin_name' => $name,
    'dir' => '',
    'queries' => [$query],
    'files' => [$temp_path]
];
require $import_script;
$output = ob_get_clean();
unlink($temp_path);

// done
echo json_encode([
    'output' => $output,
    'success' => true,
    'bin_name' => $name,
    'url' => ANALYSIS_URL.'/index.php?dataset='.$name
]);
