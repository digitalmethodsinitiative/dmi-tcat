<?php
require_once './common/config.php';
require_once './common/functions.php';

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics URL hashtag co-occurence</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>Twitter Analytics - URL hashtag co-occurence</h1>

        <?php
        validate_all_variables();
// Output format: {dataset}_{query}_{startdate}_{enddate}_{from_user_name}_{output type}.{filetype}

        $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
        $filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . "_urlHashtag.csv";
        
        $sql = "SELECT COUNT(LOWER(h.text)) AS frequency, LOWER(h.text) AS hashtag, u.url_followed AS url, u.domain AS domain FROM ";
        $sql .= $esc['mysql']['dataset']."_tweets t, ".$esc['mysql']['dataset']."_hashtags h, ".$esc['mysql']['dataset']."_urls u WHERE ";
        $sql .= "t.id = h.tweet_id AND h.tweet_id = u.tweet_id AND u.url_followed !='' AND ";
        $sql .= sqlSubset();
        $sql .= " GROUP BY u.url_followed, LOWER(h.text) ORDER BY frequency DESC";
        //print $sql." - <br>";
        
        $sqlresults = mysql_query($sql);
        
        $content = "frequency, hashtag, url, domain\n";
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $content .= $res['frequency'].",".$res['hashtag'].",".$res['url'].",".$res['domain']."\n";
        }
        file_put_contents($filename,  chr(239) . chr(187) . chr(191) . $content);
        
        echo '<fieldset class="if_parameters">';

        echo '<legend>Your File</legend>';

        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
