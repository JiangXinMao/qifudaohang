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

$banner_box = qifu_ad_image_box('below_search');
check_ad_image_fit($banner_box['width'] === 840 && $banner_box['height'] === 240, 'search-banner upload guidance does not match the 7:2 display ratio');
$side_box = qifu_ad_image_box('pc_right');
check_ad_image_fit($side_box['width'] === 560 && $side_box['height'] === 1240, 'side-ad upload guidance does not match the 14:31 display ratio');

$wide = qifu_ad_cover_dimensions(3000, 500, 'below_search');
check_ad_image_fit($wide['width'] === 840 && $wide['height'] === 240, 'wide banner output is not the exact 840x240 target');
check_ad_image_fit($wide['scaled_width'] === 1440 && $wide['scaled_height'] === 240 && $wide['offset_x'] === -300 && $wide['offset_y'] === 0, 'wide banner is not centered with cover cropping');
check_ad_image_fit($wide['resized'] && $wide['cropped'], 'wide banner does not report static-image normalization');

$tall_banner = qifu_ad_fit_dimensions(3000, 2000, 'below_search');
check_ad_image_fit($tall_banner['width'] === 840 && $tall_banner['height'] === 240, 'tall banner output is not the exact 840x240 target');
check_ad_image_fit($tall_banner['scaled_width'] === 840 && $tall_banner['scaled_height'] === 560 && $tall_banner['offset_y'] === -160, 'tall banner is not centered with cover cropping');

$side = qifu_ad_cover_dimensions(1000, 3000, 'pc_right');
check_ad_image_fit($side['width'] === 560 && $side['height'] === 1240, 'side ad output is not the exact 560x1240 target');
check_ad_image_fit($side['scaled_width'] === 560 && $side['scaled_height'] === 1680 && $side['offset_x'] === 0 && $side['offset_y'] === -220, 'side ad is not centered with cover cropping');

$small = qifu_ad_cover_dimensions(300, 100, 'below_search');
check_ad_image_fit($small['width'] === 840 && $small['height'] === 240 && $small['resized'] && $small['cropped'], 'small static images are not enlarged and cropped to the fixed banner canvas');

$gif_path = tempnam(sys_get_temp_dir(), 'qifu-ad-gif-');
file_put_contents($gif_path, base64_decode('R0lGODlhAQABAIABAP///wAAACwAAAAAAQABAAACAkQBADs='));
$gif_info = array();
check_ad_image_fit(qifu_ad_resize_saved_image($gif_path, 'below_search', $gif_info) === true, 'GIF upload handling rejected a valid image');
$gif_size = getimagesize($gif_path);
check_ad_image_fit(intval($gif_size[0]) === 1 && intval($gif_size[1]) === 1 && intval($gif_info['width']) === 1 && intval($gif_info['height']) === 1, 'GIF upload should preserve the original dimensions');
check_ad_image_fit(strpos($gif_info['message'], 'GIF') !== false, 'GIF upload does not explain that animation is preserved');
@unlink($gif_path);

