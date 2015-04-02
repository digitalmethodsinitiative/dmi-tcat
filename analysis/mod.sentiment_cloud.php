<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/CSV.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Sentiment Cloud</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Sentiment Cloud</h1>

        <?php
        validate_all_variables();
        $filename = get_filename_for_export("sentiment_cloud");
        $csv = new CSV($filename, $outputformat);

        $sql = "SELECT s.explanation FROM " . $esc['mysql']['dataset'] . "_tweets t, " . $esc['mysql']['dataset'] . "_sentiment s ";
        $sql .= sqlSubset("s.tweet_id = t.id AND ");
        //print $sql . "<br>";die;
        $rec = mysql_query($sql);
        $negativeSentiments = $positiveSentiments = $wordValues = array();
        while ($res = mysql_fetch_assoc($rec)) {
            if (preg_match_all("/[\s|\B]([\p{L}\w\d_]+)\[(-?\d)\]/u", $res['explanation'], $matches)) {

                foreach ($matches[1] as $k => $word) {
                    $word = strtolower(trim($word));
                    $sentimentValue = (int) $matches[2][$k];

                    if ($sentimentValue < 0) {
                        if (array_key_exists($word, $negativeSentiments) === false)
                            $negativeSentiments[$word] = 0;
                        $negativeSentiments[$word]++;
                    } else {
                        if (array_key_exists($word, $positiveSentiments) === false)
                            $positiveSentiments[$word] = 0;
                        $positiveSentiments[$word]++;
                    }

                    $wordValues[$word] = $sentimentValue;
                }
            }
        }

        $csv->writeheader(array('word', 'count', 'sentistrength'));
        arsort($positiveSentiments);
        foreach ($positiveSentiments as $word => $val) {
            $csv->newrow();
            $csv->addfield($word);
            $csv->addfield($val);
            $csv->addfield($wordValues[$word]);
            $csv->writerow();
        }

        arsort($negativeSentiments);
        foreach ($negativeSentiments as $word => $val) {
            $csv->newrow();
            $csv->addfield($word);
            $csv->addfield($val);
            $csv->addfield($wordValues[$word]);
            $csv->writerow();
        }

        $csv->close();

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
