<?php
include __DIR__ . "/../includes/common.php";

$login_notice_type = '';
$login_notice_title = '';
$login_notice_message = '';
$login_notice_redirect = '';
$login_site_name = isset($conf['sitename']) && trim($conf['sitename']) !== '' ? trim($conf['sitename']) : '祈福导航系统';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout'){
    qifu_require_csrf();
    qifu_admin_logout_session();
    setcookie('admin_token', '', array('expires'=>time()-3600,'path'=>'/','secure'=>qifu_is_https(),'httponly'=>true,'samesite'=>'Strict'));
    $islogin = 0;
    $login_notice_type = 'success';
    $login_notice_title = '已退出登录';
    $login_notice_message = '当前会话已安全结束。';
} elseif($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user']) && isset($_POST['pass'])){
    qifu_require_csrf();
    $user = trim((string)$_POST['user']);
    $pass = (string)$_POST['pass'];
    $wait = qifu_login_rate_wait($user);
    $format_ok = qifu_username_valid($user) && qifu_password_valid($pass);
    $user_ok = $format_ok && isset($conf['admin_user']) && hash_equals((string)$conf['admin_user'], $user);
    $pass_ok = $user_ok && qifu_admin_password_verify($pass);
    if($wait <= 0 && $user_ok && $pass_ok) {
        qifu_login_rate_clear($user);
        qifu_admin_password_migrate($pass);
        qifu_admin_login_session($user);
        $login_notice_type = 'success';
        $login_notice_title = '登录成功';
        $login_notice_message = '验证通过，正在进入后台。';
        $login_notice_redirect = './';
    } else {
        if($wait <= 0) qifu_login_rate_fail($user);
        $login_notice_type = 'error';
        $login_notice_title = '登录失败';
        $login_notice_message = $wait > 0 ? '尝试次数过多，请稍后再试。' : '账号或密码错误，请重新输入。';
    }
} elseif(isset($_GET['changed']) && $_GET['changed'] === '1'){
    $login_notice_type = 'success';
    $login_notice_title = '账号安全设置已更新';
    $login_notice_message = '请使用新的账号和密码登录。';
} elseif($islogin == 1){
    $login_notice_type = 'success';
    $login_notice_title = '已登录';
    $login_notice_message = '正在进入后台。';
    $login_notice_redirect = './';
}

$islogin = 0;
$title = $login_site_name . ' 管理登录';
include __DIR__.'/head.php';
?>
  <main class="qf-login-shell">
    <section class="qf-login-aside">
      <h1><?php echo htmlspecialchars($login_site_name, ENT_QUOTES, 'UTF-8'); ?></h1>
      <p>专注网址导航、分类管理、广告投放、友链审核与访问统计，提供稳定清晰的后台运营能力。</p>
      <div class="qf-login-meta">
        <span><b>Sites</b>&#31449;&#28857;&#36816;&#33829;</span>
        <span><b>Logs</b>&#25805;&#20316;&#30041;&#30165;</span>
        <span><b>Backup</b>&#25968;&#25454;&#32500;&#25252;</span>
      </div>
    </section>
    <section class="qf-login-panel">
      <h2>&#31649;&#29702;&#21592;&#30331;&#24405;</h2>
      <p><?php echo htmlspecialchars($login_site_name, ENT_QUOTES, 'UTF-8'); ?></p>
      <form action="./login.php" method="post" role="form">
        <?php echo qifu_csrf_input(); ?>
        <div class="input-group">
          <span class="input-group-addon"><span class="glyphicon glyphicon-user"></span></span>
          <input type="text" name="user" value="<?php echo htmlspecialchars(isset($_POST['user']) ? $_POST['user'] : ''); ?>" class="form-control" placeholder="<?php echo htmlspecialchars(qifu_username_policy_text(), ENT_QUOTES, 'UTF-8'); ?>" required="required" <?php echo qifu_username_input_attributes(); ?> autocomplete="username"/>
        </div>
        <div class="input-group">
          <span class="input-group-addon"><span class="glyphicon glyphicon-lock"></span></span>
          <input type="password" name="pass" class="form-control" placeholder="<?php echo htmlspecialchars(qifu_password_policy_text(), ENT_QUOTES, 'UTF-8'); ?>" required="required" <?php echo qifu_password_input_attributes(); ?> autocomplete="current-password"/>
        </div>
        <button type="submit" class="btn btn-primary">
          <span class="glyphicon glyphicon-log-in"></span> &#36827;&#20837;&#21518;&#21488;
        </button>
      </form>
      <div class="qf-login-links">
        <a href="../" target="_blank"><span class="glyphicon glyphicon-new-window"></span> &#26597;&#30475;&#21069;&#21488;</a>
        <a href="./login.php"><span class="glyphicon glyphicon-refresh"></span> &#21047;&#26032;&#30331;&#24405;</a>
      </div>
    </section>
  </main>
  <?php if($login_notice_type !== ''): ?>
  <div class="qf-login-feedback qf-login-feedback-<?php echo htmlspecialchars($login_notice_type, ENT_QUOTES, 'UTF-8'); ?>" id="qfLoginFeedback" data-redirect="<?php echo htmlspecialchars($login_notice_redirect, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="qf-login-feedback-card">
      <span class="qf-login-feedback-icon"></span>
      <strong><?php echo htmlspecialchars($login_notice_title, ENT_QUOTES, 'UTF-8'); ?></strong>
      <p><?php echo htmlspecialchars($login_notice_message, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
  </div>
  <script>
  (function(){
    var feedback = document.getElementById('qfLoginFeedback');
    if(!feedback) return;
    var redirect = feedback.getAttribute('data-redirect') || '';
    window.setTimeout(function(){
      if(redirect){
        window.location.href = redirect;
        return;
      }
      feedback.classList.add('is-leaving');
      window.setTimeout(function(){
        if(feedback && feedback.parentNode){ feedback.parentNode.removeChild(feedback); }
      }, 180);
    }, redirect ? 700 : 1200);
  })();
  </script>
  <?php endif; ?>
</body>
</html>
