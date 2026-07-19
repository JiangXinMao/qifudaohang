<?php
include __DIR__ . '/../includes/common.php';
if($islogin !== 1){
  header('Location: ./login.php');
  exit;
}
$title = '后台 UI 预览';
include __DIR__ . '/head.php';
?>
<div class="container" style="padding-top:70px;">
  <div class="qf-demo-page">
    <div class="qf-demo-toolbar">
      <div>
        <h2>后台 UI 演示</h2>
        <p>白色侧栏、白色顶栏、浅灰内容区，图标恢复自然彩色，菜单支持分组折叠。</p>
      </div>
      <div class="qf-demo-tabs" aria-label="UI preview tabs">
        <span>仪表盘</span>
        <span class="active">个人信息</span>
        <span>数据统计</span>
      </div>
    </div>

    <div class="qf-demo-grid">
      <section class="qf-demo-card">
        <div class="qf-demo-cover">
          <strong>Clean Admin Console</strong>
          <span>保留后台功能位置，侧栏重新归集为可折叠分组，功能图标和按钮颜色更丰富。</span>
        </div>
      </section>

      <section class="qf-demo-card qf-demo-profile">
        <h3>个人资料</h3>
        <div class="qf-demo-row"><b>账户名</b><span>administrator</span></div>
        <div class="qf-demo-row"><b>昵称</b><span>祈福导航管理员</span></div>
        <div class="qf-demo-row"><b>手机</b><span>未绑定</span></div>
        <div class="qf-demo-row"><b>角色</b><span><span class="label label-primary">超级管理员</span></span></div>
      </section>
    </div>

    <div class="qf-demo-metrics">
      <div class="qf-demo-metric"><b>128</b><span>站点内容</span></div>
      <div class="qf-demo-metric"><b>24</b><span>分类栏目</span></div>
      <div class="qf-demo-metric"><b>16</b><span>待处理事项</span></div>
      <div class="qf-demo-metric"><b>99.8%</b><span>系统运行状态</span></div>
    </div>

    <section class="panel panel-default qf-demo-table">
      <div class="panel-heading"><h3 class="panel-title">内容管理预览</h3></div>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr><th>#</th><th>模块</th><th>状态</th><th>说明</th><th>操作</th></tr>
          </thead>
          <tbody>
            <tr><td>1</td><td>站点管理</td><td><span class="label label-success">正常</span></td><td>管理导航站点、排序、显隐状态。</td><td><a class="btn btn-xs btn-primary" href="./set_dh.php">查看</a></td></tr>
            <tr><td>2</td><td>分类管理</td><td><span class="label label-primary">已接入</span></td><td>左侧菜单和内容区保持统一白色皮肤。</td><td><a class="btn btn-xs btn-default" href="./set_category.php">查看</a></td></tr>
            <tr><td>3</td><td>系统设置</td><td><span class="label label-info">演示中</span></td><td>功能不动，只调整后台视觉层。</td><td><a class="btn btn-xs btn-default" href="./set.php">查看</a></td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
</body>
</html>
