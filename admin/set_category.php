<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

include __DIR__ . "/../includes/common.php";
$title='祈福导航系统 - 分类管理';
if($islogin!=1){
    @header('Location: ./login.php');
    exit;
}
include __DIR__.'/head.php';

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

$msg = '';

// 处理POST
if($_SERVER['REQUEST_METHOD']=='POST'){
    qifu_require_csrf();
    $action = @$_POST['action'];
    if($action == 'add'){
        $name = mb_substr(trim((string)$_POST['name']), 0, 50);
        $icon = mb_substr(trim((string)$_POST['icon']), 0, 50);
        $sort = intval($_POST['sort']);
        $active = isset($_POST['active']) ? 1 : 0;
        $DB->prepared_query('INSERT INTO web_category (name,icon,sort,active,addtime) VALUES (?,?,?,?,?)', array($name,$icon,$sort,$active,time()));
        writeLog('添加', '分类', 0, "名称:$name");
        $CACHE->clear();
        $msg = '<div class="alert alert-success">分类添加成功！</div>';
    }
    if($action == 'edit'){
        $id = intval($_POST['id']);
        $old = $DB->prepared_row('SELECT name FROM web_category WHERE id=?', array($id));
        $old_name = !empty($old) ? $old['name'] : '';
        $name = mb_substr(trim((string)$_POST['name']), 0, 50);
        $icon = mb_substr(trim((string)$_POST['icon']), 0, 50);
        $sort = intval($_POST['sort']);
        $active = isset($_POST['active']) ? 1 : 0;
        $DB->prepared_query('UPDATE web_category SET name=?,icon=?,sort=?,active=? WHERE id=?', array($name,$icon,$sort,$active,$id));
        if($old_name !== '' && $old_name != $_POST['name']){
            $DB->prepared_query('UPDATE web_dh SET category=? WHERE category=?', array($name,$old_name));
        }
        writeLog('修改', '分类', $id, "名称:$name");
        $CACHE->clear();
        $msg = '<div class="alert alert-success">分类修改成功！</div>';
    }
}

// 处理GET删除
if(isset($_GET['del'])){
    qifu_require_csrf();
    $id = intval($_GET['del']);
    $row = $DB->prepared_row('SELECT name FROM web_category WHERE id=?', array($id));
    $DB->prepared_query('DELETE FROM web_category WHERE id=?', array($id));
    writeLog('删除', '分类', $id, !empty($row) ? "名称:".$row['name'] : '');
    $CACHE->clear();
    $msg = '<div class="alert alert-success">分类删除成功！</div>';
}

// 获取分类
$categories = $DB->get_results("SELECT * FROM web_category ORDER BY sort ASC,id ASC");

// 100个图标
$icon_list = [
    '⭐','🌟','✨','💫','⚡','🔥','💥','🌈','🌤️','🌙',
    '⬆️','⬇️','⬅️','➡️','🔄','🔃','🔀','🔁','⏫','⏬',
    '🔧','🔨','🔩','🔪','🔫','🔮','🔯','🔰','🔱','💎',
    '💻','📱','💾','📷','📹','🎥','📺','📻','🔋','🔌',
    '🎵','🎶','🎼','🎸','🎹','🎺','🎻','🎮','🎲','🎯',
    '📝','📋','📌','📍','📎','📏','📐','✏️','🖊️','🖋️',
    '📁','📂','📃','📄','📅','📆','📇','📈','📉','📊',
    '😀','😁','😂','😃','😄','😎','🤩','🥳','😇','🤗',
    '🍎','🍐','🍊','🍋','🍌','🍉','🍇','🍓','🍒','🍑',
    '🐱','🐶','🐰','🐻','🐼','🐨','🐯','🦁','🐸','🐵',
];

// 生成图标下拉选项HTML
function iconOptions($list, $selected=''){
    $html = '<option value="⭐" style="font-size:18px;">⭐</option>';
    foreach($list as $ic){
        if($ic=='⭐') continue;
        $sel = ($ic==$selected) ? ' selected' : '';
        $html .= '<option value="'.htmlspecialchars($ic).'" style="font-size:18px;"'.$sel.'>'.htmlspecialchars($ic).'</option>';
    }
    return $html;
}
?>
<div class="container" style="padding-top:70px;">
<div class="qf-detail-content qf-category-content center-block">
<section class="art-page-header qf-detail-header">
  <div class="art-page-header-main">
    <span class="art-page-header-icon glyphicon glyphicon-folder-open" aria-hidden="true"></span>
    <div class="art-page-header-copy">
      <h2>分类管理</h2>
      <p>维护前台标签栏分类、图标、排序与启用状态，站点数量会自动同步统计。</p>
    </div>
  </div>
  <div class="art-page-header-actions">
    <a href="./set_dh.php" class="btn btn-default"><span class="glyphicon glyphicon-globe"></span>站点管理</a>
  </div>
