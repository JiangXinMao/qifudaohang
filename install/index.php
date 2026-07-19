<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */
error_reporting(0);
@header('Content-Type: text/html; charset=UTF-8');
define('QIFU_INSTALL_CONTEXT', true);
require __DIR__.'/helpers.php';
if(session_status() === PHP_SESSION_NONE){
	ini_set('session.use_only_cookies', '1');
	ini_set('session.use_strict_mode', '1');
	session_set_cookie_params(array('lifetime'=>0,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off'),'httponly'=>true,'samesite'=>'Strict'));
	session_start();
}
$do=isset($_GET['do'])?$_GET['do']:'0';
$qifu_version = 'V1.5.0';
$lock_file = __DIR__.'/install.lock';
$installed=false;

function checkfunc($f,$m = false) {
	if (function_exists($f)) {
		return '<font color="green">可用</font>';
	} else {
		if ($m == false) {
			return '<font color="black">不支持</font>';
		} else {
			return '<font color="red">不支持</font>';
		}
	}
}

function checkclass($f,$m = false) {
	if (class_exists($f)) {
		return '<font color="green">可用</font>';
	} else {
		if ($m == false) {
			return '<font color="black">不支持</font>';
		} else {
			return '<font color="red">不支持</font>';
		}
	}
}

function build_config($db_host, $db_port, $db_user, $db_pwd, $db_name) {
	$config = array(
		'host' => $db_host,
		'port' => intval($db_port),
		'user' => $db_user,
		'pwd' => $db_pwd,
		'dbname' => $db_name,
	);
	return "<?php\n/* 数据库配置：由安装程序自动生成 */\n\$dbconfig=".var_export($config, true).";\n?>";
}

$root_dir = dirname(__DIR__);
require_once $root_dir.'/includes/admin_path.php';
$config_file = $root_dir.'/config.php';
function qifu_install_is_bundled_demo_config($file) {
	if(!is_file($file) || !is_readable($file)) return false;
	$content = (string)@file_get_contents($file, false, null, 0, 4096);
	return strpos($content, 'qifu_demo_v14.db') !== false
		|| strpos($content, "QIFU_CANONICAL_HOST', '127.0.0.1") !== false
		|| strpos($content, '"QIFU_CANONICAL_HOST", "127.0.0.1') !== false;
}
// The lock is only authoritative when the generated database config exists.
// This lets a package with a stale lock recover through the installer.
$installed = is_file($lock_file) && is_file($config_file) && is_readable($config_file) && !qifu_install_is_bundled_demo_config($config_file);
if($installed) $do='0';
$php_ok = version_compare(PHP_VERSION, '8.2.0', '>=');
$db_ext_ok = extension_loaded('mysqli') || extension_loaded('pdo_mysql');
$mb_ok = function_exists('mb_strlen');
$curl_ok = function_exists('curl_exec');
$file_get_ok = function_exists('file_get_contents');
$config_writable = (is_file($config_file) && is_writable($config_file)) || (!file_exists($config_file) && is_writable($root_dir));
$install_writable = is_file($lock_file) || (!file_exists($lock_file) && is_writable(__DIR__));
$env_ok = $php_ok && $db_ext_ok && $mb_ok && $curl_ok && $file_get_ok && $config_writable && $install_writable;

function checkok($ok) {
	return $ok ? '<font color="green">可用</font>' : '<font color="red">不支持</font>';
}

function write_install_lock() {
	return qifu_install_write_lock(__DIR__.'/install.lock');
}

function install_csrf_token() {
	if(empty($_SESSION['qifu_install_csrf']) || !is_string($_SESSION['qifu_install_csrf'])) {
		$_SESSION['qifu_install_csrf'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['qifu_install_csrf'];
}

function install_csrf_input() {
	return '<input type="hidden" name="_csrf" value="'.htmlspecialchars(install_csrf_token(), ENT_QUOTES, 'UTF-8').'">';
}

function install_require_post() {
	if($_SERVER['REQUEST_METHOD'] !== 'POST') {
		http_response_code(405);
		exit('安装写操作只允许使用 POST 请求。');
	}
	$token = isset($_POST['_csrf']) ? (string)$_POST['_csrf'] : '';
	if($token === '' || !hash_equals(install_csrf_token(), $token)) {
		http_response_code(403);
		exit('安装安全令牌无效，请返回并刷新页面后重试。');
	}
}

function install_action_form($step, $label, $class, $confirm = '') {
	$onsubmit = $confirm === '' ? '' : ' onsubmit="return confirm('.htmlspecialchars(json_encode($confirm, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8').')"';
	return '<form action="?do='.intval($step).'" method="post"'.$onsubmit.'>'.install_csrf_input().'<button type="submit" class="'.htmlspecialchars($class, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</button></form>';
}

function install_success_message($account_text) {
	$admin_directory = qifu_admin_directory_name(dirname(__DIR__), true);
	$result = qifu_install_completion(__DIR__.'/install.lock', $account_text, $admin_directory);
	if(!$result['success']) http_response_code(500);
	return $result['html'];
}

if(!$installed){
	if(in_array((string)$do, array('4','5','6'), true)) install_require_post();
	if((string)$do === '3' && !defined('SAE_ACCESSKEY') && !(isset($_GET['jump']) && $_GET['jump'] == 1)) install_require_post();
	if((string)$do === '5' && empty($_SESSION['qifu_install_ready'])){
		http_response_code(409);
		exit('安装步骤无效，请从数据库安装步骤重新开始。');
	}
	if((string)$do === '6' && empty($_SESSION['qifu_install_existing_ready'])){
		http_response_code(409);
		exit('尚未验证现有数据库，请返回数据库配置步骤。');
	}
}

$install_csrf_html = install_csrf_input();
$install_step_key = in_array((string)$do, array('0','1','2','3','4','5','6'), true) ? (string)$do : '0';
$install_step_labels = array(
	'0' => '安装说明',
	'1' => '步骤 1/5 · 环境检查',
	'2' => '步骤 2/5 · 数据库配置',
	'3' => '步骤 3/5 · 验证数据库',
	'4' => '步骤 4/5 · 创建数据表',
	'5' => '步骤 5/5 · 安装完成',
	'6' => '步骤 5/5 · 安装完成',
);
$install_step_label = $install_step_labels[$install_step_key];

?>


<html lang="zh-cn">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<meta name="viewport" content="initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0,user-scalable=no,minimal-ui">
<title>安装程序 - 祈福导航系统</title>
<link href="//cdn.bootcss.com/bootstrap/3.3.5/css/bootstrap.min.css" rel="stylesheet"/>
<style>
body{background:#f4f7fb;color:#263238}
.install-wrap{padding-top:74px;padding-bottom:40px}
.install-card{border:0;border-radius:8px;box-shadow:0 12px 36px rgba(30,68,120,.12);overflow:hidden}
.install-card .panel-heading{background:#12a53b!important;border:0;padding:16px 20px}
.install-card .panel-title{font-size:20px;font-weight:700}
.install-hero{background:linear-gradient(135deg,#f7fff9,#eef8ff);padding:28px 32px;border-bottom:1px solid #e7eef5}
.install-hero h1{font-size:28px;margin:0 0 12px;font-weight:700;color:#14324a}
.install-hero p{font-size:15px;line-height:1.8;margin:0;color:#51606d}
.install-brand{margin-top:16px;padding:12px 14px;background:#fff;border:1px solid #dce9f3;border-radius:6px;color:#31475a;font-size:14px;line-height:1.7}
.install-brand a{color:#0d8f36;font-weight:700;word-break:break-all}
.feature-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:22px}
.feature-item{background:#fff;border:1px solid #e5edf3;border-radius:8px;padding:16px;min-height:98px}
.feature-item b{display:block;color:#17324d;margin-bottom:8px;font-size:15px}
.feature-item span{display:block;color:#6c7a86;line-height:1.6;font-size:13px}
.install-section{padding:24px 32px}
.install-section h3{font-size:17px;margin:0 0 14px;color:#17324d;font-weight:700}
.install-list{padding-left:18px;line-height:2;color:#44515c}
.env-list{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:0;padding:0;list-style:none}
.env-list li{background:#f8fafc;border:1px solid #e7edf3;border-radius:6px;padding:10px 12px}
.start-actions{text-align:center;padding:0 32px 30px}
.start-actions .btn{min-width:180px;border-radius:4px;font-weight:700;padding:11px 24px}
@media(max-width:768px){.feature-grid,.env-list{grid-template-columns:1fr}.install-hero,.install-section{padding:22px 18px}}
body{min-height:100vh;background:radial-gradient(circle at 12% 8%,rgba(38,166,154,.18),transparent 28%),radial-gradient(circle at 88% 4%,rgba(47,128,237,.16),transparent 30%),linear-gradient(180deg,#f8fbff 0,#eef5fb 100%);font-family:"Microsoft YaHei","PingFang SC",Arial,sans-serif}
.navbar-default{background:rgba(255,255,255,.84);border:0;border-bottom:1px solid rgba(203,218,232,.75);backdrop-filter:blur(14px);box-shadow:0 10px 30px rgba(31,60,95,.06)}
.navbar-default .navbar-brand{font-weight:900;color:#17324d}
.navbar-default .navbar-brand{font-size:0}
.navbar-default .navbar-brand:after{content:"祈福导航系统安装向导";font-size:18px}
.install-card{border-radius:22px;box-shadow:0 24px 70px rgba(30,68,120,.14)}
.install-card .panel-heading{background:linear-gradient(135deg,#14324d,#0f9f8f)!important;padding:18px 22px}
.install-card .panel-title{letter-spacing:.08em}
.install-card .panel-title{font-size:0!important}
.install-card .panel-title:after{content:"安装说明";font-size:20px}
.install-hero{position:relative;background:linear-gradient(135deg,#f5fffb,#eef8ff 58%,#fffaf0);padding:36px 38px 30px;overflow:hidden}
.install-hero:before{content:"";position:absolute;right:-80px;top:-90px;width:260px;height:260px;border-radius:50%;background:linear-gradient(135deg,rgba(17,199,163,.18),rgba(47,128,237,.12))}
.install-hero h1{position:relative;font-size:32px;line-height:1.25;margin-bottom:14px;color:#102a43}
.install-hero>h1{display:none}
.install-title-modern{position:relative;margin-bottom:14px}
.install-title-modern h1{display:block!important;font-size:32px;line-height:1.25;margin:0;color:#102a43;font-weight:900}
.version-pill{display:inline-flex;align-items:center;gap:8px;margin-left:10px;padding:8px 13px;border-radius:999px;background:linear-gradient(135deg,#ffb020,#ff6b35);color:#fff;font-size:15px;font-weight:900;vertical-align:middle;box-shadow:0 12px 26px rgba(255,107,53,.25)}
.release-strip{position:relative;display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:20px}
.release-item{border:1px solid rgba(186,210,229,.72);border-radius:16px;background:rgba(255,255,255,.72);padding:14px 16px;box-shadow:0 10px 24px rgba(50,90,130,.06)}
.release-item b{display:block;color:#12304a;font-size:15px}
.release-item span{display:block;color:#657589;font-size:12px;margin-top:5px}
.install-brand{position:relative;border-radius:14px;border-color:#d8e8f4;box-shadow:0 10px 26px rgba(45,80,120,.05)}
.feature-item{border-radius:16px;transition:.22s;box-shadow:0 10px 24px rgba(45,80,120,.045)}
.feature-item:hover{transform:translateY(-2px);border-color:#abd7ee;box-shadow:0 18px 34px rgba(45,110,155,.10)}
.env-list li{border-radius:12px;background:#fff;box-shadow:0 8px 18px rgba(50,80,110,.04)}
.start-actions .btn,.btn-primary{border:0;border-radius:14px;background:linear-gradient(135deg,#13b7d7,#2f80ed)!important;box-shadow:0 14px 28px rgba(47,128,237,.25);font-weight:800}
.start-actions .btn:hover,.btn-primary:hover{transform:translateY(-1px);box-shadow:0 18px 34px rgba(47,128,237,.32)}
.progress{height:10px;border-radius:999px;box-shadow:none;background:#e8f0f7}
.progress-bar-success{background:linear-gradient(90deg,#11c7a3,#2f80ed)}
@media(max-width:768px){.release-strip{grid-template-columns:1fr}.version-pill{display:inline-flex;margin:10px 0 0}.install-hero h1{font-size:25px}}

/* Minimal install wizard skin. */
:root {
  --install-bg: #f6f8fb;
  --install-card: #fff;
  --install-line: #e8edf3;
  --install-text: #17233d;
  --install-muted: #697386;
  --install-primary: #16a34a;
  --install-primary-soft: #ecfdf3;
  --install-danger: #ef4444;
  --install-warning: #f59e0b;
  --install-radius: 18px;
}

html,
body {
  min-height: 100vh;
  background: var(--install-bg) !important;
  color: var(--install-text);
  font-family: "Source Han Sans SC", "Noto Sans SC", "PingFang SC", "Microsoft YaHei UI", "Microsoft YaHei", Arial, sans-serif !important;
}

.navbar-default {
  min-height: 66px;
  background: rgba(255,255,255,.96) !important;
  border: 0 !important;
  border-bottom: 1px solid var(--install-line) !important;
  box-shadow: none !important;
  backdrop-filter: blur(12px);
}

.navbar-default .container {
  min-height: 66px;
  display: flex;
  align-items: center;
}

.navbar-default .navbar-header {
  float: none;
}

.navbar-default .navbar-brand {
  height: auto;
  padding: 0;
  display: inline-flex;
  align-items: center;
  gap: 12px;
  color: var(--install-text) !important;
  font-size: 0 !important;
  font-weight: 600 !important;
  line-height: 1;
}

.navbar-default .navbar-brand:before {
  content: "";
  width: 36px;
  height: 36px;
  display: inline-block;
  border-radius: 12px;
  background: linear-gradient(135deg, #22c55e, #16a34a);
  box-shadow: 0 8px 20px rgba(22, 163, 74, .18);
}

.navbar-default .navbar-brand:after {
  content: "祈福导航安装向导" !important;
  color: var(--install-text);
  font-size: 18px !important;
  letter-spacing: .01em;
}

.install-wrap {
  width: 100%;
  max-width: 1040px;
  padding-top: 32px !important;
  padding-bottom: 56px !important;
}

.install-wrap > .center-block {
  max-width: 960px;
}

.install-wrap .panel,
.install-card {
  overflow: hidden;
  border: 1px solid var(--install-line) !important;
  border-radius: var(--install-radius) !important;
  background: var(--install-card) !important;
  box-shadow: 0 12px 36px rgba(15, 23, 42, .06) !important;
}

.install-wrap .panel-heading,
.install-card .panel-heading {
  padding: 22px 28px !important;
  border: 0 !important;
  border-bottom: 1px solid var(--install-line) !important;
  background: #fff !important;
}

.install-wrap .panel-title,
.install-card .panel-title {
  margin: 0;
  color: var(--install-text) !important;
  font-size: 19px !important;
  font-weight: 600 !important;
  letter-spacing: 0 !important;
  text-align: left !important;
}

.install-card .panel-title:after {
  display: none !important;
  content: none !important;
}

.install-wrap .panel-title:before {
  content: "";
  width: 8px;
  height: 8px;
  display: inline-block;
  margin-right: 10px;
  border-radius: 50%;
  background: var(--install-primary);
  vertical-align: 2px;
  box-shadow: 0 0 0 5px rgba(22, 163, 74, .10);
}

.install-wrap .panel-body {
  padding: 28px !important;
}

.install-hero {
  position: relative;
  padding: 34px 36px !important;
  background: #fff !important;
  border-bottom: 1px solid var(--install-line) !important;
}

.install-hero:before {
  display: none !important;
}

.install-title-modern h1,
.install-hero h1 {
  color: var(--install-text) !important;
  font-size: 30px !important;
  font-weight: 600 !important;
  letter-spacing: -.02em;
}

.install-hero p {
  max-width: 780px;
  color: var(--install-muted) !important;
  font-size: 15px !important;
  line-height: 1.9 !important;
}

.version-pill {
  padding: 5px 10px !important;
  border: 1px solid #bbf7d0;
  background: var(--install-primary-soft) !important;
  color: var(--install-primary) !important;
  box-shadow: none !important;
  font-size: 12px !important;
  font-weight: 700 !important;
}

.release-strip,
.feature-grid,
.env-list {
  gap: 12px !important;
}

.release-item,
.feature-item,
.env-list li,
.install-brand {
  border: 1px solid var(--install-line) !important;
  border-radius: 14px !important;
  background: #fff !important;
  box-shadow: none !important;
}

.feature-item:hover {
  transform: none !important;
  border-color: #bbf7d0 !important;
  box-shadow: none !important;
}

.release-item b,
.feature-item b,
.install-section h3 {
  color: var(--install-text) !important;
  font-weight: 600 !important;
}

.release-item span,
.feature-item span,
.install-list,
.env-list li {
  color: var(--install-muted) !important;
}

.install-section {
  padding: 28px 36px !important;
}

.install-list {
  padding-left: 22px !important;
  line-height: 2.05 !important;
}

.progress {
  height: 4px !important;
  margin: 0 28px !important;
  border-radius: 999px !important;
  background: #f1f5f9 !important;
  box-shadow: none !important;
  overflow: hidden;
}

.progress-bar-success {
  background: var(--install-primary) !important;
  box-shadow: none !important;
}

.table {
  margin-bottom: 20px;
  border: 1px solid var(--install-line);
  border-radius: 14px;
  overflow: hidden;
  background: #fff;
}

.table > thead > tr > th {
  border: 0 !important;
  background: #f8fafc !important;
  color: var(--install-muted);
  font-size: 13px;
  font-weight: 500;
}

.table > tbody > tr > td {
  padding: 15px 18px !important;
  border-top: 1px solid var(--install-line) !important;
  color: var(--install-text);
  vertical-align: middle !important;
}

.table-striped > tbody > tr:nth-of-type(odd) {
  background: #fff !important;
}

.form-sign {
  max-width: 560px;
  margin: 0 auto;
}

.form-sign label {
  margin-top: 15px;
  margin-bottom: 8px;
  color: var(--install-text);
  font-weight: 500;
}

.form-control {
  height: 44px;
  border: 1px solid #d8dee8;
  border-radius: 12px;
  box-shadow: none !important;
  color: var(--install-text);
}

.form-control:focus {
  border-color: #86efac;
  box-shadow: 0 0 0 3px rgba(34, 197, 94, .12) !important;
}

.btn {
  border: 0 !important;
  border-radius: 12px !important;
  padding: 10px 18px !important;
  font-weight: 500 !important;
  box-shadow: none !important;
  transition: background-color .16s ease, color .16s ease, border-color .16s ease !important;
}

.btn-primary,
.start-actions .btn {
  background: var(--install-primary) !important;
  color: #fff !important;
}

.btn-primary:hover,
.start-actions .btn:hover {
  transform: none !important;
  background: #15803d !important;
  box-shadow: none !important;
}

.btn-info {
  background: #e0f2fe !important;
  color: #0369a1 !important;
}

.btn-warning {
  background: #fff7ed !important;
  color: #c2410c !important;
}

.alert {
  border: 1px solid transparent !important;
  border-radius: 14px !important;
  box-shadow: none !important;
  line-height: 1.8;
}

.alert-success,
.alert-info {
  border-color: #bbf7d0 !important;
  background: var(--install-primary-soft) !important;
  color: #166534 !important;
}

.alert-warning {
  border-color: #fed7aa !important;
  background: #fff7ed !important;
  color: #9a3412 !important;
}

.qifu-admin-path-warning {
  margin-top: 16px !important;
  border: 1px solid #fed7aa !important;
  background: #fff7ed !important;
  font-weight: 600;
}

.qifu-admin-path-warning code {
  color: #9a3412;
  background: rgba(255,255,255,.72);
}

.alert-danger {
  border-color: #fecaca !important;
  background: #fef2f2 !important;
  color: #991b1b !important;
}

.list-group-item {
  border-color: var(--install-line) !important;
}

.start-actions {
  padding: 0 36px 34px !important;
}

.install-wrap a {
  color: var(--install-primary);
  text-decoration: none !important;
}

.install-wrap a:hover {
  color: #15803d;
}

.install-wrap font[color="green"],
.install-wrap font[color=green] {
  color: var(--install-primary) !important;
  font-weight: 600;
}

.install-wrap font[color="red"],
.install-wrap font[color=red],
.install-wrap font[color="#FF0033"] {
  color: var(--install-danger) !important;
  font-weight: 600;
}

.install-wrap font[color="black"],
.install-wrap font[color=black] {
  color: var(--install-muted) !important;
}

.install-wrap .panel > p,
.install-wrap .panel-body > p {
  margin: 20px 28px 0;
  min-height: 44px;
}

.install-wrap .panel-body > p {
  margin-left: 0;
  margin-right: 0;
}

.install-wrap .panel > .alert {
  margin: 20px 28px 28px !important;
}

.install-wrap .panel-body > .alert:first-child {
  margin-top: 0;
}

.install-wrap .btn-block {
  height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.list-group-item {
  margin: 0 0 12px;
  border-radius: 14px !important;
  background: #fff !important;
}

.list-group-item-info {
  border-color: #bfdbfe !important;
  background: #eff6ff !important;
  color: #1d4ed8 !important;
}

.install-wrap hr {
  margin: 18px 0;
  border-top-color: var(--install-line);
}

.install-wrap .panel-body > br,
.form-sign + br {
  display: none;
}

.form-sign + br + * {
  display: inline-block;
  margin-top: 18px;
  color: var(--install-muted);
  font-size: 14px;
}

@media (max-width: 768px) {
  .navbar-default .container {
    padding-left: 18px;
    padding-right: 18px;
  }
  .install-wrap {
    padding-top: 18px !important;
    padding-left: 12px;
    padding-right: 12px;
  }
  .install-wrap .panel-heading,
  .install-wrap .panel-body,
  .install-hero,
  .install-section {
    padding: 22px !important;
  }
  .install-title-modern h1,
  .install-hero h1 {
    font-size: 24px !important;
  }
  .release-strip,
  .feature-grid,
  .env-list {
    grid-template-columns: 1fr !important;
  }
}

/* Selected scheme 4: top-stage installer layout. */
body.qifu-install-top {
  min-width: 320px;
  background: #f6f8fb !important;
  color: #252b3f;
  font-family: "Microsoft YaHei", "PingFang SC", "Noto Sans CJK SC", system-ui, sans-serif !important;
}

.qifu-install-page,
.qifu-install-page * {
  box-sizing: border-box;
}

.qifu-install-page {
  width: 100%;
  max-width: 1480px;
  margin: 0 auto;
  padding: 22px;
}

.qifu-a-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  min-height: 70px;
  padding: 12px 20px;
  color: #252b3f;
  border: 1px solid #e6eaeb;
  border-radius: 0;
  background: #fff;
}

.qifu-a-brand,
.qifu-a-state {
  display: flex;
  align-items: center;
}

.qifu-a-brand { gap: 10px; min-width: 0; }
.qifu-a-brand img { width: 34px; height: 34px; object-fit: contain; }
.qifu-a-brand strong { display: block; font-size: 15px; font-weight: 650; }
.qifu-a-brand small { display: block; margin-top: 3px; color: #7987a1; font-size: 11px; }
.qifu-a-state { gap: 8px; color: #7987a1; font-size: 12px; white-space: nowrap; }
.qifu-a-state i { width: 7px; height: 7px; border-radius: 50%; background: #16a36a; }

.qifu-a-grid {
  display: block;
  min-height: 680px;
  border: 1px solid #e6eaeb;
  border-top: 0;
  background: #fff;
}

.qifu-a-nav {
  min-width: 0;
  padding: 10px 18px;
  border-bottom: 1px solid #e6eaeb;
  background: #fff;
}

.qifu-a-nav-title {
  display: none;
}

.qifu-a-steps {
  display: flex;
  flex-direction: row;
  gap: 4px;
  margin: 0;
  padding: 0;
  list-style: none;
  overflow-x: auto;
}

.qifu-a-step {
  flex: 1 0 145px;
  display: grid;
  grid-template-columns: 28px minmax(0, 1fr) 16px;
  gap: 9px;
  align-items: center;
  min-height: 48px;
  padding: 7px 9px;
  color: #4d5875;
  border-bottom: 2px solid transparent;
  border-radius: 0;
}

.qifu-a-step.active { color: #3468f5; border-bottom-color: #5d87ff; background: transparent; }
.qifu-a-step-no {
  display: grid;
  width: 28px;
  height: 28px;
  place-items: center;
  border: 1px solid #d5dbe5;
  border-radius: 6px;
  color: #7987a1;
  background: #fff;
  font-size: 11px;
  font-weight: 700;
}
.qifu-a-step.done .qifu-a-step-no { color: #fff; border-color: #16a36a; background: #16a36a; }
.qifu-a-step strong { display: block; font-size: 13px; font-weight: 600; }
.qifu-a-step small { display: block; margin-top: 2px; color: #7987a1; font-size: 10px; }
.qifu-a-step-check { color: #16a36a; font-size: 13px; }

.qifu-a-main {
  min-width: 0;
  width: 100%;
  max-width: 1080px;
  margin: 0 auto;
  padding: 40px 52px 58px;
}

.qifu-a-main .install-wrap {
  width: 100%;
  max-width: none !important;
  margin: 0;
  padding: 0 !important;
}

.qifu-a-main .install-wrap > .center-block {
  width: 100%;
  max-width: none;
  margin: 0;
  padding: 0;
}

.qifu-a-main .panel,
.qifu-a-main .install-card {
  margin: 0;
  border: 0 !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  background: transparent !important;
}

.qifu-a-main .panel-heading,
.qifu-a-main .install-card .panel-heading {
  padding: 0 0 12px !important;
  border: 0 !important;
  background: transparent !important;
}

.qifu-a-main .panel-title,
.qifu-a-main .install-card .panel-title {
  margin: 0;
  color: #252b3f !important;
  font-size: 24px !important;
  font-weight: 650 !important;
  letter-spacing: 0 !important;
  text-align: left !important;
}

.qifu-a-main .panel-title:before { display: none !important; }
.qifu-a-main .panel-body { padding: 0 !important; }
.qifu-a-main .install-card .panel-body { padding: 0 !important; }
.qifu-a-main .install-hero { padding: 0 0 22px !important; border-bottom: 1px solid #e6eaeb; background: transparent !important; }
.qifu-a-main .install-hero:before { display: none !important; }
.qifu-a-main .install-title-modern h1 { margin: 0 0 8px; color: #252b3f !important; font-size: 22px !important; font-weight: 650 !important; }
.qifu-a-main .install-hero p { max-width: 72ch; margin: 0; color: #7987a1 !important; font-size: 13px !important; line-height: 1.7 !important; }
.qifu-a-main .release-strip {
  margin-top: 18px;
  gap: 0 !important;
  border-top: 1px solid #e6eaeb;
  border-bottom: 1px solid #e6eaeb;
}
.qifu-a-main .release-item {
  padding: 14px 16px;
  border: 0 !important;
  border-right: 1px solid #e6eaeb !important;
  border-radius: 0 !important;
}
.qifu-a-main .release-item:last-child { border-right: 0 !important; }
.qifu-a-main .install-brand {
  padding: 13px 0;
  border: 0 !important;
  border-bottom: 1px solid #e6eaeb !important;
  border-radius: 0 !important;
}
.qifu-a-main .feature-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 !important;
  border-top: 1px solid #e6eaeb;
}
.qifu-a-main .feature-item {
  min-height: 0;
  padding: 15px 16px;
  border: 0 !important;
  border-bottom: 1px solid #e6eaeb !important;
  border-radius: 0 !important;
}
.qifu-a-main .feature-item:nth-child(odd) { border-right: 1px solid #e6eaeb !important; }
.qifu-a-main .env-list li { border-color: #e6eaeb !important; border-radius: 7px !important; box-shadow: none !important; }
.qifu-a-main .release-item,
.qifu-a-main .feature-item,
.qifu-a-main .install-brand { box-shadow: none !important; }
.qifu-a-main .feature-item:hover { transform: none !important; }
.qifu-a-main .install-section { padding: 22px 0 !important; }
.qifu-a-main .install-section h3 { margin: 0 0 12px; color: #252b3f !important; font-size: 14px !important; }
.qifu-a-main .progress { height: 6px !important; margin: 0 0 20px !important; border-radius: 3px !important; background: #edf0f5 !important; }
.qifu-a-main .progress-bar-success { background: #5d87ff !important; }
.qifu-a-main .table { width: 100%; margin-bottom: 20px; border-color: #e6eaeb; border-radius: 7px; }
.qifu-a-main .table > thead > tr > th { background: #f5f7fa !important; color: #7987a1; }
.qifu-a-main .table > tbody > tr > td { color: #4d5875; }
.qifu-a-main .form-sign { max-width: 640px; margin: 0; }
.qifu-a-main .form-sign label { margin-top: 14px; color: #4d5875; font-size: 12px; font-weight: 600; }
.qifu-a-main .form-control { height: 42px; border-color: #dfe4eb; border-radius: 6px; }
.qifu-a-main .btn { min-height: 38px; border-radius: 6px !important; }
.qifu-a-main .btn-primary { background: #5d87ff !important; }
.qifu-a-main .btn-primary:hover { background: #3468f5 !important; }
.qifu-a-main .alert { margin-left: 0 !important; margin-right: 0 !important; border-radius: 6px !important; }
.qifu-a-main .list-group-item { border-radius: 7px !important; }
.qifu-a-main .start-actions { padding: 20px 0 0 !important; text-align: left; }
.qifu-a-main .start-actions .btn { min-width: 150px; }
.qifu-a-main .install-wrap .panel > p { margin: 18px 0 0; }
.qifu-a-main .install-wrap .panel-body > p { min-height: 0; }
.qifu-a-main .install-wrap .panel-body > p form { display: inline-block; }
.qifu-a-main .install-wrap .panel-body > p[align="right"] { text-align: right; }
.qifu-a-main .install-wrap table { max-width: 100%; overflow-wrap: anywhere; }

.qifu-a-inspector {
  display: none;
}

.qifu-a-inspector h3 { margin: 0 0 14px; color: #252b3f; font-size: 13px; }
.qifu-a-inspector-group { padding: 12px 0; border-top: 1px solid #e6eaeb; }
.qifu-a-inspector-group:first-of-type { padding-top: 0; border-top: 0; }
.qifu-a-inspector-group > div { display: flex; justify-content: space-between; gap: 12px; padding: 5px 0; color: #7987a1; font-size: 11px; }
.qifu-a-inspector-group strong { color: #4d5875; font-weight: 600; text-align: right; }
.qifu-a-inspector-group .qifu-a-ok { color: #16a36a; }
.qifu-a-tip { padding: 11px; color: #2854a8; border-radius: 6px; background: #eef3ff; font-size: 11px; line-height: 1.6; }

.qifu-a-foot {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  padding: 12px 18px;
  color: #7987a1;
  border: 1px solid #e6eaeb;
  border-top: 0;
  background: #fff;
  font-size: 11px;
}

@media (max-width: 980px) {
  .qifu-install-page { padding: 12px; }
  .qifu-a-steps { padding-bottom: 2px; }
  .qifu-a-step { flex: 0 0 155px; }
  .qifu-a-main { padding: 30px 28px 44px; }
}

@media (max-width: 640px) {
  .qifu-a-head { align-items: flex-start; flex-direction: column; gap: 10px; }
  .qifu-a-state span:last-child { display: none; }
  .qifu-a-main { padding: 18px 16px; }
  .qifu-a-foot { flex-direction: column; gap: 3px; }
  .qifu-a-main .panel-title { font-size: 20px !important; }
  .qifu-a-main .table { display: block; overflow-x: auto; white-space: nowrap; }
  .qifu-a-main .release-item { border-right: 0 !important; border-bottom: 1px solid #e6eaeb !important; }
  .qifu-a-main .release-item:last-child { border-bottom: 0 !important; }
  .qifu-a-main .feature-grid { grid-template-columns: 1fr !important; }
  .qifu-a-main .feature-item:nth-child(odd) { border-right: 0 !important; }
}
</style>
<link href="art-design-pro.css?v=1.4.7" rel="stylesheet">
<link href="pencil-install.css?v=1.4.8" rel="stylesheet">
</head>
<body class="art-install-page qifu-install-step-<?php echo htmlspecialchars($install_step_key, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="qifu-install-page">
    <header class="qifu-a-head">
      <div class="qifu-a-brand">
        <div class="qifu-a-brand-copy"><strong>祈福导航系统</strong></div>
      </div>
      <div class="qifu-a-current"><?php echo htmlspecialchars($install_step_label, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="qifu-a-version"><?php echo htmlspecialchars($qifu_version, ENT_QUOTES, 'UTF-8'); ?></div>
    </header>
    <div class="qifu-a-grid">
      <main class="qifu-a-main">
        <div class="container install-wrap">
          <div class="col-xs-12 center-block" style="float: none;">

<?php if($do=='0'){?>
<div class="panel panel-primary install-card">
	<div class="panel-body">
		<div class="qifu-screen-grid">
			<div class="qifu-screen-column">
				<section class="qifu-card qifu-card-lg">
					<h1 class="qifu-welcome-title">欢迎安装</h1>
					<p class="qifu-card-lead">祈福导航系统安装向导将引导您完成环境检查、数据库配置和系统数据创建。请按步骤操作，并准备好数据库连接信息。</p>
				</section>
				<section class="qifu-card">
					<h3>核心能力</h3>
					<ul class="qifu-cap-list">
						<li>站点、分类、标签和友链统一管理</li>
						<li>前台搜索、公告、背景和卡片布局配置</li>
						<li>广告素材、展示区域与曝光统计管理</li>
						<li>访问统计、站点检测和邮件通知</li>
						<li>后台路径识别、登录防护与安全审计</li>
					</ul>
				</section>
			</div>
			<aside class="qifu-screen-column qifu-side-column">
				<section class="qifu-card qifu-card-dark qifu-version-tile">
					<span class="qifu-version-label">版本信息</span>
					<strong class="qifu-version-number">1.5</strong>
					<span class="qifu-version-note">安全加固正式版</span>
				</section>
				<section class="qifu-card">
					<h3>环境要求</h3>
					<dl class="qifu-detail-list">
						<div class="qifu-detail-row"><dt>PHP 版本</dt><dd>&ge; 8.2</dd></div>
						<div class="qifu-detail-row"><dt>数据库</dt><dd>MySQL 5.6+ / MariaDB 10.x</dd></div>
						<div class="qifu-detail-row"><dt>必要扩展</dt><dd>MySQL、MBString、CURL</dd></div>
						<div class="qifu-detail-row"><dt>写入权限</dt><dd>config.php、install/</dd></div>
					</dl>
				</section>
			</aside>
		</div>
		<?php if($installed){ ?>
		<div class="alert alert-warning">您已经安装过。如需重新安装，请先删除 <code>install/install.lock</code> 文件。</div>
		<?php } ?>
		<div class="qifu-bottom-bar">
			<div class="qifu-progress-wrap"><span class="qifu-progress-label">安装进度</span><span class="qifu-progress-track" style="--qifu-progress:0%"><span></span></span></div>
			<div class="qifu-bottom-buttons">
				<?php if(!$installed){ ?><a class="btn btn-primary" href="index.php?do=1">开始安装</a><?php } ?>
			</div>
		</div>
	</div>
</div>

<?php }elseif($do=='1'){?>
<div class="panel panel-primary">
	<div class="panel-body">
		<?php
		$env_checks = array(
			array('PHP 版本 &ge; 8.2', '当前版本：'.phpversion(), $php_ok, false),
			array('mysqli / pdo_mysql 扩展', $db_ext_ok ? '已启用' : '不可用', $db_ext_ok, false),
			array('MBString 扩展', $mb_ok ? '已启用' : '不可用', $mb_ok, false),
			array('CURL 扩展', $curl_ok ? '已启用' : '不可用', $curl_ok, false),
			array('file_get_contents()', $file_get_ok ? '已启用' : '不可用', $file_get_ok, false),
			array('config.php 写入权限', $config_writable ? '可写' : '不可写', $config_writable, false),
			array('install 目录写入权限', $install_writable ? '可写' : '建议手动创建 install.lock', $install_writable, true),
		);
		$env_passed = 0;
		foreach($env_checks as $env_check) if($env_check[2]) ++$env_passed;
		?>
		<div class="qifu-screen-grid">
			<section class="qifu-card qifu-card-lg">
				<h1>服务器环境检查</h1>
				<p class="qifu-card-lead">正在检查您的服务器环境是否满足祈福导航系统的运行要求。</p>
				<div class="qifu-check-list">
					<?php foreach($env_checks as $env_check){
						$env_class = $env_check[2] ? '' : ($env_check[3] ? ' is-warning' : ' is-error');
					?>
					<div class="qifu-check-row<?php echo $env_class; ?>">
						<span class="qifu-check-name"><i class="qifu-status-dot"></i><?php echo $env_check[0]; ?></span>
						<span class="qifu-check-value"><?php echo htmlspecialchars($env_check[1], ENT_QUOTES, 'UTF-8'); ?></span>
					</div>
					<?php } ?>
				</div>
				<?php if(!$env_ok){ ?><div class="alert alert-danger">当前环境未满足安装要求，请修复红色项目后重新检查。</div><?php } ?>
			</section>
			<aside class="qifu-screen-column qifu-side-column">
				<section class="qifu-card qifu-card-dark qifu-summary-tile">
					<span class="qifu-summary-label">环境状态</span>
					<div class="qifu-summary-main"><span class="qifu-summary-icon"><?php echo $env_ok ? '✓' : '!'; ?></span><strong class="qifu-summary-text"><?php echo $env_ok ? '全部通过' : '需要处理'; ?></strong></div>
					<span class="qifu-summary-note"><?php echo $env_ok ? $env_passed.' 项检查全部通过，可以继续安装。' : '请修复必需项后重新检查。'; ?></span>
				</section>
			</aside>
		</div>
		<div class="qifu-bottom-bar">
			<div class="qifu-progress-wrap"><span class="qifu-progress-label">安装进度 · 10%</span><span class="qifu-progress-track" style="--qifu-progress:10%"><span></span></span></div>
			<div class="qifu-bottom-buttons"><a class="btn" href="index.php?do=1">重新检查</a><?php if($env_ok){ ?><a class="btn btn-primary" href="index.php?do=2">下一步</a><?php } ?></div>
		</div>
	</div>
</div>

<?php }elseif($do=='2'){?>
<div class="panel panel-primary">
	<div class="panel-body">
		<div class="qifu-screen-grid">
			<section class="qifu-card qifu-card-lg">
				<h1>数据库配置</h1>
				<p class="qifu-card-lead">请填写数据库连接信息。如果数据库部署在当前服务器，通常保留默认地址和端口即可。</p>
				<?php if(!$env_ok){ ?>
					<div class="alert alert-danger">当前环境未满足安装要求，请返回环境检查页处理后继续。</div>
				<?php }elseif(defined("SAE_ACCESSKEY")){ ?>
					<div class="qifu-status-panel"><span class="qifu-status-mark">✓</span><strong>已识别 SAE 安装环境</strong><span class="qifu-card-lead">此环境支持使用已有数据库配置继续安装。</span></div>
				<?php }else{ ?>
					<form id="install-db-form" action="?do=3" method="post" novalidate>
						<?php echo $install_csrf_html; ?>
						<div class="qifu-form-grid">
							<div class="qifu-form-field"><label for="db_host">数据库地址</label><input id="db_host" type="text" class="form-control" name="db_host" value="localhost" autocomplete="off" required><small>通常填写 localhost 或数据库服务器地址</small></div>
							<div class="qifu-form-field"><label for="db_port">数据库端口</label><input id="db_port" type="text" class="form-control" name="db_port" value="3306" inputmode="numeric" required><small>MySQL 默认端口为 3306</small></div>
							<div class="qifu-form-field"><label for="db_user">数据库用户名</label><input id="db_user" type="text" class="form-control" name="db_user" autocomplete="off" required><small>请输入数据库用户名</small></div>
							<div class="qifu-form-field"><label for="db_pwd">数据库密码</label><input id="db_pwd" type="password" class="form-control" name="db_pwd" autocomplete="new-password" required><small>该密码不会在后续页面回显</small></div>
							<div class="qifu-form-field is-full"><label for="db_name">数据库名称</label><input id="db_name" type="text" class="form-control" name="db_name" autocomplete="off" required><small>请先在数据库服务中创建该数据库</small></div>
						</div>
					</form>
				<?php } ?>
			</section>
			<aside class="qifu-screen-column qifu-side-column">
				<section class="qifu-card qifu-card-dark">
					<h3 style="color:#fff">配置说明</h3>
					<ul class="qifu-tip-list"><li>请确认数据库服务已经启动</li><li>建议使用 utf8mb4 编码的数据库</li><li>数据库用户需具有创建数据表权限</li><li>远程数据库请确认防火墙规则</li></ul>
				</section>
				<section class="qifu-card"><h3>已有配置文件？</h3><p class="qifu-card-lead">如果已事先填写好 config.php，可跳过本页直接验证。</p><a class="btn" href="?do=3&amp;jump=1">跳过并验证</a></section>
			</aside>
		</div>
		<div class="qifu-bottom-bar">
			<div class="qifu-progress-wrap"><span class="qifu-progress-label">安装进度 · 30%</span><span class="qifu-progress-track" style="--qifu-progress:30%"><span></span></span></div>
			<div class="qifu-bottom-buttons"><a class="btn" href="index.php?do=1">上一步</a><?php if($env_ok){ ?><?php if(defined("SAE_ACCESSKEY")){ ?><a class="btn btn-primary" href="?do=3">下一步</a><?php }else{ ?><button class="btn btn-primary" type="submit" form="install-db-form">保存并继续</button><?php } ?><?php } ?></div>
		</div>
	</div>
</div>

<?php }elseif($do=='3'){
require __DIR__ . '/db.class.php';
$verify_ok = false;
$verify_existing = false;
$verify_error = '';
$verify_saved = false;
$dbconfig = array();
$from_existing_config = defined("SAE_ACCESSKEY") || (isset($_GET['jump']) && $_GET['jump']==1);
if($from_existing_config){
	if(defined("SAE_ACCESSKEY")) include_once dirname(__DIR__) . '/includes/sae.php';
	else include_once dirname(__DIR__) . '/config.php';
}else{
	$dbconfig = array(
		'host' => isset($_POST['db_host']) ? trim((string)$_POST['db_host']) : '',
		'port' => isset($_POST['db_port']) ? trim((string)$_POST['db_port']) : '',
		'user' => isset($_POST['db_user']) ? trim((string)$_POST['db_user']) : '',
		'pwd' => isset($_POST['db_pwd']) ? (string)$_POST['db_pwd'] : '',
		'dbname' => isset($_POST['db_name']) ? trim((string)$_POST['db_name']) : '',
	);
}
if(empty($dbconfig['user']) || empty($dbconfig['pwd']) || empty($dbconfig['dbname'])){
	$verify_error = '请返回数据库配置页，确保数据库地址、端口、用户名、密码和数据库名称都已填写。';
}elseif(!$con = DB::connect($dbconfig['host'], $dbconfig['user'], $dbconfig['pwd'], $dbconfig['dbname'], $dbconfig['port'])){
	$verify_code = DB::connect_errno();
	if($verify_code == 2002) $verify_error = '无法连接数据库服务器，请检查数据库地址和端口。';
	elseif($verify_code == 1045) $verify_error = '数据库身份验证失败，请检查用户名或密码。';
	elseif($verify_code == 1049) $verify_error = '指定的数据库不存在，请确认数据库名称。';
	else $verify_error = '数据库连接失败，请检查连接参数和服务器状态。';
}elseif(!$from_existing_config && !qifu_install_write_config($config_file, build_config($dbconfig['host'], $dbconfig['port'], $dbconfig['user'], $dbconfig['pwd'], $dbconfig['dbname']))){
	$verify_error = '无法保存 config.php，请确认网站根目录具有 PHP 写入权限。';
}else{
	$verify_ok = true;
	$verify_saved = true;
	$verify_existing = DB::query("select * from web_config where 1") !== FALSE;
	if($verify_existing) $_SESSION['qifu_install_existing_ready'] = true;
}
?>
<div class="panel panel-primary">
	<div class="panel-body">
		<div class="qifu-screen-grid">
			<section class="qifu-card qifu-card-lg">
				<h1>验证数据库连接</h1>
				<p class="qifu-card-lead">正在验证连接信息并检查系统配置文件写入状态。</p>
				<div class="qifu-status-panel<?php echo $verify_ok ? '' : ' is-error'; ?>"><span class="qifu-status-mark"><?php echo $verify_ok ? '✓' : '!'; ?></span><strong><?php echo $verify_ok ? '数据库连接成功' : '数据库连接失败'; ?></strong><span class="qifu-card-lead"><?php echo $verify_ok ? '数据库配置已通过验证，连接参数不会在页面中回显。' : htmlspecialchars($verify_error, ENT_QUOTES, 'UTF-8'); ?></span></div>
				<dl class="qifu-detail-list" style="margin-top:24px"><div class="qifu-detail-row"><dt>数据库连接</dt><dd><?php echo $verify_ok ? '已验证' : '未通过'; ?></dd></div><div class="qifu-detail-row"><dt>配置文件</dt><dd><?php echo $verify_saved ? '已保存' : '未保存'; ?></dd></div><div class="qifu-detail-row"><dt>系统数据</dt><dd><?php echo $verify_ok ? ($verify_existing ? '检测到已有数据' : '等待创建') : '等待连接'; ?></dd></div></dl>
			</section>
			<aside class="qifu-screen-column qifu-side-column">
				<?php if(!$verify_ok){ ?>
				<section class="qifu-card qifu-card-warm"><h3>需要处理</h3><p class="qifu-card-lead">请返回修改数据库连接信息后重新验证。数据库密码不会被保留或回显。</p></section>
				<?php }elseif($verify_existing){ ?>
				<section class="qifu-card qifu-card-warm"><h3>检测到已有数据</h3><p class="qifu-card-lead">请选择保留现有数据直接完成安装，或清空数据后进行全新安装。</p><div class="qifu-existing-options"><?php echo install_action_form(6, '跳过安装', 'btn btn-info'); ?><?php echo install_action_form(4, '强制全新安装', 'btn btn-warning', '全新安装将会清空所有数据，是否继续？'); ?></div></section>
				<?php }else{ ?>
				<section class="qifu-card qifu-card-dark qifu-summary-tile"><span class="qifu-summary-label">验证状态</span><div class="qifu-summary-main"><span class="qifu-summary-icon">✓</span><strong class="qifu-summary-text">可以继续</strong></div><span class="qifu-summary-note">数据库连接和配置文件均已验证。</span></section>
				<?php } ?>
			</aside>
		</div>
		<div class="qifu-bottom-bar"><div class="qifu-progress-wrap"><span class="qifu-progress-label">安装进度 · 50%</span><span class="qifu-progress-track" style="--qifu-progress:50%"><span></span></span></div><div class="qifu-bottom-buttons"><?php if(!$verify_ok){ ?><a class="btn" href="index.php?do=2">返回修改</a><?php }elseif(!$verify_existing){ echo install_action_form(4, '创建数据表', 'btn btn-primary'); } ?></div></div>
	</div>
</div>
<?php }elseif($do=='4'){?>
<div class="panel panel-primary">
	<div class="panel-body">
<?php
$t=0; $e=1; $error='数据库配置无效或不可用'; $install_elapsed=0;
if(defined("SAE_ACCESSKEY"))include_once dirname(__DIR__) . '/includes/sae.php';
else include_once dirname(__DIR__) . '/config.php';
if(!$dbconfig['user']||!$dbconfig['pwd']||!$dbconfig['dbname']) {
	$error = '请先填写并验证数据库配置后再创建数据表。';
} else {
	require __DIR__ . '/db.class.php';
	$sql=file_get_contents(__DIR__."/install.sql");
	$sql=explode(';',$sql);
	$cn = DB::connect($dbconfig['host'],$dbconfig['user'],$dbconfig['pwd'],$dbconfig['dbname'],$dbconfig['port']);
	if (!$cn) {
		$error = '无法连接数据库，请返回上一步重新验证连接信息。';
	} else {
		$install_started = microtime(true);
		DB::query("set sql_mode = ''");
		DB::query("set names utf8");
		$t=0; $e=0; $error='';
		for($i=0;$i<count($sql);$i++) {
			$query = trim($sql[$i]);
			if ($query==='')continue;
			if(DB::query($query)) {
				++$t;
			} else {
				++$e;
				$error.=DB::error()."\n";
			}
		}
		$install_elapsed = max(1, (int)ceil(microtime(true)-$install_started));
	}
}
$table_groups = array(
	array('web_config', '系统配置与管理员数据'),
	array('web_dh / web_category', '导航站点与分类数据'),
	array('web_links', '友链申请与审核数据'),
	array('web_ads / web_ad_stats', '广告素材与曝光统计'),
	array('web_site_stats / web_stats', '访问统计与日访问数据'),
	array('web_log / web_backup', '操作日志与备份数据'),
	array('web_login_attempts', '登录防护与安全数据'),
);
$install_success = $e == 0;
if($install_success) $_SESSION['qifu_install_ready'] = true;
?>
		<div class="qifu-screen-grid">
			<section class="qifu-card qifu-card-lg">
				<h1>创建数据表</h1>
				<p class="qifu-card-lead"><?php echo $install_success ? '系统所需的数据表和默认数据已经写入完成。' : '创建系统数据表时遇到问题，请查看结果后重试。'; ?></p>
				<?php if($install_success){ ?>
				<div class="qifu-table-list"><?php foreach($table_groups as $table_group){ ?><div class="qifu-table-row"><span class="qifu-table-name"><span class="qifu-summary-icon" style="font-size:16px">✓</span><?php echo $table_group[0]; ?></span><span class="qifu-table-desc"><?php echo $table_group[1]; ?></span></div><?php } ?></div>
				<?php }else{ ?><div class="qifu-status-panel is-error"><span class="qifu-status-mark">!</span><strong>数据表创建失败</strong><span class="qifu-card-lead"><?php echo htmlspecialchars(trim($error), ENT_QUOTES, 'UTF-8'); ?></span></div><?php } ?>
			</section>
			<aside class="qifu-screen-column qifu-side-column">
				<section class="qifu-card qifu-card-dark qifu-summary-tile"><span class="qifu-summary-label">安装状态</span><strong class="qifu-summary-count"><?php echo $install_success ? '12/12' : $t.'/12'; ?></strong><span class="qifu-summary-note"><?php echo $install_success ? '核心数据表已创建成功' : '请修复问题后重新尝试'; ?></span></section>
				<section class="qifu-card"><span class="qifu-summary-label">执行耗时</span><div class="qifu-time-value"><?php echo $install_success ? '&lt; '.$install_elapsed.' 秒' : '等待重试'; ?></div></section>
			</aside>
		</div>
		<div class="qifu-bottom-bar"><div class="qifu-progress-wrap"><span class="qifu-progress-label">安装进度 · 70%</span><span class="qifu-progress-track" style="--qifu-progress:70%"><span></span></span></div><div class="qifu-bottom-buttons"><?php if($install_success){ echo install_action_form(5, '完成安装', 'btn btn-primary'); }else{ ?><a class="btn" href="index.php?do=3&amp;jump=1">返回配置</a><?php echo install_action_form(4, '重新尝试', 'btn btn-primary'); } ?></div></div>
	</div>
</div>

<?php }elseif($do=='5'){?>
<div class="panel panel-primary">
	<div class="panel-body">
<?php
	$admin_directory = qifu_admin_directory_name(dirname(__DIR__), true);
	$completion = qifu_install_completion(__DIR__.'/install.lock', '默认管理账号和密码：admin1 / 123456。首次登录后请立即修改。', $admin_directory);
	if(!$completion['success']) http_response_code(500);
	if($completion['success'] && is_file($lock_file)) unset($_SESSION['qifu_install_ready'], $_SESSION['qifu_install_existing_ready']);
?>
		<?php if(!$completion['success']){ echo $completion['html']; }else{ ?>
		<div class="qifu-screen-grid">
			<div class="qifu-screen-column">
				<section class="qifu-card qifu-card-lg qifu-success-card"><span class="qifu-success-mark">✓</span><h1>安装成功！</h1><p class="qifu-card-lead">祈福导航系统 <?php echo htmlspecialchars($qifu_version, ENT_QUOTES, 'UTF-8'); ?> 已成功安装到您的服务器。请妥善保管以下信息。</p></section>
				<section class="qifu-card"><h3>默认管理员账号</h3><div class="qifu-account-list"><div class="qifu-account-row"><span>用户名</span><strong>admin1</strong></div><div class="qifu-account-row"><span>密码</span><strong>123456</strong></div><div class="qifu-account-row"><span>管理后台</span><strong>/<?php echo htmlspecialchars($admin_directory, ENT_QUOTES, 'UTF-8'); ?></strong></div><div class="qifu-account-row"><span>前台首页</span><strong>/</strong></div></div></section>
			</div>
			<aside class="qifu-screen-column qifu-side-column">
				<section class="qifu-card qifu-card-warm"><h3>安全提醒</h3><ul class="qifu-security-list"><li>首次登录后立即修改管理员密码</li><li>请将后台目录改为不易猜测的名称</li><li>确认 install 目录已由安装锁保护</li><li>建议启用 HTTPS 加密传输</li><li>定期备份数据库和上传文件</li></ul></section>
				<section class="qifu-card qifu-card-dark"><div class="qifu-quick-links"><a href="../<?php echo rawurlencode($admin_directory); ?>/">进入管理后台</a><a href="../">访问前台首页</a></div></section>
			</aside>
		</div>
		<div class="qifu-bottom-bar"><div class="qifu-progress-wrap"><span class="qifu-progress-label">安装进度 · 100%</span><span class="qifu-progress-track is-complete" style="--qifu-progress:100%"><span></span></span></div><div class="qifu-bottom-buttons"><a class="btn btn-primary" href="../<?php echo rawurlencode($admin_directory); ?>/">进入系统</a></div></div>
		<?php } ?>
	</div>
</div>

<?php }elseif($do=='6'){?>
<div class="panel panel-primary">
	<div class="panel-body">
<?php
	$admin_directory = qifu_admin_directory_name(dirname(__DIR__), true);
	$completion = qifu_install_completion(__DIR__.'/install.lock', '请使用现有管理员账号和密码登录。', $admin_directory);
	if(!$completion['success']) http_response_code(500);
	if($completion['success'] && is_file($lock_file)) unset($_SESSION['qifu_install_ready'], $_SESSION['qifu_install_existing_ready']);
?>
		<?php if(!$completion['success']){ echo $completion['html']; }else{ ?>
		<div class="qifu-screen-grid">
			<div class="qifu-screen-column">
				<section class="qifu-card qifu-card-lg qifu-success-card"><span class="qifu-success-mark">✓</span><h1>安装完成</h1><p class="qifu-card-lead">已验证现有祈福导航系统数据，本次安装未覆盖任何站点、分类或前台设置。</p></section>
				<section class="qifu-card"><h3>现有系统信息</h3><div class="qifu-account-list"><div class="qifu-account-row"><span>管理员账号</span><strong>请使用现有账号登录</strong></div><div class="qifu-account-row"><span>管理后台</span><strong>/<?php echo htmlspecialchars($admin_directory, ENT_QUOTES, 'UTF-8'); ?></strong></div><div class="qifu-account-row"><span>前台首页</span><strong>/</strong></div></div></section>
			</div>
			<aside class="qifu-screen-column qifu-side-column"><section class="qifu-card qifu-card-warm"><h3>安全提醒</h3><ul class="qifu-security-list"><li>确认后台目录不是默认的 /admin</li><li>检查管理员密码和登录安全设置</li><li>建议启用 HTTPS 加密传输</li><li>定期备份数据库和上传文件</li></ul></section><section class="qifu-card qifu-card-dark"><div class="qifu-quick-links"><a href="../<?php echo rawurlencode($admin_directory); ?>/">进入管理后台</a><a href="../">访问前台首页</a></div></section></aside>
		</div>
		<div class="qifu-bottom-bar"><div class="qifu-progress-wrap"><span class="qifu-progress-label">安装进度 · 100%</span><span class="qifu-progress-track is-complete" style="--qifu-progress:100%"><span></span></span></div><div class="qifu-bottom-buttons"><a class="btn btn-primary" href="../<?php echo rawurlencode($admin_directory); ?>/">进入系统</a></div></div>
		<?php } ?>
	</div>
</div>

<?php }?>

          </div>
        </div>
      </main>
    </div>
    <footer class="qifu-a-foot"><span>安装写操作使用 POST 与 CSRF 校验，数据库连接参数不会回显。</span><span>祈福导航系统 · <?php echo htmlspecialchars($qifu_version, ENT_QUOTES, 'UTF-8'); ?></span></footer>
  </div>
  <script>
    (function () {
      function showInstallLoading(label) {
        if (document.querySelector('.art-install-loading')) return;
        var overlay = document.createElement('div');
        overlay.className = 'art-install-loading';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.innerHTML = '<div class="art-install-loading__box"><i class="art-install-loading__spinner"></i><span>' + label + '</span></div>';
        document.body.appendChild(overlay);
      }

      document.querySelectorAll('.art-install-page form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
          if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
          }
          form.dataset.submitting = '1';
          event.preventDefault();
          var submit = form.querySelector('button[type="submit"], input[type="submit"]');
          if (submit) {
            submit.disabled = true;
            if (submit.tagName === 'INPUT') submit.value = '正在处理...';
            else submit.textContent = '正在处理...';
          }
          showInstallLoading('正在验证并进入下一步');
          window.setTimeout(function () {
            HTMLFormElement.prototype.submit.call(form);
          }, 360);
        });
      });

      document.querySelectorAll('.art-install-page a[href*="do="]').forEach(function (link) {
        link.addEventListener('click', function (event) {
          if (link.dataset.navigating === '1') return;
          link.dataset.navigating = '1';
          event.preventDefault();
          showInstallLoading('正在进入下一步');
          window.setTimeout(function () { window.location.assign(link.href); }, 360);
        });
      });
    }());
  </script>
</body>
</html>
