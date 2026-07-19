<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

include __DIR__ . "/../includes/common.php";
$title='祈福导航系统 - 站点管理';
if($islogin!=1){
    @header('Location: ./login.php');
    exit;
}
include __DIR__.'/head.php';

// 字段升级
$cols = ['category','description','desc_marquee','desc_speed','desc_color','icon','sort','clicks'];
$defs = [
    'category'=>"varchar(50) NOT NULL DEFAULT '常用推荐'",
    'description'=>"varchar(255) NOT NULL DEFAULT ''",
    'desc_marquee'=>"tinyint(1) NOT NULL DEFAULT 0",
    'desc_speed'=>"varchar(20) NOT NULL DEFAULT 'normal'",
    'desc_color'=>"varchar(20) NOT NULL DEFAULT 'default'",
    'icon'=>"varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''",
    'sort'=>"int(11) NOT NULL DEFAULT 100",
    'clicks'=>"int(11) NOT NULL DEFAULT 0"
];
foreach($cols as $col){
    $check = $DB->query("SHOW COLUMNS FROM web_dh LIKE '$col'");
    if(!$DB->fetch($check))
        $DB->query("ALTER TABLE web_dh ADD COLUMN $col ".$defs[$col]);
}
$DB->query("ALTER TABLE web_dh MODIFY icon varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''");

// 确保分类表存在
$table_chk = $DB->get_row("SHOW TABLES LIKE 'web_category'");
if(empty($table_chk)){
    $DB->query("CREATE TABLE web_category (
        id int(11) NOT NULL AUTO_INCREMENT,
        name varchar(50) NOT NULL,
        icon varchar(50) NOT NULL DEFAULT '',
        sort int(11) NOT NULL DEFAULT 100,
        active int(11) NOT NULL DEFAULT 1,
        addtime int(11) NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
    $DB->query("INSERT INTO web_category (name,icon,sort,active,addtime) VALUES ('常用推荐','⭐',100,1,".time().")");
}
$DB->query("ALTER TABLE web_category MODIFY icon varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''");

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

$my = isset($_GET['my']) ? $_GET['my'] : null;
$msg = '';

$desc_speed_options = [
    'slow' => '慢速',
    'normal' => '正常',
    'fast' => '快速',
    'rapid' => '极速'
];
$desc_color_options = [
    'default' => '默认灰白',
    'red' => '红色',
    'orange' => '橙色',
    'yellow' => '黄色',
    'green' => '绿色',
    'cyan' => '青色',
    'blue' => '蓝色',
    'purple' => '紫色',
    'rainbow' => '彩色混合'
];

function safe_desc_speed($value) {
    $allowed = ['slow', 'normal', 'fast', 'rapid'];
    return in_array($value, $allowed) ? $value : 'normal';
}

function safe_desc_color($value) {
    $allowed = ['default', 'red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'purple', 'rainbow'];
    return in_array($value, $allowed) ? $value : 'default';
}

// --- 单个操作 ---
if($_SERVER['REQUEST_METHOD']=='POST') qifu_require_csrf();

if($my == 'add_submit'){
    $name = mb_substr(trim((string)$_POST['name']), 0, 100);
    $url = mb_substr(trim((string)$_POST['url']), 0, 255);
    $category = mb_substr(trim((string)$_POST['category']), 0, 50);
    $desc = mb_substr(trim((string)$_POST['description']), 0, 255);
    $desc_marquee = isset($_POST['desc_marquee']) ? intval($_POST['desc_marquee']) : 0;
    $desc_speed = safe_desc_speed(isset($_POST['desc_speed']) ? $_POST['desc_speed'] : 'normal');
    $desc_color = safe_desc_color(isset($_POST['desc_color']) ? $_POST['desc_color'] : 'default');
    $icon = mb_substr(trim((string)$_POST['icon']), 0, 50);
    $sort = intval($_POST['sort']);
    $active = intval($_POST['active']) === 1 ? 1 : 0;
    if($name === '' || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)){
        $msg = '<div class="alert alert-danger">站点名称或 URL 格式不正确。</div>';
    } else {
        $DB->prepared_query('INSERT INTO web_dh (name,url,category,description,desc_marquee,desc_speed,desc_color,icon,sort,active) VALUES (?,?,?,?,?,?,?,?,?,?)', array($name,$url,$category,$desc,$desc_marquee,$desc_speed,$desc_color,$icon,$sort,$active));
        writeLog('添加', '站点', 0, "名称:$name");
        $CACHE->clear();
        $msg = '<div class="alert alert-success">站点添加成功！</div>';
    }
    $my = null;
}
if($my == 'edit_submit'){
    $id = intval($_GET['id']);
    $name = mb_substr(trim((string)$_POST['name']), 0, 100);
    $url = mb_substr(trim((string)$_POST['url']), 0, 255);
    $category = mb_substr(trim((string)$_POST['category']), 0, 50);
    $desc = mb_substr(trim((string)$_POST['description']), 0, 255);
    $desc_marquee = isset($_POST['desc_marquee']) ? intval($_POST['desc_marquee']) : 0;
    $desc_speed = safe_desc_speed(isset($_POST['desc_speed']) ? $_POST['desc_speed'] : 'normal');
    $desc_color = safe_desc_color(isset($_POST['desc_color']) ? $_POST['desc_color'] : 'default');
    $icon = mb_substr(trim((string)$_POST['icon']), 0, 50);
    $sort = intval($_POST['sort']);
    $active = intval($_POST['active']) === 1 ? 1 : 0;
    if($name === '' || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)){
        $msg = '<div class="alert alert-danger">站点名称或 URL 格式不正确。</div>';
    } else {
        $DB->prepared_query('UPDATE web_dh SET name=?,url=?,category=?,description=?,desc_marquee=?,desc_speed=?,desc_color=?,icon=?,sort=?,active=? WHERE id=?', array($name,$url,$category,$desc,$desc_marquee,$desc_speed,$desc_color,$icon,$sort,$active,$id));
        writeLog('修改', '站点', $id, "名称:$name");
        $CACHE->clear();
        $msg = '<div class="alert alert-success">站点修改成功！</div>';
    }
    $my = null;
}
if($my == 'delete'){
    qifu_require_csrf();
    $id = intval($_GET['id']);
    $row = $DB->prepared_row('SELECT name FROM web_dh WHERE id=?', array($id));
    $DB->prepared_query('DELETE FROM web_dh WHERE id=?', array($id));
    writeLog('删除', '站点', $id, !empty($row) ? "名称:".$row['name'] : '');
    $CACHE->clear();
    $msg = '<div class="alert alert-success">站点删除成功！</div>';
    $my = null;
}

