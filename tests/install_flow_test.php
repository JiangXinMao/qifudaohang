<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

require dirname(__DIR__).'/install/helpers.php';

$failures = array();
function check_install_flow($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

$package_root = dirname(__DIR__);
$installer_source = file_get_contents($package_root.'/install/index.php');
$readme_source = file_get_contents($package_root.'/readme.txt');
$install_doc_source = file_get_contents($package_root.'/安装文档.txt');
check_install_flow(strpos($installer_source, "version_compare(PHP_VERSION, '8.2.0', '>=')") !== false, 'installer does not enforce PHP 8.2 or newer');
check_install_flow(substr_count($installer_source, 'PHP 版本 &ge; 8.2') === 1, 'installer environment check does not display PHP 8.2');
check_install_flow(strpos($installer_source, '<dd>&ge; 8.2</dd>') !== false, 'installer requirements card does not display PHP 8.2');
check_install_flow(strpos($readme_source, 'PHP 8.2 或更高版本') !== false, 'readme still documents an older PHP requirement');
check_install_flow(strpos($install_doc_source, 'PHP 8.2 或更高版本') !== false, 'installation document still documents an older PHP requirement');
check_install_flow(strpos($installer_source.$readme_source.$install_doc_source, 'PHP 7.4') === false, 'PHP 7.4 requirement remains in installation sources');

$root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'qifu_install_'.bin2hex(random_bytes(8));
if(!mkdir($root, 0700, true)){
    fwrite(STDERR, "Unable to create install test directory.\n");
    exit(1);
}

try {
    $lock = $root.DIRECTORY_SEPARATOR.'install.lock';
    mkdir($lock, 0700);
    $blocked = qifu_install_completion($lock, 'admin1 / 123456');
    check_install_flow($blocked['success'] === false, 'blocked lock path was reported as successful');
    check_install_flow(strpos($blocked['html'], '后台管理') === false, 'failed installation exposed the backend link');
    check_install_flow(!is_file($lock), 'blocked lock path unexpectedly became a file');
    rmdir($lock);

    $complete = qifu_install_completion($lock, 'admin1 / 123456');
    check_install_flow($complete['success'] === true, 'writable lock path was reported as failed');
    check_install_flow(is_file($lock), 'successful installation did not create a lock file');
    check_install_flow(strpos($complete['html'], '后台管理') !== false, 'successful installation omitted the backend link');

    $config = $root.DIRECTORY_SEPARATOR.'config.php';
    $content = "<?php\n\$dbconfig=array('host'=>'localhost');\n?>\n";
    check_install_flow(qifu_install_write_config($config, $content), 'absolute config write failed');
    check_install_flow(file_get_contents($config) === $content, 'written config content did not match');
} finally {
    if(isset($config) && is_file($config)) unlink($config);
    if(isset($lock) && is_file($lock)) unlink($lock);
    if(isset($lock) && is_dir($lock)) rmdir($lock);
    rmdir($root);
}

if($failures){
    fwrite(STDERR, "Install flow tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Install flow tests passed.\n";
?>
