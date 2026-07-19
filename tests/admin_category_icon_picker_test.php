<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$component = file_get_contents($root.'admin-ui-source/src/views/qifu/admin-page.vue');
$iconsSource = file_get_contents($root.'admin-ui-source/src/views/qifu/qifu-category-icons.ts');
$failures = array();

function check_category_icon_picker($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

preg_match_all("/\{\s*icon:\s*'([^']+)'\s*,\s*label:\s*'([^']+)'\s*\}/u", $iconsSource, $matches, PREG_SET_ORDER);
$icons = array_map(static function($match){ return $match[1]; }, $matches);

check_category_icon_picker(count($icons) === 100, 'the category icon library does not contain exactly 100 icons');
check_category_icon_picker(count(array_unique($icons)) === 100, 'the category icon library contains duplicate icons');
check_category_icon_picker(strpos($component, "import { qifuCategoryIcons }") !== false, 'the category page does not load the shared icon library');
check_category_icon_picker(strpos($component, 'v-model="categoryIconKeyword"') !== false, 'the icon picker has no search control');
check_category_icon_picker(strpos($component, 'v-for="item in filteredCategoryIcons"') !== false, 'the icon picker does not render filtered choices');
check_category_icon_picker(strpos($component, '@click="chooseCategoryIcon(item.icon)"') !== false, 'icon choices do not update the category form');
check_category_icon_picker((bool)preg_match('/v-model="categoryForm\.icon"\s+maxlength="20"/u', $component), 'customers cannot enter a custom category icon');
check_category_icon_picker(strpos($component, '也可以直接输入自己的 Emoji、中文或符号') !== false, 'custom icon guidance is missing');

if($failures){
    fwrite(STDERR, "Admin category icon picker tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin category icon picker tests passed.\n";
