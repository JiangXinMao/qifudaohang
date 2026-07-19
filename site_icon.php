<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

error_reporting(0);
ini_set('display_errors', 0);
define('IN_CRONLITE', true);
define('ROOT', __DIR__.DIRECTORY_SEPARATOR);
define('SYSTEM_ROOT', ROOT.'includes'.DIRECTORY_SEPARATOR);
require SYSTEM_ROOT.'security.php';
require SYSTEM_ROOT.'site_meta.php';
require SYSTEM_ROOT.'site_icon.php';

if($_SERVER['REQUEST_METHOD'] !== 'GET'){
    http_response_code(405);
    exit;
}
$url = isset($_GET['url']) ? trim((string)$_GET['url']) : '';
if($url === '' || strlen($url) > 2048){
    http_response_code(204);
    exit;
}
$error = '';
$icon = qifu_site_icon_cached($url, $error);
if($icon === false){
    header('Cache-Control: public, max-age=300');
    http_response_code(204);
    exit;
}
$etag = '"'.sha1($icon['data']).'"';
if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string)$_SERVER['HTTP_IF_NONE_MATCH']) === $etag){
    header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
    header('ETag: '.$etag);
    http_response_code(304);
    exit;
}
header('Content-Type: '.$icon['mime']);
header('Content-Length: '.strlen($icon['data']));
header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
header('ETag: '.$etag);
header('X-Content-Type-Options: nosniff');
echo $icon['data'];
?>
