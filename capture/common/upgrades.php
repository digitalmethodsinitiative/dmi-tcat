<?php

/*
 * return true if an upgrade is in progress, false otherwise
 */
function upgrade_locked() {
    
    $lockfile = BASE_FILE . "proc/upgrades";

    if (!file_exists($lockfile)) return false;
    if (filesize($lockfile) > 0) return true;
 
    return false;
}

function upgrades() {
    
    global $database;

    // 10/07/2014 Add global lock to signify an upgrade

    $lockfile = BASE_FILE . "proc/upgrades";

    if (!file_exists($lockfile)) {
        touch($lockfile);
    }

    $lockfp = fopen($lockfile, "r+");

    if (flock($lockfp, LOCK_EX)) {  // acquire an exclusive lock
        ftruncate($lockfp, 0);      // truncate file
        fwrite($lockfp, "DMI-TCAT upgrade in progress running from script " . CAPTURE .  ". Started the upgrade on: " . date("D M d, Y G:i") . "\n");
        fflush($lockfp);            // flush output
    } else {
        fclose($fp);
        return;
    }

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

    if ($character_set_database == 'utf8' && $collation_database == 'utf8_general_ci') {

        fwrite($lockfp, "Converting database character set from utf8 to utf8mb4" . "\n"); fflush($lockfp);

        $query = "ALTER DATABASE $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        $rec = $dbh->prepare($query);
        $rec->execute();

        $query = "SHOW TABLES";
        $rec = $dbh->prepare($query);
        $rec->execute();
        $results = $rec->fetchAll(PDO::FETCH_COLUMN);
        foreach ($results as $k => $v) {
            $query ="ALTER TABLE $v DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $rec = $dbh->prepare($query);
            $rec->execute();
            $query ="ALTER TABLE $v CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $rec = $dbh->prepare($query);
            $rec->execute();
        }

    }
        
    // End of upgrades

    flock($lockfp, LOCK_UN);    // release the lock
    fclose($lockfp);

    unlink($lockfile);
}
