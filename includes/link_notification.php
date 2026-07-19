<?php
if(!defined('IN_CRONLITE')) exit;

function qifu_link_notification_configuration_ready($config, &$error = '')
{
    $error = '';
    if(!is_array($config) || !isset($config['mail_enabled']) || (string)$config['mail_enabled'] !== '1'){
        $error = '请先开启系统邮件通知';
        return false;
    }
    if(empty($config['mail_to']) || !filter_var(trim((string)$config['mail_to']), FILTER_VALIDATE_EMAIL)){
        $error = '请先填写正确的接收通知邮箱';
        return false;
    }
    if(empty($config['mail_user']) || !filter_var(trim((string)$config['mail_user']), FILTER_VALIDATE_EMAIL)){
        $error = '请先填写正确的发件邮箱';
        return false;
    }
    if(empty($config['mail_pass'])){
        $error = '请先填写邮箱密码或授权码';
        return false;
    }
    if(empty($config['mail_host']) || intval(isset($config['mail_port']) ? $config['mail_port'] : 0) <= 0){
        $error = '请先完成 SMTP 服务器配置';
        return false;
    }
    return true;
}

function qifu_link_notification_value($value, $max_length)
{
    $value = str_replace(array("\r", "\n", "\0"), ' ', trim((string)$value));
    $value = preg_replace('/\s+/', ' ', $value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $max_length, 'UTF-8') : substr($value, 0, $max_length);
}

function qifu_send_link_application_notification($config, $application)
{
    $result = array('attempted'=>false, 'sent'=>false, 'error'=>'');
    if(!is_array($config) || !isset($config['link_mail_notify']) || (string)$config['link_mail_notify'] !== '1') return $result;

    $configuration_error = '';
    if(!qifu_link_notification_configuration_ready($config, $configuration_error)){
        $result['error'] = $configuration_error;
        return $result;
    }

    require_once ROOT.'includes/mail.php';
    if(!function_exists('dh_send_mail')){
        $result['error'] = '邮件发送组件不可用';
        return $result;
    }

    $name = qifu_link_notification_value(isset($application['name']) ? $application['name'] : '', 100);
    $url = qifu_link_notification_value(isset($application['url']) ? $application['url'] : '', 255);
    $category = qifu_link_notification_value(isset($application['category']) ? $application['category'] : '', 50);
    $applicant_email = qifu_link_notification_value(isset($application['email']) ? $application['email'] : '', 100);
    $submitted_at = isset($application['addtime']) ? intval($application['addtime']) : time();
    $sitename = qifu_link_notification_value(isset($config['sitename']) ? $config['sitename'] : '祈福导航系统', 100);
    $subject = '['.$sitename.'] 收到新的友链申请';
    $content = "管理员您好：\n\n前台收到一条新的友链申请，请登录后台审核。\n\n"
        ."站点名称：".($name !== '' ? $name : '未填写')."\n"
        ."网站地址：".($url !== '' ? $url : '未填写')."\n"
        ."申请分类：".($category !== '' ? $category : '未选择')."\n"
        ."申请人邮箱：".($applicant_email !== '' ? $applicant_email : '未填写')."\n"
        ."提交时间：".date('Y-m-d H:i:s', $submitted_at)."\n\n"
        ."请进入后台的友链管理页面处理。\n\n—— ".$sitename;

    $result['attempted'] = true;
    $result['sent'] = (bool)@dh_send_mail(
        trim((string)$config['mail_to']),
        $subject,
        $content,
        trim((string)$config['mail_user']),
        trim((string)$config['mail_pass']),
        trim((string)$config['mail_host']),
        intval($config['mail_port']),
        !empty($config['mail_sender']) ? trim((string)$config['mail_sender']) : $sitename
    );
    if(!$result['sent']) $result['error'] = function_exists('dh_mail_last_error') ? (string)dh_mail_last_error() : '邮件发送失败';
    return $result;
}
