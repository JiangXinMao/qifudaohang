<?php
declare(strict_types=1);

if(!defined('IN_CRONLITE') && PHP_SAPI !== 'cli') exit;

/*
 * 官方新版本更新执行模块。
 *
 * 数据流程：telemetry.php 查询签名版本清单 -> SDK 校验远程签名与文件摘要
 * -> 本文件安全解压、保护用户配置与数据、暂存并提交新版本文件。
 * 发布凭据只允许注入正式 Release 安装包，不得提交到公共源码仓库。
 */

if(!defined('QIFU_UPDATE_MAX_ARCHIVE_BYTES')) define('QIFU_UPDATE_MAX_ARCHIVE_BYTES', 2147483648);
if(!defined('QIFU_UPDATE_MAX_EXTRACTED_BYTES')) define('QIFU_UPDATE_MAX_EXTRACTED_BYTES', 1073741824);
if(!defined('QIFU_UPDATE_MAX_FILE_BYTES')) define('QIFU_UPDATE_MAX_FILE_BYTES', 268435456);
if(!defined('QIFU_UPDATE_MAX_FILES')) define('QIFU_UPDATE_MAX_FILES', 20000);

function qifu_online_update_version_key($version){
    $value = ltrim(trim((string)$version), "vV ");
    if(preg_match('/^\d+\.\d+$/', $value)) $value .= '.0';
    return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $value) ? strtolower($value) : '';
}

function qifu_online_update_display_version($version){
    $key = qifu_online_update_version_key($version);
    return $key === '' ? '' : 'V'.$key;
}

function qifu_online_update_remove_tree($path){
    if(!file_exists($path) && !is_link($path)) return;
    if(is_file($path) || is_link($path)){ @unlink($path); return; }
    $entries = @scandir($path);
    if(is_array($entries)){
        foreach($entries as $entry){
            if($entry === '.' || $entry === '..') continue;
            qifu_online_update_remove_tree($path.DIRECTORY_SEPARATOR.$entry);
        }
    }
    @rmdir($path);
}

function qifu_online_update_mkdir($path){
    if(is_dir($path)) return true;
    if(file_exists($path)) return false;
    return @mkdir($path, 0770, true) || is_dir($path);
}

function qifu_online_update_normalize_entry($name){
    $name = str_replace('\\', '/', (string)$name);
    if($name === '' || strpos($name, "\0") !== false) return '';
    if($name[0] === '/' || preg_match('/^[A-Za-z]:\//', $name)) return '';
    while(substr($name, 0, 2) === './') $name = substr($name, 2);
    $directory = substr($name, -1) === '/';
    $segments = explode('/', rtrim($name, '/'));
    $clean = array();
    foreach($segments as $segment){
        if($segment === '' || $segment === '.') continue;
        if($segment === '..' || preg_match('/[\x00-\x1F]/', $segment)) return '';
        $clean[] = $segment;
    }
    if(!$clean) return '';
    return implode('/', $clean).($directory ? '/' : '');
}

function qifu_online_update_entry_is_symlink($zip, $index){
    $operations = 0;
    $attributes = 0;
    if(!method_exists($zip, 'getExternalAttributesIndex')) return false;
    if(!$zip->getExternalAttributesIndex($index, $operations, $attributes)) return false;
    $mode = ($attributes >> 16) & 0170000;
    return $mode === 0120000;
}

