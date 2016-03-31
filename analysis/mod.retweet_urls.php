<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Retweet URLs</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <style>
            .contropedia_inserted {
                color: #006837;
                background-color: rgb(221, 255, 221);
                line-height: 150%;
                padding: 2px;
            }

            .contropedia_deleted {
                color: #DB211D;
                background-color: rgb(247, 200, 200);
                line-height: 150%;
                padding: 2px;
            }
            body {
                width: 100000px;
            }
            #content {
                transform-origin: 0 0;
                -ms-transform: scale(1,1); /* IE 9 */
                -webkit-transform: scale(1,1); /* Chrome, Safari, Opera */
                transform: scale(1,1);
            }
            table {
                border-spacing: 0;
                margin: 0;
                padding: 0;
            }
            td {
                padding: 0;
                margin: 0;
                font-size: 5px;
            }
            .tweet {

            }
            .zoom {
                text-decoration: underline;
                cursor: pointer;
            }
            .seperator {
                padding-right: 2px; 
                border-right: 1px solid grey;
            }
        </style>
        <script type='text/javascript' src='scripts/jquery-1.7.1.min.js'></script>
        <script type='text/javascript'>
            $(document).ready(function(){
                var currentscale = 1;
                $('#zoomin').click(function(){
                    currentscale = currentscale + 0.4;
                    
                    $('#content').css({
                        'transform': 'scale(' + currentscale + ')',
                        '-moz-transform': 'scale(' + currentscale + ')',
                        '-webkit-transform': 'scale(' + currentscale  + ')'
                    });
                });
                $('#zoomout').click(function(){
                    currentscale = currentscale - 0.4;
                    if(currentscale < 0.2) {
                        currentscale = 0.2;
                    }
                    $('#content').css({
                        'transform': 'scale(' + currentscale + ')',
                        '-moz-transform': 'scale(' + currentscale + ')',
                        '-webkit-transform': 'scale(' + currentscale  + ')'
                    });
                });
                $('.tweetdiff').hover(
                function(){
                    $(this).find('.tweet').hide();
                    $(this).find('.diff').show();
                },
                function(){
                    $(this).find('.tweet').show();
                    $(this).find('.diff').hide();
                }
            );
            });
        </script>
    </head>

    <body>

        <h1>TCAT :: Retweet URLs</h1>

        <?php
        validate_all_variables();
        $collation = current_collation();

        $sql = "SELECT t.id, t.text COLLATE $collation as text, t.created_at, t.from_user_name COLLATE $collation as from_user_name, t.source COLLATE $collation as source FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " ORDER BY ID";
        //print $sql; die;
        $rec = mysql_unbuffered_query($sql);
        while ($res = mysql_fetch_assoc($rec)) {
            $tweets[$res['id']] = $res['text'];
            $dates[$res['id']] = $res['created_at'];
            $tweets_short[$res['id']] = trim(preg_replace("/:\s*$/", "", preg_replace("/[\"'“]/", "", preg_replace("/\.\.\./", "", preg_replace("/[\[]*https?:[^\s]*[\]]*/", "", preg_replace("/@[^\s]*/", "", preg_replace("/RT @.*?:\s*/", "", $res['text'])))))));
            $users[$res['id']] = $res['from_user_name'];
            $sources[$res['id']] = preg_replace("/<a href=.*>(.+?)<.*/","\\1",$res['source']);
        }
        mysql_free_result($rec);

        $tweets_short_count = array_count_values($tweets_short);
        foreach ($tweets_short_count as $short => $count) {
            if ($count > 1) {
                $tweets_short_ids = array_keys($tweets_short,$short);
                foreach ($tweets_short_ids as $id)
                    $retweets_short[$tweets[$id]] = $short;
            }
        }

        $indentation = array_values($retweets_short);
        $indentation = array_unique($indentation);
        $indentation = array_values($indentation);

        // find first occurrence of text of retweet
        foreach ($retweets_short as $text => $short_text) {
            $first_retweet[$text] = array_search(array_search($short_text, $retweets_short), $tweets);
        }
        $first_retweet_ids = array_values($first_retweet);
        sort($first_retweet_ids);
        $first_tweet = $tweets[$first_retweet_ids[0]];
        $first_tweet_short = $retweets_short[$first_tweet];

        // calculate levenshtein and diff for first occurences of modified text of retweet
        $levenshtein = $diffs = array();
        foreach ($first_retweet as $text => $id) {
            if (empty($levenshtein)) {
                $levenshtein[$first_tweet_short] = 0;
                $diffs[$retweets_short[$text]] = $retweets_short[$text];
            } else {
                $levenshtein[$retweets_short[$text]] = levenshtein($first_tweet_short, $retweets_short[$text]);
                $diffClass = new Diff();
                $diffs[$retweets_short[$text]] = $diffClass->renderDiff($diffClass->stringDiff($first_tweet_short, $retweets_short[$text]));
            }
        }
        
        echo "<Br>Zoom: <span id='zoomin' class='zoom'>in</span> / <span id='zoomout' class='zoom'>out</span><Br>";
        echo '<fieldset class="if_parameters">';

        echo '<legend>Retweet URLs</legend>';

        print "<div id='content'>";
        //$palette = array("108cbf", "f62221", "008027", "97c932", "fad40d", "f8962b", "c99997", "7e7e7e", "cbcbcb", "44bdec", "ff6364", "42c168", "bced5e"); // issue crawler
        //$palette = array_reverse($palette);
        $palette = array("#90C4A7", "#EDA391", "#DEE287", "#DAC0D8", "#E0B97B", "#D6D0AA", "#A9DFDE", "#A2D093", "#D4D5CC", "#BABD7B", "#D6B4A2", "#E8ABBC", "#B0C1D0", "#D9EFAC", "#B6EECA");

        asort($first_retweet);
        $distinct_retweets = count($indentation);
        print "<table cellspacing='0' cellpadding='0'>";
        // header row
        print "<tr><td>date</td><td>user</td><td>source</td><td class='seperator'>tweet</td>";
        foreach ($first_retweet_ids as $id)
            print "<td></td>";
        // content
        print "</tr>";
        foreach ($tweets as $id => $tweet) {
            $indent_level = -1;
            if (isset($first_retweet[$tweet]))
                $indent_level = array_search($retweets_short[$tweet], $indentation);
            $bgcolor = "";
            if ($indent_level >= 0) {
                $palette_index = $indent_level % count($palette);
                $bgcolor = "background-color:" . $palette[$palette_index] . ";";
            }

            print "<tr>";
            print "<td style='padding-right: 5px;'>" . $dates[$id] . "</td>";
            print "<td style='padding-right: 5px;'>" . $users[$id] . "</td>";
            print "<td style='padding-right: 5px;'>" . $sources[$id] . "</td>";

            if (!array_key_exists($tweet, $retweets_short)) {
                print "<td class='seperator' style='$bgcolor'>" . str_replace(" URL URL", " URL", preg_replace("/[\[]*https?:[^\s]*[\]]*/", "URL", $tweet)) . "</td>";
                // make sure we have enought tds
                for ($i = 0; $i < $distinct_retweets; $i++)
                    print "<td></td>";
                continue;
            } else {
                print "<td class='tweetdiff seperator'>";
                print "<span class='tweet' style='$bgcolor'>" . str_replace(" URL URL", " URL", preg_replace("/[\[]*https?:[^\s]*[\]]*/", "URL", $tweet)) . "</span>";
                print "<span class='diff' style='display:none'>" . $diffs[$retweets_short[$tweet]] . "</span>";
                print "</td>";
            }

            // indent
            for ($i = 0; $i < $indent_level; $i++) {
                print "<td></td>";
            }
            print "<td>";
            print "<span class='tweet' style='$bgcolor'>" . $retweets_short[$tweet] . "</span>";
            print "</td>";
            for ($i = $indent_level; $i < $distinct_retweets; $i++)
                print "<td></td>";
            print "</tr>";
        }
        print "</table>";
        print "</div>";
        echo '</fieldset>';
        ?>

    </body>