// --- 批量操作 ---
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['batch_action'])){
    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
    if(!empty($ids)){
        $ids_str = implode(',', $ids);
        $ba = $_POST['batch_action'];
        if($ba == 'delete'){
            $DB->query("DELETE FROM web_dh WHERE id IN($ids_str)");
            writeLog('批量删除', '站点', 0, "共".count($ids)."个");
            $msg = '<div class="alert alert-success">批量删除 '.count($ids).' 个站点完成！</div>';
        }elseif($ba == 'show'){
            $DB->query("UPDATE web_dh SET active=1 WHERE id IN($ids_str)");
            writeLog('批量显示', '站点', 0, "共".count($ids)."个");
            $msg = '<div class="alert alert-success">批量显示 '.count($ids).' 个站点完成！</div>';
        }elseif($ba == 'hide'){
            $DB->query("UPDATE web_dh SET active=0 WHERE id IN($ids_str)");
            writeLog('批量隐藏', '站点', 0, "共".count($ids)."个");
            $msg = '<div class="alert alert-success">批量隐藏 '.count($ids).' 个站点完成！</div>';
        }elseif($ba == 'move'){
            $newcat = mb_substr(trim((string)$_POST['move_category']), 0, 50);
            $DB->prepared_query("UPDATE web_dh SET category=? WHERE id IN($ids_str)", array($newcat));
            writeLog('批量移动', '站点', 0, "移动到分类:".$newcat);
            $msg = '<div class="alert alert-success">批量移动 '.count($ids).' 个站点到分类完成！</div>';
        }
        $CACHE->clear();
    }
    $my = null;
}

