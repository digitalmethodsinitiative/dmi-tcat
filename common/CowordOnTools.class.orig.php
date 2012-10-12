<?php

set_time_limit(0);
ini_set('memory_limit', '2G');
include_once('Gexf.class.php');

// class allowing use of coword machine on tools.digitalmethods.net
class CowordOnTools {

    public $series;   // stores series of cowords, per day
    public $keywords;   // holds keywords for our association profiles // @todo, this is very case specific

    function __construct() {
        $this->series = array();

        // @todo, this is cruft from the coword workshop
        $this->keywordsClimteAction = array(
            "#tarsands",
            "#eu � �",
            "#cdnpoli � �",
            "#agw � �",
            "#green � �",
            "#fqd � �",
            "#cndpoli � �",
            "#politics � �",
            "#unfccc � �",
            "#ceta � �",
            "#health",
            "#flooding",
            "#agw",
            "#unfccc",
            "#jobs",
            "#san",
            "#intern",
            "#job",
            "#cop18",
            "#cop17",
            "#health",
            "#flooding",
            "#climatechange",
            "#green",
            "#energy",
            "#globalwarming",
            "#environment",
            "#policy",
            "#losangeles",
            "#nonprofit",
        );
        // climate change
        $this->keywords = array(
            "#climate",
            "#climatechange",
            "#environment",
            "#tcot",
            "#climate_change",
            "#dt",
            "#globalwarming",
            "#co2",
            "#drought",
            "#cleancloud",
            "#flood",
            "#health",
            "#economics",
            "#eco",
            "#change",
            "#copenhagen",
            "#cop16",
            "#cancun",
            "#ows",
            "#flooding",
        );
    }

    function getCowords($texts, $time) {

//    	print_r($texts); print "<br>";
//		foreach($texts as $text) print "$text<bR>";
//		print "<br>";

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
        var_dump($json);
        curl_close($ch);

        $stuff = json_decode(stripslashes($json));
        if (empty($stuff) || !$stuff)
            print "<b>Nothing found for $time</b><br/>";

        $this->series[$time] = json_decode($json);
    }

    function variabilityOfAssociationProfiles($word_frequencies = array()) {
        // group per slice (3 months)
        // per keyword
        // 	get associated words (depth 1) per slice
        // 	get frequency, degree, ap variation (calculated on cooc frequency), words in, words out, ap keywords
        $degree = array();
//    	print "<pre>".print_r($this->series,1)."</pre>"; die;
        foreach ($this->series as $time => $cowordlist) {

            foreach ($cowordlist as $tuple) {

                if (!is_object($tuple))
                    continue; // @todo, why is this here anyway?

                $word = $tuple->word;
                $coword = $tuple->coword;
                $frequency = $tuple->frequency;

                // keep in how many time slices the word appears
                $words[$word][$time] = 1;
                $words[$coword][$time] = 1;

                // ap = association profile
                if (array_search($word, $this->keywords) !== false)
                    $ap[$word][$time][$coword] = $frequency;
                if (array_search($coword, $this->keywords) !== false)
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

        // count nr of time slices the words appears in
        foreach ($words as $word => $times) {
            $documentsPerWords[$word] = count($times);
        }

        $timeslices = array_keys($this->series);
        foreach ($ap as $word => $times) {
            for ($i = 1; $i < count($timeslices); $i++) {
                $im1 = $i - 1;
                if (array_key_exists($timeslices[$im1], $times) === false)
                    continue;
                if (array_key_exists($timeslices[$i], $times) === false)
                    continue;
                $v1 = $times[$im1];
                $v2 = $times[$i];
                $cos_sim[$word][$timeslices[$i]] = $this->cosineSimilarity($v1, $v2);
                $change_out[$word][$timeslices[$i]] = $this->change($v1, $v2);
                $change_in[$word][$timeslices[$i]] = $this->change($v2, $v1);
            }
        }

        // @todo, stability + frequency
        $out = "key\ttime\tdegree\tsimilarity\tassociational profile\tin\tout\n";
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
                $out .= $word . "\t" . $time . "\t" . $deg . "\t" . $cs . "\t" . $prof . "\t" . $inc . "\t" . $outc . "\n";
            }
        }
        //print "\n".$out;
        return $out;
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

    function gexfTimeSeries($title, $word_frequencies = array()) {
        $gexf = new Gexf();
        $gexf->setTitle("Co-word " . $title);
        $gexf->setEdgeType(GEXF_EDGE_UNDIRECTED);
        $gexf->setMode(GEXF_MODE_DYNAMIC);
        $gexf->setTimeFormat(GEXF_TIMEFORMAT_DATE);
        $gexf->setCreator("tools.digitalmethods.net"); //@todo set creator to be the logged in user
        // get words for each time slice
        $words = $degree = array();

        foreach ($this->series as $time => $cowordlist) {
            foreach ($cowordlist as $tuple) {
                if (!is_object($tuple))
                    continue; // @todo, why is this here anyway?
                $word = $tuple->word;
                $coword = $tuple->coword;
                $words[$word][$time] = 1;
                $words[$coword][$time] = 1;
            }
        }
        // count nr of time slices the words appears in
        foreach ($words as $word => $times) {
            $documentsPerWords[$word] = count($times);
        }

        //colour 0-30% green and 31-100% black
        $nrOfSlices = count($this->series);
        $threshold = $nrOfSlices * 0.3;

        // make nodes
        foreach ($this->series as $time => $cowordlist) {
            foreach ($cowordlist as $tuple) {
                if (!is_object($tuple))
                    continue; // @todo, why is this here anyway?
                $word = $tuple->word;
                $coword = $tuple->coword;
                if (isset($_GET['probabilityOfAssociation']) && !empty($_GET['probabilityOfAssociation']))
                    $frequency = $tuple->probabilityOfAssociation;
                else
                    $frequency = $tuple->frequency;

                $node1 = new GexfNode($word);
                if (isset($word_frequencies[$word]))
                    $node1->addNodeAttribute("word_frequency", $word_frequencies[$word], $type = "int");
                //$node1->addNodeAttribute("degree",$degree[$word][$time]); // @todo, add time
                $gexf->addNode($node1);
                if ($documentsPerWords[$word] > $threshold)
                    $node1->setNodeColor(0, 255, 0, 0.75);
                $gexf->nodeObjects[$node1->id]->addNodeSpell($time, $time);
                $node2 = new GexfNode($coword);
                if (isset($word_frequencies[$coword]))
                    $node2->addNodeAttribute("word_frequency", $word_frequencies[$coword], $type = "int");
                //$node2->addNodeAttribute("degree",$degree[$word][$time]); // @todo, add time
                $gexf->addNode($node2);
                if ($documentsPerWords[$coword] > $threshold)
                    $node2->setNodeColor(0, 255, 0, 0.75);
                $gexf->nodeObjects[$node2->id]->addNodeSpell($time, $time);
                $edge_id = $gexf->addEdge($node1, $node2, $frequency);
                $gexf->edgeObjects[$edge_id]->addEdgeSpell($time, $time);
            }
        }
        $gexf->render();
        return $gexf->gexfFile;
    }

}

?>
