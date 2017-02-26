<?php
// catch parameters
if (isset($_GET['dataset']) && !empty($_GET['dataset'])) {
    $dataset = urldecode($_GET['dataset']);
} else {
    $sql = "SELECT querybin FROM tcat_query_bins ORDER BY id LIMIT 1";
    if ($res = pdo_fastquery($sql, $dbh)) {
        $dataset = $res['querybin'];
    }
}
$datasets = get_all_datasets();
if (count($datasets) == 0) {
    $dataset = NULL;        // No query bins are available
}

if (isset($_GET['query']) && !empty($_GET['query']))
    $query = urldecode($_GET['query']);
else
    $query = "";
if (isset($_GET['url_query']) && !empty($_GET['url_query']))
    $url_query = urldecode($_GET['url_query']);
else
    $url_query = "";
if (isset($_GET['geo_query']) && !empty($_GET['geo_query'])) {
    $geo_query = urldecode($_GET['geo_query']);
    if (preg_match("/[^\-\,\.0-9 ]/", $geo_query)) {
        die("<font size='+1' color='red'>The GEO polygon should contain only longitude latitude pairs (with dots inside for precision), seperated by a single whitespace, and after the pair a comma to mark the next point in the polygon.</font><br />Make the polygon end at the point where you started drawing it. Please see the provided example for the proper value of a WKT polygon.");
    }
} else {
    $geo_query = "";
}
if (isset($_GET['exclude']) && !empty($_GET['exclude']))
    $exclude = urldecode($_GET['exclude']);
else
    $exclude = "";
if (isset($_GET['from_source']) && !empty($_GET['from_source']))
    $from_source = urldecode($_GET['from_source']);
else
    $from_source = "";
if (isset($_GET['from_user_name']) && !empty($_GET['from_user_name']))
    $from_user_name = urldecode($_GET['from_user_name']);
else
    $from_user_name = "";
if (isset($_GET['exclude_from_user_name']) && !empty($_GET['exclude_from_user_name']))
    $exclude_from_user_name = urldecode($_GET['exclude_from_user_name']);
else
    $exclude_from_user_name = "";
if (isset($_GET['from_user_description']) && !empty($_GET['from_user_description']))
    $from_user_description = urldecode($_GET['from_user_description']);
else
    $from_user_description = "";
if (isset($_GET['samplesize']) && !empty($_GET['samplesize']))
    $samplesize = $_GET['samplesize'];
else
    $samplesize = "1000";
if (isset($_GET['minf']) && preg_match("/^\d+$/", $_GET['minf']) !== false)
    $minf = $_GET['minf'];
else
    $minf = 2;
if (isset($_GET['topu']) && preg_match("/^\d+$/", $_GET['topu']) !== false)
    $topu = $_GET['topu'];
if (isset($_GET['startdate']) && !empty($_GET['startdate']))
    $startdate = $_GET['startdate'];
else
    $startdate = strftime("%Y-%m-%d %H:00:00", date('U') - (3600));
if (isset($_GET['enddate']) && !empty($_GET['enddate']))
    $enddate = $_GET['enddate'];
else
    $enddate = strftime("%Y-%m-%d %H:00:00", date('U') + (3600));
$u_startdate = $u_enddate = 0;

if (isset($_GET['whattodo']) && !empty($_GET['whattodo']))
    $whattodo = $_GET['whattodo'];
else
    $whattodo = "";

if (isset($_GET['keywordToTrack']) && !empty($_GET['keywordToTrack']))
    $keywordToTrack = trim(strtolower(urldecode($_GET['keywordToTrack'])));
else
    $keywordToTrack = "";

if (isset($_GET['from_user_lang']) && !empty($_GET['from_user_lang']))
    $from_user_lang = trim(strtolower($_GET['from_user_lang']));
else
    $from_user_lang = "";

if (isset($_GET['minimumCowordFrequencyOverall']))
    $minimumCowordFrequencyOverall = $_GET['minimumCowordFrequencyOverall'];
else
    $minimumCowordFrequencyOverall = 10;

if (isset($_GET['minimumCowordFrequencyOverall']))
    $minimumCowordFrequencyInterval = $_GET['minimumCowordFrequencyInterval'];
else
    $minimumCowordFrequencyInterval = 0;

if (isset($_GET['showvis']) && !empty($_GET['showvis']))
    $showvis = $_GET['showvis'];
else
    $showvis = "";

if (isset($_GET['show']) && !empty($_GET['show']))
    $show = $_GET['show'];
else
    $show = array("timeline", "hashtags", "mentions", "retweets");

if (isset($_GET['tableinterval']) && !empty($_GET['tableinterval']))
    $tableinterval = $_GET['tableinterval'];
