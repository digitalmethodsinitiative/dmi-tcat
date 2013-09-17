<?php
/**
 * KloutAPI - Version 2
 * A PHP-based Klout client library
 * 
 * @package KloutAPIv2-PHP 
 * @author Rob Bertholf <rob@bertholf.com>, @rob
 * @version 1.0.1
 * @license GPLv3 <http://www.gnu.org/licenses/gpl.txt>
 */

// Define
DEFINE("HTTP_GET","GET");
DEFINE("HTTP_POST","POST");

// Get Started
class KloutAPIv2 {
	
	/** @var String $BaseUrl The base url for the Klout API */
	private $BaseUrl = "http://api.klout.com/";
	
	/** @var String $Version YYYYMMDD */
	private $Version = '20120511'; 
        
	/** @var String $KloutKey */
	private $KloutKey;
        
        public $info;
        public $response;
	
	/**
	 * Constructor for the API
	 * Prepares the request URL and client api params
	 * @param String $client_id
	 * @param String $client_secret
	 * @param String $version Defaults to v2, appends into the API url
	 */
	public function  __construct($kloutapi_key = false, $version="v2"){
		$this->BaseUrl = "{$this->BaseUrl}$version/";
		$this->KloutKey = $kloutapi_key;
	}
	
/******************************************************/
//       Identity
/******************************************************/
	
	/** 
	 * KloutIDLookupByName
	 * Looks up a Klout ID from a Screen Name
	 * @param String $network The network to look the screen name up on
	 * @param String $screenName The screen name to look up
	 */
	public function KloutIDLookupByName($network, $screenname){
		// Build the URL
		$url = $this->BaseUrl . "identity.json/". $network;
		// Append the lookup details
		$params['screenName'] = $screenname;
		$params['key'] = $this->KloutKey;
		// Return the result;
		$CurlResult = $this->GET($url,$params);
		$ResultString = json_decode($CurlResult);
		// Assume it only returns "ks" data:
		$KloutID = $ResultString->id;

		return $KloutID; 
	}

	/** 
	 * KloutIDLookupByID
	 * Looks up a Klout ID from a Twitter ID
	 * @param String $network The network to look the screen name up on
	 * @param String $id The screen name to look up
	 */
	public function KloutIDLookupByID($network, $id){
		// Build the URL
		$url = $this->BaseUrl . "identity.json/". $network ."/". $id;
		// Append the lookup details
		$params['key'] = $this->KloutKey;
		// Return the result;
		$CurlResult = $this->GET($url,$params);
		$ResultString = json_decode($CurlResult);
		// Assume it only returns "ks" data:
		$KloutID = $ResultString->id;

		return $KloutID; 
	}


	/** 
	 * KloutIDLookupReverse
	 * Looks up a Klout ID from a Twitter ID
	 * @param String $network The network to look the screen name up on
	 * @param String $id The screen name to look up
	 */
	public function KloutIDLookupReverse($network, $id){
		// Build the URL
		$url = $this->BaseUrl . "identity.json/klout/". $id ."/". $network;
		// Append the lookup details
		$params['key'] = $this->KloutKey;
		// Return the result;
		return $this->GET($url,$params);

	}

/******************************************************/
//       User
/******************************************************/

	/** 
	 * KloutUser
	 * Looks up Klout User Data
	 * @param String $id The Klout ID to look up
	 */
	public function KloutUser($id){
		// Build the URL
		$url = $this->BaseUrl ."user.json/". $id;
		// Append the lookup details
		$params['key'] = $this->KloutKey;
		// Return the result;
		return $this->GET($url,$params);

	}

	/** 
	 * KloutUserScore
	 * Looks up Klout User Score
	 * @param String $id The Klout ID to look up
	 */
	public function KloutUserScore($id){
		// Build the URL
		$url = $this->BaseUrl ."user.json/". $id ."/score";
		// Append the lookup details
		$params['key'] = $this->KloutKey;
		// Return the result;
		return $this->GET($url,$params);

	}

