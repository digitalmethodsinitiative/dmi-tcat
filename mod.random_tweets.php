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

        $content = "time,created_at,from_user_name,text,source,location,lat,lng\n";

        $sql = "SELECT * FROM " . $esc['mysql']['dataset'] . "_tweets t WHERE ";
        $sql .= sqlSubset();
        $sql .= "ORDER BY RAND() LIMIT " . $samplesize;

        $sqlresults = mysql_query($sql);
        while ($data = mysql_fetch_assoc($sqlresults)) {
            $content .= strtotime($data["created_at"]) . "," . $data["created_at"] . "," . $data["from_user_name"] . "," . validate($data["text"], "tweet") . ",\"" . strip_tags(html_entity_decode($data["source"])). "\",\"" . trim(strip_tags(html_entity_decode($data["location"]))). "\",".$data['geo_lat'].",".$data['geo_lng']."\n"; // @todo, add stuff like location // @todo character encoding
        }

        file_put_contents($filename, chr(239) . chr(187) . chr(191) . $content);
    }

    echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';

    echo '</fieldset>';
}
?>

    </body>
</html>
