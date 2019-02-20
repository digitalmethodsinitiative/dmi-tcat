<?php

include_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../common/constants.php';
include_once __DIR__ . '/../common/functions.php';

if (!is_admin())
    die("Sorry, access denied. Your username does not match the ADMIN user defined in the config.php file.");

include_once __DIR__ . '/../common/functions.php';
include_once __DIR__ . '/../capture/common/functions.php';

$captureroles = unserialize(CAPTUREROLES);
$now = strftime("%Y-%m-%d %H:%M:00", date('U') + 60); // controller only called each minute

if (isset($_POST) && isset($_POST['action'])) {
    $action = $_POST["action"];

    switch ($action) {
        case "autoupgrade":
            tcat_autoupgrade();
            echo '{"msg":"We are now running auto upgrade in the background. Please have patience or watch the logs/controller.log file on your server for progress indications."}';
            break;
        case "newbin":
            create_new_bin($_POST);
            break;
        case "pausebin":
            pause_bin($_POST);
            break;
        case "modifybin":
            modify_bin($_POST);
            break;
        case "removebin":
            remove_bin($_POST);
        case "renamebin":
            rename_bin($_POST);
        default:
            break;
    }
}

function create_new_bin($params) {
    global $captureroles, $now;

    $bin_name = trim($params["newbin_name"]);
    if (table_exists($bin_name) != 0) {
        echo '{"msg":"Query bin [' . $bin_name . '] already exists. Please change your bin name."}';
        return;
    }
    $type = $params['type'];
    if (array_search($type, $captureroles) === false && ($type !== 'geotrack' || array_search('track', $captureroles) === false)) {
        echo '{"msg":"This capturing type is not defined in the config file"}';
        return;
    }
    $comments = sanitize_comments($params['newbin_comments']);

    // check whether the main query management tables are there, if not, create
    create_admin();

    $dbh = pdo_connect();

    // if one percent check whether there already is an active onepercent bin
    if ($type == "onepercent") {
        $sql = "SELECT querybin FROM tcat_query_bins WHERE type = 'onepercent' AND active = 1";
        $rec = $dbh->prepare($sql);
        if ($rec->execute() && $rec->rowCount() > 0) {
            echo '{"msg":"You can only have one active one percent stream at the same time"}';
            return;
        }
    }

    // populate tcat_query_bin table
    $sql = "INSERT INTO tcat_query_bins (querybin,type,active,comments) VALUES (:querybin, :type, '1', :comments);";
    $insert_querybin = $dbh->prepare($sql);
    $insert_querybin->bindParam(':querybin', $bin_name, PDO::PARAM_STR);
    $insert_querybin->bindParam(':type', $type, PDO::PARAM_STR);
    $insert_querybin->bindParam(':comments', $comments, PDO::PARAM_STR);
    $insert_querybin->execute();
    $lastbinid = $dbh->lastInsertId();

    // insert a period
    $sql = "INSERT INTO tcat_query_bins_periods (querybin_id,starttime,endtime) VALUES ('" . $lastbinid . "','$now','0000-00-00 00:00:00')";
    $insert_periods = $dbh->prepare($sql);
    $insert_periods->execute();


    $e = create_bin($bin_name);
    if ($e !== TRUE) {
        logit('controller.log', 'Failed to create database tables for bin ' . $bin_name . '. The error message was ' . $e);
        echo '{"msg":"Failed to create database tables. Please read the controller.log file for details"}';
        return;
    }

    if ($type == "track" || $type == "geotrack") {

        if ($type == "track") {
            $phrases = explode(",", $params["newbin_phrases"]);
            $phrases = array_trim_and_unique($phrases);
        } elseif ($type == "geotrack") {
            $phrases = get_phrases_from_geoquery($params["newbin_phrases"]);

            // Validate geo phrases

            foreach ($phrases as $geophrase) {
                $area = geoPhraseArea($geophrase);
                if ($area > 45.0) {
                    echo '{"msg":"Cannot add geobox ' . $geophrase . '. Area is too large."}';
                    return;
                }
            }
        }

        // populate the phrases and connector tables
        foreach ($phrases as $phrase) {
            $phrase = str_replace("\"", "'", $phrase);
            $sql = "SELECT distinct(id) FROM tcat_query_phrases WHERE phrase = :phrase";
            $check_phrase = $dbh->prepare($sql);
            $check_phrase->bindParam(":phrase", $phrase, PDO::PARAM_STR);
            $check_phrase->execute();
            if ($check_phrase->rowCount() > 0) {
                $results = $check_phrase->fetch();
                $inid = $results['id'];
            } else {
                $sql = "INSERT INTO tcat_query_phrases (phrase) VALUES (:phrase)";
                $insert_phrase = $dbh->prepare($sql);
                $insert_phrase->bindParam(":phrase", $phrase, PDO::PARAM_STR);
                $insert_phrase->execute();
                $inid = $dbh->lastInsertId();
            }
            $sql = "INSERT INTO tcat_query_bins_phrases (phrase_id,querybin_id,starttime,endtime) VALUES ('" . $inid . "','" . $lastbinid . "','$now','0000-00-00 00:00:00')";
            $insert_connect = $dbh->prepare($sql);
            $insert_connect->execute();
        }
    } elseif ($type == "follow") {
        $users = explode(",", $params["newbin_users"]);
        $users = array_trim_and_unique($users);

        foreach ($users as $user) {
            // populate the users and connector tables
            $sql = "INSERT IGNORE INTO tcat_query_users (id) VALUES (:user_id)";
            $insert_phrase = $dbh->prepare($sql);
            $insert_phrase->bindParam(":user_id", $user, PDO::PARAM_INT);
            $insert_phrase->execute();      // the user id can already exist here, but this 'error' will be ignored
            $sql = "INSERT INTO tcat_query_bins_users (user_id,querybin_id,starttime,endtime) VALUES ('" . $user . "','" . $lastbinid . "','$now','0000-00-00 00:00:00')";
            $insert_connect = $dbh->prepare($sql);
            $insert_connect->execute();
        }
    }

    if (web_reload_config_role($type)) {
        echo '{"msg":"The new query bin has been created"}';
    } else {
        echo '{"msg":"The new query bin has been created but the ' . $type . ' script could NOT be restarted"}';
    }
    $dbh = false;
}

