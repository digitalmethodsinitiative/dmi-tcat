<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../analysis/common/config.php';
try {
    require_once __DIR__ . '/../analysis/common/functions.php';
} catch (Exception $e) {
    print("caught: $e\n");
}

require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/http_util.php';
require_once __DIR__ . '/lib/dt_util.php';
require_once __DIR__ . '/lib/tcat_util.php';

// Performs one of these actions:
// - list available query bins;
// - info about a particular query bin;
// - view tweets from a particular query bin; or
// - purge tweets from a particular query bin.

// Note: depends on "../analysis/common/functions.php" to populate $datasets.

//----------------------------------------------------------------
// List names of all query bins.
//
// Returns variable for JSON output or title+html for HTML output.

function do_list_bins()
{
    global $datasets; // from require_once "../analysis/common/functions.php"

    if (!isset($datasets)) {
        $datasets = []; // database tables not yet initialized
    }

    $response_mediatype = choose_mediatype(['application/json', 'text/html']);

    switch ($response_mediatype) {
        case 'application/json':
            respond_with_json(array_keys($datasets));
            break;
        case 'text/html':
            html_begin("Query bins", [["Query Bins"]]);

            if (count($datasets) == 0) {
                echo "    <p>This TCAT instance has no query bins.</p>\n";
            } else {
                echo "    <p>Query bins:</p>\n";
                echo "    <ul>\n";

                $url = $_SERVER['SCRIPT_URI'];

                foreach (array_keys($datasets) as $name) {
                    echo "        <li><a href=\"";
                    echo htmlspecialchars($url . '/' . $name);
                    echo "\">";
                    echo htmlspecialchars($name);
                    echo "</a></li>\n";
                }
                echo "    </ul>\n";
            }

            html_end();
            break;
        default:
            foreach (array_keys($datasets) as $name) {
                print($name . "\n");
            }
            break;
    }
}

//----------------------------------------------------------------
// Information about a specific query bin.
//
// Returns variable for JSON output or title+html for HTML output.

function do_info_bin(array $querybin)
{
    global $api_timezone;

    $response_mediatype = choose_mediatype(['application/json', 'text/html']);

    switch ($response_mediatype) {
        case 'application/json':
            respond_with_json($querybin);
            break;

        case 'text/html':

            $script_url = $_SERVER['SCRIPT_URL'];
            $components = explode('/', $script_url);
            array_pop($components);
            $query_bin_list = implode('/', $components);

            html_begin("Query bin: " . $querybin['bin'],
                [
                    ["Query Bins", $query_bin_list],
                    [$querybin['bin']]
                ]);

            echo "<fieldset class=\"if_parameters\">";
            echo "<legend>Query bin information</legend>\n";
            $keys = array_keys($querybin);
            sort($keys);
            echo "<table>\n";
            echo "<tbody>\n";
            foreach ($keys as $key) {
                echo "<tr><th>";
                echo htmlspecialchars($key);
                echo "</th><td>";

                switch ($key) {
                    case 'maxtime':
                    case 'mintime':
                        $t = $querybin[$key];
                        if (isset($t)) {
                            echo dt_format_html(dt_from_utc($t), $api_timezone);
                        } else {
                            echo '-';
                        }
                        break;
                    default:
                        echo htmlspecialchars($querybin[$key]);
                        break;
                }
                echo "</td></tr>\n";
            }
            echo "</tbody>\n";
            echo "</table>\n";

            echo "<p><a title=\"View tweets\" href=\"";
            echo htmlspecialchars($querybin['bin']);
            echo "/tweets\">Tweets</a></p>";

            echo "</fieldset>\n";

            html_end();
            break;

        default:
            print("Query bin: {$querybin['bin']}\n");

            $keys = array_keys($querybin);
            sort($keys);
            foreach ($keys as $key) {
                switch ($key) {
                    case 'bin':
                        // skip: already displayed above
                        break;
                    case 'maxtime':
                    case 'mintime':
                        print("  $key: ");
                        $t = $querybin[$key];
                        if (isset($t)) {
                            print(dt_format_text(dt_from_utc($t), $api_timezone));
                        } else {
                            print('-');
                        }
                        print("\n");
                        break;
                    default:
                        print("  $key: $querybin[$key]\n");
                        break;
                }
            }
            break;
    }
}

//----------------------------------------------------------------
// View tweets from a specific query bin.

