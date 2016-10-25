<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/Coword.class.php';
validate_all_variables();
dataset_must_exist();
$dbh = pdo_connect();
// NOTICE: because this script does parallel queries, we must use buffered query mode
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Associational profiles</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="./css/main.css" type="text/css" />
        <link rel="stylesheet" href="./css/tablesorter/blue/style.css" type="text/css" media="print, projection, screen" />

        <script type="text/javascript" src="./scripts/raphael-min.js"></script>
        <script type='text/javascript' src='./scripts/jquery-1.7.1.min.js'></script>
        <script type="text/javascript" src="./scripts/tablesorter/jquery.tablesorter.min.js"></script>
        <script type="text/javascript">
            $(document).ready(function() {
                $("#metrics").tablesorter();
                $("#cowordFrequencyOverall").tablesorter({sortList: [[1,1],[0,0]] });
                var Input = $('input[name=customInterval]');
                var default_value = Input.val();

                Input.focus(function() {
                    if(Input.val() == default_value) Input.val("");
                }).blur(function(){
                    if(Input.val().length == 0) Input.val(default_value);
                });

                apply_vis_settings();

            });
            function generate_permalink() {
                var permalink = "";
                permalink += "&vis_colorcoding=" + $('[name="vis_colorcoding"]').val();
                permalink += "&vis_labels=";
                if($('#vis_labels').is(':checked')) {
                    permalink += "true";
                } else {
                    permalink += "false";
                }
                permalink += "&vis_sorting=";
                if($('#vis_sorting').is(':checked')) {
                    permalink += "true";
                } else {
                    permalink += "false";
                }
                permalink = window.location.href.replace(/&vis_colorcoding=\w+/,"").replace(/&vis_labels=\w+/,"").replace(/&vis_sorting=\w+/,"") + permalink;
                $('#permalink').text(permalink);
                $('#permalink').attr('href',permalink);
            }

            function apply_vis_settings() {

                var currentlink = window.location.href;
                $('#permalink').text(currentlink);
                $('#permalink').attr('href',currentlink);
                var vars = currentlink.match(/&vis_colorcoding=(.+?)&vis_labels=(.+?)&vis_sorting=(.+)/);
                if(vars) {
                    // @todo, also set via hidden inputs for form
                    colorcode(vars[1]);
                    $('[name="vis_colorcoding"] option[value='+vars[1]+']').attr('selected', 'selected');
                    if(vars[2] == "true") {
                        changeInterface('labels',true);
                        $('#vis_labels').prop('checked',true);
                    }
                    if(vars[3] == "true") {
                        changeInterface('sorting',true);
                        $('#vis_sorting').prop('checked',true);
                    }
                }
            }


        </script>
    </head>

    <body>

        <h1>TCAT :: Associational profiles</h1>


        <fieldset class="if_parameters">

            <legend>Top tags</legend>
            <?php
            $title = "Top tags for";
            if (!empty($query))
                $title .= " subselection <i>$query</i> of ";  // @todo but not in ...
            $title .= "dataset <i>$dataset</i> ";
            if (!empty($startdate))
                $title .= " which ranges from <i>$startdate</i> ";
            if (!empty($enddate))
                $title .= "until <i>$enddate</i>"; // @todo exclude, from_user_name
            ?>
            <div class="txt_desc"><?php echo $title; ?></div>
            <div class="txt_desc"><?php printTopHashtags(); ?></div>
        </fieldset>
        <fieldset class="if_parameters">

            <legend>Associational profile settings</legend>


            <table>

                <form action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="GET">
                    <tr>
                        <td align='right'>Keyword to generate associational profile</td>
                        <td><input type="text" name="keywordToTrack" value="<?php echo $keywordToTrack; ?>" /> </td>
                    </tr>
                    <tr>
                        <td align='right'>Minimum co-word frequency <i>over all periods</i> for a word to be included</t>
                            <td><input type="text" name="minimumCowordFrequencyOverall" value="<?php echo $minimumCowordFrequencyOverall; ?>" /></td>
                    </tr>
                    <tr>
                        <td align='right'>Minimum co-word frequency <i>per period</i> for a word to be included</t>
                            <td><input type="text" name="minimumCowordFrequencyInterval" value="<?php echo $minimumCowordFrequencyInterval; ?>" /></td>
                    </tr>
                    <tr>
                        <td align='right'>Words to exclude from the graph (comma separated)</t>
                            <td><input type="text" name="excludeFromGraph" value="<?php if (isset($_REQUEST['excludeFromGraph'])) echo $_REQUEST['excludeFromGraph']; ?>" /></td>
                    </tr>
                    <tr>
                        <td align='right'>Choose interval
                        </td><td>
                            <input type='radio' name="interval" value="daily"<?php if ($interval == 'daily') print " CHECKED"; ?>>daily</input>
                            <input type='radio' name="interval" value="weekly"<?php if ($interval == 'weekly') print " CHECKED"; ?>>weekly</input>
                            <input type='radio' name="interval" value="monthly"<?php if ($interval == 'monthly') print " CHECKED"; ?>>monthly</input>
                            <input type='radio' name="interval" value="custom"<?php if ($interval == 'custom') print " CHECKED"; ?>>custom:</input>
                            <input type='text' name='customInterval' size='50' value='<?php if (!empty($intervalDates)) print $_REQUEST['customInterval']; else print "YYYY-MM-DD;YYYY-MM-DD;...;YYYY-MM-DD"; ?>'></input>
                        </td>
                    </tr>
                    <!--
                    <tr>
                        <td></td><td>
                            <input type='checkbox' name='timeseriesGexf'<?php if (isset($_REQUEST['timeseriesGexf'])) echo " CHECKED"; ?>>Generate co-hashtag time-series (GEXF file)</input>
                        </td>
                    </tr>
                    <tr>
                        <td></td><td>
                            <input type='checkbox' name='cohashtagVariability'<?php if (isset($_REQUEST['cohashtagVariability'])) echo " CHECKED"; ?>>Generate co-hashtag variability file (Excel sheet with associational profiles thought up in London, May 2012)</input>
                        </td>
                    </tr>
                    -->
                    <tr><td></td><td><input type='checkbox' name='normalizedCowordFrequency'<?php if (isset($_REQUEST['normalizedCowordFrequency'])) echo " CHECKED"; ?>>Calculate normalized co-word frequency</tr>
                                <tr><td></td><td><input type='checkbox' name='tableOutput'<?php if (isset($_REQUEST['tableOutput'])) echo " CHECKED"; ?>>Display table with (co-word-) frequency values, specificity, etc</td></tr>
                                <tr><td></td><td><input type='checkbox' name='displayOverallCowordFrequencies'<?php if (isset($_REQUEST['displayOverallCowordFrequencies'])) echo " CHECKED"; ?>>Display overall co-word frequency</tr>

                                            <tr>
                                                <td></td><td><input type="submit" value="Get associational profile" /></td>
                                            </tr>
                                            <input type="hidden" name="dataset" value="<?php echo $dataset; ?>" />
                                            <input type="hidden" name="query" value="<?php echo $query; ?>" />
                                            <input type="hidden" name="exclude" value="<?php echo ""; ?>" /> <!-- @todo -->
                                            <input type="hidden" name="from_user_name" value="<?php echo $from_user_name; ?>" />
                                            <input type="hidden" name="startdate" value="<?php echo $startdate; ?>" />
                                            <input type="hidden" name="enddate" value="<?php echo $enddate; ?>" />
                                            </form>

                                            </table>
                                            </fieldset>



                                            <?php
                                            $cowordTimeSeries = false;
                                            if (!empty($_REQUEST['timeseriesGexf']) || isset($_REQUEST['cohashtagVariability']))
                                                $cowordTimeSeries = true;

                                            if (!empty($keywordToTrack) || $cowordTimeSeries) {

                                                $collation = current_collation();

                                                // get cowords from database
                                                $sql = "SELECT LOWER(A.text COLLATE $collation) AS h1, LOWER(B.text COLLATE $collation) AS h2 ";
                                                $sql .= ", " . sqlInterval();
                                                $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags A, " . $esc['mysql']['dataset'] . "_hashtags B, " . $esc['mysql']['dataset'] . "_tweets t ";
                                                $sql .= sqlSubset() . " AND ";
                                                $sql .= "LENGTH(A.text)>1 AND LENGTH(B.text)>1 AND ";
                                                $sql .= "LOWER(A.text COLLATE $collation) < LOWER(B.text COLLATE $collation) AND A.tweet_id = t.id AND A.tweet_id = B.tweet_id ";
                                                $sql .= "ORDER BY datepart,h1,h2 ASC";
                                                print $sql . "<br>";

                                                $date = false;

                                                $rec = $dbh->prepare($sql);
                                                $rec->execute();
                                                while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {

                                                    $word = $res['h1'];
                                                    $coword = $res['h2'];

                                                    if (!empty($intervalDates)) {
                                                        if ($date !== groupByInterval($res['datepart'])) {
                                                            $date = groupByInterval($res['datepart']);
                                                            if ($cowordTimeSeries)
                                                                $series[$date] = new Coword;
                                                        }
                                                    } elseif ($date !== $res['datepart']) {
                                                        $date = $res['datepart'];
                                                        if ($cowordTimeSeries)
                                                            $series[$date] = new Coword;
                                                    }

                                                    // construct associational profile
                                                    // retain only words which appear together with our inital word
                                                    if ($word == $keywordToTrack) {
                                                        if (!isset($ap[$word][$date][$coword]))
                                                            $ap[$word][$date][$coword] = 0;
                                                        $ap[$word][$date][$coword]++;
                                                        if (!isset($frequency_coword_total[$coword]))
                                                            $frequency_coword_total[$coword] = 0;
                                                        $frequency_coword_total[$coword]++;
                                                    }
                                                    if ($coword == $keywordToTrack) {
                                                        if (!isset($ap[$coword][$date][$word]))
                                                            $ap[$coword][$date][$word] = 0;
                                                        $ap[$coword][$date][$word]++;
                                                        if (!isset($frequency_coword_total[$word]))
                                                            $frequency_coword_total[$word] = 0;
                                                        $frequency_coword_total[$word]++;
                                                    }

                                                    // construct coword per date
                                                    if ($cowordTimeSeries) {
                                                        $series[$date]->addWord($word);
                                                        $series[$date]->addWord($coword);
                                                        $series[$date]->addCoword($word, $coword, 1);
                                                        unset($series[$date]->words); // as we are adding words manually the frequency would be messed up
                                                    }
                                                }

                                                // get user diversity per hasthag
                                                $sql = "SELECT LOWER(h.text COLLATE $collation) as h1, COUNT(t.from_user_id) as c, COUNT(DISTINCT(t.from_user_id)) AS d ";
                                                $sql .= ", " . sqlInterval();
                                                $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
                                                $where = "h.tweet_id = t.id AND ";
                                                $sql .= sqlSubset($where);
                                                $sql .= "GROUP BY datepart, h1";
                                                //print $sql . "<br>";
                                                $usersForWord = $userDiversity = $distinctUsersForWord = array();
                                                $rec = $dbh->prepare($sql);
                                                $rec->execute();
                                                while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
                                                    $date = $res['datepart'];
                                                    if (!empty($intervalDates))
                                                        $date = groupByInterval($res['datepart']);
                                                    //print "[$date]<br>";
                                                    $word = $res['h1'];
                                                    if (!isset($usersForWord[$date][$word]))
                                                        $usersForWord[$date][$word] = 0;
                                                    $usersForWord[$date][$word] += $res['c'];
                                                    if (!isset($distinctUsersForWord[$date][$word]))
                                                        $distinctUsersForWord[$date][$word] = 0;
                                                    $distinctUsersForWord[$date][$word] += $res['d'];
                                                }
                                                foreach ($distinctUsersForWord as $date => $words) {
                                                    foreach ($words as $word => $distinctUserCount) {
                                                        // (number of unique users using the hashtag) / (frequency of use)
                                                        // This'll give you a value between 0 and 1 where the closer you get to 1 the more diverse its user base is.
                                                        $userDiversity[$date][$word] = round(($distinctUsersForWord[$date][$word] / $usersForWord[$date][$word]) * 100, 2);
                                                    }
                                                }

                                                // get frequency (occurence) of hashtag in full selection
                                                $sql = "SELECT LOWER(A.text COLLATE $collation) AS h1, COUNT(LOWER(A.text COLLATE $collation)) AS frequency";
                                                $sql .= ", " . sqlInterval();
                                                $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags A, " . $esc['mysql']['dataset'] . "_tweets t ";
                                                $sql .= sqlSubset() . " AND ";
                                                $sql .= "LENGTH(A.text)>1 AND ";
                                                $sql .= "A.tweet_id = t.id GROUP BY datepart,h1 ORDER BY datepart,frequency ASC;";
                                                //print $sql . "<br>";
                                                $frequency_word_total = $frequency_word_interval = array();
                                                $rec = $dbh->prepare($sql);
                                                $rec->execute();
                                                while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
                                                    $date = $res['datepart'];
                                                    if (!empty($intervalDates))
                                                        $date = groupByInterval($res['datepart']);
                                                    $word = $res['h1'];
                                                    if (!isset($frequency_word_interval[$date][$word]))
                                                        $frequency_word_interval[$date][$word] = 0;
                                                    $frequency_word_interval[$date][$word] += $res['frequency'];
                                                    // @todo @note $frequency_word_total is not currently used but can be for specificity (if specificity should not be based on interval)
                                                    if (!isset($frequency_word_total[$word]))
                                                        $frequency_word_total[$word] = 0;
                                                    $frequency_word_total[$word]+=$res['frequency'];
                                                }

                                                // get number of tweets in interval
                                                $sql = "SELECT COUNT(t. id) AS numberOfTweets";
                                                $sql .= ", " . sqlInterval();
                                                $sql .= "FROM " . $esc['mysql']['dataset'] . "_tweets t ";
                                                $sql .= sqlSubset() . " ";
                                                $sql .= "GROUP BY datepart ORDER BY datepart";
                                                //print $sql."<bR>";
                                                $rec = $dbh->prepare($sql);
                                                $rec->execute();
                                                while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
                                                    $date = $res['datepart'];
                                                    if (!empty($intervalDates))
                                                        $date = groupByInterval($res['datepart']);
                                                    $numberOfTweets[$date] = $res['numberOfTweets'];
                                                }

                                                if (isset($_REQUEST['normalizedCowordFrequency'])) {
                                                    // get number of tags co-occuring with focus word
                                                    $sql = "SELECT LOWER(A.text COLLATE $collation) AS h1, LOWER(B.text COLLATE $collation) AS h2, COUNT(LOWER(A.text COLLATE $collation)) AS frequency";
                                                    $sql .=", " . sqlInterval();
                                                    $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags A, " . $esc['mysql']['dataset'] . "_hashtags B, " . $esc['mysql']['dataset'] . "_tweets t ";
                                                    $sql .= sqlSubset() . " AND ";
                                                    $sql .= "(A.text COLLATE $collation = '$keywordToTrack' OR B.text COLLATE $collation  = '$keywordToTrack') AND ";
                                                    $sql .= "LENGTH(A.text)>1 AND LENGTH(B.text)>1 AND ";
                                                    $sql .= "LOWER(A.text COLLATE $collation) < LOWER(B.text COLLATE $collation) AND A.tweet_id = t.id AND A.tweet_id = B.tweet_id ";
                                                    $sql .= "GROUP BY datepart,h1,h2 ";
                                                    $sql .= "ORDER BY datepart,h1,h2 ASC";
                                                    //print $sql . "<br>";

                                                    $normalizedCowordFrequency = array();
                                                    $rec = $dbh->prepare($sql);
                                                    $rec->execute();
                                                    while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {

                                                        if ($res['h1'] == $keywordToTrack)
                                                            $word = $res['h2'];
                                                        elseif ($res['h2'] == $keywordToTrack)
                                                            $word = $res['h1'];
                                                        $cowordFrequency = $res['frequency'];
                                                        $date = $res['datepart'];
                                                        if (!empty($intervalDates))
                                                            $date = groupByInterval($res['datepart']);
                                                        if (!isset($normalizedCowordFrequency[$date][$word]))
                                                            $normalizedCowordFrequency[$date][$word] = 0;
                                                        $normalizedCowordFrequency[$date][$word] += $cowordFrequency;
                                                    }
                                                    foreach ($normalizedCowordFrequency as $date => $cowordFrequencies) {
                                                        $sum = array_sum($cowordFrequencies);
                                                        foreach ($cowordFrequencies as $word => $cowordFrequency)
                                                            $normalizedCowordFrequency[$date][$word] = round(($cowordFrequency / $sum) * 100, 2);
                                                    }
                                                    //var_dump($normalizedCowordFrequency); print "<br>";
                                                    // @note normalizedCowordFrequency is calculated for all cowords, while the data_vis discards cowords which do not appear x amount of times over full period
                                                    // @todo @note tf/idf as it incorporates likelihood of a query given the rest of the set, and does not just look locally (i.e. per interval)
                                                    // @toponder what to compare it with full selection (shows overal likelihood) or compared to previous intervals (shows timely progress but has initialization problem)
                                                }

                                                // put data in right format for visualization
                                                $vis_data = array();

                                                // intialize vis_data with all intervals
                                                $sql = "SELECT ";
                                                $sql .= sqlInterval();
                                                $sql .= "FROM " . $esc['mysql']['dataset'] . "_tweets t ";
                                                $sql .= sqlSubset();
                                                $sql .= "GROUP BY datepart";
                                                //print $sql."<bR>";
                                                $rec = $dbh->prepare($sql);
                                                $rec->execute();
                                                while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
                                                    $date = $res['datepart'];
                                                    if (!empty($intervalDates))
                                                        $date = groupByInterval($res['datepart']);
                                                    $vis_data[$date] = array();
                                                }
                                                $excludeFromGraph = array();
                                                if (isset($_REQUEST['excludeFromGraph'])) {
                                                    $excludeFromGraph = explode(",", $_REQUEST['excludeFromGraph']);
                                                    foreach ($excludeFromGraph as $k => $v)
                                                        $excludeFromGraph[$k] = trim($v);
                                                }
                                                foreach ($ap[$keywordToTrack] as $date => $cowords) {
                                                    foreach ($cowords as $word => $frequency_coword) {
                                                        if ($frequency_coword_total[$word] >= $minimumCowordFrequencyOverall && $frequency_coword >= $minimumCowordFrequencyInterval) {
                                                            if (array_search($word, $excludeFromGraph) !== false)
                                                                continue;
                                                            $specificity = round(($frequency_coword / $frequency_word_interval[$date][$word]) * 100, 2); // @note, I am assuming specificity is dependent on the interval
                                                            $vis_data[$date][$word]['cowordFrequency'] = $frequency_coword;
                                                            $vis_data[$date][$word]['wordFrequency'] = $frequency_word_interval[$date][$word];
                                                            $vis_data[$date][$word]['specificity'] = $specificity;
                                                            if (isset($_REQUEST['normalizedCowordFrequency']))
                                                                $vis_data[$date][$word]['normalizedCowordFrequency'] = $normalizedCowordFrequency[$date][$word];
                                                            $vis_data[$date][$word]['normalizedWordFrequency'] = round(($frequency_word_interval[$date][$word] / $numberOfTweets[$date]) * 100, 2);
                                                            $vis_data[$date][$word]['distinctUsersForWord'] = $distinctUsersForWord[$date][$word];
                                                            $vis_data[$date][$word]['userDiversity'] = $userDiversity[$date][$word];
                                                            $vis_data[$date][$word]['wordFrequencyDividedByUniqueUsers'] = round($frequency_word_interval[$date][$word] / $distinctUsersForWord[$date][$word], 2);
                                                            $vis_data[$date][$word]['wordFrequencyMultipliedByUniqueUsers'] = $frequency_word_interval[$date][$word] * $distinctUsersForWord[$date][$word];
                                                        }
                                                        // else
                                                        //  print "skipping $word because {$frequency_coword_total[$word]} >= $minimumCowordFrequencyOverall<bR>";   // @todo: to take into account for normalizedCowordFrequency or not?
                                                    }
                                                }

                                                if (empty($vis_data))
                                                    die("<div class='txt_desc'><br><br>not enough data</div>");

                                                // generate files, if requested
                                                if ($cowordTimeSeries) {
                                                    $filename = get_filename_for_export("hashtagVariability", (isset($_GET['probabilityOfAssociation']) ? "_normalizedAssociationWeight" : ""), "gexf");
                                                    if (!empty($_REQUEST['timeseriesGexf']))
                                                        getGEXFtimeseries($filename, $series);
                                                    if (isset($_REQUEST['cohashtagVariability']))
                                                        variabilityOfAssociationProfiles($filename, $series, $keywordToTrack, $ap);
                                                }
                                                ?>

                                                <p>
                                                    <?php
                                                    // generate visualization
                                                    $datadescription = "Keywords co-occuring at least <i>$minimumCowordFrequencyOverall</i> times (overall) and at least <i>$minimumCowordFrequencyInterval</i> times (per interval) with <i>$keywordToTrack</i> in ";
                                                    if (!empty($query))
                                                        $datadescription .= " subselection <i>$query</i> of ";  // @todo but not in ...
                                                    $datadescription .= "dataset <i>$dataset</i> ";
                                                    if (!empty($startdate))
                                                        $datadescription .= " which ranges from <i>$startdate</i> ";
                                                    if (!empty($enddate))
                                                        $datadescription .= "until <i>$enddate</i>"; // @todo exclude, from_user_name
                                                    print $datadescription . "<br>";
                                                    ?>
                                                    <br><span>Permalink to visualization: <a href='' id='permalink' style='font-size:12px;color:black;text-decoration:none;'></a></span>
                                                        <br><span onclick="encode_as_img_and_link();">make SVG</span><span id="svgdown"></span><br>
                                                                <form id="vis_interface">>
                                                                    <input type="checkbox" onchange="changeInterface('labels',this.checked)" />Show labels in visualization
                                                                    <input type="checkbox" onchange="changeInterface('sorting',this.checked)" />Sort by size
                                                                </form>

                                                                <script type="text/javascript">
    <?php print "var _data = " . json_encode($vis_data); ?>
                                                                </script>
                                                                <p id="visualization"></p>
                                                                <p id="wordlist"></p>
                                                                <script type='text/javascript' src='./scripts/vis.js'></script>
                                                                </p>

                                                                <?php
                                                                if (isset($_REQUEST['tableOutput'])) {
                                                                    print "<hr>Tip: Sort multiple columns simultaneously by holding down the shift key and clicking a second, third or even fourth column header! ";
                                                                    print "<table id='metrics' class='tablesorter'>";
                                                                    print "<thead><tr><th>date</th><th>word</th><th>frequency</th><th>cowordFrequency</th><th>specificity</th><th>normalizedWordFrequency</th><th>normalizedCowordFrequency</th><th>distinctUsersForWord</th><th>userDiversity</th><th>wordFrequencyDividedByUniqueUsers</th><th>wordFrequencyMultipliedByUniqueUsers</th>></tr></thead>";
                                                                    print "<tbody>";
                                                                    foreach ($vis_data as $date => $words) {
                                                                        if (empty($words))
                                                                            print "<tr><td>$date</td><td >no associations here</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td></tr>";
                                                                        foreach ($words as $word => $specs) {
                                                                            $frequency = $specs['wordFrequency'];
                                                                            $normalizedWordFrequency = $specs['normalizedWordFrequency'];
                                                                            $frequency_coword = $specs['cowordFrequency'];
                                                                            $specificity = $specs['specificity'];
                                                                            if (isset($_REQUEST['normalizedCowordFrequency']))
                                                                                $normalizedCowordFrequency = $specs['normalizedCowordFrequency'];
                                                                            else
                                                                                $normalizedCowordFrequency = "";
                                                                            $userDiversity = $specs['userDiversity'];
                                                                            $usersForWord = $specs['distinctUsersForWord'];
                                                                            $wordFrequencyDividedByUniqueUsers = $specs['wordFrequencyDividedByUniqueUsers'];
                                                                            $wordFrequencyMultipliedByUniqueUsers = $specs['wordFrequencyMultipliedByUniqueUsers'];
                                                                            $highlight = "";
                                                                            /* if ($specificity < 100.0 && $specificity > 70.0)
                                                                              $highlight = " style='background-color:yellow'";
                                                                              if ($specificity < 50.0)
                                                                              $highlight = " style='background-color:red'";
                                                                             *
                                                                             */

                                                                            if ($frequency_coword_total[$word] >= $minimumCowordFrequencyOverall && $frequency_coword >= $minimumCowordFrequencyInterval)
                                                                                print "<tr><td>$date</td><td>$word</td><td>$frequency</td><td>$frequency_coword</td><td $highlight>$specificity</td><td>$normalizedWordFrequency</td><td>$normalizedCowordFrequency</td><td>$usersForWord</td><td>$userDiversity</td><td>$wordFrequencyDividedByUniqueUsers</td><td>$wordFrequencyMultipliedByUniqueUsers</td></tr>";
                                                                            else
                                                                                print "<tr><td>$date</td><td>$word</td><td colspan='3'>skipping because {$frequency_coword_total[$word]} < $minimumCowordFrequencyOverall</td></tr>";
                                                                        }
                                                                    }
                                                                    print "</tbody></table>";
                                                                }

                                                                if (isset($_REQUEST['displayOverallCowordFrequencies'])) {
                                                                    print "<table id='cowordFrequencyOverall' class='tablesorter'>";
                                                                    print "<thead><tr><th>coword</th><th>coword frequency</th></tr></thead>";
                                                                    print "<tbody>";

                                                                    arsort($frequency_coword_total);
                                                                    foreach ($frequency_coword_total as $word => $frequency) {
                                                                        if ($frequency >= $minimumCowordFrequencyOverall)
                                                                            print "<tr><td>$word</td><td>$frequency</td></tr>";
                                                                    }
                                                                    print "</tbody></table>";
                                                                }
                                                            }
                                                            ?>
                                                            <P>The associational profiler is a collaboration between the <a href="http://www.csisponline.net/">Centre for the Study of Invention and Social Process</a> (Goldsmiths) and the <a href="http://digitalmethods.net">Digital Methods Initiative</a> (University of Amsterdam).</P>
                                                            </body>
                                                            </html>

                                                            <?php

                                                            function printTopHashtags() {
                                                                global $esc;
                                                                global $dbh;

                                                                $collation = current_collation();

                                                                $results = array();
                                                                $sql = "SELECT COUNT(hashtags.text COLLATE $collation) AS count, LOWER(hashtags.text COLLATE $collation) AS toget ";
                                                                $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags hashtags, " . $esc['mysql']['dataset'] . "_tweets t ";
                                                                $sql .= sqlSubset("t.id = hashtags.tweet_id AND ");
                                                                $sql .= " GROUP BY toget ORDER BY count DESC limit 10";
                                                                //print $sql."<br>";

                                                                $rec = $dbh->prepare($sql);
                                                                $rec->execute();
                                                                while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
                                                                    $out .= $res['toget'] . " (" . $res['count'] . "), ";
                                                                }
                                                                print substr($out, 0, -2);
                                                            }

                                                            function getGEXFtimeseries($filename, $series) {
                                                                include_once(__DIR__ . '/common/Gexf.class.php');
                                                                $gexf = new Gexf();
                                                                $gexf->setTitle("Co-word " . $filename);
                                                                $gexf->setEdgeType(GEXF_EDGE_UNDIRECTED);
                                                                $gexf->setMode(GEXF_MODE_DYNAMIC);
                                                                $gexf->setTimeFormat(GEXF_TIMEFORMAT_DATE);
                                                                $gexf->setCreator("tools.digitalmethods.net");
                                                                foreach ($series as $time => $cw) {

                                                                    $w = $cw->getWords();
                                                                    $cw = $cw->getCowords();
                                                                    foreach ($cw as $word => $cowords) {
                                                                        foreach ($cowords as $coword => $coword_frequency) {
                                                                            $node1 = new GexfNode($word);
                                                                            if (isset($w[$word]))
                                                                                $node1->addNodeAttribute("word_frequency", $w[$word], $type = "int");
                                                                            $gexf->addNode($node1);
                                                                            //if ($documentsPerWords[$word] > $threshold)
                                                                            //    $node1->setNodeColor(0, 255, 0, 0.75);
                                                                            $gexf->nodeObjects[$node1->id]->addNodeSpell($time, $time);
                                                                            $node2 = new GexfNode($coword);
                                                                            if (isset($w[$coword]))
                                                                                $node2->addNodeAttribute("word_frequency", $w[$word], $type = "int");
                                                                            $gexf->addNode($node2);
                                                                            //if ($documentsPerWords[$coword] > $threshold)
                                                                            //    $node2->setNodeColor(0, 255, 0, 0.75);
                                                                            $gexf->nodeObjects[$node2->id]->addNodeSpell($time, $time);
                                                                            $edge_id = $gexf->addEdge($node1, $node2, $coword_frequency);
                                                                            $gexf->edgeObjects[$edge_id]->addEdgeSpell($time, $time);
                                                                        }
                                                                    }
                                                                }
                                                                $gexf->render();
                                                                file_put_contents($filename, $gexf->gexfFile);

                                                                echo '<fieldset class="if_parameters">';
                                                                echo '<legend>Your co-hashtag time-series File</legend>';
                                                                echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
                                                                echo '</fieldset>';
                                                            }

                                                            function variabilityOfAssociationProfiles($filename, $series, $keywordToTrack, $ap) {

                                                                if (empty($series) || empty($keywordToTrack))
                                                                    die('not enough data');
                                                                $filename = get_filename_for_export("hashtagVariability", "_variabilityOfAssociationProfiles", "gexf");
                                                                // group per slice
                                                                // per keyword
                                                                // 	get associated words (depth 1) per slice
                                                                // 	get frequency, degree, ap variation (calculated on cooc frequency), words in, words out, ap keywords
                                                                $degree = array();
                                                                foreach ($series as $time => $cw) {
                                                                    $cw = $cw->getCowords();
                                                                    foreach ($cw as $word => $cowords) {
                                                                        foreach ($cowords as $coword => $frequency) {

                                                                            // save how many time slices the word appears
                                                                            $words[$word][$time] = 1;
                                                                            $words[$coword][$time] = 1;


                                                                            // keep track of degree per word per time slice
                                                                            if (array_key_exists($word, $degree) === false)
                                                                                $degree[$word] = array();
                                                                            if (array_key_exists($coword, $degree) === false)
                                                                                $degree[$coword] = array();
                                                                            if (array_key_exists($time, $degree[$word]) === false)
                                                                                $degree[$word][$time] = 0;
                                                                            if (array_key_exists($time, $degree[$coword]) === false)
                                                                                $degree[$coword][$time] = 0;

                                                                            $degree[$word][$time]++;
                                                                            $degree[$coword][$time]++;
                                                                        }
                                                                    }
                                                                }

                                                                // count nr of time slices the words appears in
                                                                foreach ($words as $word => $times) {
                                                                    $documentsPerWords[$word] = count($times);
                                                                }
                                                                // calculate similarity and changes
                                                                foreach ($ap as $word => $times) {
                                                                    $times_keys = array_keys($times);
                                                                    for ($i = 1; $i < count($times_keys); $i++) {
                                                                        $im1 = $i - 1;
                                                                        $v1 = $times[$times_keys[$im1]];
                                                                        $v2 = $times[$times_keys[$i]];
                                                                        $cos_sim[$word][$times_keys[$i]] = cosineSimilarity($v1, $v2);
                                                                        $change_out[$word][$times_keys[$i]] = change($v1, $v2);
                                                                        $change_in[$word][$times_keys[$i]] = change($v2, $v1);
                                                                        $stable[$word][$times_keys[$i]] = array_intersect(array_keys($v1), array_keys($v2));
                                                                    }
                                                                }

                                                                // @todo, frequency
                                                                $out = "key\ttime\tdegree\tsimilarity\tassociational profile\tchange in\tchange out\tstable\n";
                                                                foreach ($ap as $word => $times) {
                                                                    foreach ($times as $time => $profile) {
                                                                        if (isset($change_in[$word][$time])) {
                                                                            $inc = "";
                                                                            foreach ($change_in[$word][$time] as $w => $c) {
                                                                                $inc .= "$w ($c), ";
                                                                            }
                                                                            $inc = substr($inc, 0, -2);
                                                                        } else
                                                                            $inc = "";
                                                                        if (isset($change_out[$word][$time])) {
                                                                            $outc = "";
                                                                            foreach ($change_out[$word][$time] as $w => $c) {
                                                                                $outc .= "$w ($c), ";
                                                                            }
                                                                            $outc = substr($outc, 0, -2);
                                                                        } else
                                                                            $outc = "";
                                                                        if (isset($stable[$word][$time])) {
                                                                            $stablec = array();
                                                                            foreach ($stable[$word][$time] as $w) {
                                                                                $stablec[] = $w;
                                                                            }
                                                                            $stablec = implode(", ", $stablec);
                                                                        } else
                                                                            $stablec = "";
                                                                        $prof = "";
                                                                        foreach ($profile as $w => $c)
                                                                            $prof .= "$w ($c), ";
                                                                        $prof = substr($prof, 0, -2);
                                                                        if (isset($degree[$word][$time]))
                                                                            $deg = $degree[$word][$time]; else
                                                                            $deg = "";
                                                                        if (isset($cos_sim[$word][$time]))
                                                                            $cs = $cos_sim[$word][$time]; else
                                                                            $cs = "";
                                                                        $out .= $word . "\t" . $time . "\t" . $deg . "\t" . $cs . "\t" . $prof . "\t" . $inc . "\t" . $outc . "\t" . $stablec . "\n";
                                                                    }
                                                                }


                                                                file_put_contents($filename, chr(239) . chr(187) . chr(191) . $out);
                                                                echo '<fieldset class="if_parameters">';
                                                                echo '<legend>Your co-hashtag variability File</legend>';
                                                                echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
                                                                echo '</fieldset>';
                                                            }

