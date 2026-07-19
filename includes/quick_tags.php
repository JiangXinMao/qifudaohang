<?php
if(!defined('IN_CRONLITE')) exit();

function qifu_quick_tags_defaults(){
    return array(
        array('name'=>'GitHub 趋势', 'url'=>'https://github.com/trending'),
        array('name'=>'掘金', 'url'=>'https://juejin.cn'),
        array('name'=>'Product Hunt', 'url'=>'https://producthunt.com'),
        array('name'=>'少数派', 'url'=>'https://sspai.com')
    );
}

function qifu_quick_tag_name($value){
    $value = trim(strip_tags((string)$value));
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    if(!is_string($value)) return '';
    if(function_exists('mb_substr')) return mb_substr($value, 0, 30, 'UTF-8');
    return substr($value, 0, 90);
}

function qifu_quick_tag_url($value){
    $value = trim((string)$value);
    if(strlen($value) > 500) return '';
    if(!preg_match('#^https?://#i', $value)) return '';
    return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
}

function qifu_quick_tags_from_input($names, $urls, &$invalid_count = 0){
    $invalid_count = 0;
    if(!is_array($names)) $names = array();
    if(!is_array($urls)) $urls = array();
    $tags = array();
    $row_count = min(max(count($names), count($urls)), 50);
    for($i = 0; $i < $row_count; $i++){
        $raw_name = isset($names[$i]) ? $names[$i] : '';
        $raw_url = isset($urls[$i]) ? $urls[$i] : '';
        $name = qifu_quick_tag_name($raw_name);
        $url = qifu_quick_tag_url($raw_url);
        if($name === '' && trim((string)$raw_url) === '') continue;
        if($name === '' || $url === ''){
            $invalid_count++;
            continue;
        }
        if(count($tags) >= 12){
            $invalid_count++;
            continue;
        }
        $tags[] = array('name'=>$name, 'url'=>$url);
    }
    return $tags;
}

function qifu_quick_tags_from_config($conf){
    if(!is_array($conf) || !array_key_exists('quick_tags', $conf)) return qifu_quick_tags_defaults();
    $rows = json_decode((string)$conf['quick_tags'], true);
    if(!is_array($rows)) return qifu_quick_tags_defaults();
    $names = array();
    $urls = array();
    foreach($rows as $row){
        if(!is_array($row)) continue;
        $names[] = isset($row['name']) ? $row['name'] : '';
        $urls[] = isset($row['url']) ? $row['url'] : '';
    }
    $invalid_count = 0;
    return qifu_quick_tags_from_input($names, $urls, $invalid_count);
}

function qifu_quick_tags_encode($tags){
    $json = json_encode(array_values((array)$tags), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '[]';
}
?>