else
    $tableinterval = 600;
if (isset($_GET['top_number']) && !empty($_GET['top_number']))
    $top_number = $_GET['top_number'];
else
    $top_number = 20;

if (isset($_GET['ignore_hashtags']) && !empty($_GET['ignore_hashtags']))
    $ignore_hashtags = $_GET['ignore_hashtags'];
else
    $ignore_hashtags = "rtldebat, tk17, tk2017";

$graph_resolution = "day";
if (isset($_GET['graph_resolution']) && !empty($_GET['graph_resolution'])) {
    if (array_search($_GET['graph_resolution'], array("minute", "hour")) !== false)
        $graph_resolution = $_GET['graph_resolution'];
}
$interval = "daily";
if (isset($_REQUEST['interval'])) {
    if (in_array($_REQUEST['interval'], array('minute', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'overall', 'custom')))
        $interval = $_REQUEST['interval'];
}
$outputformat = "csv";
if (isset($_REQUEST['outputformat'])) {
    if (in_array($_REQUEST['outputformat'], array('csv', 'tsv', 'gexf', 'gdf')))
        $outputformat = $_REQUEST['outputformat'];
}
// check custom interval
$intervalDates = array();
if ($interval == "custom" && isset($_REQUEST['customInterval'])) {
    $intervalDates = explode(';', $_REQUEST['customInterval']);
    $firstDate = $lastDate = false;
    foreach ($intervalDates as $k => $date) {
        $date = trim($date);
        if (empty($date))
            continue;
        $intervalDates[$k] = $date;
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $intervalDates[$k]))
            die("<font size='+1' color='red'>custom interval not in right format</font>: YYYY-MM-DD;YYYY-MM-DD;...;YYYY-MM-DD");
        if (!$firstDate)
            $firstDate = $date;
        $lastDate = $date;
    }

    if ($firstDate != $startdate)
        die("<font size='+1' color='red'>custom interval should have the same start date as the selection</font>");
    if ($lastDate > $enddate)
        die("<font size='+1' color='red'>custom interval should have the same end date as the selection</font>");
}

