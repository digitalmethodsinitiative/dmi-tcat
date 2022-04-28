<?php
$config_file = '../config/config.json';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $filters=array(
        "CONSUMERKEY",
        "CONSUMERSECRET",
        "USERTOKEN",
        "USERSECRET",
        "CAPTURE_MODE",
        "URLEXPANDYES",
        "TCAT_AUTO_UPDATE",
    );

    $final=array();

    foreach ($filters as $filter) {
        if (in_array($filter, array("CAPTURE_MODE", "TCAT_AUTO_UPDATE"))) {
            // Convert these options to integers
            $final[$filter] = intval($_POST[$filter]);
        } else {
            $final[$filter] = $_POST[$filter];
        }
    }

    $fp = fopen($config_file, 'w');
    fwrite($fp, json_encode($final));
    fclose($fp);
    header("Location: /");
    exit;
} else {
    if (file_exists($config_file)) {
        // File already exists, therefore we should ensure user is an admin
        // Cannot check for admin until config.json exists as we cannot load config.php
        include_once __DIR__ . '/../config.php';
        include_once __DIR__ . '/../common/functions.php';

        if (!is_admin())
            die("Sorry, access denied. Your username does not match the ADMIN user defined in the config.php file.");

        $config_json = json_decode(file_get_contents($config_file), true);
    } else {
        $config_json = array();
        $config_json["CONSUMERKEY"] = '';
        $config_json["CONSUMERSECRET"] = '';
        $config_json["USERTOKEN"] = '';
        $config_json["USERSECRET"] = '';
        $config_json["CAPTURE_MODE"] = 1;
        $config_json["URLEXPANDYES"] = 'y';
        $config_json["TCAT_AUTO_UPDATE"] = 0;
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>TCAT Configuration</title>
</head>
<body>
<form action="" method="post" enctype="multipart/form-data">
    <h3>TCAT Configuration Parameters:</h3>

    <table>

        <tr>
            <td class="tbl_head">Consumer API Key: </td><td><input type="text" id="consumer_key" size="60" name="CONSUMERKEY" value="<?php echo $config_json["CONSUMERKEY"]; ?>" /></td>
        </tr>
        <tr>
            <td class="tbl_head">Consumer API Secret: </td><td><input type="text" id="consumer_secret" size="60" name="CONSUMERSECRET"  value="<?php echo $config_json["CONSUMERSECRET"]; ?>" /></td>
        </tr>
        <tr>
            <td class="tbl_head">Authentication Access Token: </td><td><input type="text" id="user_token" size="60" name="USERTOKEN"  value="<?php echo $config_json["USERTOKEN"]; ?>" /></td>
        </tr>
        <tr>
            <td class="tbl_head">Authentication Access Secret: </td><td><input type="text" id="user_secret" size="60" name="USERSECRET"  value="<?php echo $config_json["USERSECRET"]; ?>" /></td>
        </tr>
        <tr>
            <td class="tbl_head">Capture Mode: </td><td><input type="number" id="capture_mode" size="60" name="CAPTURE_MODE"  value="<?php echo $config_json["CAPTURE_MODE"]; ?>" /> (1=track phrases/keywords, 2=follow users, 3=onepercent)</td>
        </tr>
        <tr>
            <td class="tbl_head">Install URL Expander: </td><td><input type="text" id="url_expander" size="60" name="URLEXPANDYES"  value="<?php echo $config_json["URLEXPANDYES"]; ?>" /> ('y' to install or 'n' to not)</td>
        </tr>
        <tr>
            <td class="tbl_head">Auto Update TCAT: </td><td><input type="number" id="tcat_auto_update" size="60" name="TCAT_AUTO_UPDATE"  value="<?php echo $config_json["TCAT_AUTO_UPDATE"]; ?>" /> (0=off, 1=trivial, 2=substantial, 3=expensive)</td>
        </tr>
    </table>
    <input type="submit">
</form>
</body>
</html>