<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/Coword.class.php';
validate_all_variables();
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics :: Associational profiles</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" src="./scripts/raphael-min.js"></script>
        <script type='text/javascript' src='./scripts/jquery-1.7.1.min.js'></script>

    </head>

    <body>

        <h1>Twitter Analytics :: Associational profiles</h1>

        <fieldset class="if_parameters">

            <legend>Interface</legend>


            <table>

                <form action="<?php echo "/coword/" . $_SERVER["PHP_SELF"]; ?>" method="GET">
                    <tr>
                        <td align='right'>Keyword to generate associational profile</td>
                        <td><input type="text" name="keywordToTrack" value="<?php echo $keywordToTrack; ?>" /> </td>
                    </tr>
                    <tr>
                        <td align='right'>Minimum co-word frequency (over all periods) for a word to be included</t>
                            <td><input type="text" name="keywordFrequency" value="<?php echo $keywordFrequency; ?>" /></td>
                    </tr>
                    <tr>
                        <td align='right'>Choose interval
                        </td><td>
                            <input type='radio' name="interval" value="daily"<?php if (!isset($_REQUEST['interval']) || (isset($_REQUEST['interval']) && $_REQUEST['interval'] == 'daily')) print " CHECKED"; ?>>daily</input>
                            <input type='radio' name="interval" value="weekly"<?php if (isset($_REQUEST['interval']) && $_REQUEST['interval'] == 'weekly') print " CHECKED"; ?>>weekly</input>
                            <input type='radio' name="interval" value="monthly"<?php if (isset($_REQUEST['interval']) && $_REQUEST['interval'] == 'monthly') print " CHECKED"; ?>>monthly</input>
                        </td>
                    </tr>
                    <!--<Tr><td>Or specify a custom interval<br> e.g. 2012-01-01,2012-01-03,2012-01-05,2012-01-10 
                        </td><td>
                            <input tyep='text' name="interval_custom" value="<?php if (isset($_REQUEST['interval_custom'])) print $_REQUEST['interval_custom']; ?>"></input>
                        </td>
                    </tr>-->
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

        <?php
        $cowordTimeSeries = false;
        if (!empty($_REQUEST['timeseriesGexf']) || isset($_REQUEST['cohashtagVariability']))
            $cowordTimeSeries = true;

        if (!empty($keywordToTrack) || $cowordTimeSeries) {

            // get cowords from database
            $sql = "SELECT LOWER(A.text) AS h1, LOWER(B.text) AS h2 ";
            if (!empty($_REQUEST['interval'])) {
                if ($_REQUEST['interval'] == "daily")
                    $sql .= ", DATE_FORMAT(t.created_at,'%Y-%m-%d') datepart "; // default daily
                if ($_REQUEST['interval'] == "weekly")
                    $sql .= ", DATE_FORMAT(t.created_at,'%u') datepart ";
                if ($_REQUEST['interval'] == "monthly")
                    $sql .= ", DATE_FORMAT(t.created_at,'%Y-%m') datepart ";
            } else
                $sql .= ", DATE_FORMAT(t.created_at,'%Y-%m-%d') datepart "; // default daily
            $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags A, " . $esc['mysql']['dataset'] . "_hashtags B, " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
            $sql .= sqlSubset() . " AND ";
            $sql .= "LENGTH(A.text)>1 AND LENGTH(B.text)>1 AND ";
            $sql .= "LOWER(A.text) < LOWER(B.text) AND A.tweet_id = t.id AND A.tweet_id = B.tweet_id ";
            $sql .= "ORDER BY datepart,h1,h2 ASC";

            $sqlresults = mysql_query($sql);
            $date = false;
            $totalCowordFrequency = array();

            while ($res = mysql_fetch_assoc($sqlresults)) {

                $word = $res['h1'];
                $coword = $res['h2'];
                if ($date !== $res['datepart']) {
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
                    if (!isset($totalCowordFrequency[$coword]))
                        $totalCowordFrequency[$coword] = 0;
                    $totalCowordFrequency[$coword]++;
                } elseif ($coword == $keywordToTrack) {
                    if (!isset($ap[$coword][$date][$word]))
                        $ap[$coword][$date][$word] = 0;
                    $ap[$coword][$date][$word]++;
                    if (!isset($totalCowordFrequency[$word]))
                        $totalCowordFrequency[$word] = 0;
                    $totalCowordFrequency[$word]++;
                }

                // construct coword per date
                if ($cowordTimeSeries) {
                    $series[$date]->addWord($word, 1);
                    $series[$date]->addWord($coword, 1);
                    $series[$date]->addCoword($word, $coword, 1);
                }
            }

            // put data in right format for visualization
            $vis_data = array();
            foreach ($ap[$keywordToTrack]as $time => $cowords) {
                if (empty($time))
                    continue;
                foreach ($cowords as $coword => $frequency) {
                    if ($totalCowordFrequency[$coword] >= $keywordFrequency) {
                        $vis_data[$time][$coword]['frequency'] = $frequency;
                        $vis_data[$time][$coword]['specificity'] = $frequency; // @todo
                    }
                }
            }
            if(empty($vis_data)) die("<div class='txt_desc'><br><br>not enough data</div>");

            // generate files, if requested
            if ($cowordTimeSeries) {
                $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
                $filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . (isset($_GET['probabilityOfAssociation']) ? "_normalizedAssociationWeight" : "") . "_hashtagVariability.gexf";
                if (!empty($_REQUEST['timeseriesGexf']))
                    getGEXFtimeseries($filename, $series);
                if (isset($_REQUEST['cohashtagVariability']))
                    variabilityOfAssociationProfiles($filename, $series, $keywordToTrack, $ap);
            }
            ?>

            <p>
                <?php
                // generate visualization
                $datadescription = "Keywords co-occuring at least <i>$keywordFrequency</i> times with <i>$keywordToTrack</i> in ";
                if (!empty($query))
                    $datadescription .= " subselection <i>$query</i> of ";  // @todo but not in ...
                $datadescription .= "dataset <i>$dataset</i> ";
                if (!empty($startdate))
                    $datadescription .= " which ranges from <i>$startdate</i> ";
                if (!empty($enddate))
                    $datadescription .= "until <i>$enddate</i>"; // @todo exclude, from_user_name
                print $datadescription . "<bR>";
                ?>

                <form>
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
        }
        ?>
    </body>
