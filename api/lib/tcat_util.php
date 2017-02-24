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

function tweet_info($query_bin, $dt_start, $dt_end, $tables = array("tweets"))
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

//    foreach (["tweets"] as $tbl) {
    foreach ($tables as $tbl) {
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

    if (!is_admin()) {
        die("Access denied.");
    }

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

//----------------------------------------------------------------
// Top hashtags
//
// Retrieve information about the top hashtags in the time period
// between $dt_start and $dt_end (inclusive).
//
// $query_bin - the array produced by ~/analysis/common/functions.php
// $dt_start - must either be a DateTime object or NULL.
// $dt_end - must either be a DateTime object or NULL.

function hashtags_top($query_bin, $dt_start, $dt_end, $limit = NULL) {

    global $esc;

    // This function allows special arguments, similar to the TCAT front-end;
    // therefore we validate and process those arguments here
    if (!isset($_GET['dataset'])) {
        $_GET['dataset'] = $query_bin;
    }
    validate_all_variables();

    // Create WHERE clause to restrict to requested timestamp range
    // Convert to sqlSubset() format
    if (isset($dt_start) && isset($dt_end)) {
        $dt_start->setTimezone(new DateTimeZone('UTC'));
        $esc['datetime']['startdate'] = $dt_start->format('Y-m-d\TH:i:s');
        $dt_end->setTimezone(new DateTimeZone('UTC'));
        $esc['datetime']['enddate'] = $dt_end->format('Y-m-d\TH:i:s');
    }
    $where = sqlSubset();

    // Query database

    $dbh = pdo_connect();

    $bin_name = $query_bin['bin'];

    // NOTICE: This query does not define a cut-off point

    $sql = 'select h.text, count(h.text) as cnt from ' . $bin_name . '_hashtags h inner join ' . $bin_name . '_tweets t on t.id = h.tweet_id '
            . $where . ' group by h.text order by count(h.text) desc';

    //$sql = 'select to_user, count(to_user) as cnt from ' . $bin_name . '_mentions m inner join ' . $bin_name . '_tweets t on t.id = m.tweet_id ' . $where .
     //   ' group by to_user order by count(to_user) desc';

    if (!is_null($limit)) {
        $sql .= ' limit ' . $limit;
    }
    $rec = $dbh->prepare($sql);
    $rec->execute();

    $list = array();

    while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
        $hashtag = $res['text'];
        $count = $res['cnt'];
        $element = array ( 'hashtag' => $hashtag,
                           'count' => $count );
        $list[] = $element;
    }

    return $list;

}

//----------------------------------------------------------------
// Top urls
//
// Retrieve information about the top urls in the time period
// between $dt_start and $dt_end (inclusive).
//
// $query_bin - the array produced by ~/analysis/common/functions.php
// $dt_start - must either be a DateTime object or NULL.
// $dt_end - must either be a DateTime object or NULL.

function urls_top($query_bin, $dt_start, $dt_end, $limit = NULL) {

    global $esc;

    // This function allows special arguments, similar to the TCAT front-end;
    // therefore we validate and process those arguments here
    if (!isset($_GET['dataset'])) {
        $_GET['dataset'] = $query_bin;
    }
    validate_all_variables();

    // Create WHERE clause to restrict to requested timestamp range
    // Convert to sqlSubset() format
    if (isset($dt_start) && isset($dt_end)) {
        $dt_start->setTimezone(new DateTimeZone('UTC'));
        $esc['datetime']['startdate'] = $dt_start->format('Y-m-d\TH:i:s');
        $dt_end->setTimezone(new DateTimeZone('UTC'));
        $esc['datetime']['enddate'] = $dt_end->format('Y-m-d\TH:i:s');
    }
    $where = sqlSubset();

    // Query database

    $dbh = pdo_connect();

    $bin_name = $query_bin['bin'];

    // NOTICE: This query does not define a cut-off point

    $sql = 'select u.url_followed as url, u.domain as `domain`, count(u.url_followed) as cnt from ' . $bin_name . '_urls u inner join ' . $bin_name . '_tweets t on t.id = u.tweet_id '
            . $where . ' group by u.url_followed order by count(u.url_followed) desc';

    //$sql = 'select to_user, count(to_user) as cnt from ' . $bin_name . '_mentions m inner join ' . $bin_name . '_tweets t on t.id = m.tweet_id ' . $where .
     //   ' group by to_user order by count(to_user) desc';

    if (!is_null($limit)) {
        $sql .= ' limit ' . $limit;
    }
    $rec = $dbh->prepare($sql);
    $rec->execute();

    $list = array();

    while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
        $url = $res['url'];
        $domain = $res['domain'];
        $count = $res['cnt'];
        $element = array ( 'url' => $url,
                           'domain' => $domain,
                           'count' => $count );
        $list[] = $element;
    }

    return $list;

}

