<?php
/* Art Design Pro Vue admin entry. Legacy PHP dashboard is kept in legacy-index.php for rollback/reference. */
require dirname(__DIR__) . '/includes/common.php';

// The compiled Art Design Pro bundle uses module chunks and runtime styles.
// Keep the application entry compatible with the existing PHP security headers
// while API endpoints retain the stricter policy.
if (function_exists('header_remove')) header_remove('Content-Security-Policy');

// The entry HTML points to hashed bundles and must never be cached itself.
// Otherwise a first visit after an upgrade can boot an old route table and old iframe adapter.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$shellPath = __DIR__ . '/ui/index.html';
if (!is_file($shellPath)) {
    http_response_code(503);
    echo '后台前端资源未构建，请先运行 admin-ui-source 的 pnpm build。';
    exit;
}

$shellHtml = file_get_contents($shellPath);
if ($shellHtml === false) {
    http_response_code(503);
    echo '后台前端资源读取失败，请检查文件权限。';
    exit;
}

$adminSiteName = isset($conf['sitename']) ? trim((string)$conf['sitename']) : '';
if ($adminSiteName === '') $adminSiteName = '祈福导航系统';
$adminTitle = htmlspecialchars($adminSiteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$shellHtml = preg_replace_callback(
    '#<title>.*?</title>#is',
    static function () use ($adminTitle) {
        return '<title>'.$adminTitle.'</title>';
    },
    $shellHtml,
    1
);

header('Content-Type: text/html; charset=UTF-8');
echo $shellHtml;
