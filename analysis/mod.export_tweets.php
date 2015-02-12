<?php
require_once './common/config.php';
require_once './common/functions.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Export Tweets</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">



        </script>

    </head>

    <body>

        <h1>TCAT :: Export Tweets</h1>

        <?php
        validate_all_variables();
        // make filename and open file for write
        $module = "fullExport";
        $exportSettings = array();
        if (isset($_GET['exportSettings']) && $_GET['exportSettings'] != "")
            $exportSettings = explode(",", $_GET['exportSettings']);
        if (isset($_GET['random']) && $_GET['random'] == 1) {
            $module = "randomTweets";
            $exportSettings[] = "1000";
        }
        if ((isset($_GET['location']) && $_GET['location'] == 1))
            $module = "geoTweets";
        $filename = get_filename_for_export($module, implode("_", $exportSettings));
        $file = fopen($filename, "w");
        fputs($file, chr(239) . chr(187) . chr(191));

        // write header
        $header = "id,time,created_at,from_user_name,text,filter_level,possibly_sensitive,withheld_copyright,withheld_scope,truncated,retweet_count,favorite_count,lang,to_user_name,in_reply_to_status_id,source,location,lat,lng,from_user_id,from_user_realname,from_user_verified,from_user_description,from_user_url,from_user_profile_image_url,from_user_utcoffset,from_user_timezone,from_user_lang,from_user_tweetcount,from_user_followercount,from_user_friendcount,from_user_favourites_count,from_user_listed,from_user_withheld_scope,from_user_created_at";
        if (array_search("urls", $exportSettings) !== false)
            $header .= ",urls,urls_expanded,urls_followed,domains,HTTP status code,url_is_media_upload,photo_sizes_width,photo_sizes_height";
        if (array_search("mentions", $exportSettings) !== false)
            $header .= ",mentions";
        if (array_search("hashtags", $exportSettings) !== false)
            $header .= ",hashtags";
        $header .= "\n";
        fputs($file, $header);

        // make query
        $sql = "SELECT * FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $where = "";
        if (isset($_GET['location']) && $_GET['location'] == 1)
            $where .= "geo_lat != 0 AND geo_lng != 0 AND ";
        $sql .= sqlSubset($where);
        if (isset($_GET['random']) && $_GET['random'] == 1)
            $sql .= "ORDER BY RAND() LIMIT " . $samplesize;
        else
            $sql .= " ORDER BY id";

        // loop over results and write to file
        $sqlresults = mysql_query($sql);
        if ($sqlresults) {
            while ($data = mysql_fetch_assoc($sqlresults)) {
                $out = "";
                if (preg_match("/_urls/", $sql))
                    $id = $data['tweet_id'];
                else
                    $id = $data['id'];
                $out .= $id . "," .
                        strtotime($data["created_at"]) . "," .
                        $data["created_at"] . "," .
                        "\"" . textToCSV($data["from_user_name"]) . "\"," .
                        "\"" . textToCSV($data["text"]) . "\"," .
                        $data['filter_level'] . "," .
                        (isset($data['possibly_sensitive']) ? $data['possibly_sensitive'] : "") . "," .
                        (isset($data['withheld_copyright']) ? $data['withheld_copyright'] : "") . "," .
                        (isset($data['withheld_scope']) ? $data['withheld_scope'] : "") . "," .
                        (isset($data['truncated']) ? $data['truncated'] : "") . "," .
                        (isset($data['retweet_count']) ? $data['retweet_count'] : "") . "," .
                        (isset($data['favorite_count']) ? $data['favorite_count'] : "") . "," .
                        (isset($data['lang']) ? "\"" . textToCSV($data['lang']) . "\"" : "") . "," .
                        (isset($data['to_user_name']) ? "\"" . textToCSV($data['to_user_name']) . "\"" : "") . "," .
                        (isset($data['in_reply_to_status_id']) ? $data['in_reply_to_status_id'] : "") . "," .
                        "\"" . textToCSV($data["source"]) . "\"," .
                        "\"" . textToCSV($data["location"]) . "\"," .
                        $data['geo_lat'] . "," .
                        $data['geo_lng'] . "," .
                        $data['from_user_id'] . "," .
                        (isset($data['from_user_realname']) ? "\"" . textToCSV($data['from_user_realname']) . "\"" : "") . "," .
                        $data['from_user_verified'] . "," .
                        "\"" . textToCSV($data['from_user_description']) . "\"," .
                        "\"" . textToCSV($data['from_user_url']) . "\"," .
                        "\"" . textToCSV($data['from_user_profile_image_url']) . "\"," .
                        (isset($data['from_user_utcoffset']) ? $data['from_user_utcoffset'] : "") . "," .
                        (isset($data['from_user_timezone']) ? $data['from_user_timezone'] : "") . "," .
                        "\"" . $data['from_user_lang'] . "\"," .
                        (isset($data['from_user_tweetcount']) ? $data['from_user_tweetcount'] : "") . "," .
                        (isset($data['from_user_followercount']) ? $data['from_user_followercount'] : "") . "," .
                        (isset($data['from_user_friendcount']) ? $data['from_user_friendcount'] : "") . "," .
                        (isset($data['from_user_favourites_count']) ? $data['from_user_favourites_count'] : "") . "," .
                        (isset($data['from_user_listed']) ? $data['from_user_listed'] : "") . "," .
                        (isset($data['from_user_withheld_scope']) ? $data['from_user_withheld_scope'] : "") . "," .
                        (isset($data['from_user_created_at']) ? $data['from_user_created_at'] : "");
                if (array_search("urls", $exportSettings) !== false) {
                    $urls = $expanded = $followed = $domain = "";
                    $sql2 = "SELECT * FROM " . $esc['mysql']['dataset'] . "_urls WHERE tweet_id = " . $data['id'];
                    $rec2 = mysql_query($sql2);
                    $urls = $expanded = $followed = $domain = $error = $media = $photo_width = $photo_height = array();
                    if (mysql_num_rows($rec2) > 0) {
                        while ($res2 = mysql_fetch_assoc($rec2)) {
                            $urls[] = $res2['url'];
                            $expanded[] = $res2['url_expanded'];
                            $followed[] = $res2['url_followed'];
                            $domain[] = $res2['domain'];
                            $error[] = $res2['error_code'];
                            if (isset($res2['url_is_media_upload'])) {
                                $media[] = $res2['url_is_media_upload'];
                                $photo_width[] = $res2['photo_size_width'];
                                $photo_height[] = $res2['photo_size_height'];
                            }
                        }
                    }
                    $out .= ",\"" . textToCSV(implode("; ", $urls)) . "\",\"" . textToCSV(implode("; ", $expanded)) . "\",\"" . textToCSV(implode("; ", $followed)) . "\",\"" . textToCSV(implode("; ", $domain)) . "\",\"" . textToCSV(implode("; ", $error)) . "\",\"" . textToCSV(implode("; ", $media)) . "\",\"" . textToCSV(implode("; ", $photo_width)) . "\",\"" . textToCSV(implode("; ", $photo_height)) . "\"";
                }
                if (array_search("mentions", $exportSettings) !== false) {
                    $sql2 = "SELECT * FROM " . $esc['mysql']['dataset'] . "_mentions WHERE tweet_id = " . $id;
                    $rec2 = mysql_query($sql2);
                    $mentions = array();
                    if (mysql_num_rows($rec2) > 0) {
                        while ($res2 = mysql_fetch_assoc($rec2)) {
                            $mentions[] = $res2['to_user'];
                        }
                    }
                    $out .= "," . implode("; ", $mentions);
                }
                if (array_search("hashtags", $exportSettings) !== false) {
                    $sql2 = "SELECT * FROM " . $esc['mysql']['dataset'] . "_hashtags WHERE tweet_id = " . $id;
                    $rec2 = mysql_query($sql2);
                    $hashtags = array();
                    if (mysql_num_rows($rec2) > 0) {
                        while ($res2 = mysql_fetch_assoc($rec2)) {
                            $hashtags[] = $res2['text'];
                        }
                    }
                    $out .= "," . implode("; ", $hashtags);
                }
                $out .= "\n";
                fputs($file, $out);
            }
        }
        fclose($file);

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
