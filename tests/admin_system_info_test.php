<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$routes = file_get_contents($root.'admin-ui-source/src/router/routes/asyncRoutes.ts');
$view = file_get_contents($root.'admin-ui-source/src/views/qifu/admin-page.vue');
$client = file_get_contents($root.'admin-ui-source/src/api/qifu.ts');
$api = file_get_contents($root.'admin/api.php');
$failures = array();

function check_system_info($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_system_info(strpos($routes, "title: '检查更新'") !== false, 'update menu title is missing');
check_system_info(strpos($routes, "path: 'system-info'") !== false && strpos($routes, "name: 'SystemInfo'") !== false, 'system information route is missing');
check_system_info(strpos($routes, "title: '系统信息'") !== false, 'system information menu title is missing');
check_system_info(strpos($view, "pageName === 'SystemInfo'") !== false && strpos($view, 'title="系统信息"') !== false, 'system information page is missing');
check_system_info(strpos($view, 'systemInfo.sodiumReady') !== false && strpos($view, 'systemInfo.zipReady') !== false, 'update runtime checks are missing');
check_system_info(strpos($client, "action=system_info") !== false, 'system information API client is missing');
check_system_info(strpos($api, 'function qifu_api_system_info()') !== false && strpos($api, "if(\$action === 'system_info')") !== false, 'system information API endpoint is missing');
check_system_info(strpos($api, "'sodiumReady'") !== false && strpos($api, "'zipReady'") !== false, 'system information extension status is missing');

if($failures){
    fwrite(STDERR, "Admin system information tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin system information tests passed.\n";
