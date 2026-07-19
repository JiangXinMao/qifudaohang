<?php
/* 祈福导航系统 V1.5 本地演示路由 */

$request_uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '/';
$raw_path = parse_url($request_uri, PHP_URL_PATH);
$raw_path = is_string($raw_path) ? $raw_path : '/';
$path = rawurldecode($raw_path);

$blocked = preg_match('#^/(?:backup|tests|\.local|includes/(?:sqlite|360safe))(?:/|$)#i', $path)
    || preg_match('#^/includes/.*\.php$#i', $path)
    || preg_match('#^/(?:config\.php|install/install\.lock|router\.php)$#i', $path);

if($blocked){
    http_response_code(404);
    exit;
}

$file = __DIR__.str_replace('/', DIRECTORY_SEPARATOR, $path);
if($path !== '/' && is_file($file)) return false;

if($path !== '/' && is_dir($file)){
    if(substr($raw_path, -1) !== '/'){
        $location = $raw_path.'/';
        $query = parse_url($request_uri, PHP_URL_QUERY);
        if(is_string($query) && $query !== '') $location .= '?'.$query;
        header('Location: '.$location, true, 302);
        exit;
    }
    $index = rtrim($file, '/\\').DIRECTORY_SEPARATOR.'index.php';
    if(is_file($index)){
        require $index;
        return true;
    }
}

if($path === '/'){
    require __DIR__.'/index.php';
    return true;
}

http_response_code(404);
exit;
