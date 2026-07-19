<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

define('IN_CRONLITE', true);
define('SYSTEM_ROOT', dirname(__DIR__).'/includes/');
define('ROOT', dirname(__DIR__).'/');
define('CACHE_FILE', 0);
define('SYS_KEY', 'test_key');
require ROOT.'config.php';
require SYSTEM_ROOT.'db.class.php';
require SYSTEM_ROOT.'function.php';
require SYSTEM_ROOT.'cache.class.php';
require SYSTEM_ROOT.'online_stats.php';

$DB = new DB($dbconfig['host'], $dbconfig['user'], $dbconfig['pwd'], $dbconfig['dbname'], $dbconfig['port']);
$CACHE = new CACHE();
$conf = $CACHE->pre_fetch();
$original_conf = $conf;
$conf['online_stats_privacy_ip'] = '0';
$failures = array();

function check_online_stats($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

qifu_online_stats_ensure_tables();
$original_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
$_SERVER['REMOTE_ADDR'] = '198.51.100.42';
$today = date('Y-m-d');
$before = intval($DB->prepared_value('SELECT COALESCE(views,0) FROM web_stats WHERE stat_date=?', array($today)));

$DB->link->beginTransaction();
qifu_online_stats_track_visit();
$real = qifu_online_stats_real_snapshot();
$visitor = $DB->prepared_row('SELECT visitor_hash,views FROM web_daily_visitors WHERE stat_date=? ORDER BY last_seen DESC LIMIT 1', array($today));
check_online_stats($visitor && strlen((string)$visitor['visitor_hash']) === 64, 'visitor was not stored as a SHA-256 HMAC');
check_online_stats(strpos((string)$visitor['visitor_hash'], '198.51.100.42') === false, 'raw visitor IP was stored');
check_online_stats($real['today_visits'] >= $before + 1, 'real visit count did not increment');
check_online_stats($real['today_active'] >= 1, 'real active visitor count did not increment');
check_online_stats($real['ip'] === '198.51.100.42', 'displayed visitor IP is incorrect');
$DB->link->rollBack();

$random_a = qifu_online_stats_random_snapshot();
$random_b = qifu_online_stats_random_snapshot();
check_online_stats($random_a === $random_b, 'random mode changed within the same day');
check_online_stats($random_a['today_active'] <= $random_a['today_visits'], 'random active visitors exceed today visits');
check_online_stats($random_a['total_visits'] >= $random_a['today_visits'], 'random total visits are below today visits');

$conf['online_stats_random_scheme'] = 'rule';
$conf['online_stats_random_active_min'] = '10';
$conf['online_stats_random_active_max'] = '24';
$conf['online_stats_random_today_min'] = '100';
$conf['online_stats_random_today_max'] = '260';
$conf['online_stats_random_trend'] = 'rise';
$conf['online_stats_random_start_date'] = $today;
$conf['online_stats_random_base_visits'] = '5000';
$conf['online_stats_random_stable'] = '1';
$rule_a = qifu_online_stats_random_snapshot();
$rule_b = qifu_online_stats_random_snapshot();
check_online_stats($rule_a === $rule_b, 'rule random mode changed within the same day');
check_online_stats($rule_a['today_active'] >= 10 && $rule_a['today_active'] <= 24, 'rule active visitors are outside configured range');
check_online_stats($rule_a['today_visits'] >= 100 && $rule_a['today_visits'] <= 260, 'rule visits are outside the configured range');
check_online_stats($rule_a['today_active'] <= $rule_a['today_visits'], 'rule active visitors exceed today visits');
check_online_stats($rule_a['total_visits'] === 5000 + $rule_a['today_visits'], 'rule base visits were not applied on the start date');

$conf['online_stats_random_start_date'] = date('Y-m-d', strtotime('-2 days'));
$grown = qifu_online_stats_random_snapshot();
check_online_stats($grown['total_visits'] > $rule_a['total_visits'], 'rule total visits did not grow after the start date');

$conf['online_stats_privacy_ip'] = '1';
$masked = qifu_online_stats_real_snapshot();
check_online_stats($masked['ip'] === '已隐藏', 'privacy switch did not hide the visitor IP');
$conf = $original_conf;

$settings_source = file_get_contents(ROOT.'admin/set.php');
check_online_stats(strpos($settings_source, 'name="online_stats_random_scheme"') !== false, 'random scheme control is missing from settings');
check_online_stats(strpos($settings_source, 'name="online_stats_random_active_min"') !== false, 'active range controls are missing from settings');
check_online_stats(strpos($settings_source, 'name="online_stats_random_today_max"') !== false, 'visit range controls are missing from settings');
check_online_stats(strpos($settings_source, 'name="online_stats_random_start_date"') !== false, 'rule start date control is missing from settings');
check_online_stats(strpos($settings_source, 'name="online_stats_random_base_visits"') !== false, 'base visits control is missing from settings');
check_online_stats(strpos($settings_source, 'name="online_stats_privacy_ip"') !== false, 'privacy control is missing from settings');
check_online_stats(strpos($settings_source, 'name="online_stats_color"') !== false, 'stats color control is missing from settings');

$front_source = file_get_contents(ROOT.'index.php');
check_online_stats(strpos($front_source, "\$online_stats_color = isset(\$conf['online_stats_color']) && \$conf['online_stats_color'] === 'dark' ? 'dark' : 'highlight';") !== false, 'frontend does not default stats to highlight while preserving an explicit dark setting');
check_online_stats(strpos($front_source, "\$online_stats_text_color = \$online_stats_color === 'highlight' ? '#fff'") !== false, 'highlight stats color does not match the site title color');
check_online_stats(strpos($front_source, '.foot{flex:0 0 auto;margin-top:0;padding:24px 0 20px;background:transparent;') !== false, 'footer background is no longer fully transparent');
check_online_stats(strpos($front_source, 'class="online-stats-label"') !== false, 'online stats labels are not structurally separated from values');
check_online_stats(strpos($front_source, 'class="online-stats-unit"') !== false, 'online stats units are not structurally separated from values');
check_online_stats(strpos($front_source, '您的 IP') !== false, 'online stats IP label is missing');

if($original_ip === null) unset($_SERVER['REMOTE_ADDR']);
else $_SERVER['REMOTE_ADDR'] = $original_ip;

if($failures){
    fwrite(STDERR, "Online stats tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Online stats tests passed.\n";
?>
