<?php
require_once '../config.php';
require_once 'common/config.php';
require_once 'common/functions.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>DMI Twitter Capturing and Analysis Toolset (DMI-TCAT)</title>

        <meta http-equiv="Content-Type" content="text/html; charset=<?php echo mb_internal_encoding(); ?>" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript" src="./scripts/jquery-1.7.1.min.js"></script>

        <script type="text/javascript" src="https://www.google.com/jsapi"></script>

        <script type="text/javascript">

            google.load("visualization", "1", {packages:["corechart"]});

            function sendUrl(_file) {
                var _d1 = $("#ipt_startdate").val();
                var _d2 = $("#ipt_enddate").val();
                var outputformat = getOutputformat();

                if(!_d1.match(/\d{4}-\d{2}-\d{2}/) || !_d2.match(/\d{4}-\d{2}-\d{2}/)) {
                    alert("Please check the date format!");
                    return false;
                }

                if(typeof(_file) == "undefined") {
                    _file = "index.php";
                    $('#whattodo').val('');
                }
                var _url =
<?php
if (defined('ANALYSIS_URL'))
    print '"' . ANALYSIS_URL . '"';
?>
            + _file +
            "?dataset=" + $("#ipt_dataset").val() +
            "&query=" + $("#ipt_query").val().replace(/#/g,"%23") +
            "&url_query=" + $("#ipt_url_query").val().replace(/#/g,"%23") +
<?php if (dbserver_has_geo_functions()) { ?>
            "&geo_query=" + $("#ipt_geo_query").val()  +
<?php } ?>
        "&exclude=" + $("#ipt_exclude").val().replace(/#/g,"%23") +
            "&from_user_name=" + $("#ipt_from_user").val() +
            "&from_source=" + $("#ipt_from_source").val() +
            "&startdate=" + $("#ipt_startdate").val() +
            "&enddate=" + $("#ipt_enddate").val() +
            "&whattodo=" + $("#whattodo").val() +
            "&graph_resolution=" + $("input[name=graph_resolution]:checked").val() +
            "&outputformat=" + outputformat;

        document.location.href = _url;
    }
    function saveSvg(id){
        $("svg").attr({ version: '1.1' , xmlns:"http://www.w3.org/2000/svg"});
        var e = document.getElementById(id);
        var svg = e.getElementsByTagName('svg')[0].parentNode.innerHTML.replace(/[\r\n]/g,"").replace(/<div.*/m,"");
        var b64 = window.btoa(unescape(encodeURIComponent(svg)));
        // Works in Firefox 3.6 and Webkit and possibly any browser which supports the data-uri
        $("#download_"+id).html($('<a style="width:25px;height:25px;" href-lang="image/svg+xml" href="data:image/svg+xml;base64,\n'+b64+'" title="file.svg">Download SVG</a>'));
    }
    function askFrequency() {
        var minf = parseInt(prompt("Specify the minimum frequency for data to be included in the export:","2"), 10);
        return minf;
    }
    function askRetweetFrequency() {
        var minf = parseInt(prompt("Specify the minimum times a tweet should be retweeted for it to be included in the export:","4"), 10);
        return minf;
    }
    function askInteractionFrequency() {
        var minf = parseInt(prompt("Specify the minimum frequency for data to be included in the export:","4"), 10);
        return minf;
    }
    function askCascadeFrequency() {
        var minf = parseInt(prompt("Specify the minimum number of tweets for the user to be included:","10"), 10);
        return minf;
    }
    function askTopht() {
        var topu = parseInt(prompt("Specify number of top hashtags to get. (by frequency of hashtag, enter 0 to get all)","500"), 10);
        return topu;
    }
    function askMentions() {
        var topu = parseInt(prompt("Specify number of top users you want to get. (by number of mentions, enter 0 to get all)","500"), 10);
        return topu;
    }
    function askLowercase() {
        var lower = parseInt(prompt("Do you want to convert all words to lowercase? (enter 0 [=no] or 1 [=yes])", "0"), 10);
        return lower;
    }
    function getInterval() {
        var selected = $('[name="interval"]:checked');
        var selectedValue = "";
        if (selected.length > 0)
            selectedValue = selected.val();
        var inter = "&interval="+selectedValue+"&customInterval="+$('[name="customInterval"]').val();
        return inter;
    }
    function getOutputformat() {
        var selected = $('[name="outputformat"]:checked');
        var selectedValue = undefined;
        if (selected.length > 0)
            selectedValue = selected.val();
        return selectedValue;
    }
    function getExportSettings() {
        var exportSettings = "&exportSettings=";
        $('input:checkbox').each(function () {
            if(this.checked) 
                exportSettings += $(this).val() + ",";
        });
        return exportSettings;
        
    }
    $(document).ready(function(){
        $('#form').submit(function(){
            sendUrl();
            return false;
        });
    });

        </script>

    </head>

    <body>

        <div id="if_fullpage">

            <h1 id="if_title">DMI Twitter Capturing and Analysis Toolset (DMI-TCAT)</h1>

            <div id="if_links">
                &raquo; <a href="https://github.com/digitalmethodsinitiative/dmi-tcat" target="_blank" class="if_toplinks">github</a>&nbsp;&nbsp;&nbsp;
                &raquo; <a href="https://github.com/digitalmethodsinitiative/dmi-tcat/issues?state=open" target="_blank" class="if_toplinks">issues</a>&nbsp;&nbsp;&nbsp;
                &raquo; <a href="https://github.com/digitalmethodsinitiative/dmi-tcat/wiki" target="_blank" class="if_toplinks">FAQ</a>
                <?php
                if (defined("ADMIN_USER") && ADMIN_USER != "" && isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER'] == ADMIN_USER)
                    print '&nbsp;&nbsp;&nbsp; &raquo; <a href="../capture/index.php" target="_blank" class="if_toplinks">admin</a>';
                ?>
            </div>

            <div style="clear: both;"></div>

            <fieldset class="if_parameters">

                <legend>Data selection</legend>

                <h3>Select the dataset:</h3>

                <form action="index.php" method="get" id="form">

                    <?php
                    echo '<select id="ipt_dataset" name="dataset">';

                    $ordered_datasets = array();
                    foreach ($datasets as $key => $set) {
                        if ($set['type'] == "track") {
                            $ordered_datasets["keyword captures"][$key] = $set;
                        } elseif ($set['type'] == "geotrack") {
                            $ordered_datasets["geo captures"][$key] = $set;
                        } elseif ($set['type'] == "follow") {
                            $ordered_datasets["user captures"][$key] = $set;
                        } elseif ($set['type'] == "onepercent") {
                            $ordered_datasets["one percent samples"][$key] = $set;
                        } elseif ($set['type'] == "timeline") {
                            $ordered_datasets["timelines"][$key] = $set;
                        } elseif ($set['type'] == "search") {
                            $ordered_datasets["search"][$key] = $set;
                        } elseif ($set['type'] == "import ytk") {
                            $ordered_datasets["imports: yourTwapperKeeper"][$key] = $set;
                        } elseif ($set['type'] == "import timeline") {
                            $ordered_datasets["imports: timeline"][$key] = $set;
                        } elseif ($set['type'] == "import track") {
                            $ordered_datasets["imports: track"][$key] = $set;
                        } else {  // legacy
                            $ordered_datasets["other"][$key] = $set;
                        }
                    }
                    ksort($ordered_datasets);

                    $count = 0;
                    foreach ($ordered_datasets as $groupname => $group) {

                        echo '<optgroup label="' . $groupname . '">';

                        foreach ($group as $key => $set) {

                            $v = ($key == $dataset) ? 'selected="selected"' : "";

                            echo '<option value="' . $key . '" ' . $v . '>' . $set["bin"] . ' --- ' . number_format($set["notweets"], 0, ",", ".") . ' tweets from ' . $set['mintime'] . ' to ' . $set['maxtime'] . '</option>';
                            $count += $set['notweets'];
                        }

                        echo '</optgroup>';
                    }

                    echo "</select> ";

                    print "<table style='float:right'><tr><td>" . number_format($count, 0, ",", ".") . " tweets archived so far (and counting)</td></tr></table>";
                    ?>

                    <h3>Select parameters:</h3>

                    <table>

                        <tr>
                            <td class="tbl_head">Query: </td><td><input type="text" id="ipt_query" size="60" name="query" value="<?php echo $query; ?>" /> (empty: containing any text*)</td>
                        </tr>

                        <tr>
                            <td class="tbl_head">Exclude: </td><td><input type="text" id="ipt_exclude" size="60" name="exclude"  value="<?php echo $exclude; ?>" /> (empty: exclude nothing*)</td>
                        </tr>

                        <tr>
                            <td class="tbl_head">From user: </td><td><input type="text" id="ipt_from_user" size="60" name="from_user_name"  value="<?php echo $from_user_name; ?>" /> (empty: from any user*)</td>
                        </tr>
                        <tr>
                            <td class="tbl_head">From twitter client: </td><td><input type="text" id="ipt_from_source" size="60" name="from_source"  value="<?php echo $from_source; ?>" /> (empty: from any client*)</td>
                        </tr>
                        <tr>
                            <td class="tbl_head">(Part of) URL: </td><td><input type="text" id="ipt_url_query" size="60" name="url_query"  value="<?php echo $url_query; ?>" /> (empty: any or all URLs*)</td>
                        </tr>
                        <?php if (dbserver_has_geo_functions()) { ?>
                            <tr>
                                <td class="tbl_head">GEO bounding polygon: </td><td><input type="text" id="ipt_geo_query" size="60" name="geo_query"  value="<?php echo $geo_query; ?>" /> (POLYGON in <a href='http://en.wikipedia.org/wiki/Well-known_text'>WKT</a> format.)</td>
                            </tr>
                        <?php } ?>
                        <tr>
                            <td class="tbl_head">Startdate:</td><td><input type="text" id="ipt_startdate" size="60" name="startdate" value="<?php echo $startdate; ?>" /> (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)</td>
                        </tr>

                        <tr>
                            <td class="tbl_head">Enddate:</td><td><input type="text" id="ipt_enddate" size="60" name="enddate" value="<?php echo $enddate; ?>" /> (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)</td>
                        </tr>
                        <tr>
                            <td><input type="submit" value="update overview" /></td>
                        </tr>
                        <tr><td colspan='2'>*  You can also do AND <b>or</b> OR queries, although you cannot mix AND and OR in the same query.</td></tr>
                    </table>

                </form>
            </fieldset>

            <?php
            validate_all_variables();

            // count current subsample
            $sql = "SELECT count(distinct(t.id)) as count FROM " . $esc['mysql']['dataset'] . "_tweets t ";
            $sql .= sqlSubset();
            $sqlresults = mysql_query($sql);
            $data = mysql_fetch_assoc($sqlresults);
            $numtweets = $data["count"];

            // count tweets containing links
            $sql = "SELECT count(distinct(t.id)) AS count FROM " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t ";
            $where = "u.tweet_id = t.id AND ";
            $sql .= sqlSubset($where);
            $sqlresults = mysql_query($sql);
            $numlinktweets = 0;
            if ($sqlresults && mysql_num_rows($sqlresults) > 0) {
                $res = mysql_fetch_assoc($sqlresults);
                $numlinktweets = $res['count'];
            }

            // number of users
            $sql = "SELECT count(distinct(t.from_user_id)) as count FROM " . $esc['mysql']['dataset'] . "_tweets t ";
            $sql .= sqlSubset();
            $sqlresults = mysql_query($sql);
            $data = mysql_fetch_assoc($sqlresults);
            $numusers = $data["count"];

            // see whether the relations table exists
            $show_relations_export = FALSE;
            //$sql = "SHOW TABLES LIKE '" . $esc['mysql']['dataset'] . "_relations'";
            //if (mysql_num_rows(mysql_query($sql)) == 1)
            //    $show_relations_export = TRUE;
            // see whether URLs are expanded @todo
            $show_url_export = false;
            if ($numlinktweets) {
                $sql = "SELECT count(u.id) as count FROM " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t ";
                $where = "u.tweet_id = t.id AND u.error_code != '' AND ";
                $sql .= sqlSubset($where);
                $rec = mysql_query($sql);
                if ($rec && mysql_num_rows($rec) > 0) {
                    $res = mysql_fetch_assoc($rec);
                    if (($res['count'] / $numlinktweets) > 0.5)
                        $show_url_export = true;
                }
            }
            // see whether the lang table exists
            $show_lang_export = FALSE;
            $sql = "SHOW TABLES LIKE '" . $esc['mysql']['dataset'] . "_lang'";
            if (mysql_num_rows(mysql_query($sql)) == 1)
                $show_lang_export = TRUE;

            // get data for the line graph
            $linedata = array();
            $curdate = strtotime($esc['datetime']['startdate']);

            $period = $graph_resolution;
            if (!isset($_GET['graph_resolution']) || $_GET['graph_resolution'] == '') {
                if ($curdate >= strtotime($esc['datetime']['enddate']) - 2 * 86400)
                    $period = "hour";
            }


            // initialize with empty dates
            while ($curdate <= strtotime($esc['datetime']['enddate'])) {
                if ($period == "day")
                    $thendate = $curdate + 86400;
                elseif ($period == "hour")
                    $thendate = $curdate + 3600;
                elseif ($period == "minute")
                    $thendate = $curdate + 60;

                if ($period == "day")
                    $tmp = strftime("%Y-%m-%d", $curdate);
                elseif ($period == "hour")
                    $tmp = strftime("%Y-%m-%d %H:00", $curdate);
                else
                    $tmp = strftime("%Y-%m-%d %H:%M", $curdate);

                $linedata[$tmp] = array();
                $linedata[$tmp]["tweets"] = 0;
                $linedata[$tmp]["users"] = 0;
                $linedata[$tmp]["locations"] = 0;
                $linedata[$tmp]["geolocs"] = 0;
                $linedata[$tmp]["full"] = 0;

                $curdate = $thendate;
            }

            // overwrite zeroed dates
            $sql = "SELECT COUNT(t.text) as count, COUNT(DISTINCT(t.from_user_name)) as usercount, COUNT(DISTINCT(t.location)) as loccount, SUM(if(t.geo_lat != '0.000000', 1, 0)) AS geocount, ";
            if ($period == "day")
                $sql .= "DATE_FORMAT(t.created_at,'%Y-%m-%d') datepart ";
            elseif ($period == "hour")
                $sql .= "DATE_FORMAT(t.created_at,'%Y-%m-%d %H:00') datepart ";
            else
                $sql .= "DATE_FORMAT(t.created_at,'%Y-%m-%d %H:%i') datepart ";
            $sql .= "FROM " . $esc['mysql']['dataset'] . "_tweets t ";
            $sql .= sqlSubset();
            $sql .= "GROUP BY datepart ORDER BY datepart";

            $rec = mysql_query($sql);
            while ($res = mysql_fetch_assoc($rec)) {
                $linedata[$res['datepart']]["tweets"] = $res['count'];
                $linedata[$res['datepart']]["users"] = $res['usercount'];
                $linedata[$res['datepart']]["locations"] = $res['loccount'];
                $linedata[$res['datepart']]["geolocs"] = $res['geocount'];
            }

            if (isset($_GET['query']) && $_GET["query"] != "") {

                $sql = "SELECT COUNT(t.text) as count, ";
                if ($period == "day")
                    $sql .= "DATE_FORMAT(t.created_at,'%Y-%m-%d') datepart ";
                elseif ($period == "hour")
                    $sql .= "DATE_FORMAT(t.created_at,'%Y-%m-%d %H:00') datepart ";
                else
                    $sql .= "DATE_FORMAT(t.created_at,'%Y-%m-%d %H:%i') datepart ";
                $sql .= "FROM " . $esc['mysql']['dataset'] . "_tweets t ";
                $sql .= "WHERE t.created_at >= '" . $esc['datetime']['startdate'] . "' AND t.created_at <= '" . $esc['datetime']['enddate'] . "' ";
                $sql .= "GROUP BY datepart ORDER BY datepart";
                $rec = mysql_query($sql);

                while ($res = mysql_fetch_assoc($rec)) {
                    $linedata[$res['datepart']]["full"] = $res['count'];
                }
            }
            ?>

            <fieldset class="if_parameters">

                <legend>Overview of your selection</legend>

                <div id="if_panel">

                    <div id="if_panel_info" class="if_panel_box">

                        <table>
                            <tr>
                                <td class="tbl_head" valign="top">Dataset:</td><td width="450"><?php echo $datasets[$dataset]['bin'] . " (" . preg_replace("/,/", ", ", $datasets[$dataset]['keywords']) . ")"; ?>

                                </td>
                            </tr>

                            <tr>
                                <td class="tbl_head">Search query:</td><td><?php echo $esc['mysql']['query']; ?></td>
                            </tr>

                            <tr>
                                <td class="tbl_head">Comments:</td><td><?php echo $datasets[$dataset]['comments']; ?></td>
                            </tr>

                            <tr>
                                <td class="tbl_head">Exclude:</td><td><?php echo $esc['mysql']['exclude']; ?></td>
                            </tr>

                            <tr>
                                <td class="tbl_head">From user:</td><td><?php echo $esc['mysql']['from_user_name']; ?></td>
                            </tr>
                            <tr>
                                <td class="tbl_head">From twitter client: </td><td><?php echo $esc['mysql']['from_source']; ?></td>
                            </tr>
                            <tr>
                                <td class="tbl_head">(Part of) URL:</td><td><?php echo $esc['mysql']['url_query']; ?></td>
                            </tr>
                            <?php if (dbserver_has_geo_functions()) { ?>
                                <tr>
                                    <td class="tbl_head">GEO polygon:</td><td><?php echo $esc['mysql']['geo_query']; ?></td>
                                </tr>
                            <?php } ?>
                            <tr>
                                <td class="tbl_head">Startdate:</td><td><?php echo $startdate; ?></td>
                            </tr>
                            <tr>
                                <td class="tbl_head">Enddate:</td><td><?php echo $enddate; ?></td>
                            </tr>
                            <tr>
                                <td class="tbl_head">Number of tweets:</td><td><?php echo number_format($numtweets, 0, ",", "."); ?></td>
                            </tr>
                            <tr>
                                <td class="tbl_head">Number of distinct users:</td><td><?php echo number_format($numusers, 0, ",", "."); ?></td>
                            </tr>
                        </table>

                    </div>

                    <div id="if_panel_linkchart" class="if_panel_box"></div>

                    <script type="text/javascript">

                        var data = new google.visualization.DataTable();
                        data.addColumn('string', 'Slice');
                        data.addColumn('number', 'Percent');
                        data.addRows(2);
                        data.setValue(0, 0, 'Tweets containing links');
                        data.setValue(0, 1, <?php echo $numlinktweets; ?>);
                        data.setValue(1, 0, 'Tweets containing no links');
                        data.setValue(1, 1, <?php echo $numtweets - $numlinktweets; ?>);

                        var chart = new google.visualization.PieChart(document.getElementById('if_panel_linkchart'));
                        chart.draw(data, {width: 380, height: 160});

                    </script>

                </div>

                <hr />

                <div id="if_panel_linegraph"></div>
                <div class='svglink'>
                    <div class='generate_svglink' onclick="saveSvg('if_panel_linegraph')">Generate SVG</div>
                    <div class='download_svglink' id="download_if_panel_linegraph"></div>
                </div>
                <br />

                <div id="if_panel_linegraph_norm"></div>

                <script type="text/javascript">

                    var data = new google.visualization.DataTable();

                    data.addColumn('string', 'Date');
                    data.addColumn('number', 'Tweets');
                    data.addColumn('number', 'Users');
                    data.addColumn('number', 'Locations');
                    data.addColumn('number', 'Geo coded');

<?php
echo "data.addRows(" . count($linedata) . ");";

$counter = 0;

foreach ($linedata as $key => $value) {

    echo "data.setValue(" . $counter . ", 0, '" . $key . "');";
    echo "data.setValue(" . $counter . ", 1, " . $value["tweets"] . ");";
    echo "data.setValue(" . $counter . ", 2, " . $value["users"] . ");";
    echo "data.setValue(" . $counter . ", 3, " . $value["locations"] . ");";
    echo "data.setValue(" . $counter . ", 4, " . $value["geolocs"] . ");";

    $counter++;
}
?>

    var chart = new google.visualization.LineChart(document.getElementById('if_panel_linegraph'));
    chart.draw(data, {width:1000, height:390, fontSize:9, lineWidth:1, hAxis:{slantedTextAngle:90, slantedText:true}, chartArea:{left:50,top:10,width:850,height:300}});

                </script>

                <?php if (isset($_GET['query']) && $_GET["query"] != "") { ?>

                    <script type="text/javascript">

                        var data = new google.visualization.DataTable();

                        data.addColumn('string', 'Date');
                        data.addColumn('number', 'Norm Query (%)');

    <?php
    echo "data.addRows(" . count($linedata) . ");";

    $counter = 0;

    foreach ($linedata as $key => $value) {

        $norm = ($value["full"] == 0 || !isset($value['tweets'])) ? 0 : round($value["tweets"] / $value["full"] * 100);

        echo "data.setValue(" . $counter . ", 0, '" . $key . "');";
        echo "data.setValue(" . $counter . ", 1, " . $norm . ");";

        $counter++;
    }
    ?>

        var chart = new google.visualization.LineChart(document.getElementById('if_panel_linegraph_norm'));
        chart.draw(data, {width:1000, height:190, fontSize:9, lineWidth:1, hAxis:{slantedTextAngle:90, slantedText:true}, vAxis:{minValue:0,maxValue:100}, chartArea:{left:50,top:10,width:850,height:100}});

                    </script>
                    <div class='svglink'>
                        <div class='generate_svglink' onclick="saveSvg('if_panel_linegraph_norm')">Generate SVG</div>
                        <div class='download_svglink' id="download_if_panel_linegraph_norm"></div>
                    </div>
                    <br />
                <?php } ?>

                <div class="txt_desc"><br />Date and time are in GMT (London).</div>

                <form action="index.php" method="get" id="form2">
                    <table>
                        <tr>
                            <td class="tbl_head">Graph resolution</td>
                            <td>
                                <input type='radio' name='graph_resolution' value="day" <?php if ($graph_resolution == "day") echo "CHECKED"; ?>/> days
                                <input type='radio' name='graph_resolution' value="hour" <?php if ($graph_resolution == "hour") echo "CHECKED"; ?>/> hours
                                <input type='radio' name='graph_resolution' value="minute" <?php if ($graph_resolution == "minute") echo "CHECKED"; ?>/> minutes
                            </td>
                            <td><input type="submit" value="update graph" onclick="sendUrl('index.php');return false;" /></td>
                        </tr>
                    </table>
                </form>

            </fieldset>

            <?php
            sentiment_graph();
            ?>

            <fieldset class="if_parameters">

                <legend>Export selected data</legend>

                <p class="txt_desc">All exports have the following filename convention: {dataset}-{startdate}-{enddate}-{query}-{exclude}-{from_user_name}-{from_user_lang}-{url_query}-{module_name}-{module_settings}-{dmi-tcat_version}.{filetype}</p>

                <p>
                    <div class='txt_desc' style='background-color: #eee; padding: 5px;'>Output format for tables:
                        <form>
                            <input type='radio' name="outputformat" value="csv"<?php if ($outputformat == 'csv') print " CHECKED"; ?>>CSV (comma-separated)</input>
                            <input type='radio' name="outputformat" value="tsv"<?php if ($outputformat == 'tsv') print " CHECKED"; ?>>TSV (tab-separated)</input>
                        </form>
                    </div>
                </p>

                <h2>Tweet statistics and activity metrics</h2>

                <div class="if_export_block">

                    <div class="txt_desc">All statistics and activity metrics come as a .csv file which you can open in Excel or similar.</div>

                    <div class='txt_desc' style='background-color: #eee; padding: 5px;'>Here you can select how the statistics should be grouped:
                        <form>
                            <input type='radio' name="interval" value="overall"<?php if ($interval == 'overall') print " CHECKED"; ?>>overall</input>
                            <input type='radio' name="interval" value="hourly"<?php if ($interval == 'hourly') print " CHECKED"; ?>>per hour</input>
                            <input type='radio' name="interval" value="daily"<?php if ($interval == 'daily') print " CHECKED"; ?>>per day</input>
                            <input type='radio' name="interval" value="weekly"<?php if ($interval == 'weekly') print " CHECKED"; ?>>per week</input>
                            <input type='radio' name="interval" value="monthly"<?php if ($interval == 'monthly') print " CHECKED"; ?>>per month</input>
                            <input type='radio' name="interval" value="yearly"<?php if ($interval == 'yearly') print " CHECKED"; ?>>per year</input>
                            <input type='radio' name="interval" value="custom"<?php if ($interval == 'custom') print " CHECKED"; ?>>custom:</input>
                            <input type='text' name='customInterval' size='50' value='<?php if (!empty($intervalDates)) print $_REQUEST['customInterval']; else print "YYYY-MM-DD;YYYY-MM-DD;...;YYYY-MM-DD"; ?>'></input>
                        </form>
                    </div>

                    <h3>Tweet stats</h3>

                    <div class="txt_desc">Contains the number of tweets, number of tweets with links, number of tweets with hashtags, number of tweets with mentions, number of retweets, and number of replies</div>
                    <div class="txt_desc">Use: get a feel for the overall characteristics of you data set.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('tweet.stats'+getInterval()); sendUrl('mod.tweet.stats.php');return false;">launch</a></div>

                    <hr />

                    <h3>User stats (overall)</h3>
                    <div class="txt_desc">Contains the min, max, average, Q1, median, Q3, and trimmed mean for: number of tweets per user, urls per user, number of followers, number of friends, nr of tweets, unique users per time interval</div>
                    <div class="txt_desc">Use: get a better feel for the users in your data set.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('user.stats'+getInterval()); sendUrl('mod.user.stats.php');return false;">launch</a></div>

                    <hr />

                    <h3>User stats (individual)</h3>
                    <div class="txt_desc">Lists users and their number of tweets, number of followers, number of friends, how many times they are listed, their UTC time offset, whether the user has a verified account and how many times they appear in the data set.</div>
                    <div class="txt_desc">Use: get a better feel for the users in your data set.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('user.list'+getInterval()); sendUrl('mod.user.list.php');return false;">launch</a></div>

                    <hr/>

                    <h3>Hashtag frequency</h3>
                    <div class="txt_desc">Contains hashtag frequencies.</div>
                    <div class="txt_desc">Use: find out which hashtags are most often associated with your subject.</div>
                    <div class="txt_link"> &raquo;  <a href="index.php?" onclick="var minf = askFrequency(); $('#whattodo').val('hashtag&minf='+minf+getInterval()); sendUrl('index.php');return false;">launch</a></div>

                    <hr />

                    <h3>Hashtag-user activity</h3>
                    <div class="txt_desc">Lists hashtags, the number of tweets with that hashtag, the numnber of distinct users tweeting with that hashtag, the number of distinct mentions tweeted together with the hashtag, and the total number of mentions tweeted together with the hashtag.</div>
                    <div class="txt_desc">Use: explor user-hashtag activity.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('mod.hashtag_user_activity'); sendUrl('mod.hashtag_user_activity.php');return false;">launch</a></div>

                    <hr />

                    <h3>User visibility (mention frequency)</h3>
                    <div class="txt_desc">Lists usernames and the number of times they were mentioned by others.</div>
                    <div class="txt_desc">Use: find out which users are "influentials".</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="var minf = askFrequency(); $('#whattodo').val('mention&minf='+minf+getInterval()); sendUrl('index.php');return false;">launch</a></div>

                    <hr />

                    <h3>User activity (tweet frequency)</h3>
                    <div class="txt_desc">Lists usernames and the amount of tweets posted.</div>
                    <div class="txt_desc">Use: find the most active tweeters, see if the dataset is dominated by certain twitterati.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="var minf = askFrequency(); $('#whattodo').val('user&minf='+minf+getInterval()); sendUrl('index.php');return false;">launch</a></div>

                    <hr />

                    <h3>User activity + visibility (tweet+mention frequency)</h3>
                    <div class="txt_desc">Lists usernames with both tweet and mention counts.</div>
                    <div class="txt_desc">Use: see wether the users mentioned are also those who tweet a lot.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('user-mention'+getInterval()); sendUrl('index.php');return false;">launch</a></div>

                    <hr />

                    <h3>Twitter client frequency</h3>
                    <div class="txt_desc">List the frequency of tweet software sources per interval.</div>
                    <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('sources'+getInterval());sendUrl('mod.sources.stats.php');return false;">launch</a></div>

                    <?php if ($show_url_export) { ?>
                        <hr />

                        <h3>Url frequency</h3>
                        <div class="txt_desc">Contains the frequencies of tweeted URLs.</div>
                        <div class="txt_desc">Use: find out which contents (articles, videos, etc.) are referenced most often.</div>
                        <div class="txt_link"> &raquo;  <a href="" onclick="var minf = askFrequency(); $('#whattodo').val('urls&minf='+minf+getInterval()); sendUrl('index.php');return false;">launch</a></div>

                        <hr />

                        <h3>Host name frequency</h3>
                        <div class="txt_desc">Contains the frequencies of tweeted domain names.</div>
                        <div class="txt_desc">Use: find out which sources (media, platforms, etc.) are referenced most often.</div>
                        <div class="txt_link"> &raquo;  <a href="" onclick="var minf = askFrequency(); $('#whattodo').val('hosts&minf='+minf+getInterval()); sendUrl('index.php');return false;">launch</a></div>
                    <?php } ?>

                    <hr/>

                    <h3>Identical tweet frequency</h3>
                    <div class="txt_desc">Contains tweets and the number of times they have been (re)tweeted indentically.</div>
                    <div class="txt_desc">Use: get a grasp of the most "popular" content.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="var minf = askFrequency(); $('#whattodo').val('retweet&minf='+minf+getInterval()); sendUrl('index.php');return false;">launch</a></div>

                    <hr/>

                    <h3>Word frequency</h3>
                    <div class="txt_desc">Contains words and the number of times they have been used.</div>
                    <div class="txt_desc">Use: get a grasp of the most used language.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="var lowercase = askLowercase(); var minf = askFrequency(); $('#whattodo').val('word_frequency&lowercase='+lowercase+'&minf='+minf+getInterval());sendUrl('mod.word_frequency.php');return false;">launch</a></div>

                    <hr/>

                    <h3>Media frequency</h3>
                    <div class="txt_desc">Contains media URLs and the number of times they have been used.</div>
                    <div class="txt_desc">Use: get a grasp of the most popular media.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="var minf = askFrequency(); $('#whattodo').val('media_frequency&minf='+minf+getInterval());sendUrl('mod.media_frequency.php');return false;">launch</a></div>


                </div>


                <h2>Tweet exports</h2>

                <div class="if_export_block">

                    <div class="txt_desc">All tweet exports come as a .csv file which you can open in Excel or similar.</div>
                    <div class='txt_desc' style='background-color: #eee; padding: 5px;'>Here you can select additional columns for the tweet exports (more = slower):
                        <form style='display:inline;'>
                            <?php
                            $exportSettings = array();
                            if (isset($_GET['exportSettings']))
                                $exportSettings = $_GET['exportSettings'];
                            ?>
                            <?php if ($show_url_export) { ?>
                                <input type='checkbox' name="exportSettings" value="urls" <?php if (array_search("urls", $exportSettings) !== false) print "CHECKED"; ?>>URLs</input>
                            <?php } ?>
                            <input type='checkbox' name="exportSettings" value="mentions" <?php if (array_search("mentions", $exportSettings) !== false) print "CHECKED"; ?>>mentions</input>
                            <input type='checkbox' name="exportSettings" value="hashtags" <?php if (array_search("hashtags", $exportSettings) !== false) print "CHECKED"; ?>>hashtags</input>
                            <input type='checkbox' name="exportSettings" value="media" <?php if (array_search("media", $exportSettings) !== false) print "CHECKED"; ?>>media</input>
                        </form>
                    </div>

                    <h3>Random set of tweets from selection</h3>
                    <div class="txt_desc">Contains 1000 randomly selected tweets and information about them (user, date created, ...).</div>
                    <div class="txt_desc">Use: a random subset of tweets is a representative sample that can be manually classified and coded much more easily than the full set.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('export_tweets&random=1'+getExportSettings());sendUrl('mod.export_tweets.php');return false;">launch</a></div>
                    <hr />

                    <h3>Export all tweets from selection</h3>
                    <div class="txt_desc">Contains all tweets and information about them (user, date created, ...).</div>
                    <div class="txt_desc">Use: spend time with your data.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('export_tweets'+getExportSettings());sendUrl('mod.export_tweets.php');return false;">export</a></div>
                    <hr />

                    <?php if ($show_lang_export) { ?>
                        <h3>Export all tweets from selection, with language CLD data</h3>
                        <div class="txt_desc">Contains all tweets and information about them (user, date created, ...), plus extra language analysis data.</div>
                        <div class="txt_desc">Use: spend time with your data.</div>
                        <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('export_tweets');sendUrl('mod.export_tweets_lang.php');return false;">export</a></div>
                        <?php if ($show_url_export) { ?>
                            <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('export_tweets&includeUrls=1');sendUrl('mod.export_tweets_lang.php');return false;">export with URLs</a> (much slower)</div>
                        <?php } ?>
                        <hr />
                    <?php } ?>

                    <h3>List each individual retweet</h3>
                    <div class="txt_desc">Lists all retweets (and all the tweets metadata like follower_count) chronologically.</div>
                    <div class="txt_desc">Use: reconstruct retweet chains.</div>
                    <div class="txt_desc"><b>Warning:</b> This script is slow. Small datasets only!</div>
                    <div class="txt_link"> &raquo; <a href="" onclick="var minf = askRetweetFrequency(); $('#whattodo').val('retweets_chain&minf='+minf+getExportSettings());sendUrl('mod.retweets_chain.php');return false;">launch</a></div>

                    <hr />

                    <h3>Only tweets with lat/lon</h3>
                    <div class="txt_desc">Contains only geo-located tweets.</div>
                    <div class="txt_desc"></div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('export_tweets&location=1'+getExportSettings());sendUrl('mod.export_tweets.php');return false;">launch</a></div>

                    <hr />

                    <h3>Export tweet ids</h3>
                    <div class="txt_desc">Contains only the tweet ids from your selection.</div>
                    <div class="txt_desc"></div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('export_tweet_ids');sendUrl('mod.export_tweet_ids.php');return false;">launch</a></div>

                </div>
                <h2>Networks</h2>

                <div class="if_export_block">

                    <div class="txt_desc">All network exports come as .gexf or .gdf files which you can open in <a href='http://www.gephi.org'>Gephi</a> or similar.</div>

                    <h3>Social graph by mentions</h3>
                    <div class="txt_desc">Produces a <a href="http://en.wikipedia.org/wiki/Directed_graph">directed graph</a> based on interactions between users. If a users mentions another one, a directed link is created.
                        The more often a user mentions another, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>"). The "count" value contains the number of tweets for each user in the specified period.</div>
                    <div class="txt_desc">Use: analyze patterns in communication, find "hubs" and "communities", categorize user accounts.</div>
                    <div class="txt_link"> &raquo; <a href="" onclick="var topu = askMentions(); $('#whattodo').val('mention_graph&topu='+topu);sendUrl('mod.mention_graph.php');return false;">launch</a></div>

                    <hr />

                    <h3>Social graph by in_reply_to_status_id</h3>
                    <div class="txt_desc">Produces a <a href="http://en.wikipedia.org/wiki/Directed_graph">directed graph</a> based on interactions between users. If a tweet was written in reply to another one, a directed link is created.</div>
                    <div class="txt_desc">Use: analyze patterns in communication, find "hubs" and "communities", categorize user accounts.</div>
                    <div class="txt_link"> &raquo; <a href="" onclick="var minf = askInteractionFrequency();  if(minf != false) { $('#whattodo').val('interaction_graph&minf='+minf);sendUrl('mod.interaction_graph.php'); } return false;">launch</a></div>

                    <hr />

                    <?php if ($show_relations_export) { ?>
                        <h3>Follower graph</h3>
                        <div class="txt_desc">Produces a <a href="http://en.wikipedia.org/wiki/Directed_graph">directed graph</a> based on follower (friend) relations between users. If a user is friends with another one, a directed link is created.</div>
                        <div class="txt_desc">Use: explore the follower network of a set of users, find shared followees.</div>
                        <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('relations'); sendUrl('mod.relations.php');return false;">launch</a></div>

                        <hr />
                    <?php } ?>

                    <h3>Co-hashtag graph</h3>
                    <div class="txt_desc">Produces an <a href="http://en.wikipedia.org/wiki/Graph_%28mathematics%29#Undirected_graph">undirected graph</a> based on co-word analysis of hashtags. If two hashtags appear in the same tweet, they are linked.
                        The more often they appear together, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").</div>
                    <div class="txt_desc">Use: explore the relations between hashtags, find and analyze sub-issues, distinguish between different types of hashtags (event related, qualifiers, etc.).</div>
                    <div class="txt_link"> &raquo; <a href="" onclick="var minf = askFrequency(); if(minf !== false) { $('#whattodo').val('hashtag_cooc&minf='+minf);sendUrl('mod.hashtag_cooc.php'); } return false;">launch</a> (set minimum frequency)</div><!-- with absolute weighting of cooccurrences</a></div>-->
                    <div class="txt_link"> &raquo; <a href="" onclick="var topu = askTopht(); if(topu !== false) { $('#whattodo').val('hashtag_cooc&topu='+topu);sendUrl('mod.hashtag_cooc.php'); } return false;">launch</a> (get top hashtags)</div>

                    <hr />

                    <h3>Bipartite hashtag-user graph</h3>
                    <div class="txt_desc">Produces a <a href="http://en.wikipedia.org/wiki/Bipartite_graph">bipartite graph</a> based on co-occurence of hashtags and users. If a user wrote a tweet with a certain hashtag, there will be a link between that user and the hashtag.
                        The more often they appear together, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").</div>
                    <div class="txt_desc">Use: explore the relations between users and hashtags, find and analyze which users group around which topics.</div>
                    <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('hashtag_user');sendUrl('mod.hashtag_user.php');return false;">launch</a></div>

                    <hr />

                    <h3>Bipartite hashtag-mention graph</h3>
                    <div class="txt_desc">Produces a <a href="http://en.wikipedia.org/wiki/Bipartite_graph">bipartite graph</a> based on co-occurence of hashtags and @mentions. If an @mention co-occurs in a tweet with a certain hashtag, there will be a link between that @mention and the hashtag.
                        The more often they appear together, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").</div>
                    <div class="txt_desc">Use: explore the relational <i>activity</i> between mentioned users and hashtags, find and analyze which users are considered experts around which topics.</div>
                    <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('mention_hashtags');sendUrl('mod.mention_hashtags.php');return false;">launch</a></div>

                    <h3>Bipartite hashtag-source graph</h3>
                    <div class="txt_desc">Produces a <a href="http://en.wikipedia.org/wiki/Bipartite_graph">bipartite graph</a> based on co-occurence of hashtags and "sources" (the client a
                        tweet was sent from is its source) . If a hashtag is tweeted from a particular client, there will be a link between that client and the hashtag.
                        The more often they appear together, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").</div>
                    <div class="txt_desc">Use: explore the relations between clients and hashtags, find and analyze which clients are related to which topics.</div>
                    <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('mod.sources_hashtags');sendUrl('mod.sources_hashtags.php');return false;">launch</a></div>

                    <?php if ($show_url_export) { ?>
                        <hr />
                        <h3>Bipartite URL-user graph</h3>
                        <div class="txt_desc">Produces a <a href="http://en.wikipedia.org/wiki/Bipartite_graph">bipartite graph</a> based on co-occurence of URLS and users. If a user wrote a tweet with a certain URL, there will be a link between that user and the URL.
                            The more often they appear together, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").</div>
                        <div class="txt_desc">Use: explore the relations between users and URLs, find and analyze which users group around which URLs.</div>
                        <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('url_user');sendUrl('mod.url_user.php');return false;">launch</a></div>

                        <hr />

                        <h3>Bipartite hashtag-URL graph</h3>
                        <div class="txt_desc">Creates a .csv file that contains URLs and the number of times they have co-occured with a particular hashtag.</div>
                        <div class="txt_desc">Creates a .gexf file that contains a <a href="http://en.wikipedia.org/wiki/Bipartite_graph">bipartite graph</a> (.gexf, open in gephi) based on co-occurence of URLs and hashtags. If a URL co-occurs with a certain hashtag, there will be a link between that URL and the hashtag.
                            The more often they appear together, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").</div>
                        <div class="txt_desc">Use: get a grasp of how urls are qualified.</div>
                        <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('url_hashtags'); sendUrl('mod.url_hashtags.php');return false;">launch</a></div>

                        <hr />

                        <h3>Bipartite hashtag-host graph</h3>
                        <div class="txt_desc">Creates a .csv file that contains hosts and the number of times they have co-occured with a particular hashtag.</div>
                        <div class="txt_desc">Creates a .gexf file that contains a <a href="http://en.wikipedia.org/wiki/Bipartite_graph">bipartite graph</a> (.gexf, open in gephi) based on co-occurence of hosts and hashtags. If a hosts co-occurs with a certain hashtag, there will be a link between that host and the hashtag.
                            The more often they appear together, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").</div>
                        <div class="txt_desc">Use: get a grasp of how hosts are qualified.</div>
                        <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('hosts_hashtags'); sendUrl('mod.hosts_hashtags.php');return false;">launch</a></div>
                    <?php } ?>


                </div>

                <h2> Experimental</h2>
                <div class='if_export_block'>

                    <h3>Cascade</h3>
                    <div class="txt_desc">The cascade interface provides a ground level view of tweet activity by charting every single tweet in the current selection. User accounts are distributed vertically; tweets - shown as dots - are spread out horizontally over time. Lines indicate retweets.</div>
                    <div class="txt_desc">Use: visually explore temporal structures and retweets patterns.</div>
                    <div class="txt_desc"><b>Warning:</b> This view requires a large screen and is limited to (very) small data selections.</div>
                    <div class="txt_link"> &raquo; <a href="" onclick="var minf = askCascadeFrequency(); $('#whattodo').val('cascade&minf='+minf);sendUrl('mod.cascade.php');return false;">launch</a></div>

                    <hr/>

                    <h3>The Sankey Maker</h3>
                    <div class="txt_desc">Produces an <a href='http://en.wikipedia.org/wiki/Alluvial_diagram' target='_blank'>alluvial diagram</a>.</div>
                    <div class="txt_desc">Use: plot the relation between various fields such as from_user_lang, hashtags or Twitter client.</div>
                    <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('mod.sankeymaker');sendUrl('mod.sankeymaker.php');return false;">launch</a></div>

                    <hr/>

                    <h3>Associational profile (hashtags)</h3>
                    <div class="txt_desc">Produces an associational profile as well as a time-encoded co-hashtag network.</div>
                    <div class="txt_desc">Use: explore shifts in hashtags associations.</div>
                    <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('hashtag_variability');sendUrl('mod.hashtag_variability.php');return false;">launch</a></div>

                    <?php if (isset($_GET['dataset']) && $_GET['dataset'] == "privacy") { ?>
                        <hr />

                        <h3>Associational profile (words)</h3>
                        <div class="txt_desc">Produces an associational profile as well as a time-encoded co-word network. Nouns etc are extracted via <a href='http://www.ark.cs.cmu.edu/TweetNLP/' target="_blank">TweetNLP</a></div>
                        <div class="txt_desc">Use: explore shifts in word associations.</div>
                        <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('word_variability');sendUrl('mod.word_variability.php');return false;">launch</a></div>
                    <?php } ?>

                    <?php if (isset($_GET['dataset']) && $_GET['dataset'] == "iranelection2013") { ?>
                        <hr/>

                        <h3>Bursty keywords</h3>
                        <div class="txt_desc">Insert a word to see a table with frequencies and burstiness scores per interval. (You can specify the interval under the 'Tweet Statistics and Activity Metrics' heading above.)</div>
                        <div class="txt_desc">Use: find out whether certain words are bursty.</div>
                        <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('trending'+getInterval());sendUrl('mod.trending.php');return false;">launch</a></div>
                    <?php } ?>

                    <?php if (sentiment_exists()) { ?>
                    </div><h2> Sentiment analysis</h2>
                    <div class='if_export_block'>
                        <h3>Export all tweets from selection, with sentiments</h3>
                        <div class="txt_desc">Contains all tweets and information about them (user, date created, ...).</div>
                        <div class="txt_desc">Use: spend time with your data.</div>
                        <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('export_tweets_sentiment');sendUrl('mod.export_tweets_sentiment.php');return false;">export</a></div>
                        <?php if ($show_url_export) { ?>
                            <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('export_tweets_sentiment&includeUrls=1');sendUrl('mod.export_tweets_sentiment.php');return false;">export with URLs</a> (much slower)</div>
                        <?php } ?>
                        <hr />
                        <h3>Social graph by mentions, with sentiments</h3>
                        <div class="txt_desc">Produces a <a href="http://en.wikipedia.org/wiki/Directed_graph">directed graph</a> based on interactions between users. If a users mentions another one, a directed link is created.
                            The more often a user mentions another, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").
                            <br>The "count" value contains the number of tweets for each user in the specified period.<br>
                                    Usernames will contain attributes conveying statistics about the sentiment of the tweets they appear in.
                                    </div>
                                    <div class="txt_desc">Use: analyze patterns in communication, find "hubs" and "communities", categorize user accounts.</div>
                                    <div class="txt_link"> &raquo; <a href="" onclick="var topu = askMentions(); $('#whattodo').val('mention_graph_sentiment&topu='+topu);sendUrl('mod.mention_graph_sentiment.php');return false;">launch</a></div>

                                    <hr />

                                    <h3>Co-hashtag graph, with sentiments</h3>
                                    <div class="txt_desc">Produces an <a href="http://en.wikipedia.org/wiki/Graph_%28mathematics%29#Undirected_graph">undirected graph</a> based on co-word analysis of hashtags. If two hashtags appear in the same tweet, they are linked.
                                        The more often they appear together, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").<br>
                                            Hashtags will contain attributes conveying statistics about the sentiment of the tweets they appear in.
                                    </div>
                                    <div class="txt_desc">Use: explore the relations between hashtags, find and analyze sub-issues, distinguish between different types of hashtags (event related, qualifiers, etc.).</div>
                                    <div class="txt_link"> &raquo; <a href="" onclick="var minf = askFrequency(); if(minf != false) { $('#whattodo').val('hashtag_cooc_sentiment&minf='+minf);sendUrl('mod.hashtag_cooc_sentiment.php'); } return false;">launch</a> (set minimum frequency)</div><!-- with absolute weighting of cooccurrences</a></div>-->
                                    <div class="txt_link"> &raquo; <a href="" onclick="var topu = askTopht(); if(topu != false) { $('#whattodo').val('hashtag_cooc_sentiment&topu='+topu);sendUrl('mod.hashtag_cooc_sentiment.php'); } return false;">launch</a> (get top hashtags)</div>
                                    </div>
                                <?php } ?>

                                </div>
                                </fieldset>

                                <div style="display:none" id="whattodo" />
                                </div>

                                </body>
                                </html>