function qifu_online_update_extract($archive, $destination){
    if(!class_exists('ZipArchive')) throw new RuntimeException('PHP Zip 扩展未启用，无法解压更新包。');
    if(!is_file($archive) || filesize($archive) <= 0) throw new RuntimeException('更新包不存在或为空。');
    if(filesize($archive) > QIFU_UPDATE_MAX_ARCHIVE_BYTES) throw new RuntimeException('更新包超过允许的大小。');
    if(!qifu_online_update_mkdir($destination)) throw new RuntimeException('无法创建更新暂存目录。');

    $zip = new ZipArchive();
    $opened = $zip->open($archive, ZipArchive::RDONLY);
    if($opened !== true) throw new RuntimeException('更新包不是有效的 ZIP 文件。');
    $total = 0;
    try {
        if($zip->numFiles < 1 || $zip->numFiles > QIFU_UPDATE_MAX_FILES) throw new RuntimeException('更新包文件数量异常。');
        for($index = 0; $index < $zip->numFiles; $index++){
            $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
            if(!is_array($stat) || !isset($stat['name'])) throw new RuntimeException('更新包目录信息损坏。');
            $raw_name = (string)$stat['name'];
            if($raw_name === '.' || $raw_name === './' || $raw_name === '.\\') continue;
            $relative = qifu_online_update_normalize_entry($raw_name);
            if($relative === '') throw new RuntimeException('更新包包含 unsafe 路径，已拒绝安装。');
            if(qifu_online_update_entry_is_symlink($zip, $index)) throw new RuntimeException('更新包包含不允许的符号链接。');
            if(isset($stat['encryption_method']) && intval($stat['encryption_method']) !== 0) throw new RuntimeException('更新包不能包含加密文件。');
            $is_directory = substr($relative, -1) === '/';
            $target = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, rtrim($relative, '/'));
            if($is_directory){
                if(!qifu_online_update_mkdir($target)) throw new RuntimeException('无法创建更新目录：'.$relative);
                continue;
            }
            $size = isset($stat['size']) ? intval($stat['size']) : 0;
            if($size < 0 || $size > QIFU_UPDATE_MAX_FILE_BYTES) throw new RuntimeException('更新包中的单个文件过大。');
            $total += $size;
            if($total > QIFU_UPDATE_MAX_EXTRACTED_BYTES) throw new RuntimeException('更新包解压后超过允许的大小。');
            if(!qifu_online_update_mkdir(dirname($target))) throw new RuntimeException('无法创建更新文件目录。');
            $input = $zip->getStream($raw_name);
            $output = @fopen($target, 'wb');
            if(!is_resource($input) || !is_resource($output)){
                if(is_resource($input)) fclose($input);
                if(is_resource($output)) fclose($output);
                throw new RuntimeException('无法读取更新包文件：'.$relative);
            }
            $written = 0;
            while(!feof($input)){
                $chunk = fread($input, 1048576);
                if($chunk === false) throw new RuntimeException('读取更新包时发生错误。');
                if($chunk === '') continue;
                $length = strlen($chunk);
                if(fwrite($output, $chunk) !== $length) throw new RuntimeException('写入更新暂存文件失败。');
                $written += $length;
                if($written > QIFU_UPDATE_MAX_FILE_BYTES) throw new RuntimeException('更新包中的文件超过安全限制。');
            }
            fclose($input);
            fclose($output);
            if($written !== $size) throw new RuntimeException('更新包文件大小校验失败。');
        }
    } finally {
        $zip->close();
    }
}

function qifu_online_update_package_root($staging){
    $required = array('index.php', 'install/index.php', 'includes/common.php', 'includes/brand.php', 'includes/online_update.php', 'admin/index.php', 'admin/login.php', 'admin/head.php', 'admin/saiadmin-skin.css');
    $valid = static function($root) use ($required){
        foreach($required as $path){
            if(!is_file($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path))) return false;
        }
        return true;
    };
    if($valid($staging)) return $staging;
    $directories = array();
    foreach(@scandir($staging) ?: array() as $entry){
        if($entry === '.' || $entry === '..') continue;
        if(is_dir($staging.DIRECTORY_SEPARATOR.$entry)) $directories[] = $entry;
    }
    if(count($directories) === 1){
        $candidate = $staging.DIRECTORY_SEPARATOR.$directories[0];
        if($valid($candidate)) return $candidate;
    }
    throw new RuntimeException('更新包结构不完整，缺少必要的程序文件。');
}

