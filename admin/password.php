<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

include __DIR__ . "/../includes/common.php";
$title='祈福导航系统 - 账号安全';
if($islogin!=1){
    @header('Location: ./login.php');
    exit;
}
$msg = '';
if($_SERVER['REQUEST_METHOD']=='POST'){
    qifu_require_csrf();
    $old = isset($_POST['oldpwd']) ? (string)$_POST['oldpwd'] : '';
    $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
    $new1 = isset($_POST['newpwd']) ? (string)$_POST['newpwd'] : '';
    $new2 = isset($_POST['newpwd2']) ? (string)$_POST['newpwd2'] : '';
    if($old === '' || $username === '' || $new1 === '' || $new2 === ''){
        $msg = '<div class="alert alert-danger">请填写所有字段！</div>';
    }elseif(!qifu_username_valid($username)){
        $msg = '<div class="alert alert-danger">账号只能使用 '.qifu_username_policy_text().'！</div>';
    }elseif(!qifu_password_valid($new1)){
        $msg = '<div class="alert alert-danger">密码只能使用 '.qifu_password_policy_text().'！</div>';
    }elseif($new1 !== $new2){
        $msg = '<div class="alert alert-danger">两次输入的新密码不一致！</div>';
    }elseif(!qifu_admin_password_verify($old)){
        $msg = '<div class="alert alert-danger">原密码错误！</div>';
    }else{
        saveSetting('admin_user', $username);
        saveSetting('admin_pwd_hash', password_hash($new1, PASSWORD_DEFAULT));
        saveSetting('admin_pwd', '');
        saveSetting('admin_auth_version', qifu_auth_version() + 1);
        $CACHE->clear();
        writeLog('修改', '账号安全', 0, '管理员更新登录账号和密码');
        qifu_admin_logout_session();
        header('Location: ./login.php?changed=1');
        exit;
    }
}
include __DIR__.'/head.php';
?>
<div class="container" style="padding-top:70px;">
<div class="qf-detail-content qf-security-content center-block">
<?php echo $msg; ?>

<section class="art-page-header">
  <div class="art-page-header-main">
    <span class="art-page-header-icon glyphicon glyphicon-lock" aria-hidden="true"></span>
    <div class="art-page-header-copy">
      <h2>账号安全</h2>
      <p>修改后台登录账号与密码。保存后当前会话会立即退出，需要使用新凭据重新登录。</p>
    </div>
  </div>
</section>

<div class="art-security-layout">
<aside class="art-security-aside">
  <span class="art-security-aside-icon glyphicon glyphicon-user" aria-hidden="true"></span>
  <h3>登录凭据要求</h3>
  <p>账号与密码只保存在当前站点数据库中。密码使用不可逆哈希保存。</p>
  <ul class="art-policy-list">
    <li><span class="glyphicon glyphicon-ok"></span><span>账号：<?php echo htmlspecialchars(qifu_username_policy_text(), ENT_QUOTES, 'UTF-8'); ?></span></li>
    <li><span class="glyphicon glyphicon-ok"></span><span>密码：<?php echo htmlspecialchars(qifu_password_policy_text(), ENT_QUOTES, 'UTF-8'); ?></span></li>
    <li><span class="glyphicon glyphicon-ok"></span><span>修改成功后，旧登录会话立即失效。</span></li>
    <li><span class="glyphicon glyphicon-ok"></span><span>请避免使用与其他网站相同的密码。</span></li>
  </ul>
</aside>

<section class="art-security-form">
<div class="art-security-form-head">
  <h3>更新登录账号与密码</h3>
  <p>所有字段均为必填项。</p>
</div>
<form method="post">
<?php echo qifu_csrf_input(); ?>
<div class="form-group">
<label>原密码</label>
<div class="art-password-input">
<input type="password" name="oldpwd" class="form-control" required <?php echo qifu_password_input_attributes(); ?> autocomplete="current-password">
<button type="button" class="art-password-toggle" data-password-toggle="oldpwd" aria-label="显示原密码" aria-pressed="false"><span class="glyphicon glyphicon-eye-open"></span></button>
</div>
</div>
<div class="form-group">
<label>新账号</label>
<input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars(isset($conf['admin_user']) ? $conf['admin_user'] : '', ENT_QUOTES, 'UTF-8'); ?>" required <?php echo qifu_username_input_attributes(); ?> autocomplete="username" placeholder="<?php echo htmlspecialchars(qifu_username_policy_text(), ENT_QUOTES, 'UTF-8'); ?>">
</div>
<div class="form-group">
<label>新密码</label>
<div class="art-password-input">
<input type="password" name="newpwd" class="form-control" required <?php echo qifu_password_input_attributes(); ?> autocomplete="new-password" placeholder="<?php echo htmlspecialchars(qifu_password_policy_text(), ENT_QUOTES, 'UTF-8'); ?>">
<button type="button" class="art-password-toggle" data-password-toggle="newpwd" aria-label="显示新密码" aria-pressed="false"><span class="glyphicon glyphicon-eye-open"></span></button>
</div>
</div>
<div class="form-group">
<label>确认新密码</label>
<div class="art-password-input">
<input type="password" name="newpwd2" class="form-control" required <?php echo qifu_password_input_attributes(); ?> autocomplete="new-password" placeholder="再次输入新密码">
<button type="button" class="art-password-toggle" data-password-toggle="newpwd2" aria-label="显示确认密码" aria-pressed="false"><span class="glyphicon glyphicon-eye-open"></span></button>
</div>
</div>
<div class="form-group">
<button type="submit" class="btn btn-primary btn-block"><span class="glyphicon glyphicon-ok"></span>确认修改</button>
</div>
</form>
</section>
</div>
</div>
</div>

<script>
(function(){
  document.querySelectorAll('[data-password-toggle]').forEach(function(button){
    button.addEventListener('click', function(){
      var input = document.querySelector('input[name="' + button.getAttribute('data-password-toggle') + '"]');
      if(!input) return;
      var visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      button.setAttribute('aria-pressed', visible ? 'false' : 'true');
      button.setAttribute('aria-label', visible ? '显示密码' : '隐藏密码');
      var icon = button.querySelector('.glyphicon');
      if(icon) icon.className = 'glyphicon ' + (visible ? 'glyphicon-eye-open' : 'glyphicon-eye-close');
    });
  });
})();
</script>