function do_view_tweets(array $querybin, $dt_start, $dt_end)
{
    global $api_timezone;

    // Get information about tweets from the query bin

    $info = tweet_info($querybin, $dt_start, $dt_end);

    // Show result

    $response_mediatype = choose_mediatype(['text/html',
        'text/csv', 'text/tab-separated-values']);

    switch ($response_mediatype) {

        case 'text/csv':
        case 'text/tab-separated-values':
            // TODO: experimental feature: needs fixing
            $ds = $querybin['bin'];

            $depth = count(explode('/', $_SERVER['PATH_INFO']));
            $rel = str_repeat('../', $depth);

            header("Location: {$rel}analysis/mod.export_tweets.php?dataset=$ds");
            exit(0);
            break;

        case 'text/html':

            $script_url = $_SERVER['SCRIPT_URL'];
            $components = explode('/', $script_url);
            array_pop($components);
            $query_bin_info = implode('/', $components);
            array_pop($components);
            $query_bin_list = implode('/', $components);

            html_begin("Query bin tweets: {$querybin['bin']}",
                [
                    ["Query Bins", $query_bin_list],
                    [$querybin['bin'], $query_bin_info],
                    ["View Tweets"]
                ]);

            $total_num = $querybin['notweets'];

            // Selection times as text for start/end text field values

            $val_A = (isset($dt_start)) ? dt_format_text($dt_start, $api_timezone) : "";
            $val_B = (isset($dt_end)) ? dt_format_text($dt_end, $api_timezone) : "";

            if (0 < $total_num) {
                // Has tweets: show form to select tweets

                $min_t = dt_from_utc($querybin['mintime']);
                $max_t = dt_from_utc($querybin['maxtime']);

                // First/last tweet times as text for placeholders

                $t1_text = dt_format_text($min_t, $api_timezone);
                $t2_text = dt_format_text($max_t, $api_timezone);

                // First/last tweet times as HTML for description

                $t1_html = dt_format_html($min_t, $api_timezone);
                $t2_html = dt_format_html($max_t, $api_timezone);

                echo <<<END

<p>This query bin contains a total of {$total_num} tweets
from {$t1_html} to {$t2_html}.</p>

<form method="GET">
  <table>
    <tr>
      <th style="white-space: nowrap">
        <label for="startdate">Start time</label></th>
      <td><input type="text" id="startdate" name="startdate"
           placeholder="$t1_text" value="$val_A"/></td>
    </tr>
    <tr>
      <th style="white-space: nowrap">
        <label for="enddate">End time</label></th>
      <td><input type="text" id="enddate" name="enddate"
          placeholder="$t2_text" value="$val_B"/></td>
    </tr>
    <tr>
      <td></td>
      <td><input type="submit" value="Update selection"/></td>
    <tr>

<tr>
<td></td>
<td>

<p>
END;

                if (isset($api_timezone) && $api_timezone !== '') {
                    // Default timezone available
                    echo <<<END
Start and end times may include an explicit timezone.
If there is no timezone, the time will be interpreted to be in
the <em>{$api_timezone}</em> timezone.
END;
                } else {
                    // No default timezone
                    echo <<<END
Start and end times must include an explicit timezone.
END;
                }

                echo <<<END

Acceptable timezone values are "Z", "UTC" or an offset in the form of
[+-]HH:MM.

No entered values means the time of the first or last tweet.  Partial
times are also permitted: only the year is mandatory, the remaining
components will be automatically added. Times are inclusive (i.e. any
tweets with timestamps equal to those times will be included in the
selection).</p>

</td>
</tr>

  </table>
</form>
END;
                $title = "Selected tweets";
            } else {
                // No tweets: do not show form to select tweets
                echo '<p>This query bin contains no tweets.</p>';
                $title = "Tweets";
            }

            // Show view of selected tweets (or no tweets)

            // Selection times as HTML for display

            $hB = (isset($dt_end)) ? dt_format_html($dt_end, $api_timezone) : "";

            if (isset($dt_start)) {
                $hA = dt_format_html($dt_start, $api_timezone);

                if (isset($dt_end)) {
                    $dt_desc_html = "From $hA to $hB";
                } else {
                    $dt_desc_html = "From $hA to last tweet";
                }
            } else {
                if (isset($dt_end)) {
                    $dt_desc_html = "From first tweet to $hB";
                } else {
                    $dt_desc_html = "All tweets from first to last";
                }
            }

            echo <<<END
<fieldset>
  <legend>$title</legend>

  <table>
    <tr><th>Selected range</th><td>$dt_desc_html</td></tr>
    <tr><th>Number of tweets</th><td>{$info['tweets']} of $total_num</td></tr>
    <tr><th>Hashtags</th><td>{$info['hashtags']}</td></tr>
    <tr><th>Mentions</th><td>{$info['mentions']}</td></tr>
    <tr><th>URLs</th><td>{$info['urls']}</td></tr>
    <tr><th>Media</th><td>{$info['media']}</td></tr>
    <tr><th>Places</th><td>{$info['places']}</td></tr>
    <tr><th>Withheld</th><td>{$info['withheld']}</td></tr>
  </table>
END;

            if (0 < $total_num) {
                // Has tweets: show button to purge tweets
                echo <<<END
    <div id="purge-button">
    <form method="POST">
        <input type="hidden" name="action" value="purge-tweets"/>
        <input type="hidden" name="startdate" value="$val_A"/>
        <input type="hidden" name="enddate" value="$val_B"/>
        <input type="submit" value="Purge tweets"/>
    </form>
    </div>
END;
            }

            // End of view of tweets

            echo "</fieldset>\n";

            // End of page

            html_end();
            break;

        default:

            $from = (isset($dt_start)) ? dt_format_text($dt_start, $api_timezone) : "";
            $to = (isset($dt_end)) ? dt_format_text($dt_end, $api_timezone) : "";

            if (isset($dt_start)) {
                if (isset($dt_end)) {
                    $dt_desc = "from $from to $to";
                } else {
                    $dt_desc = "from $from to last tweet";
                }
            } else {
                if (isset($dt_end)) {
                    $dt_desc = "first tweet up to $to";
                } else {
                    $dt_desc = "all tweets";
                }
            }

            $total_num = $querybin['notweets'];

            if (0 < $total_num) {
                // Has tweets
                $min_t = dt_from_utc($querybin['mintime']);
                $max_t = dt_from_utc($querybin['maxtime']);

                $t1_text = dt_format_text($min_t, $api_timezone);
                $t2_text = dt_format_text($max_t, $api_timezone);

                echo <<<END
Query bin: {$querybin['bin']}
  Earliest tweet: {$t1_text}
    Latest tweet: {$t2_text}
  Tweets: ($dt_desc)
    Number of tweets: {$info['tweets']} of {$querybin['notweets']}
    Hashtags: {$info['hashtags']}
    Mentions: {$info['mentions']}
    URLs: {$info['urls']}
    Media: {$info['media']}
    Places: {$info['places']}
    Withheld: {$info['withheld']}

END;
            } else {
                // No tweets
                echo "Query bin: {$querybin['bin']}\n";
                echo "  No tweets\n";
            }

            break;
    }
}

