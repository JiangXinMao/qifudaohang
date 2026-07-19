<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */
include __DIR__ . "/../includes/common.php";
require_once SYSTEM_ROOT.'backup_service.php';
$title='祈福导航系统 - 数据备份';
if($islogin!=1){
    @header('Location: ./login.php');
    exit;
}
include __DIR__.'/head.php';

$msg = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// 备份记录表
try {
    qifu_backup_ensure_schema($DB);
} catch(Throwable $error) {
    $msg = '<div class="alert alert-danger">'.htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8').'</div>';
}

// 创建备份
if($action == 'create'){
    qifu_require_csrf();
    try {
        $backup = qifu_backup_create_file($DB, 'manual');
        if(!qifu_backup_register_file($DB, $backup)) throw new RuntimeException('备份记录写入失败。');
        writeLog('备份', '数据库', 0, '生成完整备份:'.$backup['filename']);
        header("Location: ./backup.php?created=1");
        exit;
    } catch(Throwable $error) {
        $msg = '<div class="alert alert-danger">'.htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8').'</div>';
    }
}

// 删除备份
if($action == 'del' && isset($_GET['id'])){
    qifu_require_csrf();
    $id = intval($_GET['id']);
    $row = $DB->prepared_row('SELECT * FROM web_backup WHERE id=?', array($id));
    if($row){
        $filename = basename((string)$row['filename']);
        $filepath = ROOT.'backup/'.$filename;
        if(file_exists($filepath)) @unlink($filepath);
        $DB->prepared_query('DELETE FROM web_backup WHERE id=?', array($id));
        $msg = '<div class="alert alert-success">备份文件已删除！</div>';
    }
}

// 下载备份
if($action == 'download' && isset($_GET['id'])){
    $id = intval($_GET['id']);
    $row = $DB->prepared_row('SELECT * FROM web_backup WHERE id=?', array($id));
    if($row){
        $filename = basename((string)$row['filename']);
        $filepath = ROOT.'backup/'.$filename;
        if(file_exists($filepath)){
            $download_name = preg_replace('/\.php$/i','',$filename);
            header(stripos($download_name, '.qifubak') !== false ? 'Content-Type: application/json; charset=UTF-8' : 'Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','_', $download_name).'"');
            header('X-Content-Type-Options: nosniff');
            $content = file_get_contents($filepath);
            if(strpos($content, '<?php http_response_code(404); exit; ?>') === 0) $content = substr($content, strpos($content, "\n") + 1);
            echo $content;
            exit;
        }
    }
}

$backups = $DB->get_results("SELECT * FROM web_backup ORDER BY id DESC");
if(isset($_GET['created'])) $msg = '<div class="alert alert-success">备份创建成功！</div>';
$backup_count = is_array($backups) ? count($backups) : 0;
$backup_total_size = 0;
if(is_array($backups)){
    foreach($backups as $backup_item) $backup_total_size += intval($backup_item['size']);
}
$backup_latest = $backup_count > 0 ? date('Y-m-d H:i', intval($backups[0]['addtime'])) : '尚未创建';
?>
<div class="container" style="padding-top:70px;">
<div class="qf-detail-content center-block">
<?php echo $msg; ?>

<section class="art-page-header">
  <div class="art-page-header-main">
    <span class="art-page-header-icon glyphicon glyphicon-hdd" aria-hidden="true"></span>
    <div class="art-page-header-copy">
      <h2>数据库备份</h2>
      <p>完整导出站点设置、导航内容、分类、统计与操作记录。建议在升级或批量修改前创建新备份。</p>
    </div>
  </div>
  <div class="art-page-header-actions">
    <a href="<?php echo htmlspecialchars(qifu_csrf_url('./backup.php?action=create'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary" onclick="return confirm('确认创建数据库备份？')">
      <span class="glyphicon glyphicon-plus"></span>创建新备份
    </a>
  </div>
</section>

<section class="art-backup-overview" aria-label="备份数据概览">
  <div class="art-backup-metric">
    <span class="glyphicon glyphicon-duplicate" aria-hidden="true"></span>
    <div><b><?php echo $backup_count; ?></b><small>备份文件数量</small></div>
  </div>
  <div class="art-backup-metric">
    <span class="glyphicon glyphicon-save" aria-hidden="true"></span>
    <div><b><?php echo round($backup_total_size / 1024, 1); ?> KB</b><small>备份文件总大小</small></div>
  </div>
  <div class="art-backup-metric art-backup-metric-date">
    <span class="glyphicon glyphicon-time" aria-hidden="true"></span>
    <div><b><?php echo htmlspecialchars($backup_latest, ENT_QUOTES, 'UTF-8'); ?></b><small>最近一次备份</small></div>
  </div>
</section>

<div class="panel panel-default">
<div class="panel-heading">
<h3 class="panel-title"><span class="glyphicon glyphicon-folder-open"></span>备份文件</h3>
</div>
<table class="table table-striped">
<thead><tr><th>文件名</th><th>大小</th><th>备份时间</th><th>操作</th></tr></thead>
<tbody>
<?php if(empty($backups)): ?>
<tr><td colspan="4"><div class="art-empty-state"><span class="glyphicon glyphicon-inbox"></span><strong>暂无备份文件</strong><span>创建备份后，文件会显示在这里。</span></div></td></tr>
<?php else: foreach($backups as $bk): ?>
<tr>
<td><span class="glyphicon glyphicon-file"></span> <?php echo htmlspecialchars($bk['filename'], ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo round($bk['size']/1024, 1); ?> KB</td>
<td><?php echo date('Y-m-d H:i:s', $bk['addtime']); ?></td>
<td>
<a href="./backup.php?action=download&id=<?php echo intval($bk['id']); ?>" class="btn btn-xs btn-primary">下载</a>
<a href="<?php echo htmlspecialchars(qifu_csrf_url('./backup.php?action=del&id='.intval($bk['id'])), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-xs btn-danger" onclick="return confirm('确认删除该备份？')">删除</a>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>

<div class="panel panel-default">
  <div class="panel-heading"><h3 class="panel-title"><span class="glyphicon glyphicon-info-sign"></span>备份范围</h3></div>
  <div class="panel-body">
    <ul class="art-policy-list">
      <li><span class="glyphicon glyphicon-ok"></span><span>导出网站设置、导航站点、分类、友链、广告、统计、更新与操作日志。</span></li>
      <li><span class="glyphicon glyphicon-ok"></span><span>备份文件保存在受保护的 <code>backup/</code> 目录，浏览器不能直接访问。</span></li>
      <li><span class="glyphicon glyphicon-ok"></span><span>下载后请保存在安全位置，删除操作无法撤销。</span></li>
    </ul>
  </div>
</div>
</div>
</div>
