<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */
 
if(!defined('IN_CRONLITE'))exit();

$my=isset($_GET['my'])?$_GET['my']:null;
$clientip=real_ip();
$islogin=0;

if(!empty($_SESSION['qifu_admin_auth'])){
	$session_user = isset($_SESSION['qifu_admin_user']) ? (string)$_SESSION['qifu_admin_user'] : '';
	$session_version = isset($_SESSION['qifu_admin_version']) ? intval($_SESSION['qifu_admin_version']) : 0;
	$last_seen = isset($_SESSION['qifu_admin_last_seen']) ? intval($_SESSION['qifu_admin_last_seen']) : 0;
	$current_user = isset($conf['admin_user']) ? (string)$conf['admin_user'] : '';
	if($last_seen > 0 && time() - $last_seen <= 7200 && $session_version === qifu_auth_version() && hash_equals($current_user, $session_user)){
		$islogin=1;
		$_SESSION['qifu_admin_last_seen'] = time();
	} else {
		qifu_admin_logout_session();
	}
}
?>
