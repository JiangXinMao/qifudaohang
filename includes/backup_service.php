<?php
/* Structured, portable database backup and restore service. */
if(!defined('IN_CRONLITE')) exit();

function qifu_backup_allowed_tables(){
    return array(
        'web_config',
        'web_dh',
        'web_category',
        'web_log',
        'web_backup',
        'web_update_history',
        'web_site_stats',
        'web_stats',
        'web_daily_visitors',
        'web_links',
        'web_ads',
        'web_ad_stats',
        'web_login_attempts'
    );
}

function qifu_backup_required_tables(){
    return array('web_config', 'web_dh', 'web_category', 'web_log');
}

function qifu_backup_ensure_schema($DB){
    if($DB->get_row("SHOW TABLES LIKE 'web_backup'")) return true;
    if(defined('SQLITE')){
        $created = $DB->query('CREATE TABLE IF NOT EXISTS web_backup (id INTEGER PRIMARY KEY AUTOINCREMENT, filename TEXT NOT NULL, size INTEGER NOT NULL, addtime INTEGER NOT NULL)');
    } else {
        $created = $DB->query('CREATE TABLE IF NOT EXISTS web_backup (id int(11) NOT NULL AUTO_INCREMENT, filename varchar(255) NOT NULL, size int(11) NOT NULL, addtime int(11) NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
    if(!$created && !$DB->get_row("SHOW TABLES LIKE 'web_backup'")) throw new RuntimeException('无法创建备份记录表，请检查数据库权限。');
    return true;
}

function qifu_backup_json_encode($value){
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if($json === false) throw new RuntimeException('备份数据无法编码：'.json_last_error_msg());
    return $json;
}

function qifu_backup_existing_tables($DB){
    $tables = array();
    foreach(qifu_backup_allowed_tables() as $table){
        if($DB->get_row("SHOW TABLES LIKE '".$table."'")) $tables[] = $table;
    }
    return $tables;
}

function qifu_backup_build_payload($DB, $reason = 'manual'){
    qifu_backup_ensure_schema($DB);
    $tables = array();
    $total_rows = 0;
    foreach(qifu_backup_existing_tables($DB) as $table){
        $rows = $DB->get_results('SELECT * FROM `'.$table.'`');
        if(!is_array($rows)) $rows = array();
        $rows = array_values($rows);
        $tables[$table] = array(
            'rowCount'=>count($rows),
            'rows'=>$rows
        );
        $total_rows += count($rows);
    }

    foreach(qifu_backup_required_tables() as $required){
        if(!isset($tables[$required])) throw new RuntimeException('数据库缺少核心数据表：'.$required);
    }

    $payload = array(
        'format'=>'qifu-database-backup',
        'formatVersion'=>1,
        'product'=>defined('QIFU_PRODUCT_NAME') ? (string)QIFU_PRODUCT_NAME : '祈福导航系统',
        'productVersion'=>defined('QIFU_PRODUCT_VERSION') ? (string)QIFU_PRODUCT_VERSION : 'V1.5.0',
        'database'=>defined('SQLITE') ? 'sqlite' : 'mysql',
        'createdAt'=>time(),
        'reason'=>(string)$reason,
        'tableCount'=>count($tables),
        'rowCount'=>$total_rows,
        'tables'=>$tables
    );
    $payload['checksum'] = hash('sha256', qifu_backup_json_encode($tables));
    return $payload;
}

function qifu_backup_storage_prefix(){
    return "<?php http_response_code(404); exit; ?>\n";
}

function qifu_backup_create_file($DB, $reason = 'manual'){
    $payload = qifu_backup_build_payload($DB, $reason);
    $directory = ROOT.'backup'.DIRECTORY_SEPARATOR;
    if(!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)){
        throw new RuntimeException('无法创建备份目录。');
    }

    try {
        $suffix = bin2hex(random_bytes(3));
    } catch(Throwable $error) {
        $suffix = substr(sha1(uniqid('', true)), 0, 6);
    }
    $filename = 'backup_'.date('Ymd_His').'_v14_'.$suffix.'.qifubak.php';
    $path = $directory.$filename;
    $temporary = $path.'.tmp';
    $content = qifu_backup_storage_prefix().qifu_backup_json_encode($payload);
    $written = @file_put_contents($temporary, $content, LOCK_EX);
    if($written !== strlen($content) || !@rename($temporary, $path)){
        @unlink($temporary);
        throw new RuntimeException('备份文件写入失败，请检查 backup 目录权限。');
    }

    return array(
        'filename'=>$filename,
        'path'=>$path,
        'size'=>intval(filesize($path)),
        'tableCount'=>intval($payload['tableCount']),
        'rowCount'=>intval($payload['rowCount']),
        'createdAt'=>intval($payload['createdAt']),
        'reason'=>(string)$reason
    );
}

function qifu_backup_register_file($DB, $backup){
    qifu_backup_ensure_schema($DB);
    return $DB->prepared_query(
        'INSERT INTO web_backup (filename,size,addtime) VALUES (?,?,?)',
        array((string)$backup['filename'], intval($backup['size']), intval($backup['createdAt']))
    ) !== false;
}

function qifu_backup_decode_content($content){
    $content = (string)$content;
    $prefix = qifu_backup_storage_prefix();
    if(strpos($content, $prefix) === 0) $content = substr($content, strlen($prefix));
    if(strlen($content) < 20) throw new RuntimeException('备份文件为空或不完整。');
    $payload = json_decode($content, true);
    if(!is_array($payload)) throw new RuntimeException('备份文件不是有效的祈福数据包。');
    return $payload;
}

function qifu_backup_read_file($path){
    if(!is_file($path) || !is_readable($path)) throw new RuntimeException('无法读取备份文件。');
    $content = file_get_contents($path);
    if($content === false) throw new RuntimeException('备份文件读取失败。');
    return qifu_backup_decode_content($content);
}

function qifu_backup_validate_payload($DB, $payload){
    if(!is_array($payload) || ($payload['format'] ?? '') !== 'qifu-database-backup' || intval($payload['formatVersion'] ?? 0) !== 1){
        throw new RuntimeException('备份格式不受支持。');
    }
    $tables = isset($payload['tables']) && is_array($payload['tables']) ? $payload['tables'] : array();
    $checksum = isset($payload['checksum']) ? strtolower((string)$payload['checksum']) : '';
    $actual = hash('sha256', qifu_backup_json_encode($tables));
    if(!preg_match('/^[a-f0-9]{64}$/', $checksum) || !hash_equals($checksum, $actual)){
        throw new RuntimeException('备份校验失败，文件可能已损坏或被修改。');
    }

    $allowed = array_flip(qifu_backup_allowed_tables());
    $existing = qifu_backup_existing_tables($DB);
    foreach($existing as $table){
        if(!array_key_exists($table, $tables)) throw new RuntimeException('备份不完整，缺少数据表：'.$table);
    }
    foreach(qifu_backup_required_tables() as $required){
        if(!array_key_exists($required, $tables)) throw new RuntimeException('备份缺少核心数据表：'.$required);
    }

    $total_rows = 0;
    foreach($tables as $table=>$entry){
        if(!isset($allowed[$table]) || !in_array($table, $existing, true)){
            throw new RuntimeException('备份包含当前系统不支持的数据表：'.$table);
        }
        if(!is_array($entry) || !isset($entry['rows']) || !is_array($entry['rows'])){
            throw new RuntimeException('数据表内容无效：'.$table);
        }
        if(intval($entry['rowCount'] ?? -1) !== count($entry['rows'])){
            throw new RuntimeException('数据表记录数校验失败：'.$table);
        }
        $columns_checked = array();
        foreach($entry['rows'] as $row){
            if(!is_array($row)) throw new RuntimeException('数据表记录格式无效：'.$table);
            foreach($row as $column=>$value){
                if(!preg_match('/^[a-zA-Z0-9_]+$/', (string)$column)) throw new RuntimeException('备份字段名无效。');
                if(!array_key_exists($column, $columns_checked)){
                    $exists = $DB->get_row("SHOW COLUMNS FROM `".$table."` LIKE '".$column."'");
                    if(!$exists) throw new RuntimeException('当前数据库缺少字段：'.$table.'.'.$column);
                    $columns_checked[$column] = true;
                }
                if(!is_null($value) && !is_scalar($value)) throw new RuntimeException('备份字段值无效：'.$table.'.'.$column);
            }
        }
        $total_rows += count($entry['rows']);
    }
    if(intval($payload['tableCount'] ?? -1) !== count($tables) || intval($payload['rowCount'] ?? -1) !== $total_rows){
        throw new RuntimeException('备份汇总信息校验失败。');
    }
    return $payload;
}

function qifu_backup_transaction_begin($DB){
    if($DB->link instanceof PDO) return $DB->link->beginTransaction();
    if($DB->link instanceof mysqli) return mysqli_begin_transaction($DB->link);
    return $DB->query('START TRANSACTION') !== false;
}

function qifu_backup_transaction_commit($DB){
    if($DB->link instanceof PDO) return !$DB->link->inTransaction() || $DB->link->commit();
    if($DB->link instanceof mysqli) return mysqli_commit($DB->link);
    return $DB->query('COMMIT') !== false;
}

function qifu_backup_transaction_rollback($DB){
    if($DB->link instanceof PDO) return !$DB->link->inTransaction() || $DB->link->rollBack();
    if($DB->link instanceof mysqli) return mysqli_rollback($DB->link);
    return $DB->query('ROLLBACK') !== false;
}

function qifu_backup_current_admin_rows($DB){
    $keys = array('admin_user', 'admin_pwd', 'admin_pwd_hash', 'admin_auth_version');
    $rows = array();
    foreach($keys as $key){
        $row = $DB->prepared_row('SELECT k,v FROM web_config WHERE k=?', array($key));
        if($row) $rows[$key] = $row['v'];
    }
    return $rows;
}

function qifu_backup_apply_payload($DB, $payload, $preserve_admin = true){
    $payload = qifu_backup_validate_payload($DB, $payload);
    $admin_rows = $preserve_admin ? qifu_backup_current_admin_rows($DB) : array();
    $tables = $payload['tables'];
    $existing = qifu_backup_existing_tables($DB);
    $transaction = qifu_backup_transaction_begin($DB);
    try {
        foreach(array_reverse($existing) as $table){
            if($DB->query('DELETE FROM `'.$table.'`') === false) throw new RuntimeException('无法清空数据表：'.$table);
        }
        foreach(qifu_backup_allowed_tables() as $table){
            if(!isset($tables[$table])) continue;
            foreach($tables[$table]['rows'] as $row){
                if($table === 'web_config' && isset($row['k']) && array_key_exists((string)$row['k'], $admin_rows)) continue;
                if(!$row) continue;
                $columns = array_keys($row);
                $quoted = '`'.implode('`,`', $columns).'`';
                $placeholders = implode(',', array_fill(0, count($columns), '?'));
                if($DB->prepared_query('INSERT INTO `'.$table.'` ('.$quoted.') VALUES ('.$placeholders.')', array_values($row)) === false){
                    throw new RuntimeException('写入数据表失败：'.$table);
                }
            }
        }
        foreach($admin_rows as $key=>$value){
            if($DB->prepared_query('INSERT INTO web_config (k,v) VALUES (?,?)', array($key,$value)) === false){
                throw new RuntimeException('无法保留当前管理员登录信息。');
            }
        }
        if($transaction && !qifu_backup_transaction_commit($DB)) throw new RuntimeException('数据库事务提交失败。');
    } catch(Throwable $error) {
        if($transaction) qifu_backup_transaction_rollback($DB);
        throw $error;
    }
}

function qifu_backup_restore_file($DB, $path){
    $incoming = qifu_backup_validate_payload($DB, qifu_backup_read_file($path));
    $safety = qifu_backup_create_file($DB, 'pre_restore');
    try {
        qifu_backup_apply_payload($DB, $incoming, true);
    } catch(Throwable $restore_error) {
        try {
            qifu_backup_apply_payload($DB, qifu_backup_read_file($safety['path']), true);
        } catch(Throwable $rollback_error) {
            qifu_backup_register_file($DB, $safety);
            throw new RuntimeException('恢复失败且自动回滚未完成，请使用安全快照 '.$safety['filename'].' 手动恢复。');
        }
        qifu_backup_register_file($DB, $safety);
        throw new RuntimeException('恢复失败，数据库已自动回滚：'.$restore_error->getMessage());
    }
    if(!qifu_backup_register_file($DB, $safety)){
        throw new RuntimeException('数据已恢复，但恢复前安全快照未能写入备份列表。');
    }
    return array(
        'tableCount'=>intval($incoming['tableCount']),
        'rowCount'=>intval($incoming['rowCount']),
        'sourceVersion'=>(string)($incoming['productVersion'] ?? ''),
        'safetyBackup'=>(string)$safety['filename']
    );
}