//----------------------------------------------------------------
// Top mentions
//
// Retrieve information about the top mentions in the time period
// between $dt_start and $dt_end (inclusive).
//
// $query_bin - the array produced by ~/analysis/common/functions.php
// $dt_start - must either be a DateTime object or NULL.
// $dt_end - must either be a DateTime object or NULL.

function mentions_top($query_bin, $dt_start, $dt_end, $limit = NULL)
{

    global $esc;

    // This function allows special arguments, similar to the TCAT front-end;
    // therefore we validate and process those arguments here
    if (!isset($_GET['dataset'])) {
        $_GET['dataset'] = $query_bin;
    }
    validate_all_variables();

    // Create WHERE clause to restrict to requested timestamp range
    // Convert to sqlSubset() format
    if (isset($dt_start) && isset($dt_end)) {
        $dt_start->setTimezone(new DateTimeZone('UTC'));
        $esc['datetime']['startdate'] = $dt_start->format('Y-m-d\TH:i:s');
        $dt_end->setTimezone(new DateTimeZone('UTC'));
        $esc['datetime']['enddate'] = $dt_end->format('Y-m-d\TH:i:s');
    }
    $where = sqlSubset();

    // Query database

    $dbh = pdo_connect();

    $bin_name = $query_bin['bin'];

    // NOTICE: This query does not define a cut-off point

    $sql = 'select to_user, count(to_user) as cnt from ' . $bin_name . '_mentions m inner join ' . $bin_name . '_tweets t on t.id = m.tweet_id ' . $where .
           ' group by to_user order by count(to_user) desc';

    if (!is_null($limit)) {
        $sql .= ' limit ' . $limit;
    }
    $rec = $dbh->prepare($sql);
    $rec->execute();

    $list = array();

    while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
        $mention = $res['to_user'];
        $count = $res['cnt'];
        $element = array ( 'mention' => $mention,
                           'count' => $count );
        $list[] = $element;
    }

    return $list;

}

//----------------------------------------------------------------
// Top tweeters
//
// Retrieve information about the top tweeters in the time period
// between $dt_start and $dt_end (inclusive).
//
// $query_bin - the array produced by ~/analysis/common/functions.php
// $dt_start - must either be a DateTime object or NULL.
// $dt_end - must either be a DateTime object or NULL.

