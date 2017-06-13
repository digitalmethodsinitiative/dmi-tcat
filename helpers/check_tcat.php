<?php

/*
 * This is custom Nagios script to report the status of your TCAT server. (UNSUPPORTED CODE. ADVANCED USERS ONLY)
 *
 * It outputs some metrics and returns with a warning/critical status if your server is 1) not capturing tweets or 2) rate limited
 *
 * Sample nagios config line: command[check_tcat]=sh -c 'cd /var/www/dmi-tcat/helpers/ && /usr/bin/php /var/www/dmi-tcat/helpers/check_tcat.php'
 *
 * TODO: Configurability of warning and error thresholds (not on the command-line, though, because NRPE does not allow this anymore).
 * 
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../common/functions.php';
require_once __DIR__ . '/../common/constants.php';
require_once __DIR__ . '/../capture/common/functions.php';

define('NAGIOS_OK', 0);
define('NAGIOS_WARNING', 1);
define('NAGIOS_CRITICAL', 2);
define('NAGIOS_UNKNOWN', 3);

global $dbh;
$dbh = pdo_connect();

// TODO: as we are reworking the ratelimit recording logic, this query may need to be updated
$sql = "select sum(tweets) as exceed from tcat_error_ratelimit where start > (utc_timestamp() - interval 1 hour)";
$rec = $dbh->prepare($sql);
$rec->execute();
$results = $rec->fetch(PDO::FETCH_ASSOC);
$exceed = isset($results['exceed']) ? $results['exceed'] : 0;

if ($exceed >= 50000) {
    $status = NAGIOS_CRITICAL;
} else if ($exceed >= 10000) {
    $status = NAGIOS_WARNING;
}

$data = getActiveTrackBins();
$trackbins = array_keys($data);
$data = getActiveFollowBins();
$followbins = array_keys($data);
$data = getActiveOnepercentBin();
$onepercentbins = array_keys($data);
$performance = "Last hour:";

if (!empty($trackbins)) {
    $report = checkon($trackbins);
    if ($report['total'] == -1) {
        $status = NAGIOS_CRITICAL;
    } else if ($status != NAGIOS_CRITICAL && $report['total'] == 0) {
        $status = NAGIOS_WARNING;
    }
    $performance .= " " . $report['tweetspermin'] . " keyword tweets p/min.";
}
if (!empty($followbins)) {
    $report = checkon($followbins);
    if ($report['total'] == -1) {
        $status = NAGIOS_CRITICAL;
    } else if ($status != NAGIOS_CRITICAL && $report['total'] == 0) {
        $status = NAGIOS_WARNING;
    }
    $performance .= " " . $report['tweetspermin'] . " user tweets p/min.";
}
if (!empty($onepercentbins)) {
    $report = checkon($onepercentbins);
    if ($report['total'] == -1) {
        $status = NAGIOS_CRITICAL;
    } else if ($status != NAGIOS_CRITICAL && $report['total'] == 0) {
        $status = NAGIOS_WARNING;
    }
    $performance .= " " . $report['tweetspermin'] . " one percent tweets p/min.";
}

$performance .= " " . $exceed . " tweets rate limited.";

$reportstr = "";
if ($status == NAGIOS_OK) {
    $reportstr = "TCAT OK - ";
} else if ($status == NAGIOS_WARNING) {
    $reportstr = "TCAT WARNING - ";
} else if ($status == NAGIOS_CRITICAL) {
    $reportstr = "TCAT CRITICAL - ";
} else if ($status == NAGIOS_UNKNOWN) {
    $reportstr = "TCAT UNKNOWN - ";
}
$reportstr .= $performance;

echo $reportstr . PHP_EOL;

exit($status);

function checkon($bins) {
    global $dbh;

    $total = 0;
    foreach ($bins as $bin) {
        $sql = "select count(id) as cnt from `" . $bin . "_tweets` where created_at > (utc_timestamp() - interval 1 hour)";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        $results = $rec->fetch(PDO::FETCH_ASSOC);
        $cnt = isset($results['cnt']) ? $results['cnt'] : 0;
        $total += $cnt;
    }

    $totalonehour = $total;

    if ($total == 0) {
        foreach ($bins as $bin) {
            $sql = "select count(id) as cnt from `" . $bin . "_tweets` where created_at > (now() - interval 3 hour)";
            $rec = $dbh->prepare($sql);
            $rec->execute();
            $results = $rec->fetch(PDO::FETCH_ASSOC);
            $cnt = isset($results['cnt']) ? $results['cnt'] : 0;
            $total += $cnt;
        }
        if ($total == 0) {
            //  No tweets captured within the last three hours, return -1
            $total = -1;
        } else {
            //  No tweets captured within the last hour
            $total = 0;
        }
    }

    $tweetspermin = intval($total / 60 + 0.5);

    $result = array ( 'tweetspermin' => $tweetspermin,
                      'total' => $total );

    return $result;
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

