<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/CSV.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Export ratelimit data</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">



        </script>

    </head>

    <body>

        <h1>TCAT :: Export ratelimit data</h1>

        <?php

        /*
         * We want to create a realistic estimate of how many tweets where ratelimited per bin and per interval while:
         * 1) accounting for the relative distribution of tweets per bin in that particular interval (which will fluctuate); this will make the query heavy
         * 2) be mindful of the fact that a single tweet (with a unique tweet id) may end up in multiple query bins
         */
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        // NOTICE: this script does buffered queries, due to the use of temporary tables and parallel queries

        $sql = "SELECT id, `type` FROM tcat_query_bins WHERE querybin = '" . $esc['mysql']['dataset'] . "'";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        if ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $bin_id = $res['id'];
            $bin_type = $res['type'];
        } else {
            die("Query bin not found!");
        }
        if ($bin_type != "track" && $bin_type != "geotrack") {
            echo '<b>Notice:</b> You have requested rate limit data for a query bin with is not of type "track", or "geotrack". There currently is no export module for ratelimit data of other types.<br/>';
            echo '</body></html>';
            die();
        }
        if ($bin_type == "geotrack") {
            // Lookup the earliest entry in the tcat_captured_phrases table for any geotrack bin. Geotrack bins historic rate limit is not reconstructed,
            // therefore we decide to not allow this export function for earlier timeframes.
            $accept = false;
            $sql = "select min(created_at) as earliest from tcat_captured_phrases tcp inner join tcat_query_bins_phrases bp on tcp.phrase_id=bp.phrase_id inner join tcat_query_bins tqb on bp.querybin_id = tqb.id where tqb.type = 'geotrack' and earliest > '" . $esc['datetime']['startdate'] . "'";
            $rec = $dbh->prepare($sql);
            $rec->execute();
            if ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
                if (array_key_exists('earliest', $res) && is_string($res['earliest'])) {
                    $accept = true;
                }
            }
            if ($accept == false) {
                echo '<b>Notice:</b> You have requested rate limit data for a query bin which is of type "geotrack", but for a time period for which we posses no historical data. We cannot handle your request.<br/>';
                echo '</body></html>';
                die();
            }
        }
        // TODO: Support these. This shouldn't be difficult, but requires a little different logic.
        if ($esc['date']['interval'] == "custom" || $esc['date']['interval'] == "overall") {
            echo '<b>Notice:</b> You have selected an interval type which is not yet supported by this export module.<br/>';
            echo '</body></html>';
            die();
        }

        // make filename and open file for write
        if ($bin_type == "geotrack") {
            $module = "rateLimitDataGeo";
        } else {
            $module = "ratelimitData";
        }
        $module .= "-" . $esc['date']['interval'];
        $filename = get_filename_for_export($module);
        $csv = new CSV($filename, $outputformat);

        // write header
        $header = "querybin,datetime,tweets ratelimited (estimate)";
        $csv->writeheader(explode(',', $header));

        $sqlInterval = sqlInterval(); $sqlSubset = sqlSubset();
        $sqlGroup = " GROUP BY datepart ASC";

        // Use native MySQL to create a temporary table with all dateparts. They should be identical to the dateparts we will use in the GROUP BY statement.
        // Prepare the string mysql needs in date_add()
        $mysqlNativeInterval = "day";       // default $interval = daily
        switch ($esc['date']['interval']) {
            case "hourly": { $mysqlNativeInterval = "hour"; break; }
            case "daily": { $mysqlNativeInterval = "day"; break; }
            case "weekly": { $mysqlNativeInterval = "week"; break; }
            case "monthly": { $mysqlNativeInterval = "month"; break; }
            case "yearly": { $mysqlNativeInterval = "year"; break; }
        }
        $query = "CREATE TEMPORARY TABLE temp_dates ( date DATETIME )";
        $dbh->query($query);
        $query = "SET @date = '" . $esc['datetime']['startdate'] . "'";
        $dbh->query($query);
        for (;;) {
            $query = "INSERT INTO temp_dates SELECT @date := date_add(@date, interval 1 $mysqlNativeInterval)";
            $dbh->query($query);
            // Are we finished?
            $query = "SELECT @date > '" . $esc['datetime']['enddate']  . "' as finished";
            $rec = $dbh->prepare($query);
            $rec->execute();
            if ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
                if ($res['finished'] == '1') {
                    break;
                }
            }
        }
        $dateparts = array();
        $sqlIntervalForDateparts = str_replace("t.created_at", "date", $sqlInterval);
        $query = "SELECT $sqlIntervalForDateparts FROM temp_dates";
        $rec = $dbh->prepare($query);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $dateparts[] = $res['datepart'];
        }

        /*
         *                                            measured phrase matches for bin     (C)
         * Formula for estimates =  (A) ratelimited * --------------------------------
         *                                            total unique tweets with matches    (B)
         */

        $sqlIntervalForRL = str_replace("t.created_at", "start", $sqlInterval);
        $sql_query_a = "SELECT SUM(tweets) as ratelimited, $sqlIntervalForRL FROM tcat_error_ratelimit WHERE start >= '" . $esc['datetime']['startdate'] . "' AND end <= '" . $esc['datetime']['enddate'] . "' $sqlGroup";

        // This query retrieves the total unique tweets captured, grouped by the requested interval (hourly, daily, ...)
        $sql_query_b = "SELECT COUNT(distinct(t.tweet_id)) AS cnt, $sqlInterval FROM tcat_captured_phrases t $sqlSubset $sqlGroup";

        // Notice: we need to do a INNER JOIN on the querybin table here (to match phrase_id to querybin_id)
        $sql_query_c = "SELECT COUNT(distinct(t.tweet_id)) AS cnt, $sqlInterval FROM tcat_captured_phrases t INNER JOIN tcat_query_bins_phrases qbp ON t.phrase_id = qbp.phrase_id $sqlSubset AND qbp.querybin_id = $bin_id $sqlGroup";

        $fullresults = array();

        // Get ratelimits (query A)

        $rec = $dbh->prepare($sql_query_a);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            if (!array_key_exists($res['datepart'], $fullresults)) {
                $fullresults[$res['datepart']] = array();
            }
            $fullresults[$res['datepart']]['ratelimited'] = $res['ratelimited'];
        }

        // Get the total unique phrases with matches (query B)

        $rec = $dbh->prepare($sql_query_b);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            if (!array_key_exists($res['datepart'], $fullresults)) {
                $fullresults[$res['datepart']] = array();
            }
            $fullresults[$res['datepart']]['totalphrases'] = $res['cnt'];
        }
        
        // Get the measured phrases per bin (query C)

        $rec = $dbh->prepare($sql_query_c);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            if (!array_key_exists($res['datepart'], $fullresults)) {
                $fullresults[$res['datepart']] = array();
            }
            $fullresults[$res['datepart']]['measuredbin'] = $res['cnt'];
        }

        foreach ($dateparts as $datepart) {

            if (!array_key_exists($datepart, $fullresults)) {
                $csv->newrow();
                $csv->addfield($esc['mysql']['dataset']);
                $csv->addfield($datepart);
                $csv->addfield(-1);                         // report a minus 1 for a datepart with missing ratelimit information
                $csv->writerow();
            } else {

                $row = $fullresults[$datepart];
                if (!array_key_exists('ratelimited', $row) || !array_key_exists('measuredbin', $row) || !array_key_exists('totalphrases', $row)) {
                    // TODO/TEST: this cannot occur I think
                    continue;
                }

                // Now: calculate the estimate using our formula
                $estimate = round( $row['ratelimited'] * $row['measuredbin'] / $row['totalphrases'], 2 );

                $csv->newrow();
                $csv->addfield($esc['mysql']['dataset']);
                $csv->addfield($datepart);
                $csv->addfield($estimate);
                $csv->writerow();
            }

        }

        $csv->close();

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
