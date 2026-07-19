<?php
if(!defined('IN_CRONLITE') && !defined('QIFU_INSTALL_CONTEXT') && PHP_SAPI !== 'cli') exit();

function qifu_admin_directory_valid($path){
    return is_dir($path)
        && is_file($path.DIRECTORY_SEPARATOR.'index.php')
        && is_file($path.DIRECTORY_SEPARATOR.'login.php')
        && is_file($path.DIRECTORY_SEPARATOR.'head.php')
        && is_file($path.DIRECTORY_SEPARATOR.'saiadmin-skin.css');
}

function qifu_admin_directory_name($root_path = null, $refresh = false){
    static $cache = array();
    $root_path = $root_path === null && defined('ROOT') ? ROOT : (string)$root_path;
    $root_real = realpath($root_path);
    if($root_real === false) return 'admin';
    $cache_key = str_replace('\\', '/', $root_real);
    if(!$refresh && isset($cache[$cache_key])) return $cache[$cache_key];

    $script_file = isset($_SERVER['SCRIPT_FILENAME']) ? realpath((string)$_SERVER['SCRIPT_FILENAME']) : false;
    if($script_file !== false){
        $script_dir = realpath(dirname($script_file));
        if($script_dir !== false && realpath(dirname($script_dir)) === $root_real && qifu_admin_directory_valid($script_dir)){
            return $cache[$cache_key] = basename($script_dir);
        }
    }

    $candidates = array();
    foreach(scandir($root_real) as $entry){
        if($entry === '.' || $entry === '..') continue;
        $candidate = $root_real.DIRECTORY_SEPARATOR.$entry;
        if(qifu_admin_directory_valid($candidate)) $candidates[] = $entry;
    }
    if(empty($candidates)) return $cache[$cache_key] = 'admin';
    natcasesort($candidates);
    $candidates = array_values($candidates);
    $custom = array_values(array_filter($candidates, function($entry){ return strcasecmp($entry, 'admin') !== 0; }));
    if(count($custom) === 1) return $cache[$cache_key] = $custom[0];
    foreach($candidates as $candidate){
        if(strcasecmp($candidate, 'admin') === 0) return $cache[$cache_key] = $candidate;
    }
    return $cache[$cache_key] = $candidates[0];
}

function qifu_admin_url_segment($directory){
    $directory = trim(str_replace(array('/', '\\'), '', (string)$directory));
    return rawurlencode($directory === '' ? 'admin' : $directory);
}

function qifu_site_base_path($sitepath, $admin_directory){
    $sitepath = rtrim(str_replace('\\', '/', (string)$sitepath), '/');
    $admin_directory = trim(str_replace(array('/', '\\'), '', (string)$admin_directory));
    $removed_custom_admin = false;
    $segments = array('install');
    if($admin_directory !== '') $segments[] = $admin_directory;
    foreach($segments as $segment){
        $suffix = '/'.$segment;
        if(strlen($sitepath) >= strlen($suffix) && strcasecmp(substr($sitepath, -strlen($suffix)), $suffix) === 0){
            $sitepath = substr($sitepath, 0, -strlen($suffix));
            $removed_custom_admin = strcasecmp($segment, $admin_directory) === 0;
            break;
        }
    }
    if($removed_custom_admin && strcasecmp($admin_directory, 'admin') !== 0){
        $legacy_suffix = '/admin';
        if(strlen($sitepath) >= strlen($legacy_suffix) && strcasecmp(substr($sitepath, -strlen($legacy_suffix)), $legacy_suffix) === 0){
            $sitepath = substr($sitepath, 0, -strlen($legacy_suffix));
        }
    }
    return $sitepath;
}

function qifu_admin_request_is_admin($script_name, $root_path = null, $admin_directory = null){
    if($admin_directory === null) $admin_directory = qifu_admin_directory_name($root_path);
    $admin_directory = (string)$admin_directory;
    $normalized = str_replace('\\', '/', rawurldecode((string)$script_name));
    $segments = array_values(array_filter(explode('/', $normalized), 'strlen'));
    foreach($segments as $segment){
        if(hash_equals(strtolower($admin_directory), strtolower($segment))) return true;
    }

    if($root_path !== null && isset($_SERVER['SCRIPT_FILENAME'])){
        $root_real = realpath((string)$root_path);
        $script_dir = realpath(dirname((string)$_SERVER['SCRIPT_FILENAME']));
        $admin_real = $root_real !== false ? realpath($root_real.DIRECTORY_SEPARATOR.$admin_directory) : false;
        if($script_dir !== false && $admin_real !== false && $script_dir === $admin_real) return true;
    }
    return false;
}
?>
