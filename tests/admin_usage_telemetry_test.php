<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$api = file_get_contents($root.'admin/api.php');
$client = file_get_contents($root.'admin-ui-source/src/api/qifu.ts');
$after_each = file_get_contents($root.'admin-ui-source/src/router/guards/afterEach.ts');
$sidebar = file_get_contents($root.'admin-ui-source/src/components/core/layouts/art-menus/art-sidebar-menu/widget/SidebarSubmenu.vue');
$failures = array();

function check_admin_usage_telemetry($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

$events = array(
    'admin_dashboard_open',
    'admin_menu_workbench',
    'admin_menu_system_config',
    'admin_menu_content_operations',
    'admin_menu_maintenance_tools',
    'admin_menu_about_system'
);
foreach($events as $event){
    check_admin_usage_telemetry(strpos($api, "'$event'") !== false, 'missing API allowlist event: '.$event);
}
check_admin_usage_telemetry(strpos($api, "if(\$action === 'admin_usage_track')") !== false, 'admin usage endpoint is missing');
check_admin_usage_telemetry(strpos($api, 'qifu_api_require_write();') !== false, 'admin usage endpoint must require an authenticated CSRF-protected request');
check_admin_usage_telemetry(strpos($api, "qifu_telemetry_track(\$event, true, array('surface'=>'admin'))") !== false, 'admin usage is not sent through the anonymous telemetry channel');
check_admin_usage_telemetry(strpos($client, 'export function qifuTrackAdminUsage') !== false, 'frontend admin usage client is missing');
check_admin_usage_telemetry(strpos($after_each, "qifuTrackAdminUsage('admin_dashboard_open')") !== false, 'opening the admin home page is not tracked');
check_admin_usage_telemetry(strpos($sidebar, 'const primaryMenuEvents') !== false && strpos($sidebar, '@click="trackPrimaryMenu(item)"') !== false, 'primary sidebar menu tracking is missing');

if($failures){
    fwrite(STDERR, "Admin usage telemetry tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin usage telemetry tests passed.\n";
