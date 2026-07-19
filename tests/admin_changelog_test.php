<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$view = file_get_contents($root.'admin-ui-source/src/views/qifu/admin-page.vue');
$data = file_get_contents($root.'admin-ui-source/src/views/qifu/qifu-changelog.ts');
$client = file_get_contents($root.'admin-ui-source/src/api/qifu.ts');
$api = file_get_contents($root.'admin/api.php');
$history = file_get_contents($root.'includes/update_history.php');
$brand = file_get_contents($root.'includes/brand.php');
$telemetry = file_get_contents($root.'includes/telemetry.php');
$failures = array();

function check_admin_changelog($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_admin_changelog(strpos($view, 'class="changelog-scroll"') !== false, 'scrolling changelog list is missing');
check_admin_changelog(strpos($view, 'v-for="(release, index) in updateHistory"') !== false, 'database changelog entries are not rendered as a list');
check_admin_changelog(strpos($view, 'index === 0') !== false, 'latest changelog entry is not marked');
check_admin_changelog(strpos($view, ':loading="updateChecking"') !== false && strpos($view, '@click="checkForUpdates"') !== false, 'update check loading action is missing');
check_admin_changelog(strpos($view, 'updateState.updateAvailable') !== false && strpos($view, '@click="applyOnlineUpdate"') !== false, 'online update action is not activated for newer versions');
check_admin_changelog(strpos($view, 'class="update-version-compare"') !== false && strpos($view, 'class="update-compare-button"') !== false, 'selected version-comparison update design is missing');
check_admin_changelog(strpos($view, '{{ currentVersion }}') !== false && strpos($view, '{{ updateState.remoteVersion }}') !== false, 'version comparison does not use live versions');
check_admin_changelog(strpos($client, "action=update_apply") !== false, 'online update API client is missing');
check_admin_changelog(strpos($api, "if(\$action === 'update_apply')") !== false && strpos($api, 'qifu_online_update_apply') !== false, 'online update endpoint is missing');
check_admin_changelog(strpos($view, 'class="update-progress-panel"') !== false && strpos($view, 'updatePhases') !== false, 'online update progress UI is missing');
check_admin_changelog(strpos($view, "'发现新版本'") !== false, 'update-ready title is missing');
check_admin_changelog(strpos($view, '<ElTag v-if="updateState.updateAvailable" type="warning"') === false, 'duplicate remote-version tag is still rendered');
check_admin_changelog(strpos($client, "action=update_progress") !== false, 'online update progress client is missing');
check_admin_changelog(strpos($api, "if(\$action === 'update_progress')") !== false && strpos($api, 'session_write_close') !== false, 'online update progress endpoint or session unlock is missing');
check_admin_changelog(strpos($view, 'currentVersion:') !== false && strpos($view, 'updateState.currentVersion') !== false, 'installed version indicator is not server driven');
check_admin_changelog(strpos($view, "release.source === 'remote'") !== false, 'remote-synced changelog marker is missing');
check_admin_changelog(strpos($client, "action=update_status") !== false && strpos($client, "'update_check'") !== false, 'update status API client is missing');
check_admin_changelog(strpos($api, "if(\$action === 'update_status')") !== false && strpos($api, "if(\$action === 'update_check')") !== false, 'update status endpoints are missing');
check_admin_changelog(strpos($api, 'qifu_api_update_status(true)') !== false, 'manual update check does not force a live remote query');
check_admin_changelog(strpos($telemetry, 'function qifu_telemetry_remote(bool $force = false)') !== false, 'remote query does not expose cache bypass control');
check_admin_changelog(substr_count($telemetry, 'if(!$force && $cached') >= 2, 'forced remote query can still be intercepted by the five-minute cache');
check_admin_changelog(strpos($history, "web_update_history") !== false, 'local update history table is missing');
check_admin_changelog(strpos($history, "qifu_update_history_sync_remote") !== false, 'verified remote update archiving is missing');
check_admin_changelog(strpos($history, "WHERE version_key=?") !== false, 'version history is not deduplicated');
check_admin_changelog(strpos($history, "qifu_update_history_cleanup_retired_official") !== false && strpos($history, "array('1.6.0', '祈福导航 V1.6.0 正式版')") !== false, 'retired bundled V1.6.0 changelog cleanup is missing');
check_admin_changelog(strpos($brand, "QIFU_PRODUCT_VERSION', 'V1.5.0'") !== false, 'installed product version is not current');
check_admin_changelog(strpos($data, "version: 'V1.5.0'") !== false, 'bundled changelog is not aligned with the installed version');

if($failures){
    fwrite(STDERR, "Admin changelog tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin changelog tests passed.\n";
?>
