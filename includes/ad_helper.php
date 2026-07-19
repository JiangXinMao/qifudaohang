<?php
if(!defined('IN_CRONLITE')) exit();

function qifu_ad_escape($value){
    global $DB;
    return method_exists($DB, 'escape') ? $DB->escape($value) : addslashes((string)$value);
}

function qifu_ad_positions(){
    return array(
        'below_search' => '搜索栏下方四等分',
        'pc_right' => 'PC右侧悬浮',
        'pc_left' => 'PC左侧悬浮',
    );
}

function qifu_ad_slot_labels(){
    return array(
        1 => '左上',
        2 => '右上',
        3 => '左下',
        4 => '右下',
    );
}

function qifu_ad_slot_label($slot){
    $slot = max(1, min(4, intval($slot)));
    $labels = qifu_ad_slot_labels();
    return isset($labels[$slot]) ? $labels[$slot] : '位置 '.$slot;
}

function qifu_ad_image_box($position){
    return $position === 'pc_left' || $position === 'pc_right'
        ? array('width'=>600, 'height'=>800)
        : array('width'=>1440, 'height'=>480);
}

function qifu_ad_fit_dimensions($width, $height, $position){
    $width = max(1, intval($width));
    $height = max(1, intval($height));
    $box = qifu_ad_image_box($position);
    $scale = min(1, $box['width'] / $width, $box['height'] / $height);
    return array(
        'width'=>max(1, intval(round($width * $scale))),
        'height'=>max(1, intval(round($height * $scale))),
        'resized'=>$scale < 1
    );
}

function qifu_ad_resize_saved_image($path, $position, &$info){
    $info = array('resized'=>false, 'message'=>'');
    $image = @getimagesize($path);
    if(!$image || empty($image[0]) || empty($image[1])){
        $info['message'] = '图片已上传，前台会自动等比适配';
        return true;
    }
    $mime = isset($image['mime']) ? strtolower((string)$image['mime']) : '';
    $fit = qifu_ad_fit_dimensions($image[0], $image[1], $position);
    $info = array(
        'original_width'=>intval($image[0]),
        'original_height'=>intval($image[1]),
        'width'=>$fit['width'],
        'height'=>$fit['height'],
        'resized'=>false,
        'message'=>''
    );
    if(!$fit['resized']){
        $info['message'] = '上传成功，图片尺寸 '.$image[0].'×'.$image[1].'，无需缩小';
        return true;
    }
    if($mime === 'image/gif'){
        $info['width'] = intval($image[0]);
        $info['height'] = intval($image[1]);
        $info['message'] = '上传成功，GIF 动画保持原图，前台会完整等比适配';
        return true;
    }
    $loaders = array(
        'image/jpeg'=>'imagecreatefromjpeg',
        'image/png'=>'imagecreatefrompng',
        'image/webp'=>'imagecreatefromwebp'
    );
    if(!isset($loaders[$mime]) || !function_exists($loaders[$mime]) || !function_exists('imagecreatetruecolor')){
        $info['width'] = intval($image[0]);
        $info['height'] = intval($image[1]);
        $info['message'] = '上传成功，服务器未启用 GD，前台会完整等比适配原图';
        return true;
    }
    $source = @$loaders[$mime]($path);
    $canvas = @imagecreatetruecolor($fit['width'], $fit['height']);
    if(!$source || !$canvas){
        if($source) imagedestroy($source);
        if($canvas) imagedestroy($canvas);
        $info['width'] = intval($image[0]);
        $info['height'] = intval($image[1]);
        $info['message'] = '上传成功，图片缩放不可用，前台会完整等比适配原图';
        return true;
    }
    if($mime === 'image/png' || $mime === 'image/webp'){
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $fit['width'], $fit['height'], $transparent);
    }
    $copied = imagecopyresampled($canvas, $source, 0, 0, 0, 0, $fit['width'], $fit['height'], intval($image[0]), intval($image[1]));
    $temporary = $path.'.resize';
    $saved = false;
    if($copied){
        if($mime === 'image/jpeg'){
            imageinterlace($canvas, true);
            $saved = imagejpeg($canvas, $temporary, 88);
        } elseif($mime === 'image/png'){
            $saved = imagepng($canvas, $temporary, 6);
        } elseif($mime === 'image/webp'){
            $saved = imagewebp($canvas, $temporary, 86);
        }
    }
    imagedestroy($canvas);
    imagedestroy($source);
    if($saved && is_file($temporary) && @copy($temporary, $path)){
        @unlink($temporary);
        @chmod($path, 0644);
        $info['resized'] = true;
        $info['message'] = '上传成功，图片已从 '.$image[0].'×'.$image[1].' 等比缩小为 '.$fit['width'].'×'.$fit['height'];
        return true;
    }
    if(is_file($temporary)) @unlink($temporary);
    $info['width'] = intval($image[0]);
    $info['height'] = intval($image[1]);
    $info['message'] = '上传成功，图片缩放失败，前台会完整等比适配原图';
    return true;
}

