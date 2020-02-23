<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/CSV.class.php';

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
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        $media_url_count = array();

        $tempfile = tmpfile();
        fputs($tempfile, chr(239) . chr(187) . chr(191));

        $sql = "SELECT m.media_url_https as url, " . sqlInterval() . " FROM " . $esc['mysql']['dataset'] . "_media m, " .
                $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND m.tweet_id = t.id ";
        $sql .= " ORDER BY datepart ASC";
        $debug = '';
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {

            $datepart = $data["datepart"];
            $url = $data["url"];
            if (!array_key_exists($datepart, $media_url_count)) {
                $media_url_count[$datepart] = array();
            }
            if (!array_key_exists($url, $media_url_count[$datepart])) {
                $media_url_count[$datepart][$url] = 1;
            } else {
                $media_url_count[$datepart][$url]++;
            }
        }

        // write csv results

        $filename = get_filename_for_export("mediaFrequency");
        $csv = new CSV($filename, $outputformat);
        $csv->writeheader(array('interval', 'media url', 'frequency'));
        foreach ($media_url_count as $datepart => $url_count) {
            arsort($url_count);
            foreach ($url_count as $url => $count) {
                if ($minf > 0 && $count < $minf) {
                    continue;
                }
                $csv->newrow();
                $csv->addfield($datepart);
                $csv->addfield($url);
                $csv->addfield($count);
                $csv->writerow();
            }
        }
        $csv->close();

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
