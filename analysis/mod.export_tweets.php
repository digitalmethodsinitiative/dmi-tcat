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

        <h1>Twitter Analytics - Export Tweets</h1>

        <?php
        echo '<fieldset class="if_parameters">';

        echo '<legend>Your File</legend>';

        validate_all_variables();
        $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
        $filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . "_" . "fullExport.csv";


        $header = "id,time,created_at,from_user_name,from_user_lang,text,source,location,lat,lng,from_user_follower_count,from_user_friend_count,from_user_realname,to_user_name,in_reply_to_status_id,from_user_listed,from_user_utcoffset,from_user_timezone,from_user_description,from_user_url,from_user_verified,filter_level\n";

        $sql = "SELECT * FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();

        $sqlresults = mysql_query($sql);
        $out = $header;
        if ($sqlresults) {
            while ($data = mysql_fetch_assoc($sqlresults)) {
                $out .= $data['id'] . "," .
                        strtotime($data["created_at"]) . "," .
                        $data["created_at"] . "," .
                        $data["from_user_name"] . "," .
                        $data['from_user_lang'] . "," .
                        validate($data["text"], "tweet") . "," .
                        "\"" . strip_tags(html_entity_decode($data["source"])) . "\"," .
                        "\"" . preg_replace("/[\r\t\n,]/", " ", trim(strip_tags(html_entity_decode($data["location"])))) . "\"," .
                        $data['geo_lat'] . "," .
                        $data['geo_lng'] . "," .
                        (isset($data['from_user_follower_count']) ? $data['from_user_follower_count'] : "") . "," .
                        (isset($data['from_user_friend_count']) ? $data['from_user_friend_count'] : "") . "," .
                        (isset($data['from_user_realname']) ? $data['from_user_realname'] : "") . "," .
                        (isset($data['to_user_name']) ? $data['to_user_name'] : "") . "," .
                        (isset($data['in_reply_to_status_id']) ? $data['in_reply_to_status_id'] : "") . "," .
                        (isset($data['from_user_listed']) ? $data['from_user_listed'] : "") . "," .
                        (isset($data['from_user_utcoffset']) ? $data['from_user_utcoffset'] : "") . "," .
                        (isset($data['from_user_timezone']) ? $data['from_user_timezone'] : "") . "," .
                        "\"" . preg_replace("/[\r\t\n,]/", " ", trim(strip_tags(html_entity_decode($data['from_user_description'])))) . "\"," .
                        "\"" . preg_replace("/[\r\t\n,]/", " ", trim($data['from_user_url'])) . "\"," .
                        $data['from_user_verified'] . "," .
                        $data['filter_level'] . "\n";
            }
        }

        file_put_contents($filename, chr(239) . chr(187) . chr(191) . $out);


        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        ?>

    </body>
</html>
