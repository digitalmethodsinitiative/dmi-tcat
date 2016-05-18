<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../analysis/common/config.php';
require_once __DIR__ . '/../analysis/common/functions.php';
require_once __DIR__ . '/_common.php';

// Performs one of these actions:
// - lists available query bins;
// - views details about a particular query bin;
// - listing tweets from a particular query bin; or
// - purges tweets from a particular query bin.

// Note: depends on "../analysis/common/functions.php" to populate $datasets.

//----------------------------------------------------------------
// List names of all query bins.
//
// Returns variable for JSON output or title+html for HTML output.

function do_list($response_mediatype) {
    global $datasets;

    switch ($response_mediatype) {
        case 'application/json':
            respond_with_json(array_keys($datasets));
            break;
        case 'text/html':
            html_begin("Query bins");

            $url = $_SERVER['SCRIPT_URI'];

            echo "    <p>This instance of TCAT has these query bins:</p>\n";
            echo "    <ul>\n";

            foreach (array_keys($datasets) as $name) {
                echo "        <li><a href=\"";
                echo htmlspecialchars($url . "/" . $name);
                echo "\">";
                echo htmlspecialchars($name);
                echo "</a></li>\n";
            }
            echo "    </ul>\n";
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
// View details about a specific query bin.
//
// Returns variable for JSON output or title+html for HTML output.

function do_view($querybin, $response_mediatype) {

    switch ($response_mediatype) {
        case 'application/json':
            respond_with_json($querybin);
            break;

        case 'text/html':
            html_begin("Query bin: " . $querybin['bin']);

            echo "<fieldset class=\"if_parameters\">";
            echo "<legend>Details</legend>\n";
            $keys = array_keys($querybin);
            sort($keys);
            echo "<table>\n";
            echo "<tbody>\n";
            foreach ($keys as $key) {
                echo "<tr><td class=\"tbl_head\">";
                echo htmlspecialchars($key);
                echo "</td><td>";
                if ($key != 'notweets') {
                    echo htmlspecialchars($querybin[$key]);
                } else {
                    echo "<a href=\"";
                    echo htmlspecialchars($querybin['bin']);
                    echo "/tweets\">";
                    echo htmlspecialchars($querybin[$key]);
                    echo "</a>";
                }
                echo "</td></tr>\n";
            }
            echo "</tbody>\n";
            echo "</table>\n";
            echo "</fieldset>\n";

            $url = $_SERVER['SCRIPT_NAME'];
            echo "<p><a href=\"";
            echo htmlspecialchars($url);
            echo "\">Back</a></p>";
            html_end();
            break;

        default:
            $keys = array_keys($querybin);
            sort($keys);
            foreach ($keys as $key) {
                print("$key: $querybin[$key]\n");
            }
            break;
    }
}

//----------------------------------------------------------------
// View tweets from a specific query bin.

function do_view_tweets($querybin) {

    $response_mediatype = choose_mediatype(['text/html',
        'text/csv', 'text/tab-separated-values']);

    switch ($response_mediatype) {

        case 'text/csv':
        case 'text/tab-separated-values':
            // TODO: add necessary extra parameters
            $ds = $querybin['bin'];
            header("Location: ../../../analysis/mod.export_tweets.php?dataset=$ds");
            exit(0);
            break;

        case 'text/html':
            html_begin("Query bin tweets: " . $querybin['bin']);
            echo "<p>Listing of tweets is not implemented yet.</p>\n";
            echo "<p>Coming soon: download in CSV or TSV.</p>\n";
            $url = $_SERVER['SCRIPT_NAME'];
            echo "<p><a href=\"";
            echo htmlspecialchars($url);
            echo "/";
            echo htmlspecialchars($querybin['bin']);
            echo "\">Back</a></p>";
            html_end();
            break;

        default:
            abort_with_error(NULL, "Not implemented yet");
            break;
    }
}

//----------------------------------------------------------------

function do_purge_tweets($querybin, $response_mediatype) {

    // TODO

    switch ($response_mediatype) {
        case 'application/json':
            respond_with_json(['status' => 0, 'message' => "Tweets purged"]);
            break;

        case 'text/html':
            html_begin("Purged tweets");
            print("Not implemented yet");
            print($querybin['bin']);
            html_end();
            break;

        default:
            abort_with_error(NULL, "Not implemented yet");
            break;
    }
}

//----------------------------------------------------------------
// Main

// Obtain parameters

if (PHP_SAPI != 'cli') {
    // Invoked by Web server

    expected_query_parameters([ 'action' ]);

    //----------------
    // Determine mode of operation ($querybin_name will be set or not)

    $components = explode('/', $_SERVER['PATH_INFO']);
    array_shift($components); // remove empty component due to leading slash

    switch (count($components)) {
        case 0:
            // No query bin specified: list all
            $mode = 'all';
            $querybin_name = NULL;
            break;
        case 1:
            // Query bin specified
            $mode = 'querybin';
            $querybin_name = $components[0];
            break;
        case 2:
            // Tweets
            $mode = 'querybin/tweets';
            $querybin_name = $components[0];
            if ($components[1] != 'tweets') {
                abort_with_error(404, "Not found: " . $_SERVER['PATH_INFO']);
            }
            break;
        default:
            abort_with_error(404, "Not found: " . $_SERVER['PATH_INFO']);
    }

    //----------------
    // Determine action

    if (array_key_exists('action', $_GET)) {
        $action = $_GET['action']; // explicit action
    } else {
        // Use default action for mode
        switch ($mode) {
            case 'all': $action = 'list'; break;
            case 'querybin': $action = 'view'; break;
            case 'querybin/tweets':
                $action = ($method == 'DELETE') ? 'purge-tweets' : 'view-tweets';
                break;
            default: abort_with_error(500, "Internal error: bad mode: $mode");
        }
    }

    // Check combination of mode, action and method

    $method = $_SERVER['REQUEST_METHOD'];
    if ($method != 'GET' && $method != 'POST' && $method != 'DELETE') {
        abort_with_error(405, "Method not allowed: $method");
    }

    $bad_combination = false;

    switch ($mode) {
        case 'all':
            if (! ($action == 'list' && $method == 'GET')) {
                $bad_combination = true;
            }
            break;
        case 'querybin':
            if (! (($action == 'view' && $method == 'GET') ||
                ($action == 'purge-tweets' && $method == 'DELETE'))) {
                $bad_combination = true;
            }
            break;
        case 'querybin/tweets':
            if (! (($action == 'view-tweets' && $method == 'GET') ||
                ($action == 'purge-tweets' && $method == 'DELETE') ||
                ($action == 'purge-tweets' && $method == 'POST')  )) {
                $bad_combination = true;
            }
            break;
        default:
            abort_with_error(500, "Internal error: unexpected mode: $mode");
    }

    if ($bad_combination) {
        abort_with_error(400, "Invalid combination: $mode, $action, $method");
    }

} else {
    // Invoked from command line

    // PHP's getopt is terrible, but it is always available.
    $skip_num = 1;
    $options = getopt("hpb:e:t",
        ['help', 'view-tweets', 'purge-tweets', 'begin:', 'end:']);
    if ($options != false) {

        foreach ($options as $opt => $optarg) {

            $skip_num++;
            if ($optarg != NULL) {
                $skip_num++;
            }
            switch ($opt) {
                case 'h':
                    echo <<<END
Usage: php $argv[0] [options] [queryBin]
Options:
  -t | --view-tweets   view tweets
  -p | --purge-tweets  purge tweets
  -b | --begin date    start date/time for tweet purging
  -e | --end date      end date/time for tweet purging
  -h | --help          show this help message
END;
                    exit(0);
                    break;

                case 't':
                case 'view-tweets':
                    $action = 'view-tweets';
                    break;

                case 'p':
                case 'purge-tweets':
                    $action = 'purge-tweets';
                    break;

                case 'b':
                case 'begin':
                    $date_begin = $optarg;
                    break;
                case 'e':
                case 'end':
                    $date_end = $optarg;
                    break;
            }
        }
    }

    $args = array_slice($argv, $skip_num);

    if (0 < count($args)) {
        // Check for options after getopt stops processing
        if ($args[0] == '--') {
            array_shift($args);
        }
        foreach ($args as $arg) {
            if ($arg[0] == '-') {
                fwrite(STDERR, "Usage error: unexpected option: $arg\n");
                exit(2);
            }
        }
    }

    if (count($args) == 0) {
        $querybin_name = NULL;
        $action = 'list';
    } else if (count($args) == 1) {
        $querybin_name = $args[0];
        if (! isset($action)) {
            $action = 'view';
        }
    } else {
        fwrite(STDERR, "Usage error: too many arguments\n");
        exit(2);
    }
}

//----------------
// Check parameters and set $querybin if needed

if (isset($querybin_name)) {
    if (! isset($datasets[$querybin_name])) {
        abort_with_error(404, "Unknown query bin: $querybin_name");
    }
    $querybin = $datasets[$querybin_name];
} else {
    $querybin = NULL;
}

//----------------
// Perform action and produce response

$response_mediatype = choose_mediatype(array('application/json', 'text/html'));

switch ($action) {
    case 'list':
        do_list($response_mediatype);
        break;
    case 'view':
        do_view($querybin, $response_mediatype);
        break;
    case 'view-tweets':
        do_view_tweets($querybin);
        break;
    case 'purge-tweets':
        do_purge_tweets($querybin, $response_mediatype);
        break;
    default:
        abort_with_error(500, "Internal error: unexpected action: $action");
}

exit(0);

?>