//----------------------------------------------------------------

function do_purge_tweets(array $querybin, $dt_start, $dt_end)
{
    // Purge tweets

    $num_del = tweet_purge($querybin, $dt_start, $dt_end);

    // Show result

    $response_mediatype = choose_mediatype(['application/json', 'text/html']);

    switch ($response_mediatype) {
        case 'application/json':
            respond_with_json(['purge-tweets' => $num_del]);
            break;

        case 'text/html':
            $script_url = $_SERVER['SCRIPT_URL'];
            $components = explode('/', $script_url);
            array_pop($components);
            $query_bin_info = implode('/', $components);
            array_pop($components);
            $query_bin_list = implode('/', $components);

            html_begin("Query bin tweets: {$querybin['bin']}: purge",
                [
                    ["Query Bins", $query_bin_list],
                    [$querybin['bin'], $query_bin_info],
                    ["Purge"]
                ]);

            $hB = (isset($dt_end)) ? dt_format_html($dt_end) : "";

            if (isset($dt_start)) {
                $hA = dt_format_html($dt_start);
                if (isset($dt_end)) {
                    $dt_desc_html = "tweets from $hA to $hB";
                } else {
                    $dt_desc_html = "tweets from $hA to last tweet";
                }
            } else {
                if (isset($dt_end)) {
                    $dt_desc_html = "tweets from first tweet to $hB";
                } else {
                    $dt_desc_html = "all tweets";
                }
            }

            echo <<<END

<p>Purged {$dt_desc_html}.</p>

<p>Number of tweets deleted: {$num_del['tweets']}</p>

END;

            html_end();
            break;

        default:
            print("{$num_del['tweets']} tweets purged from {$querybin['bin']}\n");
            foreach ($num_del as $name => $num) {
                print("  Table $name: $num rows deleted\n");
            }

            break;
    }
}