if(!function_exists('imagecreatetruecolor')){
    $fallback_png = tempnam(sys_get_temp_dir(), 'qifu-ad-static-');
    file_put_contents($fallback_png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQImWNgYGBgAAAABQABpfZFQAAAAABJRU5ErkJggg=='));
    $fallback_info = array();
    check_ad_image_fit(qifu_ad_resize_saved_image($fallback_png, 'below_search', $fallback_info) === true, 'static uploads should remain usable when GD is unavailable');
    check_ad_image_fit(strpos($fallback_info['message'], '已保留原图') !== false, 'GD fallback does not explain frontend cover fitting');
    @unlink($fallback_png);
}

if(function_exists('imagecreatetruecolor') && function_exists('imagecreatefrompng') && function_exists('imagepng')){
    $png_path = tempnam(sys_get_temp_dir(), 'qifu-ad-png-');
    $png = imagecreatetruecolor(300, 100);
    imagealphablending($png, false);
    imagesavealpha($png, true);
    $transparent = imagecolorallocatealpha($png, 0, 0, 0, 127);
    imagefilledrectangle($png, 0, 0, 299, 99, $transparent);
    $red = imagecolorallocatealpha($png, 220, 38, 38, 0);
    imagefilledrectangle($png, 110, 25, 189, 74, $red);
    imagepng($png, $png_path);
    imagedestroy($png);

    $png_info = array();
    check_ad_image_fit(qifu_ad_resize_saved_image($png_path, 'below_search', $png_info) === true, 'PNG cover normalization failed');
    $png_size = getimagesize($png_path);
    check_ad_image_fit(intval($png_size[0]) === 840 && intval($png_size[1]) === 240, 'PNG cover normalization did not write the exact banner canvas');
    $processed = imagecreatefrompng($png_path);
    $corner = imagecolorat($processed, 0, 0);
    imagedestroy($processed);
    check_ad_image_fit((($corner >> 24) & 0x7f) >= 120, 'PNG cover normalization did not preserve transparency');
    @unlink($png_path);
}

$front_source = file_get_contents(dirname(__DIR__).'/index.php');
check_ad_image_fit(strpos($front_source, '.ad-img{display:block;width:100%;height:100%;max-width:100%;max-height:100%;object-fit:cover;object-position:center}') !== false, 'frontend ad images do not use cover fitting');
check_ad_image_fit(strpos($front_source, '.ad-cell{min-height:0;aspect-ratio:7/2;') !== false, 'search-banner cells do not use the 7:2 upload ratio');
check_ad_image_fit(strpos($front_source, '.ad-banner{width:100%;height:100%;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:0;') !== false, 'search-banner images still have an internal gap');
check_ad_image_fit(strpos($front_source, '.ad-side{') !== false && strpos($front_source, 'aspect-ratio:14/31') !== false, 'side ad container ratio is missing');
check_ad_image_fit(strpos($front_source, '.ad-side .ad-img{max-height:100%;object-fit:contain}') === false, 'side ad images still use contain fitting');
check_ad_image_fit(strpos($front_source, '@media(max-width:1440px){.ad-side{display:none}}') !== false, 'side ad is not hidden before it can overlap the content rail');
check_ad_image_fit(strpos($front_source, 'foreach($ad_below_items as $ad_slot_index => $ad_item)') !== false, 'frontend search banner does not render by deterministic slot index');
check_ad_image_fit(strpos($front_source, 'data-ad-slot="<?php echo $ad_slot; ?>"') !== false, 'frontend search banner cells do not expose their slot');
check_ad_image_fit(strpos($front_source, 'qifu_ad_slot_label($ad_slot)') !== false, 'frontend search banner does not use named four-grid slots');
check_ad_image_fit(strpos($front_source, '.online-stats-row{') !== false && strpos($front_source, '.online-stats-row{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:7px 9px;padding:11px 14px;border:0;background:transparent;') !== false, 'frontend online stats row should not have a visible frame');

$admin_controller = file_get_contents(dirname(__DIR__).'/admin/ad.php');
$admin_view = file_get_contents(dirname(__DIR__).'/admin/ad-ops-view.php');
$admin_source = $admin_controller."\n".$admin_view;

check_ad_image_fit(strpos($admin_controller, "require __DIR__ . '/ad-ops-view.php';") !== false, 'ad controller does not load the operations workspace view');
check_ad_image_fit(strpos($admin_controller, "'recommended' => '840 × 240 px'") !== false, 'search-banner upload guidance is not 840x240');
check_ad_image_fit(strpos($admin_controller, "'recommended' => '560 × 1240 px'") !== false, 'side-ad upload guidance is not 560x1240');
check_ad_image_fit(strpos($admin_controller, 'function ad_admin_single_region_limit') !== false, 'ad admin does not define per-region material limits');
check_ad_image_fit(strpos($admin_controller, "return \$position === 'below_search' ? 4 : 1") !== false, 'ad admin does not limit side-floating regions to one material');
check_ad_image_fit(strpos($admin_controller, 'function ad_admin_side_ad_conflict_count') !== false, 'ad admin does not identify duplicate historical side-floating materials');
check_ad_image_fit(strpos($admin_controller, 'ad_admin_enforce_single_side_ads') === false, 'ad admin still deletes duplicate side-floating materials automatically');
check_ad_image_fit(strpos($admin_controller, "saveSetting(\$mode_key, 'fixed')") !== false, 'ad admin does not keep the fixed-slot display mode');
check_ad_image_fit(strpos($admin_controller, "position='below_search' AND slot='{\$slot_sql}' AND id<>'{\$id_sql}'") !== false, 'ad admin does not reject duplicate search-banner slots');
check_ad_image_fit(strpos($admin_controller, '搜索栏下方的每个位置只能放置 1 个广告素材') !== false, 'duplicate search-banner slot validation has no actionable error');

check_ad_image_fit(strpos($admin_view, '$ad_ops_slots = array(1 => array(), 2 => array(), 3 => array(), 4 => array());') !== false, 'operations view does not create four deterministic search-banner slots');
check_ad_image_fit(strpos($admin_view, 'data-region-nav=') !== false && strpos($admin_view, 'data-region-panel=') !== false, 'operations view does not provide linked region navigation and panels');
check_ad_image_fit(strpos($admin_view, 'data-slot-button=') !== false && strpos($admin_view, 'data-slot-panel=') !== false, 'operations view does not provide linked four-slot navigation and panels');
check_ad_image_fit(strpos($admin_view, 'data-open-add') !== false && strpos($admin_view, 'data-add-mount') !== false, 'operations view cannot open an add form inside the selected region');
check_ad_image_fit(strpos($admin_view, 'id="adOpsAddForm"') !== false && strpos($admin_view, 'data-cancel-add') !== false, 'operations view has no cancellable shared add form');
check_ad_image_fit(strpos($admin_view, 'data-editor-drawer=') !== false && strpos($admin_view, 'data-edit-ad=') !== false, 'operations view has no inline material editor');
check_ad_image_fit(strpos($admin_view, 'form="adGlobalForm"') !== false && strpos($admin_view, 'data-region-toggle=') !== false, 'inactive region controls are not associated with the global save form');
check_ad_image_fit(strpos($admin_view, 'name="action" value="clear_cache"') !== false, 'operations workspace does not provide a cache refresh action');
check_ad_image_fit(strpos($admin_view, 'name="position" value="') !== false && strpos($admin_view, 'class="ad-position-input"') !== false, 'ad forms do not carry their parent region as a hidden position');
check_ad_image_fit(strpos($admin_view, 'name="slot" value="') !== false && strpos($admin_view, 'class="ad-slot-input"') !== false, 'ad forms do not keep the owning four-grid slot');
check_ad_image_fit(strpos($admin_view, '前台会按广告位居中裁切铺满') !== false, 'ad upload guidance does not explain cover fitting');
check_ad_image_fit(strpos($admin_view, 'aspect-ratio:14/31') !== false && strpos($admin_view, 'aspect-ratio:7/2') !== false, 'operations view previews do not use the real ad ratios');
check_ad_image_fit(strpos($admin_view, 'function fitAdDimensions') !== false && strpos($admin_view, 'function prepareAdFile') !== false, 'client-side image resizing is missing');
check_ad_image_fit(strpos($admin_view, 'var scale = Math.max(box.width / width, box.height / height);') !== false && strpos($admin_view, 'context.drawImage(image,fit.sourceX,fit.sourceY,fit.sourceWidth,fit.sourceHeight,0,0,fit.width,fit.height)') !== false, 'client-side image preparation does not center-crop to the component ratio');
check_ad_image_fit(strpos($admin_view, "data.append('position',position)") !== false, 'upload request does not send the ad position');
check_ad_image_fit(strpos($admin_view, 'qifu_csrf_input()') !== false, 'operations forms do not include CSRF fields');
check_ad_image_fit(strpos($admin_view, "window.confirm('确定删除这个广告吗？对应统计也会一起删除。')") !== false, 'advertising deletion no longer confirms that statistics are removed');
check_ad_image_fit(strpos($admin_view, '系统不会自动删除，请保留需要的素材后手动删除其余记录。') !== false, 'operations workspace does not explain manual duplicate-side-material review');
check_ad_image_fit(strpos($admin_view, 'isPreviewableImageUrl') !== false && strpos($admin_view, 'schedulePreview') !== false, 'operations workspace previews image URLs on every keystroke');
check_ad_image_fit(strpos($admin_view, '@media(prefers-reduced-motion:reduce)') !== false, 'operations workspace does not respect reduced-motion preferences');
check_ad_image_fit(strpos($admin_view, 'function setRegion') !== false && strpos($admin_view, 'function setSlot') !== false && strpos($admin_view, 'function openAdd') !== false, 'operations workspace navigation logic is incomplete');
check_ad_image_fit(strpos($admin_view, 'data-save-bar') !== false && strpos($admin_view, 'data-reset-global') !== false, 'global settings do not expose a recoverable dirty-save state');

if($failures){
    fwrite(STDERR, "Ad image fit tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Ad image fit tests passed.\n";
?>
