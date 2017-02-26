<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>zoom</title>

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
    </head>
    <body style='padding-left: 20px'>
        <?php
        require_once '../common/config.php';
        require_once '../common/functions.php';

        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);

        $sql = "SELECT from_user_name, id FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql = str_replace(" COLLATE utf8mb4_bin", "", $sql);
        $rec = $dbh->prepare($sql);
        $rec->execute();
        //print $sql."<bR>";
        while ($data = $rec->fetch(PDO::FETCH_ASSOC)) {
            if (preg_match("/_urls/", $sql))
                $ids[] = $data['tweet_id'];
            else
                $ids[] = $data['id'];
            $users[] = $data['from_user_name'];
        }
        print "<h3>" . count($ids) . " (re-)tweets ";
        if (!empty($from_user_name))
            print "from $from_user_name</h3>";
        if (!empty($query)) {
            if (substr($query, 0, 1) == "@")
                print "mentioning $query</h3>";
            else
                print "with hashtag $query</h3>";
        }
        print "<h4>Between $startdate and $enddate</h4>";
        foreach ($ids as $k => $id)
            print "<div class='tweets' id='$id' data-tweetid='$id'><a href='https://twitter.com/" . $users[$k] . "' target='_blank'>" . $users[$k] . "</a> (re)tweet: </div><div style='height:20px'></div>";
        ?>
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
                                width: '550',
                                omitScript: true,
                                hideMedia: true
                            });

                });

            });

        </script>
    </body>
</html>
