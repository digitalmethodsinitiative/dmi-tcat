<?php
require_once './common/config.php';
require_once './common/functions.php';

$variability = false;       // @todo used as hack for experiment in first issue mapping workshop
$uselocalresults = false;   // @todo used as hack for experiment in first issue mapping workshop
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics GEXF</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>Twitter Analytics</h1>

        <?php
// => gexf
// => time
        validate_all_variables();
// Output format: {dataset}_{query}_{startdate}_{enddate}_{from_user_name}_{output type}.{filetype}

        $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
        $filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . (isset($_GET['probabilityOfAssociation']) ? "_normalizedAssociationWeight" : "") . "_hashtagCooc.gexf";
        
        $sql = "SELECT LOWER(A.text) AS h1, LOWER(B.text) AS h2 FROM ".$esc['mysql']['dataset']."_hashtags A, ".$esc['mysql']['dataset']."_hashtags B, ".$esc['mysql']['dataset']."_tweets t WHERE ";
        $sql .= sqlSubset()." AND ";
        $sql .= "LENGTH(A.text)>1 AND LENGTH(B.text)>1 AND ";
        $sql .= "LOWER(A.text) < LOWER(B.text) AND A.tweet_id = t.id AND A.tweet_id = B.tweet_id ";
        $sql .= "ORDER BY h1,h2";
        
        //print $sql;die;
        $sqlresults = mysql_query($sql);
        include_once('common/Coword.class.php');
        $coword = new Coword;
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $coword->addWord($res['h1'],1);
            $coword->addWord($res['h2'],1);
            $coword->addCoword($res['h1'],$res['h2'],1);
        }
        file_put_contents($filename, chr(239) . chr(187) . chr(191) .$coword->getCowordsAsGexf($filename));
        
        echo '<fieldset class="if_parameters">';

        echo '<legend>Your File</legend>';

        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>

<?php
/*
 * Uses simple coword implementation, all works via memory. Too big a set will explode your computer.
 * 
 * @todo, consider splitting per day and producing a time series gexf
 */

function cohashtagsViaMemory($results, $filename) {
    //print "collecting tweets<Br>";
    flush();
    include_once('common/Coword.class.php');
    $coword = new Coword;
    /* $coword->setHashtags_are_separate_words(TRUE);
      $coword->setExtract_only_hashtags(TRUE);
      $coword->setSimpleTokens(TRUE);
      foreach ($results as $tags) {
      $coword->addDocument(implode(" ",$tags));
      }
      $coword->iterate();
     */
    // the following skips many steps withing $coword->iterate
    foreach ($results as $tweet_id => $tags) {
        if (count($tags) < 2)
            continue;
        $frequency = array_count_values($tags);
        $words_in_document = array_keys($frequency);
        for ($i = 0; $i < count($words_in_document); $i++) {
            $from = $words_in_document[$i];
            if (!isset($coword->words[$from]))
                $coword->words[$from] = 0;
            $coword->words[$from] += $frequency[$words_in_document[$i]];
            for ($j = $i + 1; $j < count($words_in_document); $j++) {
                $to = $words_in_document[$j];
                if (!isset($coword->words[$to]))
                    $coword->words[$to] = 0;
                $coword->words[$to] += $frequency[$words_in_document[$j]];
                $coword->addCoword($from, $to, min($frequency[$words_in_document[$i]], $frequency[$words_in_document[$j]]));
            }
        }
    }
    file_put_contents($filename, $coword->getCowordsAsGexf($filename));
}

