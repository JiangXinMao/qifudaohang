<?php
include __DIR__ . "/../includes/common.php";
$title = '广告设置 - 祈福导航系统';
if($islogin != 1){
    @header('Location: ./login.php');
    exit;
}

$tips = array();
$errors = array();

function ad_admin_tip(&$list, $text, $type = 'success'){
    $list[] = array('text' => $text, 'type' => $type);
}

function ad_admin_upload_error($code){
    $errors = array(
        UPLOAD_ERR_INI_SIZE => '文件超过服务器 upload_max_filesize 限制',
        UPLOAD_ERR_FORM_SIZE => '文件超过表单限制',
        UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
        UPLOAD_ERR_NO_FILE => '没有选择文件',
        UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录',
        UPLOAD_ERR_CANT_WRITE => '服务器写入文件失败',
        UPLOAD_ERR_EXTENSION => '上传被 PHP 扩展拦截'
    );
    return isset($errors[$code]) ? $errors[$code] : '未知上传错误：'.$code;
}

function ad_admin_upload($field, $position, &$error, &$info){
    global $rooturl;
    $info = array();
    if(!isset($_FILES[$field]) || $_FILES[$field]['error'] == UPLOAD_ERR_NO_FILE) return '';
    $upload_dir = ROOT.'images/ad/';
    $filename = qifu_ad_upload_image($_FILES[$field], $upload_dir, $field.'_'.$position, $position, $error, $info);
    if($filename === false) return '';
    return qifu_media_upload_url('images/ad/'.$filename, $rooturl);
}

function ad_admin_datetime($value){
    $value = trim((string)$value);
    if($value === '') return '';
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : '';
}

function ad_admin_single_region_limit($position){
    return $position === 'below_search' ? 4 : 1;
}

function ad_admin_side_ad_conflict_count($position){
    global $DB;
    if($position === 'below_search') return 0;
    $position_sql = qifu_ad_escape($position);
    $count = intval($DB->count("SELECT COUNT(*) FROM web_ads WHERE position='{$position_sql}'"));
    return max(0, $count - 1);
}

