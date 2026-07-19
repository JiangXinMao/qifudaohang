<?php
if(!defined('IN_CRONLITE')) exit();

function qifu_online_stats_ensure_tables(){
    static $ensured = false;
    global $DB;
    if($ensured) return;
    $ensured = true;

    if(!$DB->get_row("SHOW TABLES LIKE 'web_stats'")){
        if(defined('SQLITE')){
            $DB->query("CREATE TABLE IF NOT EXISTS web_stats (id INTEGER PRIMARY KEY AUTOINCREMENT, stat_date TEXT NOT NULL UNIQUE, views INTEGER NOT NULL DEFAULT 0, unique_visitors INTEGER NOT NULL DEFAULT 0)");
        } else {
            $DB->query("CREATE TABLE IF NOT EXISTS web_stats (`id` int(11) NOT NULL AUTO_INCREMENT,`stat_date` date NOT NULL,`views` int(11) NOT NULL DEFAULT 0,`unique_visitors` int(11) NOT NULL DEFAULT 0,PRIMARY KEY (`id`),UNIQUE KEY `stat_date` (`stat_date`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }
    if(!$DB->get_row("SHOW TABLES LIKE 'web_daily_visitors'")){
        if(defined('SQLITE')){
            $DB->query("CREATE TABLE IF NOT EXISTS web_daily_visitors (stat_date TEXT NOT NULL, visitor_hash TEXT NOT NULL, first_seen INTEGER NOT NULL DEFAULT 0, last_seen INTEGER NOT NULL DEFAULT 0, views INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (stat_date, visitor_hash))");
        } else {
            $DB->query("CREATE TABLE IF NOT EXISTS web_daily_visitors (`stat_date` date NOT NULL,`visitor_hash` char(64) NOT NULL,`first_seen` int(11) NOT NULL DEFAULT 0,`last_seen` int(11) NOT NULL DEFAULT 0,`views` int(11) NOT NULL DEFAULT 0,PRIMARY KEY (`stat_date`,`visitor_hash`),KEY `last_seen` (`last_seen`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }
}

function qifu_online_stats_track_visit(){
    global $DB, $conf;
    qifu_online_stats_ensure_tables();
    $today = date('Y-m-d');
    $now = time();
    $secret = !empty($conf['cron_key']) ? (string)$conf['cron_key'] : (defined('SYS_KEY') ? SYS_KEY : 'qifu');
    $visitor_hash = hash_hmac('sha256', $today.'|'.real_ip(), $secret);

    if(defined('SQLITE')){
        $DB->prepared_query('INSERT INTO web_stats (stat_date,views,unique_visitors) VALUES (?,?,0) ON CONFLICT(stat_date) DO UPDATE SET views=views+1', array($today,1));
        $DB->prepared_query('INSERT INTO web_daily_visitors (stat_date,visitor_hash,first_seen,last_seen,views) VALUES (?,?,?,?,1) ON CONFLICT(stat_date,visitor_hash) DO UPDATE SET last_seen=excluded.last_seen,views=views+1', array($today,$visitor_hash,$now,$now));
    } else {
        $DB->prepared_query('INSERT INTO web_stats (stat_date,views,unique_visitors) VALUES (?,?,0) ON DUPLICATE KEY UPDATE views=views+1', array($today,1));
        $DB->prepared_query('INSERT INTO web_daily_visitors (stat_date,visitor_hash,first_seen,last_seen,views) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE last_seen=VALUES(last_seen),views=views+1', array($today,$visitor_hash,$now,$now));
    }
    $unique = intval($DB->prepared_value('SELECT COUNT(*) FROM web_daily_visitors WHERE stat_date=?', array($today)));
    $DB->prepared_query('UPDATE web_stats SET unique_visitors=? WHERE stat_date=?', array($unique,$today));
    $DB->prepared_query('DELETE FROM web_daily_visitors WHERE stat_date<?', array(date('Y-m-d', strtotime('-90 days'))));
}

function qifu_online_stats_real_snapshot(){
    global $DB;
    qifu_online_stats_ensure_tables();
    $today = date('Y-m-d');
    $today_start = strtotime($today.' 00:00:00');
    $row = $DB->prepared_row('SELECT views,unique_visitors FROM web_stats WHERE stat_date=?', array($today));
    $updates = 0;
    if($DB->get_row("SHOW TABLES LIKE 'web_log'")){
        $updates = intval($DB->prepared_value("SELECT COUNT(*) FROM web_log WHERE addtime>=? AND target=? AND action IN ('添加','修改','批量导入')", array($today_start,'站点')));
    }
    return array(
        'today_active' => $row ? intval($row['unique_visitors']) : 0,
        'today_updates' => $updates,
        'total_visits' => intval($DB->prepared_value('SELECT COALESCE(SUM(views),0) FROM web_stats')),
        'today_visits' => $row ? intval($row['views']) : 0,
        'ip' => qifu_online_stats_display_ip(),
    );
}

function qifu_online_stats_config_value($key, $default = ''){
    global $conf;
    return isset($conf[$key]) ? $conf[$key] : $default;
}

function qifu_online_stats_valid_date($value, $fallback){
    $value = trim((string)$value);
    if(preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)){
        $parsed = DateTime::createFromFormat('!Y-m-d', $value);
        if($parsed && $parsed->format('Y-m-d') === $value) return $value;
    }
    return $fallback;
}

function qifu_online_stats_rule_start_date(){
    $legacy = qifu_online_stats_config_value('online_stats_random_seed_date', '');
    $configured = qifu_online_stats_config_value('online_stats_random_start_date', $legacy);
    $today = date('Y-m-d');
    $start_date = qifu_online_stats_valid_date($configured, $today);
    return $start_date > $today ? $today : $start_date;
}

function qifu_online_stats_elapsed_days($start_date){
    $start = strtotime($start_date.' 00:00:00');
    $today = strtotime(date('Y-m-d').' 00:00:00');
    if($start === false || $today === false || $start >= $today) return 0;
    return max(0, intval(floor(($today - $start) / 86400)));
}

function qifu_online_stats_range($min_key, $max_key, $default_min, $default_max){
    $min = max(0, min(1000000, intval(qifu_online_stats_config_value($min_key, $default_min))));
    $max = max(0, min(1000000, intval(qifu_online_stats_config_value($max_key, $default_max))));
    if($max < $min){
        $swap = $min;
        $min = $max;
        $max = $swap;
    }
    return array($min, $max);
}

function qifu_online_stats_random_number_for_seed($key, $min, $max, $seed){
    global $conf;
    $secret = !empty($conf['cron_key']) ? (string)$conf['cron_key'] : (defined('SYS_KEY') ? SYS_KEY : 'qifu');
    $hex = substr(hash_hmac('sha256', $seed.'|'.$key, $secret), 0, 8);
    $range = max(1, intval($max) - intval($min) + 1);
    return intval($min) + (intval(hexdec($hex)) % $range);
}

function qifu_online_stats_random_number($key, $min, $max){
    $stable = qifu_online_stats_config_value('online_stats_random_stable', '1') !== '0';
    $seed = $stable ? date('Y-m-d') : microtime(true).'|'.mt_rand();
    return qifu_online_stats_random_number_for_seed($key, $min, $max, $seed);
}

function qifu_online_stats_trend_range($min, $max, $trend, $elapsed_days){
    if($trend === 'steady' || $max <= $min) return array($min, $max);
    $progress = min(1, max(0, intval($elapsed_days) / 30));
    $span = $max - $min;
    $center = $trend === 'rise'
        ? $min + intval(round($span * $progress))
        : $max - intval(round($span * $progress));
    $half_window = max(1, intval(ceil($span * 0.3)));
    return array(max($min, $center - $half_window), min($max, $center + $half_window));
}

function qifu_online_stats_rule_total($base, $start_date, $today_min, $today_max, $trend, $today_visits){
    $elapsed_days = qifu_online_stats_elapsed_days($start_date);
    $base = max(0, min(1000000000, intval($base)));
    $total = $base;
    $loop_days = min($elapsed_days, 3660);
    $skipped_days = $elapsed_days - $loop_days;
    if($skipped_days > 0){
        $settled_range = qifu_online_stats_trend_range($today_min, $today_max, $trend, 30);
        $total += $skipped_days * intval(round(($settled_range[0] + $settled_range[1]) / 2));
    }
    $start_timestamp = strtotime($start_date.' 00:00:00');
    for($day = $skipped_days; $day < $elapsed_days; $day++){
        $day_date = date('Y-m-d', $start_timestamp + ($day * 86400));
        $day_range = qifu_online_stats_trend_range($today_min, $today_max, $trend, $day);
        $total += qifu_online_stats_random_number_for_seed('rule_daily_visits', $day_range[0], $day_range[1], $day_date);
    }
    return max($today_visits, $total + $today_visits);
}

function qifu_online_stats_display_ip(){
    return qifu_online_stats_config_value('online_stats_privacy_ip', '0') === '1' ? '已隐藏' : real_ip();
}

function qifu_online_stats_random_snapshot(){
    $scheme = qifu_online_stats_config_value('online_stats_random_scheme', 'builtin') === 'rule' ? 'rule' : 'builtin';
    if($scheme === 'rule'){
        $active_range = qifu_online_stats_range('online_stats_random_active_min', 'online_stats_random_active_max', 1, 8);
        $today_range = qifu_online_stats_range('online_stats_random_today_min', 'online_stats_random_today_max', 8, 36);
        $trend = in_array(qifu_online_stats_config_value('online_stats_random_trend', 'steady'), array('steady','rise','fall'), true)
            ? qifu_online_stats_config_value('online_stats_random_trend', 'steady')
            : 'steady';
        $start_date = qifu_online_stats_rule_start_date();
        $elapsed_days = qifu_online_stats_elapsed_days($start_date);
        $trend_range = qifu_online_stats_trend_range($today_range[0], $today_range[1], $trend, $elapsed_days);
        $today_visits = qifu_online_stats_random_number('today_visits', $trend_range[0], $trend_range[1]);
        $today_active = min($today_visits, qifu_online_stats_random_number('today_active', $active_range[0], $active_range[1]));
        $today_updates = qifu_online_stats_random_number('today_updates', 0, 3);
        $base_visits = qifu_online_stats_config_value('online_stats_random_base_visits', 5000);
        $total_visits = qifu_online_stats_rule_total($base_visits, $start_date, $today_range[0], $today_range[1], $trend, $today_visits);
    } else {
        $today_visits = qifu_online_stats_random_number('today_visits', 120, 960);
        $active_percent = qifu_online_stats_random_number('active_percent', 35, 72);
        $days = max(0, intval(floor((strtotime(date('Y-m-d')) - strtotime('2024-01-01')) / 86400)));
        $today_active = max(1, intval(floor($today_visits * $active_percent / 100)));
        $today_updates = qifu_online_stats_random_number('today_updates', 0, 18);
        $total_visits = 10000 + ($days * 137) + qifu_online_stats_random_number('total_jitter', 0, 136);
    }
    return array(
        'today_active' => $today_active,
        'today_updates' => $today_updates,
        'total_visits' => $total_visits,
        'today_visits' => $today_visits,
        'ip' => qifu_online_stats_display_ip(),
    );
}

function qifu_online_stats_snapshot($mode){
    return $mode === 'random' ? qifu_online_stats_random_snapshot() : qifu_online_stats_real_snapshot();
}
?>
