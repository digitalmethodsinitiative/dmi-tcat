<?php

/**
 * Class for stopword removal.
 * 
 * @author "Erik Borra" <erik@digitalmethods.net>
 */
class Stopwords {

    /**
     * @var string Filesystem-path to the stopwords file
     */
    public $stopwordsDir;

    /**
     * @var array Stopwords. 
     */
    public $stopwords;

    /**
     * Constructor, initializes the class variables. 
     */
    public function __construct() {
        $this->stopwordsDir = "common/stopwords";
        $this->stopwords = array();
    }

    /**
     * find all available lists
     * 
     * @return array filenames of stopword lists
     */
    public function getAvailableLists() {
        $lists = array();
        $lists["all"] = "all";
        if ($dh = opendir($this->stopwordsDir)) {
            while (($file = readdir($dh)) !== false) {
                if ($file != "." && $file != ".." && $file != "README" && $file != "CVS")
                    $lists[$file] = $file;
            }
            closedir($dh);
        }
        return $lists;
    }

    /**
     * load a list, allows for multiple lists to be loaded
     * 
     * @param string $language
     * @throws InputException  
     * @todo odd place for an InputException
     */
    public function loadList($language = "english") {
        if ($language == "all") {
            $this->loadAllLists();
            return;
        }
        $stopwords = array();
        $lists = $this->getAvailableLists();
        if (array_search($language, $lists) !== false) { // see whether list extists
            $stopwords = file($this->stopwordsDir . "/" . $language);  // load list
            foreach ($stopwords as $k => $s) {   // clean list
                $s = trim($s);
                if (!empty($s))
                    $this->stopwords[$s] = $s;
            }
        } else
            throw InputException("There is no stop word list for $language");
        $this->stopwords = array_unique($this->stopwords);
    }

    /**
     * Gets the list of available stopword lists, and loads them.
     */
    public function loadAllLists() {
        $lists = $this->getAvailableLists();
        foreach ($lists as $list) {
            if ($list == "all")
                continue;
            $this->loadList($list);
        }
    }

    /**
     * Remove stopwords from input text.
     * 
     * @param array $words
     * @return array 
     */
    public function removeStopwords($words) {
        return array_diff($words, $this->stopwords);  // remove stop words
    }

    public function isStopWord($word) {
        return isset($this->stopwords[$word]);
    }

}

?>
