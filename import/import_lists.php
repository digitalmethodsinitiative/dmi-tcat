<?php

include_once('../../config.php');



db_connect($hostname, $dbuser, $dbpass, $database);

$lists = array("list_file_name"=>"list_db_name");
foreach($lists as $file => $list) {
	$fil = file("/tmp/".$file);
	foreach($fil as $f) {
		$sql = "INSERT INTO penw_lijsten (list, from_user_id) VALUES ('$list',".trim($f).")";
		mysql_query($sql) or die(mysql_error());
	}
}

function db_connect($db_host, $db_user, $db_pass, $db_name) {
    global $connection;
    $connection = mysql_connect($db_host, $db_user, $db_pass);
    if (!mysql_select_db($db_name, $connection))
        die("could not connect");
    if (!mysql_set_charset('utf8', $connection)) {
        echo "Error: Unable to set the character set.\n";
        exit;
    }
}


?>
