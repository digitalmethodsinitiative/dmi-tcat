<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/CSV.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Export Tweets sentiment</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Export Tweets sentiment</h1>

        <?php
        validate_all_variables();

        /* @todo, use same export possibilities as mod.export_tweets.php */

        $filename = get_filename_for_export("fullExport-sentiment");
        $csv = new CSV($filename, $outputformat);
        $header = "id";
        $header .= ",sentistrength,negative,positive";
        $csv->writeheader($header);

        $sql = "SELECT s.positive, s.negative, s.explanation, t.from_user_name as user, t.id as tid FROM " . $esc['mysql']['dataset'] . "_sentiment s, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset("t.id = s.tweet_id AND ");
        $rec = mysql_query($sql);
        while ($res = mysql_fetch_assoc($rec)) {
            $sentiment[$res['tid']]['pos'] = $res['positive'];
            $sentiment[$res['tid']]['neg'] = $res['negative'];
            $sentiment[$res['tid']]['desc'] = $res['explanation'];
        }

        $sql = "SELECT * FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();

        $sqlresults = mysql_query($sql);
        if ($sqlresults) {
            while ($data = mysql_fetch_assoc($sqlresults)) {
                $csv->newrow();
                if (preg_match("/_urls/", $sql))
                    $id = $data['tweet_id'];
                else
                    $id = $data['id'];
                $csv->addfield($id);
                if (isset($sentiment[$id])) {
                    $csv->addfield($sentiment[$id]['desc']);
                    $csv->addfield($sentiment[$id]['pos']);
                    $csv->addfield($sentiment[$id]['neg']);
                }
                else {
                    for ($n = 0; $n < 3; $n++) $csv->addfield('');
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
