<?php
/**
 * Include api class 
 */
require_once('DmiTool.class.php');

/**
 * Create a new tool instance 
 */
$tool=new DmiTool('coword',DMI_TESTING);

/**
 * Set parameters 
 */
$time = strftime("%T",date('U'));
$tool->setParm('text_json',json_encode(
        array(
        	"test test test bla bla bla",
        	"test test test bla bla bla jo",
            "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut semper, nunc ac pretium scelerisque, erat massa ullamcorper felis, eget blandit nulla magna eget sapien. Morbi posuere vulputate tincidunt. Fusce rutrum risus quis nisi gravida fermentum. Sed vestibulum diam neque, quis posuere enim. Cras adipiscing consequat fringilla. Aliquam erat volutpat. Nunc lobortis hendrerit lorem. Nam at eros dolor. Morbi iaculis bibendum lacinia.",
            "Suspendisse vitae libero sed mauris lobortis pretium condimentum ac mi. Maecenas vehicula venenatis felis, id lobortis nisi egestas sed. Sed ipsum diam, condimentum at ullamcorper eu, vulputate non quam. Morbi quam tortor, sagittis id ultrices eu, convallis a purus. In porta, erat non faucibus vestibulum, leo quam fermentum massa, et scelerisque mi nibh quis odio. Vestibulum venenatis pulvinar lectus sit amet ullamcorper. Ut a cursus lectus. Suspendisse vitae urna id leo aliquet molestie eget sit amet ligula."
        )
));
$tool->setParm('options[]','urls,remove_stopwords');
$tool->setParm('stopwordList','english');
//$tool->setParm('max_document_frequency',90);
$tool->setParm('min_frequency',2);

/**
 * Invoke tool 
 */
$tool->execute("toolCallback");

/**
 * Callback, called upon return.
 * 
 * @param mixed $result either an array with field 'error' set, or the json_decoded result as received from the tool
 */
function toolCallback($result) {
	global $time;
	print ($time - strftime("%T",date('U')))." seconds passed<br><bR>";
    print_r($result);
}

?>
