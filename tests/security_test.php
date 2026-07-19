<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

define('IN_CRONLITE', true);
define('SYSTEM_ROOT', dirname(__DIR__).'/includes/');
define('ROOT', dirname(__DIR__).'/');
define('CACHE_FILE', 0);
require ROOT.'config.php';
require SYSTEM_ROOT.'db.class.php';
require SYSTEM_ROOT.'function.php';
require SYSTEM_ROOT.'security.php';

if(session_status() !== PHP_SESSION_ACTIVE) session_start();
$DB = new DB($dbconfig['host'], $dbconfig['user'], $dbconfig['pwd'], $dbconfig['dbname'], $dbconfig['port']);
$failures = array();

function check_security($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_security(qifu_username_valid('admin1'), '6 character mixed username should be valid');
check_security(qifu_username_valid('abcdef'), 'letter-only username should be valid');
check_security(qifu_username_valid('123456'), 'digit-only username should be valid');
check_security(qifu_username_valid(str_repeat('a', 18)), '18 character username should be valid');
check_security(!qifu_username_valid('admin'), '5 character username must be rejected');
check_security(!qifu_username_valid(str_repeat('a', 19)), '19 character username must be rejected');
check_security(!qifu_username_valid('admin_1'), 'username symbols must be rejected');
check_security(!qifu_username_valid("admin'--"), 'username injection payload must be rejected');

$install_sql = file_get_contents(ROOT.'install/install.sql');
check_security(strpos($install_sql, "('admin_user', 'admin1')") !== false, 'installer default administrator account is not admin1');
$install_hash_match = array();
check_security(
    preg_match("/\\('admin_pwd_hash', '([^']+)'\\)/", $install_sql, $install_hash_match) === 1
    && password_verify('123456', $install_hash_match[1]),
    'installer default administrator password is not 123456'
);
$login_source = file_get_contents(ROOT.'admin/login.php');
$password_source = file_get_contents(ROOT.'admin/password.php');
$ad_source = file_get_contents(ROOT.'admin/ad.php');
$upload_source = file_get_contents(ROOT.'admin/ajax_upload_ad.php');
$mail_test_source = file_get_contents(ROOT.'admin/ajax_test_mail.php');
$legacy_source = file_get_contents(ROOT.'admin/legacy-index.php');
$head_source = file_get_contents(ROOT.'admin/head.php');
$links_source = file_get_contents(ROOT.'admin/links.php');
$category_source = file_get_contents(ROOT.'admin/set_category.php');
$sites_source = file_get_contents(ROOT.'admin/set_dh.php');
check_security(qifu_username_input_attributes() === 'minlength="6" maxlength="18" pattern="[A-Za-z0-9]{6,18}"', 'shared username input attributes do not match the 6-18 alphanumeric policy');
check_security(strpos($login_source, 'qifu_username_input_attributes()') !== false, 'login form does not use the shared username constraints');
check_security(strpos($password_source, 'qifu_username_input_attributes()') !== false, 'account settings form does not use the shared username constraints');
check_security(strpos($password_source, 'qifu_require_csrf()') !== false, 'password change endpoint does not enforce CSRF');
check_security(strpos($ad_source, 'qifu_require_csrf()') !== false, 'legacy advertisement form does not enforce CSRF');
check_security(strpos($upload_source, 'qifu_require_csrf()') !== false, 'advertisement upload endpoint does not enforce CSRF');
check_security(strpos($mail_test_source, 'qifu_require_csrf()') !== false, 'test mail endpoint does not enforce CSRF');
check_security(strpos($legacy_source, 'qifu_require_csrf()') !== false, 'legacy quick-add endpoint does not enforce CSRF');
check_security(strpos($head_source, 'ensureCsrfFields') !== false, 'admin forms do not receive the shared CSRF field');
check_security(strpos($links_source, 'qifu_require_csrf()') !== false, 'legacy link management endpoint does not enforce CSRF');
check_security(strpos($category_source, 'qifu_require_csrf()') !== false, 'legacy category management endpoint does not enforce CSRF');
check_security(strpos($sites_source, 'qifu_require_csrf()') !== false, 'legacy site management endpoint does not enforce CSRF');

check_security(qifu_password_valid('abc123'), '6 character mixed password should be valid');
check_security(qifu_password_valid('abcdef'), 'letter-only password should be valid');
check_security(qifu_password_valid('123456'), 'digit-only password should be valid');
check_security(qifu_password_valid(str_repeat('a', 18)), '18 character password should be valid');
check_security(!qifu_password_valid('abc12'), '5 character password must be rejected');
check_security(!qifu_password_valid(str_repeat('a', 19)), '19 character password must be rejected');
check_security(!qifu_password_valid('abc_123'), 'password symbols must be rejected');
check_security(!qifu_password_valid("abc123' OR 1=1"), 'password injection payload must be rejected');
check_security(function_exists('qifu_password_input_attributes'), 'shared password input attributes helper is missing');
if(function_exists('qifu_password_input_attributes')){
    check_security(qifu_password_input_attributes() === 'minlength="6" maxlength="18" pattern="[A-Za-z0-9]{6,18}"', 'shared password input attributes do not match the 6-18 alphanumeric policy');
}
check_security(strpos($login_source, 'qifu_password_input_attributes()') !== false, 'login form does not use the shared password constraints');
check_security(substr_count($password_source, 'qifu_password_input_attributes()') >= 2, 'account settings form does not use the shared password constraints');

$hash = password_hash('abc123', PASSWORD_DEFAULT);
check_security(password_verify('abc123', $hash), 'password hash verification failed');
check_security(!password_verify('wrong123', $hash), 'wrong password unexpectedly verified');

$stored_user = (string)$DB->prepared_value('SELECT v FROM web_config WHERE k=?', array('admin_user'));
$stored_plain = (string)$DB->prepared_value('SELECT v FROM web_config WHERE k=?', array('admin_pwd'));
$stored_hash = (string)$DB->prepared_value('SELECT v FROM web_config WHERE k=?', array('admin_pwd_hash'));
check_security(qifu_username_valid($stored_user), 'stored administrator username violates policy');
check_security($stored_plain === '', 'legacy plaintext administrator password is still stored');
check_security(password_verify('123456', $stored_hash), 'stored administrator password hash is invalid');

$token = qifu_csrf_token();
check_security(strlen($token) === 64 && qifu_csrf_valid($token), 'CSRF token generation or verification failed');
check_security(!qifu_csrf_valid(str_repeat('0', 64)), 'invalid CSRF token unexpectedly verified');

$private_ip = null;
check_security(!qifu_public_http_url('http://127.0.0.1/admin', $private_ip), 'loopback URL must be rejected');
check_security(!qifu_public_http_url('http://localhost/', $private_ip), 'localhost URL must be rejected');
check_security(!qifu_public_http_url('file:///etc/passwd', $private_ip), 'non-HTTP URL must be rejected');

$table_count = intval($DB->prepared_value("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='web_dh'"));
check_security($table_count === 1, 'web_dh table is missing before injection test');
$DB->link->beginTransaction();
$payload = "security-test'); DROP TABLE web_dh; --";
$inserted = $DB->prepared_query('INSERT INTO web_category (name,icon,sort,active,addtime) VALUES (?,?,?,?,?)', array($payload,'',999,0,time()));
$stored = $DB->prepared_value('SELECT name FROM web_category WHERE name=?', array($payload));
$table_after = intval($DB->prepared_value("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='web_dh'"));
$DB->link->rollBack();
check_security($inserted !== false && $stored === $payload, 'prepared query did not preserve payload as data');
check_security($table_after === 1, 'injection payload changed database schema');
check_security($DB->query('DROP TABLE web_dh') === false, 'runtime DROP TABLE guard failed');
check_security($DB->query('ALTER TABLE web_dh ADD COLUMN compromised TEXT') === false, 'runtime ALTER TABLE guard failed');
check_security($DB->query('SELECT 1; DROP TABLE web_dh') === false, 'stacked SQL guard failed');
check_security($DB->get_row('DROP TABLE web_dh') === false, 'raw row helper bypassed the DDL guard');
check_security($DB->get_results('SELECT 1; DROP TABLE web_dh') === array(), 'raw results helper bypassed the stacked SQL guard');

qifu_login_rate_ensure_table();
$original_remote_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
$DB->link->beginTransaction();
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
for($attempt = 0; $attempt < 5; $attempt++) qifu_login_rate_fail('ratetest');
check_security(qifu_login_rate_wait('ratetest') > 0, 'combined login rate limit did not lock');
$_SERVER['REMOTE_ADDR'] = '203.0.113.11';
check_security(qifu_login_rate_wait('ratetest') > 0, 'account rate limit was bypassed by changing IP');
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
check_security(qifu_login_rate_wait('othertest') > 0, 'IP rate limit was bypassed by changing account');
$DB->link->rollBack();
if($original_remote_addr === null) unset($_SERVER['REMOTE_ADDR']);
else $_SERVER['REMOTE_ADDR'] = $original_remote_addr;

$_COOKIE['admin_user'] = 'attacker';
require SYSTEM_ROOT.'cache.class.php';
$CACHE = new CACHE();
$cached = $CACHE->pre_fetch();
check_security(!isset($cached['admin_user']) || $cached['admin_user'] !== 'attacker', 'cookie overrode protected configuration');

if($failures){
    fwrite(STDERR, "Security tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Security tests passed.\n";
