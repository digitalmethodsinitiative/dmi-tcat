<?php
/*
 * Configuration setup
 *
 * This file is used to create a config.json file that is used specifically by
 * the docker/config.php.example version of config.php.
 *
 * It also can be used to create Apache users via create_apache_users.sh.
 * Appropriate permissions must be granted AT YOUR OWN RISK and will not
 * function by default. This should not allow users to be created more than once
 * (based on the apache_file already existing).
 *
 * In the future, the manual installation via helpers/tcat-install-linux.sh
 * could be modified to also use this frontend configuration.
 */
//
// Apache user file is used to determine if admin and basic users have been created
$apache_file = '/etc/apache2/tcat.htpasswd';

$config_file = '../config/config.json';
// Check that user is an admin user
if (file_exists($config_file)) {
    // File already exists, therefore we should ensure user is an admin
    // Cannot check for admin until config.json exists as we cannot load config.php
    include_once __DIR__ . '/../config.php';
    include_once __DIR__ . '/../common/functions.php';
    // check if admin
    if (!is_admin())
        die("Sorry, access denied. Your username does not match the ADMIN user defined in the config.php file.");
} else {
    // Allow configuration to be used by anyone for the first time
    // This should only occur at initial setup
}

// Checks if background process is running
function isRunning($pid){
    try{
        $result = shell_exec(sprintf("ps %d", $pid));
        if( count(preg_split("/\n/", $result)) > 2){
            return true;
        }
    }catch(Exception $e){}

    return false;
}

// Update or Load config.json
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update config.json if POST method
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

    if (!file_exists($apache_file)) {
        // Also create Apache users
        $cmd = "sudo ../helpers/create_apache_users.sh \"${_POST["admin_username"]}\" \"${_POST["admin_password"]}\" \"${_POST["basic_username"]}\" \"${_POST["basic_password"]}\"";
        $outputfile = '../config/create_apache_users_output.txt';
        $pidfile = '../config/create_apache_users.pid';

        exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));

        echo "Adding admin user: \"${_POST["admin_username"]}\" with password: \"${_POST["admin_password"]}\"";
        echo "Adding basic user: \"${_POST["basic_username"]}\" with password: \"${_POST["basic_password"]}\"";
        echo "Please wait, you will be redirected...";

        while (isRunning($pidfile)) {
            sleep(.5);
        }
    }
    echo 'Updated settings';
    exit;
} else {
    // Load config.json or defaults for form
    if (file_exists($config_file)) {
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

        <?php if (!file_exists($apache_file)) {
            echo <<<EOL
<tr>
<td class="tbl_head">Admin Username: </td><td><input type="text" id="username" required size="60" name="admin_username" value="admin" /></td>
</tr>
<tr>
<td class="tbl_head">Admin Password: </td><td><input type="password" id="new-password" required autocomplete="new-password" size="60" name="admin_password" value="" /></td>
</tr>
<tr>
<td class="tbl_head">Analysis Only Username: </td><td><input type="text" id="username_2" required size="60" name="basic_username" value="tcat" /></td>
</tr>
<tr>
<td class="tbl_head">Analysis Only Password: </td><td><input type="password" id="new-password_2" required autocomplete="new-password" size="60" name="basic_password" value="" /></td>
</tr>
EOL;
        }?>

        <tr>
            <td class="tbl_head">Consumer API Key: </td><td><input type="text" id="consumer_key" required size="60" name="CONSUMERKEY" value="<?php echo $config_json["CONSUMERKEY"]; ?>" /></td>
        </tr>
        <tr>
            <td class="tbl_head">Consumer API Secret: </td><td><input type="text" id="consumer_secret" required size="60" name="CONSUMERSECRET"  value="<?php echo $config_json["CONSUMERSECRET"]; ?>" /></td>
        </tr>
        <tr>
            <td class="tbl_head">Authentication Access Token: </td><td><input type="text" id="user_token" required size="60" name="USERTOKEN"  value="<?php echo $config_json["USERTOKEN"]; ?>" /></td>
        </tr>
        <tr>
            <td class="tbl_head">Authentication Access Secret: </td><td><input type="text" id="user_secret" required size="60" name="USERSECRET"  value="<?php echo $config_json["USERSECRET"]; ?>" /></td>
        </tr>
        <tr>
            <td class="tbl_head">Capture Mode: </td><td><input type="number" id="capture_mode" required size="60" name="CAPTURE_MODE"  value="<?php echo $config_json["CAPTURE_MODE"]; ?>" /> (1=track phrases/keywords, 2=follow users, 3=onepercent)</td>
        </tr>
        <tr>
            <td class="tbl_head">Install URL Expander: </td><td><input type="text" id="url_expander" required size="60" name="URLEXPANDYES"  value="<?php echo $config_json["URLEXPANDYES"]; ?>" /> ('y' to install or 'n' to not)</td>
        </tr>
        <tr>
            <td class="tbl_head">Auto Update TCAT: </td><td><input type="number" id="tcat_auto_update" required size="60" name="TCAT_AUTO_UPDATE"  value="<?php echo $config_json["TCAT_AUTO_UPDATE"]; ?>" /> (0=off, 1=trivial, 2=substantial, 3=expensive)</td>
        </tr>
    </table>
    <input type="submit">
</form>
</body>
</html>