function qifu_online_update_package_version($package_root){
    $brand = @file_get_contents($package_root.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'brand.php');
    if(!is_string($brand)) return '';
    if(!preg_match('/QIFU_PRODUCT_VERSION[\'\"]\s*,\s*[\'\"]([^\'\"]+)[\'\"]/', $brand, $matches)) return '';
    return qifu_online_update_display_version($matches[1]);
}

function qifu_online_update_protected($relative){
    $relative = strtolower(str_replace('\\', '/', trim((string)$relative, '/')));
    if($relative === 'config.php' || $relative === 'install/install.lock') return true;
    foreach(array('.qifu-data/', '.qifu-update/', 'includes/sqlite/', 'includes/.telemetry/', 'backup/') as $prefix){
        if(strpos($relative.'/', $prefix) === 0) return true;
    }
    return false;
}

function qifu_online_update_files($root){
    $files = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file){
        if(!$file->isFile() || $file->isLink()) continue;
        $absolute = $file->getPathname();
        $relative = str_replace('\\', '/', substr($absolute, strlen(rtrim($root, '\\/')) + 1));
        $files[$relative] = $absolute;
    }
    ksort($files, SORT_STRING);
    return $files;
}

function qifu_online_update_target_relative($relative, $admin_directory){
    $relative = str_replace('\\', '/', $relative);
    if($relative === 'admin') return $admin_directory;
    if(strpos($relative, 'admin/') === 0) return $admin_directory.'/'.substr($relative, 6);
    return $relative;
}

function qifu_online_update_replace_file($source, $target){
    if(is_link($target) || is_dir($target)) throw new RuntimeException('目标路径不是普通文件：'.basename($target));
    if(!qifu_online_update_mkdir(dirname($target))) throw new RuntimeException('无法创建目标目录。');
    $temporary = $target.'.qifu-tmp-'.bin2hex(random_bytes(6));
    if(!@copy($source, $temporary)) throw new RuntimeException('无法写入更新文件：'.basename($target));
    $source_hash = @hash_file('sha256', $source);
    $target_hash = @hash_file('sha256', $temporary);
    if(!is_string($source_hash) || !is_string($target_hash) || !hash_equals($source_hash, $target_hash)){
        @unlink($temporary);
        throw new RuntimeException('更新文件写入校验失败：'.basename($target));
    }
    if(@rename($temporary, $target)) return;
    if(is_file($target) && !@unlink($target)){
        @unlink($temporary);
        throw new RuntimeException('目标文件正在使用，无法更新：'.basename($target));
    }
    if(!@rename($temporary, $target)){
        @unlink($temporary);
        throw new RuntimeException('无法替换程序文件：'.basename($target));
    }
}

function qifu_online_update_write_manifest($path, $manifest){
    $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if(!is_string($json) || @file_put_contents($path, $json, LOCK_EX) === false) throw new RuntimeException('无法写入更新回滚清单。');
}

function qifu_online_update_progress_id($value){
    $value = trim((string)$value);
    return preg_match('/^[A-Za-z0-9_-]{8,80}$/D', $value) ? $value : '';
}

function qifu_online_update_progress_write($target_root, $request_id, $phase, $percentage, $message, $status = 'running'){
    $request_id = qifu_online_update_progress_id($request_id);
    if($request_id === '') return false;
    $directory = rtrim((string)$target_root, '\\/').DIRECTORY_SEPARATOR.'.qifu-update'.DIRECTORY_SEPARATOR.'progress';
    if(!qifu_online_update_mkdir($directory)) return false;
    foreach(@glob($directory.DIRECTORY_SEPARATOR.'*.json') ?: array() as $old_file){
        if(@filemtime($old_file) !== false && @filemtime($old_file) < time() - 86400) @unlink($old_file);
    }
    $payload = array(
        'requestId'=>$request_id,
        'phase'=>preg_replace('/[^a-z_]/', '', strtolower((string)$phase)),
        'percentage'=>max(0, min(100, intval($percentage))),
        'message'=>trim((string)$message),
        'status'=>in_array($status, array('running','completed','failed'), true) ? $status : 'running',
        'updatedAt'=>time()
    );
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if(!is_string($json)) return false;
    $path = $directory.DIRECTORY_SEPARATOR.$request_id.'.json';
    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));
    if(@file_put_contents($temporary, $json, LOCK_EX) === false) return false;
    if(@rename($temporary, $path)) return $payload;
    if(is_file($path)) @unlink($path);
    if(@rename($temporary, $path)) return $payload;
    @unlink($temporary);
    return false;
}

