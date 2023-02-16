<?php
// TCAT Query bin stats
// Bypasses the original API because it is too slow (particularly because it 
// uses COUNT(*) queries to count tweets)
// This one instead gets table stats from the MySQL database, which sacrifices 
// accuracy for speed and is good enough in many use cases. No DMI-TCAT includes
// are loaded either, also to not spend/waste time running startup code.

$config = file_get_contents('../config.php');

preg_match('/\\$dbuser\s*=\s*"([^"]+)";/siU', $config, $match)[1];
$db_user = $match[1];

preg_match('/\\$dbpass\s*=\s*"([^"]+)";/siU', $config, $match)[1];
$db_password = $match[1];

preg_match('/\\$database\s*=\s*"([^"]+)";/siU', $config, $match)[1];
$db_database = $match[1];

preg_match('/\\$hostname\s*=\s*"([^"]+)";/siU', $config, $match)[1];
$db_host = $match[1];

$db = new \PDO('mysql:host='.$db_host.';dbname='.$db_database, $db_user, $db_password);

$host = trim(file_get_contents('/etc/hostname'));

$result = [
    'host' => $host,
    'bins' => []
];

$bins_raw = $db->query("SELECT * FROM tcat_query_bins")->fetchAll(\PDO::FETCH_ASSOC);
$bins = array_combine(
    array_map(function($row) { return $row['querybin']; }, $bins_raw),
    array_map(function($row) { return $row; }, $bins_raw));

foreach($bins as $name => $data) {
    $tweets_table = $name.'_tweets';

    $phrases = $db->query("SELECT phrase FROM tcat_query_phrases WHERE id IN ( SELECT phrase_id FROM tcat_query_bins_phrases WHERE querybin_id = ".intval($data['id']).")");
    $bins[$name]['phrases'] = $phrases ? $phrases->fetchAll(\PDO::FETCH_COLUMN, 0) : [];

    $range = $db->query("SELECT MIN(created_at) AS first_tweet, MAX(created_at) AS last_tweet FROM ".$tweets_table);
    $bins[$name]['range'] = $range ? $range->fetch(\PDO::FETCH_ASSOC) : ['first_tweet' => null, 'last_tweet' => null];

    $num_tweets = $db->query("SELECT table_rows FROM information_schema.tables WHERE table_schema = '".$db_database."' AND table_name = '".$tweets_table."'");
    $bins[$name]['tweets_approximate'] = $num_tweets ? intval($num_tweets->fetch(\PDO::FETCH_COLUMN, 0)) : 0;

    $tweets_size = $db->query("SELECT (DATA_LENGTH + INDEX_LENGTH) AS size FROM information_schema.tables WHERE table_schema = '".$db_database."' AND table_name = '".$tweets_table."'");
    $bins[$name]['tweets_diskspace'] = $tweets_size ? intval($tweets_size->fetch(\PDO::FETCH_COLUMN, 0)) : 0;

    foreach($bins[$name]['phrases'] as $index => $phrase) {
        $encoding = mb_detect_encoding($phrase);

        // I hate PHP error handling
        set_error_handler(function() {});
        $bins[$name]['phrases'][$index] = iconv($encoding, 'utf-8//IGNORE', $phrase);
        restore_error_handler();
    }

    // Adding phrase and user times using method from archive_export
    $dbh = null;
    $dbh = refresh_dbh_connection($dbh, $db_host, $db_database, $db_user, $db_password);
    // Collect phrase start and end times
    $phrase_times = array();
    $sql = "SELECT p.phrase as phrase, bp.starttime as starttime, bp.endtime as endtime FROM tcat_query_phrases p, tcat_query_bins_phrases bp, tcat_query_bins b
                                          WHERE p.id = bp.phrase_id AND bp.querybin_id = b.id AND b.querybin = :querybin";
    $q = $dbh->prepare($sql);
    $q->bindParam(':querybin', $name, PDO::PARAM_STR);
    $q->execute();
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $obj = array();
        $obj['phrase'] = sniff_and_correct_encoding($row['phrase']);
        $obj['starttime'] = $starttime = $row['starttime'];
        $obj['endtime'] = $endtime = $row['endtime'];
        $phrase_times[] = $obj;
    }
    // Collect user start and end times
    $user_times = array();
    $sql = "SELECT u.id as user_id, u.user_name as user_name, bu.starttime as starttime, bu.endtime as endtime FROM tcat_query_users u, tcat_query_bins_users bu, tcat_query_bins b
                                          WHERE u.id = bu.user_id AND bu.querybin_id = b.id AND b.querybin = :querybin";
    $q = $dbh->prepare($sql);
    $q->bindParam(':querybin', $name, PDO::PARAM_STR);
    $q->execute();
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $obj = array();
        $obj['user_id'] = $user_id = $row['user_id'];
        $obj['user_name'] = $row['user_name'];
        $obj['starttime'] = $starttime = $row['starttime'];
        $obj['endtime'] = $endtime = $row['endtime'];
        $user_times[] = $obj;

    }
    // Now add these phrase and user times to the bins object
    $bins[$name]['phrase_times'] = $phrase_times;
    $bins[$name]['user_times'] = $user_times;
}

header('Content-type: application/json');
echo json_encode($bins);

/**
 * Closes an existing database connection and establishes a new one.
 * Attempting to resolve "2006 MySQL server has gone away" issues due to long running script.
 */
function refresh_dbh_connection($dbh, $hostname, $database, $dbuser, $dbpass) {
    $dbh = null;
    $dbh = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8mb4", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set sql_mode='ALLOW_INVALID_DATES'"));
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // This bad-boy should reduce memory usage significantly
    $dbh->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    return $dbh;
}

/**
 * Copy Stijn's encoding thing
 */
function sniff_and_correct_encoding($words){
    $encoding = mb_detect_encoding($words);

    // It appears we're just going to ignore any errors at all?
    set_error_handler(function() {});
    $new_words = iconv($encoding, 'utf-8//IGNORE', $words);
    restore_error_handler();

    return $new_words;
}