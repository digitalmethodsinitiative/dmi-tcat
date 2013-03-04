<?php
// todo: ask Erik how to protect cells in CSV for mod.random_tweets.php

require_once './common/config.php';
require_once './common/functions.php';

$show_coword = FALSE;

$datasets = get_all_datasets();
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript" src="./scripts/jquery-1.7.1.min.js"></script>

        <script type="text/javascript" src="https://www.google.com/jsapi"></script>

        <script type="text/javascript">
     
            google.load("visualization", "1", {packages:["corechart"]});
     
            //google.setOnLoadCallback(drawChart);
	
            function sendUrl(_file) {
		
                var _d1 = $("#ipt_startdate").val();
                var _d2 = $("#ipt_enddate").val();
		
                if(!_d1.match(/\d{4}-\d{2}-\d{2}/) || !_d2.match(/\d{4}-\d{2}-\d{2}/)) {
                    alert("Please check the date format!");
                    return false;
                }
		
                if(typeof(_file) == "undefined") {	
                    _file = "index.php";
                    $('#whattodo').val('');
                    _prompt = true;
                } else {
                    //_prompt = true; //confirm("This will launch the export script and can take some time. Do you want to proceed?");
                    _prompt = true;
                }
                var _url = 
<?php
if (defined('BASE_URL'))
    print '"' . BASE_URL . '"';
?>
            + _file +
            "?dataset=" + $("#ipt_dataset").val() +
            "&query=" + escape($("#ipt_query").val()) +
            "&exclude=" + escape($("#ipt_exclude").val()) +
            "&from_user_name=" + $("#ipt_from_user").val() +
            "&startdate=" + $("#ipt_startdate").val() +
            "&enddate=" + $("#ipt_enddate").val() +
            "&whattodo=" + $("#whattodo").val();
			
        if(_prompt == true) {
            document.location.href = _url;
        }
    }
	
    function askFrequency() {
        var minf = prompt("Specify the minimum frequency for data to be included in the export:","2");
        return minf;
    }
	
        </script>

    </head>

    <body>

        <h1>Twitter Analytics</h1>

        <fieldset class="if_parameters">

            <legend>Data Selection</legend>

            <h3>Select the dataset:</h3>

            <form action="index.php" method="get">

                <?php
                echo '<select id="ipt_dataset" name="dataset">';

                foreach ($datasets as $key => $set) {

                    $v = ($key == $dataset) ? 'selected="selected"' : "";

                    echo '<option value="' . $key . '" ' . $v . '>' . $set["bin"] . ' --- ' . $set["notweets"] . ' tweets from ' . $set['mintime'] . ' to ' . $set['maxtime'] . '</option>';
                }

                echo "</select>";
                ?>

                <h3>Select parameters:</h3>

                <table>

                    <tr>
                        <td class="tbl_head">Query: </td><td><input type="text" id="ipt_query" name="query" value="<?php echo $query; ?>" /> (empty: containing any text)</td>
                    </tr>

                    <tr>
                        <td class="tbl_head">Exclude: </td><td><input type="text" id="ipt_exclude" name="exclude"  value="<?php echo $exclude; ?>" /> (empty: exclude nothing)</td>
                    </tr>

                    <tr>
                        <td class="tbl_head">From user: </td><td><input type="text" id="ipt_from_user" name="from_user_name"  value="<?php echo $from_user_name; ?>" /> (empty: from any user)</td>
                    </tr>

                    <tr>
                        <td class="tbl_head">Startdate:</td><td><input type="text" id="ipt_startdate" name="startdate" value="<?php echo $startdate; ?>" /> (YYYY-MM-DD)</td>
                    </tr>

                    <tr>
                        <td class="tbl_head">Enddate:</td><td><input type="text" id="ipt_enddate" name="enddate" value="<?php echo $enddate; ?>" /> (YYYY-MM-DD)</td>
                    </tr>

                    <tr>
                        <td><input type="button" onclick="sendUrl()" value="update overview" /></td>
                    </tr>

                </table>

            </form>

        </fieldset>

        <?php
        validate_all_variables();

// count current subsample
        $sql = "SELECT count(distinct(id)) as count FROM " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
        $sql .= sqlSubset();
        //print $sql . "<br>";
        $sqlresults = mysql_query($sql);
        $data = mysql_fetch_assoc($sqlresults);
        $numtweets = $data["count"];
        //print "numtweets $numtweets<bR>";
