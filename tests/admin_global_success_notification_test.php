<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$head = file_get_contents($root.'admin/head.php');
$css = file_get_contents($root.'admin/art-detail-pages.css');
$layout = file_get_contents($root.'admin-ui-source/src/views/index/index.vue');
$component = file_get_contents($root.'admin-ui-source/src/components/business/qifu-success-toast/index.vue');
$adminPage = file_get_contents($root.'admin-ui-source/src/views/qifu/admin-page.vue');
$failures = array();

function check_success_notification($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_success_notification(strpos($head, 'window.qifuNotifySuccess') !== false, 'legacy global success notifier is missing');
check_success_notification(strpos($head, '.alert.alert-success, .ad-toast.success') !== false, 'legacy success alerts are not promoted');
check_success_notification(strpos($head, "type: 'qifu-admin-success'") !== false, 'iframe success bridge is missing');
check_success_notification(strpos($css, '.qf-success-toast') !== false, 'legacy C-style success toast CSS is missing');
check_success_notification(strpos($layout, '<QifuSuccessToast />') !== false, 'Vue global success component is not mounted');
check_success_notification(strpos($component, "event.data.type !== 'qifu-admin-success'") !== false, 'Vue iframe message listener is missing');
check_success_notification(strpos($component, 'event.origin !== window.location.origin') !== false, 'iframe message origin validation is missing');
check_success_notification(strpos($component, "path.startsWith('/content/')") !== false, 'content pages do not suppress success notifications');
check_success_notification(strpos($component, "path === '/outside/iframe/legacy-ads'") !== false, 'legacy ad page does not suppress success notifications');
check_success_notification(strpos($component, 'if (isSilentContentRoute())') !== false, 'success notification route guard is missing');
check_success_notification(strpos($component, 'data-success-style="G"') !== false, 'global success notification is not using the selected G style');
check_success_notification(strpos($component, 'transform: translateX(-50%)') !== false, 'G-style success notification is not centered');
check_success_notification(substr_count($adminPage, 'qifuSuccess(') >= 10, 'Vue success actions are not using the global notification');

if($failures){
    fwrite(STDERR, "Global success notification tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Global success notification tests passed.\n";
