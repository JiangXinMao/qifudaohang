<?php
if(PHP_SAPI !== 'cli' && !defined('QIFU_INSTALL_CONTEXT')){
    http_response_code(404);
    exit;
}

function qifu_install_write_lock($lock_path){
    $lock_path = (string)$lock_path;
    if(is_file($lock_path)) return true;
    if($lock_path === '' || file_exists($lock_path)) return false;
    $directory = dirname($lock_path);
    if(!is_dir($directory) || !is_writable($directory)) return false;
    $content = '安装锁';
    $written = @file_put_contents($lock_path, $content, LOCK_EX);
    if($written !== strlen($content) || !is_file($lock_path)) return false;
    @chmod($lock_path, 0644);
    return hash_equals(hash('sha256', $content), (string)@hash_file('sha256', $lock_path));
}

function qifu_install_write_config($config_path, $content){
    $config_path = (string)$config_path;
    $content = (string)$content;
    $directory = dirname($config_path);
    if($config_path === '' || !is_dir($directory)) return false;
    if(is_file($config_path) && !is_writable($config_path)) return false;
    if(!file_exists($config_path) && !is_writable($directory)) return false;
    $written = @file_put_contents($config_path, $content, LOCK_EX);
    if($written !== strlen($content) || !is_file($config_path)) return false;
    @chmod($config_path, 0600);
    return hash_equals(hash('sha256', $content), (string)@hash_file('sha256', $config_path));
}

function qifu_install_completion($lock_path, $account_text, $admin_directory = 'admin'){
    if(!qifu_install_write_lock($lock_path)){
        return array(
            'success' => false,
            'html' => '<div class="alert alert-danger"><b>安装尚未完成：</b>无法创建 <code>install/install.lock</code>。请为 install 目录授予 PHP 写权限后重试；在锁文件创建成功前，系统不会开放前台和后台入口。</div>',
        );
    }
    $admin_directory = trim(str_replace(array('/', '\\'), '', (string)$admin_directory));
    if($admin_directory === '') $admin_directory = 'admin';
    $admin_href = '../'.rawurlencode($admin_directory).'/';
    $admin_path_html = htmlspecialchars('/'.$admin_directory, ENT_QUOTES, 'UTF-8');
    return array(
        'success' => true,
        'html' => '<div class="alert alert-info art-install-completion"><font color="green">安装完成！'.htmlspecialchars((string)$account_text, ENT_QUOTES, 'UTF-8').'</font><br/><br/><a href="../">&gt;&gt;网站首页</a>｜<a href="'.htmlspecialchars($admin_href, ENT_QUOTES, 'UTF-8').'">&gt;&gt;后台管理</a><hr/>更多设置选项请登录后台管理进行修改。</div><div class="alert alert-warning qifu-admin-path-warning art-install-security"><b>后台安全提醒：</b>请及时修改后台路径，请不要使用 <code>/admin</code> 作为您的后台！请将网站根目录中的后台文件夹重命名为不易猜测的名称。当前识别路径：<code>'.$admin_path_html.'</code>。</div>',
    );
}
?>
