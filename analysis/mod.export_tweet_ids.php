<?php
require_once './common/config.php';
require_once './common/functions.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Export Tweet IDs</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Export Tweet IDs</h1>

        <?php
        validate_all_variables();


        $sql = "SELECT id FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sqlresults = mysql_query($sql);
        $out = "";
        if ($sqlresults) {
            while ($data = mysql_fetch_assoc($sqlresults)) {
                if (preg_match("/_urls/", $sql))
                    $id = $data['tweet_id'];
                else
                    $id = $data['id'];
                $out .= $id . "\n";
            }
        }

        $filename = get_filename_for_export("ids");
        file_put_contents($filename, chr(239) . chr(187) . chr(191) . $out);

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
