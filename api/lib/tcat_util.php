<?php
// TCAT functions for the API scripts.

//----------------------------------------------------------------
// Internal function used to create the SQL condition to restrict an
// operation to the specified time period.
//
// Returns a string value of the form:
// - "xxx <= created_at AND created_at <= yyy" -- if both specified
// - "xxx <= created_at" -- if only $dt_start specified
// - "created_at <= yyy" -- if only $dt_end specified
// - NULL -- if neither were specified

function created_at_condition($dt_start, $dt_end)
{

    // Note: The MySQL DATETIME datatype does not track timezones.
    // TCAT stores UTC timezone values in the DATETIME fields.
    // So these SQL clauses specifies the times in UTC.

    $result = NULL;

    if (isset($dt_start)) {
        $dt_start->setTimezone(new DateTimeZone('UTC'));
        $sts = $dt_start->format('Y-m-d\TH:i:s');
        $result = "\"$sts\" <= created_at";
    }

    if (isset($dt_end)) {
        if (isset($result)) {
            $result .= ' AND ';
        }
        $dt_end->setTimezone(new DateTimeZone('UTC'));
        $sts = $dt_end->format('Y-m-d\TH:i:s');
        $result .= "created_at <= \"$sts\"";
    }

    return $result;
}

//----------------------------------------------------------------
// Tweet info
//
// Retrieve information about the tweets in the time period
// between $dt_start and $dt_end (inclusive).
//
// $query_bin - the array produced by ~/analysis/common/functions.php
// $dt_start - must either be a DateTime object or NULL.
// $dt_end - must either be a DateTime object or NULL.

function tweet_info($query_bin, $dt_start, $dt_end)
{

    // Create WHERE clause to restrict to requested timestamp range

    $time_condition = created_at_condition($dt_start, $dt_end);
    if (isset($time_condition)) {
        $where = 'WHERE ' . $time_condition;
    } else {
        $where = '';
    }

    // Query database

    $result = [];

    $dbh = pdo_connect();

    $bin_name = $query_bin['bin'];

    /* This join is too slow when there is a large number of tweets

        // Tables without a 'created_at' column
        // Must join with *_tweets table to get the 'created_at' timestamp

        foreach (["media", "places", "withheld"] as $tbl) {
            $table_name = $bin_name . '_' . $tbl;
            if ($where !== '') {
                $sql = "SELECT count(*) FROM `{$table_name}` X" .
                       " INNER JOIN `{$bin_name}_tweets` T ON T.id=X.tweet_id $where";
            } else {
                $sql = "SELECT count(*) FROM `{$table_name}`";
            }
            $rec = $dbh->prepare($sql);
            $rec->execute();
            $result[$tbl] = $rec->fetchColumn(0);
        }
    */

    // Tables with a 'created_at' column

    // Only do tweets, otherwise it takes a long time if there are many tweets
    //foreach (["tweets", "hashtags", "mentions", "urls"] as $tbl) {

    foreach (["tweets"] as $tbl) {
        $table_name = $bin_name . '_' . $tbl;
        $rec = $dbh->prepare("SELECT count(*) FROM `{$table_name}` $where");
        $rec->execute();
        $result[$tbl] = $rec->fetchColumn(0);
    }

    return $result;
}

//----------------------------------------------------------------
// Purge captured tweets from the query bin
//
// Deletes tweets and associated data from a query bin.
//
// $query_bin - the array produced by ~/analysis/common/functions.php
// $dt_start - must either be a DateTime object or NULL.
// $dt_end - must either be a DateTime object or NULL.
//
// Returns an array containing the number of rows deleted from each table.

function tweet_purge($query_bin, $dt_start, $dt_end)
{
    // Create WHERE clause to restrict to requested timestamp range

    $time_condition = created_at_condition($dt_start, $dt_end);
    if (isset($time_condition)) {
        $where = 'WHERE ' . $time_condition;
    } else {
        $where = '';
    }

    // Query database

    $result = [];

    $dbh = pdo_connect();

    $bin_name = $query_bin['bin'];

    // Tables without a 'created_at' column

    foreach (["media", "places", "withheld"] as $tbl) {
        $table_name = $bin_name . '_' . $tbl;
        if ($where !== '') {
            // Must join with *_tweets to get 'created_at' timestamp
            $sql = "DELETE FROM `{$table_name}`" .
                " USING `{$table_name}` INNER JOIN `{$bin_name}_tweets`" .
                " ON `{$bin_name}_tweets`.id=`{$table_name}`.tweet_id" .
                " $where";
        } else {
            $sql = "DELETE FROM `{$table_name}`"; // time irrelvant: del all
        }
        $rec = $dbh->prepare($sql);
        $rec->execute();
        $result[$tbl] = $rec->rowCount();
    }

    // Tables with a 'created_at' column

    foreach (["tweets", "hashtags", "mentions", "urls"] as $tbl) {
        $table_name = $bin_name . '_' . $tbl;
        $sql = "DELETE FROM `{$table_name}` $where";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        $result[$tbl] = $rec->rowCount();
    }

    return $result;
}
