<?php 
	require_once("KloutAPIv2.class.php");
	// Set your client key and secret
	$kloutapi_key = "ENTER YOUR KEY";
	// Load the Klout API library
	$klout = new KloutAPIv2($kloutapi_key);
	// Get Klout ID
	
	// Get Variables
	$network 	= $_GET['NetworkPlatform'];
	$screenname = $_GET['NetworkScreenName'];
	$userid 	= $_GET['NetworkUserID'];
	$kloutid 	= $_GET['KloutID'];
	
?>
<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml"
      xmlns:og="http://ogp.me/ns#"
      xmlns:fb="http://www.facebook.com/2008/fbml">
<head profile="http://www.w3.org/2005/10/profile"
      prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# klout__: http://ogp.me/ns/fb/klout__#">
    <title>Klout Code Library | KloutAPIv2-PHP</title>
    <style type="text/css">
        @import url("http://kcdn3.klout.com/static/css/gz-boilerplate-MjAxMjA1MTExODA5Mjc.css?v=2");
        @import url("http://klout.com/css/fonts.css");
        @import url("http://kcdn3.klout.com/static/css/gz-styles-MjAxMjA1MTExODA5Mjc.css");
    	.rb-column { float: left; width: 50%; }
		.clear { clear: both; }
    </style>
</head>
<body>
<div id="header-wrapper" class="public developers">
    <div id="header" class="clearfix">
        <div id="header-glow"></div>
        <div class="logo-notif-public" id="logo-notif">
            
                <a href="http://klout.com/s/developers/home" class="public logo developers"><img id="logo" src="http://kcdn3.klout.com/static/images/header-logo-developers2.png" alt="Klout: The Standard for Influence"></a>
            
        </div>
        <div id="menu">
            <ul>
                    <li class=""><a class="understand-link" href="http://klout.com">Klout</a></li>
                    <li class=""><a class="understand-link" href="http://klout.com/s/developers">API</a></li>
                    <li class=""><a class="understand-link" href="http://rob.bertholf.com">@Rob</a></li>
            </ul>
        </div>
    </div>
