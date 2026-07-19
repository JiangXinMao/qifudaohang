<?php
if(!defined('IN_CRONLITE')) exit();

if(!defined('QIFU_USERNAME_MIN_LENGTH')) define('QIFU_USERNAME_MIN_LENGTH', 6);
if(!defined('QIFU_USERNAME_MAX_LENGTH')) define('QIFU_USERNAME_MAX_LENGTH', 18);
if(!defined('QIFU_PASSWORD_MIN_LENGTH')) define('QIFU_PASSWORD_MIN_LENGTH', 6);
if(!defined('QIFU_PASSWORD_MAX_LENGTH')) define('QIFU_PASSWORD_MAX_LENGTH', 18);

function qifu_is_https(){
    if(!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    return isset($_SERVER['SERVER_PORT']) && intval($_SERVER['SERVER_PORT']) === 443;
}

function qifu_username_valid($username){
    if(!is_string($username)) return false;
    $pattern = '/^[A-Za-z0-9]{'.QIFU_USERNAME_MIN_LENGTH.','.QIFU_USERNAME_MAX_LENGTH.'}$/D';
    return preg_match($pattern, $username) === 1;
}

function qifu_username_input_attributes(){
    return 'minlength="'.QIFU_USERNAME_MIN_LENGTH.'" maxlength="'.QIFU_USERNAME_MAX_LENGTH.'" pattern="[A-Za-z0-9]{'.QIFU_USERNAME_MIN_LENGTH.','.QIFU_USERNAME_MAX_LENGTH.'}"';
}

function qifu_username_policy_text(){
    return QIFU_USERNAME_MIN_LENGTH.'-'.QIFU_USERNAME_MAX_LENGTH.' 位英文字母或数字';
}

function qifu_password_valid($password){
    if(!is_string($password)) return false;
    $pattern = '/^[A-Za-z0-9]{'.QIFU_PASSWORD_MIN_LENGTH.','.QIFU_PASSWORD_MAX_LENGTH.'}$/D';
    return preg_match($pattern, $password) === 1;
}

function qifu_password_input_attributes(){
    return 'minlength="'.QIFU_PASSWORD_MIN_LENGTH.'" maxlength="'.QIFU_PASSWORD_MAX_LENGTH.'" pattern="[A-Za-z0-9]{'.QIFU_PASSWORD_MIN_LENGTH.','.QIFU_PASSWORD_MAX_LENGTH.'}"';
}

function qifu_password_policy_text(){
    return QIFU_PASSWORD_MIN_LENGTH.'-'.QIFU_PASSWORD_MAX_LENGTH.' 位英文字母或数字';
}

function qifu_public_http_url($url, &$resolved_ip = null){
    $resolved_ip = null;
    if(!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) return false;
    $parts = parse_url($url);
    if(empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http','https'), true) || empty($parts['host'])) return false;
    $host = trim($parts['host'], '[]');
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? array($host) : @gethostbynamel($host);
    if(!$ips) return false;
    foreach($ips as $ip){
        if(!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    }
    $resolved_ip = $ips[0];
    return true;
}

function qifu_admin_password_verify($password){
    global $conf;
    $hash = isset($conf['admin_pwd_hash']) ? (string)$conf['admin_pwd_hash'] : '';
    if($hash !== '' && password_verify((string)$password, $hash)) return true;
    $legacy = isset($conf['admin_pwd']) ? (string)$conf['admin_pwd'] : '';
    return $legacy !== '' && hash_equals($legacy, (string)$password);
}

function qifu_admin_password_migrate($password){
    global $conf, $CACHE;
    if(!empty($conf['admin_pwd_hash'])) return;
    saveSetting('admin_pwd_hash', password_hash((string)$password, PASSWORD_DEFAULT));
    saveSetting('admin_pwd', '');
    $CACHE->clear();
    $conf = $CACHE->update();
}

function qifu_auth_version(){
    global $conf;
    return isset($conf['admin_auth_version']) ? max(1, intval($conf['admin_auth_version'])) : 1;
}

function qifu_admin_login_session($username){
    session_regenerate_id(true);
    $_SESSION['qifu_admin_login_at'] = time();
    $_SESSION['qifu_admin_auth'] = true;
    $_SESSION['qifu_admin_user'] = (string)$username;
    $_SESSION['qifu_admin_version'] = qifu_auth_version();
    $_SESSION['qifu_admin_last_seen'] = time();
}

function qifu_admin_logout_session(){
    unset($_SESSION['qifu_admin_auth'], $_SESSION['qifu_admin_user'], $_SESSION['qifu_admin_version'], $_SESSION['qifu_admin_last_seen'], $_SESSION['qifu_admin_login_at']);
    session_regenerate_id(true);
}

function qifu_csrf_token(){
    if(empty($_SESSION['qifu_csrf']) || !is_string($_SESSION['qifu_csrf'])){
        $_SESSION['qifu_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['qifu_csrf'];
}

function qifu_csrf_valid($token){
    return is_string($token) && $token !== '' && hash_equals(qifu_csrf_token(), $token);
}

function qifu_csrf_input(){
    return '<input type="hidden" name="_csrf" value="'.htmlspecialchars(qifu_csrf_token(), ENT_QUOTES, 'UTF-8').'">';
}

function qifu_csrf_url($url){
    $join = strpos($url, '?') === false ? '?' : '&';
    return $url.$join.'_csrf='.rawurlencode(qifu_csrf_token());
}

function qifu_require_csrf(){
    $token = isset($_POST['_csrf']) ? $_POST['_csrf'] : (isset($_GET['_csrf']) ? $_GET['_csrf'] : '');
    if($token === '' && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    if(qifu_csrf_valid($token)) return true;
    http_response_code(403);
    if(defined('DH_JSON_RESPONSE') && DH_JSON_RESPONSE){
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('code'=>0, 'msg'=>'安全令牌已失效，请刷新页面后重试'), JSON_UNESCAPED_UNICODE);
    } else {
        echo '请求已拒绝：安全令牌无效。请返回并刷新页面后重试。';
    }
    exit;
}

function qifu_login_rate_ensure_table(){
    global $DB;
    if(defined('SQLITE')){
        $DB->query("CREATE TABLE IF NOT EXISTS web_login_attempts (identity_hash TEXT PRIMARY KEY, attempts INTEGER NOT NULL DEFAULT 0, first_attempt INTEGER NOT NULL DEFAULT 0, last_attempt INTEGER NOT NULL DEFAULT 0, locked_until INTEGER NOT NULL DEFAULT 0)");
        return;
    }
    $DB->query("CREATE TABLE IF NOT EXISTS web_login_attempts (`identity_hash` char(64) NOT NULL,`attempts` int(11) NOT NULL DEFAULT 0,`first_attempt` int(11) NOT NULL DEFAULT 0,`last_attempt` int(11) NOT NULL DEFAULT 0,`locked_until` int(11) NOT NULL DEFAULT 0,PRIMARY KEY (`identity_hash`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function qifu_login_identities($username){
    $ip = (string)real_ip();
    $user = strtolower((string)$username);
    return array(
        hash('sha256', 'pair|'.$ip.'|'.$user),
        hash('sha256', 'account|'.$user),
        hash('sha256', 'ip|'.$ip),
    );
}

function qifu_login_rate_wait($username){
    global $DB;
    qifu_login_rate_ensure_table();
    $wait = 0;
    foreach(qifu_login_identities($username) as $key){
        $row = $DB->prepared_row('SELECT locked_until FROM web_login_attempts WHERE identity_hash=?', array($key));
        if($row) $wait = max($wait, intval($row['locked_until']) - time());
    }
    return max(0, $wait);
}

function qifu_login_rate_fail($username){
    global $DB;
    qifu_login_rate_ensure_table();
    $now = time();
    foreach(qifu_login_identities($username) as $key){
        $row = $DB->prepared_row('SELECT * FROM web_login_attempts WHERE identity_hash=?', array($key));
        $expired = !$row || $now - intval($row['first_attempt']) > 900;
        $attempts = $expired ? 1 : intval($row['attempts']) + 1;
        $first = $expired ? $now : intval($row['first_attempt']);
        $locked = $attempts >= 5 ? $now + 900 : 0;
        if($row){
            $DB->prepared_query('UPDATE web_login_attempts SET attempts=?,first_attempt=?,last_attempt=?,locked_until=? WHERE identity_hash=?', array($attempts,$first,$now,$locked,$key));
        } else {
            $DB->prepared_query('INSERT INTO web_login_attempts (identity_hash,attempts,first_attempt,last_attempt,locked_until) VALUES (?,?,?,?,?)', array($key,$attempts,$first,$now,$locked));
        }
    }
}

function qifu_login_rate_clear($username){
    global $DB;
    qifu_login_rate_ensure_table();
    foreach(qifu_login_identities($username) as $key){
        $DB->prepared_query('DELETE FROM web_login_attempts WHERE identity_hash=?', array($key));
    }
}

function qifu_safe_image_upload($file, $directory, $prefix, &$error, $max_bytes = 5242880){
    $error = '';
    if(!is_array($file) || !isset($file['error']) || intval($file['error']) !== UPLOAD_ERR_OK){
        $error = '图片上传失败';
        return false;
    }
    if(empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])){
        $error = '上传来源无效';
        return false;
    }
    $size = isset($file['size']) ? intval($file['size']) : 0;
    if($size < 1 || $size > $max_bytes){
        $error = '图片大小必须在 5MB 以内';
        return false;
    }
    $image = @getimagesize($file['tmp_name']);
    $allowed = array('image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp');
    $mime = $image && isset($image['mime']) ? strtolower($image['mime']) : '';
    if(!isset($allowed[$mime])){
        $error = '文件内容不是受支持的图片';
        return false;
    }
    if(intval($image[0]) < 1 || intval($image[1]) < 1 || intval($image[0]) * intval($image[1]) > 40000000){
        $error = '图片尺寸无效或过大';
        return false;
    }
    if(!is_dir($directory) && !mkdir($directory, 0755, true)){
        $error = '图片目录创建失败';
        return false;
    }
    $safe_prefix = preg_replace('/[^a-z0-9_-]+/i', '_', (string)$prefix);
    $filename = trim($safe_prefix, '_').'_'.bin2hex(random_bytes(12)).'.'.$allowed[$mime];
    $target = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$filename;
    if(!move_uploaded_file($file['tmp_name'], $target)){
        $error = '图片保存失败';
        return false;
    }
    chmod($target, 0644);
    return $filename;
}
?>
