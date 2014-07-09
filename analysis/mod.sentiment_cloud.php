<?php
require_once './common/config.php';
require_once './common/functions.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Tool</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>Twitter Analytics - Sentiment Cloud</h1>

        <?php
        validate_all_variables();


        $header = "id,time,created_at,from_user_name,from_user_lang,text,source,location,lat,lng,from_user_follower_count,from_user_friend_count,from_user_realname,to_user_name,in_reply_to_status_id,from_user_listed,from_user_utcoffset,from_user_timezone,from_user_description,from_user_url,from_user_verified,filter_level";
        if (isset($_GET['includeUrls']) && $_GET['includeUrls'] == 1)
            $header .= ",urls,urls_expanded,urls_followed,domains";
        $header .= ",sentistrength,negative,positive";
        $header .= "\n";

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

        $out = "";
        $out .= "word\tcount\tsentistrength\n";
        arsort($positiveSentiments);
        foreach ($positiveSentiments as $word => $val)
            $out .= $word . "\t" . $val . "\t" . $wordValues[$word] . "\n";

        arsort($negativeSentiments);
        foreach ($negativeSentiments as $word => $val)
            $out .= $word . "\t" . $val . "\t" . $wordValues[$word] . "\n";


        $filename = get_filename_for_export("sentiment_cloud");
        file_put_contents($filename, chr(239) . chr(187) . chr(191) . $out);

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
