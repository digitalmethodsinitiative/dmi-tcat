<?php 

include "config.php";

$domains = file_get_contents("domains.txt");

$domains = explode("\n", $domains);

//print_r($domains);
//exit;

dbconnect();
	
	$counter = 0;
	
	$sql = "SELECT id,url_expanded FROM israel_urls WHERE domain='' ORDER BY url_expanded";
	
	$sqlresult = mysql_query($sql);
	
	if(mysql_num_rows($sqlresult) == 0) {
		echo("no urls to translate");
		exit;
	}
	
	$trans = array();
	
	while($url = mysql_fetch_assoc($sqlresult)) {
		
		if($url["url_expanded"] == "") {
			continue;
		}
		
		$url = $url["url_expanded"];
		
		preg_match_all('/\/\/(.*?)(\/|$)/i', $url, $tmp);
		$domain = strtolower(preg_replace("/www./","",$tmp[1][0]));
		
		
		if(in_array($domain, $domains)) {
			
			$sqlup = "UPDATE urls SET url_followed='" . addslashes($longurl) . "',domain='".$domain."',error_code='" . $errorcode . "' WHERE id = '" . $url["id"] . "';";
		
			 //$sqlupresults = mysql_query($sqlup);
				
			 echo  $domain . "|" . $url["url"]. " => " . $url . "<br />";
						 
			 $counter++;
		}
		
		flush();
	}

	echo $counter;

dbclose();

?>
