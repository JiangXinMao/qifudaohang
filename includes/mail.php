<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

if (!function_exists('dh_mail_last_error')) {
function dh_mail_last_error($message = null)
{
    static $last_error = '';
    if ($message !== null) {
        $last_error = $message;
    }
    return $last_error;
}
}

if (!function_exists('dh_mail_read')) {
function dh_mail_read($fp)
{
    $response = '';
    while (($line = @fgets($fp, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || substr($line, 3, 1) !== '-') {
            break;
        }
    }
    return $response;
}
}

if (!function_exists('dh_mail_code')) {
function dh_mail_code($response)
{
    return substr(trim($response), 0, 3);
}
}

if (!function_exists('dh_mail_command')) {
function dh_mail_command($fp, $command, $expect)
{
    fwrite($fp, $command . "\r\n");
    $response = dh_mail_read($fp);
    $code = dh_mail_code($response);
    if (!in_array($code, (array)$expect)) {
        dh_mail_last_error(trim($response) ?: 'SMTP服务器无响应');
        return false;
    }
    return $response;
}
}

if (!function_exists('dh_mail_header_value')) {
function dh_mail_header_value($value)
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}
}

if (!function_exists('dh_mail_normalize_content')) {
function dh_mail_normalize_content($content)
{
    $content = str_replace(array("\r\n", "\r"), "\n", $content);
    $content = str_replace("\n.", "\n..", $content);
    return str_replace("\n", "\r\n", $content);
}
}

if (!function_exists('dh_send_mail')) {
function dh_send_mail($to, $subject, $content, $from_user, $from_pass, $smtp_host, $smtp_port, $sender_name)
{
    dh_mail_last_error('');

    $to = trim($to);
    $from_user = trim($from_user);
    $from_pass = trim($from_pass);
    $smtp_host = trim($smtp_host);
    $smtp_port = intval($smtp_port);
    $sender_name = trim($sender_name) !== '' ? trim($sender_name) : $from_user;

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        dh_mail_last_error('收件邮箱格式不正确');
        return false;
    }
    if (!filter_var($from_user, FILTER_VALIDATE_EMAIL)) {
        dh_mail_last_error('发件邮箱格式不正确');
        return false;
    }
    if ($from_pass === '') {
        dh_mail_last_error('邮箱密码/授权码不能为空');
        return false;
    }
    if ($smtp_host === '' || $smtp_port <= 0) {
        dh_mail_last_error('SMTP服务器或端口未填写');
        return false;
    }
    if (!function_exists('stream_socket_client')) {
        dh_mail_last_error('服务器缺少 stream_socket_client 函数');
        return false;
    }

    $timeout = 25;
    $remote = ($smtp_port === 465 ? 'ssl://' : 'tcp://') . $smtp_host . ':' . $smtp_port;
    $context = stream_context_create(array(
		'ssl' => array(
			'verify_peer' => true,
			'verify_peer_name' => true,
			'allow_self_signed' => false,
        ),
    ));

    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        dh_mail_last_error("无法连接SMTP服务器 {$smtp_host}:{$smtp_port}" . ($errstr ? "，{$errstr}" : ''));
        return false;
    }
    stream_set_timeout($fp, $timeout);

    $response = dh_mail_read($fp);
    if (dh_mail_code($response) !== '220') {
        @fclose($fp);
        dh_mail_last_error(trim($response) ?: 'SMTP服务器连接后无欢迎信息');
        return false;
    }

    $host_name = isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] ? $_SERVER['SERVER_NAME'] : 'localhost';
    $ehlo = dh_mail_command($fp, "EHLO {$host_name}", '250');
    if ($ehlo === false) {
        if (dh_mail_command($fp, "HELO {$host_name}", '250') === false) {
            @fclose($fp);
            return false;
        }
        $ehlo = '';
    }

    if ($smtp_port !== 465 && stripos($ehlo, 'STARTTLS') !== false) {
        if (!extension_loaded('openssl')) {
            @fclose($fp);
            dh_mail_last_error('服务器未启用 OpenSSL，无法使用 STARTTLS');
            return false;
        }
        if (dh_mail_command($fp, 'STARTTLS', '220') === false) {
            @fclose($fp);
            return false;
        }
        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (@stream_socket_enable_crypto($fp, true, $crypto_method) !== true) {
            @fclose($fp);
            dh_mail_last_error('STARTTLS加密握手失败');
            return false;
        }
        if (dh_mail_command($fp, "EHLO {$host_name}", '250') === false) {
            @fclose($fp);
            return false;
        }
    }

    if (dh_mail_command($fp, 'AUTH LOGIN', '334') === false) {
        @fclose($fp);
        return false;
    }
    if (dh_mail_command($fp, base64_encode($from_user), '334') === false) {
        @fclose($fp);
        return false;
    }
    if (dh_mail_command($fp, base64_encode($from_pass), '235') === false) {
        @fclose($fp);
        dh_mail_last_error('SMTP认证失败，请检查邮箱账号、授权码和SMTP服务开关');
        return false;
    }

    if (dh_mail_command($fp, "MAIL FROM:<{$from_user}>", '250') === false) {
        @fclose($fp);
        return false;
    }
    if (dh_mail_command($fp, "RCPT TO:<{$to}>", array('250', '251')) === false) {
        @fclose($fp);
        return false;
    }
    if (dh_mail_command($fp, 'DATA', '354') === false) {
        @fclose($fp);
        return false;
    }

    $headers = array(
        'Date: ' . date('r'),
        'From: ' . dh_mail_header_value($sender_name) . " <{$from_user}>",
        "To: <{$to}>",
        'Subject: ' . dh_mail_header_value($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    );
    $message = implode("\r\n", $headers) . "\r\n\r\n" . dh_mail_normalize_content($content) . "\r\n.";

    fwrite($fp, $message . "\r\n");
    $response = dh_mail_read($fp);
    if (dh_mail_code($response) !== '250') {
        @fclose($fp);
        dh_mail_last_error(trim($response) ?: '邮件内容提交失败');
        return false;
    }

    @fwrite($fp, "QUIT\r\n");
    @fclose($fp);
    return true;
}
}
?>
