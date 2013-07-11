<?php
require_once './common/config.php';
require_once './common/functions.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics :: Tweet stats</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>Twitter Analytics :: Tweet Stats</h1>

        <?php
// => gexf
// => time
        validate_all_variables();
// Output format: {dataset}_{query}_{startdate}_{enddate}_{from_user_name}_{output type}.{filetype}

        $exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];
        $filename = $resultsdir . $esc['shell']["datasetname"] . "_" . $esc['shell']["query"] . $exc . "_" . $esc['date']["startdate"] . "_" . $esc['date']["enddate"] . "_" . $esc['shell']["from_user_name"] . "_tweetStats.csv";

        // tweets in subset
        $sql = "SELECT count(distinct(t.id)) as count FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sqlresults = mysql_query($sql);
        $data = mysql_fetch_assoc($sqlresults);
        $numtweets = $data["count"];

        // tweet containing links
        $sql = "SELECT count(distinct(t.id)) AS count FROM " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t ";
        $where = "u.tweet_id = t.id AND ";
        $sql .= sqlSubset($where);
        //print $sql."<Br>";
        $sqlresults = mysql_query($sql);
        $numlinktweets = 0;
        if ($sqlresults && mysql_num_rows($sqlresults) > 0) {
            $res = mysql_fetch_assoc($sqlresults);
            $numlinktweets = $res['count'];
        } else $numlinktweets = 0;

        // number of tweets with hashtags
        $sql = "SELECT count(distinct(h.tweet_id)) as count FROM " . $esc['mysql']['dataset'] . "_hashtags h, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND t.id = h.tweet_id ";
        //print $sql . "<br>";
        $sqlresults = mysql_query($sql);
        if ($sqlresults && mysql_num_rows($sqlresults) > 0) {
            $data = mysql_fetch_assoc($sqlresults);
            $numTweetsWithHashtag = $data["count"];
        } else $numTweetsWithHashtag = 0;
        
        // number of tweets with mentions
        $sql = "SELECT count(distinct(m.tweet_id)) as count FROM " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND t.id = m.tweet_id ";
        //print $sql . "<br>";
        $sqlresults = mysql_query($sql);
        if ($sqlresults && mysql_num_rows($sqlresults) > 0) {
            $data = mysql_fetch_assoc($sqlresults);
            $numTweetsWithMentions = $data["count"];
        } else $numTweetsWithMentions = 0;
        
        // number of tweets starting with RT
        $sql = "SELECT count(distinct(id)) as count FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND text LIKE 'RT @%'";
        //print $sql . "<br>";
        $sqlresults = mysql_query($sql);
        if ($sqlresults && mysql_num_rows($sqlresults) > 0) {
            $data = mysql_fetch_assoc($sqlresults);
            $numTweetsWithRT = $data["count"];
        } else $numTweetsWithRT = 0;
        
        echo '<fieldset class="if_parameters">';
        echo '<legend>Tweet stats </legend>';
        echo '<p>';
        print "<table><thead><th>Number of tweets</th><th>Number of tweets with links</th><th>Number of tweets with hashtags</th><th>Number of tweest with mentions</th><th>Number of tweets starting with 'RT @'</th></tr></thead><tbody>";
        print "<tr><td>$numtweets</td><td>$numlinktweets</td><td>$numTweetsWithHashtag</td><td>$numTweetsWithMentions</td><td>$numTweetsWithRT</td></tr>";
        print "</tbody></table>";
        echo '</p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
