<?php

// ----- includes -----
include __DIR__ . '/../../config.php';                  // load base config file
include __DIR__ . '/../../common/constants.php';               // load constants file
include __DIR__ . '/../../common/functions.php';        // load base functions file
include __DIR__ . '/../common/functions.php';           // load capture function file

require __DIR__ . '/../common/tmhOAuth/tmhOAuth.php';

// ----- only run from command line -----
if (!env_is_cli())
    die;
$dbh = pdo_connect();

// select users without a name
$sql = "SELECT u.id AS user_id, bu.querybin_id AS bin_id, b.querybin FROM tcat_query_users u, tcat_query_bins_users bu, tcat_query_bins b WHERE u.user_name is NULL AND u.id = bu.user_id AND b.id = bu.querybin_id";
$rec = $dbh->prepare($sql);
$rec->execute();
$user_results = $rec->fetchAll();
$users = array();
$ids = array();
foreach ($user_results as $result) {
    if (!isset($users[$result['user_id']])) {
	$ids[] = $result['user_id'];
    }
}

// use REST API te get user names mapping
$idsmap = map_ids_to_screen_names($ids);

// set the names in the user table
foreach ($idsmap as $id => $username) {
    $sql = "UPDATE tcat_query_users SET user_name = :username WHERE id = :id";
    $rec3 = $dbh->prepare($sql);
    $rec3->bindParam(":username", $username, PDO::PARAM_STR);
    $rec3->bindParam(":id", $id, PDO::PARAM_INT);
    $rec3->execute();
}