/*
 * Uses elaborate coword implementation on tools.digitalmethods.net/beta/coword
 * This works via persistent objects = SLOW but does not run out of memory
 * 
 * @todo test
 * @todo extract variability
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

            if (0) { // @todo, enable if variability is wished for 
                // @todo, add word_frequencies per month
                // @todo, make timeslice optional				
                $m = strftime("%m", $data['time']);
                $d = strftime("%d", $data['time']);
                if (($m == 2 && $d >= 15) || ($m == 3 && $d < 15)) {
                    $dataPerMonth[0][] = $text;
                } elseif (($m == 3 && $d >= 15) || ($m == 4 && $d < 15)) {
                    $dataPerMonth[1][] = $text;
                } elseif (($m == 3 && $d >= 15) || ($m == 5 && $d < 15)) {
                    $dataPerMonth[2][] = $text;
                }
            }
        }
    }
    if (!$uselocalresults && isset($_GET["tooltype"]) && $_GET["tooltype"] == "new")   // @todo, what is the difference between the two
        include_once('common/CowordOnTools.class.php');
    else
        include_once('common/CowordOnTools.class.orig.php');
    $cw = new CowordOnTools;
    // get cowords per day
    print "Getting co-hasthtags<br/>";
    flush();

    if ($variability) { // @todo, enable if variability is wished for
        if ($uselocalresults) { // @todo, enable if using stored results
            // read local results
            $days = array();
            $dir = "/home/erik/cowords/";
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (strpos($file, "climate-change") !== false) {
                        $date = str_replace("climate-change_", "", str_replace(".json", "", $file));
                        $json = json_decode(file_get_contents($dir . $file));
//						print "<pre>".print_r($json->$date,1)."</pre>"; die;
                        $days[$date] = $json->$date;
                    }
                }
            }
            if (!empty($days)) {
                $interval = array();
                foreach ($days as $date => $cowords) {
                    // group days to our desired interval
                    if (preg_match("/(\d{4})-(\d{2})-(\d{2})/", $date, $match)) {
                        $m = $match[2];
                        $d = $match[3];
                        if ($m == 3 && $d <= 15)
                            $i = 0;
                        elseif ($m == 3 && $d > 15)
                            $i = 1;
                        elseif ($m == 4 && $d <= 15)
                            $i = 2;
                        elseif ($m == 4 && $d > 15)
                            $i = 3;
                        elseif ($m == 5 && $d <= 15)
                            $i = 4;
                        elseif ($m == 5 && $d > 15)
                            $i = 5;
                        elseif ($m == 6 && $d <= 15)
                            $i = 6;
                        elseif ($m == 6 && $d > 15)
                            $i = 7;
                        $interval[$i][] = $cowords;
                    }
                }
                // merge days in desired interval
                if (!empty($interval)) {
                    foreach ($interval as $k => $cowords) {
                        $results = array();
                        foreach ($cowords as $tuples) {
                            foreach ($tuples as $tuple) {
                                if (!isset($results[$tuple->word]) || !isset($results[$tuple->word][$tuple->coword]))
                                    $results[$tuple->word][$tuple->coword] = 0;
                                $results[$tuple->word][$tuple->coword]+=$tuple->frequency;
                            }
                        }
                        $cw->series[$k] = intoTuplesAgain($results);
                    }
                }
            }
        } else {    // default variability interval is a month @todo might want to give the user the option to split
            foreach ($dataPerMonth as $month => $documents) {
                print count($documents) . " month " . $month . "<br/>";
                $cw->getCowords($documents, $month);
            }
        }
        print "<br>";
    } else {
        foreach ($dataPerDay as $day => $documents) {
            //$documents = array_unique($documents);			// do not consider retweets @todo not each analysis will need this
            print count($documents) . " " . $day . "<br/>";
            flush();
            $cw->getCowords($documents, $day);
        }
        print "<br/>";
    }

    if ($variability) {
        // print variability
        $csv = $cw->variabilityOfAssociationProfiles($word_frequencies);
        $fn = str_replace("hashtagCooc.gexf", "profileVariability.csv", $filename);
        file_put_contents($fn, chr(239) . chr(187) . chr(191) . $csv);
        echo '<fieldset class="if_parameters">';
        echo '<legend>variability of association profiles</legend>';
        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $fn)) . '">' . $fn . '</a></p>';
        echo '</fieldset>';
    }

    // make GEXF time series
    $gexf = $cw->gexfTimeSeries(str_replace($resultsdir, "", $filename), $word_frequencies);
    if ($variability) {
        $gexf = str_replace('start="0" end="0"', 'start="2012-03-01" end="2012-03-15"', $gexf);
        $gexf = str_replace('start="1" end="1"', 'start="2012-03-16" end="2012-03-31"', $gexf);
        $gexf = str_replace('start="2" end="2"', 'start="2012-04-01" end="2012-04-15"', $gexf);
        $gexf = str_replace('start="3" end="3"', 'start="2012-04-16" end="2012-04-30"', $gexf);
        $gexf = str_replace('start="4" end="4"', 'start="2012-05-01" end="2012-05-15"', $gexf);
        $gexf = str_replace('start="5" end="5"', 'start="2012-05-16" end="2012-05-31"', $gexf);
        $gexf = str_replace('start="6" end="6"', 'start="2012-06-01" end="2012-06-15"', $gexf);
        $gexf = str_replace('start="7" end="7"', 'start="2012-06-16" end="2012-06-30"', $gexf);
    }
    file_put_contents($filename, $gexf);
}

function intoTuplesAgain($results) {
    $cowordlist = array();
    foreach ($results as $word => $cowords) {
        foreach ($cowords as $coword => $frequency) {
            $obj = new StdClass;
            $obj->word = $word;
            $obj->coword = $coword;
            $obj->frequency = $frequency;
            $cowordlist[] = $obj;
        }
    }
    return $cowordlist;
}
?>