// --- 搜索和筛选条件 ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_cat = isset($_GET['cat']) ? trim($_GET['cat']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// 构建查询
$where_parts = array('1=1');
$params = array();
if($search !== ''){
    $where_parts[] = '(name LIKE ? OR url LIKE ? OR description LIKE ?)';
    $like = '%'.mb_substr($search, 0, 100).'%';
    array_push($params, $like, $like, $like);
}
if($filter_cat !== ''){
    $where_parts[] = 'category=?';
    $params[] = mb_substr($filter_cat, 0, 50);
}
if($filter_status === '1' || $filter_status === '0'){
    $where_parts[] = 'active=?';
    $params[] = intval($filter_status);
}
$where = implode(' AND ', $where_parts);

// --- 批量导入 ---
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['batch_import'])){
    $data = trim($_POST['import_data']);
    $default_cat = mb_substr(trim((string)$_POST['import_default_cat']), 0, 50);
    $import_active = intval($_POST['import_active']) === 1 ? 1 : 0;
    $lines = explode("\n", $data);
    $success = 0;
    foreach($lines as $line){
        $line = trim($line);
        if(empty($line)) continue;
        // 支持 名称,URL,分类 或 名称,URL 或 只有URL
        $parts = array_map('trim', explode(',', $line));
        $cnt = count($parts);
        if($cnt == 1){
            // 只有URL，用域名作为名称
            $name = parse_url($parts[0], PHP_URL_HOST) ?: $parts[0];
            $url = $parts[0];
            $cat = $default_cat;
        } elseif($cnt == 2){
            $name = $parts[0];
            $url = $parts[1];
            $cat = $default_cat;
        } else {
            $name = $parts[0];
            $url = $parts[1];
            $cat = mb_substr($parts[2], 0, 50) ?: $default_cat;
        }
        if(empty($name) || empty($url)) continue;
        // 简单URL格式校验
        if(!preg_match('#^https?://#i', $url)) $url = 'https://'.$url;
        if(!filter_var($url, FILTER_VALIDATE_URL)) continue;
        $name = mb_substr($name, 0, 100);
        $url = mb_substr($url, 0, 255);
        $DB->prepared_query("INSERT INTO web_dh (name,url,category,desc_marquee,desc_speed,desc_color,active,sort) VALUES (?,?,?,0,'normal','default',?,100)", array($name,$url,$cat,$import_active));
        $success++;
    }
    if($success > 0){
        writeLog('批量导入', '站点', 0, "共导入{$success}个站点");
        $CACHE->clear();
        $msg = '<div class="alert alert-success">批量导入成功！共导入 '.$success.' 个站点。</div>';
    } else {
        $msg = '<div class="alert alert-warning">没有找到有效数据，导入失败。</div>';
    }
}

// 获取分类列表
$cats = $DB->get_results("SELECT name FROM web_category WHERE active=1 ORDER BY sort ASC");

