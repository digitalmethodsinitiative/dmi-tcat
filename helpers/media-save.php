<?php
/**
 * Store the media files from the _media table on disk.
 * 
 * This script requires the server to support retrieval of remote content using the PHP cURL extension.
 * You may need to install php7.0-curl package.
 *
 * On the command line, use a command like below:
 *
 * $ php media-save.php bin_name
 *
 * Optionally, pass the image save path as the second argument:
 *
 * $ php media-save.php bin_name /my/tmp/dir
 *
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../capture/common/functions.php';

$base_imgpath = '/tmp';

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit();
}

function saveMedia($bin_name, $base_imgpath)
{
    $folder_path = $base_imgpath . '/' . $bin_name . '/';
    try {
        $dbh = pdo_connect();
        $sql = 'SELECT COUNT(DISTINCT `id`) FROM `' . $bin_name . '_media` 
            WHERE `status_code` IS NULL';
        $rec = $dbh->prepare($sql);
        $rec->execute();
        $row = $rec->fetch(PDO::FETCH_NUM);
        echo 'This bin: ' . $bin_name . ' has ' . $row[0] . ' media files.' . PHP_EOL;
        
        $sql = 'SELECT DISTINCT `id`, `media_url_https` FROM `' . $bin_name . '_media` 
            WHERE `status_code` IS NULL';
        $rec2 = $dbh->prepare($sql);
        $rec2->execute();
        $i = 1;
        while ($row = $rec2->fetch(PDO::FETCH_ASSOC)) {
            echo $i . '. ';
            $statusCode =saveMedia2File($folder_path, $row['media_url_https'], $row['id']);
            $sql_update = 'UPDATE `' . $bin_name . '_media` 
                SET `status_code` = :statuscode WHERE `id` = :mid;';
            $stmt = $dbh->prepare($sql_update);
            $stmt->bindParam(':statuscode', $statusCode);
            $stmt->bindParam(':mid', $row['id']);
            $stmt->execute();
            $i++;
        }
        unset($dbh);
    } catch (PDOException $e) {
        $errorMessage = $e->getCode() . ': ' . $e->getMessage();
        return $errorMessage;
    }
}

function saveMedia2File($folder_path, $file_uri, $media_id)
{
    $file_extension = pathinfo($file_uri, PATHINFO_EXTENSION);
    $file_name = $media_id . '.' . $file_extension;
    // Skip when file exist
    if (checkFileExist($folder_path, $file_name)) {
        echo 'The file: ' . $file_name . ' already exist!' . PHP_EOL;
        return '200';
    } 

    //Open file handler.
    $saveTo = $folder_path . $file_name;
    $fp = fopen($saveTo, 'w+');

    $ch = curl_init();
    curl_setopt($ch , CURLOPT_URL , $file_uri);
    curl_setopt($ch, CURLOPT_USERAGENT, "Google Bot");
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_exec($ch);
    //Get the HTTP status code.
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //Close the cURL handler.
    curl_close($ch);
    fclose($fp);

    if($statusCode == 200){
        echo "Saved file " . $file_name . PHP_EOL;
    } else {
        unlink($saveTo);
        echo 'Problem with ' . $statusCode .' -- id: '. $media_id . PHP_EOL;
    }

    return $statusCode;
}

function checkFileExist($folder_path, $file_name)
{
    return file_exists($folder_path . $file_name);
}

// If folder not exist, create it
function createFolder($bin_name, $base_imgpath)
{
    $folder_path = $base_imgpath . '/' . $bin_name . '/';
    if (file_exists($folder_path)) {
        echo 'The folder name: ' . $bin_name . ' already exist!' . PHP_EOL;
    } else {
        if (!mkdir($folder_path, 0777)) {
            die('Failed to create folder...' . PHP_EOL);
        }else{
            echo 'Create folder with bin_name: ' . $bin_name  . PHP_EOL;
        }
    }
}

// If the _media table status column not exist, create column in Database
function checkColumn($bin_name)
{
    global $database;
    try {
        $dbh = pdo_connect();
        $sql = 'SELECT * FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = \'' . $database . '\' 
            AND TABLE_NAME = \''. $bin_name . '_media\' 
            AND COLUMN_NAME = \'status_code\';';
        $rec = $dbh->prepare($sql);
        $rec->execute();
        $row = $rec->fetch(PDO::FETCH_NUM);
        if(!$row){
            $sql = 'ALTER TABLE `' . $bin_name  . '_media` ADD `status_code` VARCHAR(10) NULL DEFAULT NULL;';
            $rec = $dbh->prepare($sql);
            $rec->execute();
            echo 'Check Status column: Add status code column.' . PHP_EOL;
        } else {
            echo 'Check Status column: Status code column already exist!' . PHP_EOL;
        }
    } catch (PDOException $e) {
        $errorMessage = $e->getCode() . ': ' . $e->getMessage();
        return $errorMessage;
    } finally {
        $rec = null;
        $dbh = null;
    }
}

// Get bin name from command line argument
if (isset($argv[1])) {
    $bin_name = $argv[1];
} else {
    die('The bin name is a required argument.' . PHP_EOL);
}
// Get save path from command line argument
if (isset($argv[2])) {
    $base_imgpath = $argv[2];
}

createFolder($bin_name, $base_imgpath);
checkColumn($bin_name);
saveMedia($bin_name, $base_imgpath);
