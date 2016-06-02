<?php
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/http_util.php';

switch (choose_mediatype(['text/html'])) {
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
    default:
        echo "DMI-TCAT API (version: ", API_VERSION, ")\n";
        echo "  Running this script from the command line does nothing useful!\n";
        echo "  Run \"php querybin.php --help\" instead.\n";
        exit(1);
        break;
}
