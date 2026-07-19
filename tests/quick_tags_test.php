<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

define('IN_CRONLITE', true);
require dirname(__DIR__).'/includes/quick_tags.php';

$failures = array();
function check_quick_tags($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

$defaults = qifu_quick_tags_from_config(array());
check_quick_tags(count($defaults) === 4, 'missing config did not return four defaults');

$invalid = 0;
$safe = qifu_quick_tags_from_input(
    array('<b>开发资源</b>', '危险链接', '', '控制'.chr(1).'字符'),
    array('https://example.com/tools', 'javascript:alert(1)', '', 'http://example.org'),
    $invalid
);
check_quick_tags(count($safe) === 2, 'valid and invalid input rows were not filtered correctly');
check_quick_tags($safe[0]['name'] === '开发资源', 'HTML was not stripped from the tag name');
check_quick_tags($safe[1]['name'] === '控制字符', 'control characters were not stripped from the tag name');
check_quick_tags($invalid === 1, 'invalid row count is incorrect');
check_quick_tags(qifu_quick_tag_name("\xC3\x28") === '', 'invalid UTF-8 was not rejected');

$many_names = array_fill(0, 14, '标签');
$many_urls = array_fill(0, 14, 'https://example.com');
$limited = qifu_quick_tags_from_input($many_names, $many_urls, $invalid);
check_quick_tags(count($limited) === 12, 'tag count was not limited to 12');
check_quick_tags($invalid === 2, 'overflow rows were not reported as invalid');

$empty = qifu_quick_tags_from_config(array('quick_tags'=>'[]'));
check_quick_tags($empty === array(), 'an explicitly empty tag list did not remain empty');

$round_trip = qifu_quick_tags_from_config(array('quick_tags'=>qifu_quick_tags_encode($safe)));
check_quick_tags($round_trip === $safe, 'encoded tags did not round-trip');

$front_source = file_get_contents(dirname(__DIR__).'/index.php');
$tag_position = strpos($front_source, "<?php if(\$show_tags!='0' && !empty(\$quick_tags)): ?>");
$ad_position = strpos($front_source, '<?php if($ad_below_show): ?>');
check_quick_tags($tag_position !== false && $ad_position !== false && $tag_position < $ad_position, 'quick tags are not rendered before the below-search ad component');

if($failures){
    fwrite(STDERR, "Quick tag tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Quick tag tests passed.\n";
?>
