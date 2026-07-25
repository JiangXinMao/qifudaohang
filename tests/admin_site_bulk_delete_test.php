<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$api = file_get_contents($root.'admin/api.php');
$view = file_get_contents($root.'admin-ui-source/src/views/qifu/admin-page.vue');
$failures = array();

function check_admin_site_bulk_delete($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_admin_site_bulk_delete(strpos($view, 'type="selection" width="48" align="center"') !== false, 'site list has no multi-select column');
check_admin_site_bulk_delete(strpos($view, '@selection-change="handleSiteSelectionChange"') !== false, 'site selection changes are not tracked');
check_admin_site_bulk_delete(strpos($view, '批量删除') !== false, 'batch delete action is missing');
check_admin_site_bulk_delete(strpos($view, 'selectedSiteCount === 0 || siteBatchDeleting') !== false, 'batch delete action is not disabled when no site is selected');
check_admin_site_bulk_delete(strpos($view, "qifuActionOptimized<{ deleted?: number }>('site_delete', { ids })") !== false, 'batch delete does not send all selected IDs in one request');
check_admin_site_bulk_delete(strpos($view, '确定永久删除已选的 ${count} 个站点吗？此操作无法恢复。') !== false, 'batch deletion lacks a clear destructive-operation confirmation');
check_admin_site_bulk_delete(strpos($view, 'watch([siteKeyword, siteCategory], () => clearSiteSelection())') !== false, 'site selection is not cleared after filtering');
check_admin_site_bulk_delete(strpos($api, "if(\$action === 'site_delete')") !== false, 'site deletion API action is missing');
check_admin_site_bulk_delete(strpos($api, "isset(\$_POST['ids']) && is_array(\$_POST['ids'])") !== false, 'site deletion API does not accept a selected-ID array');
check_admin_site_bulk_delete(strpos($api, "if(!\$ids) qifu_api_exit(array(), '请选择至少一个有效站点', 400);") !== false, 'site deletion API accepts an empty batch');
check_admin_site_bulk_delete(strpos($api, "array('requested'=>count(\$ids), 'deleted'=>\$deleted)") !== false, 'site deletion API does not report the completed deletion count');

if($failures){
    fwrite(STDERR, "Admin site bulk deletion tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin site bulk deletion tests passed.\n";
