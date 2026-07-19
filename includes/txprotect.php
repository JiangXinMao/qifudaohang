<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */
 
// +----------------------------------------------------------------------
// | 反腾讯网址安全检测系统
// | Description:屏蔽腾讯电脑管家网址安全检测
// | Version:2.5
// | Author:琳琅天上
if(isset($nosecu) && $nosecu==true)return;
//IP屏蔽
$iptables='977012992~977013247|977084416~977084927|1743654912~1743655935|1949957632~1949958143|2006126336~2006127359|2111446272~2111446527|3418570752~3418578943|3419242496~3419250687|3419250688~3419275263|3682941952~3682942207|3682942464~3682942719|3682986660~3682986663|1707474944~1707606015|1884967642|1884967620|1893733510|1709332858|1709318620|1709325774|1709342057|1709341968|1709330358|1709335492|1709327575|1709327041|1709318626|1709318617|1709327557|1709327573|1975065457|1902908741|1902908705|3029946827';
$remoteiplong=bindec(decbin(ip2long(real_ip())));
foreach(explode('|',$iptables) as $iprows){
	if($remoteiplong==$iprows)exit('欢迎使用！');
	$ipbanrange=explode('~',$iprows);
	if(count($ipbanrange) == 2 && $remoteiplong>=$ipbanrange[0] && $remoteiplong<=$ipbanrange[1])
		exit('欢迎使用！');
}
//HEADER特征屏蔽
$http_user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$http_accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
$http_accept_language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
if(preg_match("/manager/", strtolower($http_user_agent)) || preg_match("/QZONEJSSDK/", $http_user_agent) || strpos($http_user_agent, 'Mozilla')===false && strpos($http_user_agent, 'ozilla')!==false || preg_match("/Windows NT 6.1/", $http_user_agent) && $http_accept=='*/*' || preg_match("/Windows NT 5.1/", $http_user_agent) && $http_accept=='*/*' || preg_match("/vnd.wap.wml/", $http_accept) && preg_match("/Windows NT 5.1/", $http_user_agent) || isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'urls.tr.com')!==false || isset($_COOKIE['ASPSESSIONIDQASBQDRC']) || empty($http_user_agent) || preg_match("/Alibaba.Security.Heimdall/", $http_user_agent)) {
	exit('欢迎使用！');
}
if(strpos($http_user_agent, 'iPhone OS 9_3_4')!==false && $http_accept=='*/*' || strpos($http_user_agent, 'iPhone OS 8_4')!==false && $http_accept=='*/*' || strpos($http_user_agent, 'Android 6.0.1')!==false && strpos($http_user_agent, 'MQQBrowser/6.8')!==false && $http_accept=='*/*' || strpos($http_accept_language, 'en')!==false && strpos($http_accept_language, 'zh')===false || strpos($http_user_agent, 'iPhone')!==false && strpos($http_user_agent, 'en-')!==false && strpos($http_user_agent, 'zh')===false) {
	exit('您当前浏览器不支持或操作系统语言设置非中文，无法访问本站！');
}