function remove_bin($params) {
    global $captureroles, $now;

    $bin_id = trim($params["bin"]);
    $type = trim($params['type']);

    $dbh = pdo_connect();

    // get name of the query_bin
    $sql = "SELECT querybin FROM tcat_query_bins WHERE id = :bin_id AND type = :type";
    $select_querybin = $dbh->prepare($sql);
    $select_querybin->bindParam(':bin_id', $bin_id, PDO::PARAM_INT);
    $select_querybin->bindParam(':type', $type, PDO::PARAM_STR);
    $select_querybin->execute();
    if ($select_querybin->rowCount() == 0) {
        echo '{"msg":"The query bin with id [' . $bin_id . '] cannot be found."}';
        return;
    }
    if ($select_querybin->rowCount() > 1) {
        echo '{"msg":"There seem to be multiple query bins with id [' . $bin_id . ']. You will have to remove it manually."}';
        return;
    }
    if ($select_querybin->rowCount() > 0) {
        $results = $select_querybin->fetch();
        $bin_name = $results['querybin'];
    }

    // delete tcat_query_bin table
    $sql = "DELETE FROM tcat_query_bins WHERE id = :id";
    $delete_querybin = $dbh->prepare($sql);
    $delete_querybin->bindParam(':id', $bin_id, PDO::PARAM_INT);
    $delete_querybin->execute();

    // delete periods associated with the query bin
    $sql = "DELETE FROM tcat_query_bins_periods WHERE querybin_id = :id";
    $delete_querybin_periods = $dbh->prepare($sql);
    $delete_querybin_periods->bindParam(':id', $bin_id, PDO::PARAM_INT);
    $delete_querybin_periods->execute();

    // delete phrase references associated with the query bin
    $sql = "DELETE FROM tcat_query_bins_phrases WHERE querybin_id = :id";
    $delete_query_bins_phrases = $dbh->prepare($sql);
    $delete_query_bins_phrases->bindParam(":id", $bin_id, PDO::PARAM_INT);
    $delete_query_bins_phrases->execute();

    // delete orphaned phrases
    $sql = "DELETE FROM tcat_query_phrases where id not in ( select phrase_id from tcat_query_bins_phrases )";
    $delete_query_phrases = $dbh->prepare($sql);
    $delete_query_phrases->execute();

    // delete user references associated with the query bin
    $sql = "DELETE FROM tcat_query_bins_users WHERE querybin_id = :id";
    $delete_query_bins_users = $dbh->prepare($sql);
    $delete_query_bins_users->bindParam(":id", $bin_id, PDO::PARAM_INT);
    $delete_query_bins_users->execute();

    // delete orphaned users
    $sql = "DELETE FROM tcat_query_users where id not in ( select user_id from tcat_query_bins_users )";
    $delete_query_users = $dbh->prepare($sql);
    $delete_query_users->execute();

    $sql = "DROP TABLE " . $bin_name . "_tweets";
    $delete_table = $dbh->prepare($sql);
    $delete_table->execute();

    $sql = "DROP TABLE " . $bin_name . "_mentions";
    $delete_table = $dbh->prepare($sql);
    $delete_table->execute();

    $sql = "DROP TABLE " . $bin_name . "_hashtags";
    $delete_table = $dbh->prepare($sql);
    $delete_table->execute();

    $sql = "DROP TABLE " . $bin_name . "_urls";
    $delete_table = $dbh->prepare($sql);
    $delete_table->execute();

    $sql = "DROP TABLE " . $bin_name . "_withheld";
    $delete_table = $dbh->prepare($sql);
    $delete_table->execute();

    $sql = "DROP TABLE " . $bin_name . "_places";
    $delete_table = $dbh->prepare($sql);
    $delete_table->execute();

    $sql = "DROP TABLE " . $bin_name . "_media";
    $delete_table = $dbh->prepare($sql);
    $delete_table->execute();

    echo '{"msg":"Query bin [' . $bin_name . ']has been deleted"}';

    $dbh = false;
}

