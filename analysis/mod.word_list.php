<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/CSV.class.php';

$lowercase = isset($_GET['lowercase']) ? $lowercase = $_GET['lowercase'] : 0;
$minf = isset($_GET['minf']) ? $minf = $_GET['minf'] : 1;
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Word list</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Word list</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        $filename = get_filename_for_export("wordList");
        $csv = new CSV($filename, $outputformat);
        
        $sql = "SELECT id, text FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();

        $debug = '';

        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            $text = $data["text"];
            preg_match_all('/(https?:\/\/[^\s]+)|([\p{L}][\p{L}]+)/u', $text, $matches, PREG_PATTERN_ORDER);
            foreach ($matches[0] as $word) {
                if (preg_match('/(https?:\/\/)/u', $word))
                    continue;
                $word = strtolower($word);
                $csv->newrow();
                $csv->addfield(trim($word));
                $csv->addfield("{'ids': [" . $data['id'] . "]}");
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
