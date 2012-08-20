<?php
set_time_limit(0);
ini_set('memory_limit','2G');
include_once('Gexf.class.php');

// class allowing use of coword machine on tools.digitalmethods.net
class CowordOnTools {

	public $series;			// stores series of cowords, per day

    function __construct() {
        $this->series = array();
    }

    function getCowords($texts,$time) {
        $url = "https://tools.digitalmethods.net/beta/coword/";
		$texts = implode("\n\n",$texts);

		$params = array(
            'text' => $texts,
            'json' => 'syn',
            'stopwordList' => 'all',
            'max_document_frequency' => 90,
            'min_frequency' => 0,	// 5 per avg of 5000 tweets
//            'threshold_of_associations' => 0.2,
			'options[]' => 'urls, remove_stopwords',
        );
        
        if(isset($_GET['probabilityOfAssociation']) && !empty($_GET['probabilityOfAssociation'])) {
			$params['options'] = 'urls, remove_stopwords, probabilityOfAssociation';
        }
        
		$ch = curl_init();		
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_POST,count($params));
		curl_setopt($ch,CURLOPT_POSTFIELDS,$params);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
		$json = curl_exec($ch);
		curl_close($ch);

		$stuff = json_decode($json);
		if(empty($stuff) || !$stuff) print "<b>Nothing found for $time</b><br/>";

        $this->series[$time] = json_decode($json);
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
	        	if(!is_object($tuple)) continue; // @todo, why is this here anyway?
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
            	if(!is_object($tuple)) continue;	// @todo, why is this here anyway?
            	$word = $tuple->word;
            	$coword = $tuple->coword;
				if(isset($_GET['probabilityOfAssociation']) && !empty($_GET['probabilityOfAssociation'])) {
            		$frequency = $tuple->probabilityOfAssociation;
            	} else {
            		$frequency = $tuple->frequency;
            	}	
            	       
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
