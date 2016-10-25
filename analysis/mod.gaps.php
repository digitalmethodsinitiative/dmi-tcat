<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/CSV.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Export gap data</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">



        </script>

    </head>

    <body>

        <h1>TCAT :: Export gap data</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        // make filename and open file for write
        $module = "gapData";
        $sql = "SELECT id, `type` FROM tcat_query_bins WHERE querybin = '" . $esc['mysql']['dataset'] . "'";
        $rec = $dbh->prepare($sql);
        $rec->execute();
        if ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
            $bin_id = $res['id'];
            $bin_type = $res['type'];
        } else {
            die("Query bin not found!");
        }
        $exportSettings = array();
        if (isset($_GET['exportSettings']) && $_GET['exportSettings'] != "")
            $exportSettings = explode(",", $_GET['exportSettings']);
        $filename = get_filename_for_export($module, implode("_", $exportSettings));
        $csv = new CSV($filename, $outputformat);
        // write header
        $header = "start,end";
        $csv->writeheader(explode(',', $header));

        // make query
        $sql = "SELECT * FROM tcat_error_gap WHERE type = :type and
                                                   start >= :start and end <= :end";
        $rec = $dbh->prepare($sql);
        $rec->bindParam(":type", $bin_type, PDO::PARAM_STR);
        $rec->bindParam(":start", $_GET['startdate'], PDO::PARAM_STR);
        $rec->bindParam(":end", $_GET['enddate'], PDO::PARAM_STR);
        $rec->execute();

        // loop over results and write to file
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            // the query bin must have been active during the gap period, if we want to report it as a possible gap
            $sql2 = "SELECT count(*) as cnt FROM tcat_query_bins_phrases WHERE querybin_id = $bin_id and
                                                        starttime <= '" . $data["end"] . "' and (endtime >= '" . $data["start"] . "' or endtime is null or endtime = '0000-00-00 00:00:00')";
            $rec2 = $dbh->prepare($sql2);
            $rec2->execute();
            while ($data2 = $rec2->fetch(PDO::FETCH_ASSOC)) {
                if ($data2['cnt'] > 0) {
                    $csv->newrow();
                    $csv->addfield($data["start"]);
                    $csv->addfield($data["end"]);
                    $csv->writerow();
                    break;
                }
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