</html>

<?php

// adapted from https://github.com/thebuggenie/thebuggenie/blob/master/core/classes/TBGTextDiff.class.php
class Diff {

    function arrayDiff($old, $new) {
        $biggestMatch = 0;
        foreach ($old as $oldInd => $oldVal) {
            $newInds = array_keys($new, $oldVal);
            foreach ($newInds as $newInd) {
                $matches[$oldInd][$newInd] = isset($matches[$oldInd - 1][$newInd - 1]) ? $matches[$oldInd - 1][$newInd - 1] + 1 : 1;
                if ($matches[$oldInd][$newInd] > $biggestMatch) {
                    $biggestMatch = $matches[$oldInd][$newInd];
                    $oldMax = $oldInd + 1 - $biggestMatch;
                    $newMax = $newInd + 1 - $biggestMatch;
                }
            }
        }
        if ($biggestMatch === 0)
            return array(array('-' => $old, '+' => $new));
        return array_merge(
                        $this->arrayDiff(array_slice($old, 0, $oldMax), array_slice($new, 0, $newMax)), array_slice($new, $newMax, $biggestMatch), $this->arrayDiff(array_slice($old, $oldMax + $biggestMatch), array_slice($new, $newMax + $biggestMatch)));
    }

    function split($delimiters, $str) {
        return $delimiters ? preg_split("~(?<=[" . $delimiters . "])~", $str) : str_split($str); // positive lookbehind regex
    }

