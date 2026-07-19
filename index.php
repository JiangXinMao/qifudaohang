<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */
include __DIR__ . "/includes/common.php";
include __DIR__ . "/includes/txprotect.php";
include __DIR__ . "/includes/site_status.php";
include_once __DIR__ . "/includes/online_stats.php";
include_once __DIR__ . "/includes/site_icon.php";

// 兼容所有 MySQL 版本的字段升级
function ensure_column($DB, $table, $col, $definition) {
    $rs = $DB->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
    if (!$DB->fetch($rs)) { $DB->query("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$definition}"); }
}
ensure_column($DB, "web_dh", "category",    "varchar(50)  NOT NULL DEFAULT '常用推荐'");
ensure_column($DB, "web_dh", "description", "varchar(255) NOT NULL DEFAULT ''");
ensure_column($DB, "web_dh", "desc_marquee", "tinyint(1) NOT NULL DEFAULT 0");
ensure_column($DB, "web_dh", "desc_speed",   "varchar(20) NOT NULL DEFAULT 'normal'");
ensure_column($DB, "web_dh", "desc_color",   "varchar(20) NOT NULL DEFAULT 'default'");
ensure_column($DB, "web_dh", "icon",        "varchar(20)  NOT NULL DEFAULT ''");
ensure_column($DB, "web_dh", "sort",        "int(11)      NOT NULL DEFAULT 100");
dh_site_status_ensure_columns();

// 从数据库读取分类设置，前台分类顺序和图标都以这里为准
$category_meta = [];
$cat_icons = [];
$cat_rs = $DB->query("SELECT id,name,icon,sort FROM web_category WHERE active=1 ORDER BY sort ASC,id ASC");
while ($cat_row = $DB->fetch($cat_rs)) {
    $category_meta[$cat_row['name']] = $cat_row;
    if (!empty($cat_row['icon'])) {
        $cat_icons[$cat_row['name']] = $cat_row['icon'];
    }
}

// 查询所有启用链接：优先按分类管理里的排序，再按站点排序
$rs = $DB->query("SELECT d.*,c.icon AS category_icon,c.sort AS category_sort,c.id AS category_id
    FROM web_dh d
    LEFT JOIN web_category c ON d.category=c.name
    WHERE d.active=1 AND (c.id IS NULL OR c.active=1)
    ORDER BY CASE WHEN c.sort IS NULL THEN 1 ELSE 0 END ASC,c.sort ASC,c.id ASC,d.category ASC,d.sort ASC,d.id ASC");
$sections = [];
$qifu_site_count = 0;
while ($res = $DB->fetch($rs)) {
    $cat = $res['category'] ?: '其他';
    if (!isset($sections[$cat])) {
        $sections[$cat] = [];
        if (!empty($res['category_icon'])) {
            $cat_icons[$cat] = $res['category_icon'];
        } elseif (isset($category_meta[$cat]) && !empty($category_meta[$cat]['icon'])) {
            $cat_icons[$cat] = $category_meta[$cat]['icon'];
        }
    }
    $sections[$cat][] = $res;
    $qifu_site_count++;
}

qifu_telemetry_track_daily('website_view', true, [
    'site_count' => $qifu_site_count,
    'ads_enabled' => isset($conf['ad_enabled']) && $conf['ad_enabled'] === '1' ? 1 : 0,
]);

// 获取分类列表（友链申请用）
$link_cats = $DB->get_results("SELECT name FROM web_category WHERE active=1 ORDER BY sort ASC");

// 背景模式
$bg_mode = isset($conf['bg_mode']) ? $conf['bg_mode'] : 'default';
$bg_custom = isset($conf['bg_custom']) ? $conf['bg_custom'] : '';
$default_bg = 'images/moren.jpg';
$default_bg_webp = 'images/moren.webp';

// UI设置
$card_size = isset($conf['card_size']) ? $conf['card_size'] : 'normal';
$columns = isset($conf['columns']) ? $conf['columns'] : 'auto';
$time_format = isset($conf['time_format']) ? $conf['time_format'] : '24';
$clock_style = isset($conf['clock_style']) ? $conf['clock_style'] : 'digital';
$announcement = isset($conf['announcement']) ? $conf['announcement'] : '';
$show_search = isset($conf['show_search']) ? $conf['show_search'] : '1';
$site_search_enabled = isset($conf['site_search_enabled']) && $conf['site_search_enabled'] === '1';
$show_clock = isset($conf['show_clock']) ? $conf['show_clock'] : '1';
$show_tags = isset($conf['show_tags']) ? $conf['show_tags'] : '1';
$quick_tags = qifu_quick_tags_from_config($conf);
$show_link_apply = isset($conf['show_link_apply']) ? $conf['show_link_apply'] : '1';
$bg_animation = isset($conf['bg_animation']) ? $conf['bg_animation'] : '1';
$card_animation = isset($conf['card_animation']) ? $conf['card_animation'] : '1';
$online_stats_enabled = !isset($conf['online_stats_enabled']) || $conf['online_stats_enabled'] !== '0';
$online_stats_mode = isset($conf['online_stats_mode']) && $conf['online_stats_mode'] === 'random' ? 'random' : 'real';
$online_stats_data = $online_stats_enabled ? qifu_online_stats_snapshot($online_stats_mode) : array();
$footer_opacity = isset($conf['footer_opacity']) ? intval($conf['footer_opacity']) : 25;
$footer_size = isset($conf['footer_size']) ? intval($conf['footer_size']) : 12;
$gongan_beian = isset($conf['gongan_beian']) ? trim((string)$conf['gongan_beian']) : '';
$gongan_beian_url = isset($conf['gongan_beian_url']) ? trim((string)$conf['gongan_beian_url']) : '';
if($gongan_beian_url !== '' && (!filter_var($gongan_beian_url, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $gongan_beian_url))) $gongan_beian_url = '';
if($gongan_beian !== '' && $gongan_beian_url === ''){
    $gongan_record_code = preg_replace('/[^0-9]/', '', $gongan_beian);
    $gongan_beian_url = $gongan_record_code !== ''
        ? 'https://beian.mps.gov.cn/#/query/webSearch?code='.rawurlencode($gongan_record_code)
        : 'https://beian.mps.gov.cn/';
}
$footer_opacity = max(5, min(100, $footer_opacity));
$footer_size = max(10, min(18, $footer_size));
$footer_alpha = round($footer_opacity / 100, 2);
$footer_link_alpha = min(1, round(($footer_opacity + 15) / 100, 2));
$online_stats_color = isset($conf['online_stats_color']) && $conf['online_stats_color'] === 'dark' ? 'dark' : 'highlight';
$online_stats_text_color = $online_stats_color === 'highlight' ? '#fff' : 'rgba(255,255,255,'.$footer_alpha.')';

// 音乐设置
$bg_music = isset($conf['bg_music']) ? $conf['bg_music'] : '';
$bg_music_volume = isset($conf['bg_music_volume']) ? $conf['bg_music_volume'] : '50';

// Ping延迟设置
$ping_enabled = isset($conf['ping_enabled']) ? $conf['ping_enabled'] : '0';
$ping_alert_latency = isset($conf['ping_alert_latency']) ? intval($conf['ping_alert_latency']) : 3000;
$ping_alert_latency = max(500, min(30000, $ping_alert_latency));
$ping_last_run = isset($conf['ping_last_run']) ? $conf['ping_last_run'] : '';
$ping_need_refresh = $ping_enabled == '1' && $ping_last_run !== date('Y-m-d');

function qifu_front_legacy_side_ad($conf, $side) {
    $old = $side === 'right' ? 'right' : 'left';
    $image_key = 'ad_'.$old.'_image';
    if (empty($conf[$image_key])) return null;
    return array(
        'id' => 0,
        'position' => $side === 'right' ? 'pc_right' : 'pc_left',
        'slot' => 1,
        'title' => isset($conf['ad_'.$old.'_title']) ? $conf['ad_'.$old.'_title'] : '',
        'image' => $conf[$image_key],
        'link' => isset($conf['ad_'.$old.'_link']) ? $conf['ad_'.$old.'_link'] : '',
        'alt' => isset($conf['ad_'.$old.'_alt']) ? $conf['ad_'.$old.'_alt'] : '',
    );
}

// 广告设置
$ad_enabled = isset($conf['ad_enabled']) ? $conf['ad_enabled'] : '0';
$ad_show_below = isset($conf['ad_show_below']) ? $conf['ad_show_below'] : '1';
$ad_show_right = isset($conf['ad_show_right']) ? $conf['ad_show_right'] : '0';
$ad_show_left = isset($conf['ad_show_left']) ? $conf['ad_show_left'] : '0';
$ad_new_window = isset($conf['ad_new_window']) ? $conf['ad_new_window'] : '1';
$ad_target = $ad_new_window == '1' ? ' target="_blank" rel="noopener"' : '';
$ad_groups = qifu_ad_front_groups();
$ad_below_mode = isset($conf['ad_mode_below_search']) ? $conf['ad_mode_below_search'] : 'fixed';
$ad_right_mode = isset($conf['ad_mode_pc_right']) ? $conf['ad_mode_pc_right'] : 'fixed';
$ad_left_mode = isset($conf['ad_mode_pc_left']) ? $conf['ad_mode_pc_left'] : 'fixed';
$ad_below_items = array();
$ad_below_has_items = false;
for($i=1; $i<=4; $i++){
    $picked_ad = qifu_ad_pick($ad_groups['below_search'][$i], $ad_below_mode);
    if(!empty($picked_ad)) $ad_below_has_items = true;
    $ad_below_items[] = $picked_ad;
}
$ad_right = qifu_ad_pick($ad_groups['pc_right'][1], $ad_right_mode);
$ad_left = qifu_ad_pick($ad_groups['pc_left'][1], $ad_left_mode);
if(empty($ad_right)) $ad_right = qifu_front_legacy_side_ad($conf, 'right');
if(empty($ad_left)) $ad_left = qifu_front_legacy_side_ad($conf, 'left');
$ad_below_show = $ad_enabled == '1' && $ad_show_below == '1' && $ad_below_has_items;
$ad_right_show = $ad_enabled == '1' && $ad_show_right == '1' && !empty($ad_right);
$ad_left_show = $ad_enabled == '1' && $ad_show_left == '1' && !empty($ad_left);

// 卡片尺寸CSS
$card_size_css = [
    'small' => 'padding:10px 12px;gap:8px;',
    'normal' => 'padding:15px 17px;gap:14px;',
    'large' => 'padding:20px 22px;gap:18px;'
];
$card_size_css_default = isset($card_size_css[$card_size]) ? $card_size_css[$card_size] : $card_size_css['normal'];

// 网格列数CSS
$columns_css = [
    '2' => 'repeat(2, 1fr)',
    '3' => 'repeat(3, 1fr)',
    '4' => 'repeat(4, 1fr)',
    'auto' => 'repeat(auto-fill, minmax(200px, 1fr))'
];
$columns_css_default = isset($columns_css[$columns]) ? $columns_css[$columns] : $columns_css['auto'];

function qifu_http_get($url, $timeout = 6) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $body = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        if ($body !== false && ($code == 0 || ($code >= 200 && $code < 400))) {
            return $body;
        }
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body !== false) {
            return $body;
        }
    }

    return '';
}

