<?php

/*
 * Simple coword calculation, all in memory
 *
 * WARNING: Because of PHP's very very very bad UTF-8 support, splitting tweets into words only works reliably for tweets consisting solely of latin chars (i.e. English)
 */
if (0) {
    $coword = new Coword;
    $coword->setDocuments(array("bla bla bla test test clash", "test test test bla bla", "clash bla test"));
    $coword->addDocument("bla, !test 345 cla5sh");
    $coword->iterate();

    var_export($coword->getCowordsAsCsv());
    print "\n\n";
    var_export($coword->getWordsAsCsv());
    print "\n";
}

class Coword {

    public $documents = array();
    public $punctuation = array();
    public $hashtags_are_separate_words;
    public $extract_only_hashtags;
    public $remove_stop_words;
    public $min_word_length;
    public $min_word_frequency;
    public $words = array();    // holds word frequencies
    public $cowords = array();  // holds coword frequencies
    public $document_word_frequencies = array(); // holds word frequencies per document
    public $simpleTokens;
    public $countWordOncePerDocument;
    public $distinctUsersForWord = array();
    public $userDiversity = array();
    public $wordFrequency = array();
    public $wordFrequencyDividedByUniqueUsers = array();
    public $wordFrequencyMultipliedByUniqueUsers = array();
    public $sentimentMin = array();
    public $sentimentMax = array();
    public $sentimentAvg = array();
    public $sentimentMaxAbs = array();
    public $sentimentDominant = array();

    function __construct() {
        $this->hashtags_are_separate_words = FALSE;
        $this->remove_stop_words = TRUE;
        $this->extract_only_hashtags = FALSE;
        $this->min_word_length = 2;
        $this->min_word_frequency = 2;
        $this->punctuation = array("\s", "\.", ",", "!", "\?", ":", ";", "\/", "&", "\^", "\$", "\|", "`", "~", "=", "\+", "\*", "\"", "'", "\(", "\)", "\]", "\[", "{", "}", "<", ">");
        $this->simpleTokens = FALSE;
        $this->countWordOncePerDocument = TRUE;
    }

    /*
     * preprocess strings as to ensure data validity
     */

    function preprocess($v) {
        $v = strtolower($v);
        $v = trim($v);
        $v = html_entity_decode($v);
        $v = preg_replace("/https?:\/\/[^\s]*/", " ", $v);    // remove urls
        return $v;
    }

    function iterate() {

        // first get a list of all words in the corpus + their frequencies
        foreach ($this->documents as $k => $v) {

            // get words
            $words = $this->getWordsInString($v);               // get words
            $frequency = array_count_values($words);            // word frequency
            ksort($frequency);
            $words_in_document = array_keys($frequency);        // get unique words

            foreach ($frequency as $word => $count) {
                if (strlen($word) < $this->min_word_length) {    // remove small words
                    unset($frequency[$word]);
                    continue;
                }
                $this->addWord($word, $count);                  // add word to list of all words in corpus
            }
            $this->document_word_frequencies[$k] = $frequency;
        }
        // remove words appearing less than x times
        foreach ($this->words as $word => $frequency) {
            if ($frequency < $this->min_word_frequency)
                unset($this->words[$word]);
        }
        arsort($this->words);

        // remove stop words
        include_once __DIR__ . '/common/Stopwords.class.php';
        $sw = new Stopwords();
        $sw->loadAllLists();
        $cleaned = $sw->removeStopwords(array_keys($this->words));
        //print count($this->words) . " vs " . count($cleaned) . "<bR>";
        flush();
        foreach ($this->words as $word => $c) {
            if (array_search($word, $cleaned) === false)
                unset($this->words[$word]);
        }
        unset($cleaned);
        //print "done<bR>";
        flush();

        // list cowords
        foreach ($this->document_word_frequencies as $k => $frequency) {
            $words_in_document = array_keys($frequency);
            for ($i = 0; $i < count($words_in_document); $i++) {
                $from = $words_in_document[$i];
                if (!isset($this->words[$from]))
                    continue;
                for ($j = $i + 1; $j < count($words_in_document); $j++) {
                    $to = $words_in_document[$j];
                    if (!isset($this->words[$to]))
                        continue;
                    $this->addCoword($from, $to, min($frequency[$words_in_document[$i]], $frequency[$words_in_document[$j]]));
                }
            }
        }

        // @todo add minimum_coword_frequency
    }

    /*
     * Tokenize
     */

