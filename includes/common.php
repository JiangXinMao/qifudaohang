<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */
 
error_reporting(0);
define('CACHE_FILE', 0);
define('IN_CRONLITE', true);
define('SYSTEM_ROOT', dirname(__FILE__).'/');
define('ROOT', dirname(SYSTEM_ROOT).'/');
define('SYS_KEY', 'daishua_key');
define('CC_Defender', 1); //防CC攻击开关(1为session模式)
include_once(SYSTEM_ROOT.'admin_path.php');
include_once(SYSTEM_ROOT.'media_path.php');

date_default_timezone_set("PRC");
$date = date("Y-m-d H:i:s");
$https_request = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (isset($_SERVER['SERVER_PORT']) && intval($_SERVER['SERVER_PORT']) === 443);
if(session_status() === PHP_SESSION_NONE){
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_set_cookie_params(array('lifetime'=>0,'path'=>'/','secure'=>$https_request,'httponly'=>true,'samesite'=>'Strict'));
    session_start();
}
@header('X-Content-Type-Options: nosniff');
@header('X-Frame-Options: SAMEORIGIN');
@header('Referrer-Policy: same-origin');
@header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
@header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: https:; media-src 'self' https:; connect-src 'self'");

$script_name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$server_port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '';
$http_host = isset($_SERVER['HTTP_HOST']) ? trim((string)$_SERVER['HTTP_HOST']) : 'localhost';
if(defined('QIFU_CANONICAL_HOST') && QIFU_CANONICAL_HOST !== '') $http_host = (string)QIFU_CANONICAL_HOST;
if(!preg_match('/^(?:[A-Za-z0-9.-]+|\[[0-9A-Fa-f:]+\])(?::[0-9]{1,5})?$/D', $http_host)) $http_host = 'localhost';
$scriptpath=str_replace('\\','/',$script_name);
$sitepath_pos = strrpos($scriptpath, '/');
$sitepath = $sitepath_pos === false ? '' : substr($scriptpath, 0, $sitepath_pos);
$siteurl = ($server_port == '443' ? 'https://' : 'http://').$http_host.$sitepath.'/';
$admin_directory = qifu_admin_directory_name(ROOT);
$rootpath = qifu_site_base_path($sitepath, $admin_directory);
$rooturl = ($server_port == '443' ? 'https://' : 'http://').$http_host.$rootpath.'/';
$adminurl = $rooturl.qifu_admin_url_segment($admin_directory).'/';

if(defined('QIFU_ENABLE_LEGACY_WEBSCAN') && QIFU_ENABLE_LEGACY_WEBSCAN && is_file(SYSTEM_ROOT.'360safe/360webscan.php')){
    require_once(SYSTEM_ROOT.'360safe/360webscan.php');
}

if(!function_exists('dh_json_exit')) {
function dh_json_exit($msg, $code = 0) {
	if(defined('DH_JSON_RESPONSE') && DH_JSON_RESPONSE) {
		while(ob_get_level() > 0) @ob_end_clean();
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array('code'=>$code, 'msg'=>$msg), JSON_UNESCAPED_UNICODE);
		exit();
	}
}
}

