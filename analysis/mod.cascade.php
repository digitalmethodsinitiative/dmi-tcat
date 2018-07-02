<?php
// @todo order by date
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';

validate_all_variables();
dataset_must_exist();
$dbh = pdo_connect();
pdo_unbuffered($dbh);
$collation = current_collation();

$exc = (empty($esc['shell']["exclude"])) ? "" : "-" . $esc['shell']["exclude"];

$select = "t.id, from_user_name COLLATE $collation as from_user_name, text COLLATE $collation as text, created_at, retweet_id";

if (isset($_GET['minf'])&&!empty($_GET['minf'])) {
    if(!preg_match("/^\d+$/",$_GET['minf'])) die('minf not a number');
    $minf = $_GET['minf'];
    $sql = "SELECT count(t.id) as cnt, from_user_name COLLATE $collation as from_user_name FROM " . $esc['mysql']['dataset'] . "_tweets t ";
    $sql .= sqlSubset();
    $sql .= " GROUP BY from_user_name COLLATE $collation"; 
    $rec = $dbh->prepare($sql);
    $rec->execute();
    while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {
        if ($res['cnt'] >= $minf)
            $users[] = $res['from_user_name'];
    }

    $sql = "SELECT $select FROM " . $esc['mysql']['dataset'] . "_tweets t ";
    $sql .= sqlSubset();
    $sql .= " AND from_user_name IN ('" . implode("','", $users) . "')";
} else {

    $sql = "SELECT $select FROM " . $esc['mysql']['dataset'] . "_tweets t ";
    $sql .= sqlSubset();
}

$tabtweets = array();
$tabusers = array();
$tabids = array();
$tabrts = array();

$rec = $dbh->prepare($sql);
$rec->execute();
while ($res = $rec->fetch(PDO::FETCH_ASSOC)) {

	//print_r($res);

    if (!in_array($res["from_user_name"], $tabusers)) {
        $tabusers[] = $res["from_user_name"];
    }

    $tabtmp = array();

    $tabtmp["id"] = $res["id"];
    $tabtmp["screen_name"] = $res["from_user_name"];
    $tabtmp["orig"] = $res["text"];
    $tabtmp["text"] = urlencode(htmlentities($res["text"]));
    $tabtmp["created_at"] = $res['created_at'];
    $tabtmp["seconds"] = strtotime($res['created_at']);
	$tabtmp["retweet_id"] = $res['retweet_id'];
    $tabtweets[] = $tabtmp;
    $tabids[] = $tabtmp["id"];
}

//print_r($tabtweets);
//exit;

foreach($tabtweets as $tweet) {
	if($tweet["retweet_id"] != 0 && in_array($tweet["retweet_id"],$tabids) ) {
		$edge = array("t" => $tweet["id"],"s" => $tweet["retweet_id"]);
		$tabrts[] = $edge;
	} 
}

$tabdays = array();
for ($i = strtotime($startdate . " 00:00:00"); $i < strtotime($enddate . " 23:59:59"); $i += 86400) {
    $tabdays[] = $i;
}
$tabdays[] = strtotime($enddate . " 23:59:59");

$output["keywords"] = array("query: " . $query);
$output["timeaxis"] = $tabdays;
$output["tweets"] = $tabtweets;
$output["users"] = $tabusers;
$output["rts"] = $tabrts;