    function getWordsInString($v) {
        if ($this->getSimpleTokens())
            $sp = explode(" ", $v);
        else {
            $punctuation = $this->getPunctuation();
            if (!$this->getHashtags_are_separate_words())
                $punctuation[] = "#";
            $regexp = '/([' . implode("", $punctuation) . "]+)/u";  // @todo, this only works for latin chars, strings with non-ascii will become empty with this regexp
            $v = preg_replace($regexp, " ", $v);                  // replace punctuation by whitespace
            $v = preg_replace("/[\s\t\n\r]+/", " ", $v);          // replace whitespace characters by single whitespace
            $sp = preg_split("/\s/u", $v, 0, PREG_SPLIT_NO_EMPTY);
            if ($this->getExtract_only_hashtags()) {
                foreach ($sp as $k => $v)
                    if ($v[0] !== "#")
                        unset($sp[$k]);
            }
        }
        return $sp;
    }

    public function getDocuments() {
        return $this->documents;
    }

    public function setDocuments($documents) {
        $this->documents = $documents;
    }

    public function addDocument($document) {
        $this->documents[] = $this->preprocess($document);
    }

    public function getPunctuation() {
        return $this->punctuation;
    }

    public function setPunctuation($punctuation) {
        $this->punctuation = $punctuation;
    }

    public function getHashtags_are_separate_words() {
        return $this->hashtags_are_separate_words;
    }

    public function setHashtags_are_separate_words($hashtags_are_separate_words) {
        $this->hashtags_are_separate_words = $hashtags_are_separate_words;
    }

    public function getMin_word_length() {
        return $this->min_word_length;
    }

    public function setMin_word_length($min_word_length) {
        $this->min_word_length = $min_word_length;
    }

    public function addWord($word, $count = 0) {
        if (!isset($this->words[$word]))
            $this->words[$word] = 0;
        if ($this->countWordOncePerDocument)
            $this->words[$word] += 1;       // count word once per document
        else
            $this->words[$word] += $count;  // within document weight
    }

    // removes connections with a degree < $mindegree
    // @todo, does not check whether after application there are still words left without connections // only necessary if $this->words is requested
    public function applyMinDegree($mindegree) {
        foreach ($this->cowords as $word => $connections) {
            foreach ($connections as $coword => $freq) {
                if ($freq < $mindegree) {
                    unset($this->cowords[$word][$coword]);
                }
            }
            if (empty($this->cowords[$word]))
                unset($this->cowords[$word]);
        }
    }

    // removes connections with a frequency < $minfreq
    // @todo, does not check whether after application there are still words left without connections // only necessary if $this->words is requested
    public function applyMinFreq($minfreq) {
        foreach ($this->cowords as $word => $connections) {
            foreach ($connections as $coword => $freq) {
                if ($this->wordFrequency[$coword] < $minfreq) {
                    unset($this->cowords[$word][$coword]);
                }
            }
            if ($this->wordFrequency[$word] < $minfreq) {
                unset($this->cowords[$word]);
            }
        }
    }

    // @todo, gets too little
    public function applyTopUnits($topu) {

        // create a list of the top n keywords and use that to filter out all the others
        $toplist = $this->wordFrequency;
        arsort($toplist);
        // getting the frequency of the top n word to solve cutoff problem when two words have the same frequency
        $toplist = array_slice($toplist, $topu - 1, 1);
        $topvalues = array_values($toplist);
        $minfreq = array_shift($topvalues);

        foreach ($this->cowords as $word => $connections) {
            foreach ($connections as $coword => $freq) {
                if (isset($this->wordFrequency[$coword]) && $this->wordFrequency[$coword] < $minfreq) { // @todo, utf-8 encoded chars don't exist as keys
                    unset($this->cowords[$word][$coword]);
                } 
            }
            if (isset($this->wordFrequency[$word]) && $this->wordFrequency[$word] < $minfreq) {
                foreach ($connections as $coword => $freq) {
                    if ($freq >= $minfreq) {
                        if (!isset($this->cowords[$coword]))
                            $this->cowords[$coword] = array();
                    }
                }
                unset($this->cowords[$word]);
            } 
        }
    }

    function getWords() {
        arsort($this->words);
        return $this->words;
    }

    function getWordsAsCsv() {
        $out = "";
        $words = $this->getWords();
        foreach ($words as $word => $frequency)
            $out .= "$word,$frequency\n";
        return substr($out, 0, -1);
    }

    function addCoword($from, $to, $frequency) {
        if (!isset($this->cowords[$from]) || !isset($this->cowords[$from][$to]))
            $this->cowords[$from][$to] = 0;
        $this->cowords[$from][$to] += $frequency; // within document weight @todo provide option to count each coword pair only once per doc
    }

    function getCowords() {
        return $this->cowords;
    }