//----------------------------------------------------------------
// Main function
//
// This is a function to avoid unexpected clashes with global variables.

function main()
{
    global $argv;
    global $datasets; // from require_once "../analysis/common/functions.php"
    global $api_timezone; // from reqire_once "./lib/common.php"

    //----------------
    // Obtain parameters

    if (PHP_SAPI != 'cli') {
        // Invoked by Web server

        expected_query_parameters(['action', 'startdate', 'enddate']);

        // Determine resource ($querybin_name will be set or not)

        $components = explode('/', $_SERVER['PATH_INFO']);
        assert($components[0] === ''); // since PATH_INFO started with a "/"
        array_shift($components); // remove empty component from first "/"

        switch (count($components)) {
            case 0:
                // No query bin specified: list all (e.g. /api/querybin.php)
                $resource = 'all';
                $querybin_name = NULL;
                break;
            case 1:
                // Query bin (e.g. /api/querybin.php/foobar)
                $resource = 'querybin';
                $querybin_name = $components[0];
                break;
            case 2:
                // Query bin's tweets (e.g. /api/querybin.php/foobar/tweets)
                if ($components[1] != 'tweets') {
                    abort_with_error(404, "Not found: " . $_SERVER['PATH_INFO']);
                }
                $resource = 'querybin/tweets';
                $querybin_name = $components[0];
                break;
            default:
                $resource = NULL;
                abort_with_error(404, "Not found: " . $_SERVER['PATH_INFO']);
        }

        // Determine action

        if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
            array_key_exists('action', $_GET)
        ) {
            $action = $_GET['action']; // explicit action
        } else if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
            array_key_exists('action', $_POST)
        ) {
            $action = $_POST['action']; // explicit action
        } else {
            // Use default action for resource
            switch ($resource) {
                case 'all':
                    $action = 'list';
                    break;
                case 'querybin':
                    $action = 'info-bin';
                    break;
                case 'querybin/tweets':
                    $action = 'view-tweets';
                    break;
                default:
                    $action = NULL;
                    abort_with_error(500, "Internal error: bad rsrc: $resource");
            }
        }

        // Check combination of resource, action and method

        $method = $_SERVER['REQUEST_METHOD'];
        if ($method != 'GET' && $method != 'POST' && $method != 'DELETE') {
            abort_with_error(405, "Method not allowed: $method");
        }

        $bad_combination = false;

        switch ($resource) {
            case 'all':
                if (!($action === 'list' && $method === 'GET')) {
                    $bad_combination = true;
                }
                break;
            case 'querybin':
                if (!($action === 'info-bin' && $method === 'GET')) {
                    $bad_combination = true;
                }
                break;
            case 'querybin/tweets':
                if (!(($action === 'view-tweets' && $method === 'GET') ||
                    ($action === 'purge-tweets' && $method === 'DELETE') ||
                    ($action === 'purge-tweets' && $method === 'POST'))
                ) {
                    $bad_combination = true;
                }
                break;
            default:
                abort_with_error(500, "Internal error: bad rsrc: $resource");
        }

        if ($bad_combination) {
            abort_with_error(400, "Invalid: $resource, $action, $method");
        }

        // Get parameters

        $str_start = $_REQUEST['startdate'];
        $str_end = $_REQUEST['enddate'];

    } else {
        // Invoked from command line

        // PHP's getopt is terrible, but it is always available.
        $skip_num = 1;
        $options = getopt("hlitps:e:",
            ['help',
                'list',
                'info',
                'view-tweets', 'purge-tweets', 'start:', 'end:']);
        if ($options != false) {

            foreach ($options as $opt => $optarg) {

                $skip_num++;
                if ($optarg != NULL) {
                    $skip_num++;
                }
                switch ($opt) {
                    case 'h':
                    case 'help':
                        $script_name = basename($argv[0]);

                        $dtz = ($api_timezone != NULL) ? $api_timezone : "UTC";

                        echo <<<END
Usage: php $script_name [options] [queryBinName]
Options:
  -l | --list            list names of all query bins (default without name)
  -i | --info            show information about query bin (default with name)
  -t | --view-tweets     view information about tweets in named query bin
  -p | --purge-tweets    purge tweets in named query bin

  -s | --start tm        start time for tweet viewing/purging
  -e | --end tm          end time for tweet viewing/purging

  -h | --help            show this help message

Format for tm: yyyy-mm-ddThh:mm:ss[tz]
           tz: 'Z', 'UTC' or [+|-]HH:MM (default: $dtz)

END;
                        exit(0);
                        break;

                    case 'l':
                    case 'list':
                        if (isset($action)) {
                            fwrite(STDERR, "Usage error: multipe actions\n");
                            exit(2);
                        }
                        $action = 'list';
                        break;

                    case 'i':
                    case 'info':
                        if (isset($action)) {
                            fwrite(STDERR, "Usage error: multipe actions\n");
                            exit(2);
                        }
                        $action = 'info-bin';
                        break;

                    case 't':
                    case 'view-tweets':
                        if (isset($action)) {
                            fwrite(STDERR, "Usage error: multipe actions\n");
                            exit(2);
                        }
                        $action = 'view-tweets';
                        break;

                    case 'p':
                    case 'purge-tweets':
                        if (isset($action)) {
                            fwrite(STDERR, "Usage error: multipe actions\n");
                            exit(2);
                        }
                        $action = 'purge-tweets';
                        break;

                    case 's':
                    case 'start':
                        $str_start = $optarg;
                        break;
                    case 'e':
                    case 'end':
                        $str_end = $optarg;
                        break;
                }
            }
        }

        $args = array_slice($argv, $skip_num);

        if (0 < count($args)) {
            // Compensate for PHP's getopt's incomplete processing
            // Check for options after getopt stops processing
            if ($args[0] === '--') {
                array_shift($args);
            }
            foreach ($args as $arg) {
                if ($arg[0] === '-') {
                    fwrite(STDERR, "Usage error: options not allowed after arguments: $arg\n");
                    exit(2);
                }
            }
        }

        // Arguments

        if (count($args) == 0) {
            $querybin_name = NULL;
        } else if (count($args) == 1) {
            $querybin_name = $args[0];
        } else {
            fwrite(STDERR, "Usage error: too many arguments\n");
            exit(2);
        }

        // Derive implied action if no explicit action was provided

        if (!isset($action)) {
            if (isset($querybin_name)) {
                $action = 'info-bin';
            } else {
                $action = 'list';
            }
        }

        // Check for invalid option/argument combinations

        if ($action !== 'list') {
            // All these actions require a query bin to be specified
            if (!isset($querybin_name)) {
                fwrite(STDERR, "Usage error: missing query bin name\n");
                exit(2);
            }
        } else {
            if (isset($querybin_name)) {
                fwrite(STDERR, "Usage error: query bin name not required\n");
                exit(2);
            }
        }

        if ($action === 'list' || $action === 'info-bin') {
            // These actions do not use start/end time
            if (isset($str_start) || isset($str_end)) {
                fwrite(STDERR, "Usage error: start/end time not required\n");
                exit(2);
            }
        }
    }

    //----------------
    // Check/process parameters

    // Check and set $querybin (if needed)

    if (isset($querybin_name)) {
        if (!isset($datasets[$querybin_name])) {
            abort_with_error(404, "Unknown query bin: $querybin_name");
        }
        $querybin = $datasets[$querybin_name];
    } else {
        $querybin = NULL;
    }

    // Check start and end time
    //
    // Code convention:
    //   $str_start and $str_end are string values (or null).
    //   $dt_start and $dt_end are DateTime objects (or null).

    $dt_start = NULL;
    if (isset($str_start) && $str_start !== '') {
        try {
            $dt_start = dt_parse($str_start, false, $api_timezone);
        } catch (DtException $e) {
            $m = $e->getMessage();
            abort_with_error(400, "Bad start time ($m): {$e->dt_str}");
        }
    }

    $dt_end = NULL;
    if (isset($str_end) && $str_end !== '') {
        try {
            $dt_end = dt_parse($str_end, true, $api_timezone);
        } catch (DtException $e) {
            $m = $e->getMessage();
            abort_with_error(400, "Bad end time ($m): {$e->dt_str}");
        }
    }

    if (isset($dt_start) && isset($dt_end)) {
        if ($dt_end < $dt_start) {
            abort_with_error(400, "End time is before start time.");
        }
    }

    //----------------
    // Perform action and produce response

    switch ($action) {
        case 'list':
            do_list_bins();
            break;
        case 'info-bin':
            do_info_bin($querybin);
            break;
        case 'view-tweets':
            do_view_tweets($querybin, $dt_start, $dt_end);
            break;
        case 'purge-tweets':
            do_purge_tweets($querybin, $dt_start, $dt_end);
            break;
        default:
            abort_with_error(500, "Internal error: unexpected action: $action");
    }

    exit(0);
}

main();
