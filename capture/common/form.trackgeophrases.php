<?php
include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../common/constants.php';
include_once __DIR__ . '/../../common/functions.php';

if (!is_admin())
    die("Sorry, access denied. Your username does not match the ADMIN user defined in the config.php file.");
?>

<div class='if_row_header' style='height:200px;'>Bounding boxes:</div>
<div class='if_row_content'>
    <input id="newbin_geoboxes" name="newbin_geoboxes" type="text"/><br>
    Here you can specify <a href="https://dev.twitter.com/streaming/overview/request-parameters#locations" target="_blank">locations</a> (bounding boxes) to track Tweets from one or multiple areas in the world. 
    <br/>
    As specified by the <a href="https://dev.twitter.com/streaming/overview/request-parameters#locations" target="_blank">documentation</a>, the query has a strict format.<br/>
    <br>
    <ol style='margin-top:0px; list-style-position: inside; list'>
        <li>Each bounding box should be specified as a longitude and latitude pair, starting with the southwest corner of the bounding box. After the southwest corner comes the northeast corner. This makes four coordinates per area (sw lng, sw lat, ne lng, ne lat). Every coordinate is separated by a comma.</li>
        <li>Track multiple areas by adding another set of four coordinates. Keep adding commas to your query. No other delimiters are required.
        <li>Tweets will be stored in a particular bin if its coordinates are contained in at least one of the areas you specified for that bin.</li>
        <li>Twitter does not just return the Tweets with explicit GEO coordinates. If a user has set 'use my location' in his or her preferences, Twitter may also decide to use IP addresses or other measures to determine the location. The third option is for a user to attach a Tweet to a specific place. If such a place is within the bounding boxes which you defined, it will be stored.
    </ol>

    You can track a maximum of 25 geoboxes at the same time (for all query bins combined) and the total volume of all your queries should never exceed 1% of global Twitter volume (at any specific moment in time).
    <br/><br/>
    Example bounding box for San Francisco: -122.75,36.8,-121.75,37.8
</div>
