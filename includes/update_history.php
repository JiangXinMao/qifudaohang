<?php
declare(strict_types=1);

if(!defined('IN_CRONLITE')) exit;

function qifu_update_version_key($version){
    $value = ltrim(trim((string)$version), "vV ");
    if(preg_match('/^\d+\.\d+$/', $value)) $value .= '.0';
    return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $value) ? strtolower($value) : '';
}

function qifu_update_display_version($version){
    $key = qifu_update_version_key($version);
    return $key !== '' ? 'V'.$key : '';
}

function qifu_update_text($value, $limit = 500){
    if(is_array($value) || is_object($value)) return '';
    $text = trim(strip_tags(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8')));
    if($text === '') return '';
    return function_exists('mb_substr') ? mb_substr($text, 0, intval($limit), 'UTF-8') : substr($text, 0, intval($limit));
}

function qifu_update_time($value, $fallback){
    if(is_numeric($value)){
        $time = intval($value);
        if($time > 20000000000) $time = intval($time / 1000);
        return $time > 0 ? $time : intval($fallback);
    }
    $parsed = $value !== null && $value !== '' ? strtotime((string)$value) : false;
    return $parsed !== false && $parsed > 0 ? intval($parsed) : intval($fallback);
}

function qifu_update_history_ensure(){
    global $DB;
    static $ready = false;
    if($ready) return true;
    $table = $DB->get_row("SHOW TABLES LIKE 'web_update_history'");
    if(empty($table)){
        if(defined('SQLITE')){
            $sql = 'CREATE TABLE IF NOT EXISTS `web_update_history` ('
                .'`id` INTEGER PRIMARY KEY AUTOINCREMENT,'
                .'`version_key` varchar(64) NOT NULL UNIQUE,'
                .'`version` varchar(64) NOT NULL,'
                .'`title` varchar(200) NOT NULL,'
                .'`details` text NOT NULL,'
                .'`published_at` int(11) NOT NULL,'
                .'`recorded_at` int(11) NOT NULL,'
                .'`source` varchar(20) NOT NULL)';
        } else {
            $sql = 'CREATE TABLE IF NOT EXISTS `web_update_history` ('
                .'`id` int(11) NOT NULL AUTO_INCREMENT,'
                .'`version_key` varchar(64) NOT NULL,'
                .'`version` varchar(64) NOT NULL,'
                .'`title` varchar(200) NOT NULL,'
                .'`details` text NOT NULL,'
                .'`published_at` int(11) NOT NULL,'
                .'`recorded_at` int(11) NOT NULL,'
                .'`source` varchar(20) NOT NULL,'
                .'PRIMARY KEY (`id`), UNIQUE KEY `version_key` (`version_key`)) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4';
        }
        if(!$DB->query($sql)) return false;
    }
    $ready = true;
    qifu_update_history_cleanup_retired_official();
    qifu_update_history_seed();
    return true;
}

function qifu_update_history_cleanup_retired_official(){
    global $DB;
    $retired = array(
        array('1.6.0', '祈福导航 V1.6.0 正式版')
    );
    foreach($retired as $entry){
        $DB->prepared_query(
            'DELETE FROM web_update_history WHERE version_key=? AND source=? AND title=?',
            array($entry[0], 'official', $entry[1])
        );
    }
}

function qifu_update_history_seed(){
    $entries = array(
        array('V1.5.0','2026-07-19','祈福导航 V1.5.0 正式版',array(
            '全面重构后台管理界面与登录页，统一导航、表格、表单、操作按钮及移动端布局。',
            '重构广告系统，支持全局开关、三个广告区域独立控制和板块内素材管理。',
            '完善站点与分类管理，支持网址信息自动获取、描述跑马灯、彩色图标与自定义图标。',
            '新增浏览量与点击量折线统计，可按日期查看各站点访问明细。',
            '升级友情链接申请体验，加入磨砂弹窗、提交反馈和邮件通知。',
            '接入远程公告与在线更新，支持版本检查、文件安全校验、更新进度、自动备份和日志留存。',
            '完善完整数据备份恢复、个人中心和系统信息页，修复重复刷新、统计遗漏与背景预览异常等问题。'
        ))
    );
    foreach($entries as $entry){
        qifu_update_history_save(array(
            'version'=>$entry[0], 'title'=>$entry[2], 'details'=>$entry[3],
            'published_at'=>strtotime($entry[1].' 12:00:00'), 'source'=>'official'
        ), false);
    }
}

function qifu_update_history_save($entry, $overwrite = true){
    global $DB;
    $key = qifu_update_version_key(isset($entry['version']) ? $entry['version'] : '');
    if($key === '') return false;
    $version = qifu_update_display_version($key);
    $title = qifu_update_text(isset($entry['title']) ? $entry['title'] : '', 200);
    if($title === '') $title = $version.' 更新';
    $details = isset($entry['details']) && is_array($entry['details']) ? $entry['details'] : array();
    $clean = array();
    foreach($details as $detail){
        $text = qifu_update_text($detail, 500);
        if($text !== '' && !in_array($text, $clean, true)) $clean[] = $text;
        if(count($clean) >= 20) break;
    }
    if(!$clean) $clean[] = '远程更新服务已发布此版本。';
    $published = qifu_update_time(isset($entry['published_at']) ? $entry['published_at'] : null, time());
    $requested_source = isset($entry['source']) ? (string)$entry['source'] : '';
    $source = in_array($requested_source, array('remote','official'), true) ? $requested_source : 'bundled';
    $existing = $DB->prepared_row('SELECT id,source FROM web_update_history WHERE version_key=?', array($key));
    if($existing){
        if(!$overwrite
            || ($existing['source'] === 'remote' && $source !== 'remote')
            || ($existing['source'] === 'official' && $source !== 'official')) return true;
        return (bool)$DB->prepared_query(
            'UPDATE web_update_history SET version=?,title=?,details=?,published_at=?,recorded_at=?,source=? WHERE version_key=?',
            array($version,$title,json_encode($clean, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$published,time(),$source,$key)
        );
    }
    return (bool)$DB->prepared_query(
        'INSERT INTO web_update_history (version_key,version,title,details,published_at,recorded_at,source) VALUES (?,?,?,?,?,?,?)',
        array($key,$version,$title,json_encode($clean, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$published,time(),$source)
    );
}

function qifu_update_remote_details($update){
    $details = array();
    foreach(array('changelog','release_notes','notes','details') as $field){
        if(!isset($update[$field])) continue;
        if(is_array($update[$field])){
            foreach($update[$field] as $item){
                if(is_array($item)) $item = isset($item['text']) ? $item['text'] : (isset($item['title']) ? $item['title'] : '');
                $details[] = $item;
            }
        } else {
            $parts = preg_split('/\r\n|\r|\n|(?:\s*[;；]\s*)/u', (string)$update[$field]);
            foreach($parts as $part) $details[] = preg_replace('/^[\s\-*•]+/u', '', $part);
        }
    }
    if(!$details){
        foreach(array('description','body','summary') as $field){
            if(isset($update[$field]) && !is_array($update[$field])) $details[] = $update[$field];
        }
    }
    return $details;
}

function qifu_update_history_sync_remote($remote){
    if(!is_array($remote) || !isset($remote['update']) || !is_array($remote['update'])) return false;
    if(!qifu_update_history_ensure()) return false;
    $update = $remote['update'];
    $published = null;
    foreach(array('published_at','released_at','date','updated_at') as $field){
        if(isset($update[$field]) && $update[$field] !== ''){ $published = $update[$field]; break; }
    }
    return qifu_update_history_save(array(
        'version'=>isset($update['version']) ? $update['version'] : '',
        'title'=>isset($update['title']) ? $update['title'] : '',
        'details'=>qifu_update_remote_details($update),
        'published_at'=>$published,
        'source'=>'remote'
    ));
}

function qifu_update_history_all($limit = 100){
    global $DB;
    if(!qifu_update_history_ensure()) return array();
    $rows = $DB->get_results('SELECT version,title,details,published_at,recorded_at,source FROM web_update_history ORDER BY published_at DESC,id DESC LIMIT '.max(1, min(200, intval($limit))));
    $history = array();
    foreach($rows as $row){
        $details = json_decode((string)$row['details'], true);
        $history[] = array(
            'version'=>(string)$row['version'],
            'date'=>date('Y-m-d', intval($row['published_at'])),
            'title'=>(string)$row['title'],
            'details'=>is_array($details) ? array_values($details) : array(),
            'source'=>(string)$row['source'],
            'recordedAt'=>intval($row['recorded_at'])
        );
    }
    return $history;
}

function qifu_update_status($remote){
    $history = qifu_update_history_all();
    $current = qifu_update_display_version(defined('QIFU_PRODUCT_VERSION') ? QIFU_PRODUCT_VERSION : '1.0.0');
    $remote_version = '';
    if(is_array($remote) && isset($remote['update']) && is_array($remote['update'])){
        $remote_version = qifu_update_display_version(isset($remote['update']['version']) ? $remote['update']['version'] : '');
    }
    $latest = $remote_version !== '' ? $remote_version : (isset($history[0]['version']) ? $history[0]['version'] : $current);
    return array(
        'currentVersion'=>$current,
        'latestVersion'=>$latest,
        'remoteVersion'=>$remote_version,
        'updateAvailable'=>$remote_version !== '' && version_compare(qifu_update_version_key($remote_version), qifu_update_version_key($current), '>'),
        'serviceAvailable'=>is_array($remote),
        'checkedAt'=>time(),
        'history'=>$history
    );
}
