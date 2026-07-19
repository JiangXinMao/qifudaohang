<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

define('DH_JSON_RESPONSE', true);
include __DIR__ . "/../includes/common.php";
header('Content-Type: application/json; charset=utf-8');

if($islogin != 1){
    echo json_encode(array('code' => 0, 'msg' => '未登录'), JSON_UNESCAPED_UNICODE);
    exit;
}

function qf_status_bytes($value){
    $value = trim((string)$value);
    if($value === '' || $value === '-1') return 0;
    $unit = strtolower(substr($value, -1));
    $num = (float)$value;
    if($unit === 'g') return $num * 1024 * 1024 * 1024;
    if($unit === 'm') return $num * 1024 * 1024;
    if($unit === 'k') return $num * 1024;
    return $num;
}

function qf_status_human_bytes($bytes){
    $bytes = (float)$bytes;
    if($bytes <= 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $idx = 0;
    while($bytes >= 1024 && $idx < count($units) - 1){
        $bytes /= 1024;
        $idx++;
    }
    return round($bytes, $idx === 0 ? 0 : 1).' '.$units[$idx];
}

function qf_status_percent($used, $total){
    $total = (float)$total;
    if($total <= 0) return null;
    return round(max(0, min(100, ((float)$used / $total) * 100)), 1);
}

function qf_status_shell($command){
    if(!function_exists('shell_exec')) return '';
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if(in_array('shell_exec', $disabled, true)) return '';
    $out = @shell_exec($command);
    return is_string($out) ? trim($out) : '';
}

function qf_status_cpu(){
    $load = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;
    $cores = 1;
    $label = '负载数据不可用';
    $percent = null;

    if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
        $raw = qf_status_shell('wmic cpu get LoadPercentage /value 2>NUL');
        if(preg_match('/LoadPercentage=(\d+)/i', $raw, $m)){
            $percent = min(100, max(0, intval($m[1])));
            $label = 'Windows CPU 使用率';
        }
    } else {
        $nproc = qf_status_shell('getconf _NPROCESSORS_ONLN 2>/dev/null');
        if(is_numeric($nproc) && intval($nproc) > 0) $cores = intval($nproc);
        if(is_array($load)){
            $percent = qf_status_percent($load[0], $cores);
            $label = 'Load '.$load[0].' / '.$cores.' 核';
        }
    }

    return array(
        'percent' => $percent,
        'label' => $label,
        'load' => is_array($load) ? implode(', ', array_map('round', $load, array(2, 2, 2))) : '',
        'cores' => $cores
    );
}

function qf_status_memory(){
    $total = 0;
    $free = 0;
    $available = 0;

    if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
        $raw = qf_status_shell('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /value 2>NUL');
        if(preg_match('/TotalVisibleMemorySize=(\d+)/i', $raw, $m)) $total = intval($m[1]) * 1024;
        if(preg_match('/FreePhysicalMemory=(\d+)/i', $raw, $m)) $available = intval($m[1]) * 1024;
    } elseif(is_readable('/proc/meminfo')) {
        $lines = @file('/proc/meminfo');
        $info = array();
        foreach((array)$lines as $line){
            if(preg_match('/^([A-Za-z_()]+):\s+(\d+)/', $line, $m)){
                $info[$m[1]] = intval($m[2]) * 1024;
            }
        }
        $total = isset($info['MemTotal']) ? $info['MemTotal'] : 0;
        $free = isset($info['MemFree']) ? $info['MemFree'] : 0;
        $available = isset($info['MemAvailable']) ? $info['MemAvailable'] : $free;
    }

    $used = $total > 0 ? max(0, $total - $available) : 0;
    return array(
        'total' => $total,
        'used' => $used,
        'free' => $available,
        'percent' => qf_status_percent($used, $total),
        'total_human' => qf_status_human_bytes($total),
        'used_human' => qf_status_human_bytes($used),
        'free_human' => qf_status_human_bytes($available)
    );
}

function qf_status_disk(){
    $path = defined('ROOT') ? ROOT : __DIR__;
    $total = @disk_total_space($path);
    $free = @disk_free_space($path);
    $total = $total ? $total : 0;
    $free = $free ? $free : 0;
    $used = max(0, $total - $free);
    return array(
        'path' => $path,
        'total' => $total,
        'used' => $used,
        'free' => $free,
        'percent' => qf_status_percent($used, $total),
        'total_human' => qf_status_human_bytes($total),
        'used_human' => qf_status_human_bytes($used),
        'free_human' => qf_status_human_bytes($free)
    );
}

