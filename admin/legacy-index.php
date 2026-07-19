<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */
include __DIR__ . "/../includes/common.php";
require_once SYSTEM_ROOT.'site_stats.php';
$title='祈福导航系统 - 管理首页';
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

// 确保统计表存在
$stats_chk = $DB->get_row("SHOW TABLES LIKE 'web_stats'");
if(empty($stats_chk)){
    $DB->query("CREATE TABLE web_stats (
        id int(11) NOT NULL AUTO_INCREMENT,
        stat_date date NOT NULL,
        views int(11) NOT NULL DEFAULT 0,
        impressions int(11) NOT NULL DEFAULT 0,
        unique_visitors int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY stat_date (stat_date)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8");
}
qifu_site_stats_ensure_schema();

// 确保站点统计表存在（按站点统计）
$site_stats_chk = $DB->get_row("SHOW TABLES LIKE 'web_site_stats'");
if(empty($site_stats_chk)){
    $DB->query("CREATE TABLE web_site_stats (
        id int(11) NOT NULL AUTO_INCREMENT,
        site_id int(11) NOT NULL,
        stat_date date NOT NULL,
        views int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY site_date (site_id,stat_date),
        UNIQUE KEY site_date_unique (site_id,stat_date)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8");
}

// 统计数据
$total_sites = $DB->count("SELECT count(*) FROM web_dh");
$total_cats = $DB->count("SELECT count(*) FROM web_category");

// 访客统计
$today = date('Y-m-d');
$today_views = $DB->get_row("SELECT views FROM web_stats WHERE stat_date='$today'");
$today_views = $today_views ? $today_views['views'] : 0;

$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterday_views = $DB->get_row("SELECT views FROM web_stats WHERE stat_date='$yesterday'");
$yesterday_views = $yesterday_views ? $yesterday_views['views'] : 0;

$total_views = $DB->count("SELECT sum(views) FROM web_stats");
$total_views = $total_views ? $total_views : 0;

// 最近站点
$recent_sites = $DB->get_results("SELECT * FROM web_dh ORDER BY id DESC LIMIT 6");

// 今日操作
$recent_logs = $DB->get_results("SELECT * FROM web_log ORDER BY id DESC LIMIT 5");

// 站点运营概览
$active_sites = $DB->count("SELECT count(*) FROM web_dh WHERE active=1");
$hidden_sites = $DB->count("SELECT count(*) FROM web_dh WHERE active=0");
$today_site_clicks = $DB->count("SELECT COALESCE(SUM(views),0) FROM web_site_stats WHERE stat_date='$today'");
$total_site_clicks = $DB->count("SELECT COALESCE(SUM(views),0) FROM web_site_stats");
$top_sites = $DB->get_results("
    SELECT w.id,w.name,w.url,w.category,COALESCE(t.views,0) AS views
    FROM web_dh w
    LEFT JOIN (SELECT site_id,SUM(views) AS views FROM web_site_stats GROUP BY site_id) t ON w.id=t.site_id
    ORDER BY t.views DESC,w.id DESC
    LIMIT 5
");

// MySQL版本
$mysqlversion = $DB->count("SELECT VERSION()");

// 快速添加站点
$quick_msg = '';
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['quick_add'])){
    qifu_require_csrf();
    $name = mb_substr(trim((string)$_POST['q_name']), 0, 100);
    $url = mb_substr(trim((string)$_POST['q_url']), 0, 255);
    $category = mb_substr(trim((string)$_POST['q_cat']), 0, 50);
    $active = intval($_POST['q_active']) === 1 ? 1 : 0;
    if($name && filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url)){
        $DB->prepared_query('INSERT INTO web_dh (name,url,category,active) VALUES (?,?,?,?)', array($name,$url,$category,$active));
        writeLog('添加', '站点', 0, "名称:$name");
        $CACHE->clear();
        $quick_msg = '<div class="alert alert-success" style="margin:0 0 15px 0;">站点添加成功！</div>';
        header("Location: ./");
        exit;
    } else {
        $quick_msg = '<div class="alert alert-danger" style="margin:0 0 15px 0;">名称或 URL 格式不正确！</div>';
    }
}