// Keep normal requests away from partially replaced files during an online update.
$qifu_maintenance_file = ROOT.'.qifu-update/maintenance.json';
$qifu_api_action = basename((string)$script_name) === 'api.php' && isset($_GET['action']) ? (string)$_GET['action'] : '';
if(is_file($qifu_maintenance_file)){
	$maintenance_started = @filemtime($qifu_maintenance_file);
	if($maintenance_started !== false && $maintenance_started < time() - 1800){
		@unlink($qifu_maintenance_file);
	} elseif($qifu_api_action === 'update_progress'){
		if(empty($_SESSION['qifu_admin_auth'])){
			http_response_code(401);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(array('code'=>401, 'msg'=>'登录已失效，请重新登录', 'data'=>array()), JSON_UNESCAPED_UNICODE);
			exit();
		}
		$request_id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
		if(!preg_match('/^[A-Za-z0-9_-]{8,80}$/D', $request_id)){
			http_response_code(400);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(array('code'=>400, 'msg'=>'更新任务编号无效', 'data'=>array()), JSON_UNESCAPED_UNICODE);
			exit();
		}
		$progress_path = ROOT.'.qifu-update/progress/'.$request_id.'.json';
		$progress = is_file($progress_path) ? json_decode((string)@file_get_contents($progress_path), true) : null;
		if(!is_array($progress)) $progress = array('requestId'=>$request_id, 'phase'=>'overlay', 'percentage'=>60, 'message'=>'正在准备覆盖程序文件', 'status'=>'running', 'updatedAt'=>time());
		header('Content-Type: application/json; charset=UTF-8');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		echo json_encode(array('code'=>200, 'msg'=>'操作成功', 'data'=>$progress), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit();
	} elseif($qifu_api_action !== 'update_apply'){
		http_response_code(503);
		@header('Retry-After: 30');
		if(defined('DH_JSON_RESPONSE') && DH_JSON_RESPONSE){
			dh_json_exit('系统正在更新程序文件，请稍后重试', 503);
		}
		header('Content-Type: text/html; charset=UTF-8');
		echo '<!doctype html><meta charset="utf-8"><title>系统更新中</title><p style="font:16px/1.8 system-ui;padding:48px">系统正在更新程序文件，请稍后刷新。</p>';
		exit();
	}
}

// A release archive intentionally has no runtime config. Treat a stale lock
// without a readable config as an incomplete installation instead of letting
// require() turn the public homepage into a blank response.
$qifu_config_file = ROOT.'config.php';
function qifu_is_local_request_host($host) {
	$host = strtolower(preg_replace('/:\d+$/', '', trim((string)$host)));
	return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1';
}
function qifu_is_bundled_demo_config($file) {
	if(!is_file($file) || !is_readable($file)) return false;
	$content = (string)@file_get_contents($file, false, null, 0, 4096);
	return strpos($content, 'qifu_demo_v14.db') !== false
		|| strpos($content, "QIFU_CANONICAL_HOST', '127.0.0.1") !== false
		|| strpos($content, '"QIFU_CANONICAL_HOST", "127.0.0.1') !== false;
}
if(is_file($qifu_config_file) && qifu_is_bundled_demo_config($qifu_config_file) && !qifu_is_local_request_host($http_host)){
	if(defined('DH_JSON_RESPONSE') && DH_JSON_RESPONSE){
		dh_json_exit('祈福导航系统检测到演示配置，请访问 /install/ 重新安装');
	}
	@header('Location: '.$rooturl.'install/');
	exit();
}
if(!is_file(ROOT.'install/install.lock') || !is_file($qifu_config_file) || !is_readable($qifu_config_file)){
	if(defined('DH_JSON_RESPONSE') && DH_JSON_RESPONSE){
		dh_json_exit('祈福导航系统尚未安装，请先访问 /install/ 完成安装');
	}
	@header('Location: '.$rooturl.'install/');
	exit();
}

require $qifu_config_file;

if(!defined('SQLITE') && (!$dbconfig['user']||!$dbconfig['pwd']||!$dbconfig['dbname']))//检测安装
{
dh_json_exit('祈福导航系统配置缺失，请重新安装');
header('Content-type:text/html;charset=utf-8');
echo '祈福导航系统未完成安装！<a href="'.$rooturl.'install/">点此安装</a>';
exit();
}

//连接数据库
include_once(SYSTEM_ROOT."db.class.php");
$DB=new DB($dbconfig['host'],$dbconfig['user'],$dbconfig['pwd'],$dbconfig['dbname'],$dbconfig['port']);

if($DB->query("select * from web_config where 1")==FALSE)//检测安装2
{
dh_json_exit('祈福导航系统数据库未初始化，请先完成安装');
header('Content-type:text/html;charset=utf-8');
echo '祈福导航系统未完成安装！<a href="'.$rooturl.'install/">点此安装</a>';
exit();
}

include SYSTEM_ROOT.'cache.class.php';
$CACHE=new CACHE();
$conf=$CACHE->pre_fetch();//获取系统配置
$conf=qifu_media_normalize_config($conf, $rooturl);
$ad_defaults = array(
	'ad_enabled' => '0',
	'ad_position' => 'below_search',
	'ad_show_below' => '1',
	'ad_show_right' => '0',
	'ad_show_left' => '0',
	'ad_image' => '',
	'ad_link' => '',
	'ad_title' => '',
	'ad_alt' => '',
	'ad_image2' => '',
	'ad_link2' => '',
	'ad_title2' => '',
	'ad_alt2' => '',
	'ad_image3' => '',
	'ad_link3' => '',
	'ad_title3' => '',
	'ad_alt3' => '',
	'ad_image4' => '',
	'ad_link4' => '',
	'ad_title4' => '',
	'ad_alt4' => '',
	'ad_right_image' => '',
	'ad_right_link' => '',
	'ad_right_title' => '',
	'ad_right_alt' => '',
	'ad_left_image' => '',
	'ad_left_link' => '',
	'ad_left_title' => '',
	'ad_left_alt' => '',
	'ad_new_window' => '1'
);
$ad_need_update = false;
foreach($ad_defaults as $ad_key => $ad_value){
	if(!isset($conf[$ad_key])){
		$DB->query("REPLACE INTO web_config SET k='".$DB->escape($ad_key)."',v='".$DB->escape($ad_value)."'");
		$conf[$ad_key] = $ad_value;
		$ad_need_update = true;
	}
}
if($ad_need_update){
	$CACHE->clear();
	$conf=$CACHE->update();
}
include_once(SYSTEM_ROOT."function.php");
include_once(SYSTEM_ROOT."security.php");
include_once(SYSTEM_ROOT."ad_helper.php");
include_once(SYSTEM_ROOT."quick_tags.php");
qifu_ad_ensure_tables();
qifu_ad_ensure_config();
qifu_ad_seed_legacy();
@// mail.php 暂不全局加载，由具体功能页面按需引入
include_once(SYSTEM_ROOT."member.php");
include_once(SYSTEM_ROOT."version.php");
include_once(SYSTEM_ROOT."brand.php");
include_once(SYSTEM_ROOT."telemetry.php");

$qifu_config_changed = false;
if(!isset($conf['version']) || (string)$conf['version'] !== (string)VERSION){
	saveSetting('version', VERSION);
	$qifu_config_changed = true;
}
if(defined('SQLITE')){
	foreach(array('announcement','anounce','footer_text','title','description') as $versioned_key){
		if(isset($conf[$versioned_key]) && preg_match('/V1\.[0-3]/', (string)$conf[$versioned_key])){
			saveSetting($versioned_key, preg_replace('/V1\.[0-4](?:\.\d+)?/', QIFU_PRODUCT_VERSION, (string)$conf[$versioned_key]));
			$qifu_config_changed = true;
		}
	}
}
if($qifu_config_changed){
	$CACHE->clear();
	$conf = $CACHE->update();
}

$admin_script = str_replace('\\', '/', $script_name);
if(qifu_admin_request_is_admin($admin_script, ROOT, $admin_directory) && strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') === 'POST' && basename($admin_script) !== 'login.php'){
    qifu_require_csrf();
}

// Anonymous product-health event. The optional SDK queues failures and never
// changes the request response when Sodium or the remote service is absent.
qifu_telemetry_track_daily('app_ready', true);

?>
