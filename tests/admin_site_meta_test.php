<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$failures = array();

function check_admin_site_meta($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

$api_source = file_get_contents($root.'admin/api.php');
$view_source = file_get_contents($root.'admin-ui-source/src/views/qifu/admin-page.vue');
$client_source = file_get_contents($root.'admin-ui-source/src/api/qifu.ts');

check_admin_site_meta(strpos($api_source, "if(\$action === 'site_meta')") !== false, 'admin metadata API action is missing');
check_admin_site_meta(strpos($api_source, 'qifu_api_require_write()') !== false, 'admin metadata API does not require login and CSRF');
check_admin_site_meta(strpos($api_source, "qifu_site_meta_fetch(\$url, \$error)") !== false, 'admin metadata API does not use the secured metadata helper');
check_admin_site_meta(strpos($api_source, 'qifu_admin_site_meta_requests') !== false, 'admin metadata API is not rate limited');
check_admin_site_meta(strpos($client_source, "fetch('./api.php?action=site_meta'") !== false, 'admin client metadata request is missing');
check_admin_site_meta(strpos($client_source, "'X-CSRF-Token': csrf") !== false, 'admin client metadata request does not send CSRF token');
check_admin_site_meta(strpos($view_source, '@input="scheduleSiteMeta"') !== false, 'URL input does not schedule automatic metadata fetching');
check_admin_site_meta(strpos($view_source, '输入网址后自动获取网站名称和描述') !== false, 'URL input helper text is missing');
check_admin_site_meta(strpos($view_source, 'siteForm.name = meta.name') !== false, 'site name is not automatically filled');
check_admin_site_meta(strpos($view_source, 'siteForm.description = meta.description') !== false, 'site description is not automatically filled');
check_admin_site_meta(strpos($view_source, 'label="站点图标"') !== false, 'site icon field is missing from the site dialog');
check_admin_site_meta(strpos($view_source, '<ElTableColumn label="图标" width="76" align="center"') !== false, 'site icon column is missing from the site list');
check_admin_site_meta(strpos($view_source, '<ElTableColumn prop="name" label="名称" min-width="170" show-overflow-tooltip />') !== false, 'site name is not kept in its own list column');
check_admin_site_meta(strpos($view_source, 'applyAutomaticSiteIcon()') !== false, 'URL entry does not create an automatic site icon source');
check_admin_site_meta(strpos($view_source, 'siteIconFallback') !== false, 'site icon fallback is missing');
check_admin_site_meta(strpos($view_source, 'site_icon.php?url=') !== false, 'site icon cache source is missing');
check_admin_site_meta(strpos($view_source, '重新获取站点图标') !== false, 'site icon refresh action is missing');
check_admin_site_meta(strpos($view_source, '输入图标图片 URL') !== false, 'manual site icon URL input is missing');
check_admin_site_meta(strpos($view_source, 'class="site-row-actions"') !== false, 'site edit and delete button group is missing');
check_admin_site_meta((bool)preg_match('/<ElIcon>\s*<Edit\s*\/>\s*<\/ElIcon>\s*编辑/u', $view_source), 'site edit action button is missing');
check_admin_site_meta((bool)preg_match('/<Delete\s*\/>/u', $view_source) && strpos($view_source, 'aria-label="`删除站点 ${scope.row.name}`"') !== false, 'site delete action button is missing');
check_admin_site_meta(substr_count($view_source, 'class="site-row-actions"') >= 2, 'category rows do not reuse the site action button group');
check_admin_site_meta(strpos($view_source, 'fixed="right" align="right" header-align="right"') !== false, 'category action column is not aligned to the right');

if($failures){
    fwrite(STDERR, "Admin site metadata tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin site metadata tests passed.\n";
?>
