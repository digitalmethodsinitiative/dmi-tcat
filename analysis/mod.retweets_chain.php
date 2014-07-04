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
        validate_all_variables();
        $min_nr_of_nodes = $esc['shell']['minf'];
        if (isset($_GET['minf']) || !preg_match("/^\d+$/", $min_nr_of_nodes))
            $min_nr_of_nodes = 4;

        // get identical tweets
        $sql = "SELECT text, COUNT(text) AS count FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY text HAVING count >= " . $min_nr_of_nodes . " ORDER BY count DESC";
        //print $sql . "<br>";
        //flush();
        $rec = mysql_query($sql);
        print mysql_num_rows($rec) . " retweet chains found with more than " . $min_nr_of_nodes . " tweets<br>";
        flush();

        $header = "id,time,created_at,from_user_name,from_user_lang,text,source,location,lat,lng,from_user_follower_count,from_user_friend_count,from_user_realname,to_user_name,in_reply_to_status_id,from_user_listed,from_user_utcoffset,from_user_timezone,from_user_description,from_user_url,from_user_verified,filter_level\n";
        $out = $header;

        while ($res = mysql_fetch_assoc($rec)) {

            $text = $res['text'];

            /*
              // find first occurence
              $text_clean = preg_replace("/.*RT @.+?: /", "", $text);
              $sql2 = "SELECT * FROM " . $esc['mysql']['dataset'] . "_tweets WHERE text = '" . mysql_real_escape_string($text_clean) . "' ORDER BY created_at ASC LIMIT 1";
              //print $sql2 . "<br>";
              $rec2 = mysql_query($sql2);
              if (mysql_num_rows($rec2) > 0) {
              $data = mysql_fetch_assoc($rec2);
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
              } else {
              print "could not find first tweet for $text<br> failed query: $sql2<bR>";
              flush();
              }
             */

            // list other occurences
            $sql2 = "SELECT * FROM " . $esc['mysql']['dataset'] . "_tweets WHERE text = '" . mysql_real_escape_string($text) . "' ORDER BY created_at ASC";
            //print $sql2 . "<br>";
            flush();

            $rec2 = mysql_query($sql2);
            if ($rec2) {
                while ($data = mysql_fetch_assoc($rec2)) {
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
        }
        echo '<fieldset class="if_parameters">';

        echo '<legend>Your File</legend>';

        $filename = get_filename_for_export("retweets_chain", $min_nr_of_nodes);
        file_put_contents($filename, chr(239) . chr(187) . chr(191) . $out);

        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';

        echo '</fieldset>';
        //print strftime("%T", date('U')) . "<br>";
        ?>

    </body>
</html>
