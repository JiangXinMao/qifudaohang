<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

include __DIR__ . "/../includes/common.php";
require_once SYSTEM_ROOT.'backup_service.php';
if(!defined('QIFU_ENABLE_WEB_RESTORE') || !QIFU_ENABLE_WEB_RESTORE){
    http_response_code(404);
    exit;
}
$title='祈福导航系统 - 恢复数据';
if($islogin!=1){
    @header('Location: ./login.php');
    exit;
}
include __DIR__.'/head.php';

$msg = '';
$error = '';

if($_SERVER['REQUEST_METHOD']=='POST' && isset($_FILES['sqlfile'])){
    qifu_require_csrf();
    $file = $_FILES['sqlfile'];
    $restore_password = isset($_POST['restore_password']) ? (string)$_POST['restore_password'] : '';
    if(!qifu_admin_password_verify($restore_password)){
        $error = '当前管理员密码错误';
    }elseif($file['error']!=0){
        $error = '文件上传失败，错误码：'.$file['error'];
    }elseif(!is_uploaded_file($file['tmp_name']) || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'qifubak'){
        $error = '只允许上传本系统导出的 .qifubak 备份文件';
    }elseif($file['size']>32*1024*1024){
        $error = '文件大小不能超过 32MB';
    }else{
        try {
            $result = qifu_backup_restore_file($DB, $file['tmp_name']);
            $CACHE->clear();
            writeLog('恢复', '数据库', 0, '完整恢复 '.$result['tableCount'].' 张表、'.$result['rowCount'].' 条记录');
            $msg = '<div class="alert alert-success">恢复成功，共恢复 '.intval($result['tableCount']).' 张表、'.intval($result['rowCount']).' 条记录；恢复前快照已保存。</div>';
        } catch(Throwable $restore_error) {
            $error = $restore_error->getMessage();
        }
    }
}
?>
<div class="container" style="padding-top:70px;">
<div class="col-xs-12 col-sm-10 col-lg-8 center-block" style="float: none;">

<?php if($error) echo '<div class="alert alert-danger">'.htmlspecialchars($error, ENT_QUOTES, 'UTF-8').'</div>'; ?>
<?php echo $msg; ?>

<div class="panel panel-danger">
<div class="panel-heading"><h3 class="panel-title">⚠️ 恢复数据</h3></div>
<div class="panel-body">
<div class="alert alert-warning">
恢复操作会覆盖现有数据！建议恢复前先 <a href="<?php echo htmlspecialchars(qifu_csrf_url('./backup.php?action=create'), ENT_QUOTES, 'UTF-8'); ?>" class="alert-link">创建备份</a>。
</div>
<form method="post" enctype="multipart/form-data">
<?php echo qifu_csrf_input(); ?>
<div class="form-group">
<label>上传祈福完整备份文件</label>
<input type="file" name="sqlfile" accept=".qifubak" class="form-control" required>
</div>
<div class="form-group">
<label>当前管理员密码</label>
<input type="password" name="restore_password" class="form-control" required maxlength="128" autocomplete="current-password" placeholder="恢复前再次验证身份">
</div>
<button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('警告：恢复操作会覆盖现有数据！是否继续？')">
⚠️ 执行恢复
</button>
<a href="./backup.php" class="btn btn-default">返回备份管理</a>
</form>
</div>
</div>

<div class="panel panel-info">
<div class="panel-heading"><h3 class="panel-title">📝 恢复说明</h3></div>
<div class="panel-body">
<ol style="padding-left:20px;">
<li>执行恢复前系统会自动创建一份恢复前安全快照</li>
<li>仅接受本系统导出的 .qifubak 文件，并验证完整性校验和</li>
<li>恢复会覆盖站点设置、导航内容、分类、统计与操作记录</li>
<li>任一步骤失败都会自动回滚，不会保留部分恢复状态</li>
</ol>
</div>
</div>

</div>
</div>