// calculates cosine measure between two frequency vectors
                                                            function cosineSimilarity($v1, $v2) {
                                                                $l1 = $l2 = 0;
                                                                foreach ($v1 as $word => $frequency)
                                                                    $l1 += pow($frequency, 2);
                                                                $l1 = sqrt($l1);
                                                                foreach ($v2 as $word => $frequency)
                                                                    $l2 += pow($frequency, 2);
                                                                $l2 = sqrt($l2);

                                                                $dot_product = 0;
                                                                foreach ($v1 as $word => $frequency) {
                                                                    if (isset($v2[$word])) {
                                                                        $dot_product += ($v2[$word] * $frequency);
                                                                    }
                                                                }

                                                                $cos_sim = $dot_product / ($l1 * $l2);

                                                                return $cos_sim;
                                                            }

// detects gradient of change between two frequency vectors
                                                            function change($v1, $v2) {
                                                                $change = array();
                                                                foreach ($v1 as $word => $freq) {
                                                                    if (isset($v2[$word])) {
                                                                        $c = $freq - $v2[$word];
                                                                        $norm = ($freq + $v2[$word]) / 2;
                                                                    } else {
                                                                        $c = $freq;
                                                                        $norm = $freq / 2;
                                                                    }
                                                                    $change[$word] = $c / $norm;
                                                                }
                                                                arsort($change);
                                                                return $change;
                                                            }
                                                            ?>

                                                            <?php
                                                            /*
                                                             * Uses elaborate coword implementation on tools.digitalmethods.net/beta/coword
                                                             * This works via persistent objects = SLOW but does not run out of memory
                                                             *
                                                             * @todo test
                                                             * @todo extract variability
                                                             *
                                                             * @deprecated, just leaving this in for the curl call
                                                             */

                                                            function cohashtagsViaDatabase($sqlresults, $filename) {
                                                                // make arrays of tweets per day
                                                                print "collecting<br/>";
                                                                flush();
                                                                $word_frequencies = array();
                                                                while ($data = mysql_fetch_assoc($sqlresults)) { // @todo, new scheme of things
                                                                    // preprocess
                                                                    preg_match_all("/(#.+?)[" . implode("|", $punctuation) . "]/", strtolower($data["text"]), $text, PREG_PATTERN_ORDER);
                                                                    $text = trim(implode(" ", $text[1]));
                                                                    if (!empty($text)) {
                                                                        // store per day
                                                                        $dataPerDay[strftime("%Y-%m-%d", $data['time'])][] = $text;

                                                                        $words = explode(" ", $text);
                                                                        $wcvcount = count($words);
                                                                        for ($i = $wcvcount - 1; $i > 0; $i--) {
                                                                            if (!isset($word_frequencies[$words[$i]]))
                                                                                $word_frequencies[$words[$i]] = 0;
                                                                            $word_frequencies[$words[$i]]++;
                                                                        }
                                                                    }
                                                                }

                                                                foreach ($dataPerDay as $day => $texts) {
                                                                    print count($texts) . " " . $day . "<br/>";

                                                                    if (!defined('ANALYSIS_URL'))
                                                                        die('define ANALYSIS_URL');
                                                                    $url = COWORD_URL;

                                                                    $params = array(
                                                                        'text_json' => json_encode($texts),
                                                                        'stopwordList' => 'all',
                                                                        //'max_document_frequency' => 90,
                                                                        'min_frequency' => 0, // 5 per avg of 5000 tweets
//            'threshold_of_associations' => 0.2,
                                                                        'options[]' => 'urls, remove_stopwords',
                                                                    );

                                                                    // @todo, think through the inclusion of the probability of association
                                                                    // @todo, think through changes w.r.t. coword (instead of cohashtag)
                                                                    //if (isset($_GET['probabilityOfAssociation']) && !empty($_GET['probabilityOfAssociation'])) {
                                                                    //    $params['options'] = 'urls, remove_stopwords, probabilityOfAssociation';
                                                                    //}

                                                                    $ch = curl_init();
                                                                    curl_setopt($ch, CURLOPT_URL, $url);
                                                                    curl_setopt($ch, CURLOPT_POST, count($params));
                                                                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                                                                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                                                                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                                                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                                                                    $json = curl_exec($ch);
                                                                    curl_close($ch);

                                                                    $stuff = json_decode(stripslashes($json));
                                                                    if (empty($stuff) || !$stuff)
                                                                        print "<b>Nothing found for $time</b><br/>";

                                                                    $this->series[$time] = json_decode($json);
                                                                }

                                                                // make GEXF time series
                                                                $gexf = $cw->gexfTimeSeries(str_replace($resultsdir, "", $filename), $word_frequencies);
                                                                file_put_contents($filename, $gexf);
                                                            }
                                                            ?>