// 获取分类列表（快速添加用）
$cats = $DB->get_results("SELECT name FROM web_category WHERE active=1 ORDER BY sort ASC");

// 趋势图数据（14天）
$chart_data = [];
for($i=13;$i>=0;$i--){
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $v = $DB->get_row("SELECT views FROM web_stats WHERE stat_date='$d'");
    $val = $v ? intval($v['views']) : 0;
    $sv = $DB->get_row("SELECT COALESCE(SUM(views),0) AS views FROM web_site_stats WHERE stat_date='$d'");
    $site_val = $sv ? intval($sv['views']) : 0;
    $chart_data[] = ['date'=>$d, 'views'=>$val, 'site_views'=>$site_val, 'day'=>date('m/d', strtotime($d))];
}
$chart_max = 1;
foreach($chart_data as $c) $chart_max = max($chart_max, max($c['views'], $c['site_views']));
$chart_w = 700;
$chart_h = 150;
$chart_left = 26;
$chart_right = 18;
$chart_top = 18;
$chart_bottom = 122;
$chart_plot_w = $chart_w - $chart_left - $chart_right;
$chart_points = [];
$chart_count = count($chart_data);
foreach($chart_data as $idx=>$item){
    $point_val = max($item['views'], $item['site_views']);
    $x = $chart_left + ($chart_count > 1 ? ($idx * $chart_plot_w / ($chart_count - 1)) : 0);
    $y = $chart_bottom - ($point_val > 0 ? ($point_val / $chart_max * ($chart_bottom - $chart_top)) : 0);
    $item['value'] = $point_val;
    $item['x'] = round($x, 2);
    $item['y'] = round($y, 2);
    $chart_points[] = $item;
}
$chart_line_points = implode(' ', array_map(function($p){ return $p['x'].','.$p['y']; }, $chart_points));
$chart_area_points = '';
if(!empty($chart_points)){
    $first = $chart_points[0];
    $last = $chart_points[count($chart_points)-1];
    $chart_area_points = $first['x'].','.$chart_bottom.' '.$chart_line_points.' '.$last['x'].','.$chart_bottom;
}
?>
<style>
.quick-btn{padding:15px 0;border-radius:8px;font-size:14px;min-height:80px;display:flex;flex-direction:column;justify-content:center;align-items:center}
.quick-btn i{font-size:24px;display:block;margin-bottom:8px}
.quick-btn:hover{transform:translateY(-3px);box-shadow:0 4px 15px rgba(0,0,0,0.1)}
.log-action{font-weight:bold}
.action-add{color:#27ae60}
.action-edit{color:#2980b9}
.action-del{color:#c0392b}
.action-batch{color:#8e44ad}
/* 统计卡 */
.stat-card{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:8px;padding:14px 10px;text-align:center;margin-bottom:10px}
@media(min-width:768px){.stat-card{margin-bottom:0}}
.stat-card.primary{background:linear-gradient(135deg,#667eea,#764ba2)}
.stat-card.success{background:linear-gradient(135deg,#11998e,#38ef7d)}
.stat-card.warning{background:linear-gradient(135deg,#f093fb,#f5576c)}
.stat-card.info{background:linear-gradient(135deg,#4facfe,#00f2fe);color:#333}
.stat-card.dark{background:linear-gradient(135deg,#2c3e50,#4ca1af)}
.stat-card .stat-num{font-size:22px;font-weight:700}
.stat-card .stat-label{font-size:11px;opacity:0.85;margin-top:4px}
.stat-icon{font-size:16px;margin-bottom:4px}
/* 趋势图 */
.chart-line-wrap{height:165px;padding:6px 4px 0}
.trend-svg{display:block;width:100%;height:140px;overflow:visible}
.trend-grid{stroke:#e8edf3;stroke-width:1}
.trend-axis{stroke:#d6dee8;stroke-width:1.2}
.trend-area{fill:url(#trendFill)}
.trend-line{fill:none;stroke:#667eea;stroke-width:3.2;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 5px 10px rgba(102,126,234,.25))}
.chart-point{fill:#fff;stroke:#667eea;stroke-width:3;transition:.2s}
.chart-hit{fill:transparent;cursor:pointer}
.chart-point-group.zero .chart-hit{cursor:default}
.chart-point-group.zero .chart-point{fill:#f1f3f5;stroke:#cfd8dc}
.chart-point-group:not(.zero):hover .chart-point{stroke:#764ba2;stroke-width:4;filter:drop-shadow(0 4px 8px rgba(118,75,162,.32))}
.chart-meta{display:flex;justify-content:space-between;align-items:center;color:#7b8794;font-size:12px;margin-top:2px}
.chart-meta b{color:#667eea;font-weight:600}
/* 快速添加 */
.quick-add-box{border:2px dashed #ccc;border-radius:8px;padding:15px;background:#fafafa}
.ops-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;padding:15px}
.ops-pill{border:1px solid #e5e9ef;border-radius:6px;background:#fafafa;padding:12px 8px;text-align:center}
.ops-pill b{display:block;font-size:20px;color:#2c3e50;line-height:1.1}
.ops-pill span{display:block;margin-top:5px;font-size:12px;color:#7f8c8d}
.top-site-list{padding:0 15px 15px}
.top-site-row{display:flex;align-items:center;gap:10px;border-top:1px solid #eef1f4;padding:10px 0;font-size:13px}
.top-site-row:first-child{border-top:0}
.top-site-rank{width:24px;height:24px;border-radius:50%;background:#eef4ff;color:#337ab7;text-align:center;line-height:24px;font-weight:bold;flex-shrink:0}
.top-site-name{flex:1;min-width:0}
.top-site-name a{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.top-site-name small{display:block;color:#999;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
@media(max-width:768px){.ops-grid{grid-template-columns:repeat(2,1fr)}}
</style>
<div class="container" style="padding-top:70px;">
<div class="qf-detail-content center-block">
<main class="art-dashboard">

<?php
$diff = $today_views - $yesterday_views;
if($today_views > 0 && $yesterday_views > 0){
    $pct = round(($today_views - $yesterday_views) / $yesterday_views * 100);
    $pct_label = ($pct >= 0 ? '+' : '').$pct.'%';
} elseif($today_views > 0){
    $pct_label = '+100%';
} else {
    $pct_label = '0%';
}
?>

<section class="art-welcome">
  <div class="art-welcome-copy">
    <h2>欢迎回来，管理员</h2>
    <p>这里汇总了导航站点、分类、访问趋势和近期操作。所有数据均来自当前站点数据库。</p>
  </div>
  <div class="art-welcome-actions">
    <span class="art-status-chip"><i></i>系统运行正常</span>
    <a class="btn btn-primary" href="./set_dh.php?my=add"><span class="glyphicon glyphicon-plus"></span>新增站点</a>
  </div>
</section>

<section class="art-stat-grid" aria-label="运营数据概览">
  <article class="art-stat-card">
    <span class="art-stat-icon glyphicon glyphicon-eye-open" aria-hidden="true"></span>
    <div class="art-stat-copy">
      <div class="art-stat-label">今日浏览</div>
      <div class="art-stat-value"><?php echo intval($today_views); ?></div>
      <div class="art-stat-foot"><strong><?php echo htmlspecialchars($pct_label, ENT_QUOTES, 'UTF-8'); ?></strong> 较昨日 <?php echo intval($yesterday_views); ?></div>
    </div>
  </article>
  <article class="art-stat-card is-green">
    <span class="art-stat-icon glyphicon glyphicon-stats" aria-hidden="true"></span>
    <div class="art-stat-copy">
      <div class="art-stat-label">累计浏览量</div>
      <div class="art-stat-value"><?php echo intval($total_views); ?></div>
      <div class="art-stat-foot">全站历史访问统计</div>
    </div>
  </article>
  <article class="art-stat-card is-amber">
    <span class="art-stat-icon glyphicon glyphicon-globe" aria-hidden="true"></span>
    <div class="art-stat-copy">
      <div class="art-stat-label">站点总数</div>
      <div class="art-stat-value"><?php echo intval($total_sites); ?></div>
      <div class="art-stat-foot"><?php echo intval($active_sites); ?> 个正在前台显示</div>
    </div>
  </article>
  <article class="art-stat-card is-cyan">
    <span class="art-stat-icon glyphicon glyphicon-folder-open" aria-hidden="true"></span>
    <div class="art-stat-copy">
      <div class="art-stat-label">分类总数</div>
      <div class="art-stat-value"><?php echo intval($total_cats); ?></div>
      <div class="art-stat-foot">用于组织导航内容</div>
    </div>
  </article>
</section>

<!-- 趋势图 -->
<div class="row" style="margin-bottom:20px;">
<div class="col-xs-12">
<div class="panel panel-default art-section">
<div class="panel-heading"><h3 class="panel-title"><span class="glyphicon glyphicon-stats"></span>近14天浏览趋势</h3></div>
<div class="panel-body">
<div class="chart-line-wrap" id="trendChart">
<svg class="trend-svg" viewBox="0 0 <?php echo $chart_w; ?> <?php echo $chart_h; ?>" preserveAspectRatio="none" role="img" aria-label="近14天浏览趋势折线图">
<defs>
  <linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0%" stop-color="#667eea" stop-opacity=".26"/>
    <stop offset="100%" stop-color="#667eea" stop-opacity="0"/>
  </linearGradient>
</defs>
<g class="trend-grid">
  <line x1="<?php echo $chart_left; ?>" y1="35" x2="<?php echo $chart_w-$chart_right; ?>" y2="35"></line>
  <line x1="<?php echo $chart_left; ?>" y1="70" x2="<?php echo $chart_w-$chart_right; ?>" y2="70"></line>
  <line x1="<?php echo $chart_left; ?>" y1="105" x2="<?php echo $chart_w-$chart_right; ?>" y2="105"></line>
</g>
<line class="trend-axis" x1="<?php echo $chart_left; ?>" y1="<?php echo $chart_bottom; ?>" x2="<?php echo $chart_w-$chart_right; ?>" y2="<?php echo $chart_bottom; ?>"></line>
<?php if($chart_area_points): ?><polygon class="trend-area" points="<?php echo $chart_area_points; ?>"></polygon><?php endif; ?>
<?php if($chart_line_points): ?><polyline class="trend-line" points="<?php echo $chart_line_points; ?>"></polyline><?php endif; ?>
<?php foreach($chart_points as $item):
    $zero_cls = $item['value'] == 0 ? ' zero' : '';
?>
<g class="chart-point-group<?php echo $zero_cls; ?>" data-date="<?php echo $item['date']; ?>" data-day="<?php echo $item['day']; ?>" data-views="<?php echo $item['views']; ?>" data-site-views="<?php echo $item['site_views']; ?>">
  <circle class="chart-hit" cx="<?php echo $item['x']; ?>" cy="<?php echo $item['y']; ?>" r="16"></circle>
  <circle class="chart-point" cx="<?php echo $item['x']; ?>" cy="<?php echo $item['y']; ?>" r="5"></circle>
</g>
<?php endforeach; ?>
</svg>
<div class="chart-meta">
  <span>← 14天前</span>
  <b>折线越高，浏览/点击越多</b>
  <span>今天 →</span>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- 快速添加站点 -->
<div class="row" style="margin-bottom:20px;">
<div class="col-sm-12">
<div class="panel panel-primary art-section">
<div class="panel-heading"><h3 class="panel-title"><span class="glyphicon glyphicon-flash"></span>快速添加站点</h3></div>
<div class="panel-body">
<?php echo $quick_msg; ?>
<div class="row">
<form method="post" class="form-inline art-quick-form" style="width:100%;">
<input type="hidden" name="quick_add" value="1">
<div class="form-group col-xs-6 col-sm-3" style="margin-bottom:8px;">
<input type="text" name="q_name" class="form-control" placeholder="站点名称" required style="width:100%;">
</div>
<div class="form-group col-xs-6 col-sm-4" style="margin-bottom:8px;">
<input type="text" name="q_url" class="form-control" placeholder="URL地址" required style="width:100%;">
</div>
<div class="form-group col-xs-6 col-sm-2" style="margin-bottom:8px;">
<select name="q_cat" class="form-control" style="width:100%;">
<?php if($cats) foreach($cats as $c) echo '<option value="'.htmlspecialchars($c['name']).'">'.htmlspecialchars($c['name']).'</option>'; ?>
</select>
</div>
<div class="form-group col-xs-6 col-sm-3 art-radio-group" style="margin-bottom:8px;">
<label style="display:inline;margin-right:10px;"><input type="radio" name="q_active" value="1" checked> 显示</label>
<label style="display:inline;"><input type="radio" name="q_active" value="0"> 隐藏</label>
</div>
<button type="submit" class="btn btn-primary btn-sm"><span class="glyphicon glyphicon-plus"></span>快速添加</button>
</form>
</div>
</div>
</div>
</div>
</div>

<!-- 左侧：最近站点 右侧：最新操作日志 -->
<div class="row art-dashboard-grid">
<div class="col-sm-7">
<div class="panel panel-default">
<div class="panel-heading"><h3 class="panel-title"><span class="glyphicon glyphicon-time"></span>最近添加的站点</h3></div>
<div class="table-responsive">
<table class="table table-striped table-hover" style="font-size:13px;margin-bottom:0;">
<thead><tr><th>#</th><th>名称</th><th>分类</th><th>状态</th><th>操作</th></tr></thead>
<tbody>
<?php if(empty($recent_sites)): ?>
<tr><td colspan="5" class="text-center text-muted">暂无站点，<a href="./set_dh.php?my=add">立即添加</a></td></tr>
<?php else: foreach($recent_sites as $r): ?>
<tr>
<td><?php echo $r['id']; ?></td>
<td><?php echo htmlspecialchars($r['name']); ?></td>
<td><span class="label label-info"><?php echo isset($r['category'])?htmlspecialchars($r['category']):'默认'; ?></span></td>
<td><?php echo $r['active']==1?'<span class="label label-success">显示</span>':'<span class="label label-default">隐藏</span>'; ?></td>
<td><a href="./set_dh.php?my=edit&id=<?php echo $r['id']; ?>" class="btn btn-xs btn-warning">编辑</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>

<div class="panel panel-default" style="margin-top:15px;">
<div class="panel-heading"><h3 class="panel-title"><span class="glyphicon glyphicon-dashboard"></span>站点运营概览</h3></div>
<div class="ops-grid">
<div class="ops-pill"><b><?php echo intval($active_sites); ?></b><span>显示站点</span></div>
<div class="ops-pill"><b><?php echo intval($hidden_sites); ?></b><span>隐藏站点</span></div>
<div class="ops-pill"><b><?php echo intval($today_site_clicks); ?></b><span>今日点击</span></div>
<div class="ops-pill"><b><?php echo intval($total_site_clicks); ?></b><span>累计点击</span></div>
</div>
<div class="top-site-list">
<?php if(empty($top_sites)): ?>
<div class="text-center text-muted" style="padding:8px 0 12px;">暂无站点数据</div>
<?php else: foreach($top_sites as $idx=>$site): ?>
<div class="top-site-row">
<div class="top-site-rank"><?php echo $idx+1; ?></div>
<div class="top-site-name">
<a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($site['name']); ?></a>
<small><?php echo htmlspecialchars($site['category']); ?></small>
</div>
<span class="label label-primary"><?php echo intval($site['views']); ?> 次</span>
</div>
<?php endforeach; endif; ?>
</div>
</div>
</div>

<div class="col-sm-5">
<div class="panel panel-default">
<div class="panel-heading"><h3 class="panel-title"><span class="glyphicon glyphicon-list-alt"></span>最新操作日志</h3></div>
<ul class="list-group" style="margin-bottom:0;">
<?php if(empty($recent_logs)): ?>
<li class="list-group-item text-center text-muted">暂无日志</li>
<?php else: foreach($recent_logs as $lg):
    $aclass = 'action-'.mb_substr($lg['action'],0,2,'utf-8');
    $time = date('m-d H:i', $lg['addtime']);
?>
<li class="list-group-item" style="font-size:12px;padding:8px 15px;">
<span class="log-action <?php echo htmlspecialchars($aclass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lg['action'], ENT_QUOTES, 'UTF-8'); ?></span>
<span class="label label-default"><?php echo htmlspecialchars($lg['target'], ENT_QUOTES, 'UTF-8'); ?></span>
<small class="text-muted" style="float:right;"><?php echo $time; ?></small>
<br><small><?php echo htmlspecialchars($lg['detail']); ?></small>
</li>
<?php endforeach; endif; ?>
<li class="list-group-item text-center" style="padding:8px;">
<a href="./logs.php">查看全部日志 &raquo;</a>
</li>
</ul>
</div>

</div>
</div>

</main>
</div>
</div>

<!-- 站点详情弹窗 -->
<div class="modal fade" id="siteModal">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal">×</button><h4 id="siteModalTitle">站点访问详情</h4></div>
<div class="modal-body" style="max-height:400px;overflow-y:auto;">
<table class="table table-bordered table-striped" style="margin-bottom:0;">
<thead><tr><th style="width:58px;">排名</th><th>站点名称</th><th>分类</th><th style="width:90px;">点击量</th></tr></thead>
<tbody id="siteModalBody"></tbody>
</table>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-default" data-dismiss="modal">关闭</button>
</div>
</div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.chart-point-group').forEach(function(point){
        point.addEventListener('click', function(){
            if(this.classList.contains('zero')) return;
            var date = this.getAttribute('data-date');
            var day = this.getAttribute('data-day');
            var views = this.getAttribute('data-views');
            var siteViews = this.getAttribute('data-site-views');
            document.getElementById('siteModalTitle').textContent = day + ' 站点点击详情（站点点击' + siteViews + '次，页面浏览' + views + '次）';
            document.getElementById('siteModalBody').innerHTML = '<tr><td colspan="4" class="text-center"><em>加载中...</em></td></tr>';
            $('#siteModal').modal('show');
            // 获取该日各站点访问量
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'ajax_site_stats.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function(){
                var data = [];
                try {
                    data = JSON.parse(xhr.responseText || '[]');
                } catch(e) {
                    data = [];
                }
                var html = '';
                if(data.length === 0){
                    html = '<tr><td colspan="4" class="text-center text-muted">暂无站点点击记录<br><small style="color:#999;">从本次更新后，前台站点卡片被点击会自动记录到这里</small></td></tr>';
                } else {
                    data.forEach(function(item, idx){
                        var rank = idx + 1;
                        html += '<tr><td>' + rank + '</td><td><a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener">' + escapeHtml(item.name) + '</a><br><small class="text-muted">' + escapeHtml(item.url) + '</small></td><td>' + escapeHtml(item.category || '默认') + '</td><td><span class="badge">' + escapeHtml(item.views) + '</span></td></tr>';
                    });
                }
                document.getElementById('siteModalBody').innerHTML = html;
            };
            xhr.onerror = function(){
                document.getElementById('siteModalBody').innerHTML = '<tr><td colspan="4" class="text-center text-muted">加载失败</td></tr>';
            };
            xhr.send('date=' + encodeURIComponent(date) + '&_csrf=' + encodeURIComponent(document.querySelector('meta[name="qifu-csrf"]').getAttribute('content')));
        });
    });

    function escapeHtml(text){
        return String(text == null ? '' : text).replace(/[&<>"']/g, function(s){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s];
        });
    }
});
</script>