//print_r($output);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Cascade</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <style type="text/css">

            body,html { font-family:Arial, Helvetica, sans-serif; font-size:12px; margin:0px; }

            table { border-collapse:collapse; }
            table,th, td { font-size:11px; border:1px solid #aaa; padding:2px 5px 2px 5px; }

            .table_head { font-weight:bold; }

            h1 { color:#000000; font-size:14px; padding:3px 0px 3px 5px; margin:0px 0px 10px 0px; background-color:#eee; }
            h2 { color:#0099ff; }

            #if_parameters_local { display:none; }

            #div_table { float:left; padding:5px; }
            #div_stat { float:left; padding:5px; }
            .lists { float:left; margin-right:10px; }

            #div_info { position:fixed; padding:3px 3px 0px 3px; font-size:10px; background-color:#fff; border:1px solid #000; visibility:hidden; width:250px; }
            .info_title { font-weight:bold; margin-bottom:2px; }
            .info_title_rt { color:#0099FF; font-weight:bold; margin-bottom:2px; }
            .info_text { margin-bottom:4px; }

        </style>

        <script type="text/javascript" src="scripts/jquery-1.7.1.min.js"></script>
        <script type="text/javascript" src="scripts/raphael-min.js"></script>

        <script type="text/javascript">
		
            var _data = null;

            $(document).ready(function() {
                // dummy data
                // _data = {"keywords":["text LIKE '%gouverner%'"],"timeaxis":[1298937600,1299023999],"tweets":[{"id":"42364470311399424","screen_name":"MaximeL91","created_at":"2011-03-01 00:24:11","text":"Gouverner+dans+la+dur%C3%A9e%2C+parles+en+%C3%A0+Sarkozy+mon+pote+%23France2","orig":"Gouverner dans la dur\u00e9e, parles en \u00e0 Sarkozy mon pote #France2","seconds":1298939051},{"id":"42364636766552064","screen_name":"niramot","created_at":"2011-03-01 00:24:51","text":"%22Le+gouvernement+qui+ne+gouvernerait+que+dans+une+exigence+de+r%C3%A9activit%C3%A9+ne+gouvernerait+plus%22%2C+si+c%27est+Guaino+qui+le+dit..%21+%23france2","orig":"\"Le gouvernement qui ne gouvernerait que dans une exigence de r\u00e9activit\u00e9 ne gouvernerait plus\", si c'est Guaino qui le dit..! #france2","seconds":1298939091},{"id":"42476858696146944","screen_name":"deepreal","created_at":"2011-03-01 07:50:46","text":"%23tunisie+RT+%40Bard_MeChebeK%3A+%22tous+les+arts+ont+produit+des+merveilles%2C+l%27art+de+gouverner+n%27a+produit+que+des+monstres%22+saint-just","orig":"#tunisie RT @Bard_MeChebeK: \"tous les arts ont produit des merveilles, l'art de gouverner n'a produit que des monstres\" saint-just","seconds":1298965846},{"id":"42535942187270144","screen_name":"v_yaya","created_at":"2011-03-01 11:45:33","text":"%23DateDuJour+01%2F03+293+Diocl%C3%A9tien+instaure+la+%C2%ABt%C3%A9trarchie%C2%BB+pour+gouverner+l%27empire+romain+http%3A%2F%2Fj.mp%2FeaMm2e","orig":"#DateDuJour 01\/03 293 Diocl\u00e9tien instaure la \u00abt\u00e9trarchie\u00bb pour gouverner l'empire romain http:\/\/j.mp\/eaMm2e","seconds":1298979933},{"id":"42573803032805377","screen_name":"TETUmag","created_at":"2011-03-01 14:16:00","text":"Berlusconi%3A+%C2%ABPas+de+mariage+pour+les+homos+tant+que+nous+gouvernerons%21%C2%BB++http%3A%2F%2Fbit.ly%2FefCepA","orig":"Berlusconi: \u00abPas de mariage pour les homos tant que nous gouvernerons!\u00bb  http:\/\/bit.ly\/efCepA","seconds":1298988960},{"id":"42576423130644481","screen_name":"Rue69","created_at":"2011-03-01 14:26:24","text":"RT+%40TETUmag%3A+Berlusconi%3A+%C2%ABPas+de+mariage+pour+les+homos+tant+que+nous+gouvernerons%21%C2%BB++http%3A%2F%2Fbit.ly%2FefCepA","orig":"RT @TETUmag: Berlusconi: \u00abPas de mariage pour les homos tant que nous gouvernerons!\u00bb  http:\/\/bit.ly\/efCepA","seconds":1298989584},{"id":"42608167171014656","screen_name":"psst_","created_at":"2011-03-01 16:32:33","text":"Lancement+du+r%C3%A9seau+en+ligne+de+MARSEILLE+2.0%2C+le+30+mai+2011+%3D+%22Gouverner+2.0+%3A+gouverner+la+cit%C3%A9%2C+gouverner+l%E2%3F%3Fentr...+http%3A%2F%2Fbit.ly%2FhBNl1z","orig":null,"seconds":1298997153},{"id":"42608373111324672","screen_name":"action_designer","created_at":"2011-03-01 16:33:22","text":"RT+%40psst_%3A+Lancement+du+r%C3%A9seau+en+ligne+de+MARSEILLE+2.0%2C+le+30+mai+2011+%3D+%22Gouverner+2.0++http%3A%2F%2Fbit.ly%2FhBNl1z","orig":"RT @psst_: Lancement du r\u00e9seau en ligne de MARSEILLE 2.0, le 30 mai 2011 = \"Gouverner 2.0  http:\/\/bit.ly\/hBNl1z","seconds":1298997202},{"id":"42608378068992001","screen_name":"aperosdujeudi","created_at":"2011-03-01 16:33:23","text":"RT+%40psst_%3A+Lancement+du+r%C3%A9seau+en+ligne+de+MARSEILLE+2.0%2C+le+30+mai+2011+%3D+%22Gouverner+2.0++http%3A%2F%2Fbit.ly%2FhBNl1z","orig":"RT @psst_: Lancement du r\u00e9seau en ligne de MARSEILLE 2.0, le 30 mai 2011 = \"Gouverner 2.0  http:\/\/bit.ly\/hBNl1z","seconds":1298997203},{"id":"42608382162632704","screen_name":"community_mngr","created_at":"2011-03-01 16:33:24","text":"RT+%40psst_%3A+Lancement+du+r%C3%A9seau+en+ligne+de+MARSEILLE+2.0%2C+le+30+mai+2011+%3D+%22Gouverner+2.0++http%3A%2F%2Fbit.ly%2FhBNl1z","orig":"RT @psst_: Lancement du r\u00e9seau en ligne de MARSEILLE 2.0, le 30 mai 2011 = \"Gouverner 2.0  http:\/\/bit.ly\/hBNl1z","seconds":1298997204},{"id":"42608380585582593","screen_name":"brand_candy","created_at":"2011-03-01 16:33:24","text":"RT+%40psst_%3A+Lancement+du+r%C3%A9seau+en+ligne+de+MARSEILLE+2.0%2C+le+30+mai+2011+%3D+%22Gouverner+2.0++http%3A%2F%2Fbit.ly%2FhBNl1z","orig":"RT @psst_: Lancement du r\u00e9seau en ligne de MARSEILLE 2.0, le 30 mai 2011 = \"Gouverner 2.0  http:\/\/bit.ly\/hBNl1z","seconds":1298997204},{"id":"42608385409040384","screen_name":"_le_fil_","created_at":"2011-03-01 16:33:25","text":"RT+%40psst_%3A+Lancement+du+r%C3%A9seau+en+ligne+de+MARSEILLE+2.0%2C+le+30+mai+2011+%3D+%22Gouverner+2.0++http%3A%2F%2Fbit.ly%2FhBNl1z","orig":"RT @psst_: Lancement du r\u00e9seau en ligne de MARSEILLE 2.0, le 30 mai 2011 = \"Gouverner 2.0  http:\/\/bit.ly\/hBNl1z","seconds":1298997205},{"id":"42608921407528960","screen_name":"jeremydumont","created_at":"2011-03-01 16:35:33","text":"Lancement+du+r%C3%A9seau+en+ligne+de+MARSEILLE+2.0%2C+le+30+mai+2011+%3D+%22Gouverner+2.0+%3A+gouverner+la+cit%C3%A9%2C+gouverner+l%E2%3F%3Fentr...+http%3A%2F%2Fbit.ly%2FhBNl1z","orig":null,"seconds":1298997333},{"id":"42609265550163968","screen_name":"at2rty","created_at":"2011-03-01 16:36:55","text":"RT+%40jeremydumont%3A+Lancement+du+r%C3%A9seau+en+ligne+de+MARSEILLE+2.0%2C+le+30+mai+2011+%3D+%22Gouverner+2.0+%3A+gouverner+la+cit%C3%A9%2C+gouverner+l%E2%3F%3Fentr..+...","orig":null,"seconds":1298997415},{"id":"42626270600765440","screen_name":"psst_","created_at":"2011-03-01 17:44:29","text":"RT+%40psst_%3A+Lancement+du+r%C3%A9seau+en+ligne+de+MARSEILLE+2.0+%3D+%22Gouverner+2.0++http%3A%2F%2Fbit.ly%2FhBNl1z+%40marseille2","orig":"RT @psst_: Lancement du r\u00e9seau en ligne de MARSEILLE 2.0 = \"Gouverner 2.0  http:\/\/bit.ly\/hBNl1z @marseille2","seconds":1299001469},{"id":"42626268390371329","screen_name":"parisdeuxzero","created_at":"2011-03-01 17:44:29","text":"RT+%40psst_%3A+Lancement+du+r%C3%A9seau+en+ligne+de+MARSEILLE+2.0+%3D+%22Gouverner+2.0++http%3A%2F%2Fbit.ly%2FhBNl1z+%40marseille2","orig":"RT @psst_: Lancement du r\u00e9seau en ligne de MARSEILLE 2.0 = \"Gouverner 2.0  http:\/\/bit.ly\/hBNl1z @marseille2","seconds":1299001469},{"id":"42626272890847233","screen_name":"socioinnovation","created_at":"2011-03-01 17:44:30","text":"RT+%40psst_%3A+Lancement+du+r%C3%A9seau+en+ligne+de+MARSEILLE+2.0+%3D+%22Gouverner+2.0++http%3A%2F%2Fbit.ly%2FhBNl1z+%40marseille2","orig":"RT @psst_: Lancement du r\u00e9seau en ligne de MARSEILLE 2.0 = \"Gouverner 2.0  http:\/\/bit.ly\/hBNl1z @marseille2","seconds":1299001470},{"id":"42626467875663875","screen_name":"asbrousseau_com","created_at":"2011-03-01 17:45:16","text":"RT+%40socioinnovation%3A+RT+%40psst_%3A+Lancement+du+r%C3%A9seau+en+ligne+de+MARSEILLE+2.0+%3D+%22Gouverner+2.0++http%3A%2F%2Fbit.ly%2FhBNl1z+%40marseille2","orig":"RT @socioinnovation: RT @psst_: Lancement du r\u00e9seau en ligne de MARSEILLE 2.0 = \"Gouverner 2.0  http:\/\/bit.ly\/hBNl1z @marseille2","seconds":1299001516},{"id":"42628185220841472","screen_name":"lci_articles","created_at":"2011-03-01 17:52:06","text":"Libye+-+%22L%27opposition+libyenne+n%27est+pas+pr%C3%A9par%C3%A9e+%C3%A0+gouverner%22+http%3A%2F%2Fbit.ly%2FebFvxi","orig":"Libye - \"L'opposition libyenne n'est pas pr\u00e9par\u00e9e \u00e0 gouverner\" http:\/\/bit.ly\/ebFvxi","seconds":1299001926},{"id":"42655515158904832","screen_name":"instantarchi","created_at":"2011-03-01 19:40:41","text":"en+train+de+lire-Paris%2Cm%C3%A9tropole+hors+les+murs%3AAm%C3%A9nager+et+gouverner+un+Grand+Paris-F.Gilli%2C+J.M.Offner+http%3A%2F%2Fis.gd%2F95HTM3+%23demopart+%23gov20","orig":"en train de lire-Paris,m\u00e9tropole hors les murs:Am\u00e9nager et gouverner un Grand Paris-F.Gilli, J.M.Offner http:\/\/is.gd\/95HTM3 #demopart #gov20","seconds":1299008441}],"users":["MaximeL91","niramot","deepreal","v_yaya","TETUmag","Rue69","psst_","action_designer","aperosdujeudi","community_mngr","brand_candy","_le_fil_","jeremydumont","at2rty","parisdeuxzero","socioinnovation","asbrousseau_com","lci_articles","instantarchi"],"rts":[{"s":"42608921407528960","t":"42609265550163968"},{"s":"42626272890847233","t":"42626467875663875"}]};
                _data = <?php echo json_encode($output); ?>;
                setupCanvas();
            });

            var _paper = null;
            var _blockHeight = 6;
            var _leftspace = 120;

            var _nodes = new Object();
            var _edges = new Object();
	
            var _attrs = new Object();
            _attrs.EdgeLo = {"stroke":"#00d","stroke-opacity":0.2};
            _attrs.EdgeHi = {"stroke":"#0ff","stroke-opacity":1};
            _attrs.NodeLo = {"fill":"#aaa","stroke":"#ddd"};
            _attrs.NodeHi = {"fill":"#f00","stroke":"#f00"};
            _attrs.NodeRtedLo = {"fill":"#00cc00","stroke":"#00ff00"};
            _attrs.NodeRtingLo = {"fill":"#0066cc","stroke":"#0099ff"};

            function setupCanvas() {
		
                _timeStart = _data["timeaxis"][0];
                _timeEnd = _data["timeaxis"][_data["timeaxis"].length - 1];
                _timeDiff = _timeEnd - _timeStart;

                _canvasWidth = $(document).width() - 20;
                _canvasHeight = _data["users"].length * _blockHeight + _blockHeight;
		
                $("#div_info").css("top",$(document).height() - 150);

                //alert(timeToPos(_data["timeaxis"][1]))


                _paper = Raphael(document.getElementById("div_canvas"), _canvasWidth, _canvasHeight);
		
                var _border = _paper.rect(_leftspace, 0, _canvasWidth - _leftspace, _canvasHeight).attr({fill: "white"});
		

                //set grid
                for(var i = 1; i < _data["timeaxis"].length - 1; i++) {
			
                    var _tmpx = timeToPos(_data["timeaxis"][i]);

                    var _tmpline = _paper.path("M" + _tmpx + " 0L" + _tmpx + " " + _canvasHeight).attr({ stroke : "#ccc" });
                }

                for(var i = 0; i < _data["users"].length; i++) {
					
					var _tmplabel = _paper.text(_leftspace - 5,(i * _blockHeight + _blockHeight),_data["users"][i]).attr({"text-anchor":"end","font-size":7}).mouseover(function (event) { this.attr({"font-size":12})}).mouseout(function (event) { this.attr({"font-size":7})});
			
                    if(i != 0 && i % 2 == 0) {

                        var _tmpline = _paper.path("M"+_leftspace+" " + (i * _blockHeight) + "L" + _canvasWidth + " " + (i * _blockHeight)).attr({ stroke : "#eee" });
                    }
                }

                // set tweets
                for(var i = 0; i < _data["tweets"].length; i++) {
			
                    var _tmpx = timeToPos(_data["tweets"][i]["seconds"]);
                    var _tmpy = _blockHeight * userPos(_data["tweets"][i]["screen_name"]) + _blockHeight;

                    _nodes[_data["tweets"][i]["id"]] = _paper.circle(_tmpx, _tmpy, 3).attr(_attrs.NodeLo);
                    //_nodes[_data["tweets"][i]["id"]] = _paper.rect(_tmpx, _tmpy+3,2,6).attr({fill: "#cccccc", stroke:"#cccccc"});
			
                    // attach to every node its tweet, the related edges for highlighting 
                    _nodes[_data["tweets"][i]["id"]].tweet = _data["tweets"][i];																	
                    _nodes[_data["tweets"][i]["id"]].rted = new Array();
                    _nodes[_data["tweets"][i]["id"]].rting = new Array();
                    _nodes[_data["tweets"][i]["id"]].edges = new Array();
                    _nodes[_data["tweets"][i]["id"]].NodeLo = _attrs.NodeLo;

                    // --- events
                    _nodes[_data["tweets"][i]["id"]].click(function (event) {
                        alert(urldecode(this.tweet.text));
                    });

                    _nodes[_data["tweets"][i]["id"]].mouseover(function (event) {
                        this.attr(_attrs.NodeHi);
                        var _tmphtml = '<div class="info_title">' + this.tweet.screen_name + ' / ' + this.tweet.created_at + '</div>' +
                            '<div class="info_text">retweeted: ' + this.rted.length + ' times</div>' +
                            '<div class="info_text">' + urldecode(this.tweet.text) + '</div>';
                        for(var j = 0; j < this.edges.length; j++) {
                            _nodes[this.edges[j].target].attr(_attrs.NodeHi);
                            _tmphtml += '<div class="info_title_rt">' + _nodes[this.edges[j].target].tweet.screen_name + ' / ' + _nodes[this.edges[j].target].tweet.created_at + '</div>' +
                                '<div class="info_text">' + urldecode(_nodes[this.edges[j].target].tweet.text) + '</div>';
                            _nodes[this.edges[j].source].attr(_attrs.NodeHi);
                            this.edges[j].attr(_attrs.EdgeHi);
                        }
    						   
                        $("#div_info").html(_tmphtml);
                        var _tmpPosY = (event.clientY < 200) ? 0 : -200;
                        $("#div_info").css("top",event.clientY + _tmpPosY);
                        var _tmpPosX = (event.clientX < 300) ? 30 : -280;
                        $("#div_info").css("left",event.clientX + _tmpPosX);
                        $("#div_info").css("visibility","visible");
                    });

                    _nodes[_data["tweets"][i]["id"]].mouseout(function (event) {
                        this.attr(this.NodeLo);
                        for(var j = 0; j < this.edges.length; j++) {
                            _nodes[this.edges[j].target].attr(_nodes[this.edges[j].target].NodeLo);
                            _nodes[this.edges[j].source].attr(_nodes[this.edges[j].source].NodeLo);
                            this.edges[j].attr(_attrs.EdgeLo);
                        }
                        $("#div_info").html("");
                        $("#div_info").css("visibility","hidden");
                    });
                    // ---/events
                }
		

                // set connection
                for(var i = 0; i < _data["rts"].length; i++) {
			
                    var _tmps = _nodes[_data["rts"][i]["s"]];
                    var _tmpt = _nodes[_data["rts"][i]["t"]];

                    if(typeof(_tmps) != "undefined" && typeof(_tmpt) != "undefined") {
				
                        // calculate distance between points to change curve
                        var _tmpdist = Math.sqrt(Math.pow((_tmps.attrs.cx - _tmpt.attrs.cx),2) + Math.pow((_tmps.attrs.cy - _tmpt.attrs.cy),2));
                        _tmpdist = Math.pow(Math.log(_tmpdist),2) * 2;

                        //var _tmpo = _paper.path("M" + _tmps.attrs.cx + " " + _tmps.attrs.cy + "T" + _tmpt.attrs.cx + " " + _tmpt.attrs.cy).attr({stroke:"#00d"});
                        // this.reTweetLines.push(this.paper.path("M "+_xRT+","+_yRT+"C"+(_xRT+30)+","+_yRT+","+(_x-30)+","+_y+","+_x+","+_y+" M "+(_x-5)+","+(_y-2)+","+_x+","+_y+","+(_x-5)+","+(_y+2)));
                        var _tmpo = _paper.path("M"+_tmps.attrs.cx+","+_tmps.attrs.cy+"C" + (_tmps.attrs.cx + (_tmpdist)) + "," + _tmps.attrs.cy + "," + (_tmpt.attrs.cx-(_tmpdist)) + "," + _tmpt.attrs.cy + "," + _tmpt.attrs.cx + "," + _tmpt.attrs.cy +" M " + (_tmpt.attrs.cx - _tmpdist) + "," + (_tmps.attrs.cy - _tmpdist)).attr(_attrs.EdgeLo);
                        _tmpo.edge = _data["rts"][i]["s"] + "_" + _data["rts"][i]["t"];
                        _tmpo.source = _data["rts"][i]["s"];
                        _tmpo.target = _data["rts"][i]["t"];

                        _tmpo.mouseover(function (event) {
                            _nodes[this.source].attr(_attrs.NodeHi);
                            _nodes[this.target].attr(_attrs.NodeHi);
                            this.attr(_attrs.EdgeHi);
                            var _tmphtml = '<div class="info_title">' + _nodes[this.source].tweet.screen_name + ' / ' + _nodes[this.source].tweet.created_at + '</div>' + 
                                '<div class="info_text">' + urldecode(_nodes[this.source].tweet.text) + '</div>' +
                                '<div class="info_title_rt">' + _nodes[this.target].tweet.screen_name + ' / ' + _nodes[this.target].tweet.created_at + '</div>' + 
                                '<div class="info_text">' + urldecode(_nodes[this.target].tweet.text) + '</div>';
                            $("#div_info").html(_tmphtml);
                            var _tmpPosY = (event.clientY < 200) ? 0 : -200;
                            $("#div_info").css("top",event.clientY + _tmpPosY);
                            var _tmpPosX = (event.clientX < 300) ? 30 : -280;
                            $("#div_info").css("left",event.clientX - _tmpPosX);
                            $("#div_info").css("visibility","visible");
                        });

 
                        _tmpo.mouseout(function (event) {
                            _nodes[this.source].attr(_nodes[this.source].NodeLo);
                            _nodes[this.target].attr(_nodes[this.target].NodeLo);
                            this.attr(_attrs.EdgeLo);
                            $("#div_info").html("");
                            $("#div_info").css("visibility","hidden");
                        });

                        _tmpo.click(function (event) {
                            alert(urldecode(_nodes[this.source].tweet.text) + "\n" + urldecode(_nodes[this.target].tweet.text));
                        });

                        _edges[_tmpo.edge] = _tmpo;
				
				
                        // push related nodes and edge into object
                        _tmps.rted.push(_tmpt);
                        _tmpt.rting.push(_tmps);
                        _tmps.edges.push(_edges[_tmpo.edge]);
                        _tmpt.edges.push(_edges[_tmpo.edge]);
                    }
                }
		
                // color nodes according to edges
                for(var x in _nodes) {
                    //console.log(_nodes[x].rted.length + " " + _nodes[x].rting.length);
			
                    if(_nodes[x].rted.length > 0) {
                        _nodes[x].NodeLo = _attrs.NodeRtedLo;
                        _nodes[x].attr(_nodes[x].NodeLo);
                    }
			
                    if(_nodes[x].rting.length > 0) {
                        _nodes[x].NodeLo = _attrs.NodeRtingLo;
                        _nodes[x].attr(_nodes[x].NodeLo);
                    }
                }
		
            }


            function timeToPos(_value)  {
		
                var _pos = (_value - _timeStart) * ((_canvasWidth - _leftspace) / _timeDiff) + _leftspace;

                return Math.round(_pos);	
            }


            function userPos(_value) {
		
                for(var i = 0; i < _data["users"].length; i++) {
		
                    if(_value == _data["users"][i]) {
                        return i;
                    }
                }
            }
	
	
            function showUser(_posX,_posY) {
		
                _posY -= $("#div_canvas").offset().top; 
		
                $("#info_user").html("hello" + _posY);
            }
	
            function urldecode(str) {
  		
                str = str.replace(/\+/g,"%20");
                //
                str = str.replace(/%E2%3F%A6/g,"!");
                str = str.replace(/%E2%3F%9D/g," ");
                str = str.replace(/%E2%3F%3F/g,"'");
                str = str.replace(/%E2%3F%AB/g,"!");
                str = str.replace(/%E2%3F%B8/g,"!");
                str = str.replace(/%C3%3F/g,"É");
		
                //str = str.replace(/%2F/g,"%20");
	    
                //Affligeant+RT+%40TruiteMarine%3A+para%C3%AEt+que+les+femmes+sont+des+guenons%E2%3F%A6+dommage+que+l%27article+ait+disparu+si+vite+%21+http%3A%2F%2Ftwitpic.com%2F45agb7
                // End with decodeURIComponent, which most resembles PHP's encoding functions
                str = decodeURIComponent(str);
  
                return str;
            }
            
            function encode_as_img_and_link(){

				 $("svg").attr({ version: '1.1' , xmlns:"http://www.w3.org/2000/svg"});
				
				 var svg = $("#div_canvas").html();
				 var b64 = window.btoa(unescape(encodeURIComponent(svg)));
				 
				 // Works in recent Webkit(Chrome)
				 $("#downloader").html($('<img style="width:25px;height:25px;" src="data:image/svg+xml;base64,\n'+b64+'" alt="file.svg"/>'));
				
				 // Works in Firefox 3.6 and Webit and possibly any browser which supports the data-uri
				 $("#downloader").html($('<a style="width:25px;height:25px;" href-lang="image/svg+xml" href="data:image/svg+xml;base64,\n'+b64+'" title="file.svg">Download</a>'));
			}

			
        </script>
    </head>

    <body>

        <h1>TCAT :: Cascade</h1>

        <div id="div_canvas"> </div>
        <div id="div_info"> </div>
        
        <br />
        
		<div>
			<input type="button" onclick="encode_as_img_and_link()" value="generate SVG link" />
		<div id="downloader"></div> 

    </body>
</html>
