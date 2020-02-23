<?php

/*
 * Expandable generic purpose CSV file -> TCAT importer
 *
 * This is a script which should help you map fields and attributes of an existing Twitter data dump (as a CSV file) into TCAT fields so
 * a TCAT bin could be constructed. It should support the export formats of several published dataset relating to the Internet Research Agency
 * controversy out of the box. Hopefully the existing script will help you expand it to your own dataset. Study the assumptions() function
 * in particular.
 *
 * The script supports either extracting mentions, urls etc. directly from the tweet text, or from CSV fields if they have been provided.
 *
 * TODO: by default, perform a dry-run
 *
 */


if ($argc < 2) {
    print "Usage: import-auto-csv.php <CSV file> <bin name>\n";
    exit(1);
}

include_once('../config.php');
include_once('../common/functions.php');
include_once('../capture/common/functions.php');
include_once('../analysis/common/CSV.class.php');
ini_set('auto_detect_line_endings', true);
ini_set('memory_limit', '10G');
error_reporting(E_ALL);

$file = $argv[1];
$bin_name = $argv[2];

if (!file_exists($file)) {
    print "File not found: $file\n";
    exit(1);
}

create_bin($bin_name);

$fp = fopen($file, "r");

// Get default assumptions (i.e. CSV header to TCAT field mappings)

$mappings = assumptions();

// Parse header and auto-detect delimiter

$header = rtrim(fgets($fp));

// Auto-detect delimiter and whether or not fields are quoted

$fields_comma = explode(",", $header);
$fields_tab = explode("\t", $header);
$quoted = FALSE;
$delimiter = count($fields_tab) > $fields_comma ? "\t" : ",";
$fields = count($fields_tab) > $fields_comma ? $fields_tab : $fields_comma;

for ($i = 0; $i < count($fields); $i++) {
    if (strpos($fields[$i], '"') !== false) {
        $fields[$i] = str_replace('"', '', $fields[$i]);
        $quoted = TRUE;
    }
}

print "Parsing a CSV file with field delimiter: " . ( $delimiter == "\t" ? "TAB" : "COMMA" ) . "\n";
print "Parsing a CSV file with quoted fields: " . ( $quoted ? "YES" : "NO" ) . "\n";
print "Parsing a CSV file with nr. of fields: " . count($fields) . "\n";

// TODO: report on which fields are recognized!

// Start processing the body of our CSV 

