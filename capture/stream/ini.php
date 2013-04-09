<?php

// +++++ capture data +++++

// Non-space separated languages, such as CJK and Arabic, are currently unsupported.
// https://dev.twitter.com/docs/streaming-apis/parameters#track
$querybins = array(
				"globalwarming" => "global warming,globalwarming,climate,climatechange,drought,flood",  			// drought,flood added on 26.11.2012
				"datascience" => "bigdata,dataviz,datavis",
				"copyright" => "#itu,acta,#freeandopen,#netfreedom,#ETNO,#WCIT12,WCIT,#sopa,sopablackout,IPRED,TPPA,TPP", 			// freeandopen,netfreedom,#ETNO,WCIT12 added 3.12.2012
				"israel" => "israel,gaza,palestine,palestinian,#PalestinianHana,#BDS,hamas,israelunderfire,gazaunderattack",
				"anonymous" => "anonymous",
				"americanpolitics" => "#tcot,#p2,#dem,#teaparty,#gop",
				"syria" => "syria",
				"eurocrisis" => "crisis,austerity,euro,eurocrisis",
				"g20" => "g20",
				"tibet" => "immolation,tibet",																	// added 27.11.2012
				"morsi" => "morsi",																				// added 27.11.2012
				"windows8" => "win8,windows8",																	// added 27.11.2012
				"drones" => "drone,drones",																		// added 27.11.2012
				"privacy" => "surveillance,privacy,facebookprivacy",											// added 27.11.2012 (facebookprivacy added 28.11.2012)
				"africa" => "africa",																			// added 27.11.2012
				"humanrights" => "rsf,hrw,humanrights,'human rights',#amnesty,'amnesty international'",								// added 28.11.2012
				"jihad" => "jihad,djihad,dschihad,antijihad,antidjihad,antidschihad,cihad",											// added 28.11.2012
				"datajournalism" => "datajournalism,#ddj,dataviz,datavis,'data journalism','investigative journalism'",				// added 04.12.2012
				"islam" => "islam,muslim,quran,halal,sharia",
				"myjihad" => "myjihad",
				"SIOA" => "Atlasshrugs,jihadwatchRS,sioa",
				"aaronsw" => "aaronsw,pdftribute",
				"dehlirape" => "dehlirape,gangrape,rape,dehli",
				"mali" => "mali",
				"edl" => "carroll edl,carroll defence",
				"digitalhumanities" =>"digitalhumanities,digital humanities,#dh,digihum,digihums,transformdh",
				"TeaPartyvsEDL" => "teaparty,'tea party',edl,englishdefenceleague,'english defence league'",
				"mooc" => "mooc,uvamooc",
				"chemtrails"=>"chemtrails,geoengineering",
				"nuclearprotestlondon"=>"fukushima,nuclear,nukes,edf,hinkley,sellafield,tepco,radiation",
				"worldwar2" => "'wojna II',weltkrieg,світова війна,УПА,ВОВ,WWII,'segunda guerra','II guerra','seconde guerre'",
				"abdicatie" => "abdicatie,kroning,troonswisseling,koning,monarchie,koninginnedag,koningsdag,wimlex,willem-alexander,inhuldiging",
				"fairphone" => "fairphone",
				"penw" => "penw",
				"hivos" => "hivos",
				);

$queryarchives = array(
				"oscars" => "oscars,oscar,academy award,#bestdressed,theacademy,oscarroadtrip,#oscaring,#oscarnoms,#oscarfever,'red carpet',redcarpet",
				"woldwaterday" => "krnwtr,kraanwater,wereldwaterdag,22maart,horeca,bronwater,krnwtr-app,krnwtrapp,wetapwater,worldwaterday,waterday",
				"warchild"=>"warchild, actieweek, 538, 538voorwarchild, 538radio, alkmaarvoorwarchild,  arnhemvoorwarchild, bergenopzoomvoorwarchild, goudavoorwarchild, groningenvoorwarchild, heerenveenvoorwarchild, shertogenboschvoorwarchild, veenendaalvoorwarchild,538truck,kindsoldaten,kindsoldaat",
				);				


# twitter stream authentication
$user = 'milgrameister';
$pass = 'dapassX1';


// +++++ database connection data +++++

$hostname =	"localhost";					// define hostname
$database =	"twittercapture"; 				// define database name
$dbuser =	"root"; 						// user
$dbpass =	"";
$dbpass =	"Y9vEwrUh";

// +++++ includes +++++

$path_local = "/var/www/twitter/capture/stream/";


// +++++ database connection functions +++++

function dbconnect() {
	global $hostname,$database,$dbuser,$dbpass,$db;
	$db = mysql_connect($hostname,$dbuser,$dbpass) or die("Database error");
	mysql_select_db($database, $db);
	mysql_set_charset('utf8',$db);
}

function dbclose() {
	global $db;
	mysql_close($db);
}
	
?>
