<?php
if(!defined('IN_CRONLITE')) exit();

function qifu_site_icon_sources($url){
    $url = trim((string)$url);
    if($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return array();
    $parts = parse_url($url);
    if(!$parts || empty($parts['scheme']) || empty($parts['host'])) return array();
    $scheme = strtolower($parts['scheme']);
    if(!in_array($scheme, array('http', 'https'), true) || isset($parts['user']) || isset($parts['pass'])) return array();

    $host = strtolower(trim($parts['host'], '[]'));
    if($host === '' || strlen($host) > 253) return array();
    $host_for_url = strpos($host, ':') !== false ? '['.$host.']' : $host;
    $port = isset($parts['port']) ? ':'.intval($parts['port']) : '';
    $origin_icon = $scheme.'://'.$host_for_url.$port.'/favicon.ico';
    $cached_icon = 'site_icon.php?url='.rawurlencode($url);
    $cached_retry = $cached_icon.'&retry=1';
    $service_icon = 'https://favicon.im/'.rawurlencode($host).'?larger=true';
    $google_icon = 'https://www.google.com/s2/favicons?domain_url='.rawurlencode($url).'&sz=64';
    return array_values(array_unique(array($origin_icon, $cached_icon, $service_icon, $cached_retry, $google_icon)));
}

function qifu_site_icon_discover_url($html, $page_url){
    if(!class_exists('DOMDocument') || trim((string)$html) === '') return false;
    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $loaded = @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $candidates = array();
    if($loaded){
        $xpath = new DOMXPath($document);
        $nodes = @$xpath->query("//link[contains(translate(@rel, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'icon')]");
        if($nodes){
            foreach($nodes as $node){
                $href = trim((string)$node->getAttribute('href'));
                if($href === '' || stripos($href, 'data:') === 0) continue;
                $resolved = qifu_site_meta_redirect_url($page_url, $href);
                if($resolved === false) continue;
                $rel = strtolower((string)$node->getAttribute('rel'));
                $type = strtolower((string)$node->getAttribute('type'));
                $sizes = strtolower((string)$node->getAttribute('sizes'));
                $score = strpos($rel, 'apple-touch-icon') !== false ? 30 : 10;
                if(strpos($type, 'png') !== false || preg_match('/\.png(?:\?|$)/i', $resolved)) $score += 25;
                elseif(strpos($type, 'webp') !== false || preg_match('/\.webp(?:\?|$)/i', $resolved)) $score += 20;
                elseif(strpos($type, 'svg') !== false || preg_match('/\.svg(?:\?|$)/i', $resolved)) $score -= 50;
                if(preg_match('/(\d+)x(\d+)/', $sizes, $match)) $score += min(20, intval($match[1]) / 8);
                $candidates[] = array('url' => $resolved, 'score' => $score);
            }
        }
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if(!$candidates) return false;
    usort($candidates, function($left, $right){
        if($left['score'] == $right['score']) return 0;
        return $left['score'] > $right['score'] ? -1 : 1;
    });
    return $candidates[0]['url'];
}

function qifu_site_icon_image_type($data){
    if(!is_string($data) || $data === '' || strlen($data) > 524288) return false;
    if(substr($data, 0, 4) === "\x00\x00\x01\x00") return 'image/x-icon';
    $info = @getimagesizefromstring($data);
    if(!$info || empty($info['mime'])) return false;
    $allowed = array('image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon');
    return in_array(strtolower($info['mime']), $allowed, true) ? strtolower($info['mime']) : false;
}

function qifu_site_icon_fetch_image_once($target, &$error){
    $error = '';
    if(!function_exists('curl_init')){
        $error = '服务器未安装 cURL 扩展';
        return false;
    }
    $body = '';
    $headers = array();
    $too_large = false;
    $curl = curl_init($target['url']);
    $resolve_ip = strpos($target['ip'], ':') !== false ? '['.$target['ip'].']' : $target['ip'];
    $options = array(
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'QifuNavigation-Icon/1.4',
        CURLOPT_HTTPHEADER => array('Accept: image/avif,image/webp,image/png,image/*,*/*;q=0.5'),
        CURLOPT_RESOLVE => array($target['host'].':'.$target['port'].':'.$resolve_ip),
        CURLOPT_HEADERFUNCTION => function($curl_handle, $line) use (&$headers){
            $length = strlen($line);
            $position = strpos($line, ':');
            if($position !== false) $headers[strtolower(trim(substr($line, 0, $position)))] = trim(substr($line, $position + 1));
            return $length;
        },
        CURLOPT_WRITEFUNCTION => function($curl_handle, $chunk) use (&$body, &$too_large){
            if(strlen($body) + strlen($chunk) > 524288){
                $too_large = true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    );
    if(defined('CURLOPT_PROTOCOLS')) $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
    $ca_file = qifu_site_meta_ca_file();
    if($ca_file !== '') $options[CURLOPT_CAINFO] = $ca_file;
    curl_setopt_array($curl, $options);
    $success = curl_exec($curl);
    $status = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
    if($too_large || $success === false){
        $error = '图标下载失败';
        return false;
    }
    return array('status' => $status, 'headers' => $headers, 'body' => $body);
}

function qifu_site_icon_fetch_image($url, &$error){
    $error = '';
    $current_url = qifu_site_meta_normalize_url($url);
    if($current_url === false) return false;
    for($redirects = 0; $redirects <= 2; $redirects++){
        $target = qifu_site_meta_resolve_target($current_url, $error);
        if($target === false) return false;
        $response = qifu_site_icon_fetch_image_once($target, $error);
        if($response === false) return false;
        if($response['status'] >= 300 && $response['status'] < 400 && !empty($response['headers']['location'])){
            $current_url = qifu_site_meta_redirect_url($current_url, $response['headers']['location']);
            if($current_url === false) return false;
            continue;
        }
        if($response['status'] < 200 || $response['status'] >= 300) return false;
        $mime = qifu_site_icon_image_type($response['body']);
        return $mime ? array('data' => $response['body'], 'mime' => $mime) : false;
    }
    return false;
}

function qifu_site_icon_fetch_page($url, &$error){
    $current_url = qifu_site_meta_normalize_url($url);
    if($current_url === false) return false;
    for($redirects = 0; $redirects <= 2; $redirects++){
        $target = qifu_site_meta_resolve_target($current_url, $error);
        if($target === false) return false;
        $response = qifu_site_meta_fetch_once($target, $error, true);
        if($response === false) return false;
        if($response['status'] >= 300 && $response['status'] < 400 && !empty($response['headers']['location'])){
            $current_url = qifu_site_meta_redirect_url($current_url, $response['headers']['location']);
            if($current_url === false) return false;
            continue;
        }
        if($response['status'] < 200 || $response['status'] >= 300) return false;
        return array('url' => $current_url, 'html' => $response['body']);
    }
    return false;
}

function qifu_site_icon_cache_directory(){
    $directory = rtrim(sys_get_temp_dir(), '/\\').DIRECTORY_SEPARATOR.'qifu-site-icons-'.substr(hash('sha256', ROOT), 0, 12);
    if(!is_dir($directory) && !@mkdir($directory, 0700, true)) return false;
    return $directory;
}

function qifu_site_icon_cached($url, &$error){
    $error = '';
    $normalized = qifu_site_meta_normalize_url($url);
    if($normalized === false) return false;
    $directory = qifu_site_icon_cache_directory();
    if($directory === false) return false;
    $key = hash('sha256', $normalized);
    $data_file = $directory.DIRECTORY_SEPARATOR.$key.'.bin';
    $miss_file = $directory.DIRECTORY_SEPARATOR.$key.'.miss';
    $lock_file = $directory.DIRECTORY_SEPARATOR.$key.'.lock';
    if(is_file($data_file) && filemtime($data_file) >= time() - 604800){
        $data = @file_get_contents($data_file);
        $mime = qifu_site_icon_image_type($data);
        if($mime) return array('data' => $data, 'mime' => $mime, 'cached' => true);
    }
    if(is_file($miss_file) && filemtime($miss_file) >= time() - 3600) return false;

    $lock = @fopen($lock_file, 'c');
    if($lock) @flock($lock, LOCK_EX);
    if(is_file($data_file) && filemtime($data_file) >= time() - 604800){
        $data = @file_get_contents($data_file);
        $mime = qifu_site_icon_image_type($data);
        if($mime){
            if($lock){ @flock($lock, LOCK_UN); fclose($lock); }
            return array('data' => $data, 'mime' => $mime, 'cached' => true);
        }
    }

    $parts = parse_url($normalized);
    $host = strpos($parts['host'], ':') !== false ? '['.trim($parts['host'], '[]').']' : $parts['host'];
    $port = isset($parts['port']) ? ':'.intval($parts['port']) : '';
    $root_icon = $parts['scheme'].'://'.$host.$port.'/favicon.ico';
    $icon = qifu_site_icon_fetch_image($root_icon, $error);
    if($icon === false){
        $page = qifu_site_icon_fetch_page($normalized, $error);
        if($page !== false){
            $declared_url = qifu_site_icon_discover_url($page['html'], $page['url']);
            if($declared_url !== false) $icon = qifu_site_icon_fetch_image($declared_url, $error);
        }
    }
    if($icon !== false){
        @file_put_contents($data_file, $icon['data'], LOCK_EX);
        @touch($data_file);
        if(is_file($miss_file)) @unlink($miss_file);
        $icon['cached'] = false;
    }else{
        @file_put_contents($miss_file, (string)time(), LOCK_EX);
    }
    if($lock){ @flock($lock, LOCK_UN); fclose($lock); }
    return $icon;
}
?>
