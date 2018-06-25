<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/CSV.class.php';

        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        $filename = get_filename_for_export('mentionExport');
        $stream_to_open = export_start($filename, $outputformat);

        $csv = new CSV($stream_to_open, $outputformat);

        $csv->writeheader(array('tweet_id', 'user_from_id', 'user_from_name', 'user_to_id', 'user_to_name', 'mention_type'));

        $sql = "SELECT t.id as id, t.text as text, m.from_user_id as user_from_id, m.from_user_name as user_from_name, m.to_user_id as user_to_id, m.to_user as user_to_name FROM " . $esc['mysql']['dataset'] . "_mentions m, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND m.tweet_id = t.id ORDER BY id";

        $out = "";

        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            $csv->newrow();    
            $csv->addfield($data['id'], 'integer');
            $csv->addfield($data['user_from_id'], 'integer');
            $csv->addfield($data['user_from_name'], 'string');
            $csv->addfield($data['user_to_id'], 'integer');
            $csv->addfield($data['user_to_name'], 'string');
            $csv->addfield(detect_mention_type($data['text'], $data['user_to_name']), 'string');
            $csv->writerow();
        }

        $csv->close();

    if (! $use_cache_file) {
        exit(0);
    }
    // Rest of script is the HTML page with a link to the cached CSV/TSV file.
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Export mentions</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Export mentions</h1>

        <?php
        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>
