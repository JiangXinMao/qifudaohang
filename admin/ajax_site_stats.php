<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

include __DIR__ . "/../includes/common.php";
header('Content-Type: application/json; charset=utf-8');
if($islogin !== 1){
    http_response_code(401);
    echo json_encode(array('code'=>0,'msg'=>'请先登录后台'), JSON_UNESCAPED_UNICODE);
    exit;
}

$date = isset($_POST['date']) ? trim($_POST['date']) : '';
if(empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)){
    echo json_encode([]);
    exit;
}

// 检查站点统计表是否存在
$chk = $DB->get_row("SHOW TABLES LIKE 'web_site_stats'");
if(empty($chk)){
    echo json_encode([]);
    exit;
}

// 查询该日期各站点点击量（按站点聚合）
$rows = $DB->prepared_results("
    SELECT w.id, w.name, w.url, w.category, s.views
    FROM web_site_stats s
    INNER JOIN web_dh w ON w.id = s.site_id
    WHERE s.stat_date = ? AND s.views > 0
    ORDER BY s.views DESC, w.id ASC
", array($date));

echo json_encode($rows ? $rows : [], JSON_UNESCAPED_UNICODE);
?>
