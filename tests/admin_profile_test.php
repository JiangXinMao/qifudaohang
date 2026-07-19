<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$api = file_get_contents($root.'admin/api.php');
$client = file_get_contents($root.'admin-ui-source/src/api/qifu.ts');
$routes = file_get_contents($root.'admin-ui-source/src/router/routes/asyncRoutes.ts');
$view = file_get_contents($root.'admin-ui-source/src/views/qifu/admin-page.vue');
$menu = file_get_contents($root.'admin-ui-source/src/components/core/layouts/art-header-bar/widget/ArtUserMenu.vue');
$media = file_get_contents($root.'includes/media_path.php');
$failures = array();

function check_profile($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_profile(strpos($routes, "path: 'user-center'") !== false && strpos($routes, "name: 'UserCenter'") !== false, 'personal center route is missing');
check_profile(strpos($routes, "isHide: true") !== false, 'personal center route must stay hidden from the sidebar');
check_profile(
    strpos($routes, "path: 'password'") !== false
    && strpos($routes, "name: 'Password'") !== false
    && strpos($routes, "title: '账号安全'") !== false
    && strpos($routes, 'isHide: true') !== false,
    'account security route must stay hidden from the sidebar'
);
check_profile(strpos($view, "pageName === 'UserCenter'") !== false, 'personal center page is missing');
check_profile(strpos($view, '进入账号安全') === false, 'duplicate account security action is still shown in the personal center header');
check_profile(strpos($view, '最近操作') !== false && strpos($view, "go('Password')") !== false, 'activity or account security link is missing');
check_profile(strpos($client, 'export function qifuProfile') !== false && strpos($client, 'export function qifuSaveProfile') !== false, 'profile API client is incomplete');
check_profile(strpos($client, 'export async function qifuUploadAvatar') !== false, 'avatar upload client is missing');
check_profile(strpos($api, 'function qifu_api_profile()') !== false && strpos($api, "if(\$action === 'profile')") !== false, 'profile read endpoint is missing');
check_profile(strpos($api, "if(\$action === 'profile_save')") !== false && strpos($api, '$allowed_pages') !== false && strpos($api, '$allowed_density') !== false, 'profile save validation is incomplete');
check_profile(strpos($api, '$allowed_themes') !== false && strpos($api, '$allowed_languages') !== false, 'theme or language validation is incomplete');
check_profile(strpos($api, "if(\$action === 'profile_avatar_upload')") !== false && strpos($api, "ROOT.'images/avatar/'") !== false, 'profile avatar endpoint is missing');
check_profile(strpos($api, "saveSetting('admin_last_login_ip', real_ip())") !== false, 'login IP recording is missing');
check_profile(strpos($api, "saveSetting('admin_password_changed_at',(string)time())") !== false, 'password update time recording is missing');
check_profile(strpos($menu, 'userInfo.avatar || defaultAvatar') !== false, 'header avatar does not use the saved profile avatar');
check_profile(strpos($menu, "goPage('/system/password')") !== false && strpos($menu, "topBar.user.accountSecurity") !== false, 'header user menu account security entry is missing');
check_profile(strpos($media, 'ad|bg|logo|avatar') !== false, 'avatar media path is not allowed');

if($failures){
    fwrite(STDERR, "Admin profile tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin profile tests passed.\n";