function pause_bin($params) {
    global $captureroles, $now;

    $dbh = pdo_connect();

    if (!table_id_exists($params["bin"])) {
        echo '{"msg":"The query bin could not be found"}';
        return false;
    }
    $type = $params['type'];
    if (array_search($type, $captureroles) === false && ($type !== 'geotrack' || array_search('track', $captureroles) === false)) {
        echo '{"msg":"This capturing type is not defined in the config file"}';
        return;
    }

    $querybin_id = $params["bin"];

    // set the active flag in the query_bins table
    $newstate = ($params["todo"] == "stop") ? 0 : 1;
    $sql = "UPDATE tcat_query_bins SET active = :active WHERE id = :querybin_id";
    $modify_bin = $dbh->prepare($sql);
    $modify_bin->bindParam(":active", $newstate, PDO::PARAM_BOOL);
    $modify_bin->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
    $modify_bin->execute();

    // manage the query_bins_periods
    if ($params["todo"] == "stop") {
        $sql = "SELECT distinct(id) FROM tcat_query_bins_periods WHERE querybin_id = :querybin_id AND endtime = '0000-00-00 00:00:00'";
        $read_periods = $dbh->prepare($sql);
        $read_periods->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
        if ($read_periods->execute() && $read_periods->rowCount() > 0) {
            $result = $read_periods->fetch();
            $updateid = $result["id"];
            $sql = "UPDATE tcat_query_bins_periods SET endtime = '$now' WHERE id = $updateid";
            $update_periods = $dbh->prepare($sql);
            $update_periods->execute();
        }
    } else {
        $sql = "INSERT INTO tcat_query_bins_periods (querybin_id, starttime, endtime) VALUES (:querybin_id, '$now', '0000-00-00 00:00:00')";
        $insert_periods = $dbh->prepare($sql);
        $insert_periods->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
        $insert_periods->execute();
    }

    if ($type == "onepercent") { // one percent does not have queries or users, only period is the bin period
        echo '{"msg":"Your query bin has been ' . $params["todo"] . 'ed"}';
        $dbh = false;
        return;
    }

    // manage the phrase and user periods
    if ($params["todo"] == "start") {
        // get latest active queries or users
        if ($type == "track" || $type == "geotrack")
            $sql = "SELECT min(endtime) as min, max(endtime) AS max FROM tcat_query_bins_phrases WHERE querybin_id = :querybin_id";
        elseif ($type == "follow")
            $sql = "SELECT min(endtime) as min, max(endtime) AS max FROM tcat_query_bins_users WHERE querybin_id = :querybin_id";
        $rec = $dbh->prepare($sql);
        $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
        $rec->execute();
        $res = $rec->fetch();
        if ($res['min'] == "0000-00-00 00:00:00") // if prhases were first modified and now need to be started
            $endtime = $res['min'];
        else
            $endtime = $res['max'];

        if ($type == "track")
            $sql = "SELECT phrase_id FROM tcat_query_bins_phrases WHERE querybin_id = :querybin_id AND endtime = '$endtime'";
        elseif ($type == "follow")
            $sql = "SELECT user_id FROM tcat_query_bins_users WHERE querybin_id = :querybin_id AND endtime = '$endtime'";
        $read_periods = $dbh->prepare($sql);
        $read_periods->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
        $read_periods->execute();
        $results = $read_periods->fetchAll();
        foreach ($results as $res) {
            if ($type == "track")
                $sql = "INSERT tcat_query_bins_phrases (phrase_id, querybin_id, starttime, endtime) VALUES (" . $res['phrase_id'] . ", :querybin_id, '$now', '0000-00-00 00:00:00')";
            elseif ($type == "follow")
                $sql = "INSERT tcat_query_bins_users (user_id, querybin_id, starttime, endtime) VALUES (" . $res['user_id'] . ", :querybin_id, '$now', '0000-00-00 00:00:00')";
            $insert_periods = $dbh->prepare($sql);
            $insert_periods->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
            $insert_periods->execute();
        }
    } else {
        // stop all active queries or users
        if ($type == "track")
            $sql = "SELECT id, querybin_id FROM tcat_query_bins_phrases WHERE querybin_id = :querybin_id AND endtime = '0000-00-00 00:00:00'";
        elseif ($type == "follow")
            $sql = "SELECT id, querybin_id FROM tcat_query_bins_users WHERE querybin_id = :querybin_id AND endtime = '0000-00-00 00:00:00'";
        $read_periods = $dbh->prepare($sql);
        $read_periods->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
        $read_periods->execute();
        $results = $read_periods->fetchAll();
        foreach ($results as $res) {
            if ($type == "track")
                $sql = "UPDATE tcat_query_bins_phrases SET endtime = '$now' WHERE querybin_id = :querybin_id AND id = " . $res['id'];
            if ($type == "follow")
                $sql = "UPDATE tcat_query_bins_users SET endtime = '$now' WHERE querybin_id = :querybin_id AND id = " . $res['id'];
            $update_periods = $dbh->prepare($sql);
            $update_periods->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
            $update_periods->execute();
        }
    }

    if ($params['todo'] == "stop")
        $params['todo'] = "stopp";
    if (web_reload_config_role($type)) {
        echo '{"msg":"Your query bin has been ' . $params["todo"] . 'ed"}';
    } else {
        echo '{"msg":"Your query bin has been set as ' . $params["todo"] . 'ed but the ' . $type . ' script could NOT be restarted"}';
    }
    $dbh = false;
}

