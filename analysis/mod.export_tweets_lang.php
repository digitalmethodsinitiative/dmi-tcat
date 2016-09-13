<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/CSV.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Export Tweets language</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Export Tweets language</h1>

        <?php
        validate_all_variables();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);
        /* @todo, use same export possibilities as mod.export_tweets.php */

        $header = "id,time,created_at,from_user_name,from_user_lang,text,source,location,lat,lng,from_user_tweetcount,from_user_followercount,from_user_friendcount,from_user_realname,to_user_name,in_reply_to_status_id,quoted_status_id,from_user_listed,from_user_utcoffset,from_user_timezone,from_user_description,from_user_url,from_user_verified,filter_leveli,cld_name,cld_code,cld_reliable,cld_bytes,cld_percent";
        if (isset($_GET['includeUrls']) && $_GET['includeUrls'] == 1)
            $header .= ",urls,urls_expanded,urls_followed,domains";
        $header .= "\n";

        $langset = $esc['mysql']['dataset'] . '_lang';

        $sql = "SELECT * FROM " . $esc['mysql']['dataset'] . "_tweets t inner join $langset l on t.id = l.tweet_id ";
        $sql .= sqlSubset();

        $filename = get_filename_for_export("fullExportLang");
        $csv = new CSV($filename, $outputformat);
        $csv->writeheader(explode(',', $header));
        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            $csv->newrow();
            if (preg_match("/_urls/", $sql))
                $id = $data['tweet_id'];
            else
                $id = $data['id'];
            $csv->addfield($id);
            $csv->addfield(strtotime($data["created_at"]));
            $fields = array( 'created_at', 'from_user_name', 'from_user_lang', 'text', 'source', 'location', 'geo_lat', 'geo_lng', 'from_user_tweetcount', 'from_user_followercount', 'from_user_friendcount', 'from_user_realname', 'to_user_name', 'in_reply_to_status_id', 'quoted_status_id', 'from_user_listed', 'from_user_utcoffset', 'from_user_timezone', 'from_user_description', 'from_user_url', 'from_user_verified', 'filter_level' );
            foreach ($fields as $f) {
                $csv->addfield(isset($data[$f]) ? $data[$f] : ''); 
            }
            $csv->addfield($data['name']);
            $csv->addfield($data['code']);
            $csv->addfield($data['reliable'] == true ? 1 : 0);
            $csv->addfield($data['bytes']);
            $csv->addfield($data['percent']);
            if (isset($_GET['includeUrls']) && $_GET['includeUrls'] == 1) {
                $urls = $expanded = $followed = $domain = "";
                $sql2 = "SELECT url, url_expanded, url_followed, domain FROM " . $esc['mysql']['dataset'] . "_urls WHERE tweet_id = " . $data['id'];
                $rec2 = $dbh->prepare($sql2);
                $rec2->execute();
                while ($res2 = $rec2->fetch(PDO::FETCH_ASSOC)) {
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
                $csv->addfield($urls);
                $csv->addfield($expanded);
                $csv->addfield($followed);
                $csv->addfield($domain);
            }
            $csv->writerow();
        }

        $csv->close();

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
