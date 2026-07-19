<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$failures = array();
function check_admin_path($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

$helper = dirname(__DIR__).'/includes/admin_path.php';
$media_helper = dirname(__DIR__).'/includes/media_path.php';
check_admin_path(is_file($helper), 'admin path helper is missing');
if(is_file($helper)){
    define('IN_CRONLITE', true);
    require $helper;
}
check_admin_path(is_file($media_helper), 'media path helper is missing');
if(is_file($media_helper)) require $media_helper;

$temp_root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'qifu_admin_path_'.bin2hex(random_bytes(8));
$default_dir = $temp_root.DIRECTORY_SEPARATOR.'admin';
$custom_dir = $temp_root.DIRECTORY_SEPARATOR.'secure_console';
mkdir($temp_root, 0700, true);
mkdir($default_dir, 0700, true);
$markers = array('index.php','login.php','head.php','saiadmin-skin.css');
foreach($markers as $marker) file_put_contents($default_dir.DIRECTORY_SEPARATOR.$marker, 'test');

try {
    if(function_exists('qifu_admin_directory_name')){
        check_admin_path(qifu_admin_directory_name($temp_root, true) === 'admin', 'default admin directory was not detected');
        rename($default_dir, $custom_dir);
        check_admin_path(qifu_admin_directory_name($temp_root, true) === 'secure_console', 'renamed admin directory was not detected');
        check_admin_path(qifu_site_base_path('/nav/secure_console', 'secure_console') === '/nav', 'renamed admin directory was not removed from the site base path');
        check_admin_path(qifu_site_base_path('/nav/admin/secure_console', 'secure_console') === '/nav', 'legacy admin directory remained beneath the renamed admin path');
        check_admin_path(qifu_admin_request_is_admin('/nav/secure_console/set.php', $temp_root, 'secure_console'), 'renamed admin request was not identified for CSRF protection');
        check_admin_path(!qifu_admin_request_is_admin('/nav/index.php', $temp_root, 'secure_console'), 'frontend request was incorrectly identified as admin');
        check_admin_path(qifu_admin_url_segment('secure console') === 'secure%20console', 'admin URL segment was not encoded');
    }

    if(function_exists('qifu_media_normalize_url')){
        $root_url = 'https://example.com/nav/';
        check_admin_path(qifu_media_upload_url('images/ad/banner.png', $root_url) === '/nav/images/ad/banner.png', 'new uploads are not stored as site-root paths');
        check_admin_path(qifu_media_normalize_url('https://example.com/nav/admin/images/ad/banner.png', $root_url) === '/nav/images/ad/banner.png', 'legacy /admin image URL was not repaired');
        check_admin_path(qifu_media_normalize_url('/nav/admin/secure_console/images/logo/logo.png', $root_url) === '/nav/images/logo/logo.png', 'overlapping old and renamed admin directories were not repaired');
        check_admin_path(qifu_media_normalize_url('../images/bg/custom.jpg', $root_url) === '/nav/images/bg/custom.jpg', 'relative backend image URL was not repaired');
        check_admin_path(qifu_media_normalize_url('https://cdn.example.net/admin/images/ad/banner.png', $root_url) === 'https://cdn.example.net/admin/images/ad/banner.png', 'external image URL was incorrectly rewritten');
    }

    $set_source = file_get_contents(dirname(__DIR__).'/admin/set.php');
    check_admin_path(strpos($set_source, "\$siteurl.'images/") === false, 'settings upload still prefixes images with the current admin URL');
    check_admin_path(strpos($set_source, 'qifu_media_upload_url') !== false, 'settings uploads do not use the shared media path helper');
    $ad_source = file_get_contents(dirname(__DIR__).'/admin/ad.php');
    $ajax_upload_source = file_get_contents(dirname(__DIR__).'/admin/ajax_upload_ad.php');
    $router_source = file_get_contents(dirname(__DIR__).'/router.php');
    check_admin_path(strpos($ad_source, 'qifu_media_upload_url') !== false, 'advertisement form uploads do not use the shared media path helper');
    check_admin_path(strpos($ajax_upload_source, 'qifu_media_upload_url') !== false, 'AJAX advertisement uploads do not use the shared media path helper');
    check_admin_path(strpos($router_source, "substr(\$raw_path, -1) !== '/'") !== false, 'local demo router does not canonicalize directory URLs with a trailing slash');
    check_admin_path(strpos($router_source, "header('Location: '.\$location, true, 302)") !== false, 'local demo router does not redirect directory requests before rendering the application shell');

    define('QIFU_INSTALL_CONTEXT', true);
    require dirname(__DIR__).'/install/helpers.php';
    $lock = $temp_root.DIRECTORY_SEPARATOR.'install.lock';
    $completion = qifu_install_completion($lock, 'test account', 'secure_console');
    check_admin_path($completion['success'] === true, 'installation completion failed');
    check_admin_path(strpos($completion['html'], '../secure_console/') !== false, 'completion page did not link to the renamed admin directory');
    check_admin_path(strpos($completion['html'], '../admin/') === false, 'completion page still linked to the default admin directory');
    check_admin_path(strpos($completion['html'], '请不要使用 <code>/admin</code>') !== false, 'completion page omitted the admin path security warning');
    if(is_file($lock)) unlink($lock);
} finally {
    $active_dir = is_dir($custom_dir) ? $custom_dir : $default_dir;
    foreach($markers as $marker){
        $path = $active_dir.DIRECTORY_SEPARATOR.$marker;
        if(is_file($path)) unlink($path);
    }
    if(is_dir($active_dir)) rmdir($active_dir);
    if(is_dir($temp_root)) rmdir($temp_root);
}

if($failures){
    fwrite(STDERR, "Admin path tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin path tests passed.\n";
?>
