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

function ad_admin_enforce_single_side_ads($position){
    global $DB;
    if($position === 'below_search') return 0;
    $position_sql = qifu_ad_escape($position);
    $rows = $DB->get_results("SELECT id FROM web_ads WHERE position='{$position_sql}' ORDER BY CASE WHEN image<>'' THEN 0 ELSE 1 END, active DESC, sort ASC, updated_at DESC, id ASC");
    if(empty($rows) || count($rows) <= 1) return 0;
    $keep_id = intval($rows[0]['id']);
    $removed = 0;
    foreach($rows as $row){
        $id = intval($row['id']);
        if($id <= 0 || $id === $keep_id) continue;
        $DB->prepared_query('DELETE FROM web_ads WHERE id=?', array($id));
        $DB->prepared_query('DELETE FROM web_ad_stats WHERE ad_id=?', array($id));
        $removed++;
    }
    return $removed;
}

qifu_ad_ensure_tables();
qifu_ad_ensure_config();
qifu_ad_seed_legacy();
$deduped_side_ads = ad_admin_enforce_single_side_ads('pc_left') + ad_admin_enforce_single_side_ads('pc_right');
if($deduped_side_ads > 0){
    $CACHE->clear();
    $conf = $CACHE->update();
    ad_admin_tip($tips, '已清理重复的侧边悬浮广告素材，PC 左侧与右侧均只保留 1 个。');
}

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
            $mode = isset($_POST[$mode_key]) ? $_POST[$mode_key] : 'fixed';
            if(!isset(qifu_ad_modes()[$mode])) $mode = 'fixed';
            saveSetting($mode_key, $mode);
        }
        $CACHE->clear();
        $conf = $CACHE->update();
        writeLog('修改', '广告设置', 0, '保存广告全局设置');
        ad_admin_tip($tips, '广告全局设置已保存，前台会按新规则展示。');
    }

    if($action == 'save_ad'){
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $positions = qifu_ad_positions();
        $position = isset($_POST['position']) && isset($positions[$_POST['position']]) ? $_POST['position'] : 'below_search';
        $slot = $position == 'below_search' ? max(1, min(4, intval($_POST['slot']))) : 1;
        $upload_error = '';
        $upload_info = array();
        $uploaded = ad_admin_upload('ad_file', $position, $upload_error, $upload_info);
        if($upload_error !== '') $errors[] = $upload_error;
        if($uploaded !== '' && !empty($upload_info['message'])) ad_admin_tip($tips, $upload_info['message']);

        $image = isset($_POST['image']) ? qifu_media_normalize_url(qifu_ad_normalize_url($_POST['image']), $rooturl) : '';
        if($uploaded !== '') $image = $uploaded;

        $data = array(
            'position' => $position,
            'slot' => $slot,
            'title' => isset($_POST['title']) ? trim($_POST['title']) : '',
            'image' => $image,
            'link' => isset($_POST['link']) ? qifu_ad_normalize_url($_POST['link']) : '',
            'alt' => isset($_POST['alt']) ? trim($_POST['alt']) : '',
            'active' => isset($_POST['active']) ? 1 : 0,
            'start_at' => ad_admin_datetime(isset($_POST['start_at']) ? $_POST['start_at'] : ''),
            'end_at' => ad_admin_datetime(isset($_POST['end_at']) ? $_POST['end_at'] : ''),
            'sort' => isset($_POST['sort']) ? intval($_POST['sort']) : 100,
            'weight' => max(1, min(50, isset($_POST['weight']) ? intval($_POST['weight']) : 1)),
            'updated_at' => time(),
        );

        if($position !== 'below_search'){
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
                ad_admin_tip($tips, '广告已更新。');
            } else {
                $now = time();
                $DB->prepared_query('INSERT INTO web_ads (position,slot,title,image,link,alt,active,start_at,end_at,sort,weight,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', array($data['position'],$data['slot'],$data['title'],$data['image'],$data['link'],$data['alt'],$data['active'],$data['start_at'],$data['end_at'],$data['sort'],$data['weight'],$now,$now));
                writeLog('添加', '广告', 0, '新增广告内容');
                ad_admin_tip($tips, '新广告已添加。');
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
        ad_admin_tip($tips, '缓存已清理，前台会重新读取最新配置。');
    }
}

