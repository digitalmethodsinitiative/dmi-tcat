<?php
require_once './common/config.php';
require_once './common/functions.php';

$variability = false;       // @todo used as hack for experiment in first issue mapping workshop
$uselocalresults = false;   // @todo used as hack for experiment in first issue mapping workshop
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics GEXF</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>Twitter Analytics - Geo location</h1>

        <?php
        validate_all_variables();

        $sql = "SELECT * FROM ".$esc['mysql']['dataset']."_tweets t ";
        $where = "geo_lat != 0 AND geo_lng != 0 AND ";
        // @todo, add sqlinterval()
        $sql .= sqlSubset($where);
        
        //print $sql." - <br>";
        
        $sqlresults = mysql_query($sql);
        
        $filename = get_filename_for_export("location");
        $file = fopen($filename, "w");
        fputs($file, chr(239) . chr(187) . chr(191) .
              "time,created_at,from_user_name,from_user_tweetcount,from_user_followercount,from_user_lang,text,source,location,geo_lat,geo_lng\n");
        
        while ($res = mysql_fetch_assoc($sqlresults)) {
            fputs($file, strtotime($res["created_at"]) . "," . $res["created_at"] . "," . $res["from_user_name"] . "," . $res["from_user_tweetcount"] . "," . $res["from_user_followercount"] . "," . $res["from_user_lang"] . "," . validate($res["text"], "tweet") . ",\"" . strip_tags(html_entity_decode($res["source"])). "\",\"" . trim(strip_tags(html_entity_decode($res["location"]))). "\",".$res['geo_lat'].",".$res['geo_lng']."\n");
        }
       
        fclose($file);
        
        echo '<fieldset class="if_parameters">';

        echo '<legend>Your File</legend>';

        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
