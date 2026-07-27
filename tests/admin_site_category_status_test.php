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

function check_admin_site_category_status($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_admin_site_category_status(strpos($view, '@click="applySiteFilters"') !== false, 'filter button still reloads bootstrap data');
check_admin_site_category_status(strpos($view, 'function normalizeActive(value: unknown, fallback = 1)') !== false, 'active values are not normalized');
check_admin_site_category_status(strpos($view, 'active: normalizeActive(category.active)') !== false, 'category bootstrap status is not normalized');
check_admin_site_category_status(strpos($view, 'active: normalizeActive(site.active)') !== false, 'site bootstrap status is not normalized');
check_admin_site_category_status(strpos($view, '? { ...row, active: normalizeActive(row.active) }') !== false, 'category edit status is not normalized');
check_admin_site_category_status(strpos($view, ': { id: 0, name: \'\', icon: \'⭐\', sort: 10, active: 1 }') !== false, 'new categories do not default to enabled');
check_admin_site_category_status(strpos($view, '>批量修改分类</ElButton') !== false, 'batch category action is missing');
check_admin_site_category_status(strpos($view, "qifuActionOptimized<{ updated?: number }>('site_category_batch'") !== false, 'batch category action is not sent to the API');
check_admin_site_category_status(strpos($api, "if(\$action === 'site_category_batch')") !== false, 'batch category API is missing');
check_admin_site_category_status(strpos($api, "SELECT id FROM web_category WHERE name=?") !== false, 'batch category API does not validate the target category');
check_admin_site_category_status(strpos($api, "UPDATE web_dh SET category=? WHERE id=?") !== false, 'batch category API does not update selected sites');
check_admin_site_category_status(substr_count($api, "isset(\$_POST['active']) ? (intval(\$_POST['active']) === 1 ? 1 : 0) : 1") >= 1, 'site active does not default to enabled');
check_admin_site_category_status(strpos($api, "isset(\$_POST['active'])?(intval(\$_POST['active'])===1?1:0):1") !== false, 'category active does not default to enabled');
check_admin_site_category_status(strpos($api, "SELECT id,name,icon,sort,active FROM web_category WHERE id=?") !== false, 'category rename does not load the previous category name');
check_admin_site_category_status(strpos($api, "UPDATE web_dh SET category=? WHERE category=?") !== false, 'category rename does not update assigned sites');

if($failures){
    fwrite(STDERR, "Admin site/category status tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin site/category status tests passed.\n";