function qifu_url_add_param($url, $key, $value) {
    $join = strpos($url, '?') === false ? '?' : '&';
    return $url . $join . rawurlencode($key) . '=' . rawurlencode($value);
}

function qifu_full_bing_url($url) {
    if (!$url) {
        return '';
    }
    if (strpos($url, '//') === 0) {
        return 'https:' . $url;
    }
    if (strpos($url, '/') === 0) {
        return 'https://www.bing.com' . $url;
    }
    return $url;
}

function qifu_today_bing_wallpaper() {
    $today = date('Ymd');
    $api = 'https://www.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1&mkt=zh-CN&uhd=1&uhdwidth=1920&uhdheight=1080';
    $json = qifu_http_get(qifu_url_add_param($api, 'qifu_day', $today));
    $bing_url = '';

    if ($json) {
        $data = json_decode($json, true);
        if (!empty($data['images'][0]['url'])) {
            $bing_url = $data['images'][0]['url'];
        } elseif (!empty($data['images'][0]['urlbase'])) {
            $bing_url = $data['images'][0]['urlbase'] . '_1920x1080.jpg';
        }
    }

    if (!$bing_url) {
        $xml_api = 'https://www.bing.com/HPImageArchive.aspx?idx=0&n=1&mkt=zh-CN';
        $xml = qifu_http_get(qifu_url_add_param($xml_api, 'qifu_day', $today));
        if ($xml && preg_match('/<url>(.*?)<\/url>/', $xml, $m)) {
            $bing_url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        } elseif ($xml && preg_match('/<urlBase>(.*?)<\/urlBase>/', $xml, $m)) {
            $bing_url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') . '_1920x1080.jpg';
        }
    }

    $bing_url = qifu_full_bing_url($bing_url);
    return $bing_url ? qifu_url_add_param($bing_url, 'qifu_bing', $today) : '';
}

if ($bg_mode == 'bing') {
    $bing_url = qifu_today_bing_wallpaper();
    $bing_url = str_replace("'", "%27", $bing_url);
    $bg_style = $bing_url ? "background:url('{$bing_url}') center/cover no-repeat;" : "background-image:url('{$default_bg}');background-image:image-set(url('{$default_bg_webp}') type('image/webp'), url('{$default_bg}') type('image/jpeg'));background-position:center;background-size:cover;background-repeat:no-repeat;";
} elseif ($bg_mode == 'custom' && $bg_custom) {
    $bg_style = "background:url('{$bg_custom}') center/cover no-repeat;";
} else {
    $bg_style = "background-image:url('{$default_bg}');background-image:image-set(url('{$default_bg_webp}') type('image/webp'), url('{$default_bg}') type('image/jpeg'));background-position:center;background-size:cover;background-repeat:no-repeat;";
}

