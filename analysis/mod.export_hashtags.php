<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/CSV.class.php';

        validate_all_variables();
        dataset_must_exist();

        $filename = get_filename_for_export('hashtagExport');
        $stream_to_open = export_start($filename, $outputformat);
	
        $csv = new CSV($stream_to_open, $outputformat);

        $csv->writeheader(array('tweet_id', 'hashtag'));

        $sql = "SELECT t.id as id, h.text as hashtag FROM " . $esc['mysql']['dataset'] . "_tweets t, " . $esc['mysql']['dataset'] . "_hashtags h ";
        $sql .= sqlSubset();
        $sql .= " AND h.tweet_id = t.id ORDER BY id";
        $sqlresults = mysql_unbuffered_query($sql);
        $out = "";
        if ($sqlresults) {
            while ($data = mysql_fetch_assoc($sqlresults)) {
                $csv->newrow();    
                $csv->addfield($data['id'], 'integer');
                $csv->addfield($data['hashtag'], 'string');
                $csv->writerow();
            }
            mysql_free_result($sqlresults);
        }

        $csv->close();

    if (! $use_cache_file) {
        exit(0);
    }
    // Rest of script is the HTML page with a link to the cached CSV/TSV file.
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Export hashtags</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Export hashtags</h1>

        <?php
        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