qifu_ad_ensure_tables();
qifu_ad_ensure_config();
qifu_ad_seed_legacy();

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    qifu_require_csrf();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if($action == 'save_global'){
        saveSetting('ad_enabled', isset($_POST['ad_enabled']) ? '1' : '0');
        saveSetting('ad_show_below', isset($_POST['ad_show_below']) ? '1' : '0');
        saveSetting('ad_show_right', isset($_POST['ad_show_right']) ? '1' : '0');
        saveSetting('ad_show_left', isset($_POST['ad_show_left']) ? '1' : '0');
        saveSetting('ad_new_window', isset($_POST['ad_new_window']) ? '1' : '0');
        foreach(qifu_ad_positions() as $key => $label){
            $mode_key = 'ad_mode_'.$key;
            saveSetting($mode_key, 'fixed');
        }
        $CACHE->clear();
        $conf = $CACHE->update();
        writeLog('修改', '广告设置', 0, '保存广告全局设置');
        ad_admin_tip($tips, '广告设置保存成功，前台会按新规则展示。');
    }

    if($action == 'save_ad'){
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $positions = qifu_ad_positions();
        $position = isset($_POST['position']) && isset($positions[$_POST['position']]) ? $_POST['position'] : 'below_search';
        $slot = $position == 'below_search' ? max(1, min(4, intval($_POST['slot']))) : 1;
        $link_raw = isset($_POST['link']) ? trim((string)$_POST['link']) : '';
        $link = qifu_ad_normalize_url($link_raw);
        if($link_raw !== '' && $link === ''){
            $errors[] = '跳转链接仅支持 http://、https://、站内相对路径、mailto: 或 tel:。';
        }
        $upload_error = '';
        $upload_info = array();
        $uploaded = empty($errors) ? ad_admin_upload('ad_file', $position, $upload_error, $upload_info) : '';
        if($upload_error !== '') $errors[] = $upload_error;
        if($uploaded !== '' && !empty($upload_info['message'])) ad_admin_tip($tips, $upload_info['message']);

        $image = isset($_POST['image']) ? qifu_media_normalize_url(qifu_ad_normalize_url($_POST['image']), $rooturl) : '';
        if($uploaded !== '') $image = $uploaded;

        $data = array(
            'position' => $position,
            'slot' => $slot,
            'title' => isset($_POST['title']) ? trim($_POST['title']) : '',
            'image' => $image,
            'link' => $link,
            'alt' => isset($_POST['alt']) ? trim($_POST['alt']) : '',
            'active' => isset($_POST['active']) ? 1 : 0,
            'start_at' => ad_admin_datetime(isset($_POST['start_at']) ? $_POST['start_at'] : ''),
            'end_at' => ad_admin_datetime(isset($_POST['end_at']) ? $_POST['end_at'] : ''),
            'sort' => isset($_POST['sort']) ? intval($_POST['sort']) : 100,
            'weight' => max(1, min(50, isset($_POST['weight']) ? intval($_POST['weight']) : 1)),
            'updated_at' => time(),
        );

        if($position === 'below_search'){
            $slot_sql = intval($slot);
            $id_sql = intval($id);
            $existing_slot_count = intval($DB->count("SELECT COUNT(*) FROM web_ads WHERE position='below_search' AND slot='{$slot_sql}' AND id<>'{$id_sql}'"));
            if($existing_slot_count > 0){
                $errors[] = '搜索栏下方的每个位置只能放置 1 个广告素材，请选择空闲位置或编辑当前素材。';
            }
        } elseif(ad_admin_single_region_limit($position) === 1) {
            $position_sql = qifu_ad_escape($position);
            $id_sql = intval($id);
            $existing_side_count = intval($DB->count("SELECT COUNT(*) FROM web_ads WHERE position='{$position_sql}' AND id<>'{$id_sql}'"));
            if($existing_side_count > 0){
                $errors[] = 'PC 左侧/右侧悬浮均为单广告位，请直接编辑现有素材，不能继续新增。';
            }
        }

        if(empty($errors)){
            if($id > 0){
                $DB->prepared_query('UPDATE web_ads SET position=?,slot=?,title=?,image=?,link=?,alt=?,active=?,start_at=?,end_at=?,sort=?,weight=?,updated_at=? WHERE id=?', array($data['position'],$data['slot'],$data['title'],$data['image'],$data['link'],$data['alt'],$data['active'],$data['start_at'],$data['end_at'],$data['sort'],$data['weight'],$data['updated_at'],$id));
                writeLog('修改', '广告', $id, '编辑广告内容');
                ad_admin_tip($tips, '素材保存成功。');
            } else {
                $now = time();
                $DB->prepared_query('INSERT INTO web_ads (position,slot,title,image,link,alt,active,start_at,end_at,sort,weight,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', array($data['position'],$data['slot'],$data['title'],$data['image'],$data['link'],$data['alt'],$data['active'],$data['start_at'],$data['end_at'],$data['sort'],$data['weight'],$now,$now));
                writeLog('添加', '广告', 0, '新增广告内容');
                ad_admin_tip($tips, '素材添加成功。');
            }
            $CACHE->clear();
            $conf = $CACHE->update();
        }
    }

    if($action == 'delete_ad'){
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if($id > 0){
            $DB->prepared_query('DELETE FROM web_ads WHERE id=?', array($id));
            $DB->prepared_query('DELETE FROM web_ad_stats WHERE ad_id=?', array($id));
            $CACHE->clear();
            writeLog('删除', '广告', $id, '删除广告及统计');
            ad_admin_tip($tips, '广告已删除。');
        }
    }

    if($action == 'clear_cache'){
        $CACHE->clear();
        $conf = $CACHE->update();
        writeLog('清理', '缓存', 0, '后台一键清缓存');
        ad_admin_tip($tips, '缓存刷新成功，前台会重新读取最新配置。');
    }
}

$side_ad_conflicts = array(
    'pc_left' => ad_admin_side_ad_conflict_count('pc_left'),
    'pc_right' => ad_admin_side_ad_conflict_count('pc_right'),
);
if($side_ad_conflicts['pc_left'] > 0 || $side_ad_conflicts['pc_right'] > 0){
    ad_admin_tip($tips, '检测到历史侧边广告重复记录。系统不会自动删除素材或统计，请在对应广告位中保留一条并手动删除其余记录。', 'error');
}

