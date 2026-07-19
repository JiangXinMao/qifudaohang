<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

error_reporting(0);
ini_set('display_errors', 0);

define('DH_JSON_RESPONSE', true);
ob_start();
include __DIR__ . "/includes/common.php";
include __DIR__ . "/includes/site_status.php";
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function cron_status_json($code, $msg, $data = array())
{
    echo json_encode(array_merge(array('code' => $code, 'msg' => $msg), $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function cron_status_reason_text($reason)
{
    if ($reason === 'high_latency') return '延迟过高';
    if ($reason === 'offline') return '无法访问';
    if ($reason === 'invalid_url') return '地址异常';
    if ($reason === 'private_host') return '内网/本地地址';
    return '异常';
}

$cron_key = isset($conf['cron_key']) ? (string)$conf['cron_key'] : '';
$provided_key = isset($_GET['key']) ? (string)$_GET['key'] : '';
if($cron_key === '' || $provided_key === '' || !hash_equals($cron_key, $provided_key)){
    http_response_code(403);
    cron_status_json(0, '定时任务密钥无效');
}

function cron_send_ping_alert_mail($bad_sites, $summary)
{
    global $conf;
    if (empty($bad_sites)) {
        return array('sent' => 0, 'msg' => '无红灯站点');
    }

    $today = date('Y-m-d');
    if (isset($conf['ping_alert_last_date']) && $conf['ping_alert_last_date'] === $today) {
        return array('sent' => 0, 'msg' => '今日已发送过提醒');
    }
    if (!isset($conf['mail_enabled']) || $conf['mail_enabled'] != '1') {
        return array('sent' => 0, 'msg' => '邮件通知未开启');
    }

    $mail_to = isset($conf['mail_to']) ? trim($conf['mail_to']) : '';
    $mail_user = isset($conf['mail_user']) ? trim($conf['mail_user']) : '';
    $mail_pass = isset($conf['mail_pass']) ? trim($conf['mail_pass']) : '';
    $mail_host = isset($conf['mail_host']) && trim($conf['mail_host']) !== '' ? trim($conf['mail_host']) : 'smtp.qq.com';
    $mail_port = isset($conf['mail_port']) && intval($conf['mail_port']) > 0 ? intval($conf['mail_port']) : 587;
    $mail_sender = isset($conf['mail_sender']) && trim($conf['mail_sender']) !== '' ? trim($conf['mail_sender']) : $mail_user;
    if ($mail_to === '' || $mail_user === '' || $mail_pass === '') {
        return array('sent' => 0, 'msg' => '邮件配置不完整');
    }

    include_once(ROOT.'includes/mail.php');
    if (!function_exists('dh_send_mail')) {
        return array('sent' => 0, 'msg' => '邮件发送函数不存在');
    }

    $sitename = !empty($conf['sitename']) ? $conf['sitename'] : '祈福导航系统';
    $subject = "【{$sitename}】站点红灯提醒";
    $lines = array();
    $lines[] = "您好，{$sitename} 在 ".date('Y-m-d H:i:s')." 完成站点 Ping/访问检测，发现以下站点为红灯：";
    $lines[] = "";
    $lines[] = "检测汇总：总计 {$summary['total']} 个，正常 {$summary['online']} 个，红灯 {$summary['offline']} 个，延迟过高 {$summary['slow']} 个。";
    $lines[] = "高延迟阈值：{$summary['latency_limit']}ms。";
    $lines[] = "";
    foreach ($bad_sites as $site) {
        $lines[] = "- {$site['name']}";
        $lines[] = "  地址：{$site['url']}";
        $lines[] = "  状态：".cron_status_reason_text($site['reason']);
        $lines[] = "  延迟：{$site['latency']}ms";
        $lines[] = "  HTTP：{$site['http_code']}";
        $lines[] = "";
    }
    $lines[] = "请登录后台检查站点地址、服务器状态或网络延迟。";
    $content = implode("\n", $lines);

    $ok = @dh_send_mail($mail_to, $subject, $content, $mail_user, $mail_pass, $mail_host, $mail_port, $mail_sender);
    if ($ok) {
        saveSetting('ping_alert_last_date', $today);
        return array('sent' => 1, 'msg' => '红灯站点提醒邮件已发送');
    }
    $err = function_exists('dh_mail_last_error') ? dh_mail_last_error() : '';
    return array('sent' => 0, 'msg' => $err ? $err : '提醒邮件发送失败');
}

$ping_enabled = isset($conf['ping_enabled']) ? $conf['ping_enabled'] : '0';
if ($ping_enabled != '1') {
    cron_status_json(0, '站点状态检测未开启');
}

dh_site_status_ensure_columns();

$today = date('Y-m-d');
$force = isset($_GET['force']) && $_GET['force'] == '1';
$last_run = isset($conf['ping_last_run']) ? $conf['ping_last_run'] : '';
if (!$force && $last_run === $today) {
    cron_status_json(1, '今日已检测，无需重复执行', array('date' => $today));
}

$latency_limit = dh_site_status_latency_limit();
$rows = $DB->get_results("SELECT id,name,url FROM `web_dh` WHERE active=1 ORDER BY id ASC");
$total = 0;
$online = 0;
$offline = 0;
$slow = 0;
$bad_sites = array();

foreach ($rows as $row) {
    $total++;
    $result = dh_site_status_update_row($row['id'], $row['url']);
    if (!empty($result['online'])) {
        $online++;
    } else {
        $offline++;
        if (isset($result['reason']) && $result['reason'] === 'high_latency') $slow++;
        $bad_sites[] = array(
            'id' => intval($row['id']),
            'name' => $row['name'],
            'url' => $row['url'],
            'reason' => isset($result['reason']) ? $result['reason'] : 'offline',
            'latency' => isset($result['latency']) ? intval($result['latency']) : 0,
            'http_code' => isset($result['http_code']) ? intval($result['http_code']) : 0
        );
    }
}

$mail_alert = cron_send_ping_alert_mail($bad_sites, array(
    'total' => $total,
    'online' => $online,
    'offline' => $offline,
    'slow' => $slow,
    'latency_limit' => $latency_limit
));

saveSetting('ping_last_run', $today);
saveSetting('ping_last_time', time());
$CACHE->clear();

cron_status_json(1, '检测完成', array(
    'date' => $today,
    'total' => $total,
    'online' => $online,
    'offline' => $offline,
    'slow' => $slow,
    'latency_limit' => $latency_limit,
    'mail_alert' => $mail_alert
));
?>
