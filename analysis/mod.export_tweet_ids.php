<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';

        validate_all_variables();
        dataset_must_exist();

        $filename = get_filename_for_export("ids");
        $stream_to_open = export_start($filename, $outputformat);

        $sql = "SELECT id FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sqlresults = mysql_unbuffered_query($sql);
        $out = "";
        if ($sqlresults) {
            while ($data = mysql_fetch_assoc($sqlresults)) {
                if (preg_match("/_urls/", $sql))
                    $id = $data['tweet_id'];
                else
                    $id = $data['id'];
                $out .= $id . "\n";
            }
            mysql_free_result($sqlresults);
        }

        $fp = fopen($stream_to_open, 'w');
	if ($fp === false) {
	  die("Could not open output file.");
	}
        fwrite($fp, chr(239) . chr(187) . chr(191) . $out);
	fclose($fp);

    if (! $use_cache_file) {
        exit(0);
    }
    // Rest of script is the HTML page with a link to the cached CSV/TSV file.
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Export Tweet IDs</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Export Tweet IDs</h1>

        <?php
        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