$ad_enabled = isset($conf['ad_enabled']) ? $conf['ad_enabled'] : '0';
$ad_show_below = isset($conf['ad_show_below']) ? $conf['ad_show_below'] : '1';
$ad_show_right = isset($conf['ad_show_right']) ? $conf['ad_show_right'] : '0';
$ad_show_left = isset($conf['ad_show_left']) ? $conf['ad_show_left'] : '0';
$ad_new_window = isset($conf['ad_new_window']) ? $conf['ad_new_window'] : '1';
$ads = qifu_ad_all();
$slot_labels = qifu_ad_slot_labels();
$check_images = isset($_GET['check_images']) && $_GET['check_images'] == '1';

$stats_today = date('Y-m-d');
$ad_today_views = intval($DB->count("SELECT COALESCE(SUM(views),0) FROM web_ad_stats WHERE stat_date='$stats_today'"));
$ad_today_clicks = intval($DB->count("SELECT COALESCE(SUM(clicks),0) FROM web_ad_stats WHERE stat_date='$stats_today'"));
$ad_total_views = intval($DB->count("SELECT COALESCE(SUM(views),0) FROM web_ad_stats"));
$ad_total_clicks = intval($DB->count("SELECT COALESCE(SUM(clicks),0) FROM web_ad_stats"));
$ad_today_ctr = $ad_today_views > 0 ? round($ad_today_clicks * 100 / $ad_today_views, 1) : 0;
$ad_total_ctr = $ad_total_views > 0 ? round($ad_total_clicks * 100 / $ad_total_views, 1) : 0;

$position_order = array('below_search', 'pc_left', 'pc_right');
$position_meta = array(
    'below_search' => array(
        'short' => '搜索栏下方',
        'description' => '首页搜索框下方的四等分横幅区域，PC 与手机端都会显示。',
        'recommended' => '840 × 240 px',
        'limit' => '建议按 7:2 制作；不同尺寸会居中裁切铺满前台广告位',
        'toggle' => 'ad_show_below',
        'enabled' => $ad_show_below,
    ),
    'pc_left' => array(
        'short' => 'PC 左侧悬浮',
        'description' => '宽屏设备左侧竖版广告，手机端自动隐藏，适合活动与合作推广。',
        'recommended' => '560 × 1240 px',
        'limit' => '建议按 14:31 制作；宽度不超过 1440px 时前台自动隐藏，不同尺寸会居中裁切铺满广告位',
        'toggle' => 'ad_show_left',
        'enabled' => $ad_show_left,
    ),
    'pc_right' => array(
        'short' => 'PC 右侧悬浮',
        'description' => '宽屏设备右侧竖版广告，可与左侧区域独立设置和投放。',
        'recommended' => '560 × 1240 px',
        'limit' => '建议按 14:31 制作；宽度不超过 1440px 时前台自动隐藏，不同尺寸会居中裁切铺满广告位',
        'toggle' => 'ad_show_right',
        'enabled' => $ad_show_right,
    ),
);
$ads_by_position = array();
$position_summary = array();
foreach($position_order as $position_key){
    $ads_by_position[$position_key] = array();
    $position_summary[$position_key] = array('count'=>0, 'active'=>0, 'views'=>0, 'clicks'=>0, 'primary'=>null, 'slots'=>array());
}
foreach($ads as $ad_row){
    $position_key = isset($ads_by_position[$ad_row['position']]) ? $ad_row['position'] : 'below_search';
    $ads_by_position[$position_key][] = $ad_row;
    $position_summary[$position_key]['count']++;
    if($position_key === 'below_search'){
        $position_summary[$position_key]['slots'][max(1, min(4, intval($ad_row['slot'])))] = true;
    }
    $position_summary[$position_key]['views'] += intval($ad_row['views']);
    $position_summary[$position_key]['clicks'] += intval($ad_row['clicks']);
    $ad_row_status = qifu_ad_status_text($ad_row);
    if($ad_row_status[0] === 'on') $position_summary[$position_key]['active']++;
    if($position_summary[$position_key]['primary'] === null && !empty($ad_row['image'])){
        $position_summary[$position_key]['primary'] = $ad_row;
    }
}
$enabled_region_count = 0;
$active_ad_count = 0;
foreach($position_order as $position_key){
    if($position_meta[$position_key]['enabled'] === '1') $enabled_region_count++;
    $active_ad_count += intval($position_summary[$position_key]['active']);
}

include __DIR__.'/head.php';
?>
<?php require __DIR__ . '/ad-ops-view.php'; ?>
