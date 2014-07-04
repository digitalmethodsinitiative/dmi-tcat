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


        $header = "id,time,created_at,from_user_name,from_user_lang,text,source,location,lat,lng,from_user_follower_count,from_user_friend_count,from_user_realname,to_user_name,in_reply_to_status_id,from_user_listed,from_user_utcoffset,from_user_timezone,from_user_description,from_user_url,from_user_verified,filter_leveli,cld_name,cld_code,cld_reliable,cld_bytes,cld_percent";
        if (isset($_GET['includeUrls']) && $_GET['includeUrls'] == 1)
            $header .= ",urls,urls_expanded,urls_followed,domains";
        $header .= "\n";

	$langset = $esc['mysql']['dataset'] . '_lang';

        $sql = "SELECT * FROM " . $esc['mysql']['dataset'] . "_tweets t inner join $langset l on t.id = l.tweet_id ";
        $sql .= sqlSubset();

        $sqlresults = mysql_query($sql);
        $filename = get_filename_for_export("fullExportLang");
        $file = fopen($filename, "w");
        fputs($file, chr(239) . chr(187) . chr(191));
        fputs($file, $header);
        if ($sqlresults) {
            while ($data = mysql_fetch_assoc($sqlresults)) {
                $out = '';
                if (preg_match("/_urls/", $sql))
                    $id = $data['tweet_id'];
                else
                    $id = $data['id'];
                $out .= $id . "," .
                        strtotime($data["created_at"]) . "," .
                        $data["created_at"] . "," .
                        "\"" . cleanText($data["from_user_name"]) . "\"," .
                        "\"" . $data['from_user_lang'] . "\"," .
                        "\"" . validate($data["text"], "tweet") . "\"," .
                        "\"" . cleanText($data["source"]) . "\"," .
                        "\"" . cleanTExt($data["location"]) . "\"," .
                        $data['geo_lat'] . "," .
                        $data['geo_lng'] . "," .
                        (isset($data['from_user_follower_count']) ? $data['from_user_follower_count'] : "") . "," .
                        (isset($data['from_user_friend_count']) ? $data['from_user_friend_count'] : "") . "," .
                        (isset($data['from_user_realname']) ? "\"" . cleanText($data['from_user_realname']) . "\"" : "") . "," .
                        (isset($data['to_user_name']) ? "\"" . cleanText($data['to_user_name']) . "\"" : "") . "," .
                        (isset($data['in_reply_to_status_id']) ? $data['in_reply_to_status_id'] : "") . "," .
                        (isset($data['from_user_listed']) ? $data['from_user_listed'] : "") . "," .
                        (isset($data['from_user_utcoffset']) ? $data['from_user_utcoffset'] : "") . "," .
                        (isset($data['from_user_timezone']) ? $data['from_user_timezone'] : "") . "," .
                        "\"" . cleanText($data['from_user_description']) . "\"," .
                        "\"" . cleanText($data['from_user_url']) . "\"," .
                        $data['from_user_verified'] . "," .
                        $data['filter_level'];
		$out .= ",\"" . $data['name'] . "\"," .
			"\"" . $data['code'] . "\"," .
			(($data['reliable'] == true) ? 1 : 0) . "," .
			$data['bytes'] . "," .
			$data['percent'];
                if (isset($_GET['includeUrls']) && $_GET['includeUrls'] == 1) {
                    $urls = $expanded = $followed = $domain = "";
                    $sql2 = "SELECT url, url_expanded, url_followed, domain FROM " . $esc['mysql']['dataset'] . "_urls WHERE tweet_id = " . $data['id'];
                    $rec2 = mysql_query($sql2);
                    if (mysql_num_rows($rec2) > 0) {
                        $res2 = mysql_fetch_assoc($rec2);
                        $urls = $res2['url'] . " ; ";
                        $expanded = $res2['url_expanded'] . " ; ";
                        $followed = $res2['url_followed'] . " ; ";
                        $domain = $res2['domain'] . " ; ";
                    }
                    if (!empty($urls)) {
                        $urls = substr($urls, 0, -3);
                        $expanded = substr($expanded, 0, -3);
                        $followed = substr($followed, 0, -3);
                        $domain = substr($domain, 0, -3);
                    }
                    $out .= "," . $urls . "," . $expanded . "," . $followed . "," . $domain;
                }
                $out .= "\n";
                fputs($file, $out);
            }
        }

        function cleanText($text) {
            return preg_replace("/[\r\t\n,]/", " ", addslashes(trim(strip_tags(html_entity_decode($text)))));
        }

        fclose($file);

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
