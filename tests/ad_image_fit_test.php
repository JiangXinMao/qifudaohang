<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

define('IN_CRONLITE', true);
require dirname(__DIR__).'/includes/ad_helper.php';

$failures = array();
function check_ad_image_fit($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

$slot_labels = qifu_ad_slot_labels();
check_ad_image_fit($slot_labels === array(1 => '左上', 2 => '右上', 3 => '左下', 4 => '右下'), 'search-banner slot labels are incorrect');
check_ad_image_fit(qifu_ad_slot_label(1) === '左上' && qifu_ad_slot_label(4) === '右下', 'search-banner slot label lookup is incorrect');
check_ad_image_fit(qifu_ad_slot_label(99) === '右下', 'search-banner slot label lookup does not clamp invalid slots');

$wide = qifu_ad_fit_dimensions(3000, 500, 'below_search');
check_ad_image_fit($wide['width'] === 1440 && $wide['height'] === 240 && $wide['resized'], 'wide banner dimensions are incorrect');

$tall_banner = qifu_ad_fit_dimensions(3000, 2000, 'below_search');
check_ad_image_fit($tall_banner['width'] === 720 && $tall_banner['height'] === 480, 'tall banner did not fit within 1440x480');

$side = qifu_ad_fit_dimensions(1000, 3000, 'pc_right');
check_ad_image_fit($side['width'] === 267 && $side['height'] === 800 && $side['resized'], 'side ad dimensions are incorrect');

$small = qifu_ad_fit_dimensions(300, 100, 'below_search');
check_ad_image_fit($small['width'] === 300 && $small['height'] === 100 && !$small['resized'], 'small image was unexpectedly enlarged');

$front_source = file_get_contents(dirname(__DIR__).'/index.php');
check_ad_image_fit(strpos($front_source, 'object-fit:contain') !== false, 'frontend ad images do not use contain fitting');
check_ad_image_fit(strpos($front_source, '.ad-side{') !== false && strpos($front_source, 'aspect-ratio:3/4') !== false, 'side ad container ratio is missing');
check_ad_image_fit(strpos($front_source, 'foreach($ad_below_items as $ad_slot_index => $ad_item)') !== false, 'frontend search banner does not render by deterministic slot index');
check_ad_image_fit(strpos($front_source, 'data-ad-slot="<?php echo $ad_slot; ?>"') !== false, 'frontend search banner cells do not expose their slot');
check_ad_image_fit(strpos($front_source, 'qifu_ad_slot_label($ad_slot)') !== false, 'frontend search banner does not use named four-grid slots');
check_ad_image_fit(strpos($front_source, '.online-stats-row{') !== false && strpos($front_source, '.online-stats-row{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:7px 9px;padding:11px 14px;border:0;background:transparent;') !== false, 'frontend online stats row should not have a visible frame');
check_ad_image_fit(strpos($front_source, "\$online_stats_text_color = \$online_stats_color === 'highlight' ? '#fff' : 'rgba(255,255,255,'.\$footer_alpha.')';") !== false, 'frontend online stats color mode is missing');
check_ad_image_fit(strpos($front_source, '.online-stats{flex:0 0 auto;margin-top:auto;padding:48px 0 0;color:<?php echo $online_stats_text_color; ?>;') !== false, 'frontend online stats text does not use the selected color');
check_ad_image_fit(strpos($front_source, '.online-stats-item b{margin:0 2px;color:<?php echo $online_stats_text_color; ?>;') !== false, 'frontend online stats figures do not use the selected color');
check_ad_image_fit(strpos($front_source, '.online-stats-item{display:grid;grid-template-columns:minmax(0,1fr) auto auto;') !== false, 'mobile online stats items do not keep labels, values, and units aligned');
check_ad_image_fit(strpos($front_source, '.online-stats-item-ip{grid-column:1/-1;grid-template-columns:auto auto;justify-content:center;padding-top:2px}') !== false, 'mobile online stats IP is not isolated into a full row');
check_ad_image_fit(strpos($front_source, '.online-stats-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:8px 12px;padding:0 6px;line-height:1.45;transform:none}') !== false, 'mobile online stats row still uses a visual-only offset');

$admin_source = file_get_contents(dirname(__DIR__).'/admin/ad.php');
$admin_art_css = file_get_contents(dirname(__DIR__).'/admin/art-detail-pages.css');
check_ad_image_fit(strpos($admin_source, 'prepareAdFile') !== false, 'client-side image resizing is missing');
check_ad_image_fit(strpos($admin_source, "data.append('position', position)") !== false, 'upload request does not send the ad position');
check_ad_image_fit(strpos($admin_source, '<select name="position"') === false, 'ad forms still allow customers to choose a conflicting display position');
check_ad_image_fit(strpos($admin_source, 'ad-position-select') === false, 'ad scripts still depend on the removed display-position selector');
check_ad_image_fit(strpos($admin_source, 'class="ad-position-input"') !== false, 'ad forms do not carry their parent region as a hidden position');
check_ad_image_fit(strpos($admin_source, 'data-slot-field') !== false, 'the four-way slot field is not scoped to the search-banner region');
check_ad_image_fit(strpos($admin_source, '四宫格位置') !== false, 'ad admin slot selector does not explain the four-grid position');
check_ad_image_fit(strpos($admin_source, 'data-default-slot') !== false, 'ad admin add form does not default to the first available search-banner slot');
check_ad_image_fit(strpos($admin_source, 'ad-slot-map') !== false, 'ad admin overview does not show the search-banner slot map');
check_ad_image_fit(strpos($admin_source, 'function ad_admin_single_region_limit') !== false, 'ad admin does not define per-region material limits');
check_ad_image_fit(strpos($admin_source, "return \$position === 'below_search' ? 4 : 1") !== false, 'ad admin does not limit side-floating regions to one material');
check_ad_image_fit(strpos($admin_source, 'ad_admin_enforce_single_side_ads') !== false, 'ad admin does not clean duplicate side-floating materials');
check_ad_image_fit(strpos($admin_source, "ad_admin_enforce_single_side_ads('pc_right')") !== false, 'ad admin does not enforce the single-material rule for the right floating region');
check_ad_image_fit(strpos($admin_source, '已达上限') !== false, 'ad admin does not disable adding after a side-floating region reaches its limit');
check_ad_image_fit(strpos($admin_source, "form.querySelector('.ad-position-input')") !== false, 'ad form behavior does not read the inherited region position');
check_ad_image_fit(strpos($admin_source, 'function setCollapseState') !== false, 'collapse state helper is missing');
check_ad_image_fit(strpos($admin_source, 'data-toggle="collapse"') !== false, 'collapse controls are missing');
check_ad_image_fit(strpos($admin_source, "target.classList.remove('collapsing')") !== false, 'collapse state does not clear a stuck transition class');
foreach(array('below_search', 'pc_left', 'pc_right') as $region_key){
    check_ad_image_fit(strpos($admin_source, 'data-region-band="'.$region_key.'"') !== false, 'advertising region '.$region_key.' is missing');
}
check_ad_image_fit(strpos($admin_source, 'id="region-<?php echo $position_key; ?>"') !== false, 'advertising region anchors do not inherit their region key');
check_ad_image_fit(strpos($admin_source, 'class="ad-workspace-heading"') !== false, 'advertising workspace heading is missing');
check_ad_image_fit(strpos($admin_source, 'class="ad-overview-performance"') !== false, 'per-region advertising performance summary is missing');
check_ad_image_fit(strpos($admin_art_css, 'body.qf-page-ad.qf-art-embedded .ad-overview-list') !== false, 'embedded ad overview layout override is missing');
check_ad_image_fit(strpos($admin_art_css, 'grid-template-columns: 1fr !important') !== false, 'embedded ad overview does not use a full-width stacked workspace');
check_ad_image_fit(strpos($admin_art_css, '.ad-workspace-heading') !== false, 'advertising workspace heading styles are missing');
check_ad_image_fit(strpos($admin_art_css, '.ad-overview-performance') !== false, 'advertising performance rail styles are missing');
check_ad_image_fit(strpos($admin_art_css, '.ad-overview-region[data-region-band="pc_left"]') !== false && strpos($admin_art_css, '.ad-overview-region[data-region-band="pc_right"]') !== false, 'left/right floating regions are not explicitly laid out');
check_ad_image_fit(strpos($admin_art_css, '@media (max-width: 720px)') !== false, 'embedded ad overview does not provide a compact mobile workspace');
check_ad_image_fit(strpos($admin_art_css, 'min-height: calc(100vh - 146px)') === false, 'embedded ad overview list is forced to viewport height and can hide side regions');
check_ad_image_fit(strpos($admin_art_css, 'calc((100vh - 146px) / 3)') === false, 'embedded ad overview regions are stretched instead of stacking naturally');
check_ad_image_fit(!preg_match('/body\.qf-page-ad\.qf-art-embedded\s+\.ad-overview-list\s*\{[^}]*overflow:\s*hidden\s*!important/s', $admin_art_css), 'embedded ad overview list clips lower advertising regions');
check_ad_image_fit(substr_count($admin_source, 'data-add-position="<?php echo $position_key; ?>"') === 1, 'advertising add controls do not inherit their region position');
check_ad_image_fit(substr_count($admin_source, 'data-inline-add-slot="<?php echo $position_key; ?>"') === 1, 'inline add slots do not inherit their region position');
check_ad_image_fit(strpos($admin_source, 'data-cancel-inline-add') !== false, 'inline add cancellation control is missing');
check_ad_image_fit(strpos($admin_source, 'data-manage-materials="<?php echo $position_key; ?>"') !== false, 'material management controls do not identify their owning region');
check_ad_image_fit(strpos($admin_source, 'function openInlineAdAdd') !== false, 'ad material add form cannot be opened consistently from a region');
check_ad_image_fit(strpos($admin_source, 'data-region-band="pc_left"') !== false && strpos($admin_source, 'data-region-band="pc_right"') !== false, 'side-floating material management is not wired to both regions');
check_ad_image_fit(strpos($admin_source, 'data-manage-materials') !== false && strpos($admin_source, "region.getAttribute('data-region-band') !== 'below_search'") !== false, 'empty side-floating material management does not open its add form');

if($failures){
    fwrite(STDERR, "Ad image fit tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Ad image fit tests passed.\n";
?>
