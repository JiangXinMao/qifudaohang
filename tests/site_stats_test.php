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
require SYSTEM_ROOT.'site_stats.php';

$DB = new DB($dbconfig['host'], $dbconfig['user'], $dbconfig['pwd'], $dbconfig['dbname'], $dbconfig['port']);
$failures = array();

function check_site_stats($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_site_stats(qifu_site_stats_ensure_schema(), 'site statistics schema was not created');
$column = $DB->query("SHOW COLUMNS FROM web_site_stats LIKE 'impressions'");
check_site_stats((bool)$DB->fetch($column), 'impressions migration was not applied');
check_site_stats(qifu_site_stats_normalize_ids('3,3,0,-2,4') === array(3, 4), 'site ID normalization is incorrect');

$site = $DB->get_row('SELECT id FROM web_dh WHERE active=1 ORDER BY id ASC LIMIT 1');
check_site_stats((bool)$site, 'test database has no active site');
if($site){
    $site_id = intval($site['id']);
    $date = date('Y-m-d');
    $before_impressions = intval($DB->prepared_value('SELECT COALESCE(impressions,0) FROM web_site_stats WHERE site_id=? AND stat_date=?', array($site_id, $date)));
    $before_clicks = intval($DB->prepared_value('SELECT COALESCE(views,0) FROM web_site_stats WHERE site_id=? AND stat_date=?', array($site_id, $date)));
    $DB->link->beginTransaction();
    $tracked = qifu_site_stats_track_impressions(array($site_id, $site_id, 0, -1, 2147483647), $date);
    $after_impressions = intval($DB->prepared_value('SELECT COALESCE(impressions,0) FROM web_site_stats WHERE site_id=? AND stat_date=?', array($site_id, $date)));
    $after_clicks = intval($DB->prepared_value('SELECT COALESCE(views,0) FROM web_site_stats WHERE site_id=? AND stat_date=?', array($site_id, $date)));
    $rows = qifu_site_stats_rows($date, 'views');
    $matched = array_values(array_filter($rows, static function($row) use ($site_id){ return intval($row['id']) === $site_id; }));
    check_site_stats($tracked === 1, 'duplicate or invalid site IDs were not filtered');
    check_site_stats($after_impressions === $before_impressions + 1, 'site impression did not increment exactly once');
    check_site_stats($after_clicks === $before_clicks, 'impression tracking changed click totals');
    check_site_stats($matched && intval($matched[0]['count']) === $after_impressions, 'view detail rows do not return impression counts');
    $DB->link->rollBack();
}

$api_source = file_get_contents(ROOT.'admin/api.php');
$ui_source = file_get_contents(ROOT.'admin-ui-source/src/views/qifu/admin-page.vue');
$home_source = file_get_contents(ROOT.'index.php');
check_site_stats(strpos($api_source, "\$action === 'site_stats'") !== false, 'site_stats API action is missing');
check_site_stats(strpos($ui_source, "trendMetric = ref<QifuSiteMetric>('views')") !== false, 'dashboard does not default to site view details');
check_site_stats(strpos($ui_source, '站点卡片实际进入访客视口统计') !== false, 'dashboard view metric explanation is missing');
check_site_stats(strpos($home_source, "type=impression&site_ids=") !== false, 'homepage impression batch tracking is missing');

if($failures){
    fwrite(STDERR, "Site stats tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Site stats tests passed.\n";
?>
