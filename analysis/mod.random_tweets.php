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

        <h1>Twitter Analytics - Random Tweets</h1>

        <table>


            <form action="<?php echo "/coword/" . $_SERVER["PHP_SELF"]; ?>">
                <input type="hidden" name="dataset" value="<?php echo $dataset; ?>" />
                <input type="hidden" name="query" value="<?php echo $query; ?>" />
                <input type="hidden" name="exclude" value="<?php echo $exclude; ?>" />
                <input type="hidden" name="from_user_name" value="<?php echo $from_user_name; ?>" />
                <input type="hidden" name="startdate" value="<?php echo $startdate; ?>" />
                <input type="hidden" name="enddate" value="<?php echo $enddate; ?>" />
                <tr>
                    <td>No. of tweets:</td>
                    <td><input type="text" name="samplesize" value="<?php echo $samplesize; ?>" /></td>
                </tr>
                <tr>
                    <td><input type="submit" value="create file" /></td>
                </tr>
            </form>

        </table>

        <?php
        if ($samplesize > 0) {

            echo '<fieldset class="if_parameters">';

            echo '<legend>Your File</legend>';

            validate_all_variables();
            $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
            $filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . "_" . $samplesize . "randomTweets.csv";

            if (1 || !file_exists($filename)) {

                $header = "time,created_at,from_user_name,from_user_lang,text,source,location,lat,lng,hashtags,urls\n";

                $sql = "SELECT * FROM " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
                $sql .= sqlSubset();
                $sql .= "ORDER BY RAND() LIMIT " . $samplesize;

                $sqlresults = mysql_query($sql);
                $content = array();
                while ($data = mysql_fetch_assoc($sqlresults)) {
                    $content[$data['id']] = strtotime($data["created_at"]) . "," . $data["created_at"] . "," . $data["from_user_name"] . "," . $data['from_user_lang'].",".validate($data["text"], "tweet") . ",\"" . strip_tags(html_entity_decode($data["source"])) . "\",\"" . preg_replace("/[\r\t\n,]/"," ",trim(strip_tags(html_entity_decode($data["location"])))) . "\"," . $data['geo_lat'] . "," . $data['geo_lng'];
                }

                // get hashtags
                $sql = "SELECT * FROM " . $esc['mysql']['dataset'] . "_hashtags WHERE ";
                $sql .= "tweet_id IN (" . implode(",", array_keys($content)) . ")";
                $sqlresults = mysql_query($sql);
                while ($data = mysql_fetch_assoc($sqlresults)) {
                    $hashtags[$data['tweet_id']][] = $data['text'];
                }

                // get urls
                $sql = "SELECT tweet_id,url_followed FROM " . $esc['mysql']['dataset'] . "_urls WHERE ";
                $sql .= "tweet_id IN (" . implode(",", array_keys($content)) . ")";
                //print $sql . "<br>";
                $sqlresults = mysql_query($sql);
                $urls = array();
                while ($data = mysql_fetch_assoc($sqlresults)) {
                    $urls[$data['tweet_id']][] = $data['url_followed'];
                }

                $out = $header;
                foreach ($content as $id => $line) {
                    $out .= $line;
                    if (isset($hashtags[$id]))
                        $out .= "," . implode(" ; ", $hashtags[$id]);
                    else
                        $out .= ",";
                    if (isset($urls[$id]))
                        $out .= "," . implode(" ; ", $urls[$id]);
                    else
                        $out .= ",";
                    if (substr($out, 0, -1) == ",")
                        $out = substr($out, 0, -1);
                    //$out .= ",".$id;
                    $out .= "\n";
                }

                file_put_contents($filename, chr(239) . chr(187) . chr(191) . $out);
            }

            echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';

            echo '</fieldset>';
        }
        ?>

    </body>
</html>