function rename_bin($params) {
    $dbh = pdo_connect();

    if (!table_id_exists($params["bin"])) {
        echo '{"msg":"The query bin could not be found"}';
        return false;
    }

    if (!array_key_exists('newname', $params) || !is_string($params['newname']) || strlen($params['newname']) < 1 || strlen($params['newname'] > 45) ||
        preg_match("/[ `;'\"\(\)]/", $params['newname'])) {
        echo '{"msg":"Illegal query bin name"}';
        return false;
    }

    if (queryManagerBinExists($params['newname'])) {
        echo '{"msg":"The new name for the query bin is already in use"}';
        return false;
    }

    $querybin_id = $params["bin"];
    $newname = $params["newname"];

    // get name of the old query_bin
    $sql = "SELECT querybin FROM tcat_query_bins WHERE id = :bin_id";
    $select_querybin = $dbh->prepare($sql);
    $select_querybin->bindParam(':bin_id', $querybin_id, PDO::PARAM_INT);
    $select_querybin->execute();
    if ($select_querybin->rowCount() == 0) {
        echo '{"msg":"The query bin with id [' . $querybin_id . '] cannot be found."}';
        return;
    }
    $results = $select_querybin->fetch();
    $oldname = $results['querybin'];

    // change the name in the TCAT tables
    $sql = "UPDATE tcat_query_bins SET querybin = :newname WHERE id = :querybin_id";
    $modify_bin = $dbh->prepare($sql);
    $modify_bin->bindParam(":newname", $newname, PDO::PARAM_STR);
    $modify_bin->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
    $modify_bin->execute();

    // alter MySQL table names
    $exts = array('tweets', 'mentions', 'urls', 'hashtags', 'withheld', 'places', 'media');
    foreach ($exts as $ext) {
        $oldfull = $oldname . '_' . $ext;
        $newfull = $newname . '_' . $ext;
        $sql = "ALTER TABLE `$oldfull` RENAME `$newfull`";
        $modify_bin = $dbh->prepare($sql);
        // table may not exist
        try {
            @$modify_bin->execute();
        } catch (Exception $e) {
            // ignore error
        }
    }

    echo '{"msg":"Your query bin has been renamed to ' . $newname . '"}';
    $dbh = false;
}

