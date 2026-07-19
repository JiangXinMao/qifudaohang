<?php
declare(strict_types=1);

/**
 * DataCabin PHP telemetry SDK. PHP 8.2+, no Composer dependency.
 * Do not include personal data, credentials, request bodies, or cookies in properties.
 */
final class DataCabinTelemetry
{
    public const SDK_VERSION = '2.1.0';
    public const PROTOCOL_VERSION = 1;
    private string $sessionId;
    private array $events = [];
    private bool $started = false;
    private bool $flushed = false;
    private mixed $previousExceptionHandler = null;
    private readonly string $channel;
    private readonly float $timeout;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $appKey,
        private readonly string $appSecret,
        private readonly string $version,
        string $channel = 'official',
        private readonly ?string $deviceId = null,
        float $timeout = 2.0,
        private readonly ?string $queuePath = null,
        private readonly string $credentialId = '',
    ) {
        $parts=parse_url($endpoint);$localHttp=is_array($parts)&&($parts['scheme']??'')==='http'&&in_array(strtolower((string)($parts['host']??'')),['localhost','127.0.0.1'],true);
        if(!is_array($parts)||empty($parts['host'])||(($parts['scheme']??'')!=='https'&&!$localHttp)||isset($parts['user'])||isset($parts['pass']))throw new InvalidArgumentException('endpoint 必须使用 HTTPS；仅 localhost/127.0.0.1 测试允许 HTTP。');
        if(!preg_match('/^[a-f0-9]{24}$/iD',$appKey))throw new InvalidArgumentException('appKey 必须是24位十六进制字符串。');
        if(!preg_match('/^[a-f0-9]{64}$/iD',$appSecret))throw new InvalidArgumentException('appSecret 必须是64位十六进制字符串。');
        if($credentialId!==''&&!preg_match('/^hmac-[a-f0-9]{12,32}$/D',$credentialId))throw new InvalidArgumentException('credentialId 必须是 hmac- 开头的十六进制凭据 ID。');
        if(trim($version)===''||strlen($version)>60)throw new InvalidArgumentException('version 长度必须为1至60个字符。');
        $this->channel = self::normalizeTarget('channel', $channel);
        $this->timeout = is_finite($timeout) ? min(30.0, max(0.2, $timeout)) : 2.0;
        $this->sessionId = bin2hex(random_bytes(16));
    }

    public function start(bool $captureUnhandled = true): self
    {
        if ($this->started) return $this;
        $this->started = true;
        $this->drainPending();
        $this->add('start');
        if ($captureUnhandled) {
            $this->previousExceptionHandler = set_exception_handler(function (Throwable $error): void {
                $this->captureException($error);
                $this->stop();
                if (is_callable($this->previousExceptionHandler)) {
                    ($this->previousExceptionHandler)($error);
                    return;
                }
                error_log((string)$error);
            });
            register_shutdown_function(function (): void {
                $last = error_get_last();
                if (!$this->flushed && $last && in_array($last['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR], true)) {
                    $this->add('crash', [
                        'crash_type' => 'FatalError',
                        'message' => (string)$last['message'],
                        'stack' => (string)$last['file'] . ':' . (int)$last['line'],
                    ]);
                }
                $this->stop();
            });
        }
        return $this;
    }

    public function track(string $name, bool $success = true, array $properties = []): void
    {
        if (!$this->started) $this->start();
        try {
            $encoded=json_encode($properties, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
            if(strlen($encoded)>65536)$properties=['_sdk_error'=>'properties_too_large'];
        } catch (Throwable) {
            $properties = ['_sdk_error' => 'properties_not_json_serializable'];
        }
        $this->add('feature', ['name'=>substr($name,0,120),'success'=>$success,'properties'=>$properties]);
        if(count($this->events)>=80)$this->flush();
    }

    public function heartbeat(): bool
    {
        if (!$this->started) $this->start();
        $this->add('heartbeat');
        return $this->flush();
    }

    /* 查询官方新版本与公告，并在返回给业务层前完成 Ed25519 验签。 */
    public function checkRemote(string|array|null $publicKey = null, string $arch = '', string $locale = 'zh-CN', string|array|null $rootPublicKey = null): ?array
    {
        $deviceId = $this->resolvedDeviceId();
        $payload = [
            'device_id'=>$deviceId,'device_fingerprint'=>$this->deviceFingerprint($deviceId),
            'platform'=>self::normalizeTarget('platform',PHP_SAPI === 'cli' ? 'php-cli' : 'php-web'),
            'arch'=>$arch !== '' ? substr($arch,0,40) : strtolower(php_uname('m') ?: 'unknown'),
            'version'=>$this->version,'channel'=>$this->channel,'locale'=>self::normalizeTarget('locale',substr($locale,0,20) ?: 'zh-CN'),
            'sdk_version'=>self::SDK_VERSION,'protocol_version'=>self::PROTOCOL_VERSION,
        ];
        $response = $this->requestJson($this->routeEndpoint('api/v1/client/bootstrap'), $payload);
        if (!$response || empty($response['ok'])) return null;
        try {
            $raw = base64_decode((string)($response['payload'] ?? ''), true);
            if (!is_string($raw)) return null;
            $algorithm = (string)($response['algorithm'] ?? '');
            if ($algorithm !== 'Ed25519') return null;
            if (!function_exists('sodium_crypto_sign_verify_detached')) return null;
            $keys = $this->trustedPublicKeys($publicKey);
            $keys = array_replace($keys, self::certifiedPublicKeys($response['signing_key_certificates']??null,$rootPublicKey));
            if (!$keys) return null;
            $signatures = is_array($response['signatures'] ?? null) ? $response['signatures'] : [[
                'key_id'=>(string)($response['key_id']??''),'signature'=>(string)($response['signature']??''),
            ]];
            $verifiedKeyId = '';
            foreach ($signatures as $item) {
                if (!is_array($item)) continue;
                $signature = base64_decode((string)($item['signature'] ?? ''), true);
                if (!is_string($signature)) continue;
                $keyId = (string)($item['key_id'] ?? '');
                $candidates = isset($keys[$keyId]) ? [$keyId=>$keys[$keyId]] : $keys;
                foreach ($candidates as $candidateId=>$encoded) {
                    $key = base64_decode($encoded, true);
                    if (is_string($key) && strlen($key) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES && sodium_crypto_sign_verify_detached($signature, $raw, $key)) {
                        $verifiedKeyId = $keyId !== '' ? $keyId : (string)$candidateId;
                        break 2;
                    }
                }
            }
            if ($verifiedKeyId === '') return null;
            $data = json_decode($raw, true);
            if (!is_array($data) || (int)($data['expires_at'] ?? 0) < time() - 300) return null;
            $data = self::filterRemote($data);
            $replacementKeys=[];
            if (is_array($data['trusted_public_keys'] ?? null)) {
                $replacementKeys = self::validPublicKeys($data['trusted_public_keys']);
                if ($replacementKeys) $this->saveTrustedPublicKeys($replacementKeys);
            }
            $data['signature_algorithm'] = $algorithm;
            $data['key_id'] = $verifiedKeyId;
            $data['sdk_version'] = self::SDK_VERSION;
            return $data;
        } catch (Throwable) {
            return null;
        }
    }

    /* 校验更新包 SHA-256 与发布签名，阻止被替换或篡改的安装包。 */
    public static function verifyUpdateFile(string $path, array $update, string|array|null $publicKey = null): bool
    {
        if (!is_file($path) || !function_exists('sodium_crypto_sign_verify_detached')) return false;
        $actual = hash_file('sha256', $path);
        $expected = strtolower((string)($update['sha256'] ?? ''));
        if (!is_string($actual) || !preg_match('/^[a-f0-9]{64}$/', $expected) || !hash_equals($expected, $actual)) return false;
        $keys = self::coercePublicKeys($publicKey);
        $signatures = is_array($update['file_signatures'] ?? null) ? $update['file_signatures'] : [[
            'key_id'=>(string)($update['signing_key_id']??''),'signature'=>(string)($update['file_signature']??''),
        ]];
        foreach ($signatures as $item) {
            if (!is_array($item)) continue;
            $signature = base64_decode((string)($item['signature'] ?? ''), true);
            if (!is_string($signature) || strlen($signature)!==SODIUM_CRYPTO_SIGN_BYTES) continue;
            $keyId = (string)($item['key_id'] ?? '');
            $candidates = isset($keys[$keyId]) ? [$keys[$keyId]] : array_values($keys);
            foreach ($candidates as $encoded) {
                $key = base64_decode($encoded, true);
                if (is_string($key) && strlen($key)===SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES && sodium_crypto_sign_verify_detached($signature, 'release-sha256:'.$actual, $key)) return true;
            }
        }
        return false;
    }

    /* 下载官方更新包到暂存路径；验证成功后才允许交给安装模块。 */
    public function downloadUpdate(array $update, string $destination, string|array|null $publicKey = null, float $timeout = 30.0): ?string
    {
        $url = (string)($update['download_url'] ?? '');
        $parts = parse_url($url);
        $localHttp = is_array($parts) && ($parts['scheme']??'') === 'http' && in_array(strtolower((string)($parts['host']??'')), ['localhost','127.0.0.1'], true);
        if (!is_array($parts) || (($parts['scheme']??'') !== 'https' && !$localHttp) || isset($parts['user']) || isset($parts['pass'])) return null;
        $directory = dirname($destination);
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) return null;
        $temporary = $destination . '.part-' . bin2hex(random_bytes(8));
        $expected = max(0, (int)($update['file_size'] ?? 0));
        $limit = min(2147483648, $expected > 0 ? $expected + 1048576 : 2147483648);
        try {
            $context = stream_context_create(['http'=>['timeout'=>min(120.0,max(2.0,$timeout)),'follow_location'=>0,'header'=>'User-Agent: DataCabin-PHP/'.self::SDK_VERSION."\r\n"]]);
            $input = @fopen($url, 'rb', false, $context);
            $output = @fopen($temporary, 'wb');
            if (!$input || !$output) { if(is_resource($input))fclose($input);if(is_resource($output))fclose($output);@unlink($temporary);return null; }
            $total = 0;
            while (!feof($input)) {
                $chunk = fread($input, 1048576);
                if ($chunk === false) throw new RuntimeException('download_read_failed');
                $total += strlen($chunk);
                if ($total > $limit || fwrite($output, $chunk) !== strlen($chunk)) throw new RuntimeException('download_write_failed');
            }
            fflush($output);fclose($input);fclose($output);
            $verificationKeys=$this->trustedPublicKeys($publicKey);
            if (($expected > 0 && $total !== $expected) || !self::verifyUpdateFile($temporary, $update, $verificationKeys)) throw new RuntimeException('download_verify_failed');
            if (is_file($destination) && !@unlink($destination)) throw new RuntimeException('download_destination_busy');
            if (!@rename($temporary, $destination)) throw new RuntimeException('download_stage_failed');
            return $destination;
        } catch (Throwable) {
            @unlink($temporary);
            return null;
        }
    }

    public static function applyUpdate(string $stagedPath, string $targetPath, ?string $backupPath = null): bool
    {
        if (!is_file($stagedPath)) return false;
        $backupPath ??= $targetPath . '.bak';
        $moved = false;
        try {
            if (is_file($backupPath) && !@unlink($backupPath)) return false;
            if (is_file($targetPath)) { if (!@rename($targetPath, $backupPath)) return false; $moved = true; }
            if (!@rename($stagedPath, $targetPath)) throw new RuntimeException('replace_failed');
            return true;
        } catch (Throwable) {
            if ($moved && is_file($backupPath) && !is_file($targetPath)) @rename($backupPath, $targetPath);
            return false;
        }
    }

    public static function filterRemote(array $data, ?int $now = null): array
    {
        $reference = $now ?? time();
        $data['announcements'] = array_values(array_filter(
            is_array($data['announcements'] ?? null) ? $data['announcements'] : [],
            static fn(mixed $item): bool => is_array($item) && self::remoteItemActive($item, $reference)
        ));
        $update = $data['update'] ?? null;
        $data['update'] = is_array($update) && self::remoteItemActive($update, $reference) ? $update : null;
        return $data;
    }

    private static function remoteItemActive(array $item, int $now): bool
    {
        try {
            if (array_key_exists('starts_at', $item) && $item['starts_at'] !== null && $item['starts_at'] !== '' && $now < (int)$item['starts_at']) return false;
            if (array_key_exists('ends_at', $item) && $item['ends_at'] !== null && $item['ends_at'] !== '' && $now > (int)$item['ends_at']) return false;
        } catch (Throwable) {
            return false;
        }
        return true;
    }

    private static function normalizeTarget(string $dimension, mixed $value): string
    {
        $value=trim((string)$value);if(in_array($value,['不限','全部','所有'],true))return '*';$key=strtolower($value);
        $canonical=[
            'channel'=>['*'=>'*','official'=>'official','beta'=>'beta','internal'=>'internal','preview'=>'preview'],
            'locale'=>['*'=>'*','zh-cn'=>'zh-CN','zh-tw'=>'zh-TW','en-us'=>'en-US','ja-jp'=>'ja-JP','ko-kr'=>'ko-KR'],
            'platform'=>['*'=>'*','windows'=>'windows','linux'=>'linux','darwin'=>'darwin','php-web'=>'php-web','php-cli'=>'php-cli'],
        ];
        if(isset($canonical[$dimension][$key]))return $canonical[$dimension][$key];
        $aliases=[
            'channel'=>['正式'=>'official','正式版'=>'official','正式渠道'=>'official','官方'=>'official','官方版'=>'official','测试'=>'beta','测试版'=>'beta','测试渠道'=>'beta','内测'=>'internal','内部'=>'internal','内部版'=>'internal','内部渠道'=>'internal','预览'=>'preview','预览版'=>'preview','灰度'=>'preview','灰度版'=>'preview'],
            'locale'=>['中文'=>'zh-CN','简体'=>'zh-CN','简体中文'=>'zh-CN','繁体'=>'zh-TW','繁体中文'=>'zh-TW','英文'=>'en-US','英语'=>'en-US','日文'=>'ja-JP','日语'=>'ja-JP','韩文'=>'ko-KR','韩语'=>'ko-KR'],
            'platform'=>['windows桌面'=>'windows','windows 桌面'=>'windows','linux桌面'=>'linux','linux 桌面'=>'linux','macos'=>'darwin','macos 桌面'=>'darwin','苹果电脑'=>'darwin','苹果系统'=>'darwin','php网站'=>'php-web','php 网站'=>'php-web','php命令行'=>'php-cli','php 命令行'=>'php-cli'],
        ];
        return $aliases[$dimension][$key]??$value;
    }

    public function captureException(Throwable $error): void
    {
        if (!$this->started) $this->start(false);
        $this->add('crash', [
            'crash_type' => get_class($error),
            'message' => substr($error->getMessage(), 0, 8000),
            'stack' => substr($error->getTraceAsString(), 0, 60000),
        ]);
    }

    public function stop(): bool
    {
        if (!$this->started || $this->flushed) return true;
        $this->add('stop');
        $this->flushed = true;
        $ok = $this->flush();
        if ($this->previousExceptionHandler !== null) restore_exception_handler();
        return $ok;
    }

    public static function anonymousWebDeviceId(string $cookieName = 'datacabin_device'): string
    {
        $existing = (string)($_COOKIE[$cookieName] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/', $existing)) return $existing;
        $id = hash('sha256', random_bytes(32));
        if (!headers_sent()) {
            setcookie($cookieName, $id, [
                'expires'=>time()+31536000,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']),
                'httponly'=>true,'samesite'=>'Lax',
            ]);
        }
        return $id;
    }

    public static function stableServerDeviceId(string $storageFile): string
    {
        try {
            $dir = dirname($storageFile);
            if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return hash('sha256', random_bytes(32));
            $handle = @fopen($storageFile, 'c+b');
            if ($handle === false || !@flock($handle, LOCK_EX)) {
                if (is_resource($handle)) @fclose($handle);
                return hash('sha256', random_bytes(32));
            }
            try {
                rewind($handle);
                $existing = trim((string)stream_get_contents($handle));
                if (preg_match('/^[a-f0-9]{64}$/', $existing)) return $existing;
                $id = hash('sha256', random_bytes(32));
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, $id);
                fflush($handle);
                return $id;
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        } catch (Throwable) {
            return hash('sha256', random_bytes(32));
        }
    }

    private function add(string $type, array $values = []): void
    {
        $this->events[] = ['type'=>$type,'session_id'=>$this->sessionId,'timestamp'=>time()] + $values;
    }

    private function flush(): bool
    {
        if (!$this->events) return true;
        $events = $this->events;
        $this->events = [];
        $deviceId = $this->resolvedDeviceId();
        $payload = [
            'device_id'=>$deviceId,'device_fingerprint'=>$this->deviceFingerprint($deviceId),
            'platform'=>PHP_SAPI === 'cli' ? 'php-cli' : 'php-web','version'=>$this->version,
            'channel'=>$this->channel,'os_version'=>PHP_OS_FAMILY.' / PHP '.PHP_VERSION,
            'model'=>(string)($_SERVER['SERVER_SOFTWARE'] ?? PHP_SAPI),
            'sdk_version'=>self::SDK_VERSION,'protocol_version'=>self::PROTOCOL_VERSION,'events'=>$events,
        ];
        if ($this->sendPayload($payload)) return true;
        $this->persistPayload($payload);
        return false;
    }

    private function sendPayload(array $payload): bool
    {
        try {
            if (empty($payload['device_fingerprint'])) {
                $payload['device_fingerprint'] = $this->deviceFingerprint((string)($payload['device_id'] ?? $this->resolvedDeviceId()));
            }
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
            $timestamp = (string)time();
            $nonce = bin2hex(random_bytes(16));
            $signature = hash_hmac('sha256', $timestamp."\n".$nonce."\n".$body, $this->appSecret);
            $context = stream_context_create(['http'=>[
                'method'=>'POST','timeout'=>$this->timeout,'ignore_errors'=>false,
                'header'=>implode("\r\n",array_filter([
                    'Content-Type: application/json','X-App-Key: '.$this->appKey,
                    'X-Timestamp: '.$timestamp,'X-Nonce: '.$nonce,'X-Signature: '.$signature,
                    'User-Agent: DataCabin-PHP/'.self::SDK_VERSION,'X-SDK-Version: '.self::SDK_VERSION,'X-Protocol-Version: '.self::PROTOCOL_VERSION,
                    $this->credentialId!==''?'X-Credential-Id: '.$this->credentialId:null,
                ])),'content'=>$body,
            ]]);
            $result = @file_get_contents($this->endpoint, false, $context);
            if ($result === false || strlen($result) > 1048576) return false;
            $decoded = json_decode($result, true);
            return is_array($decoded) && !empty($decoded['ok']);
        } catch (Throwable) {
            return false;
        }
    }

    private function requestJson(string $endpoint, array $payload): ?array
    {
        try {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
            $timestamp = (string)time();
            $nonce = bin2hex(random_bytes(16));
            $signature = hash_hmac('sha256', $timestamp."\n".$nonce."\n".$body, $this->appSecret);
            $context = stream_context_create(['http'=>[
                'method'=>'POST','timeout'=>$this->timeout,'ignore_errors'=>false,
                'header'=>implode("\r\n",array_filter([
                    'Content-Type: application/json','X-App-Key: '.$this->appKey,
                    'X-Timestamp: '.$timestamp,'X-Nonce: '.$nonce,'X-Signature: '.$signature,
                    'User-Agent: DataCabin-PHP/'.self::SDK_VERSION,'X-SDK-Version: '.self::SDK_VERSION,'X-Protocol-Version: '.self::PROTOCOL_VERSION,
                    $this->credentialId!==''?'X-Credential-Id: '.$this->credentialId:null,
                ])),'content'=>$body,
            ]]);
            $result = @file_get_contents($endpoint, false, $context);
            if ($result === false || strlen($result) > 1048576) return null;
            $decoded = json_decode($result, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function routeEndpoint(string $route): string
    {
        $parts = parse_url($this->endpoint);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return $this->endpoint;
        parse_str((string)($parts['query'] ?? ''), $query);
        $query['r'] = $route;
        $authority = $parts['scheme'].'://'.($parts['user']??'').(isset($parts['pass'])?':'.$parts['pass']:'').(isset($parts['user'])?'@':'').$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
        return $authority.($parts['path']??'/').'?'.http_build_query($query);
    }

    private function resolvedDeviceId(): string
    {
        return $this->deviceId ?: self::stableServerDeviceId(sys_get_temp_dir().'/datacabin-device-'.$this->appKey);
    }

    private function deviceFingerprint(string $deviceId): string
    {
        $source = $this->deviceId !== null && $this->deviceId !== ''
            ? 'visitor:'.$deviceId
            : 'server:'.php_uname('n').'|'.PHP_OS_FAMILY.'|'.php_uname('m');
        return hash('sha256', "datacabin-machine-v1\0".$this->appKey."\0".$source);
    }

    private function persistPayload(array $payload): void
    {
        try {
            $line = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
            if (!is_string($line)) return;
            $file = $this->pendingFile();
            $dir = dirname($file);
            if (!is_dir($dir)) @mkdir($dir, 0770, true);
            $handle = @fopen($file, 'c+b');
            if ($handle === false || !@flock($handle, LOCK_EX)) { if (is_resource($handle)) @fclose($handle); return; }
            rewind($handle);
            $existing = stream_get_contents($handle);
            $lines = preg_split('/\r?\n/', is_string($existing) ? trim($existing) : '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $lines[] = $line;
            $kept = [];$bytes = 0;
            for ($i=count($lines)-1;$i>=0 && count($kept)<200;$i--) {
                $size = strlen($lines[$i]) + 1;
                if ($bytes + $size > 1048576) break;
                array_unshift($kept, $lines[$i]);$bytes += $size;
            }
            rewind($handle);ftruncate($handle, 0);
            if ($kept) fwrite($handle, implode("\n", $kept)."\n");
            fflush($handle);flock($handle, LOCK_UN);fclose($handle);
        } catch (Throwable) {
            // Telemetry must never interrupt the host application.
        }
    }

    private function drainPending(): void
    {
        $file = $this->pendingFile();
        if (!is_file($file)) return;
        try {
            $handle = @fopen($file, 'c+b');
            if ($handle === false || !@flock($handle, LOCK_EX)) { if (is_resource($handle)) @fclose($handle); return; }
            rewind($handle);$content = stream_get_contents($handle);
            $lines = preg_split('/\r?\n/', is_string($content) ? trim($content) : '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $attempt = array_splice($lines, 0, 3);
            rewind($handle);ftruncate($handle, 0);
            if ($lines) fwrite($handle, implode("\n", $lines)."\n");
            fflush($handle);flock($handle, LOCK_UN);fclose($handle);
            foreach ($attempt as $index=>$line) {
                $payload = json_decode($line, true);
                if (!is_array($payload) || $this->sendPayload($payload)) continue;
                $this->persistPayload($payload);
                foreach (array_slice($attempt, $index+1) as $pending) {
                    $decoded = json_decode($pending, true);
                    if (is_array($decoded)) $this->persistPayload($decoded);
                }
                break;
            }
        } catch (Throwable) {
            // A locked, corrupt, or unwritable queue is ignored by design.
        }
    }

    private function pendingFile(): string
    {
        return $this->queuePath ?: sys_get_temp_dir().'/datacabin-pending-'.substr(hash('sha256',$this->appKey),0,16).'.jsonl';
    }

    private static function coercePublicKeys(string|array|null $value): array
    {
        if (is_string($value) && $value !== '') return ['legacy'=>$value];
        if (!is_array($value)) return [];
        if ($value === []) return [];
        $associative = array_keys($value) !== range(0, count($value)-1);
        $mapped = $associative ? $value : array_combine(array_map(static fn(int $i): string=>'key-'.$i,array_keys($value)),array_values($value));
        return self::validPublicKeys(is_array($mapped)?$mapped:[]);
    }

    private static function validPublicKeys(array $keys): array
    {
        $valid=[];
        foreach($keys as $keyId=>$encoded){$raw=base64_decode((string)$encoded,true);if(is_string($raw)&&strlen($raw)===32)$valid[substr((string)$keyId,0,80)]=(string)$encoded;}
        return $valid;
    }

    private static function certifiedPublicKeys(mixed $certificates,string|array|null $rootPublicKey):array
    {
        $roots=self::coercePublicKeys($rootPublicKey);if(!$roots||!is_array($certificates)||!function_exists('sodium_crypto_sign_verify_detached'))return [];$certified=[];
        foreach($certificates as $certificate){
            if(!is_array($certificate)||(int)($certificate['certificate_version']??0)!==1)continue;
            $rootId=(string)($certificate['root_key_id']??'');$keyId=substr((string)($certificate['key_id']??''),0,80);$publicKey=(string)($certificate['public_key']??'');$issuedAt=(int)($certificate['issued_at']??0);$validFrom=(int)($certificate['valid_from']??0);$validUntil=(int)($certificate['valid_until']??0);
            $signature=base64_decode((string)($certificate['signature']??''),true);$key=base64_decode($publicKey,true);$root=isset($roots[$rootId])?base64_decode((string)$roots[$rootId],true):false;
            if(!is_string($signature)||!is_string($key)||strlen($key)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES||!is_string($root)||strlen($root)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES||$issuedAt<=0||$validFrom<=0||$validUntil<=$validFrom||time()<$validFrom-300||time()>$validUntil+300)continue;
            $message="datacabin-signing-key-v1\n{$rootId}\n{$keyId}\n{$publicKey}\n{$issuedAt}\n{$validFrom}\n{$validUntil}";
            if(sodium_crypto_sign_verify_detached($signature,$message,$root))$certified[$keyId]=$publicKey;
        }
        return $certified;
    }

    private function trustedPublicKeys(string|array|null $supplied): array
    {
        $file=$this->pendingFile().'.trusted-keys.json';
        try{$cached=json_decode((string)@file_get_contents($file),true);if(is_array($cached)){$trusted=self::validPublicKeys($cached);if($trusted)return $trusted;}}catch(Throwable){}
        return self::coercePublicKeys($supplied);
    }

    private function saveTrustedPublicKeys(array $keys): void
    {
        try{$file=$this->pendingFile().'.trusted-keys.json';$tmp=$file.'.tmp';$json=json_encode(self::validPublicKeys($keys),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);if(@file_put_contents($tmp,$json,LOCK_EX)!==false)@rename($tmp,$file);}catch(Throwable){}
    }
}

/* --- DataCabin application configuration ---
 * Keep production credentials in telemetry_credentials.php. The public
 * repository contains the full SDK and query flow, but not the private key.
 */
$datacabinCredentials = __DIR__.'/telemetry_credentials.php';
if(is_file($datacabinCredentials)) require_once $datacabinCredentials;
if (!defined('DATACABIN_ENDPOINT')) define('DATACABIN_ENDPOINT', (string)(getenv('DATACABIN_ENDPOINT') ?: ''));
if (!defined('DATACABIN_APP_KEY')) define('DATACABIN_APP_KEY', (string)(getenv('DATACABIN_APP_KEY') ?: ''));
if (!defined('DATACABIN_APP_SECRET')) define('DATACABIN_APP_SECRET', (string)(getenv('DATACABIN_APP_SECRET') ?: ''));
if (!defined('DATACABIN_CREDENTIAL_ID')) define('DATACABIN_CREDENTIAL_ID', (string)(getenv('DATACABIN_CREDENTIAL_ID') ?: ''));
if (!defined('DATACABIN_DEFAULT_CHANNEL')) define('DATACABIN_DEFAULT_CHANNEL', "official");
if (!defined('DATACABIN_REMOTE_PUBLIC_KEY')) define('DATACABIN_REMOTE_PUBLIC_KEY', (string)(getenv('DATACABIN_REMOTE_PUBLIC_KEY') ?: ''));
if (!defined('DATACABIN_REMOTE_PUBLIC_KEYS_JSON')) define('DATACABIN_REMOTE_PUBLIC_KEYS_JSON', (string)(getenv('DATACABIN_REMOTE_PUBLIC_KEYS_JSON') ?: '{}'));
if (!defined('DATACABIN_REMOTE_ROOT_PUBLIC_KEYS_JSON')) define('DATACABIN_REMOTE_ROOT_PUBLIC_KEYS_JSON', (string)(getenv('DATACABIN_REMOTE_ROOT_PUBLIC_KEYS_JSON') ?: '{}'));

function datacabin_telemetry(string $version, ?string $channel = null, ?string $deviceId = null, float $timeout = 2.0, ?string $queuePath = null): DataCabinTelemetry
{
    return new DataCabinTelemetry(endpoint: DATACABIN_ENDPOINT, appKey: DATACABIN_APP_KEY, appSecret: DATACABIN_APP_SECRET, version: $version, channel: $channel ?? DATACABIN_DEFAULT_CHANNEL, deviceId: $deviceId, timeout: $timeout, queuePath: $queuePath, credentialId: DATACABIN_CREDENTIAL_ID);
}

function check_datacabin_remote(DataCabinTelemetry $telemetry): ?array
{
    $keys=json_decode(DATACABIN_REMOTE_PUBLIC_KEYS_JSON,true);
    $roots=json_decode(DATACABIN_REMOTE_ROOT_PUBLIC_KEYS_JSON,true);
    return $telemetry->checkRemote(publicKey: is_array($keys)&&$keys ? $keys : DATACABIN_REMOTE_PUBLIC_KEY, rootPublicKey: is_array($roots)?$roots:[]);
}
