<?php
define('DH_JSON_RESPONSE', true);
include __DIR__ . "/includes/common.php";

header('Content-Type: application/json; charset=utf-8');

$ad_id = isset($_POST['ad_id']) ? intval($_POST['ad_id']) : 0;
$type = isset($_POST['type']) ? trim($_POST['type']) : 'view';
$field = $type === 'click' ? 'clicks' : 'views';

if(qifu_ad_track($ad_id, $field)){
    echo json_encode(array('code' => 1), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(array('code' => 0), JSON_UNESCAPED_UNICODE);
?>
