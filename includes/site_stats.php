<?php
if(!defined('IN_CRONLITE')) exit();

function qifu_site_stats_ensure_schema(){
    global $DB;
    $table = $DB->get_row("SHOW TABLES LIKE 'web_site_stats'");
    if(empty($table)){
        if(defined('SQLITE')){
            $DB->query('CREATE TABLE IF NOT EXISTS web_site_stats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                site_id INTEGER NOT NULL,
                stat_date TEXT NOT NULL,
                views INTEGER NOT NULL DEFAULT 0,
                impressions INTEGER NOT NULL DEFAULT 0,
                UNIQUE(site_id, stat_date)
            )');
        }else{
            $DB->query('CREATE TABLE web_site_stats (
                id int(11) NOT NULL AUTO_INCREMENT,
                site_id int(11) NOT NULL,
                stat_date date NOT NULL,
                views int(11) NOT NULL DEFAULT 0,
                impressions int(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY site_date (site_id,stat_date),
                UNIQUE KEY site_date_unique (site_id,stat_date)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4');
        }
    }

    $column = $DB->query("SHOW COLUMNS FROM web_site_stats LIKE 'impressions'");
    if(!$DB->fetch($column)){
        $DB->query('ALTER TABLE web_site_stats ADD COLUMN impressions int(11) NOT NULL DEFAULT 0');
    }

    return !empty($DB->get_row("SHOW TABLES LIKE 'web_site_stats'"));
}

function qifu_site_stats_normalize_ids($raw){
    if(is_string($raw)) $raw = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    if(!is_array($raw)) return array();
    $ids = array();
    foreach($raw as $value){
        $id = intval($value);
        if($id > 0) $ids[$id] = $id;
        if(count($ids) >= 500) break;
    }
    return array_values($ids);
}

function qifu_site_stats_track_impressions($raw_ids, $date = ''){
    global $DB;
    if(!qifu_site_stats_ensure_schema()) return 0;
    $ids = qifu_site_stats_normalize_ids($raw_ids);
    if(!$ids) return 0;

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $active_rows = $DB->prepared_results(
        'SELECT id FROM web_dh WHERE active=1 AND id IN ('.$placeholders.')',
        $ids
    );
    $today = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date) ? (string)$date : date('Y-m-d');
    $tracked = 0;
    foreach($active_rows as $row){
        $site_id = intval($row['id']);
        $result = $DB->prepared_query(
            'INSERT INTO web_site_stats (site_id, stat_date, impressions) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE impressions = impressions + 1',
            array($site_id, $today)
        );
        if($result) $tracked++;
    }
    return $tracked;
}

function qifu_site_stats_track_click($site_id, $date = ''){
    global $DB;
    $site_id = intval($site_id);
    if($site_id <= 0 || !qifu_site_stats_ensure_schema()) return false;
    $site = $DB->prepared_row('SELECT id FROM web_dh WHERE id=? LIMIT 1', array($site_id));
    if(!$site) return false;
    $today = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date) ? (string)$date : date('Y-m-d');
    $result = $DB->prepared_query(
        'INSERT INTO web_site_stats (site_id, stat_date, views) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE views = views + 1',
        array($site_id, $today)
    );
    if(!$result) return false;
    $DB->prepared_query('UPDATE web_dh SET clicks=clicks+1 WHERE id=?', array($site_id));
    return true;
}

function qifu_site_stats_rows($date, $metric = 'views'){
    global $DB;
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) return array();
    if(!qifu_site_stats_ensure_schema()) return array();
    $field = $metric === 'clicks' ? 'views' : 'impressions';
    $rows = $DB->prepared_results(
        'SELECT w.id, w.name, w.url, w.category, COALESCE(s.'.$field.',0) AS count
         FROM web_site_stats s
         INNER JOIN web_dh w ON w.id=s.site_id
         WHERE s.stat_date=? AND s.'.$field.'>0
         ORDER BY s.'.$field.' DESC, w.id ASC',
        array($date)
    );
    return $rows ? $rows : array();
}
?>
