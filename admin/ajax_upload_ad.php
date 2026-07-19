<?php
define('DH_JSON_RESPONSE', true);
include __DIR__ . "/../includes/common.php";

function ad_json($code, $msg, $url = '', $meta = array()){
    while(ob_get_level() > 0) @ob_end_clean();
    @header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array_merge(array('code'=>$code, 'msg'=>$msg, 'url'=>$url), (array)$meta), JSON_UNESCAPED_UNICODE);
    exit;
}

function ad_upload_error($code){
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

if($islogin!=1){
    ad_json(0, '请先登录后台');
}
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    ad_json(0, '请求方式错误');
}
qifu_require_csrf();
if(!isset($_FILES['file'])){
    ad_json(0, '没有收到上传文件');
}

$file = $_FILES['file'];
if($file['error'] != UPLOAD_ERR_OK){
    ad_json(0, ad_upload_error(intval($file['error'])));
}

$upload_dir = ROOT.'images/ad/';
$slot = isset($_POST['slot']) ? preg_replace('/[^a-z0-9_]+/i', '_', $_POST['slot']) : 'ad';
$positions = qifu_ad_positions();
$position = isset($_POST['position']) && isset($positions[$_POST['position']]) ? $_POST['position'] : 'below_search';
$upload_error = '';
$upload_info = array();
$filename = qifu_ad_upload_image($file, $upload_dir, $slot.'_'.$position, $position, $upload_error, $upload_info);
if($filename === false) ad_json(0, $upload_error);
ad_json(1, isset($upload_info['message']) ? $upload_info['message'] : '上传成功', qifu_media_upload_url('images/ad/'.$filename, $rooturl), $upload_info);
?>