function qf_status_gpu(){
    $gpu = array(
        'state' => '不可用',
        'label' => '未检测到 GPU 指标',
        'percent' => null,
        'memory_used_human' => '--',
        'memory_total_human' => '--',
        'temperature' => ''
    );

    $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $nvidia = qf_status_shell('nvidia-smi --query-gpu=name,utilization.gpu,memory.used,memory.total,temperature.gpu --format=csv,noheader,nounits '.($is_windows ? '2>NUL' : '2>/dev/null'));
    if($nvidia !== ''){
        $line = trim(explode("\n", $nvidia)[0]);
        $parts = array_map('trim', explode(',', $line));
        if(count($parts) >= 4){
            $gpu['state'] = '在线';
            $gpu['percent'] = is_numeric($parts[1]) ? min(100, max(0, intval($parts[1]))) : null;
            $gpu['memory_used_human'] = qf_status_human_bytes(intval($parts[2]) * 1024 * 1024);
            $gpu['memory_total_human'] = qf_status_human_bytes(intval($parts[3]) * 1024 * 1024);
            $gpu['temperature'] = isset($parts[4]) && is_numeric($parts[4]) ? $parts[4].'°C' : '';
            $gpu['label'] = $parts[0].' · '.$gpu['memory_used_human'].' / '.$gpu['memory_total_human'];
            if($gpu['temperature'] !== '') $gpu['label'] .= ' · '.$gpu['temperature'];
            return $gpu;
        }
    }

    if($is_windows){
        $raw = qf_status_shell('wmic path win32_VideoController get name,adapterram /format:list 2>NUL');
        if($raw !== '' && preg_match('/Name=(.+)/i', $raw, $m)){
            $name = trim($m[1]);
            $memory = '';
            if(preg_match('/AdapterRAM=(\d+)/i', $raw, $ram)){
                $memory = qf_status_human_bytes((float)$ram[1]);
            }
            $gpu['state'] = '已识别';
            $gpu['label'] = $name.($memory !== '' ? ' · 显存 '.$memory : '');
        }
    }

    return $gpu;
}

function qf_status_site_runtime(){
    $root = defined('ROOT') ? ROOT : dirname(__DIR__).'/';
    $candidates = array(
        $root.'install/install.lock',
        $root.'config.php',
        $root.'install/install.sql'
    );
    foreach($candidates as $path){
        if(is_file($path)){
            $time = @filemtime($path);
            if($time && $time > 0){
                return qf_status_duration(time() - $time);
            }
        }
    }
    return '不可用';
}

function qf_status_duration($seconds){
    $seconds = max(0, intval($seconds));
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $mins = floor(($seconds % 3600) / 60);
    if($days > 0) return $days.'天 '.$hours.'小时';
    if($hours > 0) return $hours.'小时 '.$mins.'分钟';
    return $mins.'分钟';
}

function qf_status_count($sql){
    global $DB;
    $value = $DB->count($sql);
    return $value ? intval($value) : 0;
}

$today = date('Y-m-d');
$today_start = strtotime(date('Y-m-d 00:00:00'));
$mysqlversion = $DB->count("SELECT VERSION()");
$memory_usage = memory_get_usage(true);
$memory_peak = memory_get_peak_usage(true);
$memory_limit = ini_get('memory_limit');
$memory_limit_bytes = qf_status_bytes($memory_limit);

$payload = array(
    'code' => 1,
    'msg' => 'ok',
    'generated_at' => date('Y-m-d H:i:s'),
    'generated_time' => date('H:i:s'),
    'cpu' => qf_status_cpu(),
    'memory' => qf_status_memory(),
    'disk' => qf_status_disk(),
    'gpu' => qf_status_gpu(),
    'server' => array(
        'os' => php_uname('s').' '.php_uname('r'),
        'software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'CLI/PHP',
        'php_version' => PHP_VERSION,
        'db_version' => $mysqlversion ? $mysqlversion : 'Unknown',
        'uptime' => qf_status_site_runtime(),
        'upload_max' => ini_get('upload_max_filesize'),
        'root_path' => defined('ROOT') ? ROOT : dirname(__DIR__)
    ),
    'process' => array(
        'memory_usage' => $memory_usage,
        'memory_peak' => $memory_peak,
        'memory_usage_human' => qf_status_human_bytes($memory_usage).' / 峰值 '.qf_status_human_bytes($memory_peak),
        'memory_limit' => $memory_limit,
        'memory_limit_percent' => qf_status_percent($memory_usage, $memory_limit_bytes)
    ),
    'site' => array(
        'total_sites' => qf_status_count("SELECT COUNT(*) FROM web_dh"),
        'active_sites' => qf_status_count("SELECT COUNT(*) FROM web_dh WHERE active=1"),
        'hidden_sites' => qf_status_count("SELECT COUNT(*) FROM web_dh WHERE active=0"),
        'categories' => qf_status_count("SELECT COUNT(*) FROM web_category"),
        'today_clicks' => qf_status_count("SELECT COALESCE(SUM(views),0) FROM web_site_stats WHERE stat_date='$today'"),
        'total_clicks' => qf_status_count("SELECT COALESCE(SUM(views),0) FROM web_site_stats"),
        'pending_links' => qf_status_count("SELECT COUNT(*) FROM web_links WHERE status=0"),
        'today_logs' => qf_status_count("SELECT COUNT(*) FROM web_log WHERE addtime>='$today_start'")
    )
);

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
?>
