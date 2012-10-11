<?php

/*
 * Simple coword calculation, all in memory
 */

$coword = new Coword;
$coword->setDocuments(array("bla bla bla test test clash", "test test test bla bla", "clash bla test"));
$coword->addDocument("bla, !test 345 cla5sh");
$coword->iterate();

if (0) {
    var_export($coword->getCowordsAsCsv());
    print "\n\n";
    var_export($coword->getWordsAsCsv());
    print "\n";
}

class Coword {

    public $documents = array();
    public $punctuation = array();
    public $hashtags_are_separate_words;
    public $min_word_length;
    public $words = array();    // holds word frequencies
    public $cowords = array();  // holds coword frequencies

    function __construct() {
        $this->hashtags_are_separate_words = FALSE;
        $this->min_word_length = 2;
        $this->punctuation = array("\s", "\.", ",", "!", "\?", ":", ";", "\/", "\\", "@", "&", "\^", "\$", "\|", "`", "~", "=", "\+", "\*", "\"", "'", "\(", "\)", "\]", "\[", "\{", "\}", "<", ">", "�");
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

        foreach ($this->documents as $k => $v) {

            // get words
            $words = $this->getWordsInString($v);                       // get words
            $frequency = array_count_values($words);            // word frequency
            ksort($frequency);
            $words = array_keys($frequency);                    // get unique words
            // @todo, remove stopwords
            // 
            // list cowords
            for ($i = 0; $i < count($words); $i++) {
                $from = $words[$i];
                if (strlen($from) < $this->getMin_word_length())
                    continue;           // do not consider words smaller than 2 chars
                for ($j = $i + 1; $j < count($words); $j++) {
                    $to = $words[$j];
                    if (strlen($to) < $this->getMin_word_length())
                        continue;           // do not consider words smaller than 2 chars
                    $this->addCoword($from, $to, min($frequency[$words[$i]], $frequency[$words[$j]]));
                }
            }
            // keep track of word frequencies
            foreach ($frequency as $word => $count)
                $this->addWord($word, $count);
        }
    }

    /*
     * Remove non-words
     */

    function getWordsInString($v) {
        $punctuation = $this->punctuation;
        if (!$this->hashtags_are_separate_words)
            $punctuation[] = "#";
        $regexp = '/([' . implode("|", $punctuation) . "]+)/u";
        $v = preg_replace($regexp, " ",$v);
        $v = preg_replace("/[\s\t\n\r]+/", " ", $v);          // replace whitespace characters by single whitespace
        return explode(" ",$v);
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

    public function addWord($word, $count) {
        if (!isset($this->words[$word]))
            $this->words[$word] = 0;
        $this->words[$word] += $count;  // within document weight @todo provide option to count each word only once per doc
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
        $this->cowords[$from][$to] += $frequency; // within document weight @todo provide option to count each coword only once per doc
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
        include_once('Gexf.class.php');
        $gexf = new Gexf();
        $gexf->setTitle("Co-word " . $title);
        $gexf->setEdgeType(GEXF_EDGE_UNDIRECTED);
        $gexf->setMode(GEXF_MODE_DYNAMIC);
        $gexf->setTimeFormat(GEXF_TIMEFORMAT_DATE);
        $gexf->setCreator("tools.digitalmethods.net");

        foreach ($this->cowords as $word => $cowords) {
            foreach ($cowords as $coword => $coword_frequency) {
                $node1 = new GexfNode($word);
                if (isset($this->words[$word]))
                    $node1->addNodeAttribute("word_frequency", $this->words[$word], $type = "int");
                $gexf->addNode($node1);
                $node2 = new GexfNode($coword);
                if (isset($this->words[$coword]))
                    $node2->addNodeAttribute("word_frequency", $this->words[$coword], $type = "int");
                $gexf->addNode($node2);
                $edge_id = $gexf->addEdge($node1, $node2, $coword_frequency);
            }
        }

        $gexf->render();
        return $gexf->gexfFile;
    }

}

?>