function modify_bin_comments($querybin_id, $params) {
    $dbh = pdo_connect();
    $comments = sanitize_comments($params['comments']);
    $sql = "UPDATE tcat_query_bins SET comments = :comments WHERE id = :querybin_id";
    $rec = $dbh->prepare($sql);
    $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
    $rec->bindParam(":comments", $comments, PDO::PARAM_STR);
    $rec->execute();
    $dbh = false;
    echo '{"msg": "The comments have been modified"}';
}

function modify_bin($params) {
    global $captureroles, $now;

    if (!table_id_exists($params["bin"])) {
        echo '{"msg":"The bin ' . $params['bin'] . ' does not seem to exist"}';
        return;
    }
    $querybin_id = trim($params['bin']);

    if (array_key_exists('comments', $params) && $params['comments'] !== '') return modify_bin_comments($querybin_id, $params);

    $type = $params['type'];
    if (array_search($type, $captureroles) === false && ($type !== 'geotrack' || array_search('track', $captureroles) === false)) {
        echo '{"msg":"This capturing type is not defined in the config file"}';
        return;
    }

    $dbh = pdo_connect();

    if ($type == "geotrack") {
        // @todo: what about explicit order in in geobin queries?
        $oldphrases = get_phrases_from_geoquery($params["oldphrases"]);
        $newphrases = get_phrases_from_geoquery($params["newphrases"]);
    } else {
        $oldphrases = array_trim_and_unique(explode(",", $params["oldphrases"]));
        $newphrases = array_trim_and_unique(explode(",", $params["newphrases"]));
    }

    $outs = array_diff($oldphrases, $newphrases);
    $ins = array_diff($newphrases, $oldphrases);

    // set endtime to now for each phrase or user going out
    if (!empty($outs)) {
        if ($type == "track" || $type == "geotrack")
            $sql = "SELECT distinct(bp.id) FROM tcat_query_bins_phrases bp, tcat_query_phrases p WHERE bp.phrase_id = p.id AND bp.querybin_id = ? AND p.phrase IN (" . implode(',', array_fill(0, count($outs), '?')) . ") AND bp.endtime = '0000-00-00 00:00:00'";
        elseif ($type == "follow")
            $sql = "SELECT distinct(bu.id) FROM tcat_query_bins_users bu, tcat_query_users u WHERE bu.user_id = u.id AND bu.querybin_id = ? AND u.id IN (" . implode(',', array_fill(0, count($outs), '?')) . ") AND bu.endtime = '0000-00-00 00:00:00'";
        $rec = $dbh->prepare($sql);
        array_unshift($outs, $querybin_id);
        if ($rec->execute($outs) && $rec->rowCount()) {
            $ids = $rec->fetchAll(PDO::FETCH_COLUMN);
            if ($type == "track" || $type == "geotrack")
                $sql = "UPDATE tcat_query_bins_phrases SET endtime = '$now' WHERE querybin_id = :querybin_id AND id IN (" . implode(",", $ids) . ")";
            elseif ($type == "follow")
                $sql = "UPDATE tcat_query_bins_users SET endtime = '$now' WHERE querybin_id = :querybin_id AND id IN (" . implode(",", $ids) . ")";
            $update_periods = $dbh->prepare($sql);
            $update_periods->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
            $update_periods->execute();
        }
    }

    // for each phrase or user coming in, check if it already exists, if not create, set new bin-phrase connection
    if (!empty($ins)) {
        foreach ($ins as $in) {
            if ($type == "track" || $type == "geotrack") {
                $sql = "SELECT id FROM tcat_query_phrases WHERE phrase = :phrase";
                $check_phrase = $dbh->prepare($sql);
                $check_phrase->bindParam(":phrase", $in, PDO::PARAM_STR);
            } elseif ($type == "follow") {
                $sql = "SELECT id FROM tcat_query_users WHERE id = :user_id";
                $check_phrase = $dbh->prepare($sql);
                $check_phrase->bindParam(":user_id", $in, PDO::PARAM_INT);
            }
            $check_phrase->execute();
            if (!$check_phrase->rowCount()) {
                if ($type == "track" || $type == "geotrack") {
                    $in = str_replace("\"", "'", $in);
                    $sql = "INSERT INTO tcat_query_phrases(phrase) VALUES(:phrase)";
                    $insert_phrase = $dbh->prepare($sql);
                    $insert_phrase->bindParam(":phrase", $in, PDO::PARAM_STR);
                } elseif ($type == "follow") {
                    $sql = "INSERT INTO tcat_query_users(id) VALUES(:user_id)";
                    $insert_phrase = $dbh->prepare($sql);
                    $insert_phrase->bindParam(":user_id", $in, PDO::PARAM_INT);
                }
                $insert_phrase->execute();
                $inid = $dbh->lastInsertId();
            } else {
                $results = $check_phrase->fetchAll();
                $inid = $results[0]["id"];
            }
            // double check whether phrase or user is already running
            if ($type == "track" || $type == "geotrack")
                $sql = "SELECT min(endtime) AS min FROM tcat_query_bins_phrases WHERE querybin_id = :querybin_id AND phrase_id = $inid";
            elseif ($type == "follow")
                $sql = "SELECT min(endtime) AS min FROM tcat_query_bins_users WHERE querybin_id = :querybin_id AND user_id = $inid";
            $rec = $dbh->prepare($sql);
            $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
            $rec->execute();
            $insertit = true;
            if ($rec->rowCount() != 0) {
                $res = $rec->fetch(PDO::FETCH_COLUMN);
                if ($res == "0000-00-00 00:00:00")
                    $insertit = false;
            }
            // insert new period
            if ($insertit) {
                if ($type == "track" || $type == "geotrack")
                    $sql = "INSERT INTO tcat_query_bins_phrases(phrase_id, querybin_id, starttime, endtime) VALUES($inid, :querybin_id,  '$now', '0000-00-00 00:00:00')";
                elseif ($type == "follow")
                    $sql = "INSERT INTO tcat_query_bins_users(user_id, querybin_id, starttime, endtime) VALUES($inid, :querybin_id,  '$now', '0000-00-00 00:00:00')";
                $insert_connect = $dbh->prepare($sql);
                $insert_connect->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
                $insert_connect->execute();
            }
        }
    }

    if ($params['active'] == 0) {
        $params["todo"] = "start";
        pause_bin($params);
    } else {
        // restart capture
        if (web_reload_config_role($type)) {
            echo '{"msg": "The queries have been modified"}';
        } else {
            echo '{"msg": "The queries have been modified but the ' . $type . ' script could NOT be restarted"}';
        }
    }
    $dbh = false;
}