function top_table($tops, $what, $times, $max_i) {
    ?>
    <div style='width: 2400px !important'>

        <?php
        $previous = array();
        for ($i = 0; $i <= $max_i; $i++) {
            /* if ($what == 'retweet') {
              ?>

              <div class="col-xs-6 col-sm-2 placeholder">
              <?php } else { */
            ?>
            <div class="col-xs-6 col-sm-1 placeholder">
                <?php //}   ?>
                <?php if ($what != "nrtweetsinperiod") { ?><h4><?= $what ?>s</h4><?php } ?>
                <span class="text-muted"><?= $times[$i]['start'] ?> - <?= $times[$i]['end'] ?></span>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th></th>
                                <?php if ($what != 'retweet') { ?>
                                    <th></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($what == 'retweet')
                                $old = top_td_retweet($tops[$what], $what, $i, $previous);
                            else
                                $old = top_td($tops[$what], $what, $i, $previous, $times);
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
            $previous = array_merge($previous, $old); // or do = $old if only against previous
        }
        ?>
    </div>
    <?php
}

function top_td($tops, $what, $i, $previous, $times) {

    global $ignore_hashtags, $top_number, $dataset;
    $old = array();
    $ignore = array();
    $c = 0;
    if ($what == "hashtag") {
        $ignore = explode(",", $ignore_hashtags);
        foreach ($ignore as $k => $v) {
            $ignore[$k] = strtolower(trim(str_replace("#", "", $v)));
        }
    }
    foreach ($tops[$i] as $array) {
        if ($what == "hashtag" && (array_search(strtolower($array[$what]), $ignore) !== false))
            continue;
        if ($c >= $top_number)
            continue;
        $style = "";
        if ($what != "nrtweetsinperiod" && array_search(strtolower($array[$what]), $previous) === false)
            $style = color_style($array['count']);
        if ($what == "hashtag")
            $link = "zoom.php?dataset=$dataset&query=%23" . $array[$what] . "&url_query=&exclude=&from_user_name=&from_user_description=&from_source=&startdate=" . $times[$i]['datetimestart'] . "&enddate=" . $times[$i]['datetimeend'];
        elseif ($what == "mention")
            $link = "zoom.php?dataset=$dataset&query=@" . $array[$what] . "&url_query=&exclude=&from_user_name=&from_user_description=&from_source=&startdate=" . $times[$i]['datetimestart'] . "&enddate=" . $times[$i]['datetimeend'];
        elseif ($what == "user")
            $link = "zoom.php?dataset=$dataset&query=&url_query=&exclude=&from_user_name=" . $array[$what] . "&from_user_description=&from_source=&startdate=" . $times[$i]['datetimestart'] . "&enddate=" . $times[$i]['datetimeend'];
        elseif ($what == "nrtweetsinperiod")
            $link = "zoom.php?dataset=$dataset&query=&url_query=&exclude=&from_user_name=&from_user_description=&from_source=&startdate=" . $times[$i]['datetimestart'] . "&enddate=" . $times[$i]['datetimeend'];

        print "<tr $style class='" . ($what !== "nrtweetsinperiod" ? "highlight " : "") . $what . $array[$what] . "' data-what='" . $what . $array[$what] . "'><td class='col-sm-1'>" . $array['count'] . "</td><td>" . ($what == "url" ? "<a href='" . $array[$what] . "' target='_blank'>" : "<a href='$link' target='_blank' class='nolink'>") . $array[$what] . ($what == "url" ? "</a>" : "</a>") . "</td></tr>";
        $old[] = strtolower($array[$what]);
        $c++;
    }
    return $old;
}

function top_td_retweet($tops, $what, $i, $previous) {
    $old = array();
    foreach ($tops[$i] as $array) {
        $style = "";
        if (array_search(strtolower($array['id']), $previous) === false)
            $style = color_style($array['count']);
        print "<tr $style class='highlight " . $what . $array['id'] . "' data-what='" . $what . $array['id'] . "'><td class='col-sm-2 tweets' id='" . $i . "_" . $array['id'] . "' data-tweetid='" . $array['id'] . "'>" . $array['count'] . " </td></tr>";
        $old[] = strtolower($array['id']);
    }
    return $old;
}

function color_style($count) {
    $val = 99 - $count;
    if ($val < 0)
        $val = "00";
    $style = 'style="background-color: #ff' . $val . $val . '"';
    return $style;
}

function linechart($starttime_u, $endtime_u) {
    global $esc;

    // get data for the line graph
    $linedata = array();
    $curdate = $starttime_u;

    $period = "minute";

    // initialize with empty dates
    while ($curdate <= $endtime_u) {
        $thendate = $curdate + 60;

        $tmp = strftime("%H:%M", $curdate);

        $linedata[$tmp] = array();
        $linedata[$tmp]["tweets"] = 0;
        $linedata[$tmp]["users"] = 0;
        $linedata[$tmp]["full"] = 0;

        $curdate = $thendate;
    }

    // overwrite zeroed dates
    $sql = "SELECT COUNT(t.text) as count, COUNT(DISTINCT(t.from_user_name)) as usercount, ";
    $sql .= "DATE_FORMAT(t.created_at,'%H:%i') datepart ";
    $sql .= "FROM " . $esc['mysql']['dataset'] . "_tweets t ";
    //$sql .= "WHERE t.created_at >= '" . strftime("%Y-%m-%d %H:%M:%S", $starttime_u) . "' AND t.created_at <= '" . strftime("%Y-%m-%d %H:%M:%S", $endtime_u) . "' ";
    $esc['datetime']['startdate'] = strftime("%Y-%m-%dT%H:%M:%S", $starttime_u);
    $esc['datetime']['enddate'] = strftime("%Y-%m-%dT%H:%M:%S", $endtime_u);
    $sql .= sqlSubset();
    $sql .= "GROUP BY datepart ORDER BY datepart";
    //print $sql . "<br>";
    $dbh = pdo_connect();
    $rec = $dbh->prepare($sql);
    $rec->execute();
    while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
        $linedata[$res['datepart']]["tweets"] = $res['count'];
        $linedata[$res['datepart']]["users"] = $res['usercount'];
    }
    ?>
    <div id="if_panel_linegraph"></div>

    <script type="text/javascript">

        var data = new google.visualization.DataTable();

        data.addColumn('string', 'Date');
        data.addColumn('number', 'Tweets');
        data.addColumn('number', 'Users');

    <?php
    echo "data.addRows(" . count($linedata) . ");";

    $counter = 0;

    foreach ($linedata as $key => $value) {

        echo "data.setValue(" . $counter . ", 0, '" . $key . "');";
        echo "data.setValue(" . $counter . ", 1, " . $value["tweets"] . ");";
        echo "data.setValue(" . $counter . ", 2, " . $value["users"] . ");";

        $counter++;
    }
    ?>

        var chart = new google.visualization.LineChart(document.getElementById('if_panel_linegraph'));
        chart.draw(data, {width: 1200, height: 150, fontSize: 9, lineWidth: 1, hAxis: {slantedTextAngle: 90, slantedText: true}, chartArea: {left: 50, top: 10, width: 1000, height: 100}});

    </script>

    <?php
}
?>