<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

include __DIR__ . "/includes/common.php";
require_once SYSTEM_ROOT.'site_stats.php';
header('Content-Type: application/json; charset=utf-8');

// 老版本升级上来时自动补齐站点总点击字段
$click_col = $DB->query("SHOW COLUMNS FROM web_dh LIKE 'clicks'");
if(!$DB->fetch($click_col)){
    $DB->query("ALTER TABLE web_dh ADD COLUMN clicks int(11) NOT NULL DEFAULT 0");
}

$site_id = isset($_POST['site_id']) ? intval($_POST['site_id']) : 0;
$type = isset($_POST['type']) ? trim((string)$_POST['type']) : 'click';

if($type === 'impression'){
    $tracked = qifu_site_stats_track_impressions(isset($_POST['site_ids']) ? $_POST['site_ids'] : array());
    echo json_encode(array('code'=>1, 'tracked'=>$tracked));
    exit;
}

if($site_id > 0){
    if(qifu_site_stats_track_click($site_id)){
        qifu_telemetry_track_daily('site_click', true, ['site_id' => $site_id]);
        echo json_encode(['code'=>1]);
        exit;
    }
}

echo json_encode(['code'=>0]);
?>
