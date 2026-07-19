<?php
if(!defined('IN_CRONLITE') && !defined('QIFU_INSTALL_CONTEXT') && PHP_SAPI !== 'cli') exit();

function qifu_media_local_relative_path($value){
    $value = trim(str_replace('\\', '/', (string)$value));
    if($value === '' || strpos($value, "\0") !== false) return false;
    $candidate = $value;
    if(strpos($candidate, '//') === 0) $candidate = 'https:'.$candidate;
    if(preg_match('#^https?://#i', $candidate)){
        $parts = @parse_url($candidate);
        if(!$parts || !isset($parts['path'])) return false;
        $candidate = $parts['path'];
    } else {
        $candidate = preg_split('/[?#]/', $candidate, 2)[0];
    }
    $candidate = rawurldecode($candidate);
    if(!preg_match('#(?:^|/)(images/(?:ad|bg|logo|avatar)/.+)$#i', $candidate, $match)) return false;
    $relative = preg_replace('#/+#', '/', trim($match[1], '/'));
    $segments = explode('/', $relative);
    foreach($segments as $segment){
        if($segment === '' || $segment === '.' || $segment === '..') return false;
    }
    return $relative;
}

function qifu_media_upload_url($relative_path, $root_url){
    $relative = qifu_media_local_relative_path($relative_path);
    if($relative === false) return '';
    $parts = @parse_url((string)$root_url);
    $base_path = $parts && isset($parts['path']) ? rtrim($parts['path'], '/') : '';
    if($base_path === '/') $base_path = '';
    return $base_path.'/'.$relative;
}

function qifu_media_normalize_url($value, $root_url){
    $value = trim((string)$value);
    if($value === '') return '';
    $comparison = $value;
    if(strpos($comparison, '//') === 0) $comparison = 'https:'.$comparison;
    if(preg_match('#^https?://#i', $comparison)){
        $source = @parse_url($comparison);
        $root = @parse_url((string)$root_url);
        if(!$source || !$root || empty($source['host']) || empty($root['host']) || strcasecmp($source['host'], $root['host']) !== 0) return $value;
    }
    $relative = qifu_media_local_relative_path($value);
    return $relative === false ? $value : qifu_media_upload_url($relative, $root_url);
}

function qifu_media_normalize_config($config, $root_url){
    if(!is_array($config)) return array();
    $keys = array(
        'site_logo', 'admin_avatar', 'bg_custom',
        'ad_image', 'ad_image2', 'ad_image3', 'ad_image4',
        'ad_right_image', 'ad_left_image'
    );
    foreach($keys as $key){
        if(isset($config[$key])) $config[$key] = qifu_media_normalize_url($config[$key], $root_url);
    }
    return $config;
}
?>
