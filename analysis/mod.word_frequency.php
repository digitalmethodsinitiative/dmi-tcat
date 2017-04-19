<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';

$lowercase = isset($_GET['lowercase']) ? $lowercase = $_GET['lowercase'] : 0;
$minf = isset($_GET['minf']) ? $minf = $_GET['minf'] : 1;

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Word frequency</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Word frequency</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        $tempfile = tmpfile();
        fputs($tempfile, chr(239) . chr(187) . chr(191));

        $sql = "SELECT text, " . sqlInterval() . " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        //$sql .= " GROUP BY datepart ORDER BY datepart ASC";
        $sql .= " ORDER BY datepart ASC";

        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            $text = $data["text"];
            $datepart = str_replace(' ', '_', $data["datepart"]);
            preg_match_all('/(https?:\/\/[^\s]+)|([@#\p{L}\p{N}][\p{L}\p{N}]+)/u', $text, $matches, PREG_PATTERN_ORDER);
            foreach ($matches[0] as $word) {
                if (preg_match('/(https?:\/\/)/u', $word)) continue;
                if ($lowercase !== 0) $word = mb_strtolower($word);
                fputs($tempfile, "\"$datepart\" \"$word\"\n");
            }
        }

        if (function_exists('eio_fsync')) { eio_fsync($tempfile); }
                                     else { fflush($tempfile); }

        $tempmeta = stream_get_meta_data($tempfile);
        $templocation = $tempmeta["uri"];

        // write csv results

        // CSV is written by awk here, so we explicitely handle the output format

        $filename = get_filename_for_export("wordFrequency");
        $csv = fopen($filename, "w");
        fputs($csv, chr(239) . chr(187) . chr(191));
        if ($outputformat == 'tsv') {
            fputs($csv, "interval\tword\tfrequency\n");
        } else {
            fputs($csv, "interval,word,frequency\n");
        }
        if ($outputformat == 'tsv') {
            system("sort -S 8% $templocation | uniq -c | sort -S 8% -b -k 2,2 -k 1,1nr -k 3,3 | awk '{ if ($1 >= $minf) { print $2 \"\\t\" $3 \"\\t\" $1} }' | sed -e 's/_/ /' >> $filename");
        } else {
            system("sort -S 8% $templocation | uniq -c | sort -S 8% -b -k 2,2 -k 1,1nr -k 3,3 | awk '{ if ($1 >= $minf) { print $2 \",\" $3 \",\" $1} }' | sed -e 's/_/ /' >> $filename");
        }
 
        fclose($csv);
        
        fclose($tempfile); // this removes the temporary file

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
