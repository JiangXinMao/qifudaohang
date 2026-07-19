<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

include __DIR__.'/includes/common.php';
include_once SYSTEM_ROOT.'online_stats.php';

qifu_online_stats_track_visit();

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
?>
