<?php

require_once './common/config.php';
require_once './common/functions.php';
	
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Twitter Analytics</title>
	
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	
	<link rel="stylesheet" href="css/main.css" type="text/css" />
	
	<script type="text/javascript" language="javascript">
	
	
	
	</script>
		
</head>

<body>

<h1>Twitter Analytics</h1>

<?php

$stopwords = array("a", "about", "above", "above", "across", "after", "afterwards", "again", "against", "all", "almost", "alone", "along", "already", "also","although","always","am","among", "amongst", "amoungst", "amount",  "an", "and", "another", "any","anyhow","anyone","anything","anyway", "anywhere", "are", "around", "as",  "at", "back","be","became", "because","become","becomes", "becoming", "been", "before", "beforehand", "behind", "being", "below", "beside", "besides", "between", "beyond", "bill", "both", "bottom","but", "by", "call", "can", "cannot", "cant", "co", "con", "could", "couldnt", "cry", "de", "describe", "detail", "do", "done", "down", "due", "during", "each", "eg", "eight", "either", "eleven","else", "elsewhere", "empty", "enough", "etc", "even", "ever", "every", "everyone", "everything", "everywhere", "except", "few", "fifteen", "fify", "fill", "find", "fire", "first", "five", "for", "former", "formerly", "forty", "found", "four", "from", "front", "full", "further", "get", "give", "go", "had", "has", "hasnt", "have", "he", "hence", "her", "here", "hereafter", "hereby", "herein", "hereupon", "hers", "herself", "him", "himself", "his", "how", "however", "hundred", "ie", "if", "in", "inc", "indeed", "interest", "into", "is", "it", "its", "itself", "keep", "last", "latter", "latterly", "least", "less", "ltd", "made", "many", "may", "me", "meanwhile", "might", "mill", "mine", "more", "moreover", "most", "mostly", "move", "much", "must", "my", "myself", "name", "namely", "neither", "never", "nevertheless", "next", "nine", "no", "nobody", "none", "noone", "nor", "not", "nothing", "now", "nowhere", "of", "off", "often", "on", "once", "one", "only", "onto", "or", "other", "others", "otherwise", "our", "ours", "ourselves", "out", "over", "own","part", "per", "perhaps", "please", "put", "rather", "re", "same", "see", "seem", "seemed", "seeming", "seems", "serious", "several", "she", "should", "show", "side", "since", "sincere", "six", "sixty", "so", "some", "somehow", "someone", "something", "sometime", "sometimes", "somewhere", "still", "such", "system", "take", "ten", "than", "that", "the", "their", "them", "themselves", "then", "thence", "there", "thereafter", "thereby", "therefore", "therein", "thereupon", "these", "they", "thickv", "thin", "third", "this", "those", "though", "three", "through", "throughout", "thru", "thus", "to", "together", "too", "top", "toward", "towards", "twelve", "twenty", "two", "un", "under", "until", "up", "upon", "us", "very", "via", "was", "we", "well", "were", "what", "whatever", "when", "whence", "whenever", "where", "whereafter", "whereas", "whereby", "wherein", "whereupon", "wherever", "whether", "which", "while", "whither", "who", "whoever", "whole", "whom", "whose", "why", "will", "with", "within", "without", "would", "yet", "you", "your", "yours", "yourself", "yourselves", "the");

// => gexf
// => time

validate_all_variables();
// Output format: {dataset}_{query}_{startdate}_{enddate}_{from_user}_{output type}.{filetype}
get_dataset_name();
$exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
$filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"]. "_" . $esc['shell']["from_user"] . (isset($_GET['probabilityOfAssociation'])?"_normalizedAssociationWeight":"") . "_wordCooc.gexf";

$store_tweets = false;	// @todo, modify if necessary. Used to output the tweets considered, instead of the cowords
if($store_tweets) print "<font color='red'>storing tweets, not doing coword analysis</font>";

if(!$store_tweets) 
	$sql = "SELECT text, time FROM " . $esc['mysql']['dataset'] . " WHERE ";
else 
	$sql = "SELECT text, time, targeturl FROM " . $esc['mysql']['dataset'] . ", urls WHERE ";
if(!empty($esc['mysql']['from_user'])) {
	$subusers = explode(" OR ", $esc['mysql']['from_user']);
	$sql .= "(";
	for($i = 0; $i < count($subusers); $i++) {
		$subusers[$i] = "from_user = '" . $subusers[$i] . "'";
	}
	$sql .= implode(" OR ", $subusers);
	$sql .= ") AND ";
}
if(!empty($esc['mysql']['query'])) {
	$subqueries = explode(" AND ", $esc['mysql']['query']);
	foreach($subqueries as $subquery) {
		$sql .= "text LIKE '%" . $subquery . "%' AND ";
	}
}
if(!empty($esc['mysql']['exclude'])) 
	$sql .= "text NOT LIKE '%" . $esc['mysql']['exclude'] . "%' AND ";
