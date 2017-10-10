<?php
include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../common/constants.php';
include_once __DIR__ . '/../../common/functions.php';

if (!is_admin())
    die("Sorry, access denied. Your username does not match the ADMIN user defined in the config.php file.");
?>

<div class='if_row_header' style='height:200px;'>Phrases to track:</div>
<div class='if_row_content'>
    <input id="newbin_phrases" name="newbin_phrases" type="text"/><br>
    Here you can specify a list of <a href='https://dev.twitter.com/docs/streaming-apis/parameters#track' target='_blank'>tracking criteria</a> consisting of single or multiple keyword queries, hashtags, and specific phrases. Each query should be separated by a comma. If you want to track a literal phrase, encapsulate it in single quotes (').<br>
    <br/>
    DMI-TCAT allows for three types of 'track' queries:
    <ol style='margin-top:0px; list-style-position: inside; list'>
        <li> a single word/hashtag. Consider that Twitter does not do partial matching on words, i.e. [twitter] will get tweets with [twitter], [#twitter] but not [twitteraddiction]
        <li> two or more words: works like an AND operator, i.e. [global warming] will find tweets that have both [global] and [warming] in any position in the tweet, e.g. "life is global but not warming"</li>
        <li> exact phrases: ['global warming'] will get only tweets with the exact phrase. Beware, however that due to how the streaming API works, tweets are captured in the same way as in 2, but tweets that do not match the exact phrase are thrown away. This means that you will request many more tweets from the Twitter API than you will see in your query bin - thus increasing the possibility that you will hit a <a href='https://dev.twitter.com/docs/faq#6861' target='_blank'>rate limit</a>. E.g. if you specify a query like ['are we'] all tweets matching both [are] and [we] are retrieved, while DMI-TCAT only retains those with the exact phrase ['are we'].</li>
    </ol>

    You can track a maximum of 400 queries at the same time (for all query bins combined) and the total volume should never exceed 1% of global Twitter volume, at any specific moment in time.
    <br/>
    Example bin: globalwarming,global warming,'climate change'
</div>
