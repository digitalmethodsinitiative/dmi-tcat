<?php
include_once("../config.php");

if (defined("ADMIN_USER") && ADMIN_USER != "" && (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] != ADMIN_USER))
    die("Go away, you evil hacker!");

include_once("query_manager.php");
include_once("../common/functions.php");
include_once("../capture/common/functions.php");

create_admin();

$captureroles = unserialize(CAPTUREROLES);

$querybins = getBins();
$activePhrases = getNrOfActivePhrases();
$activeUsers = getNrOfActiveUsers();
$lastRateLimitHit = getLastRateLimitHit();
?>

<html>
    <head>
        <title>DMI-TCAT query manager</title>

        <style type="text/css">

            body,html { font-family:Arial, Helvetica, sans-serif; font-size:12px; }

            table { font-size:11px; }
            th { background-color: #ccc; padding:5px; }
            th.toppad { font-size:14px; text-align:left; padding-top:20px; background-color:#fff; }
            td { background-color: #eee; padding:5px; }
            .keywords { width:400px; }

            .if_row { padding:2px; position: relative; width: 1024px; clear: both;}
            .if_row_header{ width: 100px; height: 20px; float: left; padding-top: 5px;}
            .if_row_content input { width: 320px; }
            <?php
            if ((array_search('track', $captureroles) !== false))
                print "#if_row_users { display:none; }";
            ?>

            th.header { 
                background-image: url(../analysis/css/tablesorter/blue/bg.gif);     
                cursor: pointer; 
                font-weight: bold; 
                background-repeat: no-repeat; 
                background-position: center left; 
                padding-left: 20px; 
                border-right: 1px solid #dad9c7; 
                margin-left: -1px; 
            } 
            th.headerSortDown { 
                background-image: url(../analysis/css/tablesorter/blue/desc.gif); 
                background-color: #3399FF; 
            } 
            th.headerSortUp { 
                background-image: url(../analysis/css/tablesorter/blue/asc.gif); 
                background-color: #3399FF; 
            } 


        </style>

    </head>

    <body>

        <h1>DMI-TCAT query manager</h1>
        <?php
        print "You currently have " . count($querybins) . " query bins and are tracking ";
        if (array_search("track", $captureroles) !== false)
            print $activePhrases . " out of 400 possible phrases";
        if (array_search("track", $captureroles) !== false && array_search("follow", $captureroles) !== false)
            print " and ";
        if (array_search("follow", $captureroles) !== false)
            print $activeUsers . " users out of 5000 possible user ids";
        if ($lastRateLimitHit) {
            print "<br><font color='red'>Your latest rate limit hit was on $lastRateLimitHit</font><bR>";
        }
        ?>
        <h3>New query bin</h3>
        <table>
            <tr>
                <td>
                    <form onsubmit="sendNewForm(); return false;" method="post">
                        <div class="if_row">
                            <div class='if_row_header'>Bin type:</div>
                            <div class='if_row_content'>
                                <select name="capture_type" id="capture_type" onchange="changeInterface();">
                                    <?php if (array_search('track', $captureroles) !== false) { ?>
                                        <option value="track">keyword track</option>
                                    <?php } if (array_search('follow', $captureroles) !== false) { ?>
                                        <option value="follow">user sample</option>
                                    <?php } if (array_search('onepercent', $captureroles) !== false) { ?>
                                        <option value="onepercent">one percent sample</option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="if_row">
                            <div class='if_row_header'>Bin name:</div>
                            <div class='if_row_content'>
                                <input id="newbin_name" name="newbin_name" type="text"/> (cannot be changed later on)
                            </div>
                        </div>
                        <?php if (array_search('track', $captureroles) !== false) { ?>
                            <div id="if_row_phrases" class="if_row">
                                <div class='if_row_header' style='height:200px;'>Phrases to track:</div>
                                <div class='if_row_content'>
                                    <input id="newbin_phrases" name="newbin_phrases" type="text"/><br>
                                    Here you can specify a list of <a href='https://dev.twitter.com/docs/streaming-apis/parameters#track' target='_blank'>tracking criteria</a> consisting of single or multiple keyword queries, hashtags, and specific phrases. Each query should be separated by a comma. If you want to track a literal phrase, encapsulate it in single quotes (').<br>
                                    <br/>
                                    DMI-TCAT allows for three types of 'track' queries:
                                    <ol style='margin-top:0px; list-style-position: inside; list'>
                                        <li> a single word/hashtag. Consider that Twitter does not do partial matching on words, i.e. [twitter] will get tweets with [twitter], [#twitter] but not [twitteraddiction]
                                        <li> two or more words: works like an AND operator, i.e. [global warming] will find tweets that have both [global] and [warming] in any position in the tweet, e.g. "life is global but not warming"</li>
                                        <li> exact phrases: ['global warming'] will get only tweets with the exact phrase. Beware, however that due to how the streaming API works, tweets are captured in the same way as in 2, but tweets that do not match the exact phrase are thrown away. This means that you will request many more tweets from the Twitter API than you will see in your query bin - thus increasing the possibility that you will hit a <a href='https://dev.twitter.com/docs/faq#6861' target='_blank'>rate limit</a>. E.g. if you specify a query like ['are we'] all tweets matching both [are] and [we] are retrieved, while DMI-TCAT only retains those with the exact phrase ['are we'].</li>
                                    </ol>

                                    You can track a maximum of 400 queries at the same time (for all query bins combined) and the total volume should never exceed 1% of global Twitter volume, at any specific moment in time.
                                    <br/><br/>
                                    Example bin: globalwarming,global warming,'climate change'

                                </div>
                            </div>
                        <?php } if (array_search('follow', $captureroles) !== false) { ?>
                            <div id="if_row_users" class="if_row">
                                <div class='if_row_header' style='height: 100px;'>Users to track:</div>
                                <div class='if_row_content'>
                                    <input id="newbin_users" name="newbin_users" type="text"/><br/>
                                    Specify a comma-separated list of user IDs, indicating the users whose Tweets should be captured. See the <a href='https://dev.twitter.com/docs/streaming-apis/parameters#follow' target='_blank'>follow parameter documentation</a> for what tweets will be collected using this method.<br><Br>Note that you can only follow a maximum of 5000 user ids at the same time (for all query bins combined). 
                                    <br/><br>
                                    Example bin: 1304933132,1286333395,856010760,381660841,381453862,224572743
                                </div>
                            </div>
                        <?php } ?>
                        <div class="if_row">
                            <input value="add query bin" type="submit" />
                        </div>
                    </form>
                </td>
            </tr>
        </table>

        <?php
        echo "<h3>Query manager</h3>";
        echo '<table id="thetable">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>querybin</th>';
        echo '<th>active</th>';
        echo '<th>type</th>';
        echo '<th class="keywords">queries</th>';
        echo '<th>no. tweets</th>';
        echo '<th>Periods in which the query bin was active</th>';
        echo '<th></th>';
        echo '<th></th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        foreach ($querybins as $bin) {
            $phraseList = array();
            $phrasePeriodsList = array();
            $activePhraselist = array();

            if ($bin->type == "track") {
                foreach ($bin->phrases as $phrase) {
                    $phrasePeriodsList[$phrase->id] = array_unique($phrase->periods);
                    $phraseList[$phrase->id] = $phrase->phrase;
                    if ($phrase->active) {
                        $activePhraselist[$phrase->id] = $phrase->phrase;
                    }
                }
            } elseif ($bin->type == "follow") {
                foreach ($bin->users as $user) {
                    $phrasePeriodsList[$user->id] = array_unique($user->periods);
                    $phraseList[$user->id] = $user->id;
                    if ($user->active) {
                        $activePhraselist[$user->id] = $user->id;
                    }
                }
            }
            $bin->periods = array_unique($bin->periods);
            sort($bin->periods);
            asort($phraseList);
            
            $action = ($bin->active == 0) ? "start" : "stop";

            echo '<tr>';
            echo '<td valign="top">' . $bin->name . '</td>';
            echo '<td valign="top">' . $bin->active . '</td>';
            echo '<td valign="top">' . $bin->type . '</td>';
            echo '<td class="keywords" valign="top">';
            if ($bin->type != "onepercent") {
                echo '<table width="100%">';
                foreach ($phraseList as $phrase_id => $phrase) {
                    echo "<tr valign='top'><td width='30%'>$phrase</td><td>" . implode("<br>", $phrasePeriodsList[$phrase_id]) . "</td></tr>";
                }
                echo '</table>';
            }
            echo '</td>';
            echo '<td valign="top" align="right">' . number_format($bin->nrOfTweets, 0, ",", ".") . '</td>'; // does not sort well
            echo '<td valign="top">' . implode("<br />", $bin->periods) . '</td>';
            echo '<td valign="top">';
            if ($bin->type != "onepercent")
                echo '<a href="" onclick="sendModify(\'' . $bin->id . '\',\'' . addslashes(implode(",", $activePhraselist)) . '\',\'' . $bin->active . '\',\'' . $bin->type . '\'); return false;">modify phrases</a>';
            echo '</td>';
            echo '<td valign="top"><a href="" onclick="sendPause(\'' . $bin->id . '\',\'' . $action . '\',\'' . $bin->type . '\'); return false;">' . $action . '</a></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table><br /><br />';
        ?>

    </table>

    <script type='text/javascript' src='../analysis/scripts/jquery-1.7.1.min.js'></script>
    <script type="text/javascript" src="../analysis/scripts/tablesorter/jquery.tablesorter.min.js"></script>
    <script type="text/javascript">

        $(document).ready(function() {
            
            // add parser for the period column through the tablesorter addParser method 
            $.tablesorter.addParser({  
                id: 'datelist', 
                is: function(s) { 
                    return false; 
                }, 
                format: function(s) { 
                    return s.replace(/(\d{4}-\d{2}-\{d2}).*/m,"$1"); 
                }, 
                type: 'text' 
            }); 
            // add parser for the nr of tweets column through the tablesorter addParser method 
            $.tablesorter.addParser({  
                id: 'nroftweets', 
                is: function(s) { 
                    return false; 
                }, 
                format: function(s) { 
                    return s.replace(/\./g,""); 
                }, 
                type: 'numeric' 
            }); 
            $("#thetable").tablesorter({headers: { 3: { sorter: false}, 4:{sorter:'nroftweets'}, 5:{sorter:'datelist'}, 6: {sorter: false}, 7: {sorter:false} } });
            
            changeInterface();
        });
            
        var bins = new Array();
<?php
$bins = getBinIds();
foreach ($bins as $id => $bin)
    print "bins[$id] = '$bin';\n";
?>
    
    var nrOfActivePhrases = <?php echo $activePhrases; ?>;
    var nrOfActiveUsers = <?php echo $activeUsers; ?>;

    function sendPause(_bin,_todo,_type) {
        if(!validateBin(_bin))
            return false;
                
        if(_todo == "start") {
            if(_type == "track")
                var _check = window.confirm("Are you sure that you want to " + _todo + " capturing the '" + bins[_bin] + "' bin with the last set of active phrases?");
            else if(_type == "follow")
                var _check = window.confirm("Are you sure that you want to " + _todo + " capturing the '" + bins[_bin] + "' bin with the last set of active users?");
            else if(_type == "onepercent")
                var _check = window.confirm("Are you sure that you want to " + _todo + " capturing the '" + bins[_bin] + "' bin?");
        } else
            var _check = window.confirm("Are you sure that you want to " + _todo + " capturing the '" + bins[_bin] + "' bin?");

        if(_check == true) {

            var _params = {action:"pausebin",todo:_todo,bin:_bin,type:_type};
                    
            $.ajax({
                dataType: "json",
                url: "query_manager.php",
                type: 'POST',
                data: _params
            }).done(function(_data) {
                alert(_data["msg"]);
                location.reload();
            });
        }
        return false;
    }

    function sendModify(_bin,_phrases,_active,_type) {
        if(!validateBin(_bin))
            return false;
                
        var _newphrases = window.prompt("Specify active keywords:",_phrases);
        if(_newphrases == null || _newphrases == _phrases) { return; }
        
        if(!validateQuery(_newphrases,_type))
            return false;
        
        var _nrOfPhrases = validateNumberOfPhrases(_phrases.split(",").length,_newphrases.split(",").length);
        if(!_nrOfPhrases) {
            alert("With this query you will exceed the number of allowed queries (400) to the Twitter API. Please reduce the number of phrases.");
            return false;
        }

        if(_active == 1)
            var _check = window.confirm("Are you sure that you want to change from\n\n" + _phrases + "\n\nto\n\n" + _newphrases + "\n\nfor this query bin?");
        else
            var _check = window.confirm("Are you sure that you want to change from\n\n" + _phrases + "\n\nto\n\n" + _newphrases + "\n\nfor this query bin, and then start the bin?");
        
        if(_check == true) {

            var _params = {action:"modifybin",bin:_bin,type:_type,oldphrases:_phrases,newphrases:_newphrases,active:_active};

            $.ajax({
                dataType: "json",
                url: "query_manager.php",
                type: 'POST',
                data: _params
            }).done(function(_data) {
                alert(_data["msg"]);
                location.reload();
            });   
        }
        return false;
    }

    function sendNewForm() {
        var _type = $("#capture_type").val();
        if(!validateType(_type))
            return false;
        var _bin = $("#newbin_name").val();
        if(!validateBin(_bin))
            return false;
        if(_type == "track") {
            var _phrases = $("#newbin_phrases").val();
            if(!validateQuery(_phrases,_type))
                return false;
            var _nrOfPhrases = validateNumberOfPhrases(0,_phrases.split(",").length);
            if(!_nrOfPhrases) {
                alert("With this query you will exceed the number of allowed queries (400) to the Twitter API. Please reduce the number of phrases.");
                return false;
            }
        } else if(_type == "follow") {
            var _users = $("#newbin_users").val();
            if(!validateQuery(_users,_type))
                return false;
            var _nrOfUsers = validateNumberOfUsers(0,_users.split(",").length);
            if(!_nrOfUsers) {
                alert("With this query you will exceed the number of allowed user ids (5000) to the Twitter API. Please reduce the number of user ids.");
                return false;
            }
        }
            
        var _check = window.confirm("You are about to create a new query bin. Are you sure?");
        if(_check == true) {
            if(_type == "track")    
                var _params = {action:"newbin",type:_type,newbin_phrases:_phrases,newbin_name:_bin,active:$("#make_active").val()};
            if(_type == "follow")    
                var _params = {action:"newbin",type:_type,newbin_users:_users,newbin_name:_bin,active:$("#make_active").val()};
            if(_type == "onepercent")    
                var _params = {action:"newbin",type:_type,newbin_name:_bin,active:$("#make_active").val()};

            $.ajax({
                dataType: "json",
                url: "query_manager.php",
                type: 'POST',
                data: _params
            }).done(function(_data) {
                alert(_data["msg"]);
                location.reload();
            });
        }
        return false;
    }
    
    // currently there is no check for duplicated phrases
    function validateNumberOfPhrases(oldphrases,newphrases) {
        if(nrOfActivePhrases - (oldphrases-newphrases) >= 400)
            return false;
        return true;
    }
    // currently there is no check for duplicated phrases
    function validateNumberOfUsers(oldusers,newusers) {
        if(nrOfActiveUsers - (oldusers-newusers) >= 5000)
            return false;
        return true;
    }
            
    function validateType(type) {
        if(type=="track")
            return true;
        if(type=="follow")
            return true;
        if(type=="onepercent")
            return true;
        alert(type + " type not recognized");
        return false;
    }
            
    function validateBin(binname) {
        if(binname == null || binname.trim()=="") {
            alert("You cannot use an empty bin name");
            return false;
        }
        var reg = /^[a-zA-Z0-9-_]+$/;
        if(!reg.test(binname.trim())) {
            alert("bin names can only consist of alpha-numeric characters, dashes and underscores")
            return false;
        }
        return true;
    }
            
    function validateQuery(query,type) {
        if(type == "onepercent")
            return true;
        if(query == null || query.trim()=="") {
            if(type == "track")
                alert("You should specify at least one query");
            else if(type == "follow")
                alert("You should specify at least one user id");
            return false;
        }
        if(type == 'track') {
            // if literal phrase, there should be no comma's in between
            if(query.indexOf("'")==-1) {
                return true;
            } else {
                var arr = query.split(",");
                var reg = /^'[^']+'$/;
                var cont = true;
                $.each(arr,function(i,subq) {
                    subq = subq.trim();
                    if(subq.indexOf("'")!==-1) {
                        if(!reg.test(subq.trim())) {
                            alert("Single quotes can only be used to specify a literal query without comma's. Please change your query.");
                            cont = false; 
                            return false;
                        }
                    }
                });
                return cont;
            }
        } else if(type == 'follow') {
            // only integers
            var arr = query.split(",");
            var reg = /^[0-9]+$/;
            var cont = true;
            $.each(arr,function(i,subq) {
                if(!reg.test(subq.trim())) {
                    alert("You can only provide user ids separated by a comma.");
                    cont = false;
                }
            });
            return cont;
        } else if(type == "onepercent") {
            return true;
        }
        alert('an unknown error occured');
        return false;
    }


    function changeInterface() {

        switch($('#capture_type').val()) {
            case "track":
                $("#if_row_users").hide();
                $("#if_row_phrases").show();
                break;
            case "follow":
                $("#if_row_users").show();
                $("#if_row_phrases").hide();
                break;
            case "onepercent":
                $("#if_row_users").hide();
                $("#if_row_phrases").hide();
                break;
        }
    }

    </script>
</body>
</html>
