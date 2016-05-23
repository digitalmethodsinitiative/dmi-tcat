<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../analysis/common/config.php';
require_once __DIR__ . '/../analysis/common/functions.php';
require_once __DIR__ . '/_common.php';

$response_mediatype = choose_mediatype(array('text/html'));

switch ($response_mediatype) {
    case 'text/html':
        html_begin("API");
        echo <<<END
    <ul>
      <li><a href="querybin.php">List query bins</a></li>
    </ul>
END;
        html_end();
        break;
    default:
        echo "DMI-TCAT API\n";
        break;
}