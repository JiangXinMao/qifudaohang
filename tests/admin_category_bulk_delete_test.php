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

function check_admin_category_bulk_delete($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_admin_category_bulk_delete(strpos($view, 'ref="categoryTableRef"') !== false, 'category list does not expose its table selection instance');
check_admin_category_bulk_delete(strpos($view, '@selection-change="handleCategorySelectionChange"') !== false, 'category selection changes are not tracked');
check_admin_category_bulk_delete(strpos($view, 'selectedCategoryCount === 0 || categoryBatchDeleting') !== false, 'category batch delete action is not disabled when no category is selected');
check_admin_category_bulk_delete(strpos($view, "qifuActionOptimized<{ deleted?: number }>('category_delete', { ids })") !== false, 'category batch delete does not send all selected IDs in one request');
check_admin_category_bulk_delete(strpos($view, '分类下的站点不会被删除。') !== false, 'category batch delete does not explain that sites are retained');
check_admin_category_bulk_delete(strpos($view, 'clearCategorySelection()') !== false, 'category selection is not cleared after completion or refresh');
check_admin_category_bulk_delete(strpos($api, "if(\$action === 'category_delete')") !== false, 'category deletion API action is missing');
check_admin_category_bulk_delete(strpos($api, "isset(\$_POST['ids']) && is_array(\$_POST['ids'])") !== false, 'category deletion API does not accept a selected-ID array');
check_admin_category_bulk_delete(strpos($api, "if(!\$ids) qifu_api_exit(array(), '请选择至少一个有效分类', 400);") !== false, 'category deletion API accepts an empty batch');
check_admin_category_bulk_delete(strpos($api, "array('requested'=>count(\$ids), 'deleted'=>\$deleted)") !== false, 'category deletion API does not report the completed deletion count');

if($failures){
    fwrite(STDERR, "Admin category bulk deletion tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin category bulk deletion tests passed.\n";
