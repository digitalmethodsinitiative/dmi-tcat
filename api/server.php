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

    // TODO: this is experimental, work-in-progress code. */

    $days_back = 30;

    $limits = array();

    for ($d = 1; $d <= $days_back; $d++) {
        // TODO: handle year loop
        $sql = "select count(*) as sum from tcat_error_ratelimit R1 join tcat_error_ratelimit R2 on R2.id = R1.id + 1 where R1.tweets != R2.tweets ".
               "and year(R1.start) = year(now()) and dayofyear(R1.start) = dayofyear(date_sub(now(), interval $d day)) group by dayofyear(R1.start)";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        if ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $limits[$days_back - $d] = sprintf("%.3f", $res['sum'] / 1440);
        } else {
            // TODO: issue warning?
            $limits[$days_back - $d] = 0;
        }
    }

    // Most used keywords

    $keywords = array();

        // TODO: handle year loop
        //$sql = "select TQP.phrase, count(*) as sum from tcat_captured_phrases TCP inner join tcat_query_phrases TQP on TCP.phrase_id = TQP.id " .
        //       "where year(created_at) = year(now()) and dayofyear(created_at) = dayofyear(date_sub(now(), interval $d day)) group by dayofyear(created_at), " .
        //       "phrase_id order by sum desc limit 30;
    $sql = "select TQP.phrase as phrase, count(*) as sum from tcat_captured_phrases TCP inner join tcat_query_phrases TQP on TCP.phrase_id = TQP.id " .
           "where year(created_at) = year(now()) and dayofyear(created_at) >= dayofyear(date_sub(now(), interval 30 day)) group by " .
           "phrase_id order by sum desc limit 50";
    $rec = $dbh->prepare($sql);
    $rec->execute();
    while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
        $keywords[$res['phrase']] = $res['sum'];
    }

    $response_mediatype = choose_mediatype(['application/json', 'text/html',
                                            'text/plain']);

    switch ($response_mediatype) {
        case 'application/json':
            $obj = array( 'ratelimited-day-descending' => $limits,
                          'top-keywords' => $keywords);
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

            echo "    <ul>\n";
            foreach ($keywords as $keyword => $sum) {
                echo "<li>$keyword: $sum</li>\n";
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
// Display rate information for this server
//
// Returns variable for JSON output or title+html for HTML output.

/**
 * Print historical capture rate information.
 *
 * Based on the tcat_captured_phrases table, and output format is
 * figured out from the HTTP request.
 *
 * @param int  $days_back           Number of days of history to report.
 * @param bool $report_missing_days Explicitly report missing days.
 *
 * @return void
 */
function do_rate_info($days_back = 30, $report_missing_days = true)
{
    global $datasets; // from require_once "../analysis/common/functions.php"

    if (!isset($datasets)) {
        $datasets = []; // database tables not yet initialized
    }

    $response_mediatype = choose_mediatype(
        ['application/json',
         'text/html',
         'text/plain']
    );

    $dbh = pdo_connect();
    $sql = 'SELECT date(t.created_at) AS date, count(*) AS count FROM (SELECT * FROM tcat_captured_phrases WHERE created_at >= CURDATE() - INTERVAL :days_back DAY) AS t GROUP BY date(t.created_at)';
    $rec = $dbh->prepare($sql);
    $rec->bindValue(':days_back', $days_back, PDO::PARAM_INT);
    $rec->execute();
    while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
        $rates[$res['date']] = +$res['count'];
    }

    if ($report_missing_days) {
        // Create an array of dates null value, and left merge it with the
        // rates from database. This way also empty days will be included
        // in the results and usefully reported.
        $dates = array();
        foreach (iterator_to_array(
            new DatePeriod(
                new DateTime($days_back * -1 . ' days'),
                new DateInterval('P1D'),
                new DateTime
            )
        ) as $date) {
            $dates[$date->format('Y-m-d')] = null;
        }
        $rates = array_merge($dates, $rates);
    }

    switch ($response_mediatype) {
        case 'application/json':
            $obj = ['days_back' => $days_back, 'counts' => $rates];
            respond_with_json($obj);
            break;
        case 'text/html':
            echo "<table>";
            echo "<tr><th>date</th><th>count</th></tr>";
            foreach ($rates as $date => $count) {
                echo "<tr><td class='date'>$date</td><td class='count'>$count</tr>";
            }
            echo "</table>";
            html_end();
            break;
        case 'text/plain':
        default:
            echo "date" . "," . "count" . PHP_EOL;
            foreach ($rates as $date => $count) {
                echo $date . "," . $count . PHP_EOL;
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
            } else if ($aspect == 'rateinfo') {
                $action = 'rate-info';
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
        case 'rate-info':
            do_rate_info();
            break;
        default:
            abort_with_error(500, "Internal error: unexpected action: $action");
    }

    exit(0);
}

main();