function array_trim_and_unique($array) {
    foreach ($array as $k => $v) {
        $v = trim($v);
        if (empty($v))
            unset($array[$k]
            );
        else
            $array[$k] = $v;
    }
    return $array;
}

function sanitize_comments($comments) {
    try {
        $comments = trim($comments);
        $comments = htmlspecialchars($comments, ENT_QUOTES);
        return $comments;
    } catch (Exception $e) {
        throw $e;
    }
}

function get_phrases_from_geoquery($query) {
    // make segments of four
    $phrases = array();
    $raw = explode(",", $query);
    $phrase = '';
    $i = -1;
    $comma = false;
    while (isset($raw[++$i])) {
        if ($comma) {
            $phrase .= ',';
        } else {
            $comma = true;
        }
        $phrase .= $raw[$i];
        if (($i + 1) % 4 == 0) {
            $phrases[] = $phrase;
            $phrase = '';
            $comma = false;
        }
    }
    return $phrases;
}

function quote_table_name($table) {
    return preg_replace("/[`;,'\"]/", "", $table);
}

function table_exists($binname) {
    $dbh = pdo_connect();

    $sql = "SELECT 1 FROM `" . quote_table_name($binname . '_tweets') . "` LIMIT 1";
    $query = $dbh->prepare($sql);
    $rc = 0;
    try {
        $query->execute();
        $rc = $query->rowCount();
    } catch (PDOException $e) {
        // good thing. table does not exist
    }
    $dbh = false;
    return $rc;
}