function qifu_online_update_progress_read($target_root, $request_id){
    $request_id = qifu_online_update_progress_id($request_id);
    if($request_id === '') return null;
    $path = rtrim((string)$target_root, '\\/').DIRECTORY_SEPARATOR.'.qifu-update'.DIRECTORY_SEPARATOR.'progress'.DIRECTORY_SEPARATOR.$request_id.'.json';
    if(!is_file($path)) return null;
    $payload = json_decode((string)@file_get_contents($path), true);
    return is_array($payload) ? $payload : null;
}

function qifu_online_update_progress_emit($callback, $phase, $percentage, $message, $status = 'running'){
    if(is_callable($callback)) call_user_func($callback, $phase, intval($percentage), $message, $status);
}

function qifu_online_update_commit($package_root, $target_root, $admin_directory, $version, $operation_id, $progress_callback = null){
    $target_root = rtrim($target_root, '\\/');
    $backup_root = $target_root.DIRECTORY_SEPARATOR.'.qifu-update'.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.$operation_id;
    $backup_files = $backup_root.DIRECTORY_SEPARATOR.'files';
    if(!qifu_online_update_mkdir($backup_files)) throw new RuntimeException('无法创建更新备份目录。');
    $manifest = array('operationId'=>$operation_id, 'version'=>$version, 'startedAt'=>time(), 'completedAt'=>0, 'status'=>'running', 'overwritten'=>array(), 'created'=>array());
    qifu_online_update_write_manifest($backup_root.DIRECTORY_SEPARATOR.'manifest.json', $manifest);

    $created_directories = array();
    $package_files = qifu_online_update_files($package_root);
    $total_files = max(1, count($package_files));
    $processed_files = 0;
    $last_progress = 64;
    try {
        foreach($package_files as $relative=>$source){
            if(qifu_online_update_protected($relative)) continue;
            $target_relative = qifu_online_update_target_relative($relative, $admin_directory);
            if(qifu_online_update_protected($target_relative)) continue;
            $target = $target_root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $target_relative);
            $parent = dirname($target);
            if(!is_dir($parent)){
                if(!qifu_online_update_mkdir($parent)) throw new RuntimeException('无法创建程序目录：'.$target_relative);
                $created_directories[] = $parent;
            }
            if(is_link($target) || is_dir($target)) throw new RuntimeException('目标路径冲突：'.$target_relative);
            if(is_file($target)){
                $backup = $backup_files.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $target_relative);
                if(!qifu_online_update_mkdir(dirname($backup)) || !@copy($target, $backup)) throw new RuntimeException('无法备份程序文件：'.$target_relative);
                $manifest['overwritten'][] = $target_relative;
            } else {
                $manifest['created'][] = $target_relative;
            }
            qifu_online_update_write_manifest($backup_root.DIRECTORY_SEPARATOR.'manifest.json', $manifest);
            qifu_online_update_replace_file($source, $target);
            $processed_files++;
            $current_progress = min(94, 65 + intval(($processed_files / $total_files) * 29));
            if($current_progress > $last_progress){
                $last_progress = $current_progress;
                qifu_online_update_progress_emit($progress_callback, 'overlay', $current_progress, '正在覆盖程序文件（'.$processed_files.'/'.$total_files.'）');
            }
        }
        $manifest['status'] = 'completed';
        $manifest['completedAt'] = time();
        qifu_online_update_write_manifest($backup_root.DIRECTORY_SEPARATOR.'manifest.json', $manifest);
        qifu_online_update_progress_emit($progress_callback, 'overlay', 95, '程序文件覆盖完成，正在进行最后检查');
        return array(
            'version'=>$version,
            'operationId'=>$operation_id,
            'changedFiles'=>count($manifest['overwritten']) + count($manifest['created']),
            'backupPath'=>'.qifu-update/backups/'.$operation_id
        );
    } catch(Throwable $error){
        qifu_online_update_progress_emit($progress_callback, 'overlay', max(65, $last_progress), '覆盖出现异常，正在自动恢复原文件');
        $rollback_errors = array();
        foreach(array_reverse($manifest['overwritten']) as $relative){
            try {
                $backup = $backup_files.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                $target = $target_root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if(is_file($backup)) qifu_online_update_replace_file($backup, $target);
            } catch(Throwable $rollback_error){ $rollback_errors[] = $relative; }
        }
        foreach(array_reverse($manifest['created']) as $relative){
            $target = $target_root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if(is_file($target) || is_link($target)) @unlink($target);
        }
        usort($created_directories, static function($a, $b){ return strlen($b) <=> strlen($a); });
        foreach($created_directories as $directory) @rmdir($directory);
        $manifest['status'] = $rollback_errors ? 'rollback_incomplete' : 'rolled_back';
        $manifest['completedAt'] = time();
        $manifest['error'] = $error->getMessage();
        $manifest['rollbackErrors'] = $rollback_errors;
        @qifu_online_update_write_manifest($backup_root.DIRECTORY_SEPARATOR.'manifest.json', $manifest);
        if($rollback_errors) throw new RuntimeException('更新失败且部分文件无法自动回滚，请从备份目录恢复。');
        throw new RuntimeException('更新失败，已自动恢复原版本：'.$error->getMessage());
    }
}

