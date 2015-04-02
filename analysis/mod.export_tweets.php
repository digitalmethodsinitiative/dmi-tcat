<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/CSV.class.php';
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
        $csv = new CSV($filename, $outputformat);

        // write header
        $header = "id,time,created_at,from_user_name,text,filter_level,possibly_sensitive,withheld_copyright,withheld_scope,truncated,retweet_count,favorite_count,lang,to_user_name,in_reply_to_status_id,source,location,lat,lng,from_user_id,from_user_realname,from_user_verified,from_user_description,from_user_url,from_user_profile_image_url,from_user_utcoffset,from_user_timezone,from_user_lang,from_user_tweetcount,from_user_followercount,from_user_friendcount,from_user_favourites_count,from_user_listed,from_user_withheld_scope,from_user_created_at";
        if (array_search("urls", $exportSettings) !== false)
            $header .= ",urls,urls_expanded,urls_followed,domains,HTTP status code";
        if (array_search("media", $exportSettings) !== false) {
            if (array_search("urls", $exportSettings) !== false) {
                // full export of followed urls and media
                $header .= ",media_id,media_urls,media_type,media_indice_start,media_indice_end,photo_sizes_width,photo_sizes_height,photo_resize";
            } else {
                // export non-followed media urls
                $header .= ",urls,urls_expanded,media_id,media_urls,media_type,media_indice_start,media_indice_end,photo_sizes_width,photo_sizes_height,photo_resize";
            }
        }
        if (array_search("mentions", $exportSettings) !== false)
            $header .= ",mentions";
        if (array_search("hashtags", $exportSettings) !== false)
            $header .= ",hashtags";
        $csv->writeheader(explode(',', $header));

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
                $csv->newrow();
                if (preg_match("/_urls/", $sql))
                    $id = $data['tweet_id'];
                else
                    $id = $data['id'];
                $csv->addfield($id);
                $csv->addfield(strtotime($data["created_at"]));
                $fields = array ( 'created_at', 'from_user_name', 'text', 'filter_level', 'possibly_sensitive', 'withheld_copyright', 'withheld_scope', 'truncated', 'retweet_count', 'favorite_count', 'lang', 'to_user_name', 'in_reply_to_status_id', 'source', 'location', 'geo_lat', 'geo_lng', 'from_user_id', 'from_user_realname', 'from_user_verified', 'from_user_description', 'from_user_url', 'from_user_profile_image_url', 'from_user_utcoffset', 'from_user_timezone', 'from_user_lang', 'from_user_tweetcount', 'from_user_followercount', 'from_user_friendcount', 'from_user_favourites_count', 'from_user_listed', 'from_user_withheld_scope', 'from_user_created_at' );
                foreach ($fields as $f) {
                    $csv->addfield(isset($data[$f]) ? $data[$f] : ''); 
                }
                if (array_search("urls", $exportSettings) !== false || 
                    array_search("media", $exportSettings) !== false) {
                    $urls = $expanded = $followed = $domain = $error = $media = $media_ids = $media_urls = $media_type = $photo_width = $photo_height = $photo_resize = $indice_start = $indice_end = array();
                    // lookup urls
                    if (array_search("urls", $exportSettings) !== false) {
                        $sql2 = "SELECT * FROM " . $esc['mysql']['dataset'] . "_urls WHERE tweet_id = " . $data['id'];
                        $rec2 = mysql_query($sql2);
                        if (mysql_num_rows($rec2) > 0) {
                            while ($res2 = mysql_fetch_assoc($rec2)) {
                                $urls[] = $res2['url'];
                                $expanded[] = $res2['url_expanded'];
                                $followed[] = $res2['url_followed'];
                                $domain[] = $res2['domain'];
                                $error[] = $res2['error_code'];
                            }
                        }
                    }
                    // lookup media from media table
                    if (array_search("media", $exportSettings) !== false) {
                        $sql3 = "SELECT * FROM " . $esc['mysql']['dataset'] . "_media WHERE tweet_id = " . $data['id'];
                        $rec3 = mysql_query($sql3);
                        if (mysql_num_rows($rec3) > 0) {
                            while ($res3 = mysql_fetch_assoc($rec3)) {
                                $urls[] = $res3['url'];
                                $expanded[] = $res3['url_expanded'];
                                $followed[] = '';
                                $domain[] = '';
                                $error[] = '';
                                $media_ids[] = $res3['id'];
                                $media_urls[] = $res3['media_url_https'];
                                $media_type[] = $res3['media_type'];
                                $photo_width[] = $res3['photo_size_width'];
                                $photo_height[] = $res3['photo_size_height'];
                                $photo_resize[] = $res3['photo_resize'];
                                $indice_start[] = $res3['indice_start'];
                                $indice_end[] = $res3['indice_end'];
                            }
                        }
                    }

                    if (array_search("media", $exportSettings) !== false && array_search("urls", $exportSettings) !== false) {
                        // full export of urls with media information
                        $csv->addfield(implode("; ", $urls));
                        $csv->addfield(implode("; ", $expanded));
                        $csv->addfield(implode("; ", $followed));
                        $csv->addfield(implode("; ", $domain));
                        $csv->addfield(implode("; ", $error));
                        $csv->addfield(implode("; ", $media_ids));
                        $csv->addfield(implode("; ", $media_urls));
                        $csv->addfield(implode("; ", $media_type));
                        $csv->addfield(implode("; ", $indice_start));
                        $csv->addfield(implode("; ", $indice_end));
                        $csv->addfield(implode("; ", $photo_width));
                        $csv->addfield(implode("; ", $photo_height));
                        $csv->addfield(implode("; ", $photo_resize));
                    } else if (array_search("urls", $exportSettings) !== false) {
                        // export of urls only
                        $csv->addfield(implode("; ", $urls));
                        $csv->addfield(implode("; ", $expanded));
                        $csv->addfield(implode("; ", $followed));
                        $csv->addfield(implode("; ", $domain));
                        $csv->addfield(implode("; ", $error));
                    } else {
                        // export of non-followed media urls
                        $csv->addfield(implode("; ", $urls));
                        $csv->addfield(implode("; ", $expanded));
                        $csv->addfield(implode("; ", $media_ids));
                        $csv->addfield(implode("; ", $media_urls));
                        $csv->addfield(implode("; ", $media_type));
                        $csv->addfield(implode("; ", $indice_start));
                        $csv->addfield(implode("; ", $indice_end));
                        $csv->addfield(implode("; ", $photo_width));
                        $csv->addfield(implode("; ", $photo_height));
                        $csv->addfield(implode("; ", $photo_resize));
                    }
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
                    $csv->addfield(implode("; ", $mentions));
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
                    $csv->addfield(implode("; ", $hashtags));
                }
                $csv->writerow();
            }
        }
        $csv->close();

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
