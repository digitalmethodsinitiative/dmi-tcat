<?php

$dataset = $esc['mysql']['dataset'];
if (!sentiments($dataset)) {
    return;
}

$sent_html = '<fieldset class="if_parameters">

            <legend>Average sentiment detected</legend>
<div id="if_panel_linegraph_sentiments">';
$sent_html .= bjs();

$avgs = sentiment_avgs();
$r = count($avgs);

$sent_html .= "
var sdata = new google.visualization.DataTable()
sdata.addColumn('string', 'Date');
sdata.addColumn('number', 'Positive');
sdata.addColumn('number', 'Negative');
sdata.addRows($r);";

//var_dump($avgs);
$counter = 0;
foreach ($avgs as $key => $sentiment) {
    $sent_html .= "sdata.setValue(" . $counter . ", 0, '" . $key . "');";
    $sent_html .= "sdata.setValue(" . $counter . ", 1, " . $sentiment[0] . ");";
    $sent_html .= "sdata.setValue(" . $counter . ", 2, " . $sentiment[1] . ");";
    $counter++;
}

$sent_html .= "
    var schart = new google.visualization.LineChart(document.getElementById('if_panel_linegraph_sentiments'));
    schart.draw(sdata, {width:1000, height:360, fontSize:9, hAxis:{slantedTextAngle:90, slantedText:true}, chartArea:{left:50,top:10,width:850,height:300}});
";

$sent_html .= ejs();

$sent_html .= '
<div class="txt_desc"><br />Date and time are in GMT (London).</div></fieldset>
';

echo $sent_html;

function bjs() {
    return '<script type="text/javascript">';
}

function ejs() {
    return '</script>';
}

function sentiments($dataset) {
    $select = "SHOW TABLES";
    $rec = mysql_query($select);
    while ($res = mysql_fetch_row($rec)) {
        if (!strcmp($res[0], $dataset . '_sentiment')) {
            return TRUE;
        }
    }
    return FALSE;
}

function sentiment_avgs() {

    $avgs = array();

    global $esc;
    global $period;

    $sql = "SELECT avg(s.positive) as pos, avg(s.negative) as neg, ";
    if ($period == "day") // @todo
        $sql .= "DATE_FORMAT(t.created_at,'%Y.%d.%m') datepart ";
    else
        $sql .= "DATE_FORMAT(t.created_at,'%d. %H:00h') datepart ";
    $sql .= "FROM " . $esc['mysql']['dataset'] . "_tweets t, ";
    $sql .= $esc['mysql']['dataset'] . "_sentiment s ";
    $sql .= sqlSubset("t.id = s.tweet_id AND ");
    $sql .= "GROUP BY datepart";

    $rec = mysql_query($sql);
    while ($res = mysql_fetch_assoc($rec)) {
        $neg = $res['neg'];
        $pos = $res['pos'];
        $el = array();
        $el[] = (float) $pos;
        $el[] = (float) abs($neg);
        $avgs[$res['datepart']] = $el;
    }
    return $avgs;
}
