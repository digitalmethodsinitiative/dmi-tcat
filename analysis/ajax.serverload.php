<?php
require_once './common/config.php';
require_once './common/functions.php';

/** configuration defaults **/
if (!defined('TCAT_SYSLOAD_CHECKING')) {
    define('TCAT_SYSLOAD_CHECKING', false);
}
if (!defined('TCAT_SYSLOAD_WARNING_QUERIES')) {
    define('TCAT_SYSLOAD_WARNING_QUERIES', 5);
}
if (!defined('TCAT_SYSLOAD_MAXIMUM_QUERIES')) { 
    define('TCAT_SYSLOAD_MAXIMUM_QUERIES', 10);
}

if (TCAT_SYSLOAD_CHECKING == false) {
    exit();
}

$exts = array ( 'tweets', 'mentions', 'urls', 'hashtags', 'media', 'places', 'withheld' );

$sql = "SHOW FULL PROCESSLIST";
$rec = mysql_query($sql);
$selects = 0; $working = array();
if (mysql_num_rows($rec) > 0) {
    while ($res = mysql_fetch_assoc($rec)) {
        if ($res['db'] !== $database) { continue; }
        if ($res['Command'] !== 'Query') { continue; }
        if (preg_match("/^SELECT/i", $res['Info']) && $res['Time'] > 3) {
            foreach ($exts as $e) {
                $search = '_' . $e;
                if (preg_match("/ ([^ ]*?)$search /i", $res['Info'], $matches)) {
                    $working[] = $matches[1];
                    break;
                }
            }
            $selects++;
        }
    }
}
$working = array_unique($working);

$load = 0;
if ($selects >= TCAT_SYSLOAD_MAXIMUM_QUERIES) {
    $load = 2;
} else if ($selects >= TCAT_SYSLOAD_WARNING_QUERIES) {
    $load = 1;
} else {
    $load = 0;
}

print "$load<div style='margin:0;padding:0'>";
switch ($load) {
    case 0: {
                svg_circle('#42c168'); # green
                break;
            }
    case 1: {
                svg_circle('#f8962b'); # orange
                break;
            }
    case 2: {
                svg_circle('#f62221'); # red
                break;
            }
}

$binstr = ''; $first = true;
foreach ($working as $bin) {
    if ($first == false) {
        $binstr .= ', ';
    } else {
        $binstr .= '[ ';
        $first = false;
    }
    $binstr .= $bin;
}
if ($binstr != '') {
    $binstr .= ' ] ';
}

echo "<span style='float:left'>";
if($load == 0) {
    print " The server is ready for your requests";
    if (count($working)) {
    	print ", processing query bins $binstr";
    }
} elseif($load == 1) {
	print " The server is already processing query bins $binstr, but can accept yours too";
} elseif($load == 2) {
	print " The server is very busy with query bins $binstr, <u>please wait until the light turns green</u>";
}
echo "</span>";
print "</div>";

function svg_circle($color) {
    echo '
<svg height="17" width="17" style="float:left">
  <circle cx="6" cy="6" r="5" stroke="' . $color . '" stroke-width="1" fill="' . $color . '" />
  Sorry, your browser does not support inline SVG.  
</svg> 
    ';
}
