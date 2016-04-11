<?php
/**
 * Store the media files from the _media table on disk.
 * 
 * This script requires the server to support retrieval of remote content using the file_get_contents() functions.
 * You may need to adjust your php.ini file to activate the function.
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
        $sql = 'SELECT COUNT(DISTINCT `id`) FROM `' . $bin_name . '_media`';
        $rec = $dbh->prepare($sql);
        $rec->execute();
        $row = $rec->fetch(PDO::FETCH_NUM);
        echo 'This bin: ' . $bin_name . ' has ' . $row[0] . ' media files.' . PHP_EOL;
        
        $sql = 'SELECT DISTINCT `id`, `media_url_https` FROM `' . $bin_name . '_media`';
        $rec2 = $dbh->prepare($sql);
        $rec2->execute();
        $i = 1;
        while ($row = $rec2->fetch(PDO::FETCH_ASSOC)) {
            echo $i . '. ';
            saveMedia2File($folder_path, $row['media_url_https'], $row['id']);
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
        echo 'The file: ' . $file_name . ' already exists!' . PHP_EOL;
    } else {
        $image = file_get_contents($file_uri);
        file_put_contents($folder_path . $file_name, $image);
        echo "Saved file " . $file_name . PHP_EOL;
    }
    unset($image);
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
saveMedia($bin_name, $base_imgpath);
