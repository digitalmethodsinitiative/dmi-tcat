<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/CSV.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Export hashtags</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Export hashtags</h1>

        <?php
        validate_all_variables();

        $filename = get_filename_for_export('hashtagExport');
        $csv = new CSV($filename, $outputformat);

        $csv->writeheader(array('tweet_id', 'hashtag'));

        $sql = "SELECT t.id as id, h.text as hashtag FROM " . $esc['mysql']['dataset'] . "_tweets t, " . $esc['mysql']['dataset'] . "_hashtags h ";
        $sql .= sqlSubset();
        $sql .= " AND h.tweet_id = t.id ORDER BY id";
        $sqlresults = mysql_query($sql);
        $out = "";
        if ($sqlresults) {
            while ($data = mysql_fetch_assoc($sqlresults)) {
                $csv->newrow();    
                $csv->addfield($data['id'], 'integer');
                $csv->addfield($data['hashtag'], 'string');
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