$linenum = 0;
$warnings = 0;
$skipped = 0;
$tweetQueue = new TweetQueue();
while ($data = fgetcsv($fp, 0, $delimiter, $quoted ? '"' : '"')) {
    $linenum++;
    if (count($data) !== count($fields)) {
        print "WARNING: Data column count (" . count($data) . ") does not match header column count (" . count($fields) . ") at row $linenum - skipping tweet\n";
        $warnings++;
        $skipped++;
        continue;
    }
    
    // Let's get to work.

    $t = new Tweet();
    $t->withheld_in_countries = array();
    $t->places = array();

    // Is there expected tweet data?

    foreach ($mappings['tweet_data'] as $expected => $becomes) {
        for ($i = 0; $i < count($fields); $i++) {
            $field = $fields[$i];
            $value = $data[$i];
            if ($field == $expected) {
                if (is_array($becomes)) {
                    foreach ($becomes as $become) {
                        $t->$become = $value;
                    }
                } else {
                    if (preg_match("/:DATEPARSE$/", $becomes)) {
                        // Parse date when so requested and format it MySQL style
                        $dt = date_parse($value);
                        $value = sprintf("%d-%02d-%02d %02d:%02d:%02d", $dt['year'], $dt['month'], $dt['day'], $dt['hour'], $dt['minute'], $dt['second']);
                        $truefield = preg_replace("/:DATEPARSE$/", "", $becomes);
                        $t->$truefield = $value;
                    } else {
                        $t->$becomes = $value;
                    }
                }
            }
        }
    }

    // Is there expected hashtag data?

    $hashtags = array();
    foreach ($mappings['json_hashtag_array'] as $expected) {
        for ($i = 0; $i < count($fields); $i++) {
            $field = $fields[$i];
            $value = $data[$i];
            if ($field == $expected) {
                // Prerequisite for this datatype is a json array containing hashtags
                if (strlen($value) == 0 || $value == '[]') { continue; }
                // Yah! PHP as of PHP 7.3 still does not support reading (only writing) unescaped unicode in JSON,
                // which is what Twitter uses in their CSV data export. We'll do the decoding ourselves for now.
//                $decoded = json_decode($value, true);   // may not work given unescaped unicode, even though $value is valid JSON
                $decoded = explode(",", mb_substr(mb_substr($value, 1), 0, mb_strlen($value) - 2));
                if (is_array($decoded)) {
                    foreach ($decoded as $hashtag_text) {
                        $hashtag = new stdclass();
                        if (isset($t->id_str)) {
                            $hashtag->tweet_id = $t->id_str;
                        } elseif (isset($t->id)) {
                            $hashtag->tweet_id = $t->id;
                        }
                        if (isset($t->created_at)) {
                            $hashtag->created_at = $t->created_at;
                        }
                        if (isset($t->from_user_name)) {
                            $hashtag->from_user_name = $t->from_user_name;
                        }
                        if (isset($t->from_user_id)) {
                            $hashtag->from_user_id = $t->from_user_id;
                        }
                        $hashtag->text = $hashtag_text;
                        $hashtags[] = $hashtag;
                    }
                } else {
                    print "WARNING: Hashtag data in unexpected format for field '$field' at row $linenum\n";
                    $warnings++;
                }
            }
        }
    }
    if (count($hashtags) > 0) {
        $t->hashtags = $hashtags;
    } else if (preg_match("/#/", $t->text)) {
        // If we could not extract hashtags from the metadata, fall back on parsing the tweet text
        if (preg_match_all("/\B#([\p{L}\w\d_]+)/u", $t->text, $hash_matches)) {
            foreach ($hash_matches[1] as $hashtag_text) {
                $hashtag = new stdclass();
                if (isset($t->id_str)) {
                    $hashtag->tweet_id = $t->id_str;
                } elseif (isset($t->id)) {
                    $hashtag->tweet_id = $t->id;
                }
                if (isset($t->created_at)) {
                    $hashtag->created_at = $t->created_at;
                }
                if (isset($t->from_user_name)) {
                    $hashtag->from_user_name = $t->from_user_name;
                }
                if (isset($t->from_user_id)) {
                    $hashtag->from_user_id = $t->from_user_id;
                }
                $hashtag->text = $hashtag_text;
                $hashtags[] = $hashtag;
            }
            $t->hashtags = $hashtags;
        }
    }

    // Is there expected mentions data?

    // The numeric form

    $mentions = array();
    foreach ($mappings['json_mention_ids_array'] as $expected) {
        for ($i = 0; $i < count($fields); $i++) {
            $field = $fields[$i];
            $value = $data[$i];
            if ($field == $expected) {
                // Prerequisite for this datatype is a json array containing mentions
                if (strlen($value) == 0 || $value == '[]') { continue; }
                // Yah! PHP as of PHP 7.3 still does not support reading (only writing) unescaped unicode in JSON,
                // which is what Twitter uses in their CSV data export. We'll do the decoding ourselves for now.
//                $decoded = json_decode($value, true);   // may not work given unescaped unicode, even though $value is valid JSON
                $decoded = explode(",", mb_substr(mb_substr($value, 1), 0, mb_strlen($value) - 2));
                if (is_array($decoded)) {
                    foreach ($decoded as $mention_user_id) {
                        $mention = new stdclass();
                        if (isset($t->id_str)) {
                            $mention->tweet_id = $t->id_str;
                        } elseif (isset($t->id)) {
                            $mention->tweet_id = $t->id;
                        }
                        if (isset($t->created_at)) {
                            $mention->created_at = $t->created_at;
                        }
                        if (isset($t->from_user_name)) {
                            $mention->from_user_name = $t->from_user_name;
                        }
                        if (isset($t->from_user_id)) {
                            $mention->from_user_id = $t->from_user_id;
                        }
                        $mention->to_user = $mention_user_id;
                        $mention->to_user_id = $mention_user_id;
                        $mentions[] = $mention;
                    }
                } else {
                    print "WARNING: Mention data in unexpected format for field '$field' at row $linenum\n";
                    $warnings++;
                }
            }
        }
    }

    // The username form

    $mentions = array();
    foreach ($mappings['json_mentions_array'] as $expected) {
        for ($i = 0; $i < count($fields); $i++) {
            $field = $fields[$i];
            $value = $data[$i];
            if ($field == $expected) {
                // Prerequisite for this datatype is a json array containing mentions
                if (strlen($value) == 0 || $value == '[]') { continue; }
                // Yah! PHP as of PHP 7.3 still does not support reading (only writing) unescaped unicode in JSON,
                // which is what Twitter uses in their CSV data export. We'll do the decoding ourselves for now.
//                $decoded = json_decode($value, true);   // may not work given unescaped unicode, even though $value is valid JSON
                $decoded = explode(",", mb_substr(mb_substr($value, 1), 0, mb_strlen($value) - 2));
                if (is_array($decoded)) {
                    foreach ($decoded as $mention_user) {
                        $mention = new stdclass();
                        if (isset($t->id_str)) {
                            $mention->tweet_id = $t->id_str;
                        } elseif (isset($t->id)) {
                            $mention->tweet_id = $t->id;
                        }
                        if (isset($t->created_at)) {
                            $mention->created_at = $t->created_at;
                        }
                        if (isset($t->from_user_name)) {
                            $mention->from_user_name = $t->from_user_name;
                        }
                        if (isset($t->from_user_id)) {
                            $mention->from_user_id = $t->from_user_id;
                        }
                        $mention->to_user = $mention_user;
                        $mention->to_user_id = null;
                        $mentions[] = $mention;
                    }
                } else {
                    print "WARNING: Mention data in unexpected format for field '$field' at row $linenum\n";
                    $warnings++;
                }
            }
        }
    }

    if (count($mentions) > 0) {
        $t->user_mentions = $mentions;
    } else if (preg_match("/@/", $t->text)) {
        // If we could not extract mentions from the metadata, fall back on parsing the tweet text
        if (preg_match_all("/\B@([\p{L}\w\d_]+)/u", $t->text, $mention_matches)) {
            foreach ($mention_matches[1] as $mention) {
                $m = new stdclass();
                if (isset($t->id_str)) {
                    $m->tweet_id = $t->id_str;
                } elseif (isset($t->id)) {
                    $m->tweet_id = $t->id;
                }
                if (isset($t->created_at)) {
                    $m->created_at = $t->created_at;
                }
                if (isset($t->from_user_name)) {
                    $m->from_user_name = $t->from_user_name;
                }
                if (isset($t->from_user_id)) {
                    $m->from_user_id = $t->from_user_id;
                }
                $m->to_user = $mention;
                $m->to_user_id = $mention;
                $mentions[] = $m;
            }
            $t->user_mentions = $mentions;
        }
    }

    // Is there expected URLs data?

    $urls = array();
    foreach ($mappings['json_urls_array'] as $expected) {
        for ($i = 0; $i < count($fields); $i++) {
            $field = $fields[$i];
            $value = $data[$i];
            if ($field == $expected) {
                // Prerequisite for this datatype is a json array containing urls
                if (strlen($value) == 0 || $value == '[]') { continue; }
                // Yah! PHP as of PHP 7.3 still does not support reading (only writing) unescaped unicode in JSON,
                // which is what Twitter uses in their CSV data export. We'll do the decoding ourselves for now.
                // For URLs, we also handle the situation where an URL may contain a comma
                $rawlist = mb_substr(mb_substr($value, 1), 0, mb_strlen($value) - 2);
                $decoded = preg_split("/, /", $rawlist);
                if (is_array($decoded)) {
                    foreach ($decoded as $url_full) {
                        // NOTICE: we interpret the URL as being followed
                        $url = new stdclass();
                        if (isset($t->id_str)) {
                            $url->tweet_id = $t->id_str;
                        } elseif (isset($t->id)) {
                            $url->tweet_id = $t->id;
                        }
                        if (isset($t->created_at)) {
                            $url->created_at = $t->created_at;
                        }
                        if (isset($t->from_user_name)) {
                            $url->from_user_name = $t->from_user_name;
                        }
                        if (isset($t->from_user_id)) {
                            $url->from_user_id = $t->from_user_id;
                        }
                        $parse = parse_url($url_full);
                        if ($parse !== FALSE && array_key_exists('host', $parse)) {
                            $url->domain = $parse['host'];
                        }
                        $url->error_code = 200;
                        $url->url = $url_full;
                        $url->url_expanded = $url_full;
                        $url->url_followed = $url_full;
                        $urls[] = $url;
                    }
                } else {
                    print "WARNING: URL data in unexpected format for field '$field' at row $linenum (debug raw: $value)\n";
                    $warnings++;
                }
            }
        }
    }
    if (count($urls) > 0) {
        $t->urls = $urls;
    } else {
        // If we could not extract URLs from the metadata, fall back on parsing the tweet text
        if (preg_match_all("/\b(https?:\/\/[^\s]+)/u", $t->text, $url_matches)) {
            foreach ($url_matches[1] as $url_in_text) {
                $url = new stdclass();
                if (isset($t->id_str)) {
                    $url->tweet_id = $t->id_str;
                } elseif (isset($t->id)) {
                    $url->tweet_id = $t->id;
                }
                if (isset($t->created_at)) {
                    $url->created_at = $t->created_at;
                }
                if (isset($t->from_user_name)) {
                    $url->from_user_name = $t->from_user_name;
                }
                if (isset($t->from_user_id)) {
                    $url->from_user_id = $t->from_user_id;
                }
                $url->url = $url_in_text;
                $urls[] = $url;
            }
            $t->urls = $urls;
        }
    }

    // TODO: media, places, withheld

    // Validation

    if (!isset($t->id) || !isset($t->id_str) || empty($t->id) || empty($t->id_str)) {
        print "WARNING: Data does not contain recognized tweet ID at row $linenum\n";
        $warnings++;
        $skipped++;
        continue;
    }

    if ($t->retweet_id === 0) {
        $t->retweet_id = null;
    }

    if ($t->in_reply_to_status_id === 0) {
        $t->in_reply_to_status_id = null;
    }

    if (!$t->isInBin($bin_name)) {
        $tweetQueue->push($t, $bin_name);
    }

    if ($tweetQueue->length() >= 100) {
        $tweetQueue->insertDB();
    }

}
fclose($fp);

