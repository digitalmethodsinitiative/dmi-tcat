<?php
set_time_limit(0);
ini_set('memory_limit','2G');
date_default_timezone_set('Europe/Amsterdam');
include_once('Gexf.class.php');
include_once('dmi_api/DmiTool.class.php');

// class allowing use of coword machine on tools.digitalmethods.net
class CowordOnTools {

	public $series;			// stores series of cowords, per day
	public $scriptStartTime;

    function __construct() {
        $this->series = array();
        $this->time = 0;
    }

    function getCowords($texts,$time) {
    	$this->scriptStartTime = date('U');
    	print "Time ".strftime("%T",$this->scriptStartTime)."<bR>"; 
    	print "getting cowords<br>";
    	$tool=new DmiTool('coword',DMI_TESTING);
    	$tool->setParm('text_json',json_encode($texts));
    	$tool->setParm('options[]','urls,remove_stopwords');
		$tool->setParm('stopwordList','english');
		$tool->setParm('max_document_frequency',90);
		$tool->setParm('min_frequency',2);
    //          'threshold_of_associations' => 0.2,
    	$this->time = $time;
		$tool->execute(array($this,"toolCallback"));
//		print "<b>series:</b><br>";
//print_r($this->series);
		file_put_contents('/home/erik/cowords/climate-change_'.$this->time.".json",json_encode($this->series));
	}
		
	function toolCallback($result) {
		$cowordlist = array();
		print "Time ".strftime("%T",date('U'))."<br>";
		if(empty($result)) 
			print "<b>Nothing found for $time</b><br/>";
		else {
			$cowordlist = $this->intoTuples($result);
			print count($cowordlist)." tuples found<br>";
		}
		$this->series[$this->time] = $cowordlist;
    }
    
    // as the response from the coword machine now is a tree, we now need to reconstruct the tuples
    function intoTuples($results) {
//print "<b>results:</b><br>";
//print_r($results);
    	$cowordlist = array();
    	foreach($results as $word => $cowords) {
    		foreach($cowords as $coword => $frequency) {
    			$obj = new StdClass;
    			$obj->word = $word;
    			$obj->coword = $coword;
    			$obj->frequency = $frequency;
    			$cowordlist[] = $obj;
    		}
    	}
//    	print "<br>";
//    	print "<b>cowordlist</b><br>";
//    	print_r($cowordlist);
//    	print "<br>";
    	return $cowordlist;
    }

    function gexfTimeSeries($title,$word_frequencies = array()) {
        $gexf = new Gexf();
        $gexf->setTitle("Co-word " . $title);
        $gexf->setEdgeType(GEXF_EDGE_UNDIRECTED);
        $gexf->setMode(GEXF_MODE_DYNAMIC);
        $gexf->setTimeFormat(GEXF_TIMEFORMAT_DATE);
        $gexf->setCreator("tools.digitalmethods.net"); //@todo set creator to be the logged in user
        // get words for each time slice
        $words = array();
        foreach ($this->series as $time => $cowordlist) {
	        foreach($cowordlist as $tuple) {
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
            	$word = $tuple->word;
            	$coword = $tuple->coword;
            	$frequency = $tuple->frequency;
            	       
                $node1 = new GexfNode($word);
                if(isset($word_frequencies[$word]))
                	$node1->addNodeAttribute("word_frequency",$word_frequencies[$word],$type="int");
                $gexf->addNode($node1);
                if ($documentsPerWords[$word] > $threshold)
                    $node1->setNodeColor(0, 255, 0, 0.75);
                $gexf->nodeObjects[$node1->id]->addNodeSpell($time, $time);
                $node2 = new GexfNode($coword);
                if(isset($word_frequencies[$coword]))
                	$node2->addNodeAttribute("word_frequency",$word_frequencies[$coword],$type="int");
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
