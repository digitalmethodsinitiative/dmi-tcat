<?php

include_once("../../config.php");

if (defined("ADMIN_USER") && ADMIN_USER != "" && (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] != ADMIN_USER))
    die("Go away, you evil hacker!");

?>

                                <div class='if_row_header' style='height:200px;'>Bounding boxes:</div>
                                <div class='if_row_content'>
                                    <input id="newbin_geoboxes" name="newbin_geoboxes" type="text"/><br>
                                    Here you can specify <a href="https://dev.twitter.com/streaming/overview/request-parameters#locations" target="_blank">locations</a> to track Tweets from one or multiple areas in the world. 
                                  <br/>
                                    As specified by the <a href="https://dev.twitter.com/streaming/overview/request-parameters#locations" target="_blank">documentation</a> the query must have a strict format. Concatenate bounding boxes to define your search area.<br/>
                                    <br>
                                    <ol style='margin-top:0px; list-style-position: inside; list'>
                                        <li>Each bounding box should be specified as a pair of longitude and latitude pairs, starting with the southwest corner of the bounding box. After the southwest corner comes the northeast corner. This makes four coordinates per area. Every coordinate is separated by a comma.</li>
                                        <li>Track multiple areas by adding another set of four coordinates. Keep adding commas to your query. No other delimiters are required.
                                        <li>Tweets will be captured in the bin if they are found in at least one of the areas.</li>
                                        <li>Twitter does not just return the Tweets with explicit GEO coordinates. If a user has set 'use my location' in his or her preferences, Twitter may also decide to use IP addresses or other measures to determine the location. The third option is for a user to attached his own Tweet to a specific place. If such a place is within our search area, it will be captured.
                                    </ol>

                                    You can track a specific (but undocumented?) ammount of locations irrespective of the number of text queries, but the total volume of all queries should never exceed 1% of global Twitter volume, at any specific moment in time.
                                    <br/><br/>
                                    Example bounding box for San Francisco: -122.75,36.8,-121.75,37.8

                                </div>

