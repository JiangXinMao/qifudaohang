<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

if(!defined('IN_CRONLITE')) exit();

function dh_site_status_ensure_columns()
{
    global $DB;
    $columns = array(
        'ping_status' => "tinyint(1) NOT NULL DEFAULT -1",
        'ping_checked_at' => "int(11) NOT NULL DEFAULT 0",
        'ping_http_code' => "int(11) NOT NULL DEFAULT 0",
        'ping_latency' => "int(11) NOT NULL DEFAULT 0"
    );
    foreach ($columns as $col => $definition) {
        $rs = $DB->query("SHOW COLUMNS FROM `web_dh` LIKE '{$col}'");
        if (!$rs || !$DB->fetch($rs)) {
            $DB->query("ALTER TABLE `web_dh` ADD COLUMN `{$col}` {$definition}");
        }
    }
}

function dh_site_status_latency_limit()
{
    global $conf;
    $limit = isset($conf['ping_alert_latency']) ? intval($conf['ping_alert_latency']) : 3000;
    return max(500, min(30000, $limit));
}

function dh_site_status_normalize_url($url)
{
    $url = trim($url);
    if ($url === '') return '';
    if (!preg_match('#^https?://#i', $url)) $url = 'https://'.$url;
    return $url;
}

function dh_site_status_private_host($host)
{
    $host = trim($host);
    if ($host === '' || preg_match('/(^|\.)localhost$/i', $host)) return true;

    $ips = @gethostbynamel($host);
    if (!$ips) return false;

    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }
    }
    return false;
}

function dh_site_status_curl_check($url, $head = true)
{
    $resolved_ip = null;
    if(!qifu_public_http_url($url, $resolved_ip)) return array(CURLE_URL_MALFORMAT, 0, 0);
    $parts = parse_url($url);
    $port = isset($parts['port']) ? intval($parts['port']) : (strtolower($parts['scheme']) === 'https' ? 443 : 80);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_USERAGENT, 'QifuNavStatus/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    if(defined('CURLOPT_PROTOCOLS')) curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_RESOLVE, array($parts['host'].':'.$port.':'.$resolved_ip));
    curl_setopt($ch, CURLOPT_NOBODY, $head);
    if (!$head) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Range: bytes=0-0'));
    }
    @curl_exec($ch);
    $errno = curl_errno($ch);
    $http_code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $latency = intval(round(floatval(curl_getinfo($ch, CURLINFO_TOTAL_TIME)) * 1000));
    curl_close($ch);
    return array($errno, $http_code, $latency);
}

function dh_site_status_stream_check($url)
{
    $ctx = stream_context_create(array(
        'http' => array(
            'method' => 'GET',
            'timeout' => 8,
            'header' => "User-Agent: QifuNavStatus/1.0\r\nRange: bytes=0-0\r\n"
        ),
		'ssl' => array(
			'verify_peer' => true,
			'verify_peer_name' => true
		)
    ));
    $start = microtime(true);
    $fp = @fopen($url, 'r', false, $ctx);
    $latency = intval(round((microtime(true) - $start) * 1000));
    if ($fp) @fclose($fp);
    return array((bool)$fp, 0, $latency);
}

function dh_site_status_check($url, $latency_limit = null)
{
    if ($latency_limit === null) $latency_limit = dh_site_status_latency_limit();
    $url = dh_site_status_normalize_url($url);
    $resolved_ip = null;
    if ($url === '' || !qifu_public_http_url($url, $resolved_ip)) {
        return array('online' => 0, 'http_code' => 0, 'latency' => 0, 'slow' => 0, 'reason' => 'invalid_url');
    }

    if (!function_exists('curl_init')) {
        list($ok, $http_code, $latency) = dh_site_status_stream_check($url);
        $slow = $ok && $latency >= $latency_limit ? 1 : 0;
        return array(
            'online' => ($ok && !$slow) ? 1 : 0,
            'http_code' => intval($http_code),
            'latency' => intval($latency),
            'slow' => $slow,
            'reason' => $slow ? 'high_latency' : ($ok ? 'ok' : 'offline')
        );
    }

    list($errno, $http_code, $latency) = dh_site_status_curl_check($url, true);
    if ($errno || $http_code == 405 || $http_code == 0) {
        list($errno, $http_code, $latency) = dh_site_status_curl_check($url, false);
    }

    $reachable = (!$errno && $http_code > 0 && $http_code < 500) ? 1 : 0;
    $slow = ($reachable && $latency >= $latency_limit) ? 1 : 0;
    $online = ($reachable && !$slow) ? 1 : 0;
    return array(
        'online' => $online,
        'http_code' => intval($http_code),
        'latency' => intval($latency),
        'slow' => $slow,
        'reason' => $slow ? 'high_latency' : ($reachable ? 'ok' : 'offline')
    );
}

function dh_site_status_update_row($id, $url)
{
    global $DB;
    $result = dh_site_status_check($url);
    $id = intval($id);
    $online = intval($result['online']);
    $http_code = intval($result['http_code']);
    $latency = isset($result['latency']) ? intval($result['latency']) : 0;
    $time = time();
    $DB->query("UPDATE `web_dh` SET `ping_status`='{$online}',`ping_checked_at`='{$time}',`ping_http_code`='{$http_code}',`ping_latency`='{$latency}' WHERE `id`='{$id}'");
    return $result;
}
?>
