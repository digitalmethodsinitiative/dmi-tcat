<?php
require_once './common/config.php';
require_once './common/functions.php';

$minf = isset($_GET['minf']) ? $minf = $_GET['minf'] : 1;

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Media frequency</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Media frequency</h1>

        <?php
        validate_all_variables();

        $tempfile = tmpfile();
        fputs($tempfile, chr(239) . chr(187) . chr(191));

        mysql_query("set names utf8");
        $sql = "SELECT m.media_url_https as url, " . sqlInterval() . " FROM " . $esc['mysql']['dataset'] . "_tweets t, " .
                $esc['mysql']['dataset'] . "_media m ";
        $sql .= sqlSubset();
        $sql .= " AND m.tweet_id = t.id ";
        $sql .= " ORDER BY datepart ASC";
        $sqlresults = mysql_query($sql);
        $debug = '';
        if ($sqlresults) {
            while ($data = mysql_fetch_assoc($sqlresults)) {
                $url = textToCSV($data["url"]);
                $datepart = str_replace(' ', '_', $data["datepart"]);
                fputs($tempfile, "\"$datepart\" \"$url\"\n");
            }
        }

        if (function_exists('eio_fsync')) { eio_fsync($tempfile); }
                                     else { fflush($tempfile); }

        $tempmeta = stream_get_meta_data($tempfile);
        $templocation = $tempmeta["uri"];

        // write csv results

        $filename = get_filename_for_export("mediaFrequency");
        $csv = fopen($filename, "w");
        fputs($csv, chr(239) . chr(187) . chr(191));
        fputs($csv, "data,media url,frequency\n");
        system("sort -S 8% $templocation | uniq -c | sort -S 8% -b -k 2,2 -k 1,1nr -k 3,3 | awk '{ if ($1 >= $minf) { print $2 \",\" $3 \",\" $1} }' | sed -e 's/_/ /' >> $filename");
 
        fclose($csv);
        
        fclose($tempfile); // this removes the temporary file

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
