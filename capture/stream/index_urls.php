<?php 

include "config.php";

dbconnect();
	
	$starttime = time();
	$endtime = $starttime + (60 * 60 * 3.9);
	
	$counter = 0;
			
	$currenttime = time();		
	
	//ht.ly, is.gd, bit.ly, ow.ly
	$sql = "SELECT id,url,url_expanded FROM datascience_urls WHERE domain='' ORDER BY url_expanded LIMIT 0,12500";
	
	$sqlresult = mysql_query($sql);
	
	if(mysql_num_rows($sqlresult) == 0) {
		echo("no urls to translate");
		exit;
	}
	
	$trans = array();
	
	while($url = mysql_fetch_assoc($sqlresult)) {
		
		if($url["url_expanded"] != "") {
			$url["url"] = $url["url_expanded"];
		}
				
		$errorcode = "";
		$longurl = "";

		//echo $urls[$i]["url_short"];
		
		if(!isset($trans[$url["url"]])) {
						
			$handle   = curl_init($url["url"]);
   
		    curl_setopt($handle, CURLOPT_HEADER, false);
		    curl_setopt($handle, CURLOPT_FAILONERROR, true);
		    //curl_setopt($handle, CURLOPT_HTTPHEADER, Array("User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.15) Gecko/20080623 Firefox/2.0.0.15") ); // request as if Firefox    
		    curl_setopt($handle, CURLOPT_NOBODY, true);
		    curl_setopt($handle, CURLOPT_RETURNTRANSFER, false);
		    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
		    curl_setopt($handle, CURLOPT_TIMEOUT, 5);
		    $connectable = curl_exec($handle);
		    
			$info = curl_getinfo($handle);
		
			$http_code = $info["http_code"];
			
			//echo $http_response_header[0];
			//echo "code: ".$http_code;
			//print_r($http_response_header);
			//print_r($info);
			
			if($http_code == 200) {
			
				$longurl = $info["url"];
				
				$trans[$url["url"]] = $longurl;
				
			} else {
				
				$longurl = $info["url"];
				
				$trans[$url["url"]] = $longurl;
				
				$errorcode = $http_code;
			}
		
		} else {
			
			$longurl = $trans[$url["url"]];
		}
		
		preg_match_all('/\/\/(.*?)(\/|$)/i', $longurl, $tmp);
		$domain = preg_replace("/www./","",$tmp[1][0]);
		
	
		$sqlup = "UPDATE urls SET url_followed='" . addslashes($longurl) . "',domain='".$domain."',error_code='" . $errorcode . "' WHERE id = '" . $url["id"] . "';";
		
		//$sqlupresults = mysql_query($sqlup);
		
		echo  $counter . " - " . $domain . " - " . $url["url"]. " => " . $longurl . "<br />";
		
		$counter++;
				
		flush();
	}

dbclose();

?>