// 获取列表
$lists = $DB->prepared_results("SELECT * FROM web_dh WHERE $where ORDER BY sort ASC,id DESC", $params);
?>
<style>
.table{font-size:14px}.form-group{margin-bottom:15px}
.search-tool{margin-bottom:15px}
.batch-panel{margin-bottom:15px;padding:10px;background:#f5f5f5;border-radius:5px;display:none}
.batch-panel.active{display:block}
.site-head-actions{float:right;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.site-head-tip{display:inline-flex;align-items:center;line-height:1;font-size:12px;font-weight:800;color:#fff;background:linear-gradient(135deg,#f97316,#e11d48);border:0;border-radius:999px;padding:7px 11px;box-shadow:0 8px 18px rgba(225,29,72,.22);white-space:nowrap}
.site-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:15px}
.site-toolbar .help-inline{color:#777;font-size:13px}
.desc-style-box{border:1px solid #e5e5e5;background:#fafafa;border-radius:6px;padding:12px 12px 2px;margin-top:-5px}
.desc-color-preview{display:inline-block;width:14px;height:14px;border-radius:50%;vertical-align:-2px;margin-right:6px;border:1px solid rgba(0,0,0,.12)}
.desc-rainbow-preview{background:linear-gradient(90deg,#ff5f6d,#ffc371,#5df1b0,#58d8ff,#8ab4ff,#c084fc)}
</style>
<div class="container" style="padding-top:70px;">
<div class="qf-detail-content qf-sites-content center-block">
<section class="art-page-header qf-detail-header">
  <div class="art-page-header-main">
    <span class="art-page-header-icon glyphicon glyphicon-globe" aria-hidden="true"></span>
    <div class="art-page-header-copy">
      <h2><?php echo $my === 'add' ? '新增站点' : ($my === 'edit' ? '编辑站点' : '站点管理'); ?></h2>
      <p><?php echo $my === 'add' ? '添加新的导航站点，并设置分类、描述、图标和前台显示状态。' : ($my === 'edit' ? '修改站点资料与展示规则，保存后前台配置同步生效。' : '集中维护导航站点、显示状态、分类归属、批量导入与批量操作。'); ?></p>
    </div>
  </div>
  <div class="art-page-header-actions">
    <?php if($my === 'add' || $my === 'edit'): ?>
      <a href="./set_dh.php" class="btn btn-default"><span class="glyphicon glyphicon-arrow-left"></span>返回列表</a>
    <?php else: ?>
      <a href="./set_category.php" class="btn btn-default"><span class="glyphicon glyphicon-folder-open"></span>分类管理</a>
      <a href="./set_dh.php?my=add" class="btn btn-primary"><span class="glyphicon glyphicon-plus"></span>新增站点</a>
    <?php endif; ?>
  </div>
</section>
<?php echo $msg; ?>

<?php if($my == 'add'): ?>
<!-- 新增站点表单 -->
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">新增站点</h3></div>
<div class="panel-body">
<form action="./set_dh.php?my=add_submit" method="POST" class="art-detail-form art-site-editor">
<div class="form-group"><label>名称</label><input type="text" class="form-control" name="name" required></div>
<div class="form-group"><label>URL</label><input type="text" class="form-control" name="url" required></div>
<div class="form-group"><label>分类</label>
<select class="form-control" name="category">
<?php if($cats) foreach($cats as $c) echo '<option value="'.htmlspecialchars($c['name']).'">'.htmlspecialchars($c['name']).'</option>'; ?>
</select>
</div>
<div class="form-group"><label>描述</label><input type="text" class="form-control" name="description"></div>
<div class="form-group">
<label>描述跑马灯设置</label>
<div class="desc-style-box">
<div class="row">
<div class="col-sm-4">
<label class="checkbox-inline"><input type="checkbox" name="desc_marquee" value="1"> 开启跑马灯</label>
<p class="help-block" style="margin:6px 0 10px;">描述较长时建议开启，前台悬停会暂停滚动。</p>
</div>
<div class="col-sm-4">
<select class="form-control" name="desc_speed">
<?php foreach($desc_speed_options as $key=>$label) echo '<option value="'.htmlspecialchars($key).'" '.($key=='normal'?'selected':'').'>跑动速度：'.htmlspecialchars($label).'</option>'; ?>
</select>
</div>
<div class="col-sm-4">
<select class="form-control" name="desc_color">
<?php foreach($desc_color_options as $key=>$label) echo '<option value="'.htmlspecialchars($key).'">'.htmlspecialchars($label).'</option>'; ?>
</select>
</div>
</div>
</div>
</div>
<div class="form-group"><label>图标</label><input type="text" class="form-control" name="icon" placeholder="留空自动获取"></div>
<div class="form-group"><label>排序</label><input type="number" class="form-control" name="sort" value="100"></div>
<div class="form-group"><label>显示</label><select class="form-control" name="active"><option value="1">是</option><option value="0">否</option></select></div>
<div class="form-group">
<button type="submit" class="btn btn-primary">确定添加</button>
<a href="./set_dh.php" class="btn btn-default">返回列表</a>
</div>
</form>
</div></div>

<?php elseif($my == 'edit'): ?>
<!-- 修改站点表单 -->
<?php
$id = intval($_GET['id']);
$row = $DB->get_row("SELECT * FROM web_dh WHERE id='$id'");
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">修改站点</h3></div>
<div class="panel-body">
<form action="./set_dh.php?my=edit_submit&id=<?php echo $id; ?>" method="POST" class="art-detail-form art-site-editor">
<div class="form-group"><label>名称</label><input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($row['name']); ?>" required></div>
<div class="form-group"><label>URL</label><input type="text" class="form-control" name="url" value="<?php echo htmlspecialchars($row['url']); ?>" required></div>
<div class="form-group"><label>分类</label>
<select class="form-control" name="category">
<?php if($cats) foreach($cats as $c){
    $sel = ($row['category']==$c['name'])?'selected':'';
    echo '<option value="'.htmlspecialchars($c['name']).'" '.$sel.'>'.htmlspecialchars($c['name']).'</option>';
} ?>
</select>
</div>
<div class="form-group"><label>描述</label><input type="text" class="form-control" name="description" value="<?php echo htmlspecialchars($row['description']); ?>"></div>
<div class="form-group">
<label>描述跑马灯设置</label>
<div class="desc-style-box">
<div class="row">
<div class="col-sm-4">
<label class="checkbox-inline"><input type="checkbox" name="desc_marquee" value="1" <?php if(!empty($row['desc_marquee'])) echo 'checked'; ?>> 开启跑马灯</label>
<p class="help-block" style="margin:6px 0 10px;">描述较长时建议开启，前台悬停会暂停滚动。</p>
</div>
<div class="col-sm-4">
<select class="form-control" name="desc_speed">
<?php
$cur_speed = !empty($row['desc_speed']) ? $row['desc_speed'] : 'normal';
foreach($desc_speed_options as $key=>$label){
    $sel = ($cur_speed==$key) ? 'selected' : '';
    echo '<option value="'.htmlspecialchars($key).'" '.$sel.'>跑动速度：'.htmlspecialchars($label).'</option>';
}
?>
</select>
</div>
<div class="col-sm-4">
<select class="form-control" name="desc_color">
<?php
$cur_color = !empty($row['desc_color']) ? $row['desc_color'] : 'default';
foreach($desc_color_options as $key=>$label){
    $sel = ($cur_color==$key) ? 'selected' : '';
    echo '<option value="'.htmlspecialchars($key).'" '.$sel.'>'.htmlspecialchars($label).'</option>';
}
?>
</select>
</div>
</div>
</div>
</div>
<div class="form-group"><label>图标</label><input type="text" class="form-control" name="icon" value="<?php echo htmlspecialchars($row['icon']); ?>"></div>
<div class="form-group"><label>排序</label><input type="number" class="form-control" name="sort" value="<?php echo $row['sort']; ?>"></div>
<div class="form-group"><label>显示</label><select class="form-control" name="active"><option value="1" <?php if($row['active']==1)echo 'selected'; ?>>是</option><option value="0" <?php if($row['active']==0)echo 'selected'; ?>>否</option></select></div>
<div class="form-group">
<button type="submit" class="btn btn-primary">保存修改</button>
<a href="./set_dh.php" class="btn btn-default">返回列表</a>
</div>
</form>
</div></div>

<?php else: ?>
<!-- 站点列表页 -->
<div class="panel panel-primary">
<div class="panel-heading">
<h3 class="panel-title">
站点列表
<span class="site-head-actions">
<span class="site-head-tip">需先创建分类</span>
<a href="./set_category.php" class="btn btn-xs btn-default">分类管理</a>
<button type="button" class="btn btn-xs btn-success" onclick="$('#importPanel').slideToggle()"><span class="glyphicon glyphicon-import" aria-hidden="true"></span>批量导入站点</button>
</span>
</h3>
</div>
<div class="panel-body">

<!-- 站点操作入口 -->
<div class="site-toolbar">
<a href="./set_dh.php?my=add" class="btn btn-warning btn-sm">添加站点</a>
<span class="help-inline">添加站点前建议先到分类管理创建分类，便于前台分组展示。</span>
</div>
<div id="importPanel" style="display:none;background:#f5f5f5;border-radius:6px;padding:15px;margin-bottom:15px;">
<div class="alert alert-info" style="margin:0 0 10px 0;padding:8px 12px;font-size:13px;">
<strong><span class="glyphicon glyphicon-list-alt" aria-hidden="true"></span> 批量导入说明：</strong>每行一条，格式：<code>名称,URL,分类</code>，留空则使用默认分类（常用推荐）
</div>
<form method="post">
<input type="hidden" name="batch_import" value="1">
<div class="form-group" style="margin-bottom:8px;">
<label>站点列表（每行一个）</label>
<textarea name="import_data" class="form-control" rows="8" placeholder="示例：&#10;GitHub,https://github.com,开发工具&#10;掘金,https://juejin.cn,学习资源&#10;百度,https://baidu.com"></textarea>
</div>
<div class="row">
<div class="col-sm-4">
<div class="form-group" style="margin-bottom:8px;">
<label>默认分类（行内未指定的站点）</label>
<select name="import_default_cat" class="form-control">
<?php if($cats) foreach($cats as $c) echo '<option value="'.htmlspecialchars($c['name']).'">'.htmlspecialchars($c['name']).'</option>'; ?>
</select>
</div>
</div>
<div class="col-sm-4">
<div class="form-group" style="margin-bottom:8px;">
<label>导入后显示状态</label>
<select name="import_active" class="form-control">
<option value="1">立即显示</option>
<option value="0">暂时隐藏</option>
</select>
</div>
</div>
</div>
<button type="submit" class="btn btn-success btn-sm" onclick="return confirm('确认批量导入？')"><span class="glyphicon glyphicon-import" aria-hidden="true"></span>开始导入</button>
</form>
</div>

<!-- 搜索筛选工具栏 -->
<form method="get" class="search-tool">
<div class="row">
<div class="col-sm-4">
<div class="input-group">
<span class="input-group-addon">搜索</span>
<input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="名称/URL/描述">
</div>
</div>
<div class="col-sm-3">
<select name="cat" class="form-control">
<option value="">全部分类</option>
<?php if($cats) foreach($cats as $c){
    $sel = ($filter_cat==$c['name'])?'selected':'';
    echo '<option value="'.htmlspecialchars($c['name']).'" '.$sel.'>'.htmlspecialchars($c['name']).'</option>';
} ?>
</select>
</div>
<div class="col-sm-2">
<select name="status" class="form-control">
<option value="">全部状态</option>
<option value="1" <?php echo $filter_status==='1'?'selected':''; ?>>显示</option>
<option value="0" <?php echo $filter_status==='0'?'selected':''; ?>>隐藏</option>
</select>
</div>
<div class="col-sm-3">
<button type="submit" class="btn btn-primary">筛选</button>
<a href="./set_dh.php" class="btn btn-default">重置</a>
<span class="help-block" style="margin:0;">共 <?php echo count($lists); ?> 条</span>
</div>
</div>
</form>

<!-- 批量操作工具栏 -->
<form method="post" id="batchForm">
<div class="batch-panel" id="batchPanel">
<div class="row">
<div class="col-sm-4" style="padding-top:5px;">
<span id="selCount">已选 0 个站点</span>
&nbsp;&nbsp;<label><input type="checkbox" id="selAll"> 全选</label>
</div>
<div class="col-sm-4">
<select name="batch_action" class="form-control" id="batchAction" onchange="toggleMoveCat(this.value)">
<option value="">请选择批量操作</option>
<option value="show">批量显示</option>
<option value="hide">批量隐藏</option>
<option value="move">移动到分类</option>
<option value="delete">批量删除</option>
</select>
</div>
<div class="col-sm-3" id="moveCatDiv" style="display:none;">
<select name="move_category" class="form-control">
<?php if($cats) foreach($cats as $c) echo '<option value="'.htmlspecialchars($c['name']).'">'.htmlspecialchars($c['name']).'</option>'; ?>
</select>
</div>
<div class="col-sm-1">
<button type="submit" class="btn btn-danger" onclick="return confirm('确认批量操作？')">执行</button>
</div>
</div>
<input type="hidden" name="ids" id="idsField">
</div>
</form>

<!-- 站点表格 -->
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th style="width:35px;"><input type="checkbox" id="selAll2"></th><th>#</th><th>名称</th><th>URL</th><th>分类</th><th>排序</th><th>状态</th><th>操作</th></tr></thead>
<tbody>
<?php if(empty($lists)): ?>
<tr><td colspan="8" class="text-center">暂无站点 <a href="./set_dh.php?my=add">添加</a></td></tr>
<?php else: foreach($lists as $row):
    $status = $row['active']==1 ? '<span class="label label-success">显示</span>' : '<span class="label label-default">隐藏</span>';
    $disp_url = mb_strlen($row['url'])>30 ? mb_substr($row['url'],0,30,'utf-8').'...' : $row['url'];
?>
<tr>
<td><input type="checkbox" class="rowsel" value="<?php echo $row['id']; ?>"></td>
<td><?php echo $row['id']; ?></td>
<td>
<strong><?php echo htmlspecialchars($row['name']); ?></strong>
<?php if(!empty($row['description'])) echo '<br><small class="text-muted">'.htmlspecialchars($row['description']).'</small>'; ?>
<?php
if(!empty($row['desc_marquee'])){
    $speed_label = isset($desc_speed_options[$row['desc_speed']]) ? $desc_speed_options[$row['desc_speed']] : '正常';
    $color_label = isset($desc_color_options[$row['desc_color']]) ? $desc_color_options[$row['desc_color']] : '默认灰白';
    echo '<br><span class="label label-primary">跑马灯</span> <small class="text-muted">'.htmlspecialchars($speed_label).' / '.htmlspecialchars($color_label).'</small>';
} elseif(!empty($row['desc_color']) && $row['desc_color']!='default') {
    $color_label = isset($desc_color_options[$row['desc_color']]) ? $desc_color_options[$row['desc_color']] : '默认灰白';
    echo '<br><span class="label label-info">描述颜色</span> <small class="text-muted">'.htmlspecialchars($color_label).'</small>';
}
?>
</td>
<td><a href="<?php echo htmlspecialchars($row['url']); ?>" target="_blank" title="<?php echo htmlspecialchars($row['url']); ?>"><?php echo htmlspecialchars($disp_url); ?></a></td>
<td><span class="label label-info"><?php echo htmlspecialchars($row['category']); ?></span></td>
<td><?php echo $row['sort']; ?></td>
<td><?php echo $status; ?></td>
<td>
<a href="./set_dh.php?my=edit&id=<?php echo $row['id']; ?>" class="btn btn-xs btn-warning">编辑</a>
<a href="<?php echo htmlspecialchars(qifu_csrf_url('./set_dh.php?my=delete&id='.intval($row['id'])), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-xs btn-danger" onclick="return confirm('确认删除？')">删除</a>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>
</div>
<?php endif; ?>

</div>
</div>

<script>
// 复选框逻辑
$('#selAll,#selAll2').change(function(){
    $('.rowsel').prop('checked', $(this).prop('checked'));
    updateSel();
});
$(document).on('change','.rowsel',function(){
    updateSel();
});
function updateSel(){
    var c = $('.rowsel:checked').length;
    $('#selCount').text('已选 '+c+' 个站点');
    $('#batchPanel').toggleClass('active', c>0);
    $('#idsField').val($('.rowsel:checked').map(function(){return $(this).val()}).get().join(','));
}
function toggleMoveCat(v){
    $('#moveCatDiv').toggle(v=='move');
}
</script>
