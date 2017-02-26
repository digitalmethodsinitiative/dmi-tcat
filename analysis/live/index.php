<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/functions.php';
require_once __DIR__ . '/../../api/lib/tcat_util.php';
require_once 'functions.php';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title><?= $dataset ?> - TCAT live</title>

        <!-- Bootstrap core CSS -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

        <!-- theme -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">

        <!-- Custom styles for this template -->
        <link href="dashboard.css" rel="stylesheet">

        <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
          <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
        <script type="text/javascript" src="https://www.google.com/jsapi"></script>
        <script type="text/javascript">google.load("visualization", "1", {packages: ["corechart"]});</script>
    </head>

    <?php
    $keywords = array();
    $esc = array();

    validate_all_variables();
    ?>

    <body>
        <nav class="navbar navbar-inverse navbar-fixed-top">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="#">TCAT live</a>
                    <span class="navbar-brand">Dataset: <?= $dataset ?></span>
                </div>
                <div id="navbar" class="navbar-collapse collapse">
                    <ul class="nav navbar-nav navbar-right">
                        <?php
                        $url = "../?";
                        foreach ($esc['mysql'] as $key => $value) {
                            $url .= "$key=$value&";
                        }
                        $url .= "startdate=$startdate&enddate=$enddate&graph_resolution=minute";
                        ?>
                        <li><a href="<?= $url ?>">Analysis</a></li>
                        <li><a href="../../capture/">Capture</a></li>
                        <li><a href='#' class='settings-toggle'>Settings</a></li>
                    </ul>
                    <!--<form class="navbar-form navbar-right">
                        <input type="text" class="form-control" placeholder="Search...">
                    </form>
                    -->
                </div>
            </div>
        </nav>
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-3 col-md-1 sidebar">
                    <ul class="nav nav-sidebar">
                        <ul class="nav nav-sidebar">
                            <li class="active"><a href="#">Export</a></li>
                            <li><a href="<?= $url ?>" traget='_blank'>Show in analysis</a></li>
                            <!--<li><a href="#">Analytics</a></li>
                            <li><a href="#">Export</a></li>-->
                        </ul>
                    </ul>
                    <ul class="nav nav-sidebar">
                        <ul class="nav nav-sidebar">
                            <li class="active"><a href="#">Locations</a></li>
                            <li><a href="http://tcat7.digitalmethods.net/analysis/live/" traget='_blank'>tcat7</a></li>
                            <li><a href="http://tcat8.digitalmethods.net/analysis/live/" traget='_blank'>tcat8</a></li>
                            <li><a href="http://tcat9.digitalmethods.net/analysis/live/" traget='_blank'>tcat9</a></li>
                            <!--<li><a href="#">Analytics</a></li>
                            <li><a href="#">Export</a></li>-->
                        </ul>
                    </ul>
                </div>
                
                <div class="col-sm-9 col-sm-offset-3 col-md-11 col-md-offset-1 main">
                    <div class="row placeholders hidden" id='settings'>
                        <button type="button" class="close settings-toggle" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <div class="table-responsive">
                            <table class="table table-condensed">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Input</th>
                                        <th>Info</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td></td>
                                        <td>
                                            <form action="index.php" method="get" id="form">
                                                <?php
                                                echo '<select id="ipt_dataset" name="dataset" class="form-control">';

                                                $ordered_datasets = array();
                                                foreach ($datasets as $key => $set) {
                                                    $ordered_datasets["other"][$key] = $set;
                                                }
                                                ksort($ordered_datasets);

                                                $count = 0;
                                                foreach ($ordered_datasets as $groupname => $group) {

                                                    echo '<optgroup label="' . $groupname . '">';

                                                    foreach ($group as $key => $set) {

                                                        $v = ($key == $dataset) ? 'selected="selected"' : "";

                                                        echo '<option value="' . $key . '" ' . $v . '>' . $set["bin"] . ' --- ' . number_format($set["notweets"], 0, ",", ".") . ' tweets from ' . $set['mintime'] . ' to ' . $set['maxtime'] . '</option>';
                                                        $count += $set['notweets'];
                                                    }

                                                    echo '</optgroup>';
                                                }

                                                echo "</select> ";
                                                ?>
                                        </td>
                                        <td></td>
                                    </tr>
                                    <tr><td>In this dataset</td><td><?php echo preg_replace("/,/", ", ", $datasets[$dataset]['keywords']); ?></td><td></td></tr>
                                    <tr>
                                        <td class="tbl_head">Query: </td><td><input type="text" id="ipt_query" class="form-control" name="query" value="<?php echo $query; ?>" /></td><td> (empty: containing any text*)</td>
                                    </tr>

                                    <tr>
                                        <td class="tbl_head">Exclude: </td><td><input type="text" id="ipt_exclude" class="form-control" name="exclude"  value="<?php echo $exclude; ?>" /></td><td> (empty: exclude nothing*)</td>
                                    </tr>

                                    <tr>
                                        <td class="tbl_head">From user: </td><td><input type="text" id="ipt_from_user" class="form-control" name="from_user_name"  value="<?php echo $from_user_name; ?>" /></td><td> (empty: from any user*)</td>
                                    </tr>

                                    <tr>
                                        <td class="tbl_head">Exclude user: </td><td><input type="text" id="ipt_from_user" class="form-control" name="exclude_from_user_name"  value="<?php echo $exclude_from_user_name; ?>" /></td><td> (empty: from any user*)</td>
                                    </tr>

                                    <tr>
                                        <td class="tbl_head">User bio: </td><td><input type="text" id="ipt_user_bio" class="form-control" name="from_user_description"  value="<?php echo $from_user_description; ?>" /></td><td> (empty: from any user*)</td>
                                    </tr>
                                    <!--<tr>
                                        <td class="tbl_head">User language: </td><td><input type="text" id="ipt_from_source" class="form-control" name="from_user_lang"  value="<?php echo $from_user_lang; ?>" /></td><td> (empty: any language*)</td>
                                    </tr>-->
                                    <tr>
                                        <td class="tbl_head">From twitter client: </td><td><input type="text" id="ipt_from_source" class="form-control" name="from_source"  value="<?php echo $from_source; ?>" /></td><td> (empty: from any client*)</td>
                                    </tr>
                                    <!--<tr>
                                        <td class="tbl_head">(Part of) URL: </td><td><input type="text" id="ipt_url_query" class="form-control" name="url_query"  value="<?php echo $url_query; ?>" /></td><td> (empty: any or all URLs*)</td>
                                    </tr>-->
                                    <?php if (dbserver_has_geo_functions()) { ?>
                                        <tr>
                                            <td class="tbl_head">GEO bounding polygon: </td><td><input type="text" id="ipt_geo_query" class="form-control" name="geo_query"  value="<?php echo $geo_query; ?>" /></td><td> (POLYGON in <a href='http://en.wikipedia.org/wiki/Well-known_text'>WKT</a> format.)</td>
                                        </tr>
                                    <?php } ?>
                                    <tr>
                                        <td class="tbl_head">Startdate (<b>UTC</b>):</td><td><input type="text" id="ipt_startdate" class="form-control" name="startdate" value="<?php echo $startdate; ?>" /></td><td> (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)</td>
                                    </tr>

                                    <tr>
                                        <td class="tbl_head">Enddate (<b>UTC</b>):</td><td><input type="text" id="ipt_enddate" class="form-control" name="enddate" value="<?php echo $enddate; ?>" /></td><td> (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)</td>
                                    </tr>
                                    <tr><td colspan='3'>*  You can also do AND <b>or</b> OR queries, although you cannot mix AND and OR in the same query.</td></tr>
                                    <tr><td class="tbl_head"><b>Display</b></td><td><div class="checkbox">
                                                <label>
                                                    <input type="checkbox" name="show[]" value='timeline' <?php if (array_search('timeline', $show) !== false) echo "CHECKED"; ?>> Timeline<br>
                                                    <input type="checkbox" name="show[]" value='nrtweetsinperiod' <?php if (array_search('nrtweetsinperiod', $show) !== false) echo "CHECKED"; ?>> Show tweets in period<br>
                                                    <input type="checkbox" name="show[]" value='hashtags' <?php if (array_search('hashtags', $show) !== false) echo "CHECKED"; ?>> Hashtags<br>
                                                    <input type="checkbox" name="show[]" value='mentions' <?php if (array_search('mentions', $show) !== false) echo "CHECKED"; ?>> Mentions<br>
                                                    <input type="checkbox" name="show[]" value='users' <?php if (array_search('users', $show) !== false) echo "CHECKED"; ?>> Users<br>
                                                    <input type="checkbox" name="show[]" value='urls' <?php if (array_search('urls', $show) !== false) echo "CHECKED"; ?>> Urls<br>
                                                    <input type="checkbox" name="show[]" value='retweets' <?php if (array_search('retweets', $show) !== false) echo "CHECKED"; ?>> Retweets<br>
                                                </label>
                                            </div>
                                        </td><td></td>
                                    </tr>
                                    <tr><td>Group counts per x seconds</td><td><input type="text" class="form-control" name="tableinterval" value="<?php echo $tableinterval; ?>" /></td><td> In seconds</td></tr>
                                    <tr><td>Number of items per table</td><td><input type="text" class="form-control" name="top_number" value="<?php echo $top_number; ?>" /></td><td> </td></tr>
                                    <tr><td>Ignore hashtags in hashtag lists</td><td><input type="text" class="form-control" name="ignore_hashtags" value="<?php echo $ignore_hashtags; ?>" /></td><td> comma separated, without #</td></tr>
                                    <tr><td></td><td><input type="submit" value="update settings" /></td><td></td></tr>
                                </tbody>
                            </table>
                            </form>
                        </div>
                    </div>
                    <h1 class="page-header"><?= $dataset ?></h1>
                    <div class='row placeholders' style='background-color: #eee; padding: 10px; margin-bottom: 10px;'>
                        <b>In deze dataset:</b> <?php echo preg_replace("/,/", ", ", $datasets[$dataset]['keywords']); ?>
                    </div>
                    <div class='row placeholders' style='background-color: #eee; padding: 10px; margin-bottom: 10px;'>
                        Opgepast: alle tijden zijn Londen-based, dus Amsterdam - 1u!!
                    </div>

                    <?php
                    /* foreach ($esc as $k => $ar) {
                      print "<b>$k</b><br>";
                      foreach ($ar as $a => $r) {
                      print "$a: $r<br/>";
                      }
                      } */
                    ?>

                    <?php
                    $tableperiod = ((strtotime($enddate) - strtotime($startdate)) / ($tableinterval));
                    $ignore_count = count(explode(",", $ignore_hashtags));
                    if ($tableperiod > 24)
                        $tableperiod = 24;
                    for ($i = 0; $i < $tableperiod; $i++) {
                        //print $startdate . " - " . $esc['datetime']['startdate'] . "<br>";
                        $starttime_u = strtotime($startdate) + ($i * $tableinterval);
                        $endtime_u = $starttime_u + ($tableinterval);
                        //if ($starttime_u > time()) // @todo
                        //    continue;
                        $max_i = $i;
                        $times[$i]['start'] = strftime("%H:%M", $starttime_u); // @todo england
                        $times[$i]['end'] = strftime("%H:%M", $endtime_u); // @todo england
                        $times[$i]['datetimestart'] = strftime("%Y-%m-%d %H:%M:%S", $starttime_u); // @todo england
                        $times[$i]['datetimeend'] = strftime("%Y-%m-%d %H:%M:%S", $endtime_u); // @todo england
                        $starttime = strftime("%Y-%m-%d %H:%M:%S", $starttime_u);
                        //print "$i ".$esc['datetime']['startdate']." ".$starttime."<br>";
                        $endtime = strftime("%Y-%m-%d %H:%M:%S", $endtime_u);
                        if (array_search('hashtags', $show) !== false)
                            $tops['hashtag'][$i] = hashtags_top($datasets[$dataset], new
                                    DateTime($starttime), new
                                    DateTime($endtime), ($top_number + $ignore_count));
                        if (array_search('mentions', $show) !== false)
                            $tops['mention'][$i] = mentions_top($datasets[$dataset], new
                                    DateTime($starttime), new
                                    DateTime($endtime), $top_number);
                        if (array_search('users', $show) !== false)
                            $tops['user'][$i] = tweeters_top($datasets[$dataset], new
                                    DateTime($starttime), new
                                    DateTime($endtime), $top_number);
                        if (array_search('retweets', $show) !== false)
                            $tops['retweet'][$i] = retweets_top($datasets[$dataset], new
                                    DateTime($starttime), new
                                    DateTime($endtime), $top_number);
                        if (array_search('urls', $show) !== false)
                            $tops['url'][$i] = urls_top($datasets[$dataset], new
                                    DateTime($starttime), new
                                    DateTime($endtime), $top_number);
                        if (array_search('nrtweetsinperiod', $show) !== false)
                            $tops['nrtweetsinperiod'][$i] = tweets_count($datasets[$dataset], new
                                    DateTime($starttime), new
                                    DateTime($endtime), $top_number);
                    }
                    ?>
                    <div class="row placeholders">
                        <?php if (array_search('timeline', $show) !== false) linechart(strtotime($startdate), strtotime($startdate) + (($max_i + 1) * $tableinterval)); ?>
                    </div>
                    <div class="row placeholders">
                        <?php if (array_search('nrtweetsinperiod', $show) !== false) top_table($tops, 'nrtweetsinperiod', $times, $max_i); ?>
                    </div>
                    <div class="row placeholders">
                        <?php if (array_search('hashtags', $show) !== false) top_table($tops, 'hashtag', $times, $max_i); ?>
                    </div>
                    <div class="row placeholders">
                        <?php if (array_search('mentions', $show) !== false) top_table($tops, 'mention', $times, $max_i); ?>
                    </div>
                    <div class="row placeholders">
                        <?php if (array_search('users', $show) !== false) top_table($tops, 'user', $times, $max_i); ?>
                    </div>
                    <div class="row placeholders">
                        <?php if (array_search('urls', $show) !== false) top_table($tops, 'url', $times, $max_i); ?>
                    </div>
                    <div class="row placeholders">
                        <?php if (array_search('retweets', $show) !== false) top_table($tops, 'retweet', $times, $max_i); ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript
        ================================================== -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
    <!-- Twitter oembed
        ================================================== -->
    <script sync src="https://platform.twitter.com/widgets.js"></script>

    <script>

            window.onload = (function () {

                var tweets = $('.tweets');
                tweets.each(function (key, value) {
                    var tweetid = $(this).data('tweetid');
                    var id = $('#' + $(this).attr('id'));
                    twttr.widgets.createTweet(
                            tweetid, id[0],
                            {
                                conversation: 'none', // or all
                                //cards: 'hidden', // or visible 
                                //linkColor: '#cc0000', // default is blue
                                //theme: 'light',    // or dark
                                width: '220',
                                omitScript: true,
                                hideMedia: true
                            });

                });

            });

    </script>
    <script type='text/javascript'>
        $('.settings-toggle').on('click', function () {
            if ($('#settings').hasClass('show')) {
                $('#settings').removeClass('show');
                $('#settings').addClass('hidden');
            } else {
                $('#settings').removeClass('hidden');
                $('#settings').addClass('show');
            }
        });
        $('.highlight').on('mouseenter', function () {
            var what = $(this).data('what');
            $('.' + what).each(function () {
                $(this).addClass('dohighlight');

            });
        });
        $('.highlight').on('mouseleave', function () {
            var what = $(this).data('what');
            $('.' + what).each(function () {
                $(this).removeClass('dohighlight');

            });
        });

    </script>

</body>

</html>
