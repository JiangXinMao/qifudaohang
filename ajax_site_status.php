<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

error_reporting(0);
ini_set('display_errors', 0);

define('DH_JSON_RESPONSE', true);
ob_start();
include __DIR__ . "/includes/common.php";
include __DIR__ . "/includes/site_status.php";
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
//
function status_json($online, $http_code = 0, $latency = 0, $reason = '')
{
    echo json_encode(array(
        'code' => 1,
        'online' => $online ? 1 : 0,
        'http_code' => intval($http_code),
        'latency' => intval($latency),
        'reason' => $reason
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

dh_site_status_ensure_columns();
// 判断时间 接口状态
if ($id > 0) {
    $row = $DB->prepared_row('SELECT id,url,ping_status,ping_http_code,ping_latency,ping_checked_at FROM web_dh WHERE id=? LIMIT 1', array($id));
    if (!$row) status_json(false);
    if(intval($row['ping_checked_at']) > time() - 60){
        status_json(intval($row['ping_status']) === 1, $row['ping_http_code'], $row['ping_latency'], 'cached');
    }
    $result = dh_site_status_update_row($row['id'], $row['url']);
    status_json(!empty($result['online']), $result['http_code'], $result['latency'], $result['reason']);
}

http_response_code(400);
status_json(false, 0, 0, 'invalid_site');
?>
