<?php
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/http_util.php';

expected_query_parameters([]); // no query parameters permitted

switch (choose_mediatype(['application/json', 'text/html', 'text/plain'])) {
    case 'application/json':
        respond_with_json(['name' => 'DMI-TCAT API',
                           'version' => API_VERSION]);
        break;

    case 'text/html':
        html_begin("API", []);
        echo <<<END

            <ul>
              <li><a href="querybin.php">List query bins</a></li>
            </ul>

END;
        echo "<p>Version: ", API_VERSION, "</p>\n";
        html_end();
        break;

    case 'text/plain':
        echo "DMI-TCAT API (version: ", API_VERSION, ")\n";
        break;

    default:
        echo "DMI-TCAT API (version: ", API_VERSION, ")\n";
        echo "  Running this script from the command line does nothing useful!\n";
        echo "  Run \"php querybin.php --help\" instead.\n";
        exit(1);
        break;
}
