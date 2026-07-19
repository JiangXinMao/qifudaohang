<?php
if(!defined('IN_CRONLITE')) exit();

function qifu_site_meta_text($value, $limit){
    $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim(preg_replace('/\s+/u', ' ', $value));
    if($value === '') return '';
    return function_exists('mb_substr') ? mb_substr($value, 0, $limit, 'UTF-8') : substr($value, 0, $limit);
}

function qifu_site_meta_normalize_url($url){
    $url = trim((string)$url);
    if($url === '') return false;
    if(!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) $url = 'https://'.$url;
    $parts = @parse_url($url);
    if(!$parts || empty($parts['scheme']) || empty($parts['host'])) return false;
    $scheme = strtolower($parts['scheme']);
    if(!in_array($scheme, array('http', 'https'), true)) return false;
    if(isset($parts['user']) || isset($parts['pass'])) return false;
    $port = isset($parts['port']) ? intval($parts['port']) : ($scheme === 'https' ? 443 : 80);
    if(($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) return false;

    $host = strtolower(trim($parts['host'], '[]'));
    if($host === '' || strlen($host) > 253) return false;
    $host_for_url = strpos($host, ':') !== false ? '['.$host.']' : $host;
    $path = isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';
    $normalized = $scheme.'://'.$host_for_url.$path;
    if(isset($parts['query']) && $parts['query'] !== '') $normalized .= '?'.$parts['query'];
    return filter_var($normalized, FILTER_VALIDATE_URL) ? $normalized : false;
}

function qifu_site_meta_resolve_target($url, &$error){
    $error = '';
    $normalized = qifu_site_meta_normalize_url($url);
    if($normalized === false){
        $error = '请输入正确的网站域名或URL';
        return false;
    }
    $parts = parse_url($normalized);
    $resolved_ip = null;
    if(!qifu_public_http_url($normalized, $resolved_ip)){
        $error = '该地址无法访问或不允许抓取';
        return false;
    }
    return array(
        'url' => $normalized,
        'scheme' => strtolower($parts['scheme']),
        'host' => trim($parts['host'], '[]'),
        'port' => isset($parts['port']) ? intval($parts['port']) : (strtolower($parts['scheme']) === 'https' ? 443 : 80),
        'ip' => $resolved_ip,
    );
}

function qifu_site_meta_redirect_url($base_url, $location){
    $location = trim((string)$location);
    if($location === '') return false;
    if(preg_match('#^https?://#i', $location)) return qifu_site_meta_normalize_url($location);
    $base = parse_url($base_url);
    if(!$base || empty($base['scheme']) || empty($base['host'])) return false;
    if(strpos($location, '//') === 0) return qifu_site_meta_normalize_url($base['scheme'].':'.$location);

    $query = '';
    $query_pos = strpos($location, '?');
    if($query_pos !== false){
        $query = substr($location, $query_pos);
        $location = substr($location, 0, $query_pos);
    }
    if($location === '') $location = isset($base['path']) ? $base['path'] : '/';
    if($location[0] !== '/'){
        $base_path = isset($base['path']) ? $base['path'] : '/';
        $slash = strrpos($base_path, '/');
        $location = substr($base_path, 0, $slash === false ? 0 : $slash + 1).$location;
    }
    $segments = array();
    foreach(explode('/', $location) as $segment){
        if($segment === '' || $segment === '.') continue;
        if($segment === '..') array_pop($segments);
        else $segments[] = $segment;
    }
    $host = strpos($base['host'], ':') !== false ? '['.trim($base['host'], '[]').']' : $base['host'];
    return qifu_site_meta_normalize_url($base['scheme'].'://'.$host.'/'.implode('/', $segments).$query);
}

function qifu_site_meta_ca_file(){
    $candidates = array(
        ini_get('curl.cainfo'),
        ini_get('openssl.cafile'),
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/cert.pem',
    );
    if(defined('ROOT')) $candidates[] = ROOT.'includes/certs/ca-bundle.crt';
    if(DIRECTORY_SEPARATOR === '\\'){
        $program_files = getenv('ProgramFiles');
        if($program_files) $candidates[] = rtrim($program_files, '/\\').'\\Git\\mingw64\\etc\\ssl\\certs\\ca-bundle.crt';
    }
    foreach($candidates as $candidate){
        if(is_string($candidate) && $candidate !== '' && is_file($candidate) && is_readable($candidate)) return $candidate;
    }
    return '';
}

function qifu_site_meta_fetch_once($target, &$error, $allow_partial = false){
    $error = '';
    if(!function_exists('curl_init')){
        $error = '服务器未安装 cURL 扩展，暂时无法自动获取';
        return false;
    }
    $body = '';
    $headers = array();
    $too_large = false;
    $max_bytes = 1048576;
    $curl = curl_init($target['url']);
    $resolve_ip = strpos($target['ip'], ':') !== false ? '['.$target['ip'].']' : $target['ip'];
    $options = array(
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'QifuNavigation-Metadata/1.4',
        CURLOPT_HTTPHEADER => array('Accept: text/html,application/xhtml+xml;q=0.9'),
        CURLOPT_RESOLVE => array($target['host'].':'.$target['port'].':'.$resolve_ip),
        CURLOPT_HEADERFUNCTION => function($curl_handle, $line) use (&$headers){
            $length = strlen($line);
            $position = strpos($line, ':');
            if($position !== false){
                $name = strtolower(trim(substr($line, 0, $position)));
                $headers[$name] = trim(substr($line, $position + 1));
            }
            return $length;
        },
        CURLOPT_WRITEFUNCTION => function($curl_handle, $chunk) use (&$body, &$too_large, $max_bytes){
            if(strlen($body) + strlen($chunk) > $max_bytes){
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
    $content_type = strtolower((string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE));
    $curl_error = curl_error($curl);

    if($too_large && !$allow_partial){
        $error = '目标网页内容过大，无法自动获取';
        return false;
    }
    if($success === false && !$too_large){
        $error = $curl_error !== '' ? '目标网站连接失败' : '目标网站无法访问';
        return false;
    }
    if($status >= 200 && $status < 300){
        $looks_html = strpos($body, '<html') !== false || strpos($body, '<head') !== false || strpos($body, '<title') !== false;
        if($content_type !== '' && strpos($content_type, 'text/html') === false && strpos($content_type, 'application/xhtml+xml') === false && !$looks_html){
            $error = '目标地址不是网页内容';
            return false;
        }
    }
    return array('status' => $status, 'headers' => $headers, 'body' => $body, 'truncated' => $too_large);
}

function qifu_site_meta_xpath_value($xpath, $query){
    $nodes = @$xpath->query($query);
    if(!$nodes || $nodes->length < 1) return '';
    return trim((string)$nodes->item(0)->nodeValue);
}

function qifu_site_meta_parse_html($html){
    $html = (string)$html;
    if($html === '') return array('name' => '', 'description' => '');
    if(function_exists('mb_detect_encoding')){
        $encoding = mb_detect_encoding($html, array('UTF-8', 'GB18030', 'GBK', 'BIG-5', 'ISO-8859-1'), true);
        if($encoding && strtoupper($encoding) !== 'UTF-8') $html = mb_convert_encoding($html, 'UTF-8', $encoding);
    }
    $name = '';
    $description = '';
    if(class_exists('DOMDocument')){
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if($loaded){
            $xpath = new DOMXPath($document);
            $lower = "translate(%s, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')";
            $name = qifu_site_meta_xpath_value($xpath, "//meta[".sprintf($lower, '@property')."='og:site_name']/@content");
            if($name === '') $name = qifu_site_meta_xpath_value($xpath, "//meta[".sprintf($lower, '@name')."='application-name']/@content");
            if($name === '') $name = qifu_site_meta_xpath_value($xpath, '//title');
            if($name === '') $name = qifu_site_meta_xpath_value($xpath, "//meta[".sprintf($lower, '@property')."='og:title']/@content");
            $description = qifu_site_meta_xpath_value($xpath, "//meta[".sprintf($lower, '@name')."='description']/@content");
            if($description === '') $description = qifu_site_meta_xpath_value($xpath, "//meta[".sprintf($lower, '@property')."='og:description']/@content");
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    if($name === '' && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match)) $name = $match[1];
    return array(
        'name' => qifu_site_meta_text($name, 100),
        'description' => qifu_site_meta_text($description, 255),
    );
}

function qifu_site_meta_fetch($url, &$error){
    $error = '';
    $current_url = qifu_site_meta_normalize_url($url);
    if($current_url === false){
        $error = '请输入正确的网站域名或URL';
        return false;
    }
    for($redirects = 0; $redirects <= 3; $redirects++){
        $target = qifu_site_meta_resolve_target($current_url, $error);
        if($target === false) return false;
        $response = qifu_site_meta_fetch_once($target, $error);
        if($response === false) return false;
        if($response['status'] >= 300 && $response['status'] < 400){
            if(empty($response['headers']['location'])){
                $error = '目标网站重定向地址无效';
                return false;
            }
            $next_url = qifu_site_meta_redirect_url($current_url, $response['headers']['location']);
            if($next_url === false){
                $error = '目标网站重定向地址无效';
                return false;
            }
            $current_url = $next_url;
            continue;
        }
        if($response['status'] < 200 || $response['status'] >= 300){
            $error = '目标网站返回异常状态';
            return false;
        }
        $meta = qifu_site_meta_parse_html($response['body']);
        if($meta['name'] === '' && $meta['description'] === ''){
            $error = '未找到网站名称或描述，请手动填写';
            return false;
        }
        $meta['url'] = $current_url;
        return $meta;
    }
    $error = '目标网站重定向次数过多';
    return false;
}
?>
