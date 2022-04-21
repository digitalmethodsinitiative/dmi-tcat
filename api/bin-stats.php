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
}

header('Content-type: application/json');
echo json_encode($bins);
