<?php

/*
 * Minimalist implementation of a CSV writer class
 */

class CSV {

    public $fp;
    public $delimiter;
    public $enclosure;

    private function textToCSV($text) {
        return preg_replace("/[\r\t\n]/", " ", html_entity_decode($text));
    }

    private function textToTSV($text) {
        return preg_replace("/[\r\t\n]/", " ", html_entity_decode($text));
    }

    public function __construct($filename, $outputformat = 'csv') {
        $this->fp = fopen($filename, "w");
        fputs($this->fp, chr(239) . chr(187) . chr(191));   // UTF-8 BOM
        if ($outputformat == 'csv') {
            $this->delimiter = ',';
            $this->enclosure = '"';
        } else {
            $this->delimiter = "\t";
            $this->enclosure = '"';
        }
        $this->newrow();
    }

    public function newrow() {
        $this->row = array();
    }

    public function writeheader($header) {
        if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 50504) {
            // null-character as escape character is neccessary due to PHP bug https://bugs.php.net/bug.php?id=43225
            fputcsv($this->fp, $header, $this->delimiter, $this->enclosure, "\0");
        } else {
            fputcsv($this->fp, $header, $this->delimiter, $this->enclosure);
        }
    }

    public function addfield($value, $type = 'string') {
        $value = trim($value);
        if (!empty($value) && $type == 'string') {
            if ($this->delimiter == ",") {
                $value = $this->textToCSV($value); 
            } else {
                $value = $this->textToTSV($value);
            }
        }
        $this->row[] = $value;
    }

    public function writerow() {
        if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 50504) {
            // null-character as escape character is neccessary due to PHP bug https://bugs.php.net/bug.php?id=43225
            fputcsv($this->fp, $this->row, $this->delimiter, $this->enclosure, "\0");
        } else {
            fputcsv($this->fp, $this->row, $this->delimiter, $this->enclosure);
        }
    }

    public function close() {
        fclose($this->fp);
    }

} 