function table_id_exists($id) {
    $dbh = pdo_connect();
    $sql = "SELECT querybin FROM tcat_query_bins WHERE id = :querybin_id";
    $rec = $dbh->prepare($sql);
    $rec->bindParam(":querybin_id", $id, PDO::PARAM_INT);
    if ($rec->execute() && $rec->rowCount() > 0) {
        $res = $rec->fetch();
        $binname = $res['querybin'];

        $sql = "SHOW TABLES LIKE '$binname%'";
        $query = $dbh->prepare($sql);
        $query->execute();
        $rc = $query->rowCount();
        $dbh = false;
        return $rc;
    }
    $dbh = false;
    return 0;
}

function getNrOfActivePhrases() {
    $dbh = pdo_connect();

    $sql = "SELECT count(distinct(p.phrase)) AS count FROM tcat_query_phrases p, tcat_query_bins_phrases bp, tcat_query_bins b WHERE b.id = bp.querybin_id AND b.type = 'track' AND  p.id = bp.phrase_id AND bp.endtime = '0000-00-00 00:00:00'";
    $res = $dbh->prepare($sql);
    $res->execute();
    if ($res->rowCount() > 0) {
        $result = $res->fetch();
        return $result['count'];
    }
    $dbh = false;
    return 0;
}

function getNrOfActiveGeoboxes() {
    $dbh = pdo_connect();

    $sql = "SELECT count(distinct(p.phrase)) AS count FROM tcat_query_phrases p, tcat_query_bins_phrases bp, tcat_query_bins b WHERE b.id = bp.querybin_id AND b.type = 'geotrack' AND  p.id = bp.phrase_id AND bp.endtime = '0000-00-00 00:00:00'";
//SELECT COUNT(*) AS cnt FROM tcat_query_bins WHERE `type` = 'geotrack' and active = 1
    $res = $dbh->prepare($sql);
    $res->execute();
    if ($res->rowCount() > 0) {
        $result = $res->fetch();
        return $result['count'];
    }
    $dbh = false;
    return 0;
}

function getNrOfActiveUsers() {
    $dbh = pdo_connect();

    $sql = "SELECT count(distinct(u.id)) AS count FROM tcat_query_users u, tcat_query_bins_users bu WHERE u.id = bu.user_id AND bu.endtime = '0000-00-00 00:00:00'";
    $res = $dbh->prepare($sql);
    $res->execute();
    if ($res->rowCount() > 0) {
        $result = $res->fetch();
        return $result['count'];
    }
    $dbh = false;
    return 0;
}

function getBinIds() {


    $dbh = pdo_connect();
    $sql = "SELECT id, querybin FROM tcat_query_bins";
    $rec = $dbh->prepare($sql);
    $tables = array();
    if ($rec->execute() && $rec->rowCount() > 0) {
        while ($res = $rec->fetch())
            $tables[
                    $res['id']] = $res['querybin'];
    }
    $dbh = false;
    return $tables;
}

