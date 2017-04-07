<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../common/functions.php';
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

// Note: depends on "../analysis/common/functions.php" to populate $datasets.

//----------------------------------------------------------------
// Display important facts about this server
//
// Returns variable for JSON output or title+html for HTML output.

function do_server_info()
{
    global $datasets; // from require_once "../analysis/common/functions.php"

    if (!isset($datasets)) {
        $datasets = []; // database tables not yet initialized
    }

    $server_roles = unserialize(CAPTUREROLES);

    $response_mediatype = choose_mediatype(['application/json', 'text/html',
                                            'text/plain']);

    switch ($response_mediatype) {
        case 'application/json':
            $obj = array( 'roles' => $server_roles );
            respond_with_json($obj);
            // respond_with_json(array_keys($datasets));
            break;
        case 'text/html':
            html_begin("Server information", [["Server information"]]);

            if (count($server_roles) == 0) {
                echo "    <p>This TCAT instance is currently not tracking anything.</p>\n";
            } else {
                echo "    <p>Tracking roles:</p>\n";
                echo "    <ul>\n";

                foreach ($server_roles as $role) {
                    echo "<li>$role</li>\n";
                }

                echo "    </ul>\n";
            }

            html_end();
            break;

        case 'text/plain':
        default:
            print "SERVER ROLES: ";
            if (count($server_roles) == 0) {
                print "none";
            } else {
                foreach ($server_roles as $role) {
                    print($role . " ");
                }
            }
            print "\n";
            break;
    }
}

//----------------------------------------------------------------
// Display ratelimit information for this server
//
// Returns variable for JSON output or title+html for HTML output.

function do_ratelimit_info()
{
    global $datasets; // from require_once "../analysis/common/functions.php"

    if (!isset($datasets)) {
        $datasets = []; // database tables not yet initialized
    }

    $dbh = pdo_connect();

    $days_back = 30;

    $limits = array();

    for ($d = 1; $d <= $days_back; $d++) {
        $sql = "select sum(tweets) as sum from tcat_error_ratelimit where dayofyear(start) = dayofyear(date_sub(now(), interval $d day)) group by dayofyear(start)";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        if ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $limits[$days_back - $d] = $res['sum'];
        } else {
            // TODO: issue warning?
            $limits[$days_back - $d] = 0;
        }
    }

    $response_mediatype = choose_mediatype(['application/json', 'text/html',
                                            'text/plain']);

    switch ($response_mediatype) {
        case 'application/json':
            $obj = array( 'ratelimited-day-descending' => $limits );
            respond_with_json($obj);
            break;
        case 'text/html':
            html_begin("Ratelimit information", [["Ratelimit information"]]);

            // TODO: charts, graphs, something ..

            echo "    <ul>\n";

            foreach ($limits as $limit) {
                echo "<li>$limit</li>\n";
            }

            echo "    </ul>\n";

            html_end();
            break;

        case 'text/plain':
        default:
            // TODO
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

    $aspect = NULL;

    if (PHP_SAPI != 'cli') {

        // Invoked by Web server

        $components = explode('/', $_SERVER['PATH_INFO']);
        assert($components[0] === ''); // since PATH_INFO started with a "/"
        array_shift($components); // remove empty component from first "/"

        switch (count($components)) {
            case 0:
                // No query bin specified: list all (e.g. /api/querybin.php)
                break;
            case 1:
                // Some server aspect (e.g. /api/server.php/ratelimits)
                $aspect = $components[0];
                break;
            default:
                abort_with_error(404, "Not found: " . $_SERVER['PATH_INFO']);
        }

        // Determine action

        if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
            array_key_exists('action', $_GET)
        ) {
            $action = $_GET['action'];  // explicit action
        } else if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
            array_key_exists('action', $_POST)
        ) {
            $action = $_POST['action']; // explicit action
        } else {
            if (is_null($aspect)) {
                $action = 'server-info';     // implicit action
            } else if ($aspect == 'ratelimits') {
                $action = 'ratelimit-info';  // implicit action
            } else {
                abort_with_error(400, "Invalid request");
            }
        }

    } else {
        // Invoked from command line

        // PHP's getopt is terrible, but it is always available.
        $skip_num = 1;
        $options = getopt("h", ['help']);
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

                        echo <<<END
Usage: php $script_name [options]
Options:
  -h | --help            show this help message

END;
                        exit(0);
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

        // Derive implied action if no explicit action was provided

        if (!isset($action)) {
            $action = 'server-info';
        }

    }

    //----------------
    // Check/process parameters

    //----------------
    // Perform action and produce response

    switch ($action) {
        case 'server-info':
            do_server_info();
            break;
        case 'ratelimit-info':
            do_ratelimit_info();
            break;
        default:
            abort_with_error(500, "Internal error: unexpected action: $action");
    }

    exit(0);
}

main();