function qifu_ad_upload_image($file, $directory, $prefix, $position, &$error, &$info){
    $info = array();
    $filename = qifu_safe_image_upload($file, $directory, $prefix, $error);
    if($filename === false) return false;
    $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$filename;
    qifu_ad_resize_saved_image($path, $position, $info);
    return $filename;
}

function qifu_ad_modes(){
    return array(
        'fixed' => '按排序展示',
        'random' => '按权重随机',
        'rotate' => '按权重轮播',
    );
}

function qifu_ad_config_defaults(){
    return array(
        'ad_mode_below_search' => 'fixed',
        'ad_mode_pc_right' => 'fixed',
        'ad_mode_pc_left' => 'fixed',
        'ad_stat_enabled' => '1',
    );
}

function qifu_ad_ensure_tables(){
    global $DB;
    if(defined('SQLITE')){
        $DB->query("CREATE TABLE IF NOT EXISTS web_ads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            position TEXT NOT NULL DEFAULT 'below_search', slot INTEGER NOT NULL DEFAULT 1,
            title TEXT NOT NULL DEFAULT '', image TEXT NOT NULL DEFAULT '', link TEXT NOT NULL DEFAULT '', alt TEXT NOT NULL DEFAULT '',
            active INTEGER NOT NULL DEFAULT 1, start_at TEXT NOT NULL DEFAULT '', end_at TEXT NOT NULL DEFAULT '',
            sort INTEGER NOT NULL DEFAULT 100, weight INTEGER NOT NULL DEFAULT 1,
            created_at INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL DEFAULT 0
        )");
        $DB->query("CREATE INDEX IF NOT EXISTS web_ads_position_slot ON web_ads(position,slot)");
        $DB->query("CREATE TABLE IF NOT EXISTS web_ad_stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT, ad_id INTEGER NOT NULL, stat_date TEXT NOT NULL,
            views INTEGER NOT NULL DEFAULT 0, clicks INTEGER NOT NULL DEFAULT 0,
            UNIQUE(ad_id,stat_date)
        )");
        return;
    }
    $DB->query("CREATE TABLE IF NOT EXISTS `web_ads` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `position` varchar(30) NOT NULL DEFAULT 'below_search',
        `slot` int(11) NOT NULL DEFAULT 1,
        `title` varchar(100) NOT NULL DEFAULT '',
        `image` varchar(255) NOT NULL DEFAULT '',
        `link` varchar(255) NOT NULL DEFAULT '',
        `alt` varchar(255) NOT NULL DEFAULT '',
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `start_at` varchar(19) NOT NULL DEFAULT '',
        `end_at` varchar(19) NOT NULL DEFAULT '',
        `sort` int(11) NOT NULL DEFAULT 100,
        `weight` int(11) NOT NULL DEFAULT 1,
        `created_at` int(11) NOT NULL DEFAULT 0,
        `updated_at` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `position_slot` (`position`,`slot`),
        KEY `active_time` (`active`,`start_at`,`end_at`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");

    $DB->query("CREATE TABLE IF NOT EXISTS `web_ad_stats` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `ad_id` int(11) NOT NULL,
        `stat_date` date NOT NULL,
        `views` int(11) NOT NULL DEFAULT 0,
        `clicks` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `ad_date` (`ad_id`,`stat_date`),
        UNIQUE KEY `ad_date_unique` (`ad_id`,`stat_date`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
}

function qifu_ad_ensure_config(){
    global $DB, $conf, $CACHE;
    $changed = false;
    foreach(qifu_ad_config_defaults() as $key => $value){
        if(!isset($conf[$key])){
            $DB->query("REPLACE INTO web_config SET k='".qifu_ad_escape($key)."',v='".qifu_ad_escape($value)."'");
            $conf[$key] = $value;
            $changed = true;
        }
    }
    if($changed && isset($CACHE)){
        $CACHE->clear();
        $conf = $CACHE->update();
    }
}

function qifu_ad_normalize_url($url){
    $url = trim((string)$url);
    if($url === '') return '';
    if(strpos($url, '//') === 0) return 'https:'.$url;
    if(preg_match('/^https?:\/\//i', $url)) return $url;
    if(preg_match('/^[a-z0-9.-]+\.[a-z]{2,}(\/.*)?$/i', $url)) return 'https://'.$url;
    return $url;
}

function qifu_ad_is_active($ad, $now = null){
    if($now === null) $now = date('Y-m-d H:i:s');
    if(empty($ad) || intval($ad['active']) !== 1) return false;
    if(!empty($ad['start_at']) && $ad['start_at'] > $now) return false;
    if(!empty($ad['end_at']) && $ad['end_at'] < $now) return false;
    return !empty($ad['image']);
}

function qifu_ad_status_text($ad){
    $now = date('Y-m-d H:i:s');
    if(intval($ad['active']) !== 1) return array('off', '已停用');
    if(!empty($ad['start_at']) && $ad['start_at'] > $now) return array('wait', '待上线');
    if(!empty($ad['end_at']) && $ad['end_at'] < $now) return array('end', '已下线');
    if(empty($ad['image'])) return array('bad', '缺少图片');
    return array('on', '投放中');
}

function qifu_ad_seed_legacy(){
    global $DB, $conf, $CACHE, $rooturl;
    qifu_ad_ensure_tables();
    if(isset($conf['ad_legacy_seeded']) && $conf['ad_legacy_seeded'] == '1') return;
    $count = intval($DB->count("SELECT COUNT(*) FROM web_ads"));
    if($count > 0){
        $DB->query("REPLACE INTO web_config SET k='ad_legacy_seeded',v='1'");
        $conf['ad_legacy_seeded'] = '1';
        if(isset($CACHE)) $CACHE->clear();
        return;
    }
    $now = time();
    $legacy = array();
    for($i=1; $i<=4; $i++){
        $suffix = $i == 1 ? '' : strval($i);
        if(!empty($conf['ad_image'.$suffix])){
            $legacy[] = array(
                'position' => 'below_search',
                'slot' => $i,
                'title' => isset($conf['ad_title'.$suffix]) ? $conf['ad_title'.$suffix] : '',
                'image' => qifu_media_normalize_url($conf['ad_image'.$suffix], $rooturl),
                'link' => isset($conf['ad_link'.$suffix]) ? $conf['ad_link'.$suffix] : '',
                'alt' => isset($conf['ad_alt'.$suffix]) ? $conf['ad_alt'.$suffix] : '',
                'sort' => 100 + $i,
            );
        }
    }
    foreach(array('right' => 'pc_right', 'left' => 'pc_left') as $old => $position){
        if(!empty($conf['ad_'.$old.'_image'])){
            $legacy[] = array(
                'position' => $position,
                'slot' => 1,
                'title' => isset($conf['ad_'.$old.'_title']) ? $conf['ad_'.$old.'_title'] : '',
                'image' => qifu_media_normalize_url($conf['ad_'.$old.'_image'], $rooturl),
                'link' => isset($conf['ad_'.$old.'_link']) ? $conf['ad_'.$old.'_link'] : '',
                'alt' => isset($conf['ad_'.$old.'_alt']) ? $conf['ad_'.$old.'_alt'] : '',
                'sort' => 100,
            );
        }
    }
    foreach($legacy as $ad){
        $DB->query("INSERT INTO web_ads (`position`,`slot`,`title`,`image`,`link`,`alt`,`active`,`sort`,`weight`,`created_at`,`updated_at`) VALUES (
            '".qifu_ad_escape($ad['position'])."',
            '".intval($ad['slot'])."',
            '".qifu_ad_escape($ad['title'])."',
            '".qifu_ad_escape($ad['image'])."',
            '".qifu_ad_escape($ad['link'])."',
            '".qifu_ad_escape($ad['alt'])."',
            1,
            '".intval($ad['sort'])."',
            1,
            '".$now."',
            '".$now."'
        )");
    }
    $DB->query("REPLACE INTO web_config SET k='ad_legacy_seeded',v='1'");
    $conf['ad_legacy_seeded'] = '1';
    if(isset($CACHE)) $CACHE->clear();
}

function qifu_ad_all(){
    global $DB, $rooturl;
    qifu_ad_ensure_tables();
    $rows = $DB->get_results("SELECT a.*,COALESCE(SUM(s.views),0) AS views,COALESCE(SUM(s.clicks),0) AS clicks
        FROM web_ads a
        LEFT JOIN web_ad_stats s ON a.id=s.ad_id
        GROUP BY a.id
        ORDER BY a.position ASC,a.slot ASC,a.sort ASC,a.id ASC");
    foreach($rows as &$row) $row['image'] = qifu_media_normalize_url($row['image'], $rooturl);
    unset($row);
    return $rows;
}

function qifu_ad_front_groups(){
    global $DB, $rooturl;
    qifu_ad_ensure_tables();
    $now = qifu_ad_escape(date('Y-m-d H:i:s'));
    $rows = $DB->get_results("SELECT * FROM web_ads
        WHERE active=1 AND image<>'' AND (start_at='' OR start_at<='$now') AND (end_at='' OR end_at>='$now')
        ORDER BY position ASC,slot ASC,sort ASC,id ASC");
    $groups = array(
        'below_search' => array(1 => array(), 2 => array(), 3 => array(), 4 => array()),
        'pc_right' => array(1 => array()),
        'pc_left' => array(1 => array()),
    );
    foreach($rows as $row){
        $row['image'] = qifu_media_normalize_url($row['image'], $rooturl);
        $position = isset($groups[$row['position']]) ? $row['position'] : 'below_search';
        $slot = $position == 'below_search' ? max(1, min(4, intval($row['slot']))) : 1;
        $groups[$position][$slot][] = $row;
    }
    return $groups;
}

function qifu_ad_pick($ads, $mode = 'fixed'){
    if(empty($ads)) return null;
    if($mode === 'random' || $mode === 'rotate'){
        $pool = array();
        foreach($ads as $ad){
            $weight = max(1, min(50, intval($ad['weight'])));
            for($i=0; $i<$weight; $i++) $pool[] = $ad;
        }
        if(empty($pool)) return $ads[0];
        if($mode === 'random') return $pool[array_rand($pool)];
        $idx = intval(floor(time() / 10)) % count($pool);
        return $pool[$idx];
    }
    return $ads[0];
}

function qifu_ad_track($ad_id, $field){
    global $DB;
    qifu_ad_ensure_tables();
    $ad_id = intval($ad_id);
    if($ad_id <= 0 || !in_array($field, array('views', 'clicks'))) return false;
    $ad = $DB->get_row("SELECT id FROM web_ads WHERE id='$ad_id' LIMIT 1");
    if(!$ad) return false;
    $today = date('Y-m-d');
    $DB->query("INSERT INTO web_ad_stats (ad_id, stat_date, `$field`) VALUES ('$ad_id', '$today', 1)
        ON DUPLICATE KEY UPDATE `$field`=`$field`+1");
    return true;
}

function qifu_ad_check_image($url){
    $url = trim((string)$url);
    if($url === '') return array(false, '未设置图片');
    if(preg_match('/^https?:\/\//i', $url)){
        $resolved_ip = null;
        if(!qifu_public_http_url($url, $resolved_ip)) return array(false, '禁止访问内网或无效图片地址');
        if(function_exists('curl_init')){
            $parts = parse_url($url);
            $port = isset($parts['port']) ? intval($parts['port']) : (strtolower($parts['scheme']) === 'https' ? 443 : 80);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            if(defined('CURLOPT_PROTOCOLS')) curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_RESOLVE, array($parts['host'].':'.$port.':'.$resolved_ip));
            curl_setopt($ch, CURLOPT_USERAGENT, 'QIFU-Ad-Checker/1.0');
            curl_exec($ch);
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            $type = strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
            $err = curl_error($ch);
            curl_close($ch);
            if($code >= 200 && $code < 400 && ($type === '' || strpos($type, 'image/') !== false)){
                return array(true, '图片正常');
            }
            return array(false, $code ? '图片异常 HTTP '.$code : ($err ?: '远程图片不可访问'));
        }
        return array(null, '服务器未开启 curl，无法自动检测远程图片');
    }
    $relative = qifu_media_local_relative_path($url);
    if($relative === false) return array(false, '本地图片路径无效');
    $path = ROOT.$relative;
    return is_file($path) ? array(true, '图片正常') : array(false, '本地图片不存在');
}
?>
