<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$failures = array();

function check_site_icon($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

$index_source = file_get_contents($root.'index.php');
$helper = $root.'includes/site_icon.php';
$endpoint = $root.'site_icon.php';

check_site_icon(strpos($index_source, 'site-icon-fallback') !== false, 'site cards have no immediately visible icon fallback');
check_site_icon(strpos($index_source, 'data-icon-sources') !== false, 'site icons do not expose multiple source candidates');
check_site_icon(strpos($index_source, 'qifuSiteIconNext') !== false, 'site icons do not switch quickly after a slow or failed source');
check_site_icon(strpos($index_source, 'qifuSiteIconArm') !== false, 'site icons have no slow-source timeout');
check_site_icon(strpos($index_source, "this.outerHTML=") === false, 'failed site icons still replace the image with delayed inline HTML');
check_site_icon(strpos($index_source, 'rel="preconnect" href="https://favicon.im"') !== false, 'favicon service connection is not warmed up');
check_site_icon(is_file($helper), 'site icon source helper is missing');
check_site_icon(is_file($endpoint), 'same-origin site icon cache endpoint is missing');

if(is_file($helper)){
    define('IN_CRONLITE', true);
    define('ROOT', $root);
    define('SYSTEM_ROOT', $root.'includes/');
    require $root.'includes/security.php';
    require $root.'includes/site_meta.php';
    require $helper;
    $sources = qifu_site_icon_sources('https://github.com/openai/example');
    check_site_icon(isset($sources[0]) && $sources[0] === 'https://github.com/favicon.ico', 'the site origin favicon is not the first candidate');
    check_site_icon(isset($sources[1]) && strpos($sources[1], 'site_icon.php?url=') === 0, 'the same-origin cached icon is not the second candidate');
    check_site_icon(count($sources) >= 5, 'fewer than five icon source candidates are available');
    check_site_icon(isset($sources[3]) && strpos($sources[3], 'site_icon.php?url=') === 0 && strpos($sources[3], 'retry=1') !== false, 'the warmed local icon cache is not retried');
    check_site_icon(strpos(implode("\n", $sources), 'https://favicon.im/github.com') !== false, 'favicon.im fallback is missing');
    check_site_icon(strpos(implode("\n", $sources), 'https://www.google.com/s2/favicons') !== false, 'secondary favicon service is missing');
    check_site_icon(qifu_site_icon_sources('javascript:alert(1)') === array(), 'unsafe site URL produced icon requests');
    check_site_icon(function_exists('qifu_site_icon_discover_url'), 'site icon discovery helper is missing');
    if(function_exists('qifu_site_icon_discover_url')){
        $discovered = qifu_site_icon_discover_url(
            '<html><head><link rel="icon" sizes="64x64" href="/assets/site-icon.png"></head></html>',
            'https://example.com/products/page'
        );
        check_site_icon($discovered === 'https://example.com/assets/site-icon.png', 'declared site icon URL was not resolved correctly');
    }
}

if($failures){
    fwrite(STDERR, "Site icon tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Site icon tests passed.\n";
