<?php

// ----- only run from command line -----
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cgi-fcgi')
    die;

include_once("../config.php");
include "functions.php";
include "../capture/common/functions.php";

// make sure only one upgrade script is running
$thislockfp = script_lock('upgrade');
if (!is_resource($thislockfp)) {
    logit("cli", "upgrade.php already running, skipping this check");
    exit();
}

if (isset($argv[1])) {
    $single = $argv[1];
    logit("cli", "Restricting upgrade to bin $single");
} else {
    $single = false;
    logit("cli", "Executing global upgrade");
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
    global $single;
    $all_bins = get_all_bins();
    $dbh = pdo_connect();

    // 29/08/2014 Alter tweets tables to add new fields, ex. 'possibly_sensitive'

    $query = "SHOW TABLES";
    $rec = $dbh->prepare($query);
    $rec->execute();
    $results = $rec->fetchAll(PDO::FETCH_COLUMN);
    foreach ($results as $k => $v) {
        if (!preg_match("/_tweets$/", $v)) continue; 
        if ($single && $v !== $single . '_tweets') { continue; }
        $query = "SHOW COLUMNS FROM $v";
        $rec = $dbh->prepare($query);
        $rec->execute();
        $columns = $rec->fetchAll(PDO::FETCH_COLUMN);
        $update = TRUE;
        foreach ($columns as $i => $c) {
            if ($c == 'from_user_withheld_scope') {
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
            // and add indexes
            $query .= ", ADD KEY `from_user_created_at` (`from_user_created_at`)" .
                      ", ADD KEY `from_user_withheld_scope` (`from_user_withheld_scope`)" .
                      ", ADD KEY `possibly_sensitive` (`possibly_sensitive`)" .
                      ", ADD KEY `withheld_copyright` (`withheld_copyright`)" .
                      ", ADD KEY `withheld_scope` (`withheld_scope`)";
            $rec = $dbh->prepare($query);
            $rec->execute();
        }
    }

    // 16/09/2014 Create a new withheld table for every bin
    foreach ($all_bins as $bin) {
        if ($single && $bin !== $single) { continue; }
        $exists = false;
        foreach ($results as $k => $v) {
            if ($v == $bin . '_places') {
                $exists = true;
            }
        }
        if (!$exists) {
            $create = $bin . '_withheld';
            logit("cli", "Creating new table $create");
            $sql = "CREATE TABLE IF NOT EXISTS " . quoteIdent($create) . " (
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
        if ($single && $bin !== $single) { continue; }
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

    // 23/09/2014 add url_is_media_upload, media_type, photo_size_width and photo_size_height fields to _urls table (and set default null)
    $query = "SHOW TABLES";
    $rec = $dbh->prepare($query);
    $rec->execute();
    $results = $rec->fetchAll(PDO::FETCH_COLUMN);
    foreach ($results as $k => $v) {
        if (!preg_match("/_urls$/", $v)) continue; 
        if ($single && $v !== $single . '_urls') { continue; }
        $query = "SHOW COLUMNS FROM $v";
        $rec = $dbh->prepare($query);
        $rec->execute();
        $columns = $rec->fetchAll(PDO::FETCH_COLUMN);
        $update = TRUE;
        foreach ($columns as $i => $c) {
            if ($c == 'url_is_media_upload') {
                $update = FALSE;
                break;
            }
        }
        if ($update) {
            logit("cli", "Adding new columns url_is_media_upload, media_type, photo_size_width and photo_size_height to table $v");
            $query = "ALTER TABLE " . quoteIdent($v) .
                        " ADD COLUMN `url_is_media_upload` tinyint(1) DEFAULT NULL," .
                        " ADD COLUMN `media_type` varchar(32) DEFAULT NULL," .
                        " ADD COLUMN `photo_size_width` int(11) DEFAULT NULL," .
                        " ADD COLUMN `photo_size_height` int(11) DEFAULT NULL," .
                        " ADD KEY `url_is_media_upload` (`url_is_media_upload`)," .
                        " ADD KEY `media_type` (`media_type`)";
            $rec = $dbh->prepare($query);
            $rec->execute();
        }
    }

    // 23/09/2014 Set global database collation to utf8mb4

    $query = "show variables like \"character_set_database\"";
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

        if ($single === false) {
            logit("cli", "Converting database character set from utf8 to utf8mb4");
            $query = "ALTER DATABASE $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $rec = $dbh->prepare($query);
            $rec->execute();
        }

        $query = "SHOW TABLES";
        $rec = $dbh->prepare($query);
        $rec->execute();
        $results = $rec->fetchAll(PDO::FETCH_COLUMN);
        foreach ($results as $k => $v) {
            if (preg_match("/_places$/", $v) || preg_match("/_withheld$/", $v)) continue; 
            if ($single && $v !== $single . '_tweets' && $v !== $single . '_hashtags' && $v !== $single . '_mentions' && $v !== $single . '_urls') continue;
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

    // 24/02/2015 remove media_type, photo_size_width and photo_size_height fields from _urls table, add url_media_id
    //            create media table
    $query = "SHOW TABLES";
    $rec = $dbh->prepare($query);
    $rec->execute();
    $results = $rec->fetchAll(PDO::FETCH_COLUMN);
    foreach ($results as $k => $v) {
        if (!preg_match("/_urls$/", $v)) continue; 
        if ($single && $v !== $single . '_urls') { continue; }
        $query = "SHOW COLUMNS FROM $v";
        $rec = $dbh->prepare($query);
        $rec->execute();
        $columns = $rec->fetchAll(PDO::FETCH_COLUMN);
        $update_remove = FALSE;
        foreach ($columns as $i => $c) {
            if ($c == 'photo_size_width') {
                $update_remove = TRUE;
                break;
            }
        }
        if ($update_remove) {
            logit("cli", "Removing columns media_type, photo_size_width and photo_size_height from table $v");
            $query = "ALTER TABLE " . quoteIdent($v) .
                        " DROP COLUMN `media_type`," .
                        " DROP COLUMN `photo_size_width`," .
                        " DROP COLUMN `photo_size_height`";
            $rec = $dbh->prepare($query);
            $rec->execute();
        }
        $mediatable = preg_replace("/_urls$/", "_media", $v);
        if (!in_array($mediatable, array_values($results))) {
            logit("cli", "Creating table $mediatable");
            $query = "CREATE TABLE IF NOT EXISTS " . quoteIdent($mediatable) . " (
                `id` bigint(20) NOT NULL,
                `tweet_id` bigint(20) NOT NULL,
                `media_url_https` varchar(2048),
                `media_type` varchar(32),
                `photo_size_width` int(11),
                `photo_size_height` int(11),
                `photo_resize` varchar(32),
                `indice_start` int(11),
                `indice_end` int(11),
                PRIMARY KEY (`id`),
                        KEY `tweet_id` (`tweet_id`),
                        KEY `media_type` (`media_type`),
                        KEY `photo_size_width` (`photo_size_width`),
                        KEY `photo_size_height` (`photo_size_height`),
                        KEY `photo_resize` (`photo_resize`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4";
            $rec = $dbh->prepare($query);
            $rec->execute();
        }
    }

    // End of upgrades
}

upgrades();
