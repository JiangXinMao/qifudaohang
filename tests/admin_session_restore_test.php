<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$api = file_get_contents($root.'admin/api.php');
$client = file_get_contents($root.'admin-ui-source/src/api/qifu.ts');
$auth = file_get_contents($root.'admin-ui-source/src/api/auth.ts');
$guard = file_get_contents($root.'admin-ui-source/src/router/guards/beforeEach.ts');
$failures = array();

function check_session_restore($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_session_restore(strpos($api, "if(\$action === 'session_status')") !== false, 'public session status endpoint is missing');
check_session_restore(strpos($api, "'authenticated'=>\$authenticated") !== false, 'session endpoint does not expose authentication state');
check_session_restore(strpos($api, "'user'=>\$authenticated ? qifu_api_user() : null") !== false, 'session endpoint exposes a user outside an authenticated session');
check_session_restore(strpos($client, 'export function qifuSessionStatus()') !== false, 'session status API client is missing');
check_session_restore(strpos($auth, 'export function fetchSessionStatus()') !== false, 'auth API does not expose session restoration');
check_session_restore(strpos($guard, 'await restoreServerSession(userStore)') !== false, 'route guard does not restore the PHP session');
check_session_restore(strpos($guard, "userStore.setToken('session')") !== false && strpos($guard, 'userStore.setLoginStatus(true)') !== false, 'restored session does not update the user store');
check_session_restore(strpos($guard, 'userStore.isLogin && to.path === RoutesAlias.Login') !== false, 'authenticated users can still remain on the login page');

if($failures){
    fwrite(STDERR, "Admin session restore tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin session restore tests passed.\n";
?>
