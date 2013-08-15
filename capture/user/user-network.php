<?php

if ($argc < 1)
    die; // only run from command line
// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);
include_once "../../config.php";
include_once BASE_FILE . '/common/functions.php';
include_once BASE_FILE . '/capture/common/functions.php';

require BASE_FILE . '/capture/common/tmhOAuth/tmhOAuth.php';

$current_key = 0;
$out_handle = fopen("/tmp/network", "a");

$accounts = file('/tmp/users');
$done = file('/tmp/users.done');
foreach ($accounts as $account) {
    $account = trim($account);
    if (in_array($account, $done) === false) {
        get($account);
        $done[] = $account;
        file_put_contents('/tmp/users.done', implode("\n", $done));
    }
    die;
}

function get($screen_name) {
    global $twitter_keys, $current_key, $out_handle;
    $cursor = '-1';
    $friends = array();
    while (true) :
        if ($cursor == '0')
            break;

        $tmhOAuth = new tmhOAuth(array(
                    'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
                    'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
                    'token' => $twitter_keys[$current_key]['twitter_user_token'],
                    'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
                ));

        $code = $tmhOAuth->request(
                'GET', $tmhOAuth->url('1.1/friends/list'), array(
            'cursor' => $cursor,
            'skip_status' => true,
            'screen_name' => $screen_name
                )
        );

        if ($code == 200) {
            $data = json_decode($tmhOAuth->response['response'], true);
            $friends = array_merge($friends, $data['users']);
            //foreach ($data['users'] as $s) {
            //    print $s['screen_name'] . "\n";
            //}
            //print "\n\n";
            $cursor = $data['next_cursor_str'];
        } else {
            print "error " . $code . " with key $current_key\n";
            sleep(5);
        }

        // keep track of right api key
        $headers = $tmhOAuth->response['headers'];
        $ratelimitremaining = $headers['x-rate-limit-remaining'];
        $ratelimitreset = $headers['x-rate-limit-reset'];
        print "remaining $ratelimitremaining\n";

        if ($ratelimitremaining == 0) {
            $current_key++;
            print "next key $current_key\n";
            if ($current_key >= count($twitter_keys)) {
                $current_key = 0;
                $looped = 1;
                print "resetting key to 0\n";
            } elseif ($current_key == 0 && $looped == 1) {
                if (count($data['users']) > 1)
                    $looped = 0;
                else {
                    print "looped over all keys but still can't get new users, sleeping\n";
                    sleep(5);
                }
            }
        } elseif (count($data['users']) <= 1)    // search exhausted    
            die("no more users found\n");

    //usleep(500000);
    endwhile;

    echo '@' . $screen_name . ' follows ' . count($friends) . ' users.' . PHP_EOL . PHP_EOL;
    foreach ($friends as $friend) {
        $json['friendof'] = $screen_name;
        fwrite($out_handle, json_encode($json) . "\n");
    }
}

?>