</section>
<?php echo $msg; ?>

<!-- 添加分类 -->
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">添加分类</h3></div>
<div class="panel-body">
<form action="./set_category.php" method="post" class="art-detail-form art-category-create">
<input type="hidden" name="action" value="add">
<div class="row">
<div class="col-sm-5"><div class="form-group"><label>分类名称</label><input type="text" name="name" class="form-control" required></div></div>
<div class="col-sm-3"><div class="form-group"><label>图标</label><select name="icon" class="form-control" style="font-size:20px;"><?php echo iconOptions($icon_list); ?></select></div></div>
<div class="col-sm-2"><div class="form-group"><label>排序</label><input type="number" name="sort" class="form-control" value="100"></div></div>
<div class="col-sm-2" style="padding-top:25px;"><div class="checkbox"><label><input type="checkbox" name="active" checked> 启用</label></div></div>
</div>
<button type="submit" class="btn btn-primary">确定添加</button>
</form>
</div>
</div>

<!-- 分类列表 -->
<div class="panel panel-success">
<div class="panel-heading"><h3 class="panel-title">分类列表</h3></div>
<div class="table-responsive"><table class="table table-hover">
<tr><th>图标</th><th>名称</th><th>排序</th><th>状态</th><th>站点</th><th>操作</th></tr>
<?php if(empty($categories)): ?>
<tr><td colspan="6" class="text-center">暂无分类</td></tr>
<?php else: foreach($categories as $cat): ?>
<?php $cnt = $DB->prepared_row('SELECT count(*) as c FROM web_dh WHERE category=?', array($cat['name'])); ?>
<tr>
<td style="font-size:22px;"><?php echo htmlspecialchars($cat['icon']); ?></td>
<td><?php echo htmlspecialchars($cat['name']); ?></td>
<td><?php echo $cat['sort']; ?></td>
<td><?php echo $cat['active']==1?'<span class="label label-success">启用</span>':'<span class="label label-default">禁用</span>'; ?></td>
<td><span class="badge"><?php echo is_array($cnt)?$cnt['c']:0; ?></span></td>
<td>
<button class="btn btn-xs btn-warning" onclick='editCat(<?php echo intval($cat['id']); ?>,<?php echo json_encode($cat['name'], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>,<?php echo json_encode($cat['icon'], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>,<?php echo intval($cat['sort']); ?>,<?php echo intval($cat['active']); ?>)'>编辑</button>
<a href="<?php echo htmlspecialchars(qifu_csrf_url('./set_category.php?del='.intval($cat['id'])), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-xs btn-danger" onclick="return confirm('删除？')">删除</a>
</td>
</tr>
<?php endforeach; endif; ?>
</table></div>
</div>

<div class="text-center" style="margin-top:20px;">
<a href="./set_dh.php" class="btn btn-lg btn-success">站点管理</a>
</div>

</div>
</div>

<!-- 编辑弹窗 -->
<div class="modal fade" id="editModal">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal">×</button><h4>编辑分类</h4></div>
<form action="./set_category.php" method="post">
<div class="modal-body">
<input type="hidden" name="action" value="edit">
<input type="hidden" name="id" id="edit_id">
<div class="form-group"><label>名称</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
<div class="form-group"><label>图标</label><select name="icon" id="edit_icon" class="form-control" style="font-size:20px;"><?php echo iconOptions($icon_list); ?></select></div>
<div class="form-group"><label>排序</label><input type="number" name="sort" id="edit_sort" class="form-control"></div>
<div class="checkbox"><label><input type="checkbox" id="edit_active" name="active"> 启用</label></div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
<button type="submit" class="btn btn-primary">保存修改</button>
</div>
</form>
</div></div>
</div>

<script>
function editCat(id,name,icon,sort,active){
    document.getElementById('edit_id').value=id;
    document.getElementById('edit_name').value=name;
    document.getElementById('edit_icon').value=icon;
    document.getElementById('edit_sort').value=sort;
    document.getElementById('edit_active').checked=(active==1);
    $('#editModal').modal('show');
}
</script>