$sql .= "time >= " . $esc['timestamp']['startdate'] . " AND time <= " . $esc['timestamp']['enddate'];
if($store_tweets) 
	$sql .= " AND tweetid = id";

$sqlresults = mysql_query($sql);
// new way of doing things
error_reporting(E_ALL);
if(1||!file_exists($filename)) {
	// make arrays of tweets per day
	print "collecting<br/>"; flush(); ob_flush();
	
	if($store_tweets) $tweets_considered = "";
	
	$word_frequencies = array();	
	while($data = mysql_fetch_assoc($sqlresults)) {
		// preprocess
		$text = trim(strtolower($data['text']));		
		if(!$store_tweets) {
			$text = preg_replace("/https?:\/\/[^\s]*/", " ", $text);		// remove URLs
			$text = preg_replace("/[^#\w\d]/", " ", $text);					// remove non-words
			$text = preg_replace("/^rt /"," ",$text);						// remove RT
			$text = preg_replace("/ rt /"," ",$text);						// remove RT		
			$text = preg_replace("/ [a-z0-9] /"," ",$text);					// remove single letter words
		}
		$text = preg_replace("/[\s\t\n\r]+/", " ", $text);					// replace whitespace characters by single whitespace
		if($store_tweets) 
			$text .= "\t".$data['targeturl'];
				
		$text = trim($text);
		if(!empty($text)) {	
			// store per day
			$dataPerDay[strftime("%Y-%m-%d",$data['time'])][] = $text;

			$words = explode(" ", $text);
			$wcvcount = count($words);
			for($i = $wcvcount - 1; $i > 0; $i--) {
				if(!isset($word_frequencies[$words[$i]])) $word_frequencies[$words[$i]] = 0;
				$word_frequencies[$words[$i]]++;
			} 
		}
	}
	if(isset($_GET["tooltype"]) && $_GET["tooltype"]=="new")
		include_once('common/CowordOnTools.class.php');
	else
		include_once('common/CowordOnTools.class.orig.php');
	$cw = new CowordOnTools;
	// get cowords per day
	print "Getting co-words<br/>";
	foreach($dataPerDay as $day => $documents) {
		//print count($documents)." ";
		$documents = array_unique($documents);							// do not consider retweets @todo not each analysis will need this
		print count($documents)." unique tweets on ".$day."<br/>"; flush(); ob_flush();
		if(!$store_tweets)
			$cw->getCowords($documents,$day);
		else {
			// storing tweets
			foreach($documents as $document) {
				$tweets_considered .= $day."\t".$document."\n";
			}
		}
	}
	print "<br/>";

	// make GEXF time series
	$gexf = $cw->gexfTimeSeries(str_replace($resultsdir,"",$filename),$word_frequencies);
	
	file_put_contents($filename,$gexf);
	
	if($store_tweets) {
		$filename = str_replace("_wordCooc.gexf","_tweetsConsidered.csv",$filename);
		file_put_contents($filename,$tweets_considered);
	}
}

/* Old way of doing things
$nodes = $edges = $fromTo = array();

if(!file_exists($filename)) {

	while($data = mysql_fetch_assoc($sqlresults)) {
	
		$data["text"] = strtolower($data["text"]);									// lower text
		*/
		//$data["text"] = preg_replace("/https?:\/\/[^\s]*/", " ", $data["text"]);	// remove URLs
		/*$data["text"] = preg_replace("/[^#\w\d]/", " ", $data["text"]);				// remove non-words
		$data["text"] = trim($data["text"]);										// trim
		$data["text"] = preg_replace("/[\s\t\n\r]+/", " ", $data["text"]);			// replace whitespace characters by single whitespace
		
		$words = explode(" ", $data["text"]);
		$words = array_diff($words,$stopwords);										// remove english stop words

		$frequency = array_count_values($words);
		$words = array_keys($frequency);
		for($i=0;$i<count($words);$i++) {
			$from = $words[$i];
			if(strlen($from)<2) continue;											// remove words smaller than 2 chars
			$nodes[] = $from;
			for($j=$i+1;$j<count($words);$j++) {
				$to = $words[$j];
				if(strlen($to)<2) continue;											// remove words smaller than 2 chars
				$nodes[] = $to;
				
				if(!isset($fromTo[$from]) || !isset($fromTo[$from][$to]))			// init
					$fromTo[$from][$to] = 0;
				
				$fromTo[$from][$to] += min($frequency[$words[$i]],$frequency[$words[$j]]);	// add per tweet cooccurence
			}
		}
	}

	$nodes = array_unique($nodes);
	$content = toGephi($fromTo,$nodes,$esc['shell']["datasetname"]);
	
	file_put_contents($filename,$content);
}
*/
echo '<fieldset class="if_parameters">';

echo '<legend>Your File</legend>';

echo '<p><a href="'.str_replace("#",urlencode("#"),str_replace("\"","%22",$filename)).'">' . $filename . '</a></p>';

echo '</fieldset>';

?>

</body>
</html>