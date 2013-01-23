<?php
require_once './common/config.php';
require_once './common/functions.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics co-hashtag variability</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" src="./scripts/raphael-min.js"></script>
        <script type='text/javascript' src='./scripts/jquery-1.7.1.min.js'></script>

    </head>

    <body>

        <h1>Twitter Analytics co-hashtag variability</h1>

        <table>

            <form action="<?php echo "/coword/" . $_SERVER["PHP_SELF"]; ?>">
                <input type="hidden" name="dataset" value="<?php echo $dataset; ?>" />
                <input type="hidden" name="query" value="<?php echo $query; ?>" />
                <input type="hidden" name="exclude" value="<?php echo $query; ?>" />
                <input type="hidden" name="from_user_name" value="<?php echo $from_user_name; ?>" />
                <input type="hidden" name="startdate" value="<?php echo $startdate; ?>" />
                <input type="hidden" name="enddate" value="<?php echo $enddate; ?>" />
                <tr>
                    <td>Keyword to generate associational profile:</td>
                    <td><input type="text" name="keywordsToTrack" value="<?php echo $keywordsToTrack; ?>" /></td>
                </tr>
                <tr>
                    <td>Minimum frequency for a word to be included</t>
                    <td><input type="text" name="keywordFrequency" value="<?php echo $keywordFrequency; ?>" /></td>
                </tr>
                <tr>
                    <td><input type="submit" value="create file" /></td>
                </tr>
            </form>

        </table>

        <?php
        validate_all_variables();
// Output format: {dataset}_{query}_{startdate}_{enddate}_{from_user_name}_{output type}.{filetype}

        $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
        $filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . (isset($_GET['probabilityOfAssociation']) ? "_normalizedAssociationWeight" : "") . "_hashtagVariability.gexf";

        $sql = "SELECT LOWER(A.text) AS h1, LOWER(B.text) AS h2 ";
        $sql .= ", DATE_FORMAT(t.created_at,'%Y-%m-%d') datepart "; // @todo, adjust interval
        $sql .= "FROM " . $esc['mysql']['dataset'] . "_hashtags A, " . $esc['mysql']['dataset'] . "_hashtags B, " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
        $sql .= sqlSubset() . " AND ";
        $sql .= "LENGTH(A.text)>1 AND LENGTH(B.text)>1 AND ";
        $sql .= "LOWER(A.text) < LOWER(B.text) AND A.tweet_id = t.id AND A.tweet_id = B.tweet_id ";
        $sql .= "ORDER BY datepart,h1,h2";

        $sqlresults = mysql_query($sql);
        include_once('common/Coword.class.php');
        $date = false;
        while ($res = mysql_fetch_assoc($sqlresults)) {

            if ($date !== $res['datepart']) {
                $date = $res['datepart'];
                $series[$date] = new Coword;
            }
            $series[$date]->addWord($res['h1'], 1);
            $series[$date]->addWord($res['h2'], 1);
            $series[$date]->addCoword($res['h1'], $res['h2'], 1);
        }

        getGEXFtimeseries($filename, $series);
        if (!empty($keywordsToTrack)) {
            //$keywordsToTrack = "acta,copy";
            //$keywordsToTrack = "#climatechange,#environment,#tcot,#climate_change,#dt,#globalwarming,#co2,#drought,#cleancloud,#flood,#health,#economics,#eco,#change,#copenhagen,#cop16,#cancun,#ows,#flooding"; // climate change keywords
            $ap = variabilityOfAssociationProfiles($filename, $series, $keywordsToTrack);
            ?>


            <p>
                <form>
                    <input type="checkbox" onchange="changeInterface('labels',this.checked)" />Show labels in visualization
					<input type="checkbox" onchange="changeInterface('sorting',this.checked)" />Sort by size
                </form>
                <script type="text/javascript">
                     /*               	
                     var _data = {
                        "slice 1" : {
                            "word 1" : 20,
                            "word 2" : 20,
                            "word 3" : 20
                        },"slice 2" : {
                            "word 1" : 10,
                            "word 3" : 40,
                            "word 2" : 20,
                            "word 4" : 20
                        },"slice 3" : {
                            "word 2" : 20,
                            "word 3" : 20,
                            "word 4" : 40
                        }, "slice 4" : {
                            "word 2" : 20,
                            "word 3" : 20,
                            "word 4" : 40
                        }
                    }
                    */
                                
    <?php
    $ap[$word][$time][$coword] = $frequency;
    $script_out = "var _data = {\n";
    foreach ($ap as $word => $times) {
        foreach ($times as $time => $cowords) {
            if (empty($time))
                continue;
            $script_out .= "\t\"$time\" : {\n";
            $counter = 0;
	    foreach ($cowords as $coword => $frequency) {
                if ($frequency > $keywordFrequency) { // @todo
                    	$counter++;
			$script_out .= "\t\t\"$coword\" : $frequency,\n";
		}
            }
            if($counter != 0) {
	    	$script_out = substr($script_out, 0, -2);
	    }
            $script_out .= "\n\t},\n";
        }
        $script_out = substr($script_out, 0, -2) . "}";
    }
    $script_out .= "\n}\n";
    print $script_out;
    ?>
                                    	
        
                                    	
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

function variabilityOfAssociationProfiles($filename, $series, $keywordsToTrack) {

    if (empty($series) || empty($keywordsToTrack))
        die('not enough data');
    $keywordsToTrack = explode(",", $keywordsToTrack);
    foreach ($keywordsToTrack as $k => $v) {
        $v = trim($v);
        if (empty($v))
            unset($keywordsToTrack[$k]);
        else
            $keywordsToTrack[$k] = $v;
    }
    if(count($keywordsToTrack)>1) die('multple keyword tracking not implemented yet');
    $filename = str_replace(".gexf", "_" . escapeshellarg(implode("_", $keywordsToTrack)) . ".csv", $filename);
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

                // ap = association profile
                if (array_search($word, $keywordsToTrack) !== false)
                    $ap[$word][$time][$coword] = $frequency;
                if (array_search($coword, $keywordsToTrack) !== false)
                    $ap[$coword][$time][$word] = $frequency;

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

    return $ap;
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
