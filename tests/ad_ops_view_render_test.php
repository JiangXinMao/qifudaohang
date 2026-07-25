<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

set_error_handler(function($severity, $message, $file, $line){
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function qifu_ad_status_text($ad){
    return intval($ad['active']) === 1 && !empty($ad['image']) ? array('on', '投放中') : array('off', '已停用');
}
function qifu_ad_slot_label($slot){
    $labels = array(1 => '左上', 2 => '右上', 3 => '左下', 4 => '右下');
    return $labels[max(1, min(4, intval($slot)))];
}
function qifu_csrf_input(){
    return '<input type="hidden" name="_csrf" value="test-token">';
}
function qifu_ad_check_image($url){
    return array(true, '可访问');
}

$slot_labels = array(1 => '左上', 2 => '右上', 3 => '左下', 4 => '右下');
$position_order = array('below_search', 'pc_left', 'pc_right');
$position_meta = array(
    'below_search' => array('short' => '搜索栏下方', 'recommended' => '840 × 240 px', 'toggle' => 'ad_show_below', 'enabled' => '1'),
    'pc_left' => array('short' => 'PC 左侧悬浮', 'recommended' => '560 × 1240 px', 'toggle' => 'ad_show_left', 'enabled' => '0'),
    'pc_right' => array('short' => 'PC 右侧悬浮', 'recommended' => '560 × 1240 px', 'toggle' => 'ad_show_right', 'enabled' => '1'),
);
$top_ad = array('id' => 1, 'slot' => 1, 'title' => '顶部广告', 'image' => '/images/top.png', 'link' => 'https://example.com/top', 'alt' => '顶部广告', 'active' => 1, 'sort' => 100, 'weight' => 1, 'start_at' => '', 'end_at' => '');
$side_ad = array('id' => 2, 'slot' => 1, 'title' => '右侧广告', 'image' => '/images/right.png', 'link' => 'https://example.com/right', 'alt' => '右侧广告', 'active' => 1, 'sort' => 100, 'weight' => 1, 'start_at' => '', 'end_at' => '');
$ads_by_position = array('below_search' => array($top_ad), 'pc_left' => array(), 'pc_right' => array($side_ad));
$position_summary = array(
    'below_search' => array('count' => 1, 'active' => 1, 'views' => 10, 'clicks' => 2, 'slots' => array(1 => true), 'primary' => $top_ad),
    'pc_left' => array('count' => 0, 'active' => 0, 'views' => 0, 'clicks' => 0, 'slots' => array(), 'primary' => null),
    'pc_right' => array('count' => 1, 'active' => 1, 'views' => 20, 'clicks' => 3, 'slots' => array(), 'primary' => $side_ad),
);
$conf = array('ad_mode_below_search' => 'fixed', 'ad_mode_pc_left' => 'fixed', 'ad_mode_pc_right' => 'fixed');
$modes = array('fixed' => '按排序固定展示', 'rotate' => '按权重轮播', 'random' => '按权重随机');
$check_images = false;
$tips = array();
$errors = array();
$ad_enabled = '1';
$ad_new_window = '1';
$ad_today_views = 12;
$ad_today_clicks = 3;
$ad_today_ctr = 25;
$ad_total_views = 240;
$ad_total_clicks = 60;
$ad_total_ctr = 25;
$enabled_region_count = 2;
$active_ad_count = 2;
$side_ad_conflicts = array('pc_left' => 0, 'pc_right' => 0);
$_GET = array('region' => 'top', 'slot' => '1');

ob_start();
require dirname(__DIR__).'/admin/ad-ops-view.php';
$html = ob_get_clean();

$expectations = array(
    'data-region-nav="below_search"' => 'top region navigation',
    'data-slot-button="1"' => 'top slot navigation',
    'data-slot-panel="4"' => 'fourth top slot panel',
    'data-add-mount' => 'add-form mount',
    'id="adOpsAddForm"' => 'shared add form',
    'name="position" value="below_search"' => 'top material position',
    'name="position" value="pc_right"' => 'side material position',
    'name="ad_show_left"' => 'left region global control',
    'form="adGlobalForm"' => 'external global controls',
    'name="action" value="clear_cache"' => 'cache refresh action',
    'data-feedback-delay="900"' => 'minimum operation feedback delay',
    'data-region-theme="below_search"' => 'top region color theme',
    'data-region-theme="pc_left"' => 'left region color theme',
    'data-region-theme="pc_right"' => 'right region color theme',
    'data-ad-image-fit-note' => 'image fit guidance',
    'class="ad-ops-empty"' => 'empty left-side state',
    'data-editor-drawer="1"' => 'top material editor',
    'data-editor-drawer="2"' => 'side material editor',
    'aria-controls="adOpsRegion-below_search"' => 'region navigation state',
    'aria-controls="adOpsSlot-1"' => 'slot navigation state',
    'aria-controls="adOpsEditor-1"' => 'inline editor state',
    'name="slot" class="form-control ad-slot-input"' => 'top material slot move control',
    'name="sort" value="100"' => 'material sort is preserved during edits',
    'name="weight" value="1"' => 'material weight is preserved during edits',
    '单一素材固定展示' => 'fixed side-ad display rule',
    '累计曝光' => 'region cumulative analytics',
);
foreach($expectations as $needle => $label){
    if(strpos($html, $needle) === false){
        fwrite(STDERR, "Missing rendered {$label}.\n");
        exit(1);
    }
}

if(substr_count($html, 'data-feedback-delay="900"') < 5){
    fwrite(STDERR, "Not every write action has the required success feedback delay.\n");
    exit(1);
}
if(strpos($html, 'is-loading') !== false || strpos($html, 'data-feedback-text') !== false){
    fwrite(STDERR, "Operation buttons still render loading feedback UI.\n");
    exit(1);
}

echo "Ad operations view render test passed.\n";