$site_title = !empty($conf['sitename']) ? $conf['sitename'] : '祈福导航系统';
$page_title = $site_title.(!empty($conf['title']) ? ' - '.$conf['title'] : '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="dns-prefetch" href="//favicon.im">
<link rel="preconnect" href="https://favicon.im" crossorigin>
<link rel="dns-prefetch" href="//www.google.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500&display=swap" rel="stylesheet">
<title><?php echo htmlspecialchars($page_title); ?></title>
<meta name="keywords"    content="<?php echo htmlspecialchars(isset($conf['keywords']) ? (string)$conf['keywords'] : '', ENT_QUOTES, 'UTF-8'); ?>">
<meta name="description" content="<?php echo htmlspecialchars($conf['description']); ?>">
<link rel="shortcut icon" href="favicon.ico">
<?php if($bg_mode=='default'){ ?>
<link rel="preload" as="image" href="<?php echo htmlspecialchars($default_bg_webp); ?>" type="image/webp" fetchpriority="high">
<?php } ?>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html{min-height:100%;-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{font-family:'Noto Sans SC',sans-serif;min-height:100vh;min-height:100dvh;overflow-x:hidden;}
.bg{position:fixed;inset:0;z-index:0;overflow:hidden}
.bg::before{content:"";position:absolute;inset:-4%;<?php echo $bg_style; ?><?php echo $bg_animation=='1'?"animation:drift 30s ease-in-out infinite alternate;":""; ?>}
@keyframes drift{0%{transform:scale(1.06) translate(0,0)}100%{transform:scale(1.1) translate(-1.5%,-1%)}}
.overlay{position:fixed;inset:0;z-index:1;background:linear-gradient(145deg,rgba(8,18,50,.52),rgba(12,25,65,.4) 50%,rgba(5,12,40,.56));}
.wrap{position:relative;z-index:10;display:flex;min-height:100vh;min-height:100dvh;max-width:1020px;margin:0 auto;padding:0 24px;flex-direction:column}
.bar{display:flex;align-items:center;justify-content:flex-end;padding:10px 0 0}
.logo{font-size:1.4rem;font-weight:300;color:#e8eeff;letter-spacing:.06em;text-shadow:0 2px 20px rgba(0,0,0,.4)}
.logo b{font-weight:600;color:rgba(160,200,255,.95)}
.bar-actions{display:flex;gap:10px;align-items:center}
.bar-btn{padding:8px 18px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:50px;color:rgba(255,255,255,.82);font-size:.78rem;cursor:pointer;transition:.25s;text-decoration:none;font-weight:500}
.bar-btn:hover{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.32);box-shadow:0 4px 24px rgba(0,0,0,.2)}
.hero{text-align:center;padding:18px 0 30px}
#clock{font-size:clamp(3.4rem,13vw,6.5rem);font-weight:200;letter-spacing:.06em;color:#fff;line-height:1;text-shadow:0 4px 40px rgba(0,0,0,.5)}
#date{font-size:.9rem;color:rgba(255,255,255,.5);letter-spacing:.22em;margin-top:8px;margin-bottom:24px;font-weight:400}
.search{display:flex;max-width:660px;margin:0 auto;background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.22);border-radius:50px;overflow:visible;box-shadow:0 16px 56px rgba(0,0,0,.3),inset 0 1px 0 rgba(255,255,255,.1);transition:.3s}
.search:focus-within{background:rgba(0,0,0,.4);border-color:rgba(255,255,255,.38);box-shadow:0 20px 60px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.15)}
.eng{position:relative;display:flex;align-items:center;padding:0 8px;border-right:1px solid rgba(255,255,255,.16);flex-shrink:0}
.engine-current{min-width:86px;height:42px;border:0;border-radius:999px;background:transparent;color:rgba(255,255,255,.86);display:flex;align-items:center;gap:8px;justify-content:center;padding:0 10px;font-family:inherit;font-size:.85rem;font-weight:500;cursor:pointer;box-shadow:none;transition:.22s}
.engine-current:hover,.engine-picker.open .engine-current{background:rgba(255,255,255,.08);box-shadow:none;color:#fff}
.engine-current .engine-badge{display:none}
.engine-badge{width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,.18);font-size:.72rem;font-weight:700;line-height:1}
.engine-arrow{width:7px;height:7px;border-right:1.5px solid rgba(255,255,255,.75);border-bottom:1.5px solid rgba(255,255,255,.75);transform:rotate(45deg);margin-top:-4px;transition:.2s}
.engine-picker.open .engine-arrow{transform:rotate(225deg);margin-top:4px}
.engine-menu{position:absolute;left:8px;top:calc(100% + 10px);width:156px;padding:8px;border-radius:16px;background:rgba(10,16,45,.92);border:1px solid rgba(255,255,255,.18);box-shadow:0 18px 55px rgba(0,0,0,.45),inset 0 1px 0 rgba(255,255,255,.08);backdrop-filter:blur(16px);opacity:0;transform:translateY(-8px) scale(.98);pointer-events:none;transition:.2s;z-index:30}
.engine-picker.open .engine-menu{opacity:1;transform:translateY(0) scale(1);pointer-events:auto}
.engine-option{width:100%;border:0;background:transparent;color:rgba(255,255,255,.78);display:flex;align-items:center;gap:10px;padding:10px 11px;border-radius:11px;font-family:inherit;font-size:.86rem;cursor:pointer;text-align:left;transition:.18s}
.engine-option:hover,.engine-option.active{background:rgba(255,255,255,.13);color:#fff}
.engine-option .engine-badge{background:rgba(99,102,241,.28)}
.sinp{flex:1;background:transparent;border:0;outline:0;padding:15px 20px;color:#fff;font-size:.95rem;font-family:inherit;min-width:0;font-weight:400}
.sinp::placeholder{color:rgba(255,255,255,.4)}
.sbtn{padding:0 28px;background:rgba(255,255,255,.15);border:0;border-left:1px solid rgba(255,255,255,.16);color:#fff;font-size:1.3rem;cursor:pointer;transition:.3s;height:100%;display:flex;align-items:center;border-radius:0 50px 50px 0;line-height:1}
.sbtn:hover{background:rgba(255,255,255,.28)}
.tags{display:flex;justify-content:center;flex-wrap:wrap;gap:10px;margin-top:12px}
.tag{padding:7px 18px;background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.16);border-radius:50px;font-size:.75rem;color:rgba(255,255,255,.7);cursor:pointer;text-decoration:none;transition:.25s;font-weight:500}
.tag:hover{background:rgba(0,0,0,.5);border-color:rgba(255,255,255,.3);color:#fff;box-shadow:0 6px 24px rgba(0,0,0,.3)}
.site-search-state{display:none;max-width:660px;margin:16px auto 0;padding:10px 14px;border-top:1px solid rgba(255,255,255,.14);border-bottom:1px solid rgba(255,255,255,.08);color:rgba(255,255,255,.68);font-size:.8rem;text-align:center;letter-spacing:.03em}
.site-search-state.show{display:block}
.site-search-state.empty{color:rgba(255,210,120,.82)}
.ad-link{display:block;text-decoration:none;color:inherit}
.ad-img{display:block;width:100%;height:100%;max-width:100%;max-height:100%;object-fit:contain;object-position:center}
.ad-grid{max-width:900px;margin:16px auto 0;display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
.ad-cell{height:92px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:linear-gradient(135deg,rgba(7,18,46,.34),rgba(31,54,92,.24));border:1px solid rgba(255,255,255,.24);border-radius:18px;box-shadow:0 14px 42px rgba(0,0,0,.2),inset 0 1px 0 rgba(255,255,255,.12)}
.ad-cell-empty{display:block;pointer-events:none}
.ad-banner{width:100%;height:100%;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:4px;transition:.25s;border-radius:18px;background:rgba(5,14,38,.2)}
.ad-banner:hover{transform:scale(1.015);filter:brightness(1.08)}
.ad-banner .ad-img{height:100%}
.ad-side{position:fixed;top:50%;z-index:120;width:168px;aspect-ratio:3/4;display:flex;align-items:center;justify-content:center;padding:4px;border-radius:18px;overflow:hidden;background:linear-gradient(145deg,rgba(5,14,38,.56),rgba(38,61,98,.42));border:1px solid rgba(255,255,255,.18);box-shadow:0 16px 48px rgba(0,0,0,.32),inset 0 1px 0 rgba(255,255,255,.12);transition:.25s;transform:translateY(-50%)}
.ad-side-right{right:28px}
.ad-side-left{left:28px}
.ad-side:hover{transform:translateY(calc(-50% - 3px));border-color:rgba(255,255,255,.32);box-shadow:0 22px 58px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.18)}
.ad-side .ad-img{max-height:100%;object-fit:contain}
.sec{margin-top:26px}
.sec-hd{display:flex;align-items:center;gap:10px;margin-bottom:16px}
.dot{width:9px;height:9px;border-radius:50%;background:rgba(140,190,255,.9);box-shadow:0 0 16px rgba(100,160,255,.5),0 0 32px rgba(100,160,255,.2);flex-shrink:0;animation:pulse 3s ease-in-out infinite}
@keyframes pulse{0%,100%{box-shadow:0 0 16px rgba(100,160,255,.5),0 0 32px rgba(100,160,255,.2)}50%{box-shadow:0 0 22px rgba(100,160,255,.7),0 0 44px rgba(100,160,255,.35)}}
.sec-title{font-size:.92rem;font-weight:600;color:rgba(255,255,255,.92);letter-spacing:.1em}
.sec-line{flex:1;height:1px;background:linear-gradient(to right,rgba(255,255,255,.15),transparent)}
.grid{display:grid;grid-template-columns:<?php echo $columns_css_default; ?>;gap:14px}
.card{display:flex;align-items:center;<?php echo $card_size_css_default; ?>background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.18);border-radius:18px;text-decoration:none;color:#fff;cursor:pointer;position:relative;overflow:hidden;<?php echo $card_animation=='1'?"transition:all .3s cubic-bezier(.4,0,.2,1);":""; ?>box-shadow:0 10px 40px rgba(0,0,0,.2),inset 0 1px 0 rgba(255,255,255,.12)}
.card::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.08),transparent 55%);opacity:1;transition:.25s;border-radius:18px;pointer-events:none}
.card:hover{background:rgba(0,0,0,.5);border-color:rgba(255,255,255,.3);transform:translateY(-5px);box-shadow:0 20px 56px rgba(0,0,0,.35),inset 0 1px 0 rgba(255,255,255,.2)}
.card:hover::before{opacity:.5}
.ico{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;overflow:hidden}
.ico img{width:100%;height:100%;object-fit:contain;border-radius:8px}
.site-icon-wrap{position:relative;isolation:isolate}
.site-icon-fallback{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.96);font-weight:700;line-height:1;text-shadow:0 1px 8px rgba(0,0,0,.38)}
.ico .site-favicon{position:absolute;inset:4px;width:calc(100% - 8px);height:calc(100% - 8px);padding:3px;object-fit:contain;border-radius:9px;background:rgba(255,255,255,.94);box-shadow:0 2px 10px rgba(0,0,0,.18);opacity:0;transform:scale(.9);transition:opacity .12s ease,transform .12s ease;z-index:1}
.ico .site-favicon.is-loaded{opacity:1;transform:scale(1)}
.ico .site-favicon.is-failed{display:none}
.inf{overflow:hidden;flex:1;min-width:0}
.nm{font-size:1rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#fff}
.ds{font-size:13px;color:rgba(255,255,255,.45);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:3px;font-weight:400;line-height:18px;height:18px;max-width:100%;-webkit-text-size-adjust:100%;text-size-adjust:100%}
.ds .ds-text{display:inline-block;max-width:100%;vertical-align:top;font-size:inherit;line-height:inherit}
.ds:not(.marquee) .ds-text{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ds.marquee{text-overflow:clip}
.ds.marquee .ds-text{max-width:none;min-width:100%;padding-left:100%;animation:descMarquee var(--desc-speed,10s) linear infinite}
.card:hover .ds.marquee .ds-text{animation-play-state:paused}
@keyframes descMarquee{0%{transform:translateX(0)}100%{transform:translateX(-100%)}}
.ds.desc-color-red{color:#ff6b6b}.ds.desc-color-orange{color:#ff9f43}.ds.desc-color-yellow{color:#ffd166}.ds.desc-color-green{color:#62f29a}.ds.desc-color-cyan{color:#5ee6ff}.ds.desc-color-blue{color:#8ab4ff}.ds.desc-color-purple{color:#c4a7ff}
.ds.desc-color-rainbow{color:#fff;text-shadow:none}.ds.desc-color-rainbow .ds-text{background:linear-gradient(90deg,#ff5f6d,#ffc371,#5df1b0,#58d8ff,#8ab4ff,#c084fc,#ff7a9e);-webkit-background-clip:text;background-clip:text;color:transparent}
.online-stats{flex:0 0 auto;margin-top:auto;padding:48px 0 0;color:<?php echo $online_stats_text_color; ?>;font-size:12px;letter-spacing:.03em}
.online-stats-row{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:7px 9px;padding:11px 14px;border:0;background:transparent;font-variant-numeric:tabular-nums;transform:translateY(20px)}
.online-stats-item{display:inline-flex;align-items:center;white-space:nowrap}
.online-stats-label{white-space:nowrap}
.online-stats-item b{margin:0 2px;color:<?php echo $online_stats_text_color; ?>;font-weight:600}
.online-stats-unit{white-space:nowrap}
.online-stats-sep{color:<?php echo $online_stats_text_color; ?>}
.online-stats-item-ip{max-width:100%;white-space:normal;overflow-wrap:anywhere}
.foot{flex:0 0 auto;margin-top:0;padding:24px 0 20px;background:transparent;text-align:center;font-size:<?php echo $footer_size; ?>px;color:rgba(255,255,255,<?php echo $footer_alpha; ?>);letter-spacing:.12em;font-weight:400}
.foot.foot-alone{margin-top:auto;padding-top:52px}
.foot-row{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:7px 9px;min-height:20px}
.foot-item,.foot a{display:inline-flex;align-items:center;min-width:0;color:inherit}
.foot a{color:rgba(255,255,255,<?php echo $footer_link_alpha; ?>)!important;text-decoration:none}
.foot a:hover{color:rgba(255,255,255,.78)!important}
.foot-sep{opacity:.62}
.foot-public-security{gap:5px;letter-spacing:.05em;white-space:nowrap}
.foot-public-security-icon{display:inline-block;width:13px;height:15px;flex:0 0 13px;background:currentColor;clip-path:polygon(50% 0,92% 17%,84% 69%,50% 100%,16% 69%,8% 17%);opacity:.82}
#clock.simple{font-size:clamp(2rem,10vw,4rem);font-weight:300;letter-spacing:.08em}
.music-btn{position:fixed;bottom:28px;left:28px;z-index:100;width:46px;height:46px;border-radius:50%;background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.2);color:#fff;font-size:1rem;cursor:pointer;transition:.25s;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 30px rgba(0,0,0,.25)}
.music-btn:hover{background:rgba(255,255,255,.22);transform:scale(1.12)}
.music-panel{position:fixed;bottom:82px;left:28px;z-index:100;background:rgba(0,0,0,.6);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.15);border-radius:16px;padding:16px 20px;display:none;min-width:200px}
.music-panel.active{display:block}
.music-panel .vol{display:flex;align-items:center;gap:10px;margin-top:8px}
.music-panel .vol input{flex:1;accent-color:#8ab4ff}
.music-panel .vol span{font-size:.7rem;color:rgba(255,255,255,.6);min-width:30px}
.ping-badge{position:absolute;top:10px;right:10px;z-index:2;display:block;width:11px;height:11px;padding:0;border-radius:50%;border:1px solid rgba(255,255,255,.72);background:rgba(148,163,184,.88);box-shadow:0 0 0 3px rgba(148,163,184,.16),0 0 14px rgba(148,163,184,.45)}
.ping-badge.checking{display:block;animation:pingBlink 1.1s ease-in-out infinite}
.ping-badge.online{display:block;background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.18),0 0 16px rgba(34,197,94,.78)}
.ping-badge.offline{display:block;background:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.18),0 0 16px rgba(239,68,68,.76)}
@keyframes pingBlink{0%,100%{opacity:.52}50%{opacity:1}}
@media(max-width:760px){
  body{width:100%;overflow-x:hidden}
  .wrap{width:100%;max-width:none;padding:0 14px}
  .bar{justify-content:flex-end;padding-top:8px}
  .bar-actions{flex-wrap:wrap;justify-content:flex-end;gap:8px}
  .bar-btn{padding:7px 12px;font-size:.72rem;line-height:1.2}
  .hero{padding:10px 0 22px}
  #clock{font-size:3.2rem;letter-spacing:0}
  #clock.simple{font-size:2.4rem;letter-spacing:0}
  #date{margin-top:7px;margin-bottom:18px;font-size:.78rem;letter-spacing:.1em}
  .search{max-width:none;border-radius:18px}
  .search form{min-width:0}
  .eng{padding:0 4px}
  .engine-current{min-width:66px;height:42px;padding:0 8px;gap:5px;font-size:.78rem}
  .engine-menu{left:0;width:136px;border-radius:14px}
  .engine-option{padding:9px 10px;font-size:.8rem}
  .sinp{padding:13px 10px;font-size:.88rem}
  .sbtn{width:48px;flex:0 0 48px;justify-content:center;padding:0;font-size:1.1rem}
  .tags{justify-content:flex-start;flex-wrap:nowrap;gap:8px;overflow-x:auto;padding-bottom:3px;-webkit-overflow-scrolling:touch}
  .tag{flex:0 0 auto;padding:7px 12px;white-space:nowrap}
  .ad-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:12px}
  .ad-cell{height:72px;border-radius:13px}
  .ad-banner{border-radius:13px}
  .sec{margin-top:20px}
  .sec-hd{gap:8px;margin-bottom:10px}
  .sec-title{font-size:.86rem;letter-spacing:.04em}
  .grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
  .card{min-width:0;padding:11px 10px;gap:9px;border-radius:14px}
  .card::before{border-radius:14px}
  .card:hover{transform:none}
  .ico{width:34px;height:34px;border-radius:10px;font-size:1rem}
  .ico img{border-radius:7px}
  .ico .site-favicon{inset:3px;width:calc(100% - 6px);height:calc(100% - 6px);padding:2px;border-radius:7px}
  .nm{font-size:.84rem}
  .ds{font-size:11px;line-height:15px;height:15px}
  .foot{padding:38px 6px 16px;line-height:1.8;letter-spacing:.04em}
  .foot:not(.foot-alone){padding-top:18px}
  .online-stats{padding-top:32px;font-size:11px;letter-spacing:.02em}
  .online-stats-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:8px 12px;padding:0 6px;line-height:1.45;transform:none}
  .online-stats-item{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:baseline;justify-content:stretch;min-width:0;white-space:nowrap;text-align:left}
  .online-stats-label{overflow:hidden;text-overflow:ellipsis}
  .online-stats-item b{margin:0 3px;font-size:12px;font-weight:700}
  .online-stats-item-ip{grid-column:1/-1;grid-template-columns:auto auto;justify-content:center;padding-top:2px}
  .online-stats-sep{display:none}
  .foot-row{gap:3px 7px}
  .foot-item,.foot a{max-width:100%;white-space:normal;overflow-wrap:anywhere;text-align:center}
  .music-btn{left:14px;bottom:14px;width:42px;height:42px}
  .music-btn:hover{transform:none}
  .music-panel{left:14px;right:14px;bottom:66px;min-width:0;padding:14px 16px}
  .ping-badge{top:7px;right:7px;width:8px;height:8px;box-shadow:0 0 0 2px rgba(148,163,184,.16),0 0 10px rgba(148,163,184,.42)}
  .ping-badge.online{box-shadow:0 0 0 2px rgba(34,197,94,.18),0 0 12px rgba(34,197,94,.75)}
  .ping-badge.offline{box-shadow:0 0 0 2px rgba(239,68,68,.18),0 0 12px rgba(239,68,68,.72)}
}
@media(max-width:380px){
  .wrap{padding-left:10px;padding-right:10px}
  #clock{font-size:2.8rem}
  #clock.simple{font-size:2rem}
  .engine-current{min-width:48px;padding:0 7px}
  .engine-current .engine-badge{display:inline-flex}
  #engineLabel{display:none}
  .sinp{padding-left:8px;padding-right:8px;font-size:.84rem}
  .sbtn{width:44px;flex-basis:44px}
  .grid{gap:8px}
  .card{padding:10px 8px;gap:8px}
  .ico{width:32px;height:32px}
  .ad-grid{grid-template-columns:1fr}
  .ad-cell{height:78px}
  .online-stats-row{grid-template-columns:1fr;gap:7px;padding:0 8px}
  .online-stats-item{grid-template-columns:auto auto auto;justify-content:center}
  .online-stats-item-ip{grid-column:auto;grid-template-columns:auto auto}
}
@media(max-width:1500px){.ad-side{width:136px}.ad-side-right{right:16px}.ad-side-left{left:16px}}
@media(max-width:1280px){.ad-side{width:92px;border-radius:14px}.ad-side-right{right:10px}.ad-side-left{left:10px}.ad-side .ad-img{max-height:220px}}
@media(max-width:980px){.ad-side{display:none}}