    function getCowordsAsCsv() {
        $out = "";
        $cowords = $this->getCowords();
        foreach ($cowords as $word => $connections) {
            foreach ($connections as $coword => $frequency) {
                $out .= "$word,$coword,$frequency\n";
            }
        }
        return substr($out, 0, -1);
    }

    function getCowordsAsGexf($title = "") {
        include_once __DIR__ . '/Gexf.class.php';
        $gexf = new Gexf();
        $gexf->setTitle("Co-word " . $title);
        $gexf->setEdgeType(GEXF_EDGE_UNDIRECTED);
        $gexf->setMode(GEXF_MODE_DYNAMIC);
        $gexf->setTimeFormat(GEXF_TIMEFORMAT_DATE);
        $gexf->setCreator("tools.digitalmethods.net");

        foreach ($this->cowords as $word => $cowords) {
            if (empty($cowords)) {
                $node1 = new GexfNode($word);
                if (isset($this->words[$word]))
                    $node1->addNodeAttribute("word_frequency", $this->words[$word], $type = "int");
                $this->addNodeExtraNodeAttributes($node1, $word);
                $gexf->addNode($node1);
            } else {
                foreach ($cowords as $coword => $coword_frequency) {
                    $node1 = new GexfNode($word);
                    if (isset($this->words[$word]))
                        $node1->addNodeAttribute("word_frequency", $this->words[$word], $type = "int");
                    $this->addNodeExtraNodeAttributes($node1, $word);
                    $gexf->addNode($node1);
                    $node2 = new GexfNode($coword);
                    if (isset($this->words[$coword]))
                        $node2->addNodeAttribute("word_frequency", $this->words[$coword], $type = "int");
                    $this->addNodeExtraNodeAttributes($node2, $coword);
                    $gexf->addNode($node2);
                    $edge_id = $gexf->addEdge($node1, $node2, $coword_frequency);
                }
            }
        }

        $gexf->render();
        return $gexf->gexfFile;
    }

    public function addNodeExtraNodeAttributes(&$node, $word) {
        if (isset($this->wordFrequency[$word]))
            $node->addNodeAttribute("wordFrequency", $this->wordFrequency[$word], $type = "int");
        if (isset($this->cowords[$word]))
            $node->addNodeAttribute("cowordFrequency", array_sum($this->cowords[$word]), $type = "int");
        if (isset($this->distinctUsersForWord[$word]))
            $node->addNodeAttribute("distinctUsersForWord", $this->distinctUsersForWord[$word], $type = "int");
        if (isset($this->userDiversity[$word]))
            $node->addNodeAttribute("userDiversity", $this->userDiversity[$word], $type = "float");
        if (isset($this->wordFrequencyDividedByUniqueUsers[$word]))
            $node->addNodeAttribute("wordFrequencyDividedByUniqueUsers", $this->wordFrequencyDividedByUniqueUsers[$word], $type = "float");
        if (isset($this->wordFrequencyMultipliedByUniqueUsers[$word]))
            $node->addNodeAttribute("wordFrequencyMultipliedByUniqueUsers", $this->wordFrequencyMultipliedByUniqueUsers[$word], $type = "int");
        if (isset($this->sentimentMax[$word]))
            $node->addNodeAttribute("sentiment_max", $this->sentimentMax[$word], $type = "integer");
        if (isset($this->sentimentMin[$word]))
            $node->addNodeAttribute("sentiment_min", $this->sentimentMin[$word], $type = "integer");
        if (isset($this->sentimentAvg[$word]))
            $node->addNodeAttribute("sentiment_avg", $this->sentimentAvg[$word], $type = "float");
        if (isset($this->sentimentMaxAbs[$word]))
            $node->addNodeAttribute("sentiment_max_absolute", $this->sentimentMaxAbs[$word], $type = "integer");
        if (isset($this->sentimentDominant[$word]))
            $node->addNodeAttribute("sentiment_dominant", $this->sentimentDominant[$word], $type = "integer");
    }

    public function getExtract_only_hashtags() {
        return $this->extract_only_hashtags;
    }

    public function setExtract_only_hashtags($extract_only_hashtags) {
        $this->extract_only_hashtags = $extract_only_hashtags;
    }

    public function getRemove_stop_words() {
        return $this->remove_stop_words;
    }

    public function setRemove_stop_words($remove_stop_words) {
        $this->remove_stop_words = $remove_stop_words;
    }

    public function getMin_word_frequency() {
        return $this->min_word_frequency;
    }

    public function setMin_word_frequency($min_word_frequency) {
        $this->min_word_frequency = $min_word_frequency;
    }

    public function getSimpleTokens() {
        return $this->simpleTokens;
    }

    public function setSimpleTokens($simpleTokens) {
        $this->simpleTokens = $simpleTokens;
    }

}

?>
