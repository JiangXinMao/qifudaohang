<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

if(extension_loaded('mysqli')) {
    class DB {
        private static $link;
		public static function connect($db_host,$db_user,$db_pass,$db_name,$db_port){
			self::$link = @mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
			if(self::$link){
				mysqli_query(self::$link, "set sql_mode = ''");
				mysqli_query(self::$link, "set names utf8");
			}
			return self::$link;
		}
		public static function connect_errno(){
			return mysqli_connect_errno();
		}
		public static function connect_error(){
			return mysqli_connect_error();
		}
		public static function fetch($q){
			return $q ? mysqli_fetch_assoc($q) : false;
		}
		public static function get_row($q){
			$result = mysqli_query(self::$link,$q);
			return $result ? mysqli_fetch_assoc($result) : false;
		}
		public static function count($q){
			$result = mysqli_query(self::$link,$q);
			$count = $result ? mysqli_fetch_array($result) : array(0);
			return $count[0];
		}
		public static function query($q){
			return mysqli_query(self::$link,$q);
		}
		public static function escape($str){
			return mysqli_real_escape_string(self::$link,(string)$str);
		}
		public static function affected(){
			return mysqli_affected_rows(self::$link);
		}
		public static function errno(){
			return mysqli_errno(self::$link);
		}
		public static function error(){
			return mysqli_error(self::$link);
		}
		public static function close(){
			return mysqli_close(self::$link);
		}
	}
} elseif(extension_loaded('pdo_mysql')) {
	class DB {
		private static $link;
		private static $error = '';
		private static $errno = 0;

		public static function connect($db_host,$db_user,$db_pass,$db_name,$db_port){
			try {
				self::$link = new PDO(
					'mysql:host='.$db_host.';port='.$db_port.';dbname='.$db_name.';charset=utf8',
					$db_user,
					$db_pass,
					array(PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
				);
				self::$link->query("set sql_mode = ''");
				self::$link->query("set names utf8");
				self::$error = '';
				self::$errno = 0;
				return self::$link;
			} catch (PDOException $e) {
				self::$error = $e->getMessage();
				self::$errno = intval($e->getCode());
				return false;
			}
		}
		public static function connect_errno(){
			return self::$errno;
		}
		public static function connect_error(){
			return self::$error;
		}
		public static function fetch($q){
			return $q ? $q->fetch(PDO::FETCH_ASSOC) : false;
		}
		public static function get_row($q){
			$result = self::query($q);
			return $result ? $result->fetch(PDO::FETCH_ASSOC) : false;
		}
		public static function count($q){
			$result = self::query($q);
			return $result ? $result->fetchColumn() : 0;
		}
		public static function query($q){
			$result = self::$link->query($q);
			if(!$result){
				$error = self::$link->errorInfo();
				self::$errno = intval($error[1]);
				self::$error = $error[2];
			}
			return $result;
		}
		public static function escape($str){
			$quoted = self::$link->quote((string)$str);
			return $quoted !== false ? substr($quoted, 1, -1) : addslashes((string)$str);
		}
		public static function affected(){
			return 0;
		}
		public static function errno(){
			return self::$errno;
		}
		public static function error(){
			return self::$error;
		}
		public static function close(){
			self::$link = null;
			return true;
		}
	}
} elseif(function_exists('mysql_connect')) {
	class DB {
		private static $link;
		public static function connect($db_host,$db_user,$db_pass,$db_name,$db_port){
			self::$link = @mysql_connect($db_host.':'.$db_port, $db_user, $db_pass);
			if (!self::$link)return false;
			$ok = mysql_select_db($db_name, self::$link);
			if($ok){
				mysql_query("set sql_mode = ''");
				mysql_query("set names utf8");
			}
			return $ok;
		}
		public static function connect_errno(){
			return mysql_errno();
		}
		public static function connect_error(){
			return mysql_error();
		}
		public static function fetch($q){
			return $q ? mysql_fetch_assoc($q) : false;
		}
		public static function get_row($q){
			$result = mysql_query($q, self::$link);
			return $result ? mysql_fetch_assoc($result) : false;
		}
		public static function count($q){
			$result = mysql_query($q, self::$link);
			$count = $result ? mysql_fetch_array($result) : array(0);
			return $count[0];
		}
        public static function query($q){
			return mysql_query($q, self::$link);
		}
		public static function escape($str){
			return mysql_real_escape_string((string)$str, self::$link);
		}
		public static function affected(){
			return mysql_affected_rows(self::$link);
		}
		public static function errno(){
			return mysql_errno(self::$link);
		}
		public static function error(){
			return mysql_error(self::$link);
		}
		public static function close(){
			return mysql_close(self::$link);
		}
	}
} else {
	class DB {
		public static function connect($db_host,$db_user,$db_pass,$db_name,$db_port){ return false; }
		public static function connect_errno(){ return 0; }
		public static function connect_error(){ return '服务器缺少 MySQL 数据库扩展，请开启 mysqli 或 pdo_mysql'; }
		public static function query($q){ return false; }
		public static function error(){ return self::connect_error(); }
	}
}
?>