/* 友链申请：网址优先，自动获取公开站点信息。 */
#lkm-wrap{position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;padding:24px;background:rgba(2,7,12,.76);backdrop-filter:blur(8px)}
#lkm-wrap.open{display:flex;animation:lkmFadeIn .18s ease-out}
@keyframes lkmFadeIn{from{opacity:0}to{opacity:1}}
.lkm-box{width:min(560px,100%);max-height:calc(100dvh - 48px);overflow:auto;border-radius:10px;background:#0e1721;box-shadow:0 8px 24px rgba(0,0,0,.5);animation:lkmScaleIn .2s cubic-bezier(.22,1,.36,1)}
@keyframes lkmScaleIn{from{opacity:0;transform:translateY(10px) scale(.98)}to{opacity:1;transform:none}}
.lkm-hd{display:flex;align-items:center;gap:12px;padding:17px 20px;border-bottom:1px solid rgba(255,255,255,.12);background:#101c28}
.lkm-head-icon{display:grid;place-items:center;width:34px;height:34px;flex:0 0 34px;border-radius:7px;background:rgba(91,120,239,.16);color:#b7c3ff;font-size:18px;font-weight:700}
.lkm-title{min-width:0}.lkm-hd h3{margin:0;color:#fff;font-size:17px;font-weight:650;line-height:1.35;letter-spacing:0}.lkm-title p{margin:3px 0 0;color:#93a2b5;font-size:11px;line-height:1.5}
.lkm-close{display:grid;place-items:center;width:36px;height:36px;margin-left:auto;padding:0;border:0;border-radius:6px;background:transparent;color:#91a0b4;font-size:24px;line-height:1;cursor:pointer;transition:background-color .18s,color .18s}
.lkm-close:hover,.lkm-close:focus-visible{outline:0;background:rgba(255,255,255,.07);color:#fff}
.lkm-bd{padding:0}
.lkm-url-priority{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.12);background:#111d2a}
.lkm-auto-badge{display:inline-flex;align-items:center;margin-left:7px;padding:2px 6px;border-radius:4px;background:rgba(66,201,138,.12);color:#65dba2;font-size:9px;font-weight:650;vertical-align:1px}
.lkm-url-line{display:grid;grid-template-columns:minmax(0,1fr) 98px;gap:8px}
.lkm-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:17px 20px}
.lkm-row{min-width:0}.lkm-row-wide{grid-column:1/-1}.lkm-row label{display:block;margin-bottom:6px;color:#d9e0e9;font-size:12px;font-weight:650;line-height:1.4}
.lkm-optional{color:#7f8da0;font-size:10px;font-weight:500}
.lkm-row input,.lkm-row select,.lkm-row textarea{box-sizing:border-box;width:100%;height:44px;padding:0 12px;border:1px solid rgba(255,255,255,.16);border-radius:7px;outline:0;background:#0b131d;color:#fff;font-family:inherit;font-size:13px;line-height:1.45;transition:border-color .18s,box-shadow .18s,background-color .18s}
.lkm-row textarea{height:78px;padding:10px 12px;resize:none}
.lkm-row input:focus,.lkm-row select:focus,.lkm-row textarea:focus{border-color:#7890f5;box-shadow:0 0 0 3px rgba(91,120,239,.15)}
.lkm-row input::placeholder,.lkm-row textarea::placeholder{color:#718095;opacity:1}.lkm-row select option{background:#111b27}
.lkm-fetch{height:44px;padding:0 12px;border:1px solid #4e69d4;border-radius:7px;background:#172750;color:#dfe6ff;font-size:12px;font-weight:650;cursor:pointer;transition:background-color .18s,border-color .18s}
.lkm-fetch:hover,.lkm-fetch:focus-visible{outline:0;border-color:#6f87e8;background:#1c3062}.lkm-fetch:disabled{cursor:wait;opacity:.65}
.lkm-meta-status{display:flex;align-items:center;gap:6px;min-height:18px;margin-top:6px;color:#8ea3bb;font-size:10px;line-height:18px}
.lkm-meta-status:not(:empty)::before{content:"";width:6px;height:6px;flex:0 0 6px;border-radius:50%;background:currentColor}.lkm-meta-status.loading{color:#aebcff}.lkm-meta-status.loading::before{animation:lkmPulse 1s ease-in-out infinite}.lkm-meta-status.success{color:#55d89b}.lkm-meta-status.error{color:#ffc26a}
@keyframes lkmPulse{50%{opacity:.3}}
.lkm-tip{display:none;margin:0 20px 14px;padding:10px 12px;border:1px solid transparent;border-radius:7px;font-size:12px;text-align:center;font-weight:600}
.lkm-tip.suc{border-color:rgba(34,197,94,.38);background:rgba(34,197,94,.12);color:#55d89b}.lkm-tip.err{border-color:rgba(239,68,68,.38);background:rgba(239,68,68,.12);color:#ff8e8e}.lkm-tip.info{border-color:rgba(250,194,106,.35);background:rgba(250,194,106,.1);color:#ffc26a}
.lkm-form-foot{display:flex;align-items:center;gap:12px;padding:13px 20px;border-top:1px solid rgba(255,255,255,.12);background:rgba(0,0,0,.1)}
.lkm-form-note{margin-right:auto;color:#8492a6;font-size:10px;line-height:1.45}
.lkm-submit{display:inline-flex;align-items:center;justify-content:center;min-width:138px;height:40px;padding:0 18px;border:1px solid #5b78ef;border-radius:6px;background:#5b78ef;color:#fff;font-size:12px;font-weight:700;cursor:pointer;transition:background-color .18s,border-color .18s}
.lkm-submit:hover,.lkm-submit:focus-visible{outline:0;border-color:#4965d8;background:#4965d8}.lkm-submit:active{background:#3e57c3}.lkm-submit:disabled{cursor:not-allowed;opacity:.55}
.lkm-done{text-align:center;padding:34px 24px}.lkm-done .lkm-tick{font-size:52px;line-height:1;margin-bottom:14px;animation:lkmPop .22s ease-out}@keyframes lkmPop{from{transform:scale(.9);opacity:0}to{transform:none;opacity:1}}.lkm-done p{margin:0 0 6px;color:#fff;font-size:18px;font-weight:650}.lkm-done small{color:#93a2b5;font-size:13px}
.lkm-spin{width:16px;height:16px;flex:0 0 16px;margin:0;border:2px solid rgba(255,255,255,.24);border-top-color:#fff;border-radius:50%;animation:lkmSpin .7s linear infinite}@keyframes lkmSpin{to{transform:rotate(360deg)}}
@media(max-width:760px){
  #lkm-wrap{align-items:flex-end;padding:10px}
  .lkm-box{width:100%;max-height:calc(100dvh - 20px);border-radius:10px 10px 6px 6px}
  .lkm-hd{position:sticky;z-index:2;top:0;padding:15px 16px}
  .lkm-url-priority{padding:14px 16px}.lkm-url-line{grid-template-columns:1fr}.lkm-fetch{width:100%}
  .lkm-fields{grid-template-columns:1fr;gap:12px;padding:15px 16px}.lkm-row-wide{grid-column:auto}
  .lkm-form-foot{position:sticky;z-index:2;bottom:0;align-items:stretch;flex-direction:column;padding:12px 16px;background:#0e1721}.lkm-form-note{margin:0}.lkm-submit{width:100%;height:44px}
  .lkm-tip{margin-right:16px;margin-left:16px}
}

/* 友链申请 A · 冰川白磨砂 */
#lkm-wrap{background:rgba(2,9,16,.48);backdrop-filter:blur(8px) saturate(.88)}
.lkm-box{width:min(640px,100%);border:1px solid rgba(255,255,255,.2);border-radius:12px;background:rgba(224,240,250,.19);box-shadow:0 8px 28px rgba(0,0,0,.34);backdrop-filter:blur(30px) saturate(1.16)}
.lkm-hd{padding:19px 22px;border-bottom-color:rgba(255,255,255,.18);background:rgba(255,255,255,.06)}
.lkm-head-icon{width:36px;height:36px;flex-basis:36px;border-radius:8px;background:rgba(216,241,255,.16);color:#d7f0ff}
.lkm-head-icon img{display:block;width:20px;height:20px;object-fit:contain}
.lkm-hd h3{font-size:18px}.lkm-title p{color:rgba(234,241,248,.68)}
.lkm-close{border-radius:7px;color:rgba(234,241,248,.68)}.lkm-close:hover,.lkm-close:focus-visible{background:rgba(255,255,255,.1)}
.lkm-fields{gap:14px 16px;padding:20px 22px}.lkm-row label{color:#f7f9fc}.lkm-optional{color:rgba(234,241,248,.65)}
.lkm-row input,.lkm-row select,.lkm-row textarea{border-color:rgba(239,249,255,.3);border-radius:8px;background:rgba(5,19,31,.34);color:#f7f9fc}
.lkm-row input::placeholder,.lkm-row textarea::placeholder{color:rgba(234,241,248,.58)}
.lkm-row input:focus,.lkm-row select:focus,.lkm-row textarea:focus{border-color:#a8d8ff;background:rgba(4,16,28,.44);box-shadow:0 0 0 3px rgba(168,216,255,.2)}
.lkm-fetch{padding:0 16px;border-color:rgba(239,249,255,.3);border-radius:8px;background:rgba(255,255,255,.1);color:#f7f9fc}.lkm-fetch:hover,.lkm-fetch:focus-visible{border-color:#a8d8ff;background:rgba(255,255,255,.17)}
.lkm-meta-status{color:rgba(234,241,248,.68)}.lkm-meta-status.loading{color:#a8d8ff}.lkm-meta-status.success{color:#75e3b3}
.lkm-tip{margin-right:22px;margin-left:22px;border-radius:8px;backdrop-filter:blur(10px)}
.lkm-form-foot{padding:14px 22px;border-top-color:rgba(255,255,255,.18);background:rgba(2,10,18,.1)}.lkm-form-note{color:rgba(234,241,248,.62)}
.lkm-submit,.lkm-done-close{display:inline-flex;min-width:144px;height:40px;align-items:center;justify-content:center;gap:8px;padding:0 18px;border:1px solid #a8d8ff;border-radius:7px;background:#a8d8ff;color:#10263a;font-family:inherit;font-size:12px;font-weight:750;cursor:pointer;transition:filter .18s ease,box-shadow .18s ease,background-color .18s ease,border-color .18s ease,color .18s ease}.lkm-submit:hover,.lkm-submit:focus-visible,.lkm-done-close:hover,.lkm-done-close:focus-visible{outline:0;filter:brightness(1.06);box-shadow:0 0 0 3px rgba(168,216,255,.2)}.lkm-submit:active,.lkm-done-close:active{filter:brightness(.97)}.lkm-submit.is-loading{border-color:rgba(239,249,255,.34);background:rgba(255,255,255,.12);color:#f7f9fc}.lkm-submit.is-success{border-color:#75e3b3;background:#75e3b3;color:#0f2a21}.lkm-submit.is-pending{border-color:#ffd58a;background:#ffd58a;color:#35250d}
.lkm-done{padding:29px 22px 22px;text-align:left}.lkm-success-brand{display:grid;width:48px;height:48px;margin-bottom:18px;place-items:center;border:1px solid rgba(255,255,255,.2);border-radius:10px;background:rgba(216,241,255,.14);animation:lkmSuccessMark .42s var(--lkm-ease,cubic-bezier(.22,1,.36,1))}.lkm-success-brand img{display:block;width:28px;height:28px;object-fit:contain}@keyframes lkmSuccessMark{from{opacity:0;transform:scale(.84) translateY(6px)}to{opacity:1;transform:none}}.lkm-done-status{color:#a8d8ff;font-size:11px;font-weight:750;animation:lkmDoneRise .32s .05s both}.lkm-done h4{margin:8px 0 7px;color:#f7f9fc;font-size:21px;line-height:1.35;animation:lkmDoneRise .32s .09s both}.lkm-done>p{max-width:48ch;margin:0;color:rgba(234,241,248,.7);font-size:12px;line-height:1.7;animation:lkmDoneRise .32s .13s both}.lkm-done-next{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:24px 0 18px;padding:14px 0;border-top:1px solid rgba(255,255,255,.16);border-bottom:1px solid rgba(255,255,255,.16);animation:lkmDoneRise .32s .17s both}.lkm-done-next span{color:rgba(234,241,248,.6);font-size:11px}.lkm-done-next b{color:#f7f9fc;font-size:13px}.lkm-done-actions{display:flex;justify-content:flex-end;animation:lkmDoneRise .32s .21s both}@keyframes lkmDoneRise{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}
@media(max-width:760px){
  .lkm-box{background:rgba(34,57,73,.74)}.lkm-hd{padding:16px}.lkm-fields{padding:16px}.lkm-form-foot{display:block;padding:12px 16px;background:rgba(25,47,63,.88)}.lkm-form-note{display:block;margin:0 0 9px}.lkm-submit{width:100%;height:44px}.lkm-done{padding:26px 18px 18px}.lkm-done-next{align-items:flex-start;flex-direction:column;gap:5px}.lkm-done-close{width:100%;height:44px}
}
@media(prefers-reduced-motion:reduce){#lkm-wrap.open,.lkm-box,.lkm-success-brand,.lkm-done-status,.lkm-done h4,.lkm-done>p,.lkm-done-next,.lkm-done-actions{animation:none!important}}
</style>
<script>
(function(){
  function iconSources(image){
    try{return JSON.parse(image.getAttribute('data-icon-sources') || '[]');}
    catch(e){return [];}
  }
  function clearIconTimer(image){
    if(image._qifuIconTimer){clearTimeout(image._qifuIconTimer);image._qifuIconTimer=null;}
  }
  window.qifuSiteIconArm=function(image){
    clearIconTimer(image);
    if(image.getAttribute('data-icon-ready') === '1' || image.classList.contains('is-failed')) return;
    image._qifuIconTimer=setTimeout(function(){qifuSiteIconNext(image);},900);
  };
  window.qifuSiteIconLoaded=function(image){
    if(image.naturalWidth < 12 || image.naturalHeight < 12){qifuSiteIconNext(image);return;}
    clearIconTimer(image);
    image.setAttribute('data-icon-ready','1');
    image.classList.add('is-loaded');
  };
  window.qifuSiteIconNext=function(image){
    clearIconTimer(image);
    var sources=iconSources(image),index=parseInt(image.getAttribute('data-icon-index') || '0',10)+1;
    image.classList.remove('is-loaded');
    image.removeAttribute('data-icon-ready');
    if(index >= sources.length){image.classList.add('is-failed');image.removeAttribute('src');return;}
    image.setAttribute('data-icon-index',String(index));
    image.src=sources[index];
    qifuSiteIconArm(image);
  };
  document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('img.site-favicon').forEach(function(image){qifuSiteIconArm(image);});
  });
})();
</script>
</head>
<body>
<div class="bg"></div>
<div class="overlay"></div>
<div class="wrap">

  <?php if($show_link_apply!='0'): ?>
  <nav class="bar">
    <div class="bar-actions">
      <button class="bar-btn" id="lkmBtn" type="button">🔗 提交友联</button>
    </div>
  </nav>
  <?php endif; ?>

  <div class="hero">
    <?php if(!empty($announcement)): ?>
    <div style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);border-radius:12px;padding:12px 20px;max-width:660px;margin:0 auto 20px;font-size:.85rem;color:rgba(255,255,255,.85);">
      📢 <?php echo htmlspecialchars($announcement); ?>
    </div>
    <?php endif; ?>
    <?php if($show_clock!='0'): ?>
    <div id="clock" class="<?php echo $clock_style; ?>">00:00:00</div>
    <div id="date"></div>
    <?php endif; ?>
    <?php if($show_search!='0'): ?>
    <div class="search">
      <form onsubmit="doSearch();return false" style="display:flex;width:100%;align-items:center;">
      <div class="eng engine-picker" id="enginePicker">
        <button class="engine-current" type="button" id="engineBtn" aria-haspopup="true" aria-expanded="false">
          <span class="engine-badge" id="engineBadge"><?php echo $site_search_enabled ? '站' : '百'; ?></span>
          <span id="engineLabel"><?php echo $site_search_enabled ? '本站' : '百度'; ?></span>
          <span class="engine-arrow"></span>
        </button>
        <div class="engine-menu" id="engineMenu">
          <?php if($site_search_enabled): ?><button class="engine-option active" type="button" data-label="本站" data-icon="站" data-mode="local" data-url="local"><span class="engine-badge">站</span><span>本站搜索</span></button><?php endif; ?>
          <button class="engine-option <?php echo $site_search_enabled ? '' : 'active'; ?>" type="button" data-label="百度" data-icon="百" data-mode="external" data-url="https://www.baidu.com/s?wd="><span class="engine-badge">百</span><span>百度</span></button>
          <button class="engine-option" type="button" data-label="Google" data-icon="G" data-mode="external" data-url="https://www.google.com/search?q="><span class="engine-badge">G</span><span>Google</span></button>
          <button class="engine-option" type="button" data-label="Bing" data-icon="B" data-mode="external" data-url="https://www.bing.com/search?q="><span class="engine-badge">B</span><span>Bing</span></button>
        </div>
        <input type="hidden" id="eng" value="<?php echo $site_search_enabled ? 'local' : 'https://www.baidu.com/s?wd='; ?>" data-mode="<?php echo $site_search_enabled ? 'local' : 'external'; ?>">
      </div>
      <input class="sinp" id="sinp" type="text" placeholder="<?php echo $site_search_enabled ? '搜索本站资源...' : '搜索网页、资源、工具...'; ?>" autocomplete="off">
      <button class="sbtn" type="submit">⌕</button>
      </form>
    </div>
    <?php endif; ?>
    <?php if($show_tags!='0' && !empty($quick_tags)): ?>
    <div class="tags">
      <?php foreach($quick_tags as $quick_tag): ?>
      <a class="tag" href="<?php echo htmlspecialchars($quick_tag['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($quick_tag['name'], ENT_QUOTES, 'UTF-8'); ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if($ad_below_show): ?>
    <div class="ad-grid">
      <?php foreach($ad_below_items as $ad_slot_index => $ad_item):
        $ad_slot = intval($ad_slot_index) + 1;
        $ad_slot_label = function_exists('qifu_ad_slot_label') ? qifu_ad_slot_label($ad_slot) : ('位置 '.$ad_slot);
      ?>
      <div class="ad-cell" data-ad-slot="<?php echo $ad_slot; ?>" data-ad-slot-label="<?php echo htmlspecialchars($ad_slot_label); ?>">
        <?php if(!empty($ad_item) && $ad_item['image'] !== ''): ?>
        <a class="ad-link ad-banner" data-ad-id="<?php echo intval($ad_item['id']); ?>" data-ad-slot="<?php echo $ad_slot; ?>" href="<?php echo htmlspecialchars($ad_item['link'] ?: 'javascript:void(0)'); ?>"<?php echo $ad_item['link'] ? $ad_target : ''; ?> title="<?php echo htmlspecialchars($ad_item['title'] ?: $ad_slot_label); ?>" aria-label="<?php echo htmlspecialchars('搜索栏下方广告'.$ad_slot_label); ?>">
          <img class="ad-img" src="<?php echo htmlspecialchars($ad_item['image']); ?>" alt="<?php echo htmlspecialchars($ad_item['alt'] ?: $ad_item['title'] ?: 'ad'); ?>">
        </a>
        <?php else: ?>
        <span class="ad-cell-empty"></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if($site_search_enabled): ?><div class="site-search-state" id="siteSearchState" role="status" aria-live="polite"></div><?php endif; ?>
  <div id="sections">
<?php if (empty($sections)): ?>
  <div style="text-align:center;color:rgba(255,255,255,.45);padding:60px 0;font-size:.9rem;">
    暂无导航内容
  </div>
<?php else: ?>
  <?php foreach ($sections as $cat => $items):
    $cat_emoji = isset($cat_icons[$cat]) ? $cat_icons[$cat] : mb_substr($cat, 0, 1, 'UTF-8');
    $palette = ['#6c63ff','#10a37f','#d97706','#0ea5e9','#ec4899','#f59e0b','#8b5cf6','#14b8a6','#ef4444','#3b82f6','#06b6d4','#84cc16'];
  ?>
  <div class="sec">
    <div class="sec-hd">
      <div class="dot"></div>
      <span style="font-size:.9rem"><?php echo htmlspecialchars($cat_emoji); ?></span>
      <span class="sec-title"><?php echo htmlspecialchars($cat); ?></span>
      <div class="sec-line"></div>
    </div>
    <div class="grid">
      <?php foreach ($items as $item):
        $name    = htmlspecialchars($item['name']);
        $url     = htmlspecialchars($item['url']);
        $desc    = htmlspecialchars($item['description']);
        $icon    = trim($item['icon']);
        $domain = '';
        if (preg_match('#^https?://([^/]+)#i', $item['url'], $m)) { $domain = $m[1]; }
        $ci    = abs(crc32($item['name'])) % count($palette);
        $color = $palette[$ci];
        $show_desc = $desc ?: $domain;
        $desc_speed_map = ['slow' => 16, 'normal' => 10, 'fast' => 7, 'rapid' => 4];
        $desc_color_map = ['default', 'red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'purple', 'rainbow'];
        $desc_marquee = !empty($item['desc_marquee']) && intval($item['desc_marquee']) === 1;
        $desc_speed_key = isset($item['desc_speed']) && isset($desc_speed_map[$item['desc_speed']]) ? $item['desc_speed'] : 'normal';
        $desc_color_key = isset($item['desc_color']) && in_array($item['desc_color'], $desc_color_map) ? $item['desc_color'] : 'default';
        $desc_classes = 'ds'.($desc_marquee ? ' marquee' : '').($desc_color_key !== 'default' ? ' desc-color-'.$desc_color_key : '');
        $desc_style = $desc_marquee ? ' style="--desc-speed:'.$desc_speed_map[$desc_speed_key].'s"' : '';
        $icon_is_image = $icon !== '' && (filter_var($icon, FILTER_VALIDATE_URL) || preg_match('#^(?:/|\.\./)?site_icon\.php\?#i', $icon));
        $icon_sources = qifu_site_icon_sources((string)$item['url']);
        if($icon_is_image) array_unshift($icon_sources, $icon);
        $icon_sources = array_values(array_unique($icon_sources));
        $show_favicon = !empty($icon_sources);
        $icon_sources_json = $show_favicon ? htmlspecialchars(json_encode($icon_sources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') : '';
        $search_text = (string)$item['name'].' '.(isset($item['description']) ? (string)$item['description'] : '').' '.(string)$cat.' '.(string)$item['url'].' '.$domain;
        $initial = mb_substr($item['name'], 0, 1, 'UTF-8');
        $ping_status = isset($item['ping_status']) ? intval($item['ping_status']) : -1;
        $ping_latency = isset($item['ping_latency']) ? intval($item['ping_latency']) : 0;
        $ping_class = $ping_status === 1 ? 'online' : ($ping_status === 0 ? 'offline' : 'checking');
        if ($ping_status === 1) {
            $ping_title = $ping_latency > 0 ? '站点可访问，延迟 '.$ping_latency.'ms' : '站点可访问';
        } elseif ($ping_status === 0) {
            $ping_title = $ping_latency >= $ping_alert_latency ? '站点延迟过高，延迟 '.$ping_latency.'ms' : '站点无法访问';
        } else {
            $ping_title = '等待检测';
        }
      ?>
      <a class="card" href="<?php echo $url; ?>" target="_blank" rel="noopener" data-site-id="<?php echo intval($item['id']); ?>" data-search="<?php echo htmlspecialchars($search_text, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if($ping_enabled=='1'): ?><span class="ping-badge <?php echo $ping_class; ?>" title="<?php echo $ping_title; ?>"></span><?php endif; ?>
        <div class="ico<?php echo $show_favicon ? ' site-icon-wrap' : ''; ?>" style="background:<?php echo $color; ?>22;border:1px solid <?php echo $color; ?>44;">
          <?php if ($icon && !$icon_is_image): ?>
            <?php echo htmlspecialchars($icon); ?>
          <?php elseif ($show_favicon): ?>
            <span class="site-icon-fallback" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
            <img class="site-favicon" src="<?php echo htmlspecialchars($icon_sources[0], ENT_QUOTES, 'UTF-8'); ?>" data-icon-sources="<?php echo $icon_sources_json; ?>" data-icon-index="0" onload="qifuSiteIconLoaded(this)" onerror="qifuSiteIconNext(this)" alt="<?php echo $name; ?>" loading="eager" decoding="async" referrerpolicy="no-referrer">
          <?php else: ?>
            <?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?>
          <?php endif; ?>
        </div>
        <div class="inf">
          <div class="nm"><?php echo $name; ?></div>
          <div class="<?php echo $desc_classes; ?>"<?php echo $desc_style; ?>><span class="ds-text"><?php echo $show_desc; ?></span></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
  </div>

  <?php if($online_stats_enabled): ?>
  <section class="online-stats" aria-label="网站在线统计">
    <div class="online-stats-row">
      <span class="online-stats-item"><span class="online-stats-label">今日活跃</span><b><?php echo number_format($online_stats_data['today_active']); ?></b><span class="online-stats-unit">人</span></span><span class="online-stats-sep">|</span>
      <span class="online-stats-item"><span class="online-stats-label">今日更新</span><b><?php echo number_format($online_stats_data['today_updates']); ?></b><span class="online-stats-unit">个</span></span><span class="online-stats-sep">|</span>
      <span class="online-stats-item"><span class="online-stats-label">本站已有</span><b><?php echo number_format($online_stats_data['total_visits']); ?></b><span class="online-stats-unit">人访问</span></span><span class="online-stats-sep">|</span>
      <span class="online-stats-item"><span class="online-stats-label">今日有</span><b><?php echo number_format($online_stats_data['today_visits']); ?></b><span class="online-stats-unit">人访问</span></span><span class="online-stats-sep">|</span>
      <span class="online-stats-item online-stats-item-ip"><span class="online-stats-label">您的 IP</span><b><?php echo htmlspecialchars($online_stats_data['ip'], ENT_QUOTES, 'UTF-8'); ?></b></span>
    </div>
  </section>
  <?php endif; ?>

  <footer class="foot <?php echo $online_stats_enabled ? '' : 'foot-alone'; ?>" id="pageFooter">
    <div class="foot-row">
    <span class="foot-item"><?php echo htmlspecialchars($conf['footer_text'], ENT_QUOTES, 'UTF-8'); ?></span>
    <?php if(!empty($conf['footer_link'])): ?>
     <span class="foot-sep">·</span><a href="<?php echo htmlspecialchars($conf['footer_link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($conf['footer_link_text'], ENT_QUOTES, 'UTF-8'); ?></a>
    <?php endif; ?>
    <?php if(!empty($conf['icp'])): ?>
     <span class="foot-sep">·</span><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($conf['icp'], ENT_QUOTES, 'UTF-8'); ?></a>
    <?php endif; ?>
    <?php if($gongan_beian !== ''): ?>
     <span class="foot-sep">·</span><a class="foot-public-security" href="<?php echo htmlspecialchars($gongan_beian_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><span class="foot-public-security-icon" aria-hidden="true"></span><span><?php echo htmlspecialchars($gongan_beian, ENT_QUOTES, 'UTF-8'); ?></span></a>
    <?php endif; ?>
    </div>
  </footer>
</div>

<?php if($ad_right_show): ?>
<a class="ad-link ad-side ad-side-right" data-ad-id="<?php echo intval($ad_right['id']); ?>" href="<?php echo htmlspecialchars($ad_right['link'] ?: 'javascript:void(0)'); ?>"<?php echo $ad_right['link'] ? $ad_target : ''; ?> title="<?php echo htmlspecialchars($ad_right['title']); ?>">
  <img class="ad-img" src="<?php echo htmlspecialchars($ad_right['image']); ?>" alt="<?php echo htmlspecialchars($ad_right['alt'] ?: $ad_right['title'] ?: 'ad'); ?>">
</a>
<?php endif; ?>
<?php if($ad_left_show): ?>
<a class="ad-link ad-side ad-side-left" data-ad-id="<?php echo intval($ad_left['id']); ?>" href="<?php echo htmlspecialchars($ad_left['link'] ?: 'javascript:void(0)'); ?>"<?php echo $ad_left['link'] ? $ad_target : ''; ?> title="<?php echo htmlspecialchars($ad_left['title']); ?>">
  <img class="ad-img" src="<?php echo htmlspecialchars($ad_left['image']); ?>" alt="<?php echo htmlspecialchars($ad_left['alt'] ?: $ad_left['title'] ?: 'ad'); ?>">
</a>
<?php endif; ?>

<?php if($bg_music): ?>
<div class="music-btn" id="musicBtn" onclick="toggleMusic()">🎵</div>
<div class="music-panel" id="musicPanel">
  <div style="font-size:.8rem;color:rgba(255,255,255,.7);margin-bottom:5px;">🎧 背景音乐</div>
  <div class="vol">
    <span id="volIcon">🔊</span>
    <input type="range" id="volSlider" min="0" max="100" value="<?php echo $bg_music_volume; ?>" oninput="setVolume(this.value)">
    <span id="volVal"><?php echo $bg_music_volume; ?>%</span>
  </div>
</div>
<audio id="bgAudio" src="<?php echo htmlspecialchars($bg_music); ?>" loop preload="auto"></audio>
<?php endif; ?>

<?php if($show_link_apply!='0'): ?>
<!-- 友联申请弹窗 -->
<div id="lkm-wrap" role="dialog" aria-modal="true" aria-labelledby="lkmTitle">
  <div class="lkm-box">
    <div class="lkm-hd">
      <span class="lkm-head-icon" aria-hidden="true"><img src="install/default-logo.webp" alt=""></span>
      <div class="lkm-title">
        <h3 id="lkmTitle">提交友链申请</h3>
        <p>先填写网址，系统会自动补全站点资料</p>
      </div>
      <button class="lkm-close" id="lkmCloseBtn" type="button" aria-label="关闭友链申请弹窗">&times;</button>
    </div>
    <div class="lkm-bd" id="lkmFormBd">
      <div class="lkm-fields">
        <div class="lkm-row lkm-row-wide">
          <label>网站地址 <span class="lkm-auto-badge">自动识别</span></label>
          <div class="lkm-url-line">
            <input type="url" id="lkmUrl" placeholder="输入域名或完整 URL" autocomplete="off" inputmode="url">
            <button class="lkm-fetch" id="lkmFetchMeta" type="button">获取信息</button>
          </div>
          <div class="lkm-meta-status" id="lkmMetaStatus" aria-live="polite"></div>
        </div>
        <div class="lkm-row">
          <label>网站名称</label>
          <input type="text" id="lkmName" placeholder="等待自动获取" autocomplete="off">
        </div>
        <div class="lkm-row">
          <label>申请分类</label>
          <select id="lkmCat">
            <?php foreach($link_cats as $c): echo '<option value="'.htmlspecialchars($c['name']).'">'.htmlspecialchars($c['name']).'</option>'; endforeach; ?>
          </select>
        </div>
        <div class="lkm-row lkm-row-wide">
          <label>网站描述</label>
          <textarea id="lkmDesc" placeholder="等待自动获取，获取后仍可修改" autocomplete="off"></textarea>
        </div>
        <div class="lkm-row lkm-row-wide">
          <label>通知邮箱 <span class="lkm-optional">选填 · 仅用于审核结果</span></label>
          <input type="email" id="lkmEmail" placeholder="name@example.com" autocomplete="off">
        </div>
      </div>
      <div class="lkm-tip" id="lkmTip"></div>
      <div class="lkm-form-foot">
        <span class="lkm-form-note">信息获取失败时可以手动填写</span>
        <button class="lkm-submit" id="lkmSubmit">确认并提交</button>
      </div>
    </div>
    <div class="lkm-done" id="lkmDoneBd" style="display:none;" aria-live="polite">
      <div class="lkm-success-brand" aria-hidden="true"><img src="install/default-logo.webp" alt=""></div>
      <div class="lkm-done-status" id="lkmDoneStatus">申请已提交</div>
      <h4 id="lkmDoneTitle">友链申请提交成功</h4>
      <p id="lkmDoneMessage">站点资料已经进入审核列表，管理员处理后会通过您填写的邮箱发送结果。</p>
      <div class="lkm-done-next"><span>接下来</span><b id="lkmDoneNext">请等待管理员审核</b></div>
      <div class="lkm-done-actions"><button class="lkm-done-close" id="lkmDoneCloseBtn" type="button">返回首页</button></div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
<?php if($show_link_apply!='0'): ?>
(function(){
  var wrap = document.getElementById('lkm-wrap');
  var btn = document.getElementById('lkmBtn');
  var closeBtn = document.getElementById('lkmCloseBtn');
  var doneCloseBtn = document.getElementById('lkmDoneCloseBtn');
  var titleEl = document.getElementById('lkmTitle');
  var titleNote = titleEl.parentNode.querySelector('p');
  var submitBtn = document.getElementById('lkmSubmit');
  var tip = document.getElementById('lkmTip');
  var formBd = document.getElementById('lkmFormBd');
  var doneBd = document.getElementById('lkmDoneBd');
  var doneStatus = document.getElementById('lkmDoneStatus');
  var doneTitle = document.getElementById('lkmDoneTitle');
  var doneMessage = document.getElementById('lkmDoneMessage');
  var doneNext = document.getElementById('lkmDoneNext');
  var nameInput = document.getElementById('lkmName');
  var urlInput = document.getElementById('lkmUrl');
  var fetchButton = document.getElementById('lkmFetchMeta');
  var descInput = document.getElementById('lkmDesc');
  var emailInput = document.getElementById('lkmEmail');
  var metaStatus = document.getElementById('lkmMetaStatus');
  var metaTimer = null;
  var metaRequest = null;
  var metaRequestUrl = '';
  var metaCallbacks = [];

  function cancelMetaRequest(){
    clearTimeout(metaTimer);
    metaCallbacks = [];
    var pending = metaRequest;
    metaRequest = null;
    if(pending) pending.abort();
  }

  function setSubmitState(state, label){
    submitBtn.classList.remove('is-loading','is-success','is-pending');
    if(state) submitBtn.classList.add('is-' + state);
    submitBtn.disabled = state === 'loading';
    submitBtn.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
    submitBtn.innerHTML = state === 'loading' ? '<span class="lkm-spin" aria-hidden="true"></span><span>'+label+'</span>' : label;
  }

  function showDoneState(options){
    setSubmitState(options.buttonState || 'success', options.buttonLabel || '提交成功');
    titleEl.textContent = options.headerTitle;
    titleNote.textContent = options.headerNote;
    doneStatus.textContent = options.status;
    doneTitle.textContent = options.title;
    doneMessage.textContent = options.message;
    doneNext.textContent = options.next;
    var transitionDelay = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 380;
    setTimeout(function(){
      formBd.style.display = 'none';
      doneBd.style.display = 'block';
      submitBtn.classList.remove('is-success','is-pending');
      setTimeout(function(){ doneCloseBtn.focus(); }, 0);
    }, transitionDelay);
  }

  btn.addEventListener('click', function(){
    cancelMetaRequest();
    wrap.classList.add('open');
    titleEl.textContent = '提交友链申请';
    titleNote.textContent = '输入网址后，系统会自动补全公开站点资料';
    formBd.style.display = 'block';
    doneBd.style.display = 'none';
    tip.style.display = 'none';
    setSubmitState('', '确认并提交');
    nameInput.value = '';
    urlInput.value = '';
    descInput.value = '';
    emailInput.value = '';
    nameInput.removeAttribute('data-auto-value');
    descInput.removeAttribute('data-auto-value');
    setMetaStatus('', '');
    setTimeout(function(){ urlInput.focus(); }, 0);
  });

  closeBtn.addEventListener('click', function(){
    cancelMetaRequest();
    wrap.classList.remove('open');
  });
  doneCloseBtn.addEventListener('click', function(){
    wrap.classList.remove('open');
  });

  function normalizeSiteUrl(value){
    value = String(value || '').trim();
    if(!value) return '';
    if(!/^[a-z][a-z0-9+.-]*:\/\//i.test(value)) value = 'https://' + value;
    try{
      var parsed = new URL(value);
      if(parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return '';
      return parsed.href;
    }catch(e){ return ''; }
  }

  function setMetaStatus(message, state){
    metaStatus.textContent = message || '';
    metaStatus.className = 'lkm-meta-status' + (state ? ' ' + state : '');
    fetchButton.disabled = state === 'loading';
    fetchButton.textContent = state === 'loading' ? '获取中...' : '获取信息';
  }

  function applyAutoValue(input, value){
    value = String(value || '').trim();
    if(!value) return;
    var previous = input.getAttribute('data-auto-value') || '';
    if(!input.value.trim() || input.value === previous){
      input.value = value;
      input.setAttribute('data-auto-value', value);
    }
  }

  function clearPreviousAutoValue(input){
    var previous = input.getAttribute('data-auto-value') || '';
    if(previous && input.value === previous) input.value = '';
    input.removeAttribute('data-auto-value');
  }

  function finishMetaRequest(request, success){
    if(metaRequest !== request) return;
    var callbacks = metaCallbacks.slice();
    metaCallbacks = [];
    metaRequest = null;
    callbacks.forEach(function(callback){ callback(success); });
  }

  function requestSiteMeta(callback){
    var normalized = normalizeSiteUrl(urlInput.value);
    if(!normalized){
      setMetaStatus(urlInput.value.trim() ? '请输入正确的网站域名或URL' : '', urlInput.value.trim() ? 'error' : '');
      if(callback) callback(false);
      return;
    }
    urlInput.value = normalized;
    if(metaRequest && metaRequestUrl === normalized){
      if(callback) metaCallbacks.push(callback);
      return;
    }
    if(metaRequestUrl !== normalized){
      clearPreviousAutoValue(nameInput);
      clearPreviousAutoValue(descInput);
    }
    if(metaRequest){
      metaRequest.abort();
      metaRequest = null;
    }
    metaCallbacks = callback ? [callback] : [];
    metaRequestUrl = normalized;
    setMetaStatus('正在获取网站名称和描述...', 'loading');
    var request = new XMLHttpRequest();
    metaRequest = request;
    request.open('POST', 'ajax_site_meta.php', true);
    request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    request.onload = function(){
      var response;
      try{ response = JSON.parse(request.responseText); }
      catch(e){
        setMetaStatus('自动获取失败，请手动填写', 'error');
        finishMetaRequest(request, false);
        return;
      }
      if(response.code == 1 && response.data){
        applyAutoValue(nameInput, response.data.name);
        applyAutoValue(descInput, response.data.description);
        if(response.data.url) urlInput.value = response.data.url;
        setMetaStatus('已自动获取网站信息', 'success');
        finishMetaRequest(request, true);
      }else{
        setMetaStatus(response.msg || '未能自动获取，请手动填写', 'error');
        finishMetaRequest(request, false);
      }
    };
    request.onerror = function(){
      setMetaStatus('自动获取失败，请手动填写', 'error');
      finishMetaRequest(request, false);
    };
    request.onabort = function(){ finishMetaRequest(request, false); };
    request.send('url=' + encodeURIComponent(normalized) + '&_csrf=<?php echo rawurlencode(qifu_csrf_token()); ?>');
  }

  urlInput.addEventListener('input', function(){
    clearTimeout(metaTimer);
    setMetaStatus('', '');
    metaTimer = setTimeout(function(){ requestSiteMeta(); }, 900);
  });
  urlInput.addEventListener('blur', function(){
    clearTimeout(metaTimer);
    requestSiteMeta();
  });
  fetchButton.addEventListener('click', function(){
    clearTimeout(metaTimer);
    requestSiteMeta();
  });

  function submitApplication(){
    var name = nameInput.value.trim();
    var url = urlInput.value.trim();
    var desc = descInput.value.trim();
    var cat = document.getElementById('lkmCat').value;
    var email = emailInput.value.trim();
    if(!name || !url){
      tip.style.display='block'; tip.className='lkm-tip err'; tip.innerHTML='请填写网站名称和URL'; return;
    }
    setSubmitState('loading', '正在提交...');
    tip.style.display='none';
    var x = new XMLHttpRequest();
    x.open('POST','ajax_link.php',true);
    x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    x.onload = function(){
      try{var r = JSON.parse(x.responseText);}catch(e){
        setSubmitState('', '确认并提交');
        tip.style.display='block'; tip.className='lkm-tip err'; tip.innerHTML='返回格式错误，请重试'; return;
      }
      if(r.code==1){
        showDoneState({buttonState:'success',buttonLabel:'提交成功',headerTitle:'申请已提交',headerNote:'站点资料已安全送达审核列表',status:'申请已提交',title:'友链申请提交成功',message:'站点资料已经进入审核列表，管理员处理后会通过您填写的邮箱发送结果。',next:'请等待管理员审核'});
      } else if(String(r.msg || '').indexOf('已提交过申请') !== -1){
        showDoneState({buttonState:'pending',buttonLabel:'已在审核中',headerTitle:'申请正在审核',headerNote:'无需重复提交站点资料',status:'申请已在审核中',title:'该网站已提交过申请',message:'我们已经收到该网站的友链资料，当前正在等待管理员处理。',next:'请耐心等待审核结果'});
      } else {
        setSubmitState('', '确认并提交');
        tip.style.display='block'; tip.className='lkm-tip info'; tip.innerHTML=r.msg;
      }
    };
    x.onerror = function(){
      setSubmitState('', '确认并提交');
      tip.style.display='block'; tip.className='lkm-tip err'; tip.innerHTML='网络错误，请检查连接';
    };
    x.send('name='+encodeURIComponent(name)+'&url='+encodeURIComponent(url)+'&desc='+encodeURIComponent(desc)+'&cat='+encodeURIComponent(cat)+'&email='+encodeURIComponent(email)+'&_csrf=<?php echo rawurlencode(qifu_csrf_token()); ?>');
  }

  submitBtn.addEventListener('click', function(){
    if(!nameInput.value.trim() && urlInput.value.trim()){
      requestSiteMeta(function(){ submitApplication(); });
      return;
    }
    submitApplication();
  });
})();
<?php endif; ?>
<?php if($bg_music): ?>
var audio=document.getElementById('bgAudio'),isPlaying=false;
function toggleMusic(){var panel=document.getElementById('musicPanel');panel.classList.toggle('active');}
function setVolume(v){audio.volume=v/100;document.getElementById('volVal').textContent=v+'%';document.getElementById('volIcon').textContent=v==0?'🔇':v<50?'🔉':'🔊';}
document.getElementById('musicBtn').addEventListener('click',function(){if(!isPlaying){audio.volume=<?php echo $bg_music_volume; ?>/100;audio.play().then(function(){isPlaying=true}).catch(function(){});}else{audio.pause();isPlaying=false;}});
<?php endif; ?>
function tick(){var n=new Date(),pad=function(v){return String(v).padStart(2,'0')},is12h='<?php echo $time_format; ?>'==='12';var h=n.getHours(),ampm=h>=12?'下午':'上午';if(is12h)h=h%12||12;var ts=is12h?ampm+' '+pad(h)+':'+pad(n.getMinutes()):pad(h)+':'+pad(n.getMinutes())+':'+pad(n.getSeconds());var ce=document.getElementById('clock');if(ce){if(ce.classList.contains('simple')){ce.textContent='现在'+ampm+' '+pad(h)+':'+pad(n.getMinutes());}else{ce.textContent=ts;}}var de=document.getElementById('date');if(de){var days=['日','一','二','三','四','五','六'];de.textContent=n.getFullYear()+' · '+pad(n.getMonth()+1)+' · '+pad(n.getDate())+' · 星期'+days[n.getDay()];}}
setInterval(tick,1000);tick();
function filterLocalSites(query,scrollToResults){
  var sections=document.getElementById('sections'),state=document.getElementById('siteSearchState');
  if(!sections)return 0;
  var normalized=String(query||'').trim().toLocaleLowerCase(),count=0;
  sections.querySelectorAll('.sec').forEach(function(section){
    var sectionMatches=0;
    section.querySelectorAll('.card').forEach(function(card){
      var match=!normalized||(card.getAttribute('data-search')||'').toLocaleLowerCase().indexOf(normalized)!==-1;
      card.hidden=!match;
      if(match){count++;sectionMatches++;}
    });
    section.hidden=sectionMatches===0;
  });
  if(state){
    if(!normalized){state.className='site-search-state';state.textContent='';}
    else{state.className='site-search-state show'+(count===0?' empty':'');state.textContent=count===0?'未找到匹配的本站资源':'找到 '+count+' 个本站资源';}
  }
  if(scrollToResults&&normalized&&state)state.scrollIntoView({behavior:'smooth',block:'center'});
  return count;
}
function doSearch(){
  var input=document.getElementById('sinp'),eng=document.getElementById('eng');
  if(!input||!eng)return;
  var q=input.value.trim();
  if(!q){if(eng.getAttribute('data-mode')==='local')filterLocalSites('',false);return;}
  if(eng.getAttribute('data-mode')==='local'){filterLocalSites(q,true);return;}
  window.open(eng.value+encodeURIComponent(q),'_blank','noopener');
}
var sinpEl=document.getElementById('sinp');if(sinpEl){
  sinpEl.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();doSearch();}});
  sinpEl.addEventListener('input',function(){var eng=document.getElementById('eng');if(eng&&eng.getAttribute('data-mode')==='local')filterLocalSites(sinpEl.value,false);});
}
(function(){
  var picker=document.getElementById('enginePicker'),btn=document.getElementById('engineBtn'),menu=document.getElementById('engineMenu'),eng=document.getElementById('eng'),label=document.getElementById('engineLabel'),badge=document.getElementById('engineBadge');
  if(!picker||!btn||!menu||!eng)return;
  btn.addEventListener('click',function(e){e.stopPropagation();var open=picker.classList.toggle('open');btn.setAttribute('aria-expanded',open?'true':'false');});
  menu.querySelectorAll('.engine-option').forEach(function(opt){
    opt.addEventListener('click',function(){
      menu.querySelectorAll('.engine-option').forEach(function(o){o.classList.remove('active');});
      opt.classList.add('active');eng.value=opt.getAttribute('data-url');eng.setAttribute('data-mode',opt.getAttribute('data-mode')||'external');label.textContent=opt.getAttribute('data-label');badge.textContent=opt.getAttribute('data-icon');
      var local=eng.getAttribute('data-mode')==='local',input=document.getElementById('sinp');if(input)input.placeholder=local?'搜索本站资源...':'搜索网页、资源、工具...';if(local){filterLocalSites(input?input.value:'',false);}else{filterLocalSites('',false);}
      picker.classList.remove('open');btn.setAttribute('aria-expanded','false');
    });
  });
  document.addEventListener('click',function(){picker.classList.remove('open');btn.setAttribute('aria-expanded','false');});
})();
(function(){
  function trackAd(adId,type){
    if(!adId)return;
    var body='ad_id='+encodeURIComponent(adId)+'&type='+encodeURIComponent(type);
    if(navigator.sendBeacon){
      navigator.sendBeacon('ajax_ad_track.php',new Blob([body],{type:'application/x-www-form-urlencoded'}));
    }else if(window.fetch){
      fetch('ajax_ad_track.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,keepalive:true}).catch(function(){});
    }
  }
  document.querySelectorAll('.ad-link[data-ad-id]').forEach(function(ad){
    trackAd(ad.getAttribute('data-ad-id'),'view');
    ad.addEventListener('click',function(){
      trackAd(ad.getAttribute('data-ad-id'),'click');
    });
  });
})();
(function(){
  var cards=Array.prototype.slice.call(document.querySelectorAll('.card[data-site-id]'));
  var viewed={};
  var pending=[];
  var flushTimer=0;
  function postSiteStats(body){
    if(navigator.sendBeacon){
      var accepted=navigator.sendBeacon('ajax_track.php',new Blob([body],{type:'application/x-www-form-urlencoded'}));
      if(accepted)return;
    }
    if(window.fetch)fetch('ajax_track.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,keepalive:true,credentials:'same-origin'}).catch(function(){});
  }
  function flushImpressions(){
    if(flushTimer){clearTimeout(flushTimer);flushTimer=0;}
    if(!pending.length)return;
    var ids=pending.splice(0,pending.length);
    postSiteStats('type=impression&site_ids='+encodeURIComponent(ids.join(',')));
  }
  function markViewed(card){
    var id=card.getAttribute('data-site-id');
    if(!id||viewed[id])return;
    viewed[id]=true;
    pending.push(id);
    if(!flushTimer)flushTimer=setTimeout(flushImpressions,250);
  }
  if('IntersectionObserver' in window){
    var observer=new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if(entry.isIntersecting&&entry.intersectionRatio>=0.25){markViewed(entry.target);observer.unobserve(entry.target);}
      });
    },{threshold:[0.25],rootMargin:'0px 0px 64px 0px'});
    cards.forEach(function(card){observer.observe(card);});
  }else{
    cards.forEach(markViewed);
  }
  document.addEventListener('visibilitychange',function(){if(document.visibilityState==='hidden')flushImpressions();});
  window.addEventListener('pagehide',flushImpressions);
  cards.forEach(function(card){
    card.addEventListener('click',function(){
      var id=card.getAttribute('data-site-id');if(!id)return;
      var body='type=click&site_id='+encodeURIComponent(id);
      var sent=false;
      if(window.fetch){
        try{
          sent=true;
          fetch('ajax_track.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,keepalive:true,credentials:'same-origin'}).catch(function(){
            if(navigator.sendBeacon) navigator.sendBeacon('ajax_track.php',new Blob([body],{type:'application/x-www-form-urlencoded'}));
          });
        }catch(e){ sent=false; }
      }
      if(!sent && navigator.sendBeacon){
        navigator.sendBeacon('ajax_track.php',new Blob([body],{type:'application/x-www-form-urlencoded'}));
      }
    });
  });
})();
<?php if($ping_need_refresh): ?>
setTimeout(function(){
  fetch('cron_site_status.php?auto=1', {cache:'no-store'}).catch(function(){});
},1200);
<?php endif; ?>
</script>
<img src="stats.php" width="0" height="0" style="display:none" alt="">
</body>
</html>

