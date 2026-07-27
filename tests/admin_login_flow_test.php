<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$guard = file_get_contents($root.'admin-ui-source/src/router/guards/beforeEach.ts');
$user_store = file_get_contents($root.'admin-ui-source/src/store/modules/user.ts');
$http = file_get_contents($root.'admin-ui-source/src/utils/http/index.ts');
$http_error = file_get_contents($root.'admin-ui-source/src/utils/http/error.ts');
$login = file_get_contents($root.'admin-ui-source/src/views/auth/login/index.vue');
$security = file_get_contents($root.'includes/security.php');
$api = file_get_contents($root.'admin/api.php');
$legacy_login = file_get_contents($root.'admin/login.php');
$failures = array();

function check_admin_login_flow($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

$login_guard_start = strpos($guard, 'function handleLoginStatus(');
$login_guard_end = strpos($guard, 'function isStaticRoute(', $login_guard_start);
$login_guard = substr($guard, $login_guard_start, $login_guard_end - $login_guard_start);

check_admin_login_flow(strpos($login_guard, 'userStore.logOut()') === false, 'route guard still triggers a second login navigation');
check_admin_login_flow(strpos($login_guard, "name: 'Login'") !== false && strpos($login_guard, 'replace: true') !== false, 'anonymous redirect does not replace the protected route');
check_admin_login_flow(strpos($user_store, "currentRoute.path !== '/auth/login'") !== false, 'logout still treats the actual login route as a protected redirect target');
check_admin_login_flow(strpos($user_store, 'router.replace({') !== false, 'logout keeps stacking login routes in browser history');
check_admin_login_flow(strpos($http, "requestUrl.includes('action=login')") !== false, 'login 401 responses are not distinguished from expired sessions');
check_admin_login_flow(strpos($http, '&& !isLoginRequest') !== false, 'invalid credentials still trigger global logout');
check_admin_login_flow(strpos($http_error, 'const message = errorMessage ||') !== false, 'server login errors are replaced with a generic unauthorized message');
check_admin_login_flow(strpos($security, 'function qifu_admin_credentials_verify(') !== false, 'legacy administrator credentials have no compatibility verifier');
check_admin_login_flow(strpos($api, 'qifu_admin_credentials_verify($username, $password)') !== false, 'SPA login still rejects legacy stored administrator names');
check_admin_login_flow(strpos($legacy_login, 'qifu_admin_credentials_verify($user, $pass)') !== false, 'legacy login still rejects legacy stored administrator names');
check_admin_login_flow(strpos($login, "ElMessage.warning(t('login.placeholder.slider'))") !== false, 'missing slider verification gives no visible feedback');
check_admin_login_flow(strpos($login, 'validate().catch(() => false)') !== false, 'invalid form fields are still reported as an unexpected login failure');
check_admin_login_flow(strpos($login, 'ElMessage.error(error.message') !== false, 'login failures give no visible feedback');
check_admin_login_flow(strpos($login, 'dragVerify.value?.reset?.()') !== false, 'drag verification reset can fail before the component is ready');
check_admin_login_flow(strpos($login, "redirect.startsWith('/auth/')") !== false, 'login redirect accepts another authentication route');
check_admin_login_flow(strpos($login, 'await router.replace(redirect ||') !== false, 'successful login keeps the polluted login route in history');

if($failures){
    fwrite(STDERR, "Admin login flow tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin login flow tests passed.\n";
