<?php
declare(strict_types=1);

define('IN_CRONLITE', true);
require_once dirname(__DIR__).'/includes/online_update.php';

$failures = array();
function check_online_update($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

function online_update_remove_tree($path){
    if(!file_exists($path) && !is_link($path)) return;
    if(is_file($path) || is_link($path)){ @unlink($path); return; }
    foreach(scandir($path) ?: array() as $entry){
        if($entry === '.' || $entry === '..') continue;
        online_update_remove_tree($path.DIRECTORY_SEPARATOR.$entry);
    }
    @rmdir($path);
}

function online_update_write($path, $contents){
    $directory = dirname($path);
    if(!is_dir($directory)) mkdir($directory, 0777, true);
    file_put_contents($path, $contents);
}

function online_update_zip($path, $files){
    $zip = new ZipArchive();
    if($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true){
        throw new RuntimeException('Unable to create test ZIP');
    }
    foreach($files as $name=>$contents){
        if($contents === null) $zip->addEmptyDir($name);
        else $zip->addFromString($name, $contents);
    }
    $zip->close();
}

if(!class_exists('ZipArchive')){
    fwrite(STDERR, "Online update tests require PHP Zip.\n");
    exit(1);
}

$base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'qifu-online-update-'.bin2hex(random_bytes(6));
$root = $base.DIRECTORY_SEPARATOR.'site';
$archive = $base.DIRECTORY_SEPARATOR.'release.zip';
mkdir($root, 0777, true);

try {
    online_update_write($root.'/index.php', 'old-index');
    online_update_write($root.'/config.php', 'customer-config');
    online_update_write($root.'/install/install.lock', 'installed');
    online_update_write($root.'/includes/common.php', 'old-common');
    online_update_write($root.'/includes/brand.php', "<?php define('QIFU_PRODUCT_VERSION', 'V1.4.3');");
    online_update_write($root.'/includes/sqlite/customer.db', 'customer-db');
    online_update_write($root.'/includes/.telemetry/remote.json', 'customer-cache');
    online_update_write($root.'/backup/customer.db', 'customer-backup');
    online_update_write($root.'/secure-admin/index.php', 'old-admin-index');
    online_update_write($root.'/secure-admin/login.php', 'old-login');
    online_update_write($root.'/secure-admin/head.php', 'old-head');
    online_update_write($root.'/secure-admin/saiadmin-skin.css', 'old-skin');

    online_update_zip($archive, array(
        './'=>null,
        './index.php'=>'new-index',
        './config.php'=>'package-config',
        './install/index.php'=>'new-installer',
        './install/install.lock'=>'package-lock',
        './includes/common.php'=>'new-common',
        './includes/brand.php'=>"<?php define('QIFU_PRODUCT_VERSION', 'V1.5');",
        './includes/online_update.php'=>'updater-v15',
        './includes/sqlite/default.db'=>'package-db',
        './includes/.telemetry/remote.json'=>'package-cache',
        './backup/default.db'=>'package-backup',
        './admin/index.php'=>'new-admin-index',
        './admin/login.php'=>'new-login',
        './admin/head.php'=>'new-head',
        './admin/saiadmin-skin.css'=>'new-skin',
        './assets/new.txt'=>'new-asset'
    ));

    $progressEvents = array();
    $progressCallback = static function($phase, $percentage, $message, $status) use (&$progressEvents){
        $progressEvents[] = array($phase, $percentage, $message, $status);
    };
    $result = qifu_online_update_install_archive($archive, array('version'=>'1.5'), $root, 'secure-admin', $progressCallback);
    check_online_update(($result['version'] ?? '') === 'V1.5.0', 'installed version was not returned');
    check_online_update(file_get_contents($root.'/index.php') === 'new-index', 'root file was not updated');
    check_online_update(file_get_contents($root.'/secure-admin/index.php') === 'new-admin-index', 'renamed admin directory was not mapped');
    check_online_update(!is_dir($root.'/admin'), 'default admin directory leaked into renamed installation');
    check_online_update(file_get_contents($root.'/config.php') === 'customer-config', 'customer config was overwritten');
    check_online_update(file_get_contents($root.'/install/install.lock') === 'installed', 'install lock was overwritten');
    check_online_update(file_get_contents($root.'/includes/sqlite/customer.db') === 'customer-db', 'customer SQLite data was overwritten');
    check_online_update(!is_file($root.'/includes/sqlite/default.db'), 'package SQLite data should not be installed');
    check_online_update(file_get_contents($root.'/includes/.telemetry/remote.json') === 'customer-cache', 'telemetry cache was overwritten');
    check_online_update(file_get_contents($root.'/backup/customer.db') === 'customer-backup', 'customer backup was overwritten');
    check_online_update(is_file($root.'/assets/new.txt'), 'new release file was not created');
    check_online_update(is_file($root.'/.qifu-update/backups/'.$result['operationId'].'/manifest.json'), 'rollback manifest was not retained');
    check_online_update(count(array_filter($progressEvents, static function($event){ return $event[0] === 'overlay' && $event[1] === 95; })) === 1, 'real overlay progress was not emitted');

    $progressId = 'test-progress-1234';
    $writtenProgress = qifu_online_update_progress_write($root, $progressId, 'download', 55, 'download verified');
    $readProgress = qifu_online_update_progress_read($root, $progressId);
    check_online_update(is_array($writtenProgress) && is_array($readProgress) && $readProgress['percentage'] === 55, 'update progress state was not persisted');
    check_online_update(qifu_online_update_progress_read($root, '../invalid') === null, 'unsafe progress identifier was accepted');

    $unsafeArchive = $base.DIRECTORY_SEPARATOR.'unsafe.zip';
    online_update_zip($unsafeArchive, array(
        '../escape.php'=>'unsafe',
        'index.php'=>'new-index',
        'install/index.php'=>'installer',
        'includes/common.php'=>'common',
        'includes/brand.php'=>"<?php define('QIFU_PRODUCT_VERSION', 'V1.6');",
        'includes/online_update.php'=>'updater-v16',
        'admin/index.php'=>'admin',
        'admin/login.php'=>'login',
        'admin/head.php'=>'head',
        'admin/saiadmin-skin.css'=>'skin'
    ));
    $unsafeRejected = false;
    try { qifu_online_update_install_archive($unsafeArchive, array('version'=>'1.6'), $root, 'secure-admin'); }
    catch(Throwable $error){ $unsafeRejected = str_contains($error->getMessage(), 'unsafe'); }
    check_online_update($unsafeRejected, 'unsafe archive path was not rejected');
    check_online_update(!is_file($base.'/escape.php'), 'unsafe archive escaped the staging directory');

    $rollbackArchive = $base.DIRECTORY_SEPARATOR.'rollback.zip';
    online_update_write($root.'/blocked', 'blocking-file');
    online_update_write($root.'/aaa.txt', 'old-aaa');
    online_update_zip($rollbackArchive, array(
        'aaa.txt'=>'new-aaa',
        'index.php'=>'rollback-new-index',
        'install/index.php'=>'installer',
        'includes/common.php'=>'common-v16',
        'includes/brand.php'=>"<?php define('QIFU_PRODUCT_VERSION', 'V1.6');",
        'includes/online_update.php'=>'updater-v16',
        'admin/index.php'=>'admin-v16',
        'admin/login.php'=>'login-v16',
        'admin/head.php'=>'head-v16',
        'admin/saiadmin-skin.css'=>'skin-v16',
        'blocked/child.txt'=>'must-fail'
    ));
    $rollbackFailed = false;
    try { qifu_online_update_install_archive($rollbackArchive, array('version'=>'1.6'), $root, 'secure-admin'); }
    catch(Throwable){ $rollbackFailed = true; }
    check_online_update($rollbackFailed, 'commit conflict did not fail');
    check_online_update(file_get_contents($root.'/aaa.txt') === 'old-aaa', 'already overwritten file was not restored after failure');
    check_online_update(file_get_contents($root.'/index.php') === 'new-index', 'overwritten file was not restored after failure');
    check_online_update(!is_file($root.'/blocked/child.txt'), 'new file remained after rollback');
} finally {
    online_update_remove_tree($base);
}

if($failures){
    fwrite(STDERR, "Online update tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Online update tests passed.\n";
