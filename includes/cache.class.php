<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */
 
if(!defined('IN_CRONLITE'))exit();
class CACHE {
	public $file_name = '';

	public function __construct(){
		global $pass;
		$this->file_name=ROOT.'includes/cache/'.md5(md5(isset($pass) ? (string)$pass : '')).'.txt';
	}
	public function get($key) {
		global $_CACHE;
		return isset($_CACHE[$key]) ? $_CACHE[$key] : null;
	}
	public function read() {
		if(CACHE_FILE==1) return is_file($this->file_name) ? str_replace('<?php exit;//','',file_get_contents($this->file_name)) : '';
		global $DB;
		$row=$DB->get_row("SELECT v FROM web_config WHERE k='cache' limit 1");
		return $row && isset($row['v']) ? $row['v'] : '';
	}
	public function save($value) {
		if (is_array($value)) $value = serialize($value);
		if(CACHE_FILE==1) return file_put_contents($this->file_name,'<?php exit;//'.$value);
		global $DB;
		return $DB->prepared_query("UPDATE web_config SET v=? WHERE k='cache'", array((string)$value));
	}
	public function pre_fetch(){
		global $_CACHE;
		$_CACHE=array();
		$cache = $this->read();
		$cache_data = @unserialize($cache);
		if(!is_array($cache_data)) $cache_data = array();
		$_CACHE = $cache_data;
		if(empty($_CACHE['version'])) $_CACHE = $this->update();
		return $_CACHE;
	}
	public function update() {
		global $DB;
		$cache = array();
		$query = $DB->query('SELECT * FROM web_config where 1');
		while($result = $DB->fetch($query)){
			if($result['k']=='cache') continue;
			$cache[ $result['k'] ] = $result['v'];
		}
		$this->save($cache);
		return $cache;
	}
	public function clear() {
		global $DB;
		return $DB->prepared_query("UPDATE web_config SET v='' WHERE k='cache'");
	}
}
