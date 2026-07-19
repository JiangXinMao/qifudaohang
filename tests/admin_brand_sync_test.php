<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$api = file_get_contents($root.'admin/api.php');
$settings = file_get_contents($root.'admin/set.php');
$client = file_get_contents($root.'admin-ui-source/src/api/qifu.ts');
$main = file_get_contents($root.'admin-ui-source/src/main.ts');
$logo = file_get_contents($root.'admin-ui-source/src/components/core/base/art-logo/index.vue');
$sidebar = file_get_contents($root.'admin-ui-source/src/components/core/layouts/art-menus/art-sidebar-menu/index.vue');
$adminEntry = file_get_contents($root.'admin/index.php');
$sourceShell = file_get_contents($root.'admin-ui-source/index.html');
$builtShell = file_get_contents($root.'admin/ui/index.html');
$failures = array();

function check_brand_sync($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_brand_sync(strpos($api, "if(\$action === 'brand')") !== false, 'public brand endpoint is missing');
check_brand_sync(strpos($api, "if(\$action === 'logo_upload')") !== false, 'authenticated logo upload endpoint is missing');
check_brand_sync(strpos($api, "qifu_safe_image_upload(\$_FILES['file']") !== false, 'logo upload does not use the safe image validator');
check_brand_sync(strpos($settings, "action=logo_upload") !== false, 'logo picker does not upload immediately');
check_brand_sync(strpos($settings, 'logoUrlInput.value = json.data.url') !== false, 'uploaded logo URL is not written back to the address field');
check_brand_sync(strpos($settings, "type: 'qifu-brand-updated'") !== false, 'saved settings do not notify the Art shell');
check_brand_sync(strpos($client, 'export function qifuBrand') !== false, 'brand API client is missing');
check_brand_sync(strpos($main, 'initializeQifuBrand') !== false, 'global brand synchronization is not initialized');
check_brand_sync(strpos($logo, 'brand.logo') !== false, 'Art logo does not use the saved logo');
check_brand_sync(strpos($sidebar, 'brand.name') !== false, 'Art sidebar does not use the saved site name');
check_brand_sync(strpos($adminEntry, "\$conf['sitename']") !== false, 'the first admin response does not read the saved site name');
check_brand_sync(strpos($adminEntry, "preg_replace_callback(") !== false && strpos($adminEntry, "'#<title>.*?</title>#is'") !== false, 'the first admin response does not replace the static shell title');
check_brand_sync(strpos($sourceShell, '<title>Art Design Pro</title>') === false, 'the source admin shell still exposes the template title');
check_brand_sync(strpos($builtShell, '<title>Art Design Pro</title>') === false, 'the built admin shell still exposes the template title');
$entryScript = '';
if(preg_match('#<script[^>]+src="([^"]+\.js)"#i', $builtShell, $entryMatch)) $entryScript = basename($entryMatch[1]);
check_brand_sync($entryScript !== '', 'the built admin shell is missing its module entry script');
check_brand_sync($entryScript !== '' && is_file($root.'admin/assets/'.$entryScript), 'the built admin shell points to a missing module entry script');

if($failures){
    fwrite(STDERR, "Admin brand sync tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin brand sync tests passed.\n";
