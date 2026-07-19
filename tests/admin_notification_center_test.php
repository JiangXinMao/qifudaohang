<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$api = file_get_contents($root.'admin/api.php');
$client = file_get_contents($root.'admin-ui-source/src/api/qifu.ts');
$panel = file_get_contents($root.'admin-ui-source/src/components/core/layouts/art-notification/index.vue');
$header = file_get_contents($root.'admin-ui-source/src/components/core/layouts/art-header-bar/index.vue');
$telemetry = file_get_contents($root.'includes/telemetry.php');
$common = file_get_contents($root.'includes/common.php');
$home = file_get_contents($root.'index.php');
$click = file_get_contents($root.'ajax_track.php');
$failures = array();

function check_notification_center($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_notification_center(strpos($api, "if(\$action === 'notifications')") !== false, 'authenticated notification endpoint is missing');
check_notification_center(strpos($api, 'qifu_telemetry_remote()') !== false, 'verified remote notification source is missing');
check_notification_center(strpos($api, 'remote-announcement-') !== false, 'remote announcement mapping is missing');
check_notification_center(strpos($api, "'announcement', \$title") !== false, 'remote announcement category is missing');
check_notification_center(strpos($api, "'category', 'update'") !== false || strpos($api, "'update', \$update_title") !== false, 'remote update notification category is missing');
check_notification_center(strpos($api, 'ping_checked_at>0 AND ping_status=0') !== false, 'offline site notification query is missing');
check_notification_center(strpos($api, 'link-pending-') === false, 'pending link reminders must not appear in the notification center');
check_notification_center(strpos($api, 'security-login-') === false, 'security reminders must not appear in the notification center');
check_notification_center(strpos($api, 'backup-none') === false, 'backup reminders must not appear in the notification center');
check_notification_center(strpos($api, 'ad-expiring-') === false, 'advertising reminders must not appear in the notification center');
check_notification_center(strpos($api, "'update-'.\$latest_version") === false, 'local update reminders must not replace verified remote updates');

check_notification_center(strpos($client, 'export interface QifuNotification') !== false, 'notification API contract is missing');
check_notification_center(strpos($client, "url: './api.php?action=notifications'") !== false, 'notification API client is missing');
check_notification_center(strpos($panel, 'qifu-admin-read-notifications-v1') !== false, 'read state persistence is missing');
check_notification_center(strpos($panel, 'setInterval(() => void loadNotifications(), 300000)') !== false, 'five-minute notification refresh is missing');
check_notification_center(strpos($panel, 'await router.push(item.route)') !== false, 'notification route navigation is missing');
check_notification_center(strpos($panel, '全部已读') !== false, 'mark-all-read action is missing');
check_notification_center(strpos($panel, "announcement: { label: '远程公告'") !== false, 'remote announcement presentation is missing');
check_notification_center(strpos($panel, '站点、安全、备份与广告状态均正常') === false, 'legacy notification categories are still described in the empty state');
check_notification_center(strpos($header, 'noticeCount > 99') !== false, 'real notification count badge is missing');
check_notification_center(strpos($header, 'size-1.5 !bg-danger rounded-full') === false, 'fixed notification dot is still present');
check_notification_center(strpos($common, "qifu_telemetry_track_daily('app_ready'") !== false, 'daily app-ready telemetry throttle is missing');
check_notification_center(strpos($home, "qifu_telemetry_track_daily('website_view'") !== false, 'website-view feedback is not limited to once per day');
check_notification_center(strpos($click, "qifu_telemetry_track_daily('site_click'") !== false, 'site-click feedback is not limited to once per day');
check_notification_center(strpos($telemetry, "QIFU_TELEMETRY_FEEDBACK_INTERVAL', 86400") !== false, 'daily telemetry feedback interval is missing');
check_notification_center(strpos($telemetry, "QIFU_REMOTE_QUERY_INTERVAL', 300") !== false, 'five-minute remote query interval is missing');
check_notification_center(strpos($telemetry, "remote.lock") !== false, 'remote announcement polling lock is missing');
check_notification_center(substr_count($telemetry, '< QIFU_REMOTE_QUERY_INTERVAL') >= 2, 'five-minute remote announcement and update cache is missing');

if($failures){
    fwrite(STDERR, "Admin notification center tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin notification center tests passed.\n";