function getBins() {

    $dbh = pdo_connect();

    // select query bins
    // select phrases
    // select users

    $sql = "SELECT b.id, b.querybin, b.type, b.comments, b.active, period.starttime AS bin_starttime, period.endtime AS bin_endtime FROM tcat_query_bins b, tcat_query_bins_periods period WHERE b.id = period.querybin_id AND b.access != " . TCAT_QUERYBIN_ACCESS_INVISIBLE . " GROUP BY b.id";

    $rec = $dbh->prepare($sql);
    $rec->execute();
    $bin_results = $rec->fetchAll();
    $querybins = array();
    foreach ($bin_results as $data) {
        if (!isset($querybins[$data['id']])) {
            $bin = new stdClass();
            $bin->periods = array();
            $bin->phrases = array();
            $bin->users = array();
            $bin->id = $data['id'];
            $bin->name = $data['querybin'];
            $bin->type = $data['type'];
            $bin->active = $data['active'];
            $bin->comments = $data['comments'];
        } else {
            $bin = $querybins[$data['id']];
        }
        $bin->periods[] = $data['bin_starttime'] . " - " . str_replace("0000-00-00 00:00:00", "now", $data['bin_endtime']);

        if ($bin->type == "track" || $bin->type == "geotrack") {
            $sql = "SELECT p.id AS phrase_id, p.phrase, bp.starttime AS phrase_starttime, bp.endtime AS phrase_endtime FROM tcat_query_phrases p, tcat_query_bins_phrases bp WHERE p.id = bp.phrase_id AND bp.querybin_id = " . $bin->id;

            $rec = $dbh->prepare($sql);
            $rec->execute();
            $phrase_results = $rec->fetchAll();
            foreach ($phrase_results as $result) {
                if (!isset($bin->phrases[$result['phrase_id']])) {
                    $phrase = new stdClass();
                    $phrase->id = $result['phrase_id'];
                    $phrase->phrase = $result['phrase'];
                    $phrase->periods = array();
                    $phrase->active = false;
                } else {
                    $phrase = $bin->phrases[$result['phrase_id']];
                }
                if ($result["phrase_endtime"] == "0000-00-00 00:00:00")
                    $phrase->active = true;

                $phrase->periods[] = $result['phrase_starttime'] . " - " . str_replace("0000-00-00 00:00:00", "now", $result["phrase_endtime"]);
                $bin->phrases[$result['phrase_id']] = $phrase;
            }
        } elseif ($bin->type == "follow" || $bin->type == "timeline") {
            $sql = "SELECT u.id AS user_id, u.user_name, bu.starttime AS user_starttime, bu.endtime AS user_endtime FROM tcat_query_users u, tcat_query_bins_users bu WHERE u.id = bu.user_id AND bu.querybin_id = " . $bin->id;
            $rec = $dbh->prepare($sql);
            $rec->execute();
            $user_results = $rec->fetchAll();
            foreach ($user_results as $result) {
                if (!isset($bin->users[$result['user_id']])) {
                    $user = new stdClass();
                    $user->id = $result['user_id'];
                    $user->user_name = $result['user_name'];
                    $user->periods = array();
                    $user->active = false;
                } else {
                    $user = $bin->users[$result['user_id']];
                }
                if ($result["user_endtime"] == "0000-00-00 00:00:00")
                    $user->active = true;

                $user->periods[] = $result['user_starttime'] . " - " . str_replace("0000-00-00 00:00:00", "now", $result["user_endtime"]);
                $bin->users[$result['user_id']] = $user;
            }
        }
        $querybins[$bin->id] = $bin;
    }



    // get nr of tweets per bin
    foreach ($querybins as $bin) {
        $querybins[$bin->id]->nrOfTweets = 0;
        $sql = "SELECT count(id) AS count FROM " . $bin->name . "_tweets";
        $res = $dbh->prepare($sql);
        if ($res->execute() && $res->rowCount()) {
            $result = $res->fetch();
            $querybins[$bin->id]->nrOfTweets = $result['count'];
        }
    }
    $dbh = false;
    return $querybins;
}

function getLastRateLimitHit() {
    // For now, we report only about rate limit hits from the last 48 hours on the analysis panel (issue #83)
    $dbh = pdo_connect();
    $rec = $dbh->prepare("SELECT end FROM tcat_error_ratelimit WHERE tweets > 0 and end > date_sub(now(), interval 2 day) ORDER BY end DESC LIMIT 1");
    if ($rec->execute() && $rec->rowCount() > 0) {
        $res = $rec->fetch();
        return $res['end'];
    }
    return 0;
}

?>
