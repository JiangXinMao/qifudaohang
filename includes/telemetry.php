<?php
declare(strict_types=1);

if(!defined('IN_CRONLITE')) exit;

if(!defined('QIFU_REMOTE_QUERY_INTERVAL')) define('QIFU_REMOTE_QUERY_INTERVAL', 300);
if(!defined('QIFU_TELEMETRY_FEEDBACK_INTERVAL')) define('QIFU_TELEMETRY_FEEDBACK_INTERVAL', 86400);

/**
 * Optional DataCabin integration. Remote content is only accepted after the
 * SDK verifies its Ed25519 signature; all failures intentionally degrade to
 * local-only behavior.
 */
function qifu_telemetry_instance(): ?DataCabinTelemetry {
    static $telemetry = null;
    static $registered = false;
    if($telemetry instanceof DataCabinTelemetry) return $telemetry;
    $sdk = SYSTEM_ROOT.'telemetry_sdk.php';
    if(!is_file($sdk)) return null;
    @include_once $sdk;
    if(!class_exists('DataCabinTelemetry') || !function_exists('datacabin_telemetry')) return null;
    try {
        $queue_dir = ROOT.'includes/.telemetry';
        if(!is_dir($queue_dir)) @mkdir($queue_dir, 0700, true);
        $queue_path = $queue_dir.'/pending.jsonl';
        $version = qifu_telemetry_version();
        $telemetry = datacabin_telemetry(
            $version,
            'official',
            DataCabinTelemetry::anonymousWebDeviceId('qifu_telemetry_device'),
            0.5,
            $queue_path
        );
        $telemetry->start(false);
        if(!$registered){
            $registered = true;
            register_shutdown_function(static function() use (&$telemetry): void {
                try { if($telemetry instanceof DataCabinTelemetry) $telemetry->stop(); } catch(Throwable) {}
            });
        }
        return $telemetry;
    } catch(Throwable) {
        $telemetry = null;
        return null;
    }
}

function qifu_telemetry_version(): string {
    $version = defined('QIFU_PRODUCT_VERSION') ? trim((string)QIFU_PRODUCT_VERSION) : '1.0.0';
    $version = ltrim($version, "vV ");
    if(preg_match('/^\d+\.\d+$/', $version)) $version .= '.0';
    if(preg_match('/^\d+$/', $version)){
        $digits = str_pad($version, 3, '0', STR_PAD_RIGHT);
        $version = intval($digits[0]).'.'.intval($digits[1]).'.'.intval(substr($digits, 2));
    }
    return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) ? $version : '1.0.0';
}

/*
 * 功能调用上报入口：后台首页和一级菜单等匿名使用事件从这里进入
 * 官方查询端。不得在 properties 中加入账号、Cookie、Token 或正文数据。
 */
function qifu_telemetry_track(string $name, bool $success = true, array $properties = []): void {
    $telemetry = qifu_telemetry_instance();
    if(!$telemetry) return;
    try { $telemetry->track($name, $success, $properties); } catch(Throwable) {}
}

function qifu_telemetry_track_daily(string $name, bool $success = true, array $properties = []): void {
    $dir = ROOT.'includes/.telemetry';
    if(!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return;
    $key = preg_replace('/[^A-Za-z0-9._-]/', '-', $name);
    $path = $dir.'/daily-'.$key.'.txt';
    $handle = @fopen($path, 'c+b');
    if($handle === false || !@flock($handle, LOCK_EX)){
        if(is_resource($handle)) @fclose($handle);
        return;
    }
    $now = time();
    try {
        rewind($handle);
        $stored = trim((string)stream_get_contents($handle));
        $last = ctype_digit($stored) ? intval($stored) : (($parsed = strtotime($stored)) !== false ? intval($parsed) : 0);
        if($last > 0 && ($now - $last) < QIFU_TELEMETRY_FEEDBACK_INTERVAL) return;
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, (string)$now);
        fflush($handle);
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
    qifu_telemetry_track($name, $success, $properties);
}

function qifu_telemetry_cache_path(): string {
    return ROOT.'includes/.telemetry/remote.json';
}

function qifu_telemetry_read_cache(): ?array {
    $path = qifu_telemetry_cache_path();
    if(!is_file($path)) return null;
    $cached = json_decode((string)@file_get_contents($path), true);
    return is_array($cached) && is_array($cached['data'] ?? null) ? $cached : null;
}

/*
 * 官方新版本远程查询入口：获取公告、版本清单和更新包元数据。
 * SDK 必须先通过 Ed25519 验签，未通过验证的远程内容不会进入更新流程。
 */
function qifu_telemetry_remote(bool $force = false): ?array {
    $cached = qifu_telemetry_read_cache();
    $now = time();
    if(!$force && $cached && ($now - intval($cached['fetched_at'] ?? 0)) < QIFU_REMOTE_QUERY_INTERVAL) return $cached['data'];
    if(!function_exists('sodium_crypto_sign_verify_detached')) return $cached && ($now - intval($cached['verified_at'] ?? 0)) < 86400 ? $cached['data'] : null;
    $sdk = SYSTEM_ROOT.'telemetry_sdk.php';
    if(!is_file($sdk)) return null;
    @include_once $sdk;
    if(!class_exists('DataCabinTelemetry') || !function_exists('check_datacabin_remote')) return null;
    $dir = dirname(qifu_telemetry_cache_path());
    if(!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return $cached && ($now - intval($cached['verified_at'] ?? 0)) < 86400 ? $cached['data'] : null;
    $lock = @fopen($dir.'/remote.lock', 'c+b');
    if($lock === false || !@flock($lock, LOCK_EX)){
        if(is_resource($lock)) @fclose($lock);
        return $cached && ($now - intval($cached['verified_at'] ?? 0)) < 86400 ? $cached['data'] : null;
    }
    try {
        $cached = qifu_telemetry_read_cache();
        $now = time();
        if(!$force && $cached && ($now - intval($cached['fetched_at'] ?? 0)) < QIFU_REMOTE_QUERY_INTERVAL) return $cached['data'];
        // Remote bootstrap is infrequent and may take longer than the
        // fire-and-forget event transport used by normal page requests.
        $telemetry = datacabin_telemetry(
            qifu_telemetry_version(),
            'official',
            DataCabinTelemetry::anonymousWebDeviceId('qifu_telemetry_device'),
            8.0,
            ROOT.'includes/.telemetry/pending.jsonl'
        );
        $remote = check_datacabin_remote($telemetry);
        if(!is_array($remote)) return $cached && ($now - intval($cached['verified_at'] ?? 0)) < 86400 ? $cached['data'] : null;
        @file_put_contents(qifu_telemetry_cache_path(), json_encode(['fetched_at'=>$now,'verified_at'=>$now,'data'=>$remote], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $remote;
    } catch(Throwable) {
        return $cached && ($now - intval($cached['verified_at'] ?? 0)) < 86400 ? $cached['data'] : null;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}