function qifu_online_update_install_archive($archive, $update, $target_root, $admin_directory = 'admin', $progress_callback = null){
    $target_root = rtrim((string)$target_root, '\\/');
    if(!is_dir($target_root)) throw new RuntimeException('站点根目录不存在。');
    $admin_directory = trim(str_replace(array('/', '\\'), '', (string)$admin_directory));
    if($admin_directory === '') $admin_directory = 'admin';
    $expected_version = qifu_online_update_display_version(isset($update['version']) ? $update['version'] : '');
    if($expected_version === '') throw new RuntimeException('远程版本号格式无效。');
    $operation_id = date('Ymd-His').'-'.bin2hex(random_bytes(4));
    $staging = $target_root.DIRECTORY_SEPARATOR.'.qifu-update'.DIRECTORY_SEPARATOR.'staging'.DIRECTORY_SEPARATOR.$operation_id;
    try {
        qifu_online_update_progress_emit($progress_callback, 'overlay', 60, '正在解压并检查更新包结构');
        qifu_online_update_extract($archive, $staging);
        $package_root = qifu_online_update_package_root($staging);
        $package_version = qifu_online_update_package_version($package_root);
        if($package_version === '' || qifu_online_update_version_key($package_version) !== qifu_online_update_version_key($expected_version)){
            throw new RuntimeException('更新包版本与远程发布版本不一致。');
        }
        qifu_online_update_progress_emit($progress_callback, 'overlay', 64, '更新包检查通过，准备备份并覆盖文件');
        return qifu_online_update_commit($package_root, $target_root, $admin_directory, $package_version, $operation_id, $progress_callback);
    } finally {
        qifu_online_update_remove_tree($staging);
    }
}

function qifu_online_update_public_keys(){
    $keys = defined('DATACABIN_REMOTE_PUBLIC_KEYS_JSON') ? json_decode((string)DATACABIN_REMOTE_PUBLIC_KEYS_JSON, true) : array();
    if(is_array($keys) && $keys) return $keys;
    return defined('DATACABIN_REMOTE_PUBLIC_KEY') ? (string)DATACABIN_REMOTE_PUBLIC_KEY : null;
}

