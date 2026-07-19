<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$temp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'qifu_backup_test_'.bin2hex(random_bytes(4));
mkdir($temp, 0700, true);
define('IN_CRONLITE', true);
define('ROOT', $temp.DIRECTORY_SEPARATOR);
define('SQLITE', true);
define('QIFU_SQLITE_PATH', $temp.DIRECTORY_SEPARATOR.'test.db');
define('QIFU_ALLOW_RUNTIME_DDL', true);
require $root.'includes/db.class.php';
require $root.'includes/backup_service.php';

$DB = new DB('test');
$failures = array();
$api_source = file_get_contents($root.'admin/api.php');
$client_source = file_get_contents($root.'admin-ui-source/src/api/qifu.ts');
$page_source = file_get_contents($root.'admin-ui-source/src/views/qifu/admin-page.vue');

function backup_check($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

function backup_remove_tree($path){
    if(!is_dir($path)) return;
    foreach(scandir($path) as $name){
        if($name === '.' || $name === '..') continue;
        $item = $path.DIRECTORY_SEPARATOR.$name;
        if(is_dir($item)) backup_remove_tree($item); else @unlink($item);
    }
    @rmdir($path);
}

try {
    backup_check(strpos($api_source, "if(\$action === 'backup_restore')") !== false, 'authenticated restore API endpoint is missing');
    backup_check(strpos($api_source, 'qifu_admin_password_verify') !== false, 'restore API does not re-authenticate the administrator');
    backup_check(strpos($client_source, 'qifuRestoreBackup') !== false, 'frontend restore upload client is missing');
    backup_check(strpos($page_source, '恢复数据</ElButton') !== false, 'restore data button is missing');
    backup_check(strpos($page_source, '恢复会覆盖当前站点数据') !== false, 'restore risk confirmation is missing');

    $DB->query('CREATE TABLE web_config (k TEXT PRIMARY KEY, v TEXT NULL)');
    $DB->query('CREATE TABLE web_dh (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, description TEXT NULL)');
    $DB->query('CREATE TABLE web_category (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    $DB->query('CREATE TABLE web_log (id INTEGER PRIMARY KEY AUTOINCREMENT, detail TEXT NULL)');
    $DB->prepared_query('INSERT INTO web_config (k,v) VALUES (?,?)', array('sitename', '祈福导航'));
    $DB->prepared_query('INSERT INTO web_config (k,v) VALUES (?,?)', array('admin_user', 'current-admin'));
    $DB->prepared_query('INSERT INTO web_config (k,v) VALUES (?,?)', array('admin_pwd_hash', 'current-hash'));
    $DB->prepared_query('INSERT INTO web_dh (name,description) VALUES (?,?)', array("站点'A", "第一行\n第二行"));
    $DB->prepared_query('INSERT INTO web_category (name) VALUES (?)', array('常用推荐'));
    $DB->prepared_query('INSERT INTO web_log (detail) VALUES (?)', array(null));

    $backup = qifu_backup_create_file($DB, 'test');
    backup_check(is_file($backup['path']), 'backup file was not created');
    $payload = qifu_backup_read_file($backup['path']);
    backup_check($payload['tableCount'] === 5, 'not all existing application tables were exported');
    backup_check(hash_equals($payload['checksum'], hash('sha256', qifu_backup_json_encode($payload['tables']))), 'backup checksum is invalid');

    $DB->prepared_query('UPDATE web_config SET v=? WHERE k=?', array('已修改', 'sitename'));
    $DB->prepared_query('UPDATE web_config SET v=? WHERE k=?', array('new-admin', 'admin_user'));
    $DB->query('DELETE FROM web_dh');
    $result = qifu_backup_restore_file($DB, $backup['path']);
    backup_check($result['tableCount'] === 5, 'restore result table count is wrong');
    backup_check($DB->prepared_value('SELECT v FROM web_config WHERE k=?', array('sitename')) === '祈福导航', 'site settings were not restored');
    backup_check($DB->prepared_value('SELECT v FROM web_config WHERE k=?', array('admin_user')) === 'new-admin', 'current administrator identity was not preserved');
    $site = $DB->get_row('SELECT name,description FROM web_dh LIMIT 1');
    backup_check($site && $site['name'] === "站点'A" && $site['description'] === "第一行\n第二行", 'site data was not restored losslessly');
    $log = $DB->get_row('SELECT detail FROM web_log LIMIT 1');
    backup_check($log && array_key_exists('detail', $log) && $log['detail'] === null, 'NULL values were not restored losslessly');
    backup_check(intval($DB->count('SELECT COUNT(*) FROM web_backup')) >= 1, 'pre-restore safety backup was not registered');

    $corrupt = $payload;
    $corrupt['tables']['web_config']['rows'][0]['v'] = 'tampered';
    $rejected = false;
    try { qifu_backup_validate_payload($DB, $corrupt); } catch(RuntimeException $error) { $rejected = true; }
    backup_check($rejected, 'tampered backup was accepted');

    $broken = $payload;
    $broken['tables']['web_dh']['rows'][0]['name'] = null;
    $broken['checksum'] = hash('sha256', qifu_backup_json_encode($broken['tables']));
    $broken_path = $temp.DIRECTORY_SEPARATOR.'broken.qifubak';
    file_put_contents($broken_path, qifu_backup_json_encode($broken));
    $DB->prepared_query('UPDATE web_config SET v=? WHERE k=?', array('回滚前状态', 'sitename'));
    $failed_restore = false;
    try { qifu_backup_restore_file($DB, $broken_path); } catch(RuntimeException $error) { $failed_restore = true; }
    backup_check($failed_restore, 'invalid row did not fail restore');
    backup_check($DB->prepared_value('SELECT v FROM web_config WHERE k=?', array('sitename')) === '回滚前状态', 'failed restore did not roll back to the safety snapshot');
} finally {
    $DB->close();
    backup_remove_tree($temp);
}

if($failures){
    fwrite(STDERR, "Backup and restore tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Backup and restore tests passed.\n";
