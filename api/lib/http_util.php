<?php
// Common functions for the API scripts.

//----------------------------------------------------------------
// Chooses a media type for the HTTP response.
//
// The $mediatypes arguments is an array of media types (strings) that can be
// produced, with the first one being the media type the server prefers to
// produce.
//
// The HTTP Accept request-header is examined to determine the media type for
// the HTTP response. One of the values from the supplied $mediatypes is
// returned. The first item in $mediatypes is returned if "*/*" in the HTTP
// Accept request-header is matched or there is no HTTP Accept request-header.
// After examining the Accept request-header and no suitable media type is
// found, an error page with HTTP status of 406 (not acceptable) is generated
// and the script is exited (i.e. this function does not return).
//
// If the script is not invoked by a Web server, NULL is returned.

function choose_mediatype(array $mediatypes)
{
    if (PHP_SAPI === 'cli') {
        return NULL;
    }

    if (!empty($mediatypes)) {
        if (!array_key_exists('HTTP_ACCEPT', $_SERVER)) {
            return $mediatypes[0]; // no Accept request-header
        }

        // Parse Accept request-header

        $accepts = array();
        $parts = preg_split('/\s*,\s*/', $_SERVER['HTTP_ACCEPT']);
        foreach ($parts as $pos => $t) {
            if (preg_match(",^(\S+)\s*;\s*(?:q|level)=([0-9\.]+),i", $t, $M)) {
                $accepts[] = ['pos' => $pos, 'type' => $M[1],
                    'q' => (double)$M[2]];
            } else {
                $accepts[] = ['pos' => $pos, 'type' => $t, 'q' => 1.0];
            }
        }

        // Sort into preferred order by "q" and position

        usort($accepts, function ($a, $b) {
            if ($a['q'] < $b['q']) {
                return 1;
            } else if ($b['q'] < $a['q']) {
                return -1;
            } else {
                if ($a['pos'] < $b['pos']) {
                    return -1;
                } else if ($b['pos'] < $a['pos']) {
                    return 1;
                } else {
                    return 0;
                }
            }
        });

        // Find supported media type according to preferred order

        foreach ($accepts as $fmt) {
            if (strcmp($fmt['type'], '*/*') != 0) {
                if (in_array($fmt['type'], $mediatypes)) {
                    return $fmt['type'];
                }
            } else {
                return $mediatypes[0]; // match found
            }
        }
    }

    // No match found: produce HTTP response with error status

    abort_with_error(406, "no acceptable media type found");
    return NULL;
}

//----------------------------------------------------------------
// Produce HTTP response with a JSON representation of the $data.

function respond_with_json($data)
{
    global $esc;

    $data['original_request'] = array(
        'URI' => $_SERVER['PATH_INFO'],
        'GET' => $_GET,
        'POST' => $_POST,
        'esc' => $esc,
    );

    header("Content-Type: application/json");
    echo json_encode($data);
}

//----------------------------------------------------------------
// Produce HTTP response with a HTML page containing the $title and $html.

function html_begin($title, array $breadcrumbs)
{
    if (isset($_SERVER['PATH_INFO'])) {
        $depth = count(explode('/', $_SERVER['PATH_INFO']));
        $rpath = str_repeat('../', $depth - 1);
    } else {
        $rpath = './';
    }

    $title = htmlspecialchars("TCAT: $title");
    $charset = mb_internal_encoding();

    echo <<<END
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>$title</title>
    <meta http-equiv="Content-Type" content="text/html; charset=$charset"/>
    <link rel="stylesheet" href="{$rpath}api.css" type="text/css"/>
</head>

<body>
    <h1>$title</h1>

    <div id="if_links">
	    <a href="https://github.com/digitalmethodsinitiative/dmi-tcat"
  	       target="_blank">github</a>
   	    <a href="https://github.com/digitalmethodsinitiative/dmi-tcat/issues?state=open"
   	       target="_blank">issues</a>
   	    <a href="https://github.com/digitalmethodsinitiative/dmi-tcat/wiki"
   	       target="_blank">FAQ</a>
        <a href="/capture/">capture</a>
        <a href="/analysis/">analysis</a>
     </div>
END;

    // Breadcrumb navigation links

    if (isset($breadcrumbs)) {
        echo "<ul class=\"breadcrumbs\">\n";

        foreach ($breadcrumbs as $crumb) {
            $label = $crumb[0];

            echo "<li>";

            if (isset($crumb[1])) {
                // Hyperlink
                $url = $crumb[1];
                echo "<a href=\"";
                echo htmlspecialchars($url);
                echo "\">";
                echo htmlspecialchars($label);
                echo "</a>";
            } else {
                // Text label only
                echo htmlspecialchars($label);
            }

            echo "</li>\n";
        }

        echo "</ul>\n";
    }

    echo "<div id=\"content\">\n";
}

function html_end()
{
    echo <<<END

    </div>
</body>
</html>
END;
}

//----------------------------------------------------------------
// Abort the PHP script with an error message.
//
// If invoked by a Web server, an error HTML page with the $message is
// produced with the $status as the HTTP response status.
//
// If invoked from the command line, the $message is printed out
// on stderr and the program exits with a non-zero exit status.
//
// This function does not return.

function abort_with_error($status, $message)
{
    global $argv;

    if (PHP_SAPI !== 'cli') {
        // Invoked by Web server
        http_response_code($status);
        html_begin("Error", []);
        print("<p style=\"color: red;\">");
        print(htmlspecialchars($message));
        print("</p>");
        html_end();
        exit(0);
    } else {
        /// Invoked from command line
        fwrite(STDERR, "$argv[0]: error: $message\n");
        exit(1);
    }
}

//----------------------------------------------------------------
// Check for unexpected query parameters.
//
// If any are found HTTP error page is produced and this function
// does not return.

function expected_query_parameters(array $expected_params)
{
    foreach (array_keys($_GET) as $param) {
        if (!in_array($param, $expected_params)) {
            abort_with_error(400, "Unexpected query parameter: $param");
        }
    }
}
