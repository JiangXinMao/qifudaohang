<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

include __DIR__ . "/../includes/common.php";
$title='祈福导航系统 - 操作日志';
if($islogin!=1){
    @header('Location: ./login.php');
    exit;
}
include __DIR__.'/head.php';

// 确保日志表存在
$log_chk = $DB->get_row("SHOW TABLES LIKE 'web_log'");
if(empty($log_chk)){
    $DB->query("CREATE TABLE web_log (
        id int(11) NOT NULL AUTO_INCREMENT,
        action varchar(50) NOT NULL,
        target varchar(50) NOT NULL,
        target_id int(11) DEFAULT NULL,
        detail varchar(255) DEFAULT NULL,
        ip varchar(50) DEFAULT NULL,
        addtime int(11) NOT NULL,
        PRIMARY KEY (id),
        KEY action (action),
        KEY addtime (addtime)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8");
}

// 分页
$page = max(1, intval(@$_GET['page']));
$perpage = 20;
$offset = ($page-1)*$perpage;

// 筛选
$filter_action = isset($_GET['action']) ? trim($_GET['action']) : '';
$filter_target = isset($_GET['target']) ? trim($_GET['target']) : '';

$where = array('1=1');
$params = array();
if($filter_action){ $where[] = 'action=?'; $params[] = mb_substr($filter_action,0,50); }
if($filter_target){ $where[] = 'target=?'; $params[] = mb_substr($filter_target,0,50); }
$where_sql = implode(' AND ', $where);

$total = intval($DB->prepared_value("SELECT count(*) FROM web_log WHERE $where_sql", $params));
$logs = $DB->prepared_results("SELECT * FROM web_log WHERE $where_sql ORDER BY id DESC LIMIT $offset,$perpage", $params);
$pages = ceil($total/$perpage);

// 统计
$action_stats = $DB->get_results("SELECT action, count(*) as cnt FROM web_log GROUP BY action ORDER BY cnt DESC");

// 清除日志
if(isset($_GET['clear'])){
    qifu_require_csrf();
    $DB->query(defined('SQLITE') ? 'DELETE FROM web_log' : 'TRUNCATE TABLE web_log');
    header("Location: ./logs.php");
    exit;
}
?>
<style>
.log-action{font-weight:bold}
.action-add{color:#27ae60}
.action-edit{color:#2980b9}
.action-delete{color:#c0392b}
.action-batch{color:#8e44ad}
.log-table{font-size:13px}
.log-time{color:#999;font-size:12px}
</style>
<div class="container" style="padding-top:70px;">
<div class="qf-detail-content center-block">

<section class="art-page-header">
  <div class="art-page-header-main">
    <span class="art-page-header-icon glyphicon glyphicon-list-alt" aria-hidden="true"></span>
    <div class="art-page-header-copy">
      <h2>操作日志</h2>
      <p>查看后台设置、内容维护和数据操作记录，用于安全审计与问题追踪。</p>
    </div>
  </div>
  <div class="art-page-header-actions">
    <a href="<?php echo htmlspecialchars(qifu_csrf_url('./logs.php?clear=1'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-danger" onclick="return confirm('确认清空所有日志？该操作不可恢复。')"><span class="glyphicon glyphicon-trash"></span>清空日志</a>
  </div>
</section>

<div class="panel panel-default">
<div class="panel-heading"><h3 class="panel-title"><span class="glyphicon glyphicon-time"></span>日志记录</h3></div>
<div class="art-toolbar">
<form method="get" class="art-toolbar-form">
<select name="action" class="form-control">
<option value="">全部操作</option>
<option value="添加" <?php echo $filter_action=='添加'?'selected':''; ?>>添加</option>
<option value="修改" <?php echo $filter_action=='修改'?'selected':''; ?>>修改</option>
<option value="删除" <?php echo $filter_action=='删除'?'selected':''; ?>>删除</option>
<option value="批量删除" <?php echo $filter_action=='批量删除'?'selected':''; ?>>批量删除</option>
<option value="批量显示" <?php echo $filter_action=='批量显示'?'selected':''; ?>>批量显示</option>
<option value="批量隐藏" <?php echo $filter_action=='批量隐藏'?'selected':''; ?>>批量隐藏</option>
<option value="批量移动" <?php echo $filter_action=='批量移动'?'selected':''; ?>>批量移动</option>
</select>
<select name="target" class="form-control">
<option value="">全部对象</option>
<option value="站点" <?php echo $filter_target=='站点'?'selected':''; ?>>站点</option>
<option value="分类" <?php echo $filter_target=='分类'?'selected':''; ?>>分类</option>
<option value="设置" <?php echo $filter_target=='设置'?'selected':''; ?>>设置</option>
</select>
<button type="submit" class="btn btn-primary">筛选</button>
<a href="./logs.php" class="btn btn-default">重置</a>
<span class="art-toolbar-count">共 <?php echo $total; ?> 条记录</span>
</form>
<div>
<?php if($pages>1): ?>
<ul class="pagination" style="margin:0;">
<?php if($page>1): ?><li><a href="?page=<?php echo $page-1; ?>&action=<?php echo urlencode($filter_action); ?>&target=<?php echo urlencode($filter_target); ?>">&laquo;</a></li><?php endif; ?>
<?php for($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
<li <?php echo $i==$page?'class="active"':''; ?>><a href="?page=<?php echo $i; ?>&action=<?php echo urlencode($filter_action); ?>&target=<?php echo urlencode($filter_target); ?>"><?php echo $i; ?></a></li>
<?php endfor; ?>
<?php if($page<$pages): ?><li><a href="?page=<?php echo $page+1; ?>&action=<?php echo urlencode($filter_action); ?>&target=<?php echo urlencode($filter_target); ?>">&raquo;</a></li><?php endif; ?>
</ul>
<?php endif; ?>
</div>
</div>

<table class="table table-striped table-hover log-table">
<thead><tr><th>时间</th><th>操作</th><th>对象</th><th>详情</th><th>IP</th></tr></thead>
<tbody>
<?php if(empty($logs)): ?>
<tr><td colspan="5"><div class="art-empty-state"><span class="glyphicon glyphicon-list-alt"></span><strong>暂无日志记录</strong><span>后台操作产生记录后会显示在这里。</span></div></td></tr>
<?php else: foreach($logs as $log):
    $aclass = 'action-'.mb_substr($log['action'],0,2,'utf-8');
    $time = date('Y-m-d H:i', $log['addtime']);
?>
<tr>
<td class="log-time"><?php echo $time; ?></td>
<td><span class="log-action <?php echo htmlspecialchars($aclass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8'); ?></span></td>
<td><span class="label label-default"><?php echo htmlspecialchars($log['target'], ENT_QUOTES, 'UTF-8'); ?></span><?php if($log['target_id']) echo ' #'.intval($log['target_id']); ?></td>
<td><?php echo htmlspecialchars($log['detail']); ?></td>
<td><small><?php echo htmlspecialchars($log['ip'], ENT_QUOTES, 'UTF-8'); ?></small></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<?php if($pages>1): ?>
<ul class="pagination">
<?php if($page>1): ?><li><a href="?page=<?php echo $page-1; ?>&action=<?php echo urlencode($filter_action); ?>&target=<?php echo urlencode($filter_target); ?>">&laquo;</a></li><?php endif; ?>
<?php for($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
<li <?php echo $i==$page?'class="active"':''; ?>><a href="?page=<?php echo $i; ?>&action=<?php echo urlencode($filter_action); ?>&target=<?php echo urlencode($filter_target); ?>"><?php echo $i; ?></a></li>
<?php endfor; ?>
<?php if($page<$pages): ?><li><a href="?page=<?php echo $page+1; ?>&action=<?php echo urlencode($filter_action); ?>&target=<?php echo urlencode($filter_target); ?>">&raquo;</a></li><?php endif; ?>
</ul>
<?php endif; ?>
</div>
</div>
</div>