$ad_enabled = isset($conf['ad_enabled']) ? $conf['ad_enabled'] : '0';
$ad_show_below = isset($conf['ad_show_below']) ? $conf['ad_show_below'] : '1';
$ad_show_right = isset($conf['ad_show_right']) ? $conf['ad_show_right'] : '0';
$ad_show_left = isset($conf['ad_show_left']) ? $conf['ad_show_left'] : '0';
$ad_new_window = isset($conf['ad_new_window']) ? $conf['ad_new_window'] : '1';
$ads = qifu_ad_all();
$modes = qifu_ad_modes();
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
        'recommended' => '420 × 120 px',
        'limit' => '上传时最大等比缩小到 1440 × 480 px',
        'toggle' => 'ad_show_below',
        'enabled' => $ad_show_below,
    ),
    'pc_left' => array(
        'short' => 'PC 左侧悬浮',
        'description' => '宽屏设备左侧竖版广告，手机端自动隐藏，适合活动与合作推广。',
        'recommended' => '300 × 400 px',
        'limit' => '上传时最大等比缩小到 600 × 800 px',
        'toggle' => 'ad_show_left',
        'enabled' => $ad_show_left,
    ),
    'pc_right' => array(
        'short' => 'PC 右侧悬浮',
        'description' => '宽屏设备右侧竖版广告，可与左侧区域独立设置和投放。',
        'recommended' => '300 × 400 px',
        'limit' => '上传时最大等比缩小到 600 × 800 px',
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
<style>
.ad-shell{padding-top:70px;padding-bottom:44px;color:#172033}
.ad-page-head{display:flex;align-items:center;gap:18px;min-height:94px;margin-bottom:14px;padding:15px 17px;border:1px solid #d9e3ec;border-radius:6px;background:#fff;box-shadow:0 2px 8px rgba(38,55,77,.05)}
.ad-page-title{display:flex;align-items:center;gap:13px;min-width:0}
.ad-page-title-icon{width:44px;height:44px;border-radius:6px;background:#eaf4fb;color:#1769aa;display:grid;place-items:center;font-size:18px;flex-shrink:0}
.ad-page-head h1{font-size:22px;line-height:1.25;margin:0 0 5px;font-weight:700;color:#172033}
.ad-page-head p{margin:0;color:#667085;font-size:12px;line-height:1.6;max-width:690px}
.ad-page-actions{display:flex;gap:8px;align-items:center;margin-left:auto;flex-wrap:wrap;justify-content:flex-end}
.ad-page-actions form{margin:0}
.ad-btn{height:36px!important;display:inline-flex!important;align-items:center;justify-content:center;gap:6px;padding:0 13px!important;border-radius:6px!important;font-size:13px;font-weight:700;line-height:1!important;box-shadow:none!important;white-space:nowrap;transition:.18s}
.ad-btn:active{transform:translateY(1px)}
.ad-btn-primary{background:#1769aa!important;border-color:#1769aa!important;color:#fff!important}
.ad-btn-primary:hover,.ad-btn-primary:focus{background:#0f568e!important;border-color:#0f568e!important;color:#fff!important}
.ad-btn-muted{background:#fff!important;border-color:#d5dce5!important;color:#344054!important}
.ad-btn-danger{background:#fff!important;border-color:#efc1bc!important;color:#b42318!important}
.ad-toast{border:1px solid transparent;border-radius:6px;padding:11px 14px;margin-bottom:10px;font-size:13px}
.ad-toast.success{background:#edf8f2;border-color:#caead8;color:#18794e}
.ad-toast.error{background:#fff0ee;border-color:#f4c7c2;color:#b42318}
.ad-stat-scroll{overflow-x:auto;margin-bottom:16px;padding-bottom:0}
.ad-stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border:1px solid #bfd8e9;border-radius:6px;background:#fff;overflow:hidden;min-width:720px}
.ad-stat{min-width:0;min-height:96px;padding:16px;border-right:1px solid #e3e7ec;display:grid;grid-template-columns:42px minmax(0,1fr);align-items:center;gap:12px}
.ad-stat:last-child{border-right:0}
.ad-stat-icon{width:42px;height:42px;border-radius:6px;background:#1769aa;color:#fff;display:grid;place-items:center;font-size:17px;flex-shrink:0}
.ad-stat-content{min-width:0}
.ad-stat-label{display:block;margin:0 0 4px;color:#667085;font-size:12px;line-height:20px}
.ad-stat-line{min-width:0;display:flex;align-items:flex-start;flex-direction:column;gap:4px}
.ad-stat-line b{font-size:24px;line-height:28px;font-weight:600;font-variant-numeric:tabular-nums;color:#172033}
.ad-stat-meta,.ad-stat-meta.muted{max-width:100%;overflow:hidden;color:#667085;font-size:12px;line-height:20px;text-overflow:ellipsis;white-space:nowrap}
.ad-global-control{display:flex;align-items:center;gap:14px;padding:13px 15px;border:1px solid #bdd7e8;border-radius:6px;background:#f4faff;margin-bottom:14px}
.ad-global-copy strong{display:block;font-size:15px;margin-bottom:3px;color:#172033}
.ad-global-copy small{font-size:12px;color:#667085}
.ad-global-actions{display:flex;gap:9px;align-items:center;margin-left:auto;flex-wrap:wrap;justify-content:flex-end}
.ad-switch{display:inline-flex;align-items:center;gap:10px;margin:0;cursor:pointer;white-space:nowrap}
.ad-switch input{position:absolute;opacity:0}
.ad-switch-ui{width:64px;height:34px;border-radius:999px;background:#aeb8c4;box-shadow:inset 0 2px 5px rgba(29,43,58,.16);position:relative;flex-shrink:0;transition:.18s}
.ad-switch-ui:after{content:"";position:absolute;left:4px;top:4px;width:26px;height:26px;border-radius:50%;background:#fff;box-shadow:0 2px 5px rgba(22,37,51,.28);transition:.18s}
.ad-switch input:checked + .ad-switch-ui{background:#1769aa}
.ad-switch input:checked + .ad-switch-ui:after{transform:translateX(30px)}
.ad-switch input:focus + .ad-switch-ui{box-shadow:0 0 0 3px rgba(23,105,170,.16),inset 0 2px 5px rgba(29,43,58,.12)}
.ad-switch-copy b{display:block;font-size:13px;color:#344054}
.ad-switch-copy small{display:block;color:#667085;font-size:11px;margin-top:2px}
.ad-inline-switch .ad-switch-ui{width:46px;height:26px}
.ad-inline-switch .ad-switch-ui:after{width:20px;height:20px;left:3px;top:3px}
.ad-inline-switch input:checked + .ad-switch-ui:after{transform:translateX(20px)}
.ad-add-panel{border:1px solid #dfe4eb;border-radius:6px;background:#fff;margin-bottom:14px;overflow:hidden}
.ad-section-head{display:flex;align-items:center;gap:9px;min-height:50px;padding:10px 15px;border-bottom:1px solid #e3e7ec;background:#fbfcfd}
.ad-section-head h2{font-size:15px;margin:0;font-weight:700;color:#172033}
.ad-section-head p{font-size:12px;color:#667085;margin:0}
.ad-section-head .ad-section-right{margin-left:auto}
.ad-editor-layout{display:grid;grid-template-columns:260px minmax(0,1fr);gap:16px;padding:16px}
.ad-editor-preview{height:214px;border:1px dashed #b8c2ce;border-radius:6px;background:#f7f9fb;display:grid;place-items:center;overflow:hidden;color:#667085;text-align:center;padding:12px}
.ad-editor-preview img{width:100%;height:100%;object-fit:contain;display:none}
.ad-editor-preview.has-image img{display:block}
.ad-editor-preview.has-image .ad-editor-placeholder{display:none}
.ad-editor-placeholder .glyphicon{font-size:28px;color:#8ea0b2;margin-bottom:8px}
.ad-editor-placeholder b{display:block;color:#344054;margin-bottom:5px}
.ad-editor-placeholder small{line-height:1.5}
.ad-form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.ad-form-grid .wide{grid-column:span 2}
.ad-form-grid label{font-size:12px;color:#344054;font-weight:700;margin-bottom:6px}
.ad-form-grid .form-control{height:38px;border-radius:6px;border-color:#cfd6df;box-shadow:none;font-size:13px}
.ad-form-grid .form-control:focus{border-color:#1769aa;box-shadow:0 0 0 3px rgba(23,105,170,.12)}
.ad-form-grid input[type=file].form-control{height:auto;min-height:38px;padding:7px 9px}
.ad-slot-field.is-hidden{display:none}
.ad-editor-actions{display:flex;align-items:center;gap:8px;margin-top:13px;padding-top:13px;border-top:1px solid #e7eaee}
.ad-upload-progress{height:7px;background:#edf1f5;border-radius:999px;overflow:hidden;margin-top:8px;display:none}
.ad-upload-progress span{display:block;height:100%;width:100%;background:#1769aa;transform:scaleX(0);transform-origin:left center;transition:transform .15s ease-out}
.ad-upload-msg{font-size:11px;color:#667085;margin-top:6px;min-height:17px}
.ad-media-band{border:1px solid #dfe4eb;border-radius:6px;background:#fff;margin-bottom:14px;overflow:hidden;transition:.18s}
.ad-media-band.is-off{background:#f4f5f7;border-color:#dde1e6}
.ad-media-band.is-off .ad-media-dim{opacity:.62;filter:grayscale(.65)}
.ad-media-band.is-off .ad-band-rules,.ad-media-band.is-off .ad-band-actions,.ad-media-band.is-off .ad-materials{opacity:1;filter:none}
.ad-band-main{display:grid;grid-template-columns:minmax(300px,.78fr) minmax(0,1.22fr);min-height:240px}
.ad-key-media{background:#e8edf2;border-right:1px solid #dfe4eb;display:flex;flex-direction:column;min-width:0}
.ad-key-image{height:205px;padding:12px;display:grid;place-items:center;overflow:hidden}
.ad-key-image img{width:100%;height:100%;object-fit:contain;border-radius:4px;background:#f6f8fa}
.ad-key-empty{height:100%;width:100%;display:grid;place-items:center;text-align:center;color:#667085;border:1px dashed #b8c2ce;border-radius:5px;background:#f8fafb;padding:18px}
.ad-key-empty .glyphicon{display:block;font-size:30px;color:#8ea0b2;margin-bottom:8px}
.ad-key-empty b{display:block;color:#344054;margin-bottom:5px}
.ad-key-empty small{display:block;max-width:260px;line-height:1.55}
.ad-key-caption{min-height:35px;padding:8px 12px;border-top:1px solid #d9dfe6;background:#f7f9fb;font-size:11px;color:#667085;display:flex;align-items:center;justify-content:space-between;gap:10px}
.ad-key-caption b{color:#344054;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ad-band-content{padding:16px;display:flex;flex-direction:column;min-width:0}
.ad-band-top{display:flex;align-items:flex-start;gap:14px}
.ad-band-title h2{font-size:19px;line-height:1.25;margin:0 0 5px;color:#172033}
.ad-band-title p{font-size:12px;line-height:1.6;color:#667085;margin:0;max-width:680px}
.ad-band-switch{margin-left:auto}
.ad-band-switch .ad-switch-ui{width:70px;height:38px}
.ad-band-switch .ad-switch-ui:after{width:30px;height:30px}
.ad-band-switch input:checked + .ad-switch-ui:after{transform:translateX(32px)}
.ad-region-facts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;margin:17px 0 14px;border-top:1px solid #e3e7ec;border-bottom:1px solid #e3e7ec}
.ad-region-fact{padding:10px 11px;border-right:1px solid #e3e7ec}
.ad-region-fact:last-child{border-right:0}
.ad-region-fact span{display:block;color:#667085;font-size:10px;margin-bottom:4px}
.ad-region-fact b{display:block;font-size:13px;color:#344054;font-variant-numeric:tabular-nums;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ad-band-rules{display:grid;grid-template-columns:minmax(190px,240px) 1fr;gap:12px;align-items:end}
.ad-band-rules label{display:block;font-size:12px;color:#344054;font-weight:700;margin-bottom:6px}
.ad-band-rules select{height:38px;border-radius:6px;border-color:#cfd6df;box-shadow:none}
.ad-band-size{font-size:11px;line-height:1.55;color:#667085;padding-bottom:2px}
.ad-band-size b{display:block;color:#344054;font-size:12px}
.ad-band-actions{display:flex;gap:8px;align-items:center;margin-top:auto;padding-top:14px;flex-wrap:wrap}
.ad-materials{border-top:1px solid #dfe4eb}
.ad-materials-head{display:flex;align-items:center;gap:8px;padding:10px 14px;background:#fbfcfd;border-bottom:1px solid #e3e7ec}
.ad-materials-head h3{font-size:13px;margin:0;font-weight:700}.ad-materials-head span{font-size:11px;color:#667085}
.ad-materials-head .right{margin-left:auto}
.ad-material-row{display:grid;grid-template-columns:132px minmax(0,1fr) 190px 190px;gap:13px;align-items:center;padding:12px 14px;border-bottom:1px solid #e7eaee;background:#fff}
.ad-material-row:last-of-type{border-bottom:0}
.ad-material-thumb{width:132px;height:74px;border:1px solid #d9dfe6;border-radius:5px;background:#f1f4f7;display:grid;place-items:center;overflow:hidden;color:#8a96a3;font-size:11px}
.ad-material-thumb img{width:100%;height:100%;object-fit:contain}
.ad-material-info{min-width:0}.ad-material-info h4{font-size:13px;margin:0 0 5px;color:#172033;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ad-material-info p{font-size:11px;color:#667085;margin:0;line-height:1.55;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ad-material-meta{display:grid;grid-template-columns:1fr 1fr;gap:6px 10px}
.ad-material-meta span{font-size:10px;color:#667085}.ad-material-meta b{display:block;font-size:11px;color:#344054;margin-top:2px;font-variant-numeric:tabular-nums}
.ad-material-actions{display:flex;gap:6px;justify-content:flex-end;align-items:center;flex-wrap:wrap}
.ad-material-actions form{margin:0}
.ad-status{display:inline-block;border-radius:999px;padding:4px 8px;font-size:11px;font-weight:700}
.ad-status.on{background:#e9f7f0;color:#18794e}.ad-status.wait{background:#fff4df;color:#9a5b13}.ad-status.end,.ad-status.off{background:#e9edf1;color:#667085}.ad-status.bad{background:#fff0ee;color:#b42318}
.ad-img-ok{color:#18794e;font-weight:700}.ad-img-bad{color:#b42318;font-weight:700}
.ad-material-edit{background:#f7f9fb;border-bottom:1px solid #dfe4eb;padding:15px}
.ad-empty-materials{padding:26px 16px;text-align:center;color:#667085;background:#fff}
.ad-empty-materials .glyphicon{display:block;font-size:28px;color:#9aa8b5;margin-bottom:8px}.ad-empty-materials b{display:block;color:#344054;margin-bottom:5px}
@media(max-width:1100px){.ad-band-main{grid-template-columns:280px minmax(0,1fr)}.ad-material-row{grid-template-columns:112px minmax(0,1fr) 170px}.ad-material-thumb{width:112px}.ad-material-actions{grid-column:1/-1;justify-content:flex-start}.ad-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:780px){.ad-shell{padding-top:60px}.ad-page-head{display:block;padding:14px}.ad-page-actions{margin:12px 0 0;justify-content:flex-start}.ad-stat-scroll{margin-right:-2px}.ad-stat-grid{grid-template-columns:1.15fr repeat(3,minmax(0,1fr))!important}.ad-global-control{align-items:flex-start;flex-wrap:wrap}.ad-global-actions{width:100%;margin-left:0;justify-content:flex-start}.ad-editor-layout,.ad-band-main{grid-template-columns:1fr}.ad-key-media{border-right:0;border-bottom:1px solid #dfe4eb}.ad-key-image{height:190px}.ad-band-top{display:block}.ad-band-switch{margin:13px 0 0}.ad-region-facts{grid-template-columns:repeat(2,1fr)}.ad-region-fact:nth-child(2){border-right:0}.ad-region-fact:nth-child(-n+2){border-bottom:1px solid #e3e7ec}.ad-band-rules{grid-template-columns:1fr}.ad-material-row{grid-template-columns:100px minmax(0,1fr)}.ad-material-thumb{width:100px;height:66px}.ad-material-meta,.ad-material-actions{grid-column:1/-1}.ad-material-actions{justify-content:flex-start}.ad-form-grid{grid-template-columns:1fr}.ad-form-grid .wide{grid-column:span 1}}
@media(max-width:480px){.ad-editor-preview{height:170px}.ad-region-facts{grid-template-columns:1fr}.ad-region-fact{border-right:0;border-bottom:1px solid #e3e7ec!important}.ad-region-fact:last-child{border-bottom:0!important}.ad-material-row{grid-template-columns:1fr}.ad-material-thumb{width:100%;height:130px}.ad-material-meta{grid-template-columns:1fr 1fr}}

/* A 区域总览：首屏只承担比较和开关，素材表单按区域展开。 */
.ad-overview-master{display:grid;grid-template-columns:minmax(300px,1fr) auto auto;align-items:center;gap:20px;margin-bottom:14px;padding:16px 18px;border:1px solid #e4e7ed;border-radius:8px;background:#fff}
.ad-overview-master-copy strong{display:block;margin-bottom:4px;color:#30324d;font-size:16px;font-weight:600}
.ad-overview-master-copy small{display:block;max-width:620px;color:#697386;font-size:12px;line-height:1.55}
.ad-overview-master-facts{display:grid;grid-template-columns:repeat(4,minmax(92px,1fr));align-items:stretch;gap:8px}
.ad-overview-master-facts span{min-width:92px;padding:10px 12px;border:1px solid #e4e7ed;border-radius:6px;background:#f8f9fc}
.ad-overview-master-facts span:nth-child(1){border-color:#dbe2ff;background:#f3f6ff}
.ad-overview-master-facts span:nth-child(2){border-color:#cfeee2;background:#effaf6}
.ad-overview-master-facts span:nth-child(3){border-color:#f1dfbd;background:#fff8eb}
.ad-overview-master-facts span:nth-child(4){border-color:#cde8ee;background:#eff9fb}
.ad-overview-master-facts small{display:block;margin-bottom:5px;color:#697386;font-size:10px;font-weight:550;white-space:nowrap}
.ad-overview-master-facts b{display:block;color:#30324d;font-size:15px;line-height:20px;font-weight:650;font-variant-numeric:tabular-nums;white-space:nowrap}
.ad-overview-master-actions{display:grid;grid-template-columns:repeat(2,minmax(190px,1fr));align-items:stretch;gap:8px;min-width:430px}
.ad-overview-action-control{display:flex;align-items:center;min-height:56px;padding:10px 12px;border:1px solid #e4e7ed;border-radius:6px;background:#f8f9fc;white-space:normal;transition:border-color .18s ease,background-color .18s ease}
.ad-overview-action-control:hover{border-color:#c9d5e3;background:#f5f8fc}
.ad-overview-action-control:focus-within{border-color:#1769aa;background:#f3f7fb}
.ad-overview-action-control .ad-switch-copy{min-width:0}
.ad-overview-action-control .ad-switch-copy b{font-size:12px;font-weight:600;line-height:1.35}
.ad-overview-action-control .ad-switch-copy small{font-size:10px;line-height:1.45;white-space:normal}
.ad-overview-master-save{grid-column:1/-1;width:100%;height:38px!important}
.ad-overview-master-switch .ad-switch-ui,.ad-overview-switch .ad-switch-ui,.ad-overview-action-control .ad-switch-ui{width:48px;height:26px;box-shadow:none}
.ad-overview-master-switch .ad-switch-ui:after,.ad-overview-switch .ad-switch-ui:after,.ad-overview-action-control .ad-switch-ui:after{top:3px;left:3px;width:20px;height:20px}
.ad-overview-master-switch input:checked+.ad-switch-ui:after,.ad-overview-switch input:checked+.ad-switch-ui:after,.ad-overview-action-control input:checked+.ad-switch-ui:after{transform:translateX(22px)}
.ad-overview-list{overflow:hidden;border:1px solid #e4e7ed;border-radius:8px;background:#fff}
.ad-overview-region{border-bottom:1px solid #e4e7ed;background:#fff;transition:background-color .2s ease}
.ad-overview-region:last-child{border-bottom:0}
.ad-overview-region.is-off{background:#fafbfc}
.ad-overview-region.is-off .ad-overview-row .ad-media-dim{opacity:.5;filter:grayscale(.6)}
.ad-overview-row{display:grid;grid-template-columns:245px 150px minmax(230px,1fr) 170px 272px;align-items:center;gap:15px;min-height:112px;padding:14px}
.ad-overview-region-title{display:flex;align-items:flex-start;gap:10px;min-width:0}
.ad-overview-region-icon{display:grid;place-items:center;width:36px;height:36px;flex:0 0 36px;border-radius:7px;background:#eef2ff;color:#5d7df7;font-size:10px;font-weight:800}
.ad-overview-region-icon-pc_left{background:#eaf9f3;color:#12a16f}
.ad-overview-region-icon-pc_right{background:#fff7e8;color:#b67818}
.ad-overview-region-title h2{margin:0 0 4px;color:#30324d;font-size:14px;font-weight:600}
.ad-overview-region-title p{display:-webkit-box;overflow:hidden;margin:0;color:#697386;font-size:11px;line-height:1.5;-webkit-box-orient:vertical;-webkit-line-clamp:2}
.ad-overview-switch{min-width:0}
.ad-overview-switch .ad-switch-copy{min-width:0}
.ad-overview-switch .ad-switch-copy b{font-size:12px;font-weight:600}
.ad-overview-switch .ad-switch-copy small{font-size:10px}
.ad-overview-material{display:grid;grid-template-columns:104px minmax(0,1fr);align-items:center;gap:10px;min-width:0}
.ad-overview-thumb{display:grid;place-items:center;width:104px;height:60px;overflow:hidden;border:1px solid #dfe3ea;border-radius:6px;background:#eef1f5;color:#95a0b1}
.ad-overview-thumb img{width:100%;height:100%;object-fit:contain;background:#f2f4f7}
.ad-overview-material>div:last-child{min-width:0}
.ad-overview-material b,.ad-overview-material small,.ad-overview-material span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ad-overview-material b{margin-bottom:4px;color:#30324d;font-size:12px;font-weight:600}
.ad-overview-material small,.ad-overview-material span{color:#7b8495;font-size:10px;line-height:1.5}
.ad-overview-rule{min-width:0}
.ad-overview-rule label{display:block;margin-bottom:5px;color:#697386;font-size:10px;font-weight:600}
.ad-overview-rule .form-control{height:34px;border-color:#d5dae4;border-radius:6px;box-shadow:none;color:#30324d;font-size:12px}
.ad-overview-rule small{display:block;overflow:hidden;margin-top:5px;color:#7b8495;font-size:10px;text-overflow:ellipsis;white-space:nowrap}
.ad-slot-map{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:5px;margin-top:8px}
.ad-slot-map span{display:flex;align-items:center;gap:5px;min-width:0;padding:5px 7px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc;color:#64748b;font-size:10px;line-height:1.2}
.ad-slot-map span i{display:grid;place-items:center;width:16px;height:16px;flex:0 0 16px;border-radius:4px;background:#e2e8f0;color:#475569;font-style:normal;font-weight:700}
.ad-slot-map span.is-filled{border-color:var(--ad-region-accent);background:var(--ad-region-soft);color:var(--ad-region-accent);font-weight:650}
.ad-slot-map span.is-filled i{background:var(--ad-region-accent);color:#fff}
.ad-overview-actions{display:flex;align-items:center;justify-content:flex-end;gap:6px;flex-wrap:wrap}
.ad-overview-actions .ad-btn{flex:0 0 auto;min-width:0}
.ad-overview-actions .ad-btn{height:32px!important;padding:0 10px!important;font-size:12px!important}
.ad-overview-detail{border-top:1px solid #e4e7ed;background:#f8f9fc}
.ad-overview-detail .ad-materials{border-top:0;background:#fff}
.ad-overview-detail .ad-materials-head{min-height:52px;padding:9px 14px;background:#f8f9fc}
.ad-overview-detail .ad-materials-head .right{display:flex;align-items:center;gap:8px}
.ad-overview-detail .ad-materials-head .ad-btn{height:30px!important;padding:0 9px!important;font-size:11px!important}
.ad-overview-detail .ad-material-edit{background:#f8f9fc}
.ad-shell.is-system-off .ad-overview-list{opacity:.72}
.ad-shell.is-system-off .ad-overview-master{background:#fafbfc}
/* Keep each ad position visually distinct for quick scanning. */
.ad-overview-region{--ad-region-accent:#1d4ed8;--ad-region-soft:#dbeafe;--ad-region-border:#60a5fa;border:2px solid var(--ad-region-border);border-radius:8px;overflow:hidden;margin:10px;background:#fff}
.ad-overview-region[data-region-band="below_search"]{--ad-region-accent:#1d4ed8;--ad-region-soft:#dbeafe;--ad-region-border:#60a5fa}
.ad-overview-region[data-region-band="pc_left"]{--ad-region-accent:#047857;--ad-region-soft:#d1fae5;--ad-region-border:#34d399}
.ad-overview-region[data-region-band="pc_right"]{--ad-region-accent:#6d28d9;--ad-region-soft:#ede9fe;--ad-region-border:#a78bfa}
.ad-overview-region .ad-overview-row{background:#fff;border-top:3px solid var(--ad-region-accent)}
.ad-overview-region .ad-overview-region-icon{background:var(--ad-region-accent);color:#fff;border:0;box-shadow:0 2px 0 rgba(15,23,42,.12)}
.ad-overview-region .ad-overview-region-title h2{color:var(--ad-region-accent)}
.ad-overview-region .ad-overview-switch .ad-switch-copy b{color:var(--ad-region-accent)}
.ad-overview-region .ad-overview-switch input:checked+.ad-switch-ui{background:var(--ad-region-accent)}
.ad-overview-region .ad-overview-rule .form-control:focus{border-color:var(--ad-region-accent);box-shadow:0 0 0 3px rgba(59,130,246,.14)}
.ad-overview-region .ad-overview-actions .ad-btn-primary{background:var(--ad-region-accent)!important;border-color:var(--ad-region-accent)!important}
.ad-overview-region .ad-overview-actions .ad-btn-primary:hover,.ad-overview-region .ad-overview-actions .ad-btn-primary:focus{filter:brightness(.92)}
.ad-overview-region .ad-overview-detail{background:#fff}
.ad-add-source{display:none!important}
.ad-inline-add-slot{display:none;padding:14px;background:#fff;border-top:1px dashed #d7dee8}
.ad-inline-add-slot.is-active{display:block}
.ad-inline-add-head{display:flex;align-items:baseline;gap:10px;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #e5eaf0}
.ad-inline-add-head b{color:#172033;font-size:13px}.ad-inline-add-head small{color:#667085;font-size:11px}
.ad-inline-add-slot .ad-add-form{margin:0;padding:0;border:0;background:#fff}
.ad-inline-add-slot .ad-editor-layout{padding:0}
.ad-material-row>*{min-width:0}
.ad-material-actions{min-width:0}
@media(max-width:1280px){
  .ad-overview-master{grid-template-columns:minmax(280px,1fr) auto}.ad-overview-master-actions{grid-column:1/-1;min-width:0;padding-top:12px;border-top:1px solid #e4e7ed}
  .ad-overview-row{grid-template-columns:minmax(250px,1fr) 160px minmax(260px,1fr)}.ad-overview-rule{grid-column:1/2}.ad-overview-actions{grid-column:2/4;justify-content:flex-start}
}
@media(max-width:780px){
  .ad-overview-master{grid-template-columns:1fr;padding:14px}.ad-overview-master-facts{display:grid;grid-template-columns:1fr 1fr;gap:12px}.ad-overview-master-actions{grid-column:auto;grid-template-columns:1fr}.ad-overview-master-save{grid-column:auto;height:44px!important}
  .ad-overview-row{grid-template-columns:1fr;gap:13px;padding:15px}.ad-overview-rule,.ad-overview-actions{grid-column:auto}.ad-overview-actions{justify-content:flex-start}.ad-overview-material{grid-template-columns:96px minmax(0,1fr)}.ad-overview-thumb{width:96px;height:60px}
  .ad-overview-detail .ad-materials-head{align-items:flex-start;flex-wrap:wrap}.ad-overview-detail .ad-materials-head .right{width:100%;margin-left:0;justify-content:space-between}
}
@media(prefers-reduced-motion:reduce){.ad-overview-region,.ad-switch-ui,.ad-switch-ui:after{transition:none!important}}
</style>
<div class="container ad-shell">
  <?php foreach($tips as $tip): ?>
    <div class="ad-toast <?php echo $tip['type']; ?>"><span class="glyphicon glyphicon-ok"></span> <?php echo htmlspecialchars($tip['text']); ?></div>
  <?php endforeach; ?>
  <?php foreach($errors as $error): ?>
    <div class="ad-toast error"><span class="glyphicon glyphicon-remove"></span> <?php echo htmlspecialchars($error); ?></div>
  <?php endforeach; ?>

  <form id="adGlobalForm" method="post" class="ad-overview-master">
    <input type="hidden" name="action" value="save_global">
    <div class="ad-overview-master-copy"><strong>整个广告系统</strong><small>总开关关闭后，三个区域的配置、素材和统计仍会保留，但前台不再展示广告。</small></div>
    <div class="ad-overview-master-facts" aria-label="广告系统概览">
      <span><small>已开启区域</small><b data-enabled-region-count><?php echo $enabled_region_count; ?> / 3</b></span>
      <span><small>启用素材</small><b><?php echo $active_ad_count; ?> 个</b></span>
      <span><small>今日曝光</small><b><?php echo number_format($ad_today_views); ?></b></span>
      <span><small>今日点击</small><b><?php echo number_format($ad_today_clicks); ?></b></span>
    </div>
    <div class="ad-overview-master-actions">
      <label class="ad-switch ad-inline-switch ad-overview-action-control"><input type="checkbox" name="ad_new_window" value="1" <?php echo $ad_new_window=='1'?'checked':''; ?>><i class="ad-switch-ui"></i><span class="ad-switch-copy"><b>新窗口打开</b><small>广告链接在独立窗口打开</small></span></label>
      <label class="ad-switch ad-overview-master-switch ad-overview-action-control"><input type="checkbox" name="ad_enabled" value="1" <?php echo $ad_enabled=='1'?'checked':''; ?> data-global-master><i class="ad-switch-ui"></i><span class="ad-switch-copy"><b data-global-master-label><?php echo $ad_enabled=='1'?'广告系统已开启':'广告系统已关闭'; ?></b><small>控制全部广告位</small></span></label>
      <button class="btn ad-btn ad-btn-primary ad-overview-master-save" type="submit"><span class="glyphicon glyphicon-ok"></span> 保存全部设置</button>
    </div>
  </form>

  <div class="collapse ad-add-source" id="adAddPanel" aria-hidden="true" style="display:none">
    <section class="ad-add-panel">
      <div class="ad-section-head"><span class="glyphicon glyphicon-plus"></span><h2>新增广告素材</h2><p>上传后会自动检查尺寸并等比缩小</p></div>
      <div id="adAddFormHost">
      <form method="post" enctype="multipart/form-data" class="ad-edit-form ad-add-form">
        <input type="hidden" name="action" value="save_ad">
        <input type="hidden" name="position" value="below_search" class="ad-position-input">
        <div class="ad-editor-layout">
          <div class="ad-editor-preview">
            <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="待添加广告素材预览">
            <div class="ad-editor-placeholder"><span class="glyphicon glyphicon-picture"></span><b>广告素材预览</b><small>选择图片或填写图片地址后在这里预览</small></div>
          </div>
          <div>
            <div class="ad-form-grid">
              <div class="ad-slot-field" data-slot-field><label>四宫格位置</label><select name="slot" class="form-control ad-slot-select"><?php foreach($slot_labels as $slot_value => $slot_name): ?><option value="<?php echo intval($slot_value); ?>"><?php echo htmlspecialchars($slot_name); ?></option><?php endforeach; ?></select><small class="text-muted">搜索栏下方按左上、右上、左下、右下固定展示。</small></div>
              <div><label>排序</label><input type="number" name="sort" value="100" class="form-control"></div>
              <div><label>随机权重</label><input type="number" name="weight" value="1" min="1" max="50" class="form-control"></div>
              <div class="wide"><label>广告标题</label><input type="text" name="title" class="form-control" placeholder="用于后台识别这条广告"></div>
              <div class="wide"><label>跳转链接</label><input type="text" name="link" class="form-control" placeholder="https://example.com"></div>
              <div class="wide"><label>图片外链 / 上传后自动填入</label><input type="text" name="image" class="form-control ad-url-input" placeholder="https://example.com/ad.gif"></div>
              <div class="wide"><label>上传图片，支持 JPG、PNG、GIF、WebP</label><input type="file" name="ad_file" class="form-control ad-upload-input" accept="image/jpeg,image/png,image/gif,image/webp"><div class="ad-upload-progress"><span></span></div><div class="ad-upload-msg"></div></div>
              <div class="wide"><label>图片说明</label><input type="text" name="alt" class="form-control" placeholder="图片无法显示时展示的文字"></div>
              <div><label>广告状态</label><label class="ad-switch ad-inline-switch"><input type="checkbox" name="active" value="1" checked><i class="ad-switch-ui"></i><span class="ad-switch-copy"><b>立即启用</b></span></label></div>
              <div class="wide"><label>定时上线</label><input type="datetime-local" name="start_at" class="form-control"></div>
              <div class="wide"><label>定时下线</label><input type="datetime-local" name="end_at" class="form-control"></div>
            </div>
            <div class="ad-editor-actions"><button class="btn ad-btn ad-btn-primary" type="submit"><span class="glyphicon glyphicon-plus"></span> 添加广告素材</button><button class="btn ad-btn ad-btn-muted" type="button" data-cancel-inline-add>取消添加</button><span class="text-muted">当前表单只会保存到本广告板块。</span></div>
          </div>
        </div>
      </form>
      </div>
    </section>
  </div>

  <div class="ad-workspace-heading">
    <div>
      <h2>广告区域</h2>
      <p>按前台展示位置管理素材、投放规则与区域状态。</p>
    </div>
    <span><b><?php echo $enabled_region_count; ?></b> / 3 个区域已开启</span>
  </div>

  <div class="ad-overview-list">
  <?php foreach($position_order as $position_key):
    $meta = $position_meta[$position_key];
    $summary = $position_summary[$position_key];
    $region_ads = $ads_by_position[$position_key];
    $primary_ad = $summary['primary'];
    $mode_key = 'ad_mode_'.$position_key;
    $current_mode = isset($conf[$mode_key]) ? $conf[$mode_key] : 'fixed';
    $region_enabled = $meta['enabled'] === '1';
    $region_ctr = $summary['views'] > 0 ? round($summary['clicks'] * 100 / $summary['views'], 1) : 0;
    $region_limit = ad_admin_single_region_limit($position_key);
    $region_can_add = $summary['count'] < $region_limit;
    $default_slot = 1;
    if($position_key === 'below_search'){
        foreach($slot_labels as $slot_value => $slot_name){
            if(empty($summary['slots'][$slot_value])){
                $default_slot = intval($slot_value);
                break;
            }
        }
    }
  ?>
  <section class="ad-overview-region <?php echo $region_enabled?'':'is-off'; ?>" id="region-<?php echo $position_key; ?>" data-region-band="<?php echo $position_key; ?>" data-default-slot="<?php echo intval($default_slot); ?>" data-can-add="<?php echo $region_can_add ? '1' : '0'; ?>">
    <div class="ad-overview-row">
      <div class="ad-overview-region-title">
        <span class="ad-overview-region-icon ad-overview-region-icon-<?php echo $position_key; ?>"><?php echo $position_key === 'below_search' ? 'TOP' : ($position_key === 'pc_left' ? 'LEFT' : 'RIGHT'); ?></span>
        <div><h2><?php echo htmlspecialchars($meta['short']); ?></h2><p><?php echo htmlspecialchars($meta['description']); ?></p></div>
      </div>
      <label class="ad-switch ad-band-switch ad-overview-switch">
        <input form="adGlobalForm" type="checkbox" name="<?php echo $meta['toggle']; ?>" value="1" <?php echo $region_enabled?'checked':''; ?> data-region-toggle="<?php echo $position_key; ?>">
        <i class="ad-switch-ui"></i>
        <span class="ad-switch-copy"><b data-region-switch-label><?php echo $region_enabled?'区域已开启':'区域已关闭'; ?></b><small>独立控制前台展示</small></span>
      </label>
      <div class="ad-overview-material ad-media-dim">
        <div class="ad-overview-thumb">
          <?php if($primary_ad): ?>
            <img class="ad-material-image" src="<?php echo htmlspecialchars($primary_ad['image']); ?>" alt="<?php echo htmlspecialchars($primary_ad['alt'] ?: ($primary_ad['title'] ?: $meta['short'].'广告素材')); ?>">
          <?php else: ?>
            <span class="glyphicon glyphicon-picture" aria-hidden="true"></span>
          <?php endif; ?>
        </div>
        <div><b><?php echo $primary_ad ? htmlspecialchars($primary_ad['title'] ?: '未命名广告') : '等待添加素材'; ?></b><small><?php echo htmlspecialchars($meta['recommended']); ?> · <?php echo $position_key === 'below_search' ? count($summary['slots']).' / 4 个位置已配置' : intval($summary['count'] > 0 ? 1 : 0).' / 1 个素材'; ?></small><span class="ad-image-dimensions"><?php echo $primary_ad ? '正在读取图片尺寸' : '暂无素材'; ?></span></div>
      </div>
      <div class="ad-overview-rule ad-media-dim">
        <label>展示方式</label>
        <select form="adGlobalForm" name="<?php echo $mode_key; ?>" class="form-control"><?php foreach($modes as $mk=>$mv): ?><option value="<?php echo $mk; ?>" <?php echo $current_mode===$mk?'selected':''; ?>><?php echo htmlspecialchars($mv); ?></option><?php endforeach; ?></select>
        <div class="ad-overview-performance" aria-label="区域投放统计">
          <span><small>累计曝光</small><b><?php echo intval($summary['views']); ?></b></span>
          <span><small>累计点击</small><b><?php echo intval($summary['clicks']); ?></b></span>
          <span><small>点击率</small><b><?php echo $region_ctr; ?>%</b></span>
        </div>
        <small><?php echo intval($summary['views']); ?> 次曝光 · <?php echo intval($summary['clicks']); ?> 次点击 · CTR <?php echo $region_ctr; ?>%</small>
        <?php if($position_key === 'below_search'): ?>
        <div class="ad-slot-map" aria-label="搜索栏下方四宫格位置">
          <?php foreach($slot_labels as $slot_value => $slot_name): ?>
          <span class="<?php echo empty($summary['slots'][$slot_value]) ? '' : 'is-filled'; ?>"><i><?php echo intval($slot_value); ?></i><?php echo htmlspecialchars($slot_name); ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="ad-overview-actions">
        <button class="btn ad-btn ad-btn-muted" type="button" data-manage-materials="<?php echo $position_key; ?>" aria-expanded="false"><span class="glyphicon glyphicon-th-list"></span> 管理素材</button>
        <a class="btn ad-btn ad-btn-muted" href="../" target="_blank" rel="noopener"><span class="glyphicon glyphicon-eye-open"></span> 查看前台</a>
        <button form="adGlobalForm" class="btn ad-btn ad-btn-primary" type="submit"><span class="glyphicon glyphicon-ok"></span> 保存区域</button>
      </div>
    </div>

    <div class="collapse ad-overview-detail" id="adMaterials<?php echo $position_key; ?>">
      <div class="ad-materials ad-media-dim">
        <div class="ad-materials-head"><span class="glyphicon glyphicon-picture"></span><h3><?php echo htmlspecialchars($meta['short']); ?>素材清单</h3><span><?php echo $position_key === 'below_search' ? '搜索栏下方最多配置 4 个位置，按左上、右上、左下、右下展示。' : 'PC 侧边悬浮为单广告位，只能保留 1 个素材。'; ?></span><div class="right"><span class="ad-status <?php echo $region_enabled?'on':'off'; ?>" data-region-state><?php echo $region_enabled?'前台区域开启':'前台区域关闭'; ?></span><?php if($region_can_add): ?><button class="btn ad-btn ad-btn-primary" type="button" data-add-position="<?php echo $position_key; ?>"><span class="glyphicon glyphicon-plus"></span> 在此板块添加</button><?php else: ?><button class="btn ad-btn ad-btn-muted" type="button" disabled><span class="glyphicon glyphicon-lock"></span> 已达上限</button><?php endif; ?></div></div>
        <div class="ad-inline-add-slot" data-inline-add-slot="<?php echo $position_key; ?>"><div class="ad-inline-add-head"><b>在 <?php echo htmlspecialchars($meta['short']); ?> 中添加素材</b><small>表单提交后只会写入当前广告板块</small></div></div>
      <?php if(empty($region_ads)): ?>
        <div class="ad-empty-materials"><span class="glyphicon glyphicon-picture"></span><b>这个区域还没有广告素材</b><span>点击“添加此区域广告”，系统会自动带入当前广告位置。</span></div>
      <?php else: foreach($region_ads as $ad):
        $status = qifu_ad_status_text($ad);
        $img_check = $check_images ? qifu_ad_check_image($ad['image']) : array(null, '未检测');
        $slot_label = $ad['position'] === 'below_search' ? qifu_ad_slot_label($ad['slot']) : '单一广告位';
        $start_value = $ad['start_at'] ? date('Y-m-d\TH:i', strtotime($ad['start_at'])) : '';
        $end_value = $ad['end_at'] ? date('Y-m-d\TH:i', strtotime($ad['end_at'])) : '';
      ?>
        <article class="ad-material-row">
          <div class="ad-material-thumb"><?php if($ad['image']): ?><img class="ad-material-image" src="<?php echo htmlspecialchars($ad['image']); ?>" alt="<?php echo htmlspecialchars($ad['alt'] ?: ($ad['title'] ?: '广告素材')); ?>"><?php else: ?>无图片<?php endif; ?></div>
          <div class="ad-material-info"><h4><?php echo htmlspecialchars($ad['title'] ?: '未命名广告'); ?></h4><p><?php echo htmlspecialchars($ad['link'] ?: '未设置跳转链接'); ?></p><p><span class="ad-status <?php echo $status[0]; ?>"><?php echo htmlspecialchars($status[1]); ?></span>　<?php echo $slot_label; ?>　<span class="ad-row-image-size">读取尺寸中</span></p></div>
          <div class="ad-material-meta"><span>排序 / 权重<b><?php echo intval($ad['sort']); ?> / <?php echo intval($ad['weight']); ?></b></span><span>累计曝光 / 点击<b><?php echo intval($ad['views']); ?> / <?php echo intval($ad['clicks']); ?></b></span><span>上线时间<b><?php echo htmlspecialchars($ad['start_at'] ?: '立即'); ?></b></span><span>图片检测<b><?php if($img_check[0] === true): ?><em class="ad-img-ok"><?php echo htmlspecialchars($img_check[1]); ?></em><?php elseif($img_check[0] === false): ?><em class="ad-img-bad"><?php echo htmlspecialchars($img_check[1]); ?></em><?php else: ?><?php echo htmlspecialchars($img_check[1]); ?><?php endif; ?></b></span></div>
          <div class="ad-material-actions"><button class="btn ad-btn ad-btn-muted" type="button" data-toggle="collapse" data-target="#adEdit<?php echo intval($ad['id']); ?>"><span class="glyphicon glyphicon-pencil"></span> 编辑</button><form method="post" class="ad-delete-form"><input type="hidden" name="action" value="delete_ad"><input type="hidden" name="id" value="<?php echo intval($ad['id']); ?>"><button class="btn ad-btn ad-btn-danger" type="submit"><span class="glyphicon glyphicon-trash"></span> 删除</button></form></div>
        </article>
        <div class="collapse ad-material-edit" id="adEdit<?php echo intval($ad['id']); ?>">
          <form method="post" enctype="multipart/form-data" class="ad-edit-form">
            <input type="hidden" name="action" value="save_ad"><input type="hidden" name="id" value="<?php echo intval($ad['id']); ?>">
            <input type="hidden" name="position" value="<?php echo htmlspecialchars($ad['position']); ?>" class="ad-position-input">
            <div class="ad-form-grid">
              <div class="ad-slot-field" data-slot-field><label>四宫格位置</label><select name="slot" class="form-control ad-slot-select"><?php foreach($slot_labels as $slot_value => $slot_name): ?><option value="<?php echo intval($slot_value); ?>" <?php echo intval($ad['slot'])===intval($slot_value)?'selected':''; ?>><?php echo htmlspecialchars($slot_name); ?></option><?php endforeach; ?></select><small class="text-muted">搜索栏下方按左上、右上、左下、右下固定展示。</small></div>
              <div><label>排序</label><input type="number" name="sort" value="<?php echo intval($ad['sort']); ?>" class="form-control"></div>
              <div><label>随机权重</label><input type="number" name="weight" value="<?php echo intval($ad['weight']); ?>" min="1" max="50" class="form-control"></div>
              <div class="wide"><label>广告标题</label><input type="text" name="title" value="<?php echo htmlspecialchars($ad['title']); ?>" class="form-control"></div>
              <div class="wide"><label>跳转链接</label><input type="text" name="link" value="<?php echo htmlspecialchars($ad['link']); ?>" class="form-control"></div>
              <div class="wide"><label>图片外链 / 上传后自动填入</label><input type="text" name="image" value="<?php echo htmlspecialchars($ad['image']); ?>" class="form-control ad-url-input"></div>
              <div class="wide"><label>上传替换图片</label><input type="file" name="ad_file" class="form-control ad-upload-input" accept="image/jpeg,image/png,image/gif,image/webp"><div class="ad-upload-progress"><span></span></div><div class="ad-upload-msg"></div></div>
              <div class="wide"><label>图片说明</label><input type="text" name="alt" value="<?php echo htmlspecialchars($ad['alt']); ?>" class="form-control"></div>
              <div><label>广告状态</label><label class="ad-switch ad-inline-switch"><input type="checkbox" name="active" value="1" <?php echo intval($ad['active'])===1?'checked':''; ?>><i class="ad-switch-ui"></i><span class="ad-switch-copy"><b>启用广告</b></span></label></div>
              <div class="wide"><label>定时上线</label><input type="datetime-local" name="start_at" value="<?php echo $start_value; ?>" class="form-control"></div>
              <div class="wide"><label>定时下线</label><input type="datetime-local" name="end_at" value="<?php echo $end_value; ?>" class="form-control"></div>
            </div>
            <div class="ad-editor-actions"><button class="btn ad-btn ad-btn-primary" type="submit"><span class="glyphicon glyphicon-ok"></span> 保存广告修改</button><button class="btn ad-btn ad-btn-muted" type="button" data-toggle="collapse" data-target="#adEdit<?php echo intval($ad['id']); ?>">取消</button></div>
          </form>
        </div>
      <?php endforeach; endif; ?>
      </div>
    </div>
  </section>
  <?php endforeach; ?>
  </div>
</div>
<script>
(function(){
  /* Bootstrap is optional in embedded admin pages. Keep collapse controls usable without it. */
  function getCollapseTarget(button){
    if(!button) return null;
    var selector = button.getAttribute('data-target') || button.getAttribute('href');
    if(!selector || selector.charAt(0) !== '#') return null;
    try{ return document.querySelector(selector); }catch(error){ return null; }
  }
  function setCollapseState(target, open, trigger){
    if(!target) return;
    target.classList.remove('collapsing');
    target.classList.toggle('in', open);
    target.style.display = open ? 'block' : 'none';
    target.style.height = '';
    target.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.querySelectorAll('[data-toggle="collapse"]').forEach(function(button){
      if(getCollapseTarget(button) === target) button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    if(trigger && getCollapseTarget(trigger) === target) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  document.querySelectorAll('[data-toggle="collapse"]').forEach(function(button){
    var target = getCollapseTarget(button);
    if(!target) return;
    var initiallyOpen = target.classList.contains('in');
    setCollapseState(target, initiallyOpen, button);
    button.addEventListener('click', function(event){
      event.preventDefault();
      event.stopImmediatePropagation();
      var isOpen = target.classList.contains('in') || target.style.display === 'block';
      setCollapseState(target, !isOpen, button);
    }, true);
  });
  function setMsg(box, text, ok){ box.textContent = text; box.style.color = ok ? '#1f7a3a' : '#b42318'; }
  function updateAddPreview(form, url){
    if(!form || !form.classList.contains('ad-add-form')) return;
    var preview = form.querySelector('.ad-editor-preview');
    var image = preview ? preview.querySelector('img') : null;
    if(!preview || !image) return;
    url = String(url || '').trim();
    if(!url){
      preview.classList.remove('has-image');
      image.removeAttribute('src');
      return;
    }
    image.src = url;
    preview.classList.add('has-image');
  }
  function updateSlotState(form){
    var pos = form.querySelector('.ad-position-input');
    var slot = form.querySelector('.ad-slot-select');
    var slotField = form.querySelector('[data-slot-field]');
    if(!pos) return;
    var isBelowSearch = pos.value === 'below_search';
    if(slot) slot.disabled = !isBelowSearch;
    if(slotField) slotField.classList.toggle('is-hidden', !isBelowSearch);
  }
  document.querySelectorAll('.ad-edit-form').forEach(function(form){
    updateSlotState(form);
    var urlInput = form.querySelector('.ad-url-input');
    if(urlInput){
      urlInput.addEventListener('input', function(){ updateAddPreview(form, this.value); });
      updateAddPreview(form, urlInput.value);
    }
  });
  function openInlineAdAdd(positionKey, region, focusFirstInput){
    var panel = document.getElementById('adAddPanel');
    var form = panel ? panel.querySelector('.ad-add-form') : null;
    var position = form ? form.querySelector('.ad-position-input') : null;
    var slot = region ? region.querySelector('[data-inline-add-slot="' + positionKey + '"]') : null;
    var detail = region ? region.querySelector('#adMaterials' + positionKey) : null;
    if(!panel || !form || !position || !slot) return false;
    position.value = positionKey;
    var slotSelect = form.querySelector('.ad-slot-select');
    var defaultSlot = region.getAttribute('data-default-slot') || '';
    if(position.value === 'below_search' && slotSelect && defaultSlot) slotSelect.value = defaultSlot;
    updateSlotState(form);
    document.querySelectorAll('.ad-inline-add-slot.is-active').forEach(function(activeSlot){
      activeSlot.classList.remove('is-active');
    });
    slot.appendChild(form);
    slot.classList.add('is-active');
    if(detail) setCollapseState(detail, true, null);
    if(focusFirstInput){
      window.setTimeout(function(){
        slot.scrollIntoView({behavior:'smooth', block:'center'});
        var firstInput = slot.querySelector('input[name="title"]');
        if(firstInput) firstInput.focus();
      }, 80);
    }
    return true;
  }
  document.querySelectorAll('[data-add-position]').forEach(function(button){
    button.addEventListener('click', function(){
      var region = button.closest('[data-region-band]');
      openInlineAdAdd(button.getAttribute('data-add-position'), region, true);
    });
  });
  document.querySelectorAll('[data-manage-materials]').forEach(function(button){
    button.addEventListener('click', function(){
      var region = button.closest('[data-region-band]');
      var positionKey = button.getAttribute('data-manage-materials');
      var detail = region ? region.querySelector('#adMaterials' + positionKey) : null;
      if(!region || !positionKey || !detail) return;
      setCollapseState(detail, true, button);
      if(region.getAttribute('data-region-band') !== 'below_search' && region.getAttribute('data-can-add') === '1'){
        openInlineAdAdd(positionKey, region, false);
      }
    });
  });
  document.querySelectorAll('[data-cancel-inline-add]').forEach(function(button){
    button.addEventListener('click', function(){
      var panel = document.getElementById('adAddPanel');
      var host = document.getElementById('adAddFormHost');
      var form = button.closest('.ad-add-form');
      if(!panel || !host || !form) return;
      host.appendChild(form);
      document.querySelectorAll('.ad-inline-add-slot.is-active').forEach(function(activeSlot){
        activeSlot.classList.remove('is-active');
      });
      form.reset();
      updateSlotState(form);
      updateAddPreview(form, '');
      var msg = form.querySelector('.ad-upload-msg');
      if(msg) msg.textContent = '';
    });
  });
  function updateRegionBand(input){
    var key = input.getAttribute('data-region-toggle');
    var band = document.querySelector('[data-region-band="' + key + '"]');
    if(!band) return;
    band.classList.toggle('is-off', !input.checked);
    var title = band.querySelector('[data-region-switch-label]');
    var status = band.querySelector('[data-region-state]');
    if(title) title.textContent = input.checked ? '区域已开启' : '区域已关闭';
    if(status){
      status.textContent = input.checked ? '前台区域开启' : '前台区域关闭';
      status.className = 'ad-status ' + (input.checked ? 'on' : 'off');
    }
    var enabledCount = document.querySelectorAll('[data-region-toggle]:checked').length;
    var enabledLabel = document.querySelector('[data-enabled-region-count]');
    if(enabledLabel) enabledLabel.textContent = enabledCount + ' / 3';
  }
  document.querySelectorAll('[data-region-toggle]').forEach(function(input){
    updateRegionBand(input);
    input.addEventListener('change', function(){ updateRegionBand(input); });
  });
  function updateGlobalMaster(input){
    var shell = document.querySelector('.ad-shell');
    var label = document.querySelector('[data-global-master-label]');
    if(shell) shell.classList.toggle('is-system-off', !input.checked);
    if(label) label.textContent = input.checked ? '广告系统已开启' : '广告系统已关闭';
  }
  var globalMaster = document.querySelector('[data-global-master]');
  if(globalMaster){
    updateGlobalMaster(globalMaster);
    globalMaster.addEventListener('change', function(){ updateGlobalMaster(globalMaster); });
  }
  document.querySelectorAll('.ad-delete-form').forEach(function(form){
    form.addEventListener('submit', function(event){
      if(!window.confirm('确定删除这个广告吗？对应统计也会一起删除。')) event.preventDefault();
    });
  });
  function showImageDimensions(image){
    var text = image.naturalWidth && image.naturalHeight ? image.naturalWidth + ' × ' + image.naturalHeight + ' px' : '无法读取图片尺寸';
    var keyMedia = image.closest('.ad-key-media');
    var row = image.closest('.ad-material-row');
    var overview = image.closest('.ad-overview-material');
    if(keyMedia){
      var keyLabel = keyMedia.querySelector('.ad-image-dimensions');
      if(keyLabel) keyLabel.textContent = text;
    }
    if(row){
      var rowLabel = row.querySelector('.ad-row-image-size');
      if(rowLabel) rowLabel.textContent = text;
    }
    if(overview){
      var overviewLabel = overview.querySelector('.ad-image-dimensions');
      if(overviewLabel) overviewLabel.textContent = text;
    }
  }
  document.querySelectorAll('.ad-material-image').forEach(function(image){
    if(image.complete) showImageDimensions(image);
    image.addEventListener('load', function(){ showImageDimensions(image); });
    image.addEventListener('error', function(){
      var row = image.closest('.ad-material-row');
      var keyMedia = image.closest('.ad-key-media');
      var overview = image.closest('.ad-overview-material');
      var label = row ? row.querySelector('.ad-row-image-size') : (keyMedia ? keyMedia.querySelector('.ad-image-dimensions') : (overview ? overview.querySelector('.ad-image-dimensions') : null));
      if(label) label.textContent = '图片加载失败';
    });
  });
  function fitAdDimensions(width, height, position){
    var box = position === 'pc_left' || position === 'pc_right' ? {width:600, height:800} : {width:1440, height:480};
    var scale = Math.min(1, box.width / width, box.height / height);
    return {width:Math.max(1, Math.round(width * scale)), height:Math.max(1, Math.round(height * scale)), resized:scale < 1};
  }
  function prepareAdFile(file, position){
    return new Promise(function(resolve){
      var fallback = {file:file, name:file.name, resized:false, originalWidth:0, originalHeight:0, width:0, height:0};
      if(!file || file.type === 'image/gif' || !/^image\/(jpeg|png|webp)$/i.test(file.type)){
        resolve(fallback);
        return;
      }
      var objectUrl = URL.createObjectURL(file);
      var image = new Image();
      image.onload = function(){
        var originalWidth = image.naturalWidth || image.width;
        var originalHeight = image.naturalHeight || image.height;
        var fit = fitAdDimensions(originalWidth, originalHeight, position);
        if(!fit.resized){
          URL.revokeObjectURL(objectUrl);
          resolve({file:file, name:file.name, resized:false, originalWidth:originalWidth, originalHeight:originalHeight, width:originalWidth, height:originalHeight});
          return;
        }
        var canvas = document.createElement('canvas');
        canvas.width = fit.width;
        canvas.height = fit.height;
        var context = canvas.getContext('2d');
        if(!context){
          URL.revokeObjectURL(objectUrl);
          resolve(fallback);
          return;
        }
        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.drawImage(image, 0, 0, fit.width, fit.height);
        URL.revokeObjectURL(objectUrl);
        canvas.toBlob(function(blob){
          if(!blob){ resolve(fallback); return; }
          resolve({file:blob, name:file.name, resized:true, originalWidth:originalWidth, originalHeight:originalHeight, width:fit.width, height:fit.height});
        }, file.type, file.type === 'image/png' ? undefined : .88);
      };
      image.onerror = function(){
        URL.revokeObjectURL(objectUrl);
        resolve(fallback);
      };
      image.src = objectUrl;
    });
  }
  document.querySelectorAll('.ad-upload-input').forEach(function(input){
    input.addEventListener('change', function(){
      if(!this.files || !this.files[0]) return;
      var sourceFile = this.files[0];
      var form = this.closest('form');
      var target = form.querySelector('.ad-url-input');
      var positionInput = form.querySelector('.ad-position-input');
      var position = positionInput ? positionInput.value : 'below_search';
      var submitButton = form.querySelector('button[type="submit"]');
      var bar = form.querySelector('.ad-upload-progress');
      var fill = bar.querySelector('span');
      var msg = form.querySelector('.ad-upload-msg');
      bar.style.display = 'block';
      fill.style.transform = 'scaleX(0)';
      input.disabled = true;
      if(submitButton) submitButton.disabled = true;
      setMsg(msg, '正在检查并适配图片尺寸...', true);
      prepareAdFile(sourceFile, position).then(function(prepared){
        var data = new FormData();
        data.append('file', prepared.file, prepared.name || sourceFile.name);
        data.append('slot', 'ad_admin');
        data.append('position', position);
        data.append('_csrf', document.querySelector('meta[name="qifu-csrf"]').getAttribute('content'));
        var xhr = new XMLHttpRequest();
        xhr.open('POST', './ajax_upload_ad.php', true);
        if(prepared.resized){
          setMsg(msg, '已从 ' + prepared.originalWidth + '×' + prepared.originalHeight + ' 等比缩小为 ' + prepared.width + '×' + prepared.height + '，正在上传...', true);
        }else{
          setMsg(msg, sourceFile.type === 'image/gif' ? 'GIF 动画保持原图，正在上传...' : '图片无需缩小，正在上传...', true);
        }
        xhr.upload.onprogress = function(e){
          if(e.lengthComputable) fill.style.transform = 'scaleX(' + (e.loaded / e.total) + ')';
        };
        xhr.onload = function(){
          input.disabled = false;
          if(submitButton) submitButton.disabled = false;
          var res;
          try{ res = JSON.parse(xhr.responseText); }catch(e){ setMsg(msg, '服务器返回异常：' + xhr.responseText.substring(0, 120), false); return; }
          if(res.code == 1){
            fill.style.transform = 'scaleX(1)';
            target.value = res.url;
            updateAddPreview(form, res.url);
            input.value = '';
            var resultText = prepared.resized
              ? '上传成功，图片已从 ' + prepared.originalWidth + '×' + prepared.originalHeight + ' 等比缩小为 ' + prepared.width + '×' + prepared.height
              : String(res.msg || '上传成功');
            setMsg(msg, resultText + '，保存广告后生效。', true);
          }else{
            setMsg(msg, res.msg || '上传失败，可直接点保存尝试兜底上传。', false);
          }
        };
        xhr.onerror = function(){ input.disabled = false; if(submitButton) submitButton.disabled = false; setMsg(msg, '网络错误，可直接点保存尝试兜底上传。', false); };
        xhr.send(data);
      }).catch(function(){
        input.disabled = false;
        if(submitButton) submitButton.disabled = false;
        setMsg(msg, '图片处理失败，可直接点保存尝试兜底上传。', false);
      });
    });
  });
})();
</script>
