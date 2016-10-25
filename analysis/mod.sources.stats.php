<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/CSV.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Source stats</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">



        </script>

    </head>

    <body>

        <h1>TCAT :: Source stats</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        $filename = get_filename_for_export("sourceStats");

        $csv = new CSV($filename, $outputformat);

        // tweets per source 
        $sql = "SELECT COUNT(*) AS count, source, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY datepart,source ORDER BY count DESC";

        //print $sql . "<br>"; exit;


        $array = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {

			$res['source'] = preg_replace("/<.+>/U", "", $res['source']);
			$res['source'] = preg_replace("/,/", "_", $res['source']);
			$res['source'] = preg_replace("/[ \s\t]+/", " ", $res['source']);
			$res['source'] = trim($res['source']);

			if(!isset($array[$res['datepart']][$res['source']])) {
				 $array[$res['datepart']][$res['source']] = 0;
			}
            $array[$res['datepart']][$res['source']] += $res['count'];
        }

        $csv->writeheader(array("date", "source", "count"));

        foreach ($array as $date => $sources) {
        	arsort($sources);
        	foreach($sources as $source => $freq) {
                $csv->newrow();
                $csv->addfield($date);
                $csv->addfield($source);
                $csv->addfield($freq);
                $csv->writerow();
			}
		}

        $csv->close();

        echo '<fieldset class="if_parameters">';
        echo '<legend>Source stats</legend>';
        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';
        echo '</fieldset>';


        ?>

    </body>
</html>