// count links
        $sql = "SELECT count(u.id) AS count FROM " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t WHERE u.tweet_id = t.id AND ";
        $sql .= sqlSubset();
        //print $sql;
        $sqlresults = mysql_query($sql);
        $numlinktweets = 0;
        if ($sqlresults && mysql_num_rows($sqlresults) > 0) {
            $res = mysql_fetch_assoc($sqlresults);
            $numlinktweets = $res['count'];
        }
        //print "numlinktweets $numlinktweets<bR>";
// see whether all URLs are loaded 
        $sql = "SELECT count(u.id) as count FROM " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t WHERE u.tweet_id = t.id AND u.url_followed != '' AND ";
        $sql .= sqlSubset();
        $show_url_export = false;
        $rec = mysql_query($sql);
        if ($rec && mysql_num_rows($rec) > 0) {
            $res = mysql_fetch_assoc($rec);
            if ($res['count'] / $numlinktweets > 0.9)
                $show_url_export = true;
        }
        //print "share tweets " . $res['count'] . "<bR>";
        if (0) {
            print $sql . "<br>";
            print $res['count'] / $numlinktweets . "<br>";
        }

// get data for the line graph
        $period = ( (strtotime($esc['datetime']['enddate']) - strtotime($esc['datetime']['startdate'])) <= 86400 * 2) ? "hour" : "day"; // @todo
        $curdate = strtotime($esc['datetime']['startdate']);
        $linedata = array();

        $sql = "SELECT COUNT(text) as count, COUNT(DISTINCT from_user_name) as usercount, COUNT(DISTINCT location) as loccount, COUNT(DISTINCT geo_lat) as geocount, ";
        if ($period == "day")
            $sql .= "DATE_FORMAT(t.created_at,'%d.%m') datepart ";
        else
            $sql .= "DATE_FORMAT(t.created_at,'%d. %H:00h') datepart ";
        $sql .= "FROM " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY datepart";
        //print $sql."<br>";
        $rec = mysql_query($sql);
        // initialize with empty dates
        while ($curdate < strtotime($esc['datetime']['enddate'])) {
            $thendate = ($period == "day") ? $curdate + 86400 : $curdate + 3600;

            $tmp = ($period == "day") ? strftime("%d.%m", $curdate) : strftime("%d. %H:%M", $curdate) . "h";
            $linedata[$tmp] = array();
            $linedata[$tmp]["tweets"] = 0;
            $linedata[$tmp]["users"] = 0;
            $linedata[$tmp]["locations"] = 0;
            $linedata[$tmp]["geolocs"] = 0;

            $curdate = $thendate;
        }
        // overwrite found dates
        while ($res = mysql_fetch_assoc($rec)) {
            $linedata[$res['datepart']]["tweets"] = $res['count'];
            $linedata[$res['datepart']]["users"] = $res['usercount'];
            $linedata[$res['datepart']]["locations"] = $res['loccount'];
            $linedata[$res['datepart']]["geolocs"] = $res['geocount'];
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
                            <td class="tbl_head">Search query:</td><td><?php echo $query; ?></td>
                        </tr>

                        <tr>
                            <td class="tbl_head">Exclude:</td><td><?php echo $exclude; ?></td>
                        </tr>

                        <tr>
                            <td class="tbl_head">From user:</td><td><?php echo $from_user_name; ?></td>
                        </tr>

                        <tr>
                            <td class="tbl_head">Startdate:</td><td><?php echo $startdate; ?></td>
                        </tr>

                        <tr>
                            <td class="tbl_head">Enddate:</td><td><?php echo $enddate; ?></td>
                        </tr>

                        <tr>
                            <td class="tbl_head">Number of tweets:</td><td><?php echo $numtweets; ?></td>
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
    chart.draw(data, {width:1000, height:360, fontSize:9, hAxis:{slantedTextAngle:90, slantedText:true}, chartArea:{left:50,top:10,width:850,height:300}});
      
            </script>

            <div class="txt_desc"><br />Date and time are in GMT (London).</div>

        </fieldset>


        <fieldset class="if_parameters">

            <legend>Export selected data</legend>

            <p class="txt_desc">All scripts use this output format: {dataset}_{query}{-exclude}_{startdate}_{enddate}_{from_user_name}_{output type}.{filetype}</p>


            <h2>Frequencies</h2>

            <div class="if_export_block">

                <h3>Hashtag frequency</h3>
                <div class="txt_desc">Creates a .csv file (open in Excel or similar) that contains hashtag (#hashtag) frequencies, per day (date range > 2 days) or per hour (date range 2 days or smaller).</div>
                <div class="txt_desc">Use: find out which hashtags are most often associated with your subject.</div>
                <div class="txt_link"> &raquo;  <a href="index.php?" onclick="var minf = askFrequency(); $('#whattodo').val('hashtag&minf='+minf); sendUrl('index.php');return false;">launch</a></div>

                <hr />
                <h3>User mention frequency</h3>
                <div class="txt_desc">Creates a .csv file (open in Excel or similar) that lists usernames and the number of times they were mentioned by others, per day (date range > 2 days) or per hour (date range 2 days or smaller).</div>
                <div class="txt_desc">Use: find out which users are "influentials".</div>
                <div class="txt_link"> &raquo;  <a href="" onclick="var minf = askFrequency(); $('#whattodo').val('mention&minf='+minf); sendUrl('index.php');return false;">launch</a></div>

                <hr />
                <h3>User tweet frequency</h3>
                <div class="txt_desc">Creates a .csv file (open in Excel or similar) that lists usernames and how many tweets they posted, per day (date range > 2 days) or per hour (date range 2 days or smaller).</div>
                <div class="txt_desc">Use: find the most active tweeters, see if the dataset is dominated by certain twitterati.</div>
                <div class="txt_link"> &raquo;  <a href="" onclick="var minf = askFrequency(); $('#whattodo').val('user&minf='+minf); sendUrl('index.php');return false;">launch</a></div>

                <hr />
                <h3>User tweet+mention frequency</h3>
                <div class="txt_desc">Creates a .csv file (open in Excel or similar) that lists usernames and both tweet and mention frequencies, per day (date range > 2 days) or per hour (date range 2 days or smaller).</div>
                <div class="txt_desc">Use: see wether the users mentioned are also those who tweet a lot.</div>
                <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('user-mention'); sendUrl('index.php');return false;">launch</a></div>

                <?php if ($show_url_export) { ?>
                    <hr />
                    <h3>Url frequency</h3>
                    <div class="txt_desc">Creates a .csv file (open in Excel or similar) that contains the frequencies of tweeted URLs, per day (date range > 2 days) or per hour (date range 2 days or smaller).</div>
                    <div class="txt_desc">Use: find out which contents (articles, videos, etc.) are referenced most often.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="var minf = askFrequency(); $('#whattodo').val('urls&minf='+minf); sendUrl('index.php');return false;">launch</a></div>

                    <hr />
                    <h3>Host name frequency</h3>
                    <div class="txt_desc">Creates a .csv file (open in Excel or similar) that contains the frequencies of tweeted domain names, per day (date range > 2 days) or per hour (date range 2 days or smaller).</div>
                    <div class="txt_desc">Use: find out which sources (media, platforms, etc.) are referenced most ofter.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="var minf = askFrequency(); $('#whattodo').val('hosts&minf='+minf); sendUrl('index.php');return false;">launch</a></div>
                <?php } ?>

            </div>


            <h2>Tweets</h2>

            <div class="if_export_block">

                <h3>Identical tweet frequency</h3>
                <div class="txt_desc">Creates a .csv file (open in Excel or similar) that contains tweets and the number of times they have been retweeted indentically, per day (date range > 2 days) or per hour (date range 2 days or smaller).</div>
                <div class="txt_desc">Use: get a grasp of the most "popular" content.</div>
                <div class="txt_link"> &raquo;  <a href="" onclick="var minf = askFrequency(); $('#whattodo').val('retweet&minf='+minf); sendUrl('index.php');return false;">launch</a></div>

                <hr />
                <h3>Random set of tweets</h3>
                <div class="txt_desc">Creates a .csv file (open in Excel or similar) that contains a specified number of randomly selected tweets and information about them (user, date created, ...).</div>
                <div class="txt_desc">Use: a random subset of tweets is a representative sample that can be manually classified and coded much more easily than the full set.</div>
                <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('random_tweets');sendUrl('mod.random_tweets.php');return false;">launch</a></div>

                <hr />
                <h3>Tweets with geo location</h3>
                <div class="txt_desc">Creates a .csv file (open in Excel or similar) that contains only tweets with a lat/lon.</div>
                <div class="txt_desc"></div>
                <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('location');sendUrl('mod.location.php');return false;">launch</a></div>

            </div>


            <h2>Network</h2>

            <div class="if_export_block">

                <h3>Social graph by mentions</h3>
                <div class="txt_desc">Produces a <a href="http://en.wikipedia.org/wiki/Directed_graph">directed graph</a> (.gdf, open in gephi) based on interactions between users. If a users mentions another one, a directed link is created.
                    The more often a user mentions another, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>"). The "count" value contains the number of tweets for each user in the specified period.</div>
                <div class="txt_desc">Use: analyze patterns in communication, find "hubs" and "communities", categorize user accounts.</div>
                <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('mention_graph');sendUrl('mod.mention_graph.php');return false;">launch</a></div>

                <hr />

                <h3>Co-hashtag analysis</h3>
                <div class="txt_desc">Produces an <a href="http://en.wikipedia.org/wiki/Graph_%28mathematics%29#Undirected_graph">undirected graph</a> (.gdf, open in gephi) based on co-word analysis of hashtags. If two hashtags appear in the same tweet, they are linked.
                    The more often they appear together, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").</div>
                <div class="txt_desc">Use: explore the relations between hashtags, find and analyze sub-issues, distinguish between different types of hashtags (event related, qualifiers, etc.).</div>
                <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('hashtag_cooc');sendUrl('mod.hashtag_cooc.php');return false;">launch</a></div><!-- with absolute weighting of cooccurrences</a></div>-->
                <!-- <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('hashtag_cooc&probabilityOfAssociation=1');sendUrl('mod.hashtag_cooc.php');return false;">launch with cooccurrence weight normalization</a></div> -->
                <hr />
                <?php if ($show_coword) { ?>
                    <h3>Co-word analysis</h3>
                    <div class="txt_desc">Produces an <a href="http://en.wikipedia.org/wiki/Graph_%28mathematics%29#Undirected_graph">undirected graph</a> (.gdf, open in gephi) based on co-word analysis of the words found in tweets. If two words appear in the same tweet, they are linked.
                        The more often they appear together, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").</div>
                    <div class="txt_desc">Use: explore the relations between words, find and analyze sub-issues, distinguish between different types of words.</div>
                    <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('word_cooc');sendUrl('mod.word_cooc.php');return false;">launch with absolute weighting of coorccurrences</a></div>
                    <!--        <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('word_cooc&probabilityOfAssociation=1');sendUrl('mod.word_cooc.php');return false;">launch with cooccurrence weight normalization</a></div> -->
                <?php } ?>
            </div>
            <h2> Experimental</h2>
            <div class='if_export_block'>

                <h3>Associational profile</h3>
                <div class="txt_desc">Produces an associational profile as well as a time-encoded co-hashtag network.</div>
                <div class="txt_desc">Use: explore shifts in hashtags associations.</div>
                <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('hashtag_variability');sendUrl('mod.hashtag_variability.php');return false;">launch</a></div>

                <hr />
                <h3>Hashtag-user frequency</h3>
                <div class="txt_desc">Produces a <a href="http://en.wikipedia.org/wiki/Bipartite_graph">bipartite graph</a> (.gexf, open in gephi) based on co-occurence of hashtags and users. If a user wrote a tweet with a certain hashtag, there will be a link between that user and the hashtag.
                    The more often they appear together, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").</div>
                <div class="txt_desc">Use: explore the relations between users and hashtags, find and analyze which users group around which topics.</div>
                <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('hashtag_user');sendUrl('mod.hashtag_user.php');return false;">launch</a></div>

                <hr />
                <h3>Hashtag-mention frequency</h3>
                <div class="txt_desc">Produces a <a href="http://en.wikipedia.org/wiki/Bipartite_graph">bipartite graph</a> (.gexf, open in gephi) based on co-occurence of hashtags and @replies. If an @reply co-occurs in a tweet with a certain hashtag, there will be a link between that @reply and the hashtag.
                    The more often they appear together, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").</div>
                <div class="txt_desc">Use: explore the relational <i>activity</i> between mentioned users and hashtags, find and analyze which users are considered experts around which topics.</div>
                <div class="txt_link"> &raquo; <a href="" onclick="$('#whattodo').val('mention_hashtags');sendUrl('mod.mention_hashtags.php');return false;">launch</a></div>

                <?php if ($show_url_export) { ?>
                    <hr />
                    <h3>URL hashtag co-occurence</h3>
                    <div class="txt_desc">Creates a .csv file (open in Excel or similar) that contains URLs and the number of times they have co-occured with a particular hashtag.</div>
                    <div class="txt_desc">Creates a .gexf file (open in Gephi) that contains a <a href="http://en.wikipedia.org/wiki/Bipartite_graph">bipartite graph</a> (.gexf, open in gephi) based on co-occurence of URLs and hashtags. If a URL co-occurs with a certain hashtag, there will be a link between that URL and the hashtag.
                        The more often they appear together, the stronger the link ("<a href="http://en.wikipedia.org/wiki/Weighted_graph#Weighted_graphs_and_networks">link weight</a>").</div>
                    <div class="txt_desc">Use: get a grasp of how urls are qualified.</div>
                    <div class="txt_link"> &raquo;  <a href="" onclick="$('#whattodo').val('url_hashtags'); sendUrl('mod.url_hashtags.php');return false;">launch</a></div>
                <?php } ?>

                <div style="display:none" id="whattodo" />

        </fieldset>


    </body>
</html>