function tweeters_top($query_bin, $dt_start, $dt_end, $limit = NULL)
{

    global $esc;

    // This function allows special arguments, similar to the TCAT front-end;
    // therefore we validate and process those arguments here
    if (!isset($_GET['dataset'])) {
        $_GET['dataset'] = $query_bin;
    }
    validate_all_variables();

    // Create WHERE clause to restrict to requested timestamp range
    // Convert to sqlSubset() format
    if (isset($dt_start) && isset($dt_end)) {
        $dt_start->setTimezone(new DateTimeZone('UTC'));
        $esc['datetime']['startdate'] = $dt_start->format('Y-m-d\TH:i:s');
        $dt_end->setTimezone(new DateTimeZone('UTC'));
        $esc['datetime']['enddate'] = $dt_end->format('Y-m-d\TH:i:s');
    }
    $where = sqlSubset();

    // Query database

    $dbh = pdo_connect();

    $bin_name = $query_bin['bin'];

    // NOTICE: This query does not define a cut-off point

    $sql = 'select count(t.id) as cnt, t.from_user_name as from_user_name from ' . $bin_name . '_tweets t ' . $where .
           ' group by from_user_name order by count(t.id) desc';

    if (!is_null($limit)) {
        $sql .= ' limit ' . $limit;
    }
    $rec = $dbh->prepare($sql);
    $rec->execute();

    $list = array();

    while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
        $user = $res['from_user_name'];
        $count = $res['cnt'];
        $element = array ( 'user' => $user,
                           'count' => $count );
        $list[] = $element;
    }

    return $list;

}


//----------------------------------------------------------------
// Top retweets
//
// Retrieve information about the top mentions in the time period
// between $dt_start and $dt_end (inclusive).
//
// $query_bin - the array produced by ~/analysis/common/functions.php
// $dt_start - must either be a DateTime object or NULL.
// $dt_end - must either be a DateTime object or NULL.

function retweets_top($query_bin, $dt_start, $dt_end, $limit = NULL) {

    global $esc;

    // This function allows special arguments, similar to the TCAT front-end;
    // therefore we validate and process those arguments here
    if (!isset($_GET['dataset'])) {
        $_GET['dataset'] = $query_bin;
    }
    validate_all_variables();

    // Create WHERE clause to restrict to requested timestamp range
    // Convert to sqlSubset() format
    if (isset($dt_start) && isset($dt_end)) {
        $dt_start->setTimezone(new DateTimeZone('UTC'));
        $esc['datetime']['startdate'] = $dt_start->format('Y-m-d\TH:i:s');
        $dt_end->setTimezone(new DateTimeZone('UTC'));
        $esc['datetime']['enddate'] = $dt_end->format('Y-m-d\TH:i:s');
    }
    $where = sqlSubset();

    // Query database

    $dbh = pdo_connect();

    $bin_name = $query_bin['bin'];

    // NOTICE: This query does not define a cut-off point

    $sql = 'select T2.from_user_name as user, T2.text as text, t.text as rt_text, t.retweet_id as id, count(t.retweet_id) as cnt from ' . $bin_name . '_tweets t '.
           'left join ' . $bin_name . '_tweets T2 on t.retweet_id = T2.id ' . $where .
           ' and t.retweet_id is not null ' .
           ' group by t.retweet_id order by count(t.retweet_id) desc';
    if (!is_null($limit)) {
        $sql .= ' limit ' . $limit;
    }
    // DEBUG BEGIN
    file_put_contents("/tmp/debug.sql", $sql);
    // DEBUG END
    $rec = $dbh->prepare($sql);
    $rec->execute();

    $list = array();

    while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
        $id = $res['id'];
        $user = $res['user'];
        $count = $res['cnt'];
        $text = $res['text'];
        $rt_text = $res['rt_text'];
        $element = array ( 'id' => $id,
                           'user' => $user,
                           'text' => $text,
                           'rt_text' => $rt_text,
                           'count' => $count );
        $list[] = $element;
    }

    // We can now have tweets with user = NULL and text = NULL, if the original tweets was not in the dataset

    for ($n = 0; $n < count($list); $n++) {
        if (is_null($list[$n]['user'])) {
            // As Twitter user names cannot contain a ':' character, this regular expression should match the original username
            if (preg_match("/^RT @(.*?): (.*)$/", $list[$n]['rt_text'], $matches)) {
                if (isset($matches[1])) {
                   $list[$n]['user'] = $matches[1];
                }
                if (isset($matches[2])) {
                    $list[$n]['text'] = $matches[2];
                }
            }
        }
        unset($list[$n]['rt_text']);
    }

    return $list;

}

