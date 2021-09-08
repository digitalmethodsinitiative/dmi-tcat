<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/../config.separate.php';
require_once __DIR__ . '/common/functions.php';

/*
 * PLEASE NOTE: This script references (configuration) files not present in mainline TCAT.
 * It also not up-to-date with PHP 7 set. It is only here for archival purposes unless it will be revived.
 */
die();

$bootstrap = 14;
if (isset($_GET['bootstrap']))
    $bootstrap = $_GET['bootstrap'];
validate_all_variables();
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Trending keywords</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" src="https://www.google.com/jsapi"></script>

        <script type="text/javascript">

            google.load("visualization", "1", {packages:["corechart"]});
        </script>

    </head>

    <body>

        <h1>TCAT :: Trending keywords</h1>

        <table>


            <form action="<?php echo "/coword/" . $_SERVER["PHP_SELF"]; ?>">
                <input type="hidden" name="dataset" value="<?php echo $dataset; ?>" />
                <input type="hidden" name="exclude" value="<?php echo $exclude; ?>" />
                <input type="hidden" name="from_user_name" value="<?php echo $from_user_name; ?>" />
                <input type="hidden" name="startdate" value="<?php echo $startdate; ?>" />
                <input type="hidden" name="enddate" value="<?php echo $enddate; ?>" />
                <input type="hidden" name="interval" value="<?php echo $interval; ?>" />
                <input type="hidden" name="customInterval" value="<?php if (isset($_GET['customInterval'])) echo htmlentities($_GET['customInterval']); ?>" />
                <tr>
                    <td>Keyword:</td>
                    <td><input type="text" name="query" value="<?php echo $query; ?>" /></td>
                </tr>
                <tr>
                    <td>Days to bootstrap:</td>
                    <td><input type="text" name="bootstrap" value="<?php echo $bootstrap; ?>" /></td>
                </tr>
                <tr>
                    <td><input type="submit" value="create trend profile" /></td>
                </tr>
            </form>

        </table>

        Burstiness scores are calculated as follows: frequency of the keyword at time t / (average of that frequency during times 1 to t-1)<br><br>

                <?php
                if (!empty($query)) {
                    $trendword = mysql_real_escape_string($query);

                    $sql = "SELECT count(id) AS count, ";
                    $sql .= sqlInterval();
                    $sql .= " FROM " . $esc['shell']['datasetname'] . "_tweets t ";
                    $sql .= sqlSubset();
                    $sql .= " GROUP BY datepart";
                    //print $sql . "<bR>";
                    $rec = mysql_query($sql);
                    $freq = array();
                    if (mysql_num_rows($rec) > 0) {
                        while ($res = mysql_fetch_assoc($rec)) {
                            $freq[$res['datepart']] = $res['count'];
                        }
                    } //else var_export(mysql_error());

                    $i = 0;
                    foreach ($freq as $date => $count) {
                        $i++;
                        if ($i < $bootstrap) {
                            $prevdates[$date] = $count;
                            continue;
                        }

                        $trend[$date] = round($count / (array_sum($prevdates) / count($prevdates)), 2);

                        $prevdates[$date] = $count;
                        // $trend[$date] = $freq[$date] / ( ( (0.1*$freq[$date]/100) + (array_sum($freq)-$freq[$date])) / (array_count($freq) -1 + 0.1) ); // Weber, Garimella and Borra 2012
                    }
                    ?>

                    <div id="if_panel_linegraph" class="if_panel_box"></div>
                    <div style='clear:both'></div>
                    <div id="if_panel_linegraph_score" class="if_panel_box" ></div>
                    <script type="text/javascript">

                        var data = new google.visualization.DataTable();
                        data.addRows(<?php echo count($freq); ?>);
                        data.addColumn('string', 'Date');
                        data.addColumn('number', 'Tweets');
    <?php
    $i = 0;
    foreach ($freq as $date => $count) {
        echo "data.setValue(" . $i . ", 0, '" . $date . "');";
        echo "data.setValue(" . $i . ", 1, " . $count . ");";
        $i++;
    }
    ?>
        var chart = new google.visualization.LineChart(document.getElementById('if_panel_linegraph'));
        chart.draw(data, {width:1000, height:50, fontSize:9, hAxis:{slantedTextAngle:90, slantedText:true}, chartArea:{left:50,top:10,width:850,height:50}});

        var data = new google.visualization.DataTable();
        data.addRows(<?php echo count($freq); ?>);
        data.addColumn('string', 'Date');
        data.addColumn('number', 'Score');
    <?php
    $i = 0;
    foreach ($freq as $date => $count) {
        if (isset($trend[$date]))
            $count = $trend[$date];
        else
            $count = 0;
        echo "data.setValue(" . $i . ", 0, '" . $date . "');";
        echo "data.setValue(" . $i . ", 1, " . $count . ");";
        $i++;
    }
    ?>
        var chart = new google.visualization.LineChart(document.getElementById('if_panel_linegraph_score'));
        chart.draw(data, {width:1000, height:150, fontSize:9, hAxis:{slantedTextAngle:90, slantedText:true}, chartArea:{left:50,top:10,width:850,height:50}});
                    </script>
                    <?php
                    print "<div style='clear:both'></div>";
                    print "<table><tr><th>date</th><th>frequency</th><th>score</th></tr>";
                    foreach ($freq as $date => $count) {
                        $score = "-";
                        if (isset($trend[$date]))
                            $score = $trend[$date];
                        print "<tr><td>" . $date . "</td><td>" . $count . "</td><td>$score</td></tr>";
                    }
                    print "</table>";
                }
                ?>

                </body>
                </html>
