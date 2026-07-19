<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

error_reporting(0);
ini_set('display_errors', 0);

define('DH_JSON_RESPONSE', true);
ob_start();
include __DIR__.'/includes/common.php';
ob_end_clean();
require_once SYSTEM_ROOT.'site_meta.php';

header('Content-Type: application/json; charset=utf-8');

function site_meta_json($code, $msg, $data = array()){
    echo json_encode(array('code' => $code, 'msg' => $msg, 'data' => $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST') site_meta_json(0, '请求方式错误');
if(!qifu_csrf_valid(isset($_POST['_csrf']) ? $_POST['_csrf'] : '')){
    http_response_code(403);
    site_meta_json(0, '安全令牌已失效，请刷新页面后重试');
}

$now = time();
$requests = isset($_SESSION['qifu_site_meta_requests']) && is_array($_SESSION['qifu_site_meta_requests']) ? $_SESSION['qifu_site_meta_requests'] : array();
$requests = array_values(array_filter($requests, function($timestamp) use ($now){ return intval($timestamp) >= $now - 60; }));
if(count($requests) >= 10) site_meta_json(0, '自动获取过于频繁，请稍后再试');
$requests[] = $now;
$_SESSION['qifu_site_meta_requests'] = $requests;

$url = isset($_POST['url']) ? trim((string)$_POST['url']) : '';
$error = '';
$meta = qifu_site_meta_fetch($url, $error);
if($meta === false) site_meta_json(0, $error !== '' ? $error : '无法自动获取网站信息');
site_meta_json(1, '网站信息获取成功', $meta);
?>
