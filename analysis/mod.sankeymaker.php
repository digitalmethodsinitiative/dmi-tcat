<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/Gexf.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Twitter Analytics - Sankey maker</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">

        </script>

        <script src="http://d3js.org/d3.v2.min.js?2.9.1"></script>
        <script src="http://bost.ocks.org/mike/sankey/sankey.js"></script>

        <style>

            body, html {
                font-family: Arial, Helvetica, sans-serif;
                font-size: 10px;
            }

            #chart {
                /* height: 500px; */
            }

            .node rect {
                cursor: move;
                fill-opacity: .9;
                shape-rendering: crispEdges;
            }

            .node text {
                pointer-events: none;
                /* text-shadow: 0 1px 0 #fff; */
            }

            .link {
                fill: none;
                stroke: #000;
                stroke-opacity: .2;
            }

            .link:hover {
                stroke-opacity: .5;
            }

            .form_row {
                margin:5px;
            }

        </style>

    </head>

    <body>

        <h1>Twitter Analytics - The Sankey Maker</h1>

        <form action="">

            <input type="hidden" name="dataset" value="<?php echo $_GET["dataset"]; ?>" />
            <input type="hidden" name="query" value="<?php echo $_GET["query"]; ?>" />
            <input type="hidden" name="url_query" value="<?php echo $_GET["url_query"]; ?>" />
            <input type="hidden" name="exclude" value="<?php echo $_GET["exclude"]; ?>" />
            <input type="hidden" name="from_user_name" value="<?php echo $_GET["from_user_name"]; ?>" />
            <input type="hidden" name="from_source" value="<?php echo $_GET["from_source"]; ?>" />
            <input type="hidden" name="startdate" value="<?php echo $_GET["startdate"]; ?>" />
            <input type="hidden" name="enddate" value="<?php echo $_GET["enddate"]; ?>" />

            <div class="form_row">
                col1:
                <select name="col1_type">
                    <option value="source" <?php echo ($_GET["col1_type"] == "source") ? "selected" : ""; ?>>sources</option>
                    <option value="from_user_lang" <?php echo ($_GET["col1_type"] == "from_user_lang") ? "selected" : ""; ?>>languages</option>
                    <option value="from_user_utcoffset" <?php echo ($_GET["col1_type"] == "from_user_utcoffset") ? "selected" : ""; ?>>utcoffsets</option>
                    <option value="hashtag" <?php echo ($_GET["col1_type"] == "hashtag") ? "selected" : ""; ?>>hashtags</option>
                </select>

                cutoff (0 = all) <input name="col1_cutoff" value="<?php echo ($_GET["col1_cutoff"] == "") ? 0 : $_GET["col1_cutoff"]; ?>" />
            </div>

            <div class="form_row">
                col2:
                <select name="col2_type">
                    <option value="source" <?php echo ($_GET["col2_type"] == "source") ? "selected" : ""; ?>>sources</option>
                    <option value="from_user_lang" <?php echo ($_GET["col2_type"] == "from_user_lang") ? "selected" : ""; ?>>languages</option>
                    <option value="from_user_utcoffset" <?php echo ($_GET["col2_type"] == "from_user_utcoffset") ? "selected" : ""; ?>>utcoffsets</option>
                    <option value="hashtag" <?php echo ($_GET["col2_type"] == "hashtag") ? "selected" : ""; ?>>hashtags</option>
                </select>

                cutoff (0 = all) <input name="col2_cutoff" value="<?php echo ($_GET["col2_cutoff"] == "") ? 0 : $_GET["col2_cutoff"]; ?>" />
            </div>

            <div class="form_row">
                <input name="discard_other" type="checkbox" <?php if (isset($_GET["discard_other"]) && $_GET["discard_other"] == "on") echo 'checked="checked"'; ?> /> discard "other" from diagram
            </div>

            <div class="form_row">
                <input type="submit" />
            </div>
        </form>


        <?php
        validate_all_variables();

        if ($_GET["col1_type"] == $_GET["col2_type"]) {
            echo "columns must be different";
            exit;
        }

        $sql = "SELECT LOWER(" . $_GET["col1_type"] . ") AS col1, LOWER(t." . $_GET["col2_type"] . ") AS col2 FROM ";
        $sql .= $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();

        if ($_GET["col1_type"] == "hashtag") {
            $sql = "SELECT LOWER(h.text) AS col1, LOWER(t." . $_GET["col2_type"] . ") AS col2 FROM ";
            $sql .= $esc['mysql']['dataset'] . "_tweets t, " . $esc['mysql']['dataset'] . "_hashtags h ";
            $where = "t.id = h.tweet_id AND ";
            $sql .= sqlSubset($where);
        }
        if ($_GET["col2_type"] == "hashtag") {
            $sql = "SELECT LOWER(t." . $_GET["col1_type"] . ") AS col1,LOWER(h.text) AS col2 FROM ";
            $sql .= $esc['mysql']['dataset'] . "_tweets t, " . $esc['mysql']['dataset'] . "_hashtags h ";
            $where = "t.id = h.tweet_id AND ";
            $sql .= sqlSubset($where);
        }

        //echo "sql:" . $sql;
        //exit;

        $sqlresults = mysql_query($sql);

        // run through the data once to create item counts for cutting and fusing
        $data = array();
        $toplists = array();
        $toplists["col1"] = array();
        $toplists["col2"] = array();
        while ($res = mysql_fetch_assoc($sqlresults)) {

            $col1 = $res["col1"];
            $col2 = $res["col2"];

            $col1 = preg_replace("/<.+>/U", "", $col1);
            $col1 = preg_replace("/[ \s\t]+/", " ", $col1);
            $col1 = trim($col1);
            $res["col1"] = $col1;

            $col2 = preg_replace("/<.+>/U", "", $col2);
            $col2 = preg_replace("/[ \s\t]+/", " ", $col2);
            $col2 = trim($col2);
            $res["col2"] = $col2;

            if (!isset($toplists["col1"][$col1])) {
                $toplists["col1"][$col1] = 0;
            }
            $toplists["col1"][$col1]++;

            if (!isset($toplists["col2"][$col2])) {
                $toplists["col2"][$col2] = 0;
            }
            $toplists["col2"][$col2]++;

            $data[] = $res;
        }

        foreach ($toplists as $key => $list) {
            arsort($toplists[$key]);
        }
        if ($_GET["col1_cutoff"] != 0) {
            $toplists["col1"] = array_slice($toplists["col1"], 0, $_GET["col1_cutoff"]);
        }
        if ($_GET["col2_cutoff"] != 0) {
            $toplists["col2"] = array_slice($toplists["col2"], 0, $_GET["col2_cutoff"]);
        }

        $network = array();
        $network["nodes"] = array();
        $network["links"] = array();
        $translate = array();

        foreach ($data as $res) {

            $col1 = $res['col1'];
            $col2 = $res['col2'];

            if (!isset($toplists["col1"][$col1])) {
                if (isset($_GET["discard_other"]) && $_GET["discard_other"] == "on") {
                    continue;
                }
                $col1 = "other " . $_GET["col1_type"];
            }
            if (!isset($toplists["col2"][$col2])) {
                if (isset($_GET["discard_other"]) && $_GET["discard_other"] == "on") {
                    continue;
                }
                $col2 = "other " . $_GET["col2_type"];
            }


            if (!in_array($col1, $network["nodes"])) {
                $network["nodes"][] = $col1;
                $translate[$col1] = count($network["nodes"]) - 1;
            }

            if (!in_array($col2, $network["nodes"])) {
                $network["nodes"][] = $col2;
                $translate[$col2] = count($network["nodes"]) - 1;
            }

            $edge = $col1 . "_XXX_" . $col2;

            if (!isset($network["links"][$edge])) {
                $network["links"][$edge] = 0;
            }
            $network["links"][$edge]++;
        }


        $newwork = array();
        $newwork["nodes"] = array();
        $newwork["links"] = array();

        for ($i = 0; $i < count($network["nodes"]); $i++) {
            $newwork["nodes"][$i] = array("name" => $network["nodes"][$i]);
        }

        $highestnode = array();       // count element frequency from edges (nodes have full frequency and not the proportional needed for correct coloring)
        $highestlink = 0;
        foreach ($network["links"] as $key => $value) {
            $elements = explode("_XXX_", $key);
            $edge = array("source" => $translate[$elements[0]], "target" => $translate[$elements[1]], "value" => $value);
            $newwork["links"][] = $edge;

            if (!isset($highestnode[$elements[0]])) {
                $highestnode[$elements[0]] = 0;
            }
            if (!isset($highestnode[$elements[1]])) {
                $highestnode[$elements[1]] = 0;
            }
            $highestnode[$elements[0]] += $value;
            $highestnode[$elements[1]] += $value;

			if($value > $highestlink) { $highestlink = $value; }
        }

        arsort($highestnode);
        $highestnode = array_values($highestnode);
        $highestnode = $highestnode[0];
        ?>

        <div id="chart"></div>

        <script>

            var margin = {top: 10, right: 10, bottom: 10, left: 10},
            width = 960 - margin.left - margin.right,
            height = 600 - margin.top - margin.bottom;

            var formatNumber = d3.format(",.0f"),
            format = function(d) { return formatNumber(d) + " tweets"; },
            color = d3.scale.category20();

            var svg = d3.select("#chart").append("svg")
            .attr("width", width + margin.left + margin.right)
            .attr("height", height + margin.top + margin.bottom)
            .append("g")
            .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

            var sankey = d3.sankey()
            .nodeWidth(15)
            .nodePadding(10)
            .size([width, height]);

            var path = sankey.link();

            var color1 = d3.scale.linear()
            .domain([0,<?php echo $highestnode/2; ?>,<?php echo $highestnode; ?>])
            .range(["#3399ff","#ffff00","#ff0000"]);

            var color2 = d3.scale.linear()
            .domain([0,<?php echo $highestlink/2; ?>, <?php echo $highestlink; ?>])
            .range(["#3399ff","#ffff00","#ff0000"]);

            var energy = <?php echo json_encode($newwork); ?>;

            sankey
            .nodes(energy.nodes)
            .links(energy.links)
            .layout(64);

            var link = svg.append("g").selectAll(".link")
            .data(energy.links)
            .enter().append("path")
            .attr("class", "link")
            .attr("d", path)
            .style("stroke-width", function(d) { return Math.max(1, d.dy); })
            .style("stroke", function(d) { return color2(d.value); })
            .sort(function(a, b) { return b.dy - a.dy; });

            link.append("title")
            .text(function(d) { return d.source.name + " and " + d.target.name + "\n" + format(d.value); });

            var node = svg.append("g").selectAll(".node")
            .data(energy.nodes)
            .enter().append("g")
            .attr("class", "node")
            .attr("transform", function(d) { return "translate(" + d.x + "," + d.y + ")"; })
            .call(d3.behavior.drag()
            .origin(function(d) { return d; })
            .on("dragstart", function() { this.parentNode.appendChild(this); })
            .on("drag", dragmove));

            node.append("rect")
            .attr("height", function(d) { return d.dy; })
            .attr("width", sankey.nodeWidth())
            //.style("fill", "#57B7B9")
            //.attr("fill", function(d) { return color(d.division); });
            .style("fill", function(d) { return color1(d.value); })
            .style("stroke", "#555")
            .style("stroke-width", 1)
            .append("title")
            .text(function(d) { return d.name + "\n" + format(d.value); });

            node.append("text")
            .attr("x", -6)
            .attr("y", function(d) { return d.dy / 2; })
            .attr("dy", ".35em")
            .attr("text-anchor", "end")
            .attr("transform", null)
            .text(function(d) { return d.name; })
            .filter(function(d) { return d.x < width / 2; })
            .attr("x", 6 + sankey.nodeWidth())
            .attr("text-anchor", "start");

            function dragmove(d) {
                d3.select(this).attr("transform", "translate(" + d.x + "," + (d.y = Math.max(0, Math.min(height - d.dy, d3.event.y))) + ")");
                sankey.relayout();
                link.attr("d", path);
            }


        </script>

    </body>
</html>