	/** 
	 * KloutUserTopics
	 * Looks up Klout User Topics
	 * @param String $id The Klout ID to look up
	 */
	public function KloutUserTopics($id){
		// Build the URL
		$url = $this->BaseUrl ."user.json/". $id ."/topics";
		// Append the lookup details
		$params['key'] = $this->KloutKey;
		// Return the result;
		return $this->GET($url,$params);

	}

	/** 
	 * KloutUserInfluence
	 * Looks up Klout User Influence
	 * @param String $id The Klout ID to look up
	 */
	public function KloutUserInfluence($id){
		// Build the URL
		$url = $this->BaseUrl ."user.json/". $id ."/influence";
		// Append the lookup details
		$params['key'] = $this->KloutKey;
		// Return the result;
		return $this->GET($url,$params);

	}



/******************************************************/
//       Scores
/******************************************************/

	/** 
	 * KloutScore
	 * Looks up Klout Score, does not return any other data
	 * @param String $id The Klout ID to look up
	 */
	public function KloutScore($id){
		// Use the Klout Score Data call to pull just the Score
		$CurlResult = $this->KloutUserScore($id);
		$ResultString = json_decode($CurlResult);
		$KloutScore = $ResultString->score;

		return $KloutScore; 
	}

	/** 
	 * KloutScore
	 * Returns changes in klout score
	 * @param String $id The Klout ID to look up
	 * @param String $period options are either day, week, month
	 */
	public function KloutScoreChanges($id, $period){
		// Use the Klout Score Data call to pull just the Score
		$CurlResult = $this->KloutUserScore($id);
		$ResultString = json_decode($CurlResult);
		if ($period == "day") {
			$KloutScoreChanges = $ResultString->scoreDelta->dayChange;
		} elseif ($period == "week") {
			$KloutScoreChanges = $ResultString->scoreDelta->weekChange;
		} else {
			$KloutScoreChanges = $ResultString->scoreDelta->monthChange;
		}
		return $KloutScoreChanges; 
	}





/******************************************************/
//       Utility
/******************************************************/

	/**
	 * Request
	 * Performs a cUrl request with a url generated by MakeUrl. 
	 * @param String $url The base url to query
	 * @param Array $params The parameters to pass to the request
	 */
	private function Request($url,$params=false,$type=HTTP_GET){
		
		// Populate data for the GET request
		if($type == HTTP_GET) $url = $this->MakeUrl($url,$params);

		// borrowed from Andy Langton: http://andylangton.co.uk/
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		if ( isset($_SERVER['HTTP_USER_AGENT']) ) {
			curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] );
		}else {
			// Handle the useragent like we are Google Chrome
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.X.Y.Z Safari/525.13.');
		}
		curl_setopt($ch , CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		// Populate the data for POST
		if($type == HTTP_POST){
			curl_setopt($ch, CURLOPT_POST, 1); 
			if($params) curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		}

		$result=curl_exec($ch);
		$info=curl_getinfo($ch);
		curl_close($ch);
                
                $this->response = $result;
                $this->info = $info;
		
		return $result;
	}

	/**
	 * GET
	 * Abstraction of the GET request
	 */
	private function GET($url,$params=false){
		return $this->Request($url,$params,HTTP_GET);
	}

	/**
	 * POST
	 * Abstraction of a POST request
	 */
	private function POST($url,$params=false){
		return $this->Request($url,$params,HTTP_POST);
	}

	/**
	 * MakeUrl
	 * Takes a base url and an array of parameters and sanitizes the data, then creates a complete
	 * url with each parameter as a GET parameter in the URL (Credit to Stephen Young)
	 * @param String $url The base URL to append the query string to (without any query data)
	 * @param Array $params The parameters to pass to the URL
	 */	
	private function MakeUrl($url,$params){
		if(!empty($params) && $params){
			foreach($params as $k=>$v) $kv[] = "$k=$v";
			$url_params = str_replace(" ","+",implode('&',$kv));
			$url = trim($url) . '?' . $url_params;
		}
		return $url;
	}

}
?>