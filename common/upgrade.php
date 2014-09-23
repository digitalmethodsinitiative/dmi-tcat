<?php

// ----- only run from command line -----
if (php_sapi_name() !== 'cli')
    exit();

include_once("../config.php");
include "functions.php";
include "../capture/common/functions.php";

// make sure only one upgrade script is running
$thislockfp = script_lock('upgrade');
if (!is_resource($thislockfp)) {
    logit("cli", "upgrade.php already running, skipping this check");
    exit();
}

function get_all_bins() {
    $dbh = pdo_connect();
    $sql = "select querybin from tcat_query_bins";
    $rec = $dbh->prepare($sql);
    $bins = array();
    if ($rec->execute() && $rec->rowCount() > 0) {
        while ($res = $rec->fetch()) {
            $bins[] = $res['querybin'];
        }
    }
    $dbh = false;
    return $bins;
}

function upgrades() {
    
    global $database;
    global $all_bins;
    $all_bins = get_all_bins();

    // 10/07/2014 Set global database collation to utf8mb4

    $query = "show variables like \"character_set_database\"";
    $dbh = pdo_connect();
    $rec = $dbh->prepare($query);
    $rec->execute();
    $results = $rec->fetch(PDO::FETCH_ASSOC);
    $character_set_database = isset($results['Value']) ? $results['Value'] : 'unknown';
    
    $query = "show variables like \"collation_database\"";
    $rec = $dbh->prepare($query);
    $rec->execute();
    $results = $rec->fetch(PDO::FETCH_ASSOC);
    $collation_database = isset($results['Value']) ? $results['Value'] : 'unknown';
    
    if ($character_set_database == 'utf8' && ($collation_database == 'utf8_general_ci' || $collation_database == 'utf8_unicode_ci')) {

        logit("cli", "Converting database character set from utf8 to utf8mb4");

        $query = "ALTER DATABASE $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        $rec = $dbh->prepare($query);
        $rec->execute();

        $query = "SHOW TABLES";
        $rec = $dbh->prepare($query);
        $rec->execute();
        $results = $rec->fetchAll(PDO::FETCH_COLUMN);
        foreach ($results as $k => $v) {
            logit("cli", "Converting table $v character set utf8 to utf8mb4");
            $query ="ALTER TABLE $v DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $rec = $dbh->prepare($query);
            $rec->execute();
            $query ="ALTER TABLE $v CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $rec = $dbh->prepare($query);
            $rec->execute();
            logit("cli", "Repairing and optimizing table $v");
            $query ="REPAIR TABLE $v";
            $rec = $dbh->prepare($query);
            $rec->execute();
            $query ="OPTIMIZE TABLE $v";
            $rec = $dbh->prepare($query);
            $rec->execute();
        }

    }

    // 29/08/2014 Alter tweets tables to add new fields, ex. 'possibly_sensitive'

    $query = "SHOW TABLES";
    $rec = $dbh->prepare($query);
    $rec->execute();
    $results = $rec->fetchAll(PDO::FETCH_COLUMN);
    foreach ($results as $k => $v) {
        if (!preg_match("/_tweets$/", $v)) continue; 
        $query = "SHOW COLUMNS FROM $v";
        $rec = $dbh->prepare($query);
        $rec->execute();
        $columns = $rec->fetchAll(PDO::FETCH_COLUMN);
        $update = TRUE;
        foreach ($columns as $i => $c) {
            if ($c == 'possibly_sensitive') {
                $update = FALSE;
                break;
            }
        }
        if ($update) {
            logit("cli", "Adding new columns (ex. possibly_sensitive) to table $v");
            $definitions = array(
                          "`from_user_withheld_scope` varchar(32)",
                          "`from_user_favourites_count` int(11)",
                          "`from_user_created_at` datetime",
                          "`possibly_sensitive` tinyint(1)",
                          "`truncated` tinyint(1)",
                          "`withheld_copyright` tinyint(1)",
                          "`withheld_scope` varchar(32)"
                        );
            $query = "ALTER TABLE " . quoteIdent($v); $first = TRUE;
            foreach ($definitions as $subpart) {
                if (!$first) { $query .= ", "; } else { $first = FALSE; }
                $query .= " ADD COLUMN $subpart";
            }
            $rec = $dbh->prepare($query);
            $rec->execute();
        }
    }

    // 16/09/2014 Create a new withheld table for every bin
    foreach ($all_bins as $bin) {
        $exists = false;
        foreach ($results as $k => $v) {
            if ($v == $bin . '_places') {
                $exists = true;
            }
        }
        if (!$exists) {
            $create = $bin . '_withheld';
            logit("cli", "Creating new table $create");
            $sql = "CREATE TABLE IF NOT EXISTS " . quoteIdent($create . "_withheld") . " (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `tweet_id` bigint(20) NOT NULL,
                    `user_id` bigint(20),
                    `country` char(5),
                        PRIMARY KEY (`id`),
                                KEY `user_id` (`user_id`),
                                KEY `tweet_id` (`user_id`),
                                KEY `country` (`country`)
                    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4";
            $create_withheld = $dbh->prepare($sql);
            $create_withheld->execute();
        }
    }

    // 16/09/2014 Create a new places table for every bin
    foreach ($all_bins as $bin) {
        $exists = false;
        foreach ($results as $k => $v) {
            if ($v == $bin . '_places') {
                $exists = true;
            }
        }
        if (!$exists) {
            $create = $bin . '_places';
            logit("cli", "Creating new table $create");
            $sql = "CREATE TABLE IF NOT EXISTS " . quoteIdent($create) . " (
                    `id` varchar(32) NOT NULL,
                    `tweet_id` bigint(20) NOT NULL,
                        PRIMARY KEY (`id`, `tweet_id`)
                    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4";
            $create_places = $dbh->prepare($sql);
            $create_places->execute();
        }
    }

    // 16/09/2014 add url_is_media field to _urls table (and set default null)
    $query = "SHOW TABLES";
    $rec = $dbh->prepare($query);
    $rec->execute();
    $results = $rec->fetchAll(PDO::FETCH_COLUMN);
    foreach ($results as $k => $v) {
        if (!preg_match("/_urls$/", $v)) continue; 
        $query = "SHOW COLUMNS FROM $v";
        $rec = $dbh->prepare($query);
        $rec->execute();
        $columns = $rec->fetchAll(PDO::FETCH_COLUMN);
        $update = TRUE;
        foreach ($columns as $i => $c) {
            if ($c == 'url_is_media') {
                $update = FALSE;
                break;
            }
        }
        if ($update) {
            logit("cli", "Adding new column url_is_media to table $v");
            $query = "ALTER TABLE " . quoteIdent($v) . " ADD COLUMN `url_is_media` tinyint(1) DEFAULT NULL";
            $rec = $dbh->prepare($query);
            $rec->execute();
        }
    }


    // End of upgrades
}

upgrades();
