<?php
require_once './common/config.php';
require_once './common/functions.php';

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics :: User list</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">



        </script>

    </head>

    <body>

        <h1>Twitter Analytics :: User Stats</h1>

        <?php
// => gexf
// => time
        validate_all_variables();
// Output format: {dataset}_{query}_{startdate}_{enddate}_{from_user_name}_{output type}.{filetype}

        $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
        $filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . "_userList.csv";

        // tweets per user
        $sql = "SELECT from_user_id,from_user_name,from_user_realname,from_user_lang,from_user_tweetcount,from_user_followercount,from_user_friendcount,from_user_listed,from_user_utcoffset,from_user_verified,count(distinct(id)) AS count,from_user_description,from_user_url FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY from_user_id"; // @todo, does this assure most recent user stats?
        $sqlresults = mysql_query($sql);
        $array = array();
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $array[] = preg_replace("/[\n\r\t]/"," ",str_replace(","," ",$res));
        }


        $content = "from_user_id,from_user_name,from_user_realname,from_user_lang,from_user_tweetcount,from_user_followercount,from_user_friendcount,from_user_listed,from_user_utcoffset,from_user_verified,tweetsInSelection,from_user_description,from_user_url\n";
        foreach($array as $a) {
            $content .= $a["from_user_id"].",".$a["from_user_name"].",".$a["from_user_realname"].",".$a["from_user_lang"].",".$a["from_user_tweetcount"].",".$a["from_user_followercount"].",".$a["from_user_friendcount"].",".$a["from_user_listed"].",".$a["from_user_utcoffset"].",".$a["from_user_verified"].",".$a["count"] . ",".$a['from_user_description'].",".$a['from_user_url']."\n";
        }

        file_put_contents($filename,  chr(239) . chr(187) . chr(191) . $content);

        echo '<fieldset class="if_parameters">';
        echo '<legend>User list</legend>';
        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';
        echo '</fieldset>';



        ?>

    </body>
</html>