    function merge($array) {
        return implode("", $array);
    }

    function stringDiff($old, $new, $delimiters = " ,\n.;!?") { // word and sentence delimiters
        $diff = $this->arrayDiff($this->split($delimiters, $old), $this->split($delimiters, $new));
        $newDiff = array();
        $newKey = 0;
        foreach ($diff as $key => $val) {
            if (is_array($val)) {
                if (isset($newDiff[$newKey]))
                    $newKey++;
                $newDiff[$newKey]['+'] = $this->merge($val['+']);
                $newDiff[$newKey]['-'] = $this->merge($val['-']);
                $newKey++;
            }
            else {
                if (!isset($newDiff[$newKey]))
                    $newDiff[$newKey] = "";
                $newDiff[$newKey] .= $val;
            }
        }

        return $newDiff;
    }

    function sequentialChanges($diff) {
        $changes = array();
        $index = 0;
        foreach ($diff as $val) {
            if (is_array($val)) {
                if ($val['-'])
                    $changes[] = array("type" => "-", "val" => $val['-'], "pos" => $index);
                if ($val['+']) {
                    $changes[] = array("type" => "+", "val" => $val['+'], "pos" => $index);
                    $index += strlen($val['+']);
                }
            }
            else
                $index += strlen($val);
        }
        return $changes;
    }

    function renderDiff($diff) {
        $str = "";
        foreach ($diff as $val) {
            if (is_array($val)) {
                $del = $val['-'] !== array() && !empty($val['-']) ? "<span class='contropedia_deleted'>" . $val['-'] . "</span>" : '';
                $ins = $val['+'] !== array() && !empty($val['+']) ? "<span class='contropedia_inserted'>" . $val['+'] . "</span>" : '';
                $str .= $del . $ins;
            }
            else
                $str .= $val;
        }
        //logit("DIFF: $str\n");
        return $str;
    }

    function renderChanges($changes, $str = "") {
        foreach ($changes as $change) {
            if ($change['type'] === "+") {
                $str = substr_replace($str, $change['val'], $change['pos'], 0);
            }
            if ($change['type'] === "-") {
                $str = substr_replace($str, "", $change['pos'], strlen($change['val']));
            }
        }
        return $str;
    }

}
?>
