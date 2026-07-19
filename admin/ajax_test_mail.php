<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

// 邮件发送测试 - 修复版
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
define('DH_JSON_RESPONSE', true);
include __DIR__ . "/../includes/common.php";
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if(!isset($islogin) || $islogin != 1){
    echo json_encode(['code'=>0, 'msg'=>'请先登录后台'], JSON_UNESCAPED_UNICODE);
    exit;
}
qifu_require_csrf();

$mail_enabled = isset($_POST['mail_enabled']) ? trim($_POST['mail_enabled']) : (isset($conf['mail_enabled']) ? $conf['mail_enabled'] : '0');
if($mail_enabled != '1'){
    echo json_encode(['code'=>0, 'msg'=>'请先开启「启用邮件通知」'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mail_to = isset($_POST['mail_to']) ? trim($_POST['mail_to']) : (isset($conf['mail_to']) ? trim($conf['mail_to']) : '');
$mail_user = isset($_POST['mail_user']) ? trim($_POST['mail_user']) : (isset($conf['mail_user']) ? trim($conf['mail_user']) : '');
$mail_pass = isset($_POST['mail_pass']) ? trim($_POST['mail_pass']) : (isset($conf['mail_pass']) ? trim($conf['mail_pass']) : '');
$mail_host = isset($_POST['mail_host']) ? trim($_POST['mail_host']) : (isset($conf['mail_host']) ? trim($conf['mail_host']) : '');
$mail_port = isset($_POST['mail_port']) ? intval($_POST['mail_port']) : (isset($conf['mail_port']) ? intval($conf['mail_port']) : 0);
$mail_sender = isset($_POST['mail_sender']) ? trim($_POST['mail_sender']) : (isset($conf['mail_sender']) ? trim($conf['mail_sender']) : '');
$mail_host = $mail_host !== '' ? $mail_host : 'smtp.qq.com';
$mail_port = $mail_port > 0 ? $mail_port : 587;
$mail_sender = $mail_sender !== '' ? $mail_sender : $mail_user;

if(empty($mail_to) || empty($mail_user) || empty($mail_pass)){
    echo json_encode(['code'=>0, 'msg'=>'请先填写完整的邮箱配置（收件邮箱、发件邮箱、授权码）'], JSON_UNESCAPED_UNICODE);
    exit;
}
if(!filter_var($mail_to, FILTER_VALIDATE_EMAIL) || !filter_var($mail_user, FILTER_VALIDATE_EMAIL)){
    echo json_encode(['code'=>0, 'msg'=>'收件邮箱或发件邮箱格式不正确'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检测函数支持
if(!function_exists('stream_socket_client')){
    echo json_encode(['code'=>0, 'msg'=>'服务器不支持 stream_socket_client，请联系主机商开启'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检测端口连通性
$errno = 0; $errstr = '';
if ($mail_port == 465) {
    $test_fp = @stream_socket_client(
        'ssl://' . $mail_host . ':' . $mail_port,
        $errno, $errstr, 10, STREAM_CLIENT_CONNECT,
		stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]])
    );
} else {
    $test_fp = @stream_socket_client('tcp://' . $mail_host . ':' . $mail_port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
}
if(!$test_fp){
    $hint = '';
    if($errno == 110 || $errno == 111){
        $hint = '（连接超时或拒绝 - 可能是服务器封禁了SMTP端口，建议换用虚拟主机内置邮箱服务）';
    }
    $einfo = $errno > 0 ? "错误码:{$errno} {$errstr}" : '';
    echo json_encode(['code'=>0, 'msg'=>"无法连接 {$mail_host}:{$mail_port}，{$einfo} {$hint}"], JSON_UNESCAPED_UNICODE);
    exit;
}
@fclose($test_fp);

// 发送测试邮件
$sitename = isset($conf['sitename']) ? $conf['sitename'] : '祈福导航系统';
$subject = "【{$sitename}】邮件通知测试";
$content = "您好！\n\n这是一封来自 {$sitename} 的邮件通知测试。\n\n如果您收到此邮件，说明邮件发送功能配置正确！\n\n—— {$sitename}";

$mail_php = ROOT . "includes/mail.php";
if (!file_exists($mail_php)) {
    echo json_encode(['code'=>0, 'msg'=>'mail.php 文件不存在，请检查文件是否完整'], JSON_UNESCAPED_UNICODE);
    exit;
}

include_once($mail_php);

if (!function_exists('dh_send_mail')) {
    echo json_encode(['code'=>0, 'msg'=>'dh_send_mail 函数未定义，请检查 mail.php 语法'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = @dh_send_mail($mail_to, $subject, $content, $mail_user, $mail_pass, $mail_host, $mail_port, $mail_sender);

if($result){
    echo json_encode(['code'=>1, 'msg'=>'测试邮件发送成功！请检查收件箱（包括垃圾邮件文件夹）'], JSON_UNESCAPED_UNICODE);
} else {
    $tips = [];
    $last_error = function_exists('dh_mail_last_error') ? dh_mail_last_error() : '';
    if (strlen($mail_pass) == 16) {
        $tips[] = '授权码格式(16位)正确';
    } elseif (strlen($mail_pass) < 10) {
        $tips[] = '请确认使用「授权码」而非「登录密码」';
    }
    if ($last_error) {
        $tips[] = '服务器返回：' . $last_error;
    }
    $tip_str = !empty($tips) ? '；' . implode('，', $tips) : '';
    echo json_encode(['code'=>0, 'msg'=>"发送失败，请检查：授权码是否正确、邮箱是否开启SMTP服务、服务器是否封禁了SMTP端口{$tip_str}"], JSON_UNESCAPED_UNICODE);
}
?>