</div>
<div id="main-wrapper" class="developers">
     <div id="main" class="default"> 
        <div id="container" class="clearfix public">
            
            <div class="content-header">
                <h1 class="header-text">Klout API Version 2 PHP</h1>
            </div>
            <div class="content-body clearfix">
   
                <div class="sidebar">
                    <ul class="navigation">
                        <li><a class="nav-link"  href="">Developer Site<div class="triangle"></div></a></li>
                        <li><a class="nav-link selected"  href="#">Version 2: PHP<div class="triangle"></div></a></li>
                        <li><a class="nav-link"  href="#Request">Request Klout ID<div class="triangle"></div></a></li>
                        <li><a class="nav-link"  href="#Reverse">Reverse Lookup<div class="triangle"></div></a></li>
                    </ul>
                </div>   
            
            
                <div class="content-main">
                    <div class="section image-section first">
                    </div>
                    <div class="section">
                        <a name="Request"></a>
                        <h2 class="section-header">Request Klout ID</h2>
                        <div class="section-body">
                            <div class="dev-notice-red">
                                <hr />
                                <div class="rb-column">
                                <h3>By Screen Name</h3>
                                    <form action="" method="GET">
                                        <label for="NetworkPlatform">Network: </label><select name="NetworkPlatform"><option value="twitter">Twitter</option></select><br />
                                        <label for="NetworkScreenName">User: </label><input type="text" name="NetworkScreenName" value="rob" /><br />
                                        <input type="submit" value="Get Klout ID" />
                                    </form>
                                </div>
                                <div class="rb-column">
                                <h3>By ID</h3>
                                    <form action="" method="GET">
                                        Network: <select name="NetworkPlatform"><option value="tw">Twitter</option></select><br />
                                        User: <input type="text" name="NetworkUserID" value="13044" /><br />
                                        <input type="submit" value="Get Klout ID" />
                                    </form>
                                </div>
                                <div class="clear"></div>

                            <?php 
                                // Is there a Screen name or ID to use?
                                if (isset($screenname)) {
                                    //echo "<p>Klout ID for <strong>$screenname</strong> on <strong>$network</strong> is: ";
                                    $kloutid = $klout->KloutIDLookupByName($network,$screenname);
                                    //echo "<strong>". $kloutid ."</strong>";
                                } elseif (isset($userid)) {
                                    //echo "<p>Klout ID for <strong>$userid</strong> on <strong>$network</strong> is: ";
                                    $kloutid = $klout->KloutIDLookupByID($network,$userid);
                                    //echo "<strong>". $kloutid ."</strong>";
                                }
                                
                                // Is there a Klout ID to be found?
                                if (isset($kloutid)) {
                                    echo "<h2>Klout ID: ". $kloutid ."</h2>\n";
									
									$KloutScore = ceil($klout->KloutScore($kloutid));
									?>
                                    <hr />

									<div id="score">
										<div class="large-flag kscore">
											<img class="kflag" src="http://kcdn3.klout.com/static/images/stroke-flag.png">
											<span class="value"><?php echo $KloutScore; ?></span>
											<div class="hovercard"></div>
										</div>
									</div>
									<?php
									
									// Get Score Changes
									$dayChanges = $klout->KloutScoreChanges($kloutid, "day");
									$weekChanges = $klout->KloutScoreChanges($kloutid, "week");
									$monthChanges = $klout->KloutScoreChanges($kloutid, "month");
									
									echo "Day: ". $dayChanges ."<br />";
									echo "Week: ". $weekChanges ."<br />";
									echo "Month: ". $monthChanges ."<br />";
									
									
									// Get Topics
									$result = $klout->KloutUserTopics($kloutid);
									$topics = json_decode($result);
									foreach($topics as $topic):
									echo "<div class=\"topic\">\n";
									echo "  <a href=\"http://klout.com/". $topic->slug ."\" target=\"_blank\"><img src=\"". $topic->imageUrl ."\" /></a>\n";
									echo "  <a href=\"http://klout.com/". $topic->slug ."\" target=\"_blank\">". $topic->displayName ."</a>\n";
									echo "</div>";
									endforeach; 

									echo "<hr />\n";
									
									
									// Get Influencers
									$result = $klout->KloutUserInfluence($kloutid);
									$influencers = json_decode($result);
									
									echo "<ul class=\"mini-user-list clearfix\">\n";
									foreach($influencers->myInfluencers as $influencer):
										$handle = $klout->KloutUser($influencer->entity->payload->kloutId);
										$handle = json_decode($handle);
										$handle = $handle->nick;
										

									echo "  <li class=\"user-item first first-row\">\n";
									echo "  <span class=\"mini-user avatar first\">\n";
									echo "    <a class=\"user-pic\" href=\"\">\n";
									echo "     ". $handle .""; //<img class=\"user-img\" src=\"/picture/n/annepanlilio/small\" alt=\"\">\n";
									echo "     <div class=\"micro-flag\">". ceil($influencer->entity->payload->score->score) ."</div>\n";
									echo "    </a>\n";
									echo "  </span>\n";
									echo "  </li>\n";
									endforeach; 
									echo "</ul>\n";



									
									echo "<ul class=\"mini-user-list clearfix\">\n";
									foreach($influencers->myInfluencees as $influencer):
										$handle = $klout->KloutUser($influencer->entity->payload->kloutId);
										$handle = json_decode($handle);
										$handle = $handle->nick;
										

									echo "  <li class=\"user-item first first-row\">\n";
									echo "  <span class=\"mini-user avatar first\">\n";
									echo "    <a class=\"user-pic\" href=\"\">\n";
									echo "     ". $handle .""; //<img class=\"user-img\" src=\"/picture/n/annepanlilio/small\" alt=\"\">\n";
									echo "     <div class=\"micro-flag\">". ceil($influencer->entity->payload->score->score) ."</div>\n";
									echo "    </a>\n";
									echo "  </span>\n";
									echo "  </li>\n";
									endforeach; 
									echo "</ul>\n";

									echo "<hr />";
                                    echo "<h3>Data Returned from \$klout->KloutUser() call</h3>\n";
                                    $result = $klout->KloutUser($kloutid);
									echo "<pre>\n";
                                    print_r($result);
                                    echo "</pre>\n";


                                    echo "<h3>Data Returned from \$klout->KloutUserTopics() call</h3>\n";
                                    $result = $klout->KloutUserTopics($kloutid);
									echo "<pre>\n";
                                    print_r($result);
                                    echo "</pre>\n";


                                    echo "<h3>Data Returned from \$klout->KloutUserInfluence() call</h3>\n";
                                    $result = $klout->KloutUserInfluence($kloutid);
									echo "<pre>\n";
                                    print_r($result);
                                    echo "</pre>\n";


                                }
                            ?>
    
    
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="section">
                        <a name="Reverse"></a>
                        <h2 class="section-header">Klout Reverse Lookup</h2>
                        <div class="section-body">
    
                            <h3>By Klout ID</h3>
                                <form action="" method="GET">
                                    Klout ID: <input type="text" name="KloutID" value="725" /><br />
                                    Return Network Data: <select name="NetworkPlatform"><option value="tw">Twitter</option></select><br />
                                    <input type="submit" value="Get Network Data" />
                                </form>
                                
								<?php 
                                    if (isset($kloutid)) {
                                        echo "<p>Network <strong>$network</strong> data for Klout ID <strong>$screenname</strong>:<br />\n";
                                        $networkdata = $klout->KloutIDLookupReverse($network,$kloutid);
                                        print_r($networkdata);
                                    }
                                ?>
                
                        </div>
                    </div>
                </div>



        </div>
     </div>
</div>
</body>
</html>
