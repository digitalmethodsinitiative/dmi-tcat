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
        // Add one day to datetime as default is at start of day whic may then exclude most recent gaps
        $end_date = date('Y-m-d H:i:s', strtotime($_GET['enddate'] . ' +1 day'));

        // Prep header and query depending on bin type
        if ( $bin_type == 'follow' ) {
          $header = "user_id,gap_start,gap_end";
          $sql = "SELECT BU.user_id as query, GAP.start as start, GAP.end as end FROM tcat_error_gap GAP, tcat_query_bins_users BU
                           WHERE GAP.type = :type and
                                 GAP.start >= :start and
                                 GAP.end <= :end and
                                 BU.querybin_id = :bin_id and
                                 BU.starttime <= GAP.end and
                                 ( BU.endtime >= GAP.start or
                                 BU.endtime is null or
                                 BU.endtime = '0000-00-00 00:00:00' )";
        } elseif ( $bin_type == 'track' ) {
          $header = "phrase,gap_start,gap_end";
          $sql = "SELECT P.phrase as phrase, GAP.start as start, GAP.end as end FROM tcat_error_gap GAP, tcat_query_bins_phrases BP, tcat_query_phrases P
                           WHERE GAP.type = :type and
                                 GAP.start >= :start and
                                 GAP.end <= :end and
                                 BP.querybin_id = :bin_id and
                                 BP.phrase_id = P.id and
                                 BP.starttime <= GAP.end and
                                 ( BP.endtime >= GAP.start or
                                 BP.endtime is null or
                                 BP.endtime = '0000-00-00 00:00:00' )";
        } else {
          // TODO: add gap for 'one-percent'
          // TODO: verify if other types use phrase; 4CAT imports do use phrase setup
          $header = "phrase,gap_start,gap_end";
          $sql = "SELECT P.phrase as phrase, GAP.start as start, GAP.end as end FROM tcat_error_gap GAP, tcat_query_bins_phrases BP, tcat_query_phrases P
                           WHERE GAP.type = :type and
                                 GAP.start >= :start and
                                 GAP.end <= :end and
                                 BP.querybin_id = :bin_id and
                                 BP.phrase_id = P.id and
                                 BP.starttime <= GAP.end and
                                 ( BP.endtime >= GAP.start or
                                 BP.endtime is null or
                                 BP.endtime = '0000-00-00 00:00:00' )";
        }
          // write header
        $csv->writeheader(explode(',', $header));

        // Run query
        $rec = $dbh->prepare($sql);
        $rec->bindParam(":type", $bin_type, PDO::PARAM_STR);
        $rec->bindParam(":start", $_GET['startdate'], PDO::PARAM_STR);
        $rec->bindParam(":end", $end_date, PDO::PARAM_STR);
        $rec->bindParam(":bin_id", $bin_id, PDO::PARAM_STR);
        $rec->execute();

        // loop over results and write to file
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
          $csv->newrow();
          $csv->addfield($data["query"]);
          $csv->addfield($data["start"]);
          $csv->addfield($data["end"]);
          $csv->writerow();
        }

        $csv->close();

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