</html>

<?php

function printTopHashtags() {
    global $esc;

    // determine interval
    $sql = "SELECT MIN(created_at) AS min, MAX(created_at) AS max FROM " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
    $sql .= sqlSubset();
    $rec = mysql_query($sql);
    $res = mysql_fetch_assoc($rec);
    // determine whether we should display intervals as days or hours
    if (strtotime($res['max']) - strtotime($res['min']) < 86400 * 2) { // if smaller than 2 days we'll do hours
        $interval = "hours";
        $sql_interval = "DATE_FORMAT(t.created_at,'%Y-%m-%d %Hh') datepart ";
    } else {
        $interval = "days";
        $sql_interval = "DATE_FORMAT(t.created_at,'%Y-%m-%d') datepart ";
    }
    $results = array();
    $sql = "SELECT COUNT(hashtags.text) AS count, LOWER(hashtags.text) AS toget, ";
    $sql .= $sql_interval;
    $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags hashtags, " . $esc['mysql']['dataset'] . "_tweets t ";
    $sql .= "WHERE t.id = hashtags.tweet_id AND ";
    $sql .= sqlSubset();
    $sql .= " GROUP BY toget ORDER BY count DESC limit 10";
    //print $sql."<br>";
    $rec = mysql_query($sql);
    $out = "";
    while ($res = mysql_fetch_assoc($rec)) {
        //if ($res['count'] > $esc['shell']['minf'])
        $out .= $res['toget'] . " (" . $res['count'] . "), ";
    }
    print substr($out, 0, -2);
}

function getGEXFtimeseries($filename, $series) {
// time-series gexf
    include_once('common/Gexf.class.php');
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
    echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';
    echo '</fieldset>';
}

function variabilityOfAssociationProfiles($filename, $series, $keywordToTrack, $ap) {

    if (empty($series) || empty($keywordToTrack))
        die('not enough data');
    $filename = str_replace(".gexf", "_" . escapeshellarg(implode("_", $keywordToTrack)) . ".csv", $filename);
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
    echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';
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

        if (!defined('BASE_URL'))
            die('define BASE_URL');
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