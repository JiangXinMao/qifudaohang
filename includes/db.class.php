<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */
 
//MySQL、MySQLi、SQLite 三合一数据库操作类
if(!defined('IN_CRONLITE'))exit();

$nomysqli=false;

function qifu_db_query_allowed($query){
	$query = trim((string)$query);
	if($query === '' || strpos($query, "\0") !== false) return false;
	if(preg_match('/;\s*\S/s', rtrim($query, ';'))) return false;
	if(defined('QIFU_ALLOW_RUNTIME_DDL') && QIFU_ALLOW_RUNTIME_DDL) return true;
	if(preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?web_login_attempts`?/i', $query)) return true;
	if(preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?(web_stats|web_daily_visitors)`?/i', $query)) return true;
	if(preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?web_backup`?/i', $query)) return true;
	if(preg_match('/^CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?web_site_stats`?/i', $query)) return true;
	if(preg_match('/^ALTER\s+TABLE\s+`?web_site_stats`?\s+ADD\s+COLUMN\s+`?impressions`?\s+/i', $query)) return true;
	if(preg_match('/^ALTER\s+TABLE\s+`?web_dh`?\s+ADD\s+COLUMN\s+`?clicks`?\s+/i', $query)) return true;
	if(preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?web_update_history`?/i', $query)) return true;
	return !preg_match('/^(CREATE|ALTER|DROP|TRUNCATE|RENAME|ATTACH|DETACH|PRAGMA|VACUUM|REINDEX|GRANT|REVOKE|LOAD\s+DATA)\b/i', $query);
}

if(defined('SQLITE')==true){
	class DB {
		var $link = null;
		var $result = null;

		function __construct($db_file){
			global $siteurl;
			$sqlite_path = defined('QIFU_SQLITE_PATH') ? QIFU_SQLITE_PATH : ROOT.'includes/sqlite/'.$db_file.'.db';
			$this->link = new PDO('sqlite:'.$sqlite_path);
			$this->link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
		if (!$this->link) {
			if(function_exists('dh_json_exit')) dh_json_exit('数据库连接失败');
			die('Connection Sqlite failed.\n');
		}
		return true;
        }

		function fetch($q){
			return $q ? $q->fetch(PDO::FETCH_ASSOC) : false;
		}
		function get_row($q){
			if(!qifu_db_query_allowed($q)) return false;
			$sth = $this->link->query($this->normalize_query($q));
			return $sth ? $sth->fetch(PDO::FETCH_ASSOC) : false;
		}
		function get_results($q){
			if(!qifu_db_query_allowed($q)) return array();
			$sth = $this->link->query($this->normalize_query($q));
			if(!$sth) return array();
			return $sth->fetchAll(PDO::FETCH_ASSOC);
		}
		function count($q){
			if(!qifu_db_query_allowed($q)) return 0;
			$sth = $this->link->query($this->normalize_query($q));
			return $sth->fetchColumn();
		}
		function query($q){
			if(!qifu_db_query_allowed($q)) return false;
			return $this->result=$this->link->query($this->normalize_query($q));
		}
		function prepared_query($q, $params = array()){
			if(!qifu_db_query_allowed($q)) return false;
			$stmt = $this->link->prepare($this->normalize_query($q));
			if(!$stmt || !$stmt->execute(array_values($params))) return false;
			return $this->result = $stmt;
		}
		function prepared_row($q, $params = array()){
			$stmt = $this->prepared_query($q, $params);
			return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
		}
		function prepared_results($q, $params = array()){
			$stmt = $this->prepared_query($q, $params);
			return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
		}
		function prepared_value($q, $params = array()){
			$stmt = $this->prepared_query($q, $params);
			return $stmt ? $stmt->fetchColumn() : false;
		}
		function prepared_insert($q, $params = array()){
			if(!$this->prepared_query($q, $params)) return false;
			return $this->link->lastInsertId();
		}
		function normalize_query($q){
			$q = trim($q);
			if(preg_match('/^SELECT\s+VERSION\(\)/i', $q)) return 'SELECT sqlite_version()';
			if(preg_match('/^ALTER\s+TABLE\s+`?[a-zA-Z0-9_]+`?\s+MODIFY\s+/i', $q)) return 'SELECT 1';
			if(preg_match("/^SHOW\s+COLUMNS\s+FROM\s+`?([a-zA-Z0-9_]+)`?\s+LIKE\s+'([^']+)'/i", $q, $m)){
				$table = str_replace("'", "''", $m[1]);
				$column = str_replace("'", "''", $m[2]);
				return "SELECT name FROM pragma_table_info('{$table}') WHERE name='{$column}'";
			}
			if(preg_match("/^SHOW\s+TABLES\s+LIKE\s+'([^']+)'/i", $q, $m)){
				$table = str_replace("'", "''", $m[1]);
				return "SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'";
			}
			if(preg_match('/^REPLACE\s+INTO\s+`?([a-zA-Z0-9_]+)`?\s+SET\s+(.+)$/is', $q, $m)){
				$columns = array();
				$values = array();
				preg_match_all("/`?([a-zA-Z0-9_]+)`?\s*=\s*('(?:''|[^'])*'|-?[0-9]+)/", $m[2], $pairs, PREG_SET_ORDER);
				foreach($pairs as $pair){
					$columns[] = '`'.$pair[1].'`';
					$values[] = $pair[2];
				}
				if($columns){
					return 'INSERT OR REPLACE INTO `'.$m[1].'` ('.implode(',', $columns).') VALUES ('.implode(',', $values).')';
				}
			}
			if(stripos($q, 'ON DUPLICATE KEY UPDATE') !== false){
				$field = preg_match('/ON\s+DUPLICATE\s+KEY\s+UPDATE\s+`?(views|clicks|impressions)`?/i', $q, $m) ? $m[1] : 'views';
				$conflict = stripos($q, 'web_site_stats') !== false ? 'site_id, stat_date' : 'ad_id, stat_date';
				$q = preg_replace('/ON\s+DUPLICATE\s+KEY\s+UPDATE\s+.+$/is', 'ON CONFLICT('.$conflict.') DO UPDATE SET '.$field.'='.$field.'+1', $q);
			}
			return $q;
		}
		function num_rows($q){
			if(!$q) return 0;
			$rows = $q->fetchAll(PDO::FETCH_ASSOC);
			return count($rows);
		}
		function escape($str){
			return str_replace("'", "''", (string)$str);
		}
		function insert($q){
			if($this->query($q)) return $this->link->lastInsertId();
			return false;
		}
		function insert_array($table,$array){
			$columns = array_keys($array);
			$quoted = array();
			foreach($array as $value) $quoted[] = "'".$this->escape($value)."'";
			$q = "INSERT INTO `{$table}` (`".implode('`,`', $columns)."`) VALUES (".implode(',', $quoted).")";
			return $this->insert($q);
		}
		function affected(){
			return $this->result ? $this->result->rowCount() : 0;
		}
		function error(){
			$error = $this->link->errorInfo();
			return '['.$error[1].'] '.$error[2];
		}
		function close(){
			$this->link = null;
			return true;
		}
	}
}
elseif(extension_loaded('mysqli') && $nomysqli==false) {
    class DB {
        var $link = null;
		var $result = null;

        function __construct($db_host,$db_user,$db_pass,$db_name,$db_port){
            // PHP 8 can enable mysqli exceptions by default. The application
            // deliberately handles query failures through return values so a
            // missing optional table cannot turn the homepage into a blank
            // 200 response. Keep the existing injection/DDL guard in charge.
            if(function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
            $this->link = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
            
            if (!$this->link) {
				if(function_exists('dh_json_exit')) dh_json_exit('数据库连接失败：'.mysqli_connect_error());
				die('Connect Error (' . mysqli_connect_errno() . ') '.mysqli_connect_error());
			}
            
            //mysqli_select_db($this->link, $db_name) or die(mysqli_error($this->link));
            
 
mysqli_query($this->link,"set sql_mode = ''");
 //字符转换，读库
if(!mysqli_query($this->link,"set character set 'utf8mb4'")) mysqli_query($this->link,"set character set 'utf8'");
//写库
if(!mysqli_query($this->link,"set names 'utf8mb4'")) mysqli_query($this->link,"set names 'utf8'");
 
	return true;
	}
		function fetch($q){
			return $q ? mysqli_fetch_assoc($q) : false;
		}
		function get_row($q){
			if(!qifu_db_query_allowed($q)) return false;
			$result = mysqli_query($this->link,$q);
			return $result ? mysqli_fetch_assoc($result) : false;
		}
		function get_results($q){
			if(!qifu_db_query_allowed($q)) return array();
			$result = mysqli_query($this->link,$q);
			if(!$result) return array();
			$rows = array();
			while($row = mysqli_fetch_assoc($result)) $rows[] = $row;
			return $rows;
		}
		function num_rows($q){
			return $q ? mysqli_num_rows($q) : 0;
		}
		function count($q){
			if(!qifu_db_query_allowed($q)) return 0;
			$result = mysqli_query($this->link,$q);
			$count = $result ? mysqli_fetch_array($result) : array(0);
			return $count ? $count[0] : 0;
		}
		function query($q){
			if(!qifu_db_query_allowed($q)) return false;
			return mysqli_query($this->link,$q);
		}
		function prepared_query($q, $params = array()){
			if(!qifu_db_query_allowed($q)) return false;
			$stmt = mysqli_prepare($this->link, $q);
			if(!$stmt) return false;
			if($params){
				$types = '';
				$bind = array();
				foreach(array_values($params) as $key => $value){
					if(is_int($value)) $types .= 'i';
					elseif(is_float($value)) $types .= 'd';
					else $types .= 's';
					$bind[$key] = $value;
				}
				$refs = array($types);
				foreach($bind as $key => &$value) $refs[] = &$value;
				if(!call_user_func_array(array($stmt, 'bind_param'), $refs)) return false;
			}
			if(!mysqli_stmt_execute($stmt)) return false;
			return $this->result = $stmt;
		}
		function prepared_results($q, $params = array()){
			$stmt = $this->prepared_query($q, $params);
			if(!$stmt) return array();
			$result = mysqli_stmt_get_result($stmt);
			if(!$result) return array();
			$rows = array();
			while($row = mysqli_fetch_assoc($result)) $rows[] = $row;
			return $rows;
		}
		function prepared_row($q, $params = array()){
			$rows = $this->prepared_results($q, $params);
			return isset($rows[0]) ? $rows[0] : false;
		}
		function prepared_value($q, $params = array()){
			$row = $this->prepared_row($q, $params);
			return $row ? reset($row) : false;
		}
		function prepared_insert($q, $params = array()){
			if(!$this->prepared_query($q, $params)) return false;
			return mysqli_insert_id($this->link);
		}
		function escape($str){
			return mysqli_real_escape_string($this->link,(string)$str);
		}
		function insert($q){
			if($this->query($q))
				return mysqli_insert_id($this->link); 
			return false;
		}
		function affected(){
			return mysqli_affected_rows($this->link);
		}
		function insert_array($table,$array){
			$values = array();
			foreach($array as $value) $values[] = $this->escape($value);
			$q = "INSERT INTO `$table`";
			$q .=" (`".implode("`,`",array_keys($array))."`) ";
			$q .=" VALUES ('".implode("','",$values)."') ";
			
			return $this->insert($q);
		}
		function error(){
			$error = mysqli_error($this->link);
			$errno = mysqli_errno($this->link);
			return '['.$errno.'] '.$error;
		}
		function close(){
			$q = mysqli_close($this->link);
			return $q;
		}
	}
}
elseif(extension_loaded('pdo_mysql')) {
	class DB {
		var $link = null;
		var $result = null;

		function __construct($db_host,$db_user,$db_pass,$db_name,$db_port){
			$dsn = 'mysql:host='.$db_host.';port='.$db_port.';dbname='.$db_name.';charset=utf8mb4';
			try {
				$this->link = new PDO($dsn, $db_user, $db_pass, array(
					PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				));
			} catch (PDOException $e) {
				if(function_exists('dh_json_exit')) dh_json_exit('数据库连接失败：'.$e->getMessage());
				die('Connect Error '.$e->getMessage());
			}
			$this->link->query("set sql_mode = ''");
			if(!$this->link->query("set character set 'utf8mb4'")) $this->link->query("set character set 'utf8'");
			if(!$this->link->query("set names 'utf8mb4'")) $this->link->query("set names 'utf8'");
			return true;
		}
		function fetch($q){
			return $q ? $q->fetch(PDO::FETCH_ASSOC) : false;
		}
		function get_row($q){
			if(!qifu_db_query_allowed($q)) return false;
			$result = $this->link->query($q);
			return $result ? $result->fetch(PDO::FETCH_ASSOC) : false;
		}
		function get_results($q){
			if(!qifu_db_query_allowed($q)) return array();
			$result = $this->link->query($q);
			return $result ? $result->fetchAll(PDO::FETCH_ASSOC) : array();
		}
		function num_rows($q){
			return $q ? $q->rowCount() : 0;
		}
		function count($q){
			if(!qifu_db_query_allowed($q)) return 0;
			$result = $this->link->query($q);
			return $result ? $result->fetchColumn() : 0;
		}
		function query($q){
			if(!qifu_db_query_allowed($q)) return false;
			return $this->result=$this->link->query($q);
		}
		function prepared_query($q, $params = array()){
			if(!qifu_db_query_allowed($q)) return false;
			$stmt = $this->link->prepare($q);
			if(!$stmt || !$stmt->execute(array_values($params))) return false;
			return $this->result = $stmt;
		}
		function prepared_row($q, $params = array()){
			$stmt = $this->prepared_query($q, $params);
			return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
		}
		function prepared_results($q, $params = array()){
			$stmt = $this->prepared_query($q, $params);
			return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
		}
		function prepared_value($q, $params = array()){
			$stmt = $this->prepared_query($q, $params);
			return $stmt ? $stmt->fetchColumn() : false;
		}
		function prepared_insert($q, $params = array()){
			if(!$this->prepared_query($q, $params)) return false;
			return $this->link->lastInsertId();
		}
		function escape($str){
			$quoted = $this->link->quote((string)$str);
			return $quoted !== false ? substr($quoted, 1, -1) : addslashes((string)$str);
		}
		function insert($q){
			if($this->query($q))
				return $this->link->lastInsertId();
			return false;
		}
		function affected(){
			return $this->result ? $this->result->rowCount() : 0;
		}
		function insert_array($table,$array){
			$values = array();
			foreach($array as $value) $values[] = $this->escape($value);
			$q = "INSERT INTO `$table`";
			$q .=" (`".implode("`,`",array_keys($array))."`) ";
			$q .=" VALUES ('".implode("','",$values)."') ";

			return $this->insert($q);
		}
		function error(){
			$error = $this->link->errorInfo();
			return '['.$error[1].'] '.$error[2];
		}
		function close(){
			$this->link = null;
			return true;
		}
	}
} else { // we use the old mysql
	class DB {
		var $link = null;

		function __construct($db_host,$db_user,$db_pass,$db_name,$db_port){
		if(!function_exists('mysql_connect')) {
			if(function_exists('dh_json_exit')) dh_json_exit('服务器缺少 MySQL 数据库扩展，请开启 mysqli 或 pdo_mysql');
			die('Database extension missing: please enable mysqli or pdo_mysql.');
		}

		$this->link = @mysql_connect($db_host.':'.$db_port, $db_user, $db_pass);
            
		if (!$this->link) {
			if(function_exists('dh_json_exit')) dh_json_exit('数据库连接失败：'.mysql_error());
			die('Connect Error (' . mysql_errno() . ') '.mysql_error());
		}
            
			mysql_select_db($db_name, $this->link) or die(mysql_error($this->link));

mysql_query("set sql_mode = ''");
//字符转换，读库
if(!mysql_query("set character set 'utf8mb4'")) mysql_query("set character set 'utf8'");
//写库
if(!mysql_query("set names 'utf8mb4'")) mysql_query("set names 'utf8'");
 

	return true;
		}
		function fetch($q){
			return $q ? mysql_fetch_assoc($q) : false;
		}
		function get_row($q){
			if(!qifu_db_query_allowed($q)) return false;
			$result = mysql_query($q, $this->link);
			return $result ? mysql_fetch_assoc($result) : false;
		}
		function get_results($q){
			if(!qifu_db_query_allowed($q)) return array();
			$result = mysql_query($q, $this->link);
			if(!$result) return array();
			$rows = array();
			while($row = mysql_fetch_assoc($result)) $rows[] = $row;
			return $rows;
		}
        function num_rows($q){
			return $q ? mysql_num_rows($q) : 0;
		}
		function count($q){
			if(!qifu_db_query_allowed($q)) return 0;
			$result = mysql_query($q, $this->link);
			$count = $result ? mysql_fetch_array($result) : array(0);
			return $count ? $count[0] : 0;
		}
        function query($q){
			if(!qifu_db_query_allowed($q)) return false;
			return mysql_query($q, $this->link);
		}
		function escape($str){
			return mysql_real_escape_string((string)$str, $this->link);
		}
		function affected(){
			return mysql_affected_rows($this->link);
		}
		function insert($q){
			if($this->query($q))
				return mysql_insert_id($this->link);
			return false;
		}
		function insert_array($table,$array){
			$q = "INSERT INTO `$table`";
			$q .=" (`".implode("`,`",array_keys($array))."`) ";
			$q .=" VALUES ('".implode("','",array_values($array))."') ";

			return $this->insert($q);
		}
		function error(){
			$error = mysql_error($this->link);
			$errno = mysql_errno($this->link);
			return '['.$errno.'] '.$error;
		}
		function close(){
			$q = mysql_close($this->link);
			return $q;
		}
	}

}
?>
