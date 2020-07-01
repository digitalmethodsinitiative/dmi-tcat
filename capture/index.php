<?php
include_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../common/constants.php';
include_once __DIR__ . '/../common/functions.php';

if (!is_admin())
    die("Sorry, access denied. Your username does not match the ADMIN user defined in the config.php file.");

include_once __DIR__ . '/query_manager.php';
include_once __DIR__ . '/../common/functions.php';
include_once __DIR__ . '/../common/upgrade.php';
include_once __DIR__ . '/../capture/common/functions.php';

create_admin();
create_error_logs();

$captureroles = unserialize(CAPTUREROLES);

$querybins = getBins();
$activePhrases = getNrOfActivePhrases();
$activeGeoboxes = getNrOfActiveGeoboxes();
$activeUsers = getNrOfActiveUsers();
$lastRateLimitHit = getLastRateLimitHit();
?>

<html>
    <head>
        <title>DMI-TCAT query manager</title>
        <meta charset='<?php echo mb_internal_encoding(); ?>'>
        <style type="text/css">

            body,html { font-family:Arial, Helvetica, sans-serif; font-size:12px; }

            #updatewarning { margin-top:5px;margin-bottom:5px;padding:8px;width:1024px;border:red 1px solid; }

            table { font-size:11px; }
            th { background-color: #ccc; padding:5px; }
            th.toppad { font-size:14px; text-align:left; padding-top:20px; background-color:#fff; }
            td { background-color: #eee; padding:5px; }
            .keywords { width:400px; }

            #if_fullpage { width:1000px; }

            h1 { font-size:16px; margin:20px 0px 15px 0px; }

            #if_title { float:left; }
            #if_links { float:right; padding-top:22px; margin-right:-20px; }

            .if_toplinks { display: inline-block; margin-left: 1em; font-size:12px; text-decoration:none; color: #000; }
            .if_toplinks:before { content: "» "; }
            .if_toplinks:hover { text-decoration:underline; }

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
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.11.1/themes/smoothness/jquery-ui.css">

    </head>

    <body>
        <div id="if_fullpage">
        <h1 id="if_title">DMI-TCAT query manager</h1>
        <div id="if_links">
            <a href="https://github.com/digitalmethodsinitiative/dmi-tcat" target="_blank" class="if_toplinks">github</a>
            <a href="https://github.com/digitalmethodsinitiative/dmi-tcat/issues?state=open" target="_blank" class="if_toplinks">issues</a>
            <a href="https://github.com/digitalmethodsinitiative/dmi-tcat/wiki" target="_blank" class="if_toplinks">FAQ</a>
            <a href="../analysis/index.php" class="if_toplinks">analysis</a>
        </div>
        <div style="clear:both;"></div>
        </div>

        <?php
        if (!dbserver_has_utf8mb4_support()) {
            print "<br /><font color='red'>Your MySQL version is too old, please upgrade to at least MySQL 5.5.3 to use DMI-TCAT.</font><br>";
        }
        print "You currently have " . count($querybins) . " query bins and are tracking ";
        $trackWhat = array();
        if (array_search("track", $captureroles) !== false && $activePhrases)
            $trackWhat[] = $activePhrases . " out of 400 possible phrases";
        if (array_search("track", $captureroles) !== false && $activeGeoboxes)
            $trackWhat[] = $activeGeoboxes . " out of 25 possible geolocations";
        if (array_search("follow", $captureroles) !== false)
            $trackWhat[] = $activeUsers . " out of 5000 possible user ids";
        if (array_search("onepercent", $captureroles) !== false)
            $trackWhat[] = "a one percent sample";
        if (empty($trackWhat)) {
            $trackWhat[] = 'nothing';
        }
        $and = false;
        foreach ($trackWhat as $what) {
            if ($and) {
                print ", and ";
            } else {
                $and = true;
            }
            print $what;
        }
        print ".<br/>";
        if ($lastRateLimitHit) {
            print "<br /><font color='red'>Your latest rate limit hit was on $lastRateLimitHit</font><br>";
        }
        $git = getGitLocal();
        $showupdatemsg = false;
        if (defined('AUTOUPDATE_ENABLED') && AUTOUPDATE_ENABLED == true && import_mysql_timezone_data() == false) {
            if (!$showupdatemsg) {
                print '<div id="updatewarning">';
                print "You have configured TCAT to automatically upgrade in the background. However, a specific upgrade instruction requires MySQL root privileges and cannot be run by TCAT itself. You will need to install the MySQL Time Zone Support manually using instructions provided here:<br/><br/><a href=\"https://dev.mysql.com/doc/refman/5.5/en/time-zone-support.html\" target=_blank>https://dev.mysql.com/doc/refman/5.5/en/time-zone-support.html</a><br/><br/>For <i>Debian</i> or <i>Ubuntu Linux</i> systems, the following command, issued as root (use sudo su to become root), will install the neccessary time zone data.<br/></br>/usr/bin/mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql --defaults-file=/etc/mysql/debian.cnf --force -u debian-sys-maint mysql</br>";
            }
            $showupdatemsg = true;
        }
        if (is_array($git)) {
            $remote = getGitRemote($git['commit'], $git['branch']);
            if (is_array($remote)) {
                $date_unix = strtotime($remote['date']);
                if ($git['commit'] !== $remote['commit'] && $date_unix < time() - 3600 * 24) {
                    $commit = '#' . substr($remote['commit'], 0, 7) . '...';
                    $mesg = $remote['mesg'];
                    $url = $remote['url'];
                    $required = $remote['required'];
                    $autoupgrade = 'autoupgrade()';
                    if (!$showupdatemsg) {
                        print '<div id="updatewarning">';
                    }
                    $wikilink = 'https://github.com/digitalmethodsinitiative/dmi-tcat/wiki/Upgrading-TCAT';
                    if ($required) {
                        print "A newer version of TCAT is available, containing important updates. You are strongly recommended to upgrade. Please read the <a href='$wikilink' target='_blank'>documentation</a> for instructions on upgrading, or click <a href='#' onclick='$autoupgrade'>here</a> to schedule an automatic upgrade. [ commit <a href='$url' target='_blank'>$commit</a> - $mesg ]<br>";
                    } else {
                        print "A newer version of TCAT is available. You can get the latest code via git pull. Please read the <a href='$wikilink' target='_blank'>documentation</a> for instructions on upgrading, or click <a href='#' onclick='$autoupgrade'>here</a> to schedule an automatic upgrade. [ commit <a href='$url' target='_blank'>$commit</a> - $mesg ]<br>";
                    }
                    $showupdatemsg = true;
                }
            }
        }
        $tests = upgrades(true);
        if ($tests['suggested'] || $tests['required']) {
            if (!$showupdatemsg) {
                print '<div id="updatewarning">';
            } else {
                print '<br/>';
            }
            $wikilink = 'https://github.com/digitalmethodsinitiative/dmi-tcat/wiki/Upgrading-TCAT#upgrading-database-tables';
            if ($tests['required']) {
                    print "Your database is out-of-date and needs to be upgraded to fix bugs. Follow the <a href='$wikilink' target='_blank'>documentation</a> and run the command-line script common/upgrade.php from your shell.<br/>";
            } else {
                    print "Your database must be updated before some new TCAT features can be used. Follow the <a href='$wikilink' target='_blank'>documentation</a> and run the command-line script common/upgrade.php from your shell.<br/>";
            }
            $showupdatemsg = true;
        }
        if ($showupdatemsg) {
            print "</div>";
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
                                        <option value="geotrack">geo track</option>
<?php } if (array_search('follow', $captureroles) !== false) { ?>
                                        <option value="follow">user sample</option>
<?php } if (array_search('onepercent', $captureroles) !== false) { ?>
                                        <option value="onepercent">one percent sample</option>
                        <?php } ?>
                                </select>
                                <span>(cannot be changed later on)</span>
                            </div>
                        </div>
                        <div class="if_row">
                            <div class='if_row_header'>Bin name:</div>
                            <div class='if_row_content'>
                                <input id="newbin_name" name="newbin_name" type="text" maxlength="45"/>
                            </div>
                        </div>
<?php if (array_search('track', $captureroles) !== false) { ?>
                            <div id="if_row_phrases" class="if_row">
                                <script>
                                    window.onload=function() {
                                        $.ajax({
                                            url: 'public/form.trackphrases.php'
                                        }).done(function (content) {
                                            $("#if_row_phrases").html(content);
                                        });
                                    }
                                </script>
                            </div>
                        <?php } if (array_search('follow', $captureroles) !== false) { ?>
                            <div id="if_row_users" class="if_row">
                                <div class='if_row_header' style='height: 100px;'>Users to track:</div>
                                <div class='if_row_content'>
                                    <input id="newbin_users" name="newbin_users" type="text"/><br/>
                                    Specify a comma-separated list of user IDs, indicating the users whose Tweets should be captured. See the <a href='https://developer.twitter.com/en/docs/tweets/filter-realtime/guides/basic-stream-parameters#track' target='_blank'>follow parameter documentation</a> for what tweets will be collected using this method.<br><Br>Note that you can only follow a maximum of 5000 user ids at the same time (for all query bins combined). 
                                    <br/><br>
                                    Example bin: 1304933132,1286333395,856010760,381660841,381453862,224572743
                                </div>
                            </div>
        <?php } ?>
                        <br/><div class="if_row">
                            <div class='if_row_header'>Optional notes:</div>
                            <div class='if_row_content'>
                                <textarea name="newbin_comments" style="height:4em; width=80em;" cols=80 rows=4 maxlength=2000></textarea><br/>
                            </div>
                        </div>

                        <br/>

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
        echo '<th>comments</th>';
        echo '<th>no. tweets</th>';
        echo '<th>Periods in which the query bin was active</th>';
        echo '<th></th>';
        echo '<th></th>';
        echo '<th></th>';
        echo '<th></th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        foreach ($querybins as $bin) {
            $phraseList = array();
            $phrasePeriodsList = array();
            $activePhraselist = array();

            if (strstr($bin->type, "track") !== false || strstr($bin->type, "geotrack") !== false || $bin->type == 'search' || $bin->type == 'import ytk') {
                foreach ($bin->phrases as $phrase) {
                    $phrasePeriodsList[$phrase->id] = array_unique($phrase->periods);
                    $phraseList[$phrase->id] = str_replace("\"", "'", $phrase->phrase);
                    if ($phrase->active) {
                        $activePhraselist[$phrase->id] = str_replace("\"", "'", $phrase->phrase);
                    }
                }
            } elseif (strstr($bin->type, "follow") !== false || strstr($bin->type, "timeline") !== false) {
                foreach ($bin->users as $user) {
                    $phrasePeriodsList[$user->id] = array_unique($user->periods);
                    $phraseList[$user->id] = empty($user->user_name) ? $user->id : $user->user_name;
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
            echo '<td valign="top">' . $bin->comments . ' (<a href="" onclick="sendEditComments(\'' . $bin->id . '\', \'' . addslashes($bin->comments) . '\'); return false;">modify</a>)</td>';
            echo '<td valign="top" align="right">' . number_format($bin->nrOfTweets, 0, ",", ".") . '</td>'; // does not sort well
            echo '<td valign="top">' . implode("<br />", $bin->periods) . '</td>';
            echo '<td valign="top">';
            if ($bin->type != "onepercent" &&
                    array_search($bin->type, $captureroles) !== false ||
                    array_search('track', $captureroles) !== false && $bin->type == "geotrack") {
                echo '<a href="" onclick="sendModify(\'' . $bin->id . '\',\'' . addslashes(implode(",", $activePhraselist)) . '\',\'' . $bin->active . '\',\'' . $bin->type . '\'); return false;">modify ';
                if ($bin->type == 'follow') {
                    echo 'users';
                } elseif ($bin->type == 'track') {
                    echo 'phrases';
                } elseif ($bin->type == 'geotrack') {
                    echo 'geoboxes';
                }
                echo '</a>';
            }
            echo '</td>';
            echo '<td valign="top">';
            if (array_search($bin->type, $captureroles) !== false ||
                    array_search('track', $captureroles) !== false && $bin->type == "geotrack") {
                if ($bin->type == "geotrack") {
                    echo '<a href="" onclick="sendPause(\'' . $bin->id . '\',\'' . $action . '\',\'' . 'track\',1' . '); return false;">' . $action . '</a>';
                } else {
                    echo '<a href="" onclick="sendPause(\'' . $bin->id . '\',\'' . $action . '\',\'' . $bin->type . '\'); return false;">' . $action . '</a>';
                }
            }
            echo '</td>';
            echo '<td valign="top"><a href="" onclick="sendDelete(\'' . $bin->id . '\',\'' . $bin->active . '\',\'' . $bin->type . '\'); return false;">delete</a></td>';
            echo '<td valign="top"><a href="" onclick="sendRename(\'' . $bin->id . '\',\'' . $bin->active . '\',\'' . $bin->type . '\'); return false;">rename</a></td>';
            echo '</tr>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table><br /><br />';
        ?>

    </table>
    <div id="dialog-confirm" title="Hold on ...">
        <p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;"></span><span class="msg">These items will be permanently deleted and cannot be recovered. Are you sure?</span></p>
    </div>
    <div id="dialog-modify" title="Specify active keywords">
        <p>Separate queries by a comma</p>
        <p><span><textarea type='textarea' name='phrases' rows='15' cols='43'></textarea></span></p>
    </div>
    <div id="dialog-editcomments" title="Modify notes for bin">
        <p><span><textarea type='textarea' name='comments' rows='15' cols='43'></textarea></span></p>
    </div>

    <script type='text/javascript' src='../analysis/scripts/jquery-1.7.1.min.js'></script>
    <script src="https://code.jquery.com/ui/1.11.1/jquery-ui.js"></script>
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
            $("#thetable").tablesorter({sortList: [[0,0]], headers: { 3: { sorter: false},4: { sorter: false},  5:{sorter:'nroftweets'}, 6:{sorter:'datelist'}, 7: {sorter: false}, 8: {sorter:false} } });
            
            changeInterface();
        });
            
        var bins = new Array();
<?php
$bins = getBinIds();
foreach ($bins as $id => $bin)
    print "bins[$id] = '$bin';\n";
?>
    
    var nrOfActivePhrases = <?php echo $activePhrases; ?>;
    var nrOfActiveGeoboxes = <?php echo $activeGeoboxes; ?>;
    var nrOfActiveUsers = <?php echo $activeUsers; ?>;
    var params = undefined;
    
    $( "#dialog-confirm" ).dialog({
        autoOpen: false,
        resizable: true,
        height:'auto',
        modal: true,
        width:'auto',
        create: function(event,ui) {
            $(this).css("maxWidth", ($(window).width() - 40) + "px");  
        },
        buttons: {
            'Yes': function(){
                $(this).dialog('close');
                callbackDialog(true);
            },
            'No': function(){
                $(this).dialog('close');
                return false;
            }
        }
    });
    function dialogConfirm(msg) {
        $("#dialog-confirm .msg").html(msg);
        $("#dialog-confirm").dialog('open');
    }
    function callbackDialog(confirmed) {
        if(confirmed) {
            $.ajax({
                dataType: "json",
                url: "query_manager.php",
                type: 'POST',
                data: params
            }).done(function(_data) {
                alert(_data["msg"]);
                location.reload();
            });   
        }
    }
    function sendPause(_bin,_todo,_type,_geo) {
        if (typeof _geo === 'undefined') { _geo = 0; }
        if(!validateBin(_bin))
            return false;
        params = {action:"pausebin",todo:_todo,bin:_bin,type:_type};
        if(_todo == "start") {
            if(_type == "track" && _geo == 0)
                dialogConfirm("Are you sure that you want to " + _todo + " capturing the '" + bins[_bin] + "' bin with the last set of active phrases?");
            if(_type == "track" && _geo == 1)
                dialogConfirm("Are you sure that you want to " + _todo + " capturing the '" + bins[_bin] + "' bin with the last set of active geoboxes?");
            else if(_type == "follow")
                dialogConfirm("Are you sure that you want to " + _todo + " capturing the '" + bins[_bin] + "' bin with the last set of active users?");
            else if(_type == "onepercent")
                dialogConfirm("Are you sure that you want to " + _todo + " capturing the '" + bins[_bin] + "' bin?");
        } else
            dialogConfirm("Are you sure that you want to " + _todo + " capturing the '" + bins[_bin] + "' bin?");
    }

    
    function sendModify(_bin,_phrases,_active,_type) {
        if(!validateBin(_bin))
            return false;
        
        params = {action:"modifybin",bin:_bin,type:_type,oldphrases:_phrases,active:_active};
        
        $('#dialog-modify textarea').val(_phrases);
        $('#dialog-modify').dialog('open');
        
        return false;
    }
    $( "#dialog-modify" ).dialog({
        autoOpen: false,
        resizable: true,
        height:'auto',
        modal: true,
        width:'auto',
        create: function(event,ui) {
            $(this).css("maxWidth", ($(window).width()-40) + "px");  
        },
        buttons: {
            'Submit': function(){
                var _newphrases = $('#dialog-modify textarea').val();
                if(_newphrases == null || _newphrases == params['oldphrases']) { 
                    $(this).dialog('close'); 
                    return false; 
                }
        
                if(!validateQuery(_newphrases,params['type'])) {
                    return false;
                }
                if(params['type']=='track') {
                    var _nrOfPhrases = validateNumberOfPhrases(params['oldphrases'].split(",").length,_newphrases.split(",").length);
                    if(!_nrOfPhrases) {
                        alert("With this query you will exceed the number of allowed queries (400) to the Twitter API. Please reduce the number of phrases.");
                        return false;
                    }
                } else if(params['type']=='geotrack') {
                    var _nrOfUsers = validateNumberOfGeoboxes(params['oldphrases'].split(",").length,_newphrases.split(",").length);
                    if(!_nrOfUsers) {
                        alert("With this query you will exceed the number of allowed location queries (25) to the Twitter API. Please reduce the number of geoboxes.");
                        return false;
                    }
                } else if(params['type']=='follow') {
                    var _nrOfUsers = validateNumberOfUsers(params['oldphrases'].split(",").length,_newphrases.split(",").length);
                    if(!_nrOfUsers) {
                        alert("With this query you will exceed the number of allowed user ids (5000) to the Twitter API. Please reduce the number of user ids.");
                        return false;
                    }
                }
                params['newphrases'] = _newphrases;
                if(params['active'] == 1)
                    dialogConfirm("Please confirm that you want to change these queries:<br><br>" + params['oldphrases'] + "<br><br>into these queries:<br><br>" + _newphrases );
                else
                    dialogConfirm("Please confirm that you want to start the existing bin with the new following new queries:<br><br>" + _newphrases);
                $(this).dialog('close');
                return false;
            },
            'Cancel': function(){
                $(this).dialog('close');
                return false;
            }
        }
    });

    function sendEditComments(_bin, _comments) {

        if(!validateBin(_bin))
            return false;

        params = {action:"modifybin",bin:_bin,comments:_comments};

        _comments = _comments.replace(/\\'/g, "'");
        
        $('#dialog-editcomments textarea').val(_comments);
        $('#dialog-editcomments').dialog('open');
        
        return false;
    }
    $( "#dialog-editcomments" ).dialog({
        autoOpen: false,
        resizable: true,
        height:'auto',
        modal: true,
        width:'auto',
        create: function(event,ui) {
            $(this).css("maxWidth", ($(window).width()-40) + "px");  
        },
        buttons: {
            'Submit': function(){
                var _newcomments = $('#dialog-editcomments textarea').val();
                if (!_newcomments) { _newcomments = ' '; }
                if(_newcomments.length > 2000) { 
                    alert("Your comments exceed 2000 characters.");
                    $(this).dialog('close'); 
                    return false; 
                }
                $(this).dialog('close');
                params['comments'] = _newcomments;
                dialogConfirm("Please confirm that you want to change these notes to:<br><br>" + _newcomments);
                return false;
            },
            'Cancel': function(){
                $(this).dialog('close');
                return false;
            }
        }
    });

    
    function sendDelete(_bin,_active,_type) {
        
        var _check = window.confirm("Are you sure that you want to REMOVE this bin?");
        
        if(_check == true) {
            
            if(_active == 1)
                var _check = window.confirm("The query bin is STILL RUNNING! Are you absolutely sure that you want to completely remove it?");
            if(_check == false)
                return false;
            
            var _check = window.confirm("Last time: are you really sure that you want to REMOVE the query bin?");
            if(_check == false)
                return false;

            var _params = {action:"removebin",bin:_bin,type:_type,active:_active};

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

    function sendRename(_bin,_active,_type) {
        
        var _check = window.confirm("Are you sure that you want to rename this bin?");
        
        if(_check == true) {
            
            if(_active == 1) {
                alert("The query bin is still running! You will need to stop it first.");
                return false;
            }

            var _newname = window.prompt("Please enter the new name for your query bin.");

            var _params = {action:"renamebin",bin:_bin,type:_type,active:_active,newname:_newname};

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
        _bin = _bin.replace(/ /g,"_");
        if(!validateBin(_bin))
            return false;
        var _comments = $("textarea[name=newbin_comments]").val();
        if (!validateComments(_comments))
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
        } else if (_type == "geotrack") {
            var _phrases = $("#newbin_geoboxes").val();
            if(!validateQuery(_phrases,_type))
                return false;
            var _nrOfPhrases = validateNumberOfGeoboxes(0,_phrases.split(",").length);
            if(!_nrOfPhrases) {
                alert("With this query you will exceed the number of allowed location queries (25) to the Twitter API. Please reduce the number of geoboxes.");
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
                var _params = {action:"newbin",type:_type,newbin_phrases:_phrases,newbin_name:_bin,newbin_comments:_comments,active:$("#make_active").val()};
            if(_type == "geotrack")    
                var _params = {action:"newbin",type:_type,newbin_phrases:_phrases,newbin_name:_bin,newbin_comments:_comments,active:$("#make_active").val()};
            if(_type == "follow")    
                var _params = {action:"newbin",type:_type,newbin_users:_users,newbin_name:_bin,newbin_comments:_comments,active:$("#make_active").val()};
            if(_type == "onepercent")    
                var _params = {action:"newbin",type:_type,newbin_name:_bin,newbin_comments:_comments,active:$("#make_active").val()};


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
        if(nrOfActivePhrases - (oldphrases-newphrases) > 400)
            return false;
        return true;
    }
    // currently there is no check for duplicated phrases
    function validateNumberOfGeoboxes(oldphrases,newphrases) {
        if(nrOfActiveGeoboxes - (oldphrases/4-newphrases/4) > 25)
            return false;
        return true;
    }
    // currently there is no check for duplicated phrases
    function validateNumberOfUsers(oldusers,newusers) {
        if(nrOfActiveUsers - (oldusers-newusers) > 5000)
            return false;
        return true;
    }
            
    function validateType(type) {
        if(type=="track")
            return true;
        if(type=="geotrack")
            return true;
        if(type=="follow")
            return true;
        if(type=="onepercent")
            return true;
        alert(type + " type not recognized");
        return false;
    }

    function validateComments(comments) {
        if (comments.length > 2000) {
            alert("Comments are too long (more than 2000 characters)");
            return false;
        }
        return true;
    }

            
    function validateBin(binname) {
        if(binname == null || binname.trim()=="") {
            alert("You cannot use an empty bin name");
            return false;
        }
        var reg = /^[a-zA-Z0-9_]+$/;
        if(!reg.test(binname.trim())) {
            alert("bin names can only consist of alpha-numeric characters and underscores")
            return false;
        }
        if(binname.length > 45) {
            alert("Bin names must be shorter than 45 characters in length (you entered " + binname.length +")");
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
        if(query.indexOf("\t") != -1 || query.indexOf("\n") != -1) {
            alert("Please do not use tabs or spaces in your query definition!");
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
        } else if (type == 'geotrack') {
            query = query.replace(/\s/g, "");
            var re = new RegExp("[^-,\.0-9]");
            var results = re.exec(query);
            if (results && results.length > 0) {
                alert("You must only use coordinates and comma's in your geo string.");
                return false;
            }
            var boxes = query.split(',');
            if (boxes.length == 0 || boxes.length % 4 !== 0) {
                alert("Specify geoboxes in sets of four coordinates.");
                return false;
            }
            for (var i = 0; i < boxes.length; i++) {
                var count = boxes[i].split("\.",-1).length-1;
                if (count > 1) {
                    alert("Only one period in a coordinate (" + boxes[i] + ") please.");
                    return false;
                }
                var count = boxes[i].split("-",-1).length-1;
                if (count > 1 || count == 1 && boxes[i].charAt(0) !== '-') {
                    alert("Illegal coordinate (" + boxes[i] + ")");
                    return false;
                } 
            }
            return true;
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
                $.ajax({
                    url: 'public/form.trackphrases.php'
                }).done(function (content) {
                    $("#if_row_phrases").html(content);
                });
                break;
            case "geotrack":
                $("#if_row_users").hide();
                $("#if_row_phrases").show();
                $.ajax({
                    url: 'public/form.trackgeophrases.php'
                }).done(function (content) {
                    $("#if_row_phrases").html(content);
                });
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

    function autoupgrade() {
        var _check = window.confirm("Your config.php file currently instructs us to upgrade everything with a complexity level up to '<?php if (defined('AUTOUPDATE_LEVEL')) { echo AUTOUPDATE_LEVEL; } else { echo 'trivial'; } ?>'. \nPlease confirm you would like to schedule an upgrade of TCAT.");
        if (_check) {
            var _params = {action:"autoupgrade"};
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
    }

    </script>
</body>
</html>
