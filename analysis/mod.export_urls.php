<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
require_once __DIR__ . '/common/CSV.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Export URLs</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Export URLs</h1>

        <?php
        validate_all_variables();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        $filename = get_filename_for_export('urlsExport');
        $csv = new CSV($filename, $outputformat);

        $csv->writeheader(array('tweet_id', 'url', 'url_expanded', 'url_followed'));

        $sql = "SELECT t.id as id, u.url as url, u.url_expanded as url_expanded, u.url_followed as url_followed FROM " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND u.tweet_id = t.id ORDER BY id";

        $out = "";

        $rec = $dbh->prepare($sql);
        $rec->execute();
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            $csv->newrow();    
            $csv->addfield($data['id'], 'integer');
            $csv->addfield($data['url'], 'string');
            if (isset($data['url_followed']) && strlen($data['url_followed']) > 1) {
                $csv->addfield($data['url'], 'string');
            } else {
                $csv->addfield('', 'string');
            }
            if (isset($data['url_expanded']) && strlen($data['url_expanded']) > 1) {
                $csv->addfield($data['url_expanded'], 'string');
            } else {
                $csv->addfield('', 'string');
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