function qifu_online_update_apply($remote, $target_root = null, $progress_callback = null){
    $target_root = $target_root === null && defined('ROOT') ? ROOT : (string)$target_root;
    if(!is_array($remote) || !isset($remote['update']) || !is_array($remote['update'])) throw new RuntimeException('没有可用的远程更新。');
    $update = $remote['update'];
    $remote_version = qifu_online_update_version_key(isset($update['version']) ? $update['version'] : '');
    $current_version = qifu_online_update_version_key(defined('QIFU_PRODUCT_VERSION') ? QIFU_PRODUCT_VERSION : '1.0.0');
    if($remote_version === '' || $current_version === '' || !version_compare($remote_version, $current_version, '>')) throw new RuntimeException('当前已经是最新版本。');
    if(isset($update['platform']) && (string)$update['platform'] !== '' && (string)$update['platform'] !== 'php-web') throw new RuntimeException('远程更新包不适用于当前程序。');
    if(!class_exists('ZipArchive')) throw new RuntimeException('PHP Zip 扩展未启用，请启用后再执行在线更新。');
    if(!function_exists('sodium_crypto_sign_verify_detached')) throw new RuntimeException('PHP Sodium 扩展未启用，无法验证更新签名。');
    if(!function_exists('qifu_telemetry_instance')) throw new RuntimeException('远程更新 SDK 未加载。');

    $state_root = rtrim($target_root, '\\/').DIRECTORY_SEPARATOR.'.qifu-update';
    if(!qifu_online_update_mkdir($state_root)) throw new RuntimeException('站点根目录不可写，无法创建更新目录。');
    $lock_handle = @fopen($state_root.DIRECTORY_SEPARATOR.'update.lock', 'c+b');
    if(!is_resource($lock_handle) || !@flock($lock_handle, LOCK_EX | LOCK_NB)){
        if(is_resource($lock_handle)) fclose($lock_handle);
        throw new RuntimeException('另一个更新任务正在运行，请稍后再试。');
    }

    $archive = $state_root.DIRECTORY_SEPARATOR.'downloads'.DIRECTORY_SEPARATOR.'update-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.zip';
    $maintenance = $state_root.DIRECTORY_SEPARATOR.'maintenance.json';
    try {
        qifu_online_update_progress_emit($progress_callback, 'download', 25, '远程发布信息验签通过，正在连接下载服务');
        $telemetry = qifu_telemetry_instance();
        if(!$telemetry) throw new RuntimeException('远程更新 SDK 初始化失败。');
        qifu_online_update_progress_emit($progress_callback, 'download', 32, '正在下载新版本安装包');
        $downloaded = $telemetry->downloadUpdate($update, $archive, qifu_online_update_public_keys(), 120.0);
        if(!is_string($downloaded) || !is_file($downloaded)) throw new RuntimeException('更新包下载或签名校验失败，请稍后重试。');
        qifu_online_update_progress_emit($progress_callback, 'download', 55, '安装包下载完成，文件签名校验通过');
        $maintenance_data = json_encode(array('startedAt'=>time(), 'targetVersion'=>qifu_online_update_display_version($remote_version)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if(!is_string($maintenance_data) || @file_put_contents($maintenance, $maintenance_data, LOCK_EX) === false) throw new RuntimeException('无法进入更新维护状态。');
        $admin_directory = function_exists('qifu_admin_directory_name') ? qifu_admin_directory_name($target_root, true) : 'admin';
        $result = qifu_online_update_install_archive($downloaded, $update, $target_root, $admin_directory, $progress_callback);
        if(function_exists('qifu_telemetry_track_daily')) qifu_telemetry_track_daily('online_update', true, array('version'=>$result['version']));
        qifu_online_update_progress_emit($progress_callback, 'complete', 100, '更新完成，正在重新载入后台', 'completed');
        return $result;
    } catch(Throwable $error){
        if(function_exists('qifu_telemetry_track_daily')) qifu_telemetry_track_daily('online_update', false, array('reason'=>substr($error->getMessage(), 0, 120)));
        throw $error;
    } finally {
        @unlink($maintenance);
        @unlink($archive);
        @flock($lock_handle, LOCK_UN);
        @fclose($lock_handle);
    }
}