if ($tweetQueue->length() > 0) {
    $tweetQueue->insertDB();
}

print "Finished importing data with $warnings warnings. $skipped tweets were skipped.\n";

// Default assumptions
function assumptions() {

    return array(

        'tweet_data' =>

            array (
                'tweetid' => array( 'id', 'id_str' ),                           // Twitter Elections integrity dataset
                'tweet_id' => array( 'id', 'id_str' ),                          // NBC News Russian troll tweets dataset
                'userid' => 'from_user_id',                                     // Twitter Elections integrity dataset
                'user_id' => 'from_user_id',                                    // NBC News Russian troll tweets dataset
                'alt_external_id' => 'from_user_id',                            // Clemson University Russian trolls dataset
                'user_display_name' => 'from_user_realname',                    // Twitter Elections integrity dataset
                'user_screen_name' => 'from_user_name',                         // Twitter Elections integrity dataset
                'author' => 'from_user_name',                                   // Clemson University Russian trolls dataset
                'user_key' => 'from_user_name',                                 // NBC News Russian troll tweets dataset
                'user_reported_location' => 'location',                         // Twitter Elections integrity dataset
                'user_profile_description' => 'from_user_description',          // Twitter Elections integrity dataset
                'user_profile_url' => 'from_user_url',                          // Twitter Elections integrity dataset
                'follower_count' => 'from_user_followercount',                  // Twitter Elections integrity dataset
                'followers' => 'from_user_followercount',                       // Clemson University Russian trolls dataset
                'following_count' => 'from_user_friendcount',                   // Twitter Elections integrity dataset
                'following' => 'from_user_friendcount',                         // Clemson University Russian trolls dataset
                'updates' => 'from_user_tweetcount',                            // Clemson University Russian trolls dataset
                'account_creation_date' => 'from_user_created_at',              // Twitter Elections integrity dataset
                'account_language' => 'from_user_lang',                         // Twitter Elections integrity dataset
                'tweet_language' => 'lang',                                     // Twitter Elections integrity dataset
                'language' => 'lang',                                           // Clemson University Russian trolls dataset
                'tweet_text' => 'text',                                         // Twitter Elections integrity dataset
                'text' => 'text',                                               // NBC News Russian troll tweets dataset
                'content' => 'text',                                            // Clemson University Russian trolls dataset
                'tweet_time' => 'created_at',                                   // Twitter Elections integrity dataset
                'created_str' => 'created_at',                                  // NBC News Russian troll tweets dataset
                'publish_date' => 'created_at:DATEPARSE',                       // Clemson University Russian trolls dataset
                'tweet_client_name' => 'source',                                // Twitter Elections integrity dataset
                'source' => 'source',                                           // NBC News Russian troll tweets dataset
                'in_reply_to_tweetid' => 'in_reply_to_status_id',               // Twitter Elections integrity dataset
                'in_reply_to_status_id' => 'in_reply_to_status_id',             // NBC News Russian troll tweets dataset
                'in_reply_to_userid' => 'in_reply_to_user_id',                  // Twitter Elections integrity dataset
                'quoted_tweet_tweetid' => 'quoted_status_id',                   // Twitter Elections integrity dataset
                'retweet_userid' => 'retweet_id',                               // Twitter Elections integrity dataset
                'retweeted_status_id' => 'retweet_id',                          // NBC News Russian troll tweets dataset
                'latitude' => 'geo_lat',                                        // Twitter Elections integrity dataset
                'longitude' => 'geo_lon',                                       // Twitter Elections integrity dataset
                'like_count' => 'favorite_count',                               // Twitter Elections integrity dataset
                'favorite_count' => 'favorite_count',                           // NBC News Russian troll tweets dataset
                'retweet_count' => 'retweet_count'                              // Twitter Elections integrity dataset

            ),

        'json_hashtag_array' =>                                                 // An array containing Twitter hashtags (without the '#' symbol)

            array (

                'hashtags',                                                     // Twitter Elections integrity dataset

            ),

        'json_mentions_array' =>                                                // An array containing Twitter mentions (without the '@' symbol)

            array (

                'mentions',                                                     // NBC News Russian troll tweets dataset

            ),

        'json_mention_ids_array' =>                                             // An array containing Twitter user IDs (not handles!)

            array (

                'user_mentions',                                                // Twitter Elections integrity dataset

            ),

        'json_urls_array' =>

            array (

                 'urls',                                                        // Twitter Elections integrity dataset
                 'expanded_urls',                                               // NBC News Russian troll tweets dataset

            )
    );
}
