<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/CSV.class.php'
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

        <h1>TCAT :: Source Stats</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        $filename = get_filename_for_export("sourceStats");

        $csv = new CSV($filename, $outputformat);

        // tweets per source
        $sql = "SELECT count(t.id) AS count, source, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY datepart, source";
        //print $sql . "<br>";

        $array = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $array[$res['datepart']][$res['source']] = $res['count'];
        }
        if (!empty($array)) {
            foreach ($array as $date => $ar)
                $stats[$date]['tweets_per_source'] = stats_summary($ar);
        }

        // users per interval
        $sql = "SELECT count(distinct(t.source)) AS count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY datepart";
        //print $sql . "<br>";
        $array = array();
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $array[$res['datepart']] = $res['count'];
        }
        if (!empty($array)) {
            $stats['all dates']['sources_per_date'] = stats_summary($array);
        }

        // urls per source per interval
        $sql = "SELECT count(distinct(u.url)) AS count, t.source, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t ";
        $where = "t.id = u.tweet_id AND ";
        $sql .= sqlSubset($where);
        $sql .= "GROUP BY datepart, source";
        //print $sql."<br>";
        $array = array();
        $rec = $dbh->prepare($sql); 
        $rec->execute();
        while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $array[$res['datepart']][$res['source']] = $res['count'];
        }
        if (!empty($array)) {
            foreach ($array as $date => $ar)
                $stats[$date]['urls_per_source'] = stats_summary($ar);
        }

        $csv->writeheader(array("date", "what", "min", "max", "avg", "Q1", "median", "Q3", "25%TrimmedMean"));
        foreach ($stats as $date => $datestats) {
            foreach ($datestats as $what => $stat) {
                $csv->newrow();
                $csv->addfield($date);
                $csv->addfield($what);
                $csv->addfield($stat['min']);
                $csv->addfield($stat['max']);
                $csv->addfield($stat['avg']);
                $csv->addfield($stat['Q1']);
                $csv->addfield($stat['median']);
                $csv->addfield($stat['Q3']);
                $csv->addfield($stat['truncatedMean']);
                $csv->writerow();
            }
        }
        $csv->close();

        echo '<fieldset class="if_parameters">';
        echo '<legend>User stats</legend>';
        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        /*
          // interface language, user-defined location
          $sql = "SELECT max(t.created_at), t.from_user_id, t.from_user_lang, t.location FROM " . $esc['mysql']['dataset'] . "_tweets t ";
          $sql .= sqlSubset();
          $sql .= "GROUP BY from_user_id";
          $sqlresults = mysql_query($sql);
          $locations = $languages = array();
          while ($res = mysql_fetch_assoc($sqlresults)) {
          $locations[] = $res['location'];
          $languages[] = $res['from_user_lang'];
          }

          $locations = array_count_values($locations);
          arsort($locations);
          $contents = "location,frequency\n";
          foreach ($locations as $location => $frequency)
          $contents .= preg_replace("/[\r\n\s\t,]+/im", " ", trim($location)) . ",$frequency\n";

          file_put_contents($filename_locations, chr(239) . chr(187) . chr(191) . $contents);

          echo '<fieldset class="if_parameters">';
          echo '<legend>Locations </legend>';
          echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename_locations)) . '">' . $filename_locations . '</a></p>';
          echo '</fieldset>';

          $languages = array_count_values($languages);
          arsort($languages);
          $contents = "language,frequency\n";
          foreach ($languages as $language => $frequency)
          $contents .= preg_replace("/[\r\n\s\t]+/", "", $language) . ",$frequency\n";

          file_put_contents($filename_languages, chr(239) . chr(187) . chr(191) . $contents);

          echo '<fieldset class="if_parameters">';
          echo '<legend>Languages </legend>';
          echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename_languages)) . '">' . $filename_languages . '</a></p>';
          echo '</fieldset>';
         */
        ?>

    </body>
</html>
