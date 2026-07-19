<?php
/* Art Design Pro 后台数据接口。所有写操作都必须经过登录会话和 CSRF 校验。 */
define('DH_JSON_RESPONSE', true);
include __DIR__ . '/../includes/common.php';
require_once SYSTEM_ROOT.'update_history.php';
require_once SYSTEM_ROOT.'online_update.php';
require_once SYSTEM_ROOT.'backup_service.php';
require_once SYSTEM_ROOT.'site_stats.php';

@header('Content-Type: application/json; charset=UTF-8');
@header('Cache-Control: no-store, no-cache, must-revalidate');

function qifu_api_exit($data = array(), $msg = '操作成功', $code = 200){
    http_response_code($code);
    echo json_encode(array('code'=>$code, 'msg'=>$msg, 'data'=>$data), JSON_UNESCAPED_UNICODE);
    exit;
}

function qifu_api_input(){
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string)$raw, true);
    if(is_array($decoded)) return $decoded;
    return array();
}

function qifu_api_require_login(){
    global $islogin;
    if(!isset($islogin) || intval($islogin) !== 1){
        qifu_api_exit(array(), '登录已失效，请重新登录', 401);
    }
}

function qifu_api_require_write(){
    qifu_api_require_login();
    qifu_require_csrf();
}

function qifu_api_user(){
    global $conf, $rooturl;
    $nickname = isset($conf['admin_nickname']) ? trim((string)$conf['admin_nickname']) : '';
    $avatar = isset($conf['admin_avatar']) ? qifu_media_normalize_url((string)$conf['admin_avatar'], $rooturl) : '';
    return array(
        'userId' => 1,
        'userName' => isset($conf['admin_user']) ? (string)$conf['admin_user'] : 'admin',
        'nickName' => $nickname !== '' ? $nickname : '管理员',
        'email' => isset($conf['mail_to']) ? (string)$conf['mail_to'] : '',
        'avatar' => $avatar,
        'homePath' => isset($conf['admin_default_page']) ? (string)$conf['admin_default_page'] : '/dashboard/console',
        'roles' => array('R_SUPER'),
        'buttons' => array('*')
    );
}

function qifu_api_profile(){
    global $DB, $conf;
    $logs = $DB->get_results('SELECT id,action,target,detail,ip,addtime FROM web_log ORDER BY id DESC LIMIT 10');
    return array(
        'user'=>qifu_api_user(),
        'roleLabel'=>'超级管理员',
        'lastLoginAt'=>isset($conf['admin_last_login_at']) ? intval($conf['admin_last_login_at']) : 0,
        'lastLoginIp'=>isset($conf['admin_last_login_ip']) ? (string)$conf['admin_last_login_ip'] : '',
        'passwordChangedAt'=>isset($conf['admin_password_changed_at']) ? intval($conf['admin_password_changed_at']) : 0,
        'sessionStartedAt'=>isset($_SESSION['qifu_admin_login_at']) ? intval($_SESSION['qifu_admin_login_at']) : 0,
        'sessionActive'=>true,
        'preferences'=>array(
            'defaultPage'=>isset($conf['admin_default_page']) ? (string)$conf['admin_default_page'] : '/dashboard/console',
            'tableDensity'=>isset($conf['admin_table_density']) ? (string)$conf['admin_table_density'] : 'default',
            'theme'=>isset($conf['admin_theme']) ? (string)$conf['admin_theme'] : 'auto',
            'language'=>isset($conf['admin_language']) ? (string)$conf['admin_language'] : 'zh'
        ),
        'recentLogs'=>$logs ?: array()
    );
}

function qifu_api_brand(){
    global $conf;
    $name = isset($conf['sitename']) ? trim((string)$conf['sitename']) : '';
    $logo = isset($conf['site_logo']) ? trim((string)$conf['site_logo']) : '';
    return array(
        'name' => $name !== '' ? $name : '祈福导航后台',
        'logo' => $logo,
        'title' => isset($conf['title']) ? trim((string)$conf['title']) : ''
    );
}

function qifu_api_system_info(){
    global $admin_directory;
    return array(
        'productName'=>defined('QIFU_PRODUCT_NAME') ? (string)QIFU_PRODUCT_NAME : '祈福导航系统',
        'currentVersion'=>defined('QIFU_PRODUCT_VERSION') ? (string)QIFU_PRODUCT_VERSION : 'V1',
        'phpVersion'=>PHP_VERSION,
        'database'=>defined('SQLITE') ? 'SQLite' : 'MySQL',
        'timezone'=>date_default_timezone_get(),
        'serverTime'=>time(),
        'adminDirectory'=>isset($admin_directory) ? basename((string)$admin_directory) : 'admin',
        'sodiumReady'=>function_exists('sodium_crypto_sign_verify_detached'),
        'zipReady'=>class_exists('ZipArchive'),
        'installLocked'=>is_file(ROOT.'install/install.lock')
    );
}

function qifu_api_last_id(){
    global $DB;
    $row = defined('SQLITE') ? $DB->get_row('SELECT last_insert_rowid() AS id') : $DB->get_row('SELECT LAST_INSERT_ID() AS id');
    return $row && isset($row['id']) ? intval($row['id']) : 0;
}

function qifu_api_settings(){
    global $conf;
    $settings = is_array($conf) ? $conf : array();
    $settings['mail_configured'] = isset($settings['mail_enabled']) && (string)$settings['mail_enabled'] === '1'
        && !empty($settings['mail_to']) && filter_var(trim((string)$settings['mail_to']), FILTER_VALIDATE_EMAIL)
        && !empty($settings['mail_user']) && filter_var(trim((string)$settings['mail_user']), FILTER_VALIDATE_EMAIL)
        && !empty($settings['mail_pass']) && !empty($settings['mail_host']) && intval(isset($settings['mail_port']) ? $settings['mail_port'] : 0) > 0 ? '1' : '0';
    unset($settings['admin_pwd'], $settings['admin_pwd_hash'], $settings['mail_pass'], $settings['cache']);
    return $settings;
}

function qifu_api_trend(){
    global $DB;
    qifu_site_stats_ensure_schema();
    $trend = array();
    for($i=13; $i>=0; $i--){
        $date = date('Y-m-d', strtotime('-'.$i.' day'));
        $row = $DB->prepared_row('SELECT views FROM web_stats WHERE stat_date=?', array($date));
        $site_row = $DB->prepared_row('SELECT COALESCE(SUM(views),0) AS clicks FROM web_site_stats WHERE stat_date=?', array($date));
        $trend[] = array(
            'date'=>$date,
            'views'=>$row ? intval($row['views']) : 0,
            'clicks'=>$site_row ? intval($site_row['clicks']) : 0
        );
    }
    return $trend;
}

function qifu_api_site_clicks($date){
    $rows = qifu_site_stats_rows($date, 'clicks');
    foreach($rows as &$row) $row['clicks'] = intval($row['count']);
    unset($row);
    return $rows;
}

function qifu_api_site_stats($date, $metric){
    $metric = $metric === 'clicks' ? 'clicks' : 'views';
    $rows = qifu_site_stats_rows($date, $metric);
    foreach($rows as &$row) $row['count'] = intval($row['count']);
    unset($row);
    return $rows;
}

function qifu_api_stats(){
    global $DB;
    qifu_site_stats_ensure_schema();
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $today_row = $DB->prepared_row('SELECT views FROM web_stats WHERE stat_date=?', array($today));
    $yesterday_row = $DB->prepared_row('SELECT views FROM web_stats WHERE stat_date=?', array($yesterday));
    $stats = array(
        'todayViews' => $today_row ? intval($today_row['views']) : 0,
        'yesterdayViews' => $yesterday_row ? intval($yesterday_row['views']) : 0,
        'totalViews' => intval($DB->count('SELECT COALESCE(SUM(views),0) FROM web_stats')),
        'totalSites' => intval($DB->count('SELECT count(*) FROM web_dh')),
        'activeSites' => intval($DB->count('SELECT count(*) FROM web_dh WHERE active=1')),
        'hiddenSites' => intval($DB->count('SELECT count(*) FROM web_dh WHERE active=0')),
        'totalCategories' => intval($DB->count('SELECT count(*) FROM web_category')),
        'todayClicks' => intval($DB->prepared_value('SELECT COALESCE(SUM(views),0) FROM web_site_stats WHERE stat_date=?', array($today))),
        'totalClicks' => intval($DB->count('SELECT COALESCE(SUM(views),0) FROM web_site_stats'))
    );
    $stats['trend'] = qifu_api_trend();
    return $stats;
}

function qifu_api_notification_item($id, $type, $category, $title, $description, $time, $route){
    return array(
        'id' => (string)$id,
        'type' => (string)$type,
        'category' => (string)$category,
        'title' => (string)$title,
        'description' => (string)$description,
        'time' => intval($time),
        'route' => (string)$route
    );
}

function qifu_api_remote_text($value, $limit = 240){
    if(is_array($value) || is_object($value)) return '';
    $text = trim(strip_tags(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8')));
    if($text === '') return '';
    if(function_exists('mb_substr')) return mb_substr($text, 0, intval($limit), 'UTF-8');
    return substr($text, 0, intval($limit));
}

function qifu_api_remote_time($value, $fallback){
    if(is_numeric($value)){
        $time = intval($value);
        if($time > 20000000000) $time = intval($time / 1000);
        return $time > 0 ? $time : intval($fallback);
    }
    $parsed = $value !== null && $value !== '' ? strtotime((string)$value) : false;
    return $parsed !== false && $parsed > 0 ? intval($parsed) : intval($fallback);
}

function qifu_api_remote_type($value){
    $severity = strtolower(trim((string)$value));
    if(in_array($severity, array('danger','error','critical','urgent','high'), true)) return 'danger';
    if(in_array($severity, array('warning','warn','medium'), true)) return 'warning';
    return 'info';
}

function qifu_api_notifications(){
    global $DB;
    $now = time();
    $items = array();

    $site_columns = $DB->get_row("SHOW COLUMNS FROM web_dh LIKE 'ping_status'");
    if(!empty($site_columns)){
        $problem_sites = $DB->prepared_results(
            'SELECT id,name,ping_checked_at FROM web_dh WHERE active=1 AND ping_checked_at>0 AND ping_status=0 ORDER BY ping_checked_at DESC LIMIT 100',
            array()
        );
        if(!empty($problem_sites)){
            $names = array();
            foreach($problem_sites as $site){
                if(count($names) < 3) $names[] = (string)$site['name'];
            }
            $offline = count($problem_sites);
            $items[] = qifu_api_notification_item(
                'site-offline-'.intval($problem_sites[0]['ping_checked_at']).'-'.$offline,
                'danger', 'site', '发现 '.$offline.' 个失效站点',
                implode('、', $names).($offline > 3 ? ' 等' : '').' 无法正常访问，请及时检查。',
                intval($problem_sites[0]['ping_checked_at']), '/content/sites'
            );
        }
    }

    // Remote content has already passed the SDK's signature and active-window
    // checks. Malformed fields are ignored or bounded before entering the UI.
    $remote = qifu_telemetry_remote();
    if(is_array($remote)){
        qifu_update_history_sync_remote($remote);
        $announcements = isset($remote['announcements']) && is_array($remote['announcements']) ? $remote['announcements'] : array();
        foreach(array_slice($announcements, 0, 50) as $announcement){
            if(!is_array($announcement)) continue;
            $title = qifu_api_remote_text(isset($announcement['title']) ? $announcement['title'] : '', 120);
            if($title === '') $title = '远程公告';
            $description = '';
            foreach(array('body','description','message','content','text') as $field){
                if(isset($announcement[$field])){
                    $description = qifu_api_remote_text($announcement[$field], 300);
                    if($description !== '') break;
                }
            }
            if($description === '') $description = '远程服务发布了一条公告，请查看详情。';
            $time = qifu_api_remote_time(
                isset($announcement['starts_at']) ? $announcement['starts_at'] : (isset($announcement['published_at']) ? $announcement['published_at'] : null),
                $now
            );
            $hash = substr(sha1($title.'|'.$description.'|'.$time), 0, 16);
            $items[] = qifu_api_notification_item(
                'remote-announcement-'.$hash,
                qifu_api_remote_type(isset($announcement['severity']) ? $announcement['severity'] : (isset($announcement['type']) ? $announcement['type'] : 'info')),
                'announcement', $title, $description, $time, '/dashboard/console'
            );
        }

        $remote_update = isset($remote['update']) && is_array($remote['update']) ? $remote['update'] : null;
        if($remote_update){
            $remote_version = qifu_api_remote_text(isset($remote_update['version']) ? $remote_update['version'] : '', 40);
            $current_version = defined('QIFU_PRODUCT_VERSION') ? ltrim((string)QIFU_PRODUCT_VERSION, 'vV') : '';
            $is_newer = $remote_version !== '' && ($current_version === '' || version_compare(ltrim($remote_version, 'vV'), $current_version, '>'));
            if($is_newer){
                $update_title = qifu_api_remote_text(isset($remote_update['title']) ? $remote_update['title'] : '', 120);
                if($update_title === '') $update_title = '发现远程更新 '.$remote_version;
                $update_description = qifu_api_remote_text(isset($remote_update['description']) ? $remote_update['description'] : (isset($remote_update['body']) ? $remote_update['body'] : ''), 300);
                if($update_description === '') $update_description = '远程服务提供了新的版本信息，请进入关于系统查看详情。';
                $items[] = qifu_api_notification_item(
                    'remote-update-'.preg_replace('/[^A-Za-z0-9._-]/', '', $remote_version),
                    'info', 'update', $update_title, $update_description,
                    qifu_api_remote_time(isset($remote_update['published_at']) ? $remote_update['published_at'] : null, $now), '/about/index'
                );
            }
        }
    }

    $priority = array('danger'=>0, 'warning'=>1, 'info'=>2);
    usort($items, function($left, $right) use ($priority){
        $left_priority = isset($priority[$left['type']]) ? $priority[$left['type']] : 9;
        $right_priority = isset($priority[$right['type']]) ? $priority[$right['type']] : 9;
        if($left_priority !== $right_priority) return $left_priority - $right_priority;
        return intval($right['time']) - intval($left['time']);
    });

    return array('items'=>$items, 'generatedAt'=>$now);
}

function qifu_api_bootstrap(){
    global $DB;
    $categories = $DB->get_results('SELECT id,name,icon,sort,active FROM web_category ORDER BY sort ASC,id ASC');
    $sites = $DB->get_results('SELECT id,name,url,description,desc_marquee,desc_speed,desc_color,icon,category,sort,active FROM web_dh ORDER BY sort ASC,id DESC LIMIT 500');
    $links = array();
    $link_table = $DB->get_row("SHOW TABLES LIKE 'web_links'");
    if(!empty($link_table)) $links = $DB->get_results('SELECT * FROM web_links ORDER BY id DESC LIMIT 500');
    $logs = $DB->get_results('SELECT id,action,target,target_id,detail,ip,addtime FROM web_log ORDER BY id DESC LIMIT 100');
    $backups = array();
    $backup_table = $DB->get_row("SHOW TABLES LIKE 'web_backup'");
    if(!empty($backup_table)) $backups = $DB->get_results('SELECT id,filename,size,addtime FROM web_backup ORDER BY id DESC');
    $ad_keys = array('ad_enabled','ad_position','ad_show_below','ad_show_right','ad_show_left','ad_image','ad_link','ad_title','ad_alt','ad_image2','ad_link2','ad_title2','ad_alt2','ad_image3','ad_link3','ad_title3','ad_alt3','ad_image4','ad_link4','ad_title4','ad_alt4','ad_right_image','ad_right_link','ad_left_image','ad_left_link');
    $settings = qifu_api_settings();
    $ads = array();
    foreach($ad_keys as $key) $ads[$key] = isset($settings[$key]) ? $settings[$key] : '';
    return array('user'=>qifu_api_user(),'settings'=>$settings,'ads'=>$ads,'stats'=>qifu_api_stats(),'categories'=>$categories ?: array(),'sites'=>$sites ?: array(),'links'=>$links ?: array(),'logs'=>$logs ?: array(),'backups'=>$backups ?: array(),'csrf'=>qifu_csrf_token());
}

function qifu_api_update_status($force = false){
    $remote = qifu_telemetry_remote((bool)$force);
    if(is_array($remote)) qifu_update_history_sync_remote($remote);
    return qifu_update_status($remote);
}

function qifu_api_track_admin_usage(){
    $event = isset($_POST['event']) ? trim((string)$_POST['event']) : '';
    $allowed_events = array(
        'admin_dashboard_open',
        'admin_menu_workbench',
        'admin_menu_system_config',
        'admin_menu_content_operations',
        'admin_menu_maintenance_tools',
        'admin_menu_about_system'
    );
    if(!in_array($event, $allowed_events, true)) qifu_api_exit(array(), '功能调用标识无效', 400);

    // Usage reports include only the admin surface and a fixed event name.
    qifu_telemetry_track($event, true, array('surface'=>'admin'));
    return array('event'=>$event);
}

$action = isset($_GET['action']) ? (string)$_GET['action'] : '';
$input = qifu_api_input();
if($input) $_POST = array_merge($_POST, $input);

if($action === 'csrf') qifu_api_exit(array('csrf'=>qifu_csrf_token()));
if($action === 'brand') qifu_api_exit(qifu_api_brand());

if($action === 'login'){
    if($_SERVER['REQUEST_METHOD'] !== 'POST') qifu_api_exit(array(), '请求方式错误', 405);
    qifu_require_csrf();
    $username = trim(isset($_POST['userName']) ? (string)$_POST['userName'] : (string)@$_POST['user']);
    $password = isset($_POST['password']) ? (string)$_POST['password'] : (string)@$_POST['pass'];
    $wait = qifu_login_rate_wait($username);
    $format_ok = qifu_username_valid($username) && qifu_password_valid($password);
    $user_ok = $format_ok && isset($conf['admin_user']) && hash_equals((string)$conf['admin_user'], $username);
    if($wait > 0 || !$user_ok || !qifu_admin_password_verify($password)){
        if($wait <= 0) qifu_login_rate_fail($username);
        qifu_api_exit(array(), $wait > 0 ? '尝试次数过多，请稍后再试' : '账号或密码错误', 401);
    }
    qifu_login_rate_clear($username);
    qifu_admin_password_migrate($password);
    qifu_admin_login_session($username);
    saveSetting('admin_last_login_at', (string)time());
    saveSetting('admin_last_login_ip', real_ip());
    writeLog('登录','后台',0,'管理员登录后台');
    $CACHE->clear();
    $conf = $CACHE->update();
    qifu_api_exit(array('token'=>'session','refreshToken'=>'','user'=>qifu_api_user()), '登录成功');
}

if($action === 'logout'){
    qifu_api_require_write();
    qifu_admin_logout_session();
    qifu_api_exit(array(), '已退出登录');
}

qifu_api_require_login();

if($action === 'user_info') qifu_api_exit(qifu_api_user());
if($action === 'profile') qifu_api_exit(qifu_api_profile());
if($action === 'bootstrap') qifu_api_exit(qifu_api_bootstrap());
if($action === 'trend') qifu_api_exit(qifu_api_trend());
if($action === 'site_clicks'){
    $date = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
    qifu_api_exit(qifu_api_site_clicks($date));
}
if($action === 'site_stats'){
    $date = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
    $metric = isset($_GET['metric']) ? trim((string)$_GET['metric']) : 'views';
    qifu_api_exit(qifu_api_site_stats($date, $metric));
}
if($action === 'notifications') qifu_api_exit(qifu_api_notifications());
if($action === 'system_info') qifu_api_exit(qifu_api_system_info());
if($action === 'update_status') qifu_api_exit(qifu_api_update_status());
if($action === 'admin_usage_track'){
    if($_SERVER['REQUEST_METHOD'] !== 'POST') qifu_api_exit(array(), '请求方式错误', 405);
    qifu_api_require_write();
    qifu_api_exit(qifu_api_track_admin_usage(), '功能调用已上报');
}
if($action === 'update_progress'){
    $request_id = isset($_GET['id']) ? qifu_online_update_progress_id($_GET['id']) : '';
    if($request_id === '') qifu_api_exit(array(), '更新任务编号无效', 400);
    $progress = qifu_online_update_progress_read(ROOT, $request_id);
    if(!is_array($progress)){
        $progress = array('requestId'=>$request_id, 'phase'=>'verify', 'percentage'=>2, 'message'=>'正在准备远程验签', 'status'=>'running', 'updatedAt'=>time());
    }
    qifu_api_exit($progress);
}

if($action === 'update_check'){
    qifu_api_require_write();
    $status = qifu_api_update_status(true);
    if(!empty($status['updateAvailable'])){
        qifu_api_exit($status, '发现新版本 '.$status['remoteVersion']);
    }
    if(empty($status['serviceAvailable'])){
        qifu_api_exit($status, '远程更新服务暂时不可用，本地更新日志仍可查看');
    }
    qifu_api_exit($status, '当前已是最新版本 '.$status['currentVersion']);
}

if($action === 'update_apply'){
    qifu_api_require_write();
    $request_id = isset($_POST['operationId']) ? qifu_online_update_progress_id($_POST['operationId']) : '';
    if($request_id === '') qifu_api_exit(array(), '更新任务编号无效', 400);
    @set_time_limit(300);
    @ignore_user_abort(true);
    qifu_online_update_progress_write(ROOT, $request_id, 'verify', 8, '正在获取并验证远程发布信息');
    if(session_status() === PHP_SESSION_ACTIVE) @session_write_close();
    try {
        $remote = qifu_telemetry_remote(true);
        if(!is_array($remote)) throw new RuntimeException('远程更新服务暂时不可用，请稍后重试。');
        qifu_online_update_progress_write(ROOT, $request_id, 'verify', 20, '远程发布信息验签通过');
        if(is_array($remote)) qifu_update_history_sync_remote($remote);
        $progress_callback = static function($phase, $percentage, $message, $status) use ($request_id){
            qifu_online_update_progress_write(ROOT, $request_id, $phase, $percentage, $message, $status);
        };
        $result = qifu_online_update_apply($remote, ROOT, $progress_callback);
        qifu_api_exit($result, '程序已更新到 '.$result['version'].'，数据库与安装状态保持不变');
    } catch(Throwable $error){
        $current_progress = qifu_online_update_progress_read(ROOT, $request_id);
        $percentage = is_array($current_progress) ? intval($current_progress['percentage']) : 0;
        qifu_online_update_progress_write(ROOT, $request_id, 'failed', $percentage, $error->getMessage(), 'failed');
        qifu_api_exit(array(), $error->getMessage(), 500);
    }
}

if($action === 'logo_upload'){
    qifu_api_require_write();
    if(!isset($_FILES['file'])) qifu_api_exit(array(), '请选择需要上传的 LOGO 图片', 400);
    $upload_error = '';
    $filename = qifu_safe_image_upload($_FILES['file'], ROOT.'images/logo/', 'logo', $upload_error);
    if($filename === false) qifu_api_exit(array(), $upload_error !== '' ? $upload_error : 'LOGO 上传失败', 400);
    $url = qifu_media_upload_url('images/logo/'.$filename, $rooturl);
    qifu_api_exit(array('url'=>$url, 'filename'=>$filename), 'LOGO 上传成功');
}

if($action === 'profile_save'){
    qifu_api_require_write();
    $nickname = mb_substr(trim((string)@$_POST['nickname']), 0, 40);
    $email = trim((string)@$_POST['notificationEmail']);
    $default_page = (string)@$_POST['defaultPage'];
    $density = (string)@$_POST['tableDensity'];
    $theme = (string)@$_POST['theme'];
    $language = (string)@$_POST['language'];
    $allowed_pages = array('/dashboard/console','/content/sites','/maintenance/logs');
    $allowed_density = array('default','compact','comfortable');
    $allowed_themes = array('light','dark','auto');
    $allowed_languages = array('zh','en');
    if($nickname === '') qifu_api_exit(array(), '请填写管理员昵称', 400);
    if($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) qifu_api_exit(array(), '通知邮箱格式不正确', 400);
    if(!in_array($default_page, $allowed_pages, true)) qifu_api_exit(array(), '默认进入页面无效', 400);
    if(!in_array($density, $allowed_density, true)) qifu_api_exit(array(), '表格密度设置无效', 400);
    if(!in_array($theme, $allowed_themes, true)) qifu_api_exit(array(), '主题设置无效', 400);
    if(!in_array($language, $allowed_languages, true)) qifu_api_exit(array(), '界面语言设置无效', 400);
    saveSetting('admin_nickname', $nickname);
    saveSetting('mail_to', $email);
    saveSetting('admin_default_page', $default_page);
    saveSetting('admin_table_density', $density);
    saveSetting('admin_theme', $theme);
    saveSetting('admin_language', $language);
    $CACHE->clear();
    $conf = $CACHE->update();
    writeLog('修改','个人资料',0,'管理员更新个人资料与偏好');
    qifu_api_exit(qifu_api_profile(), '个人资料已保存');
}

if($action === 'profile_avatar_upload'){
    qifu_api_require_write();
    if(!isset($_FILES['file'])) qifu_api_exit(array(), '请选择需要上传的头像图片', 400);
    $upload_error = '';
    $filename = qifu_safe_image_upload($_FILES['file'], ROOT.'images/avatar/', 'admin', $upload_error, 2097152);
    if($filename === false) qifu_api_exit(array(), $upload_error !== '' ? $upload_error : '头像上传失败', 400);
    $url = qifu_media_upload_url('images/avatar/'.$filename, $rooturl);
    saveSetting('admin_avatar', $url);
    $CACHE->clear();
    $conf = $CACHE->update();
    writeLog('修改','个人资料',0,'管理员更新头像');
    qifu_api_exit(qifu_api_profile(), '头像已更新');
}

if($action === 'site_meta'){
    if($_SERVER['REQUEST_METHOD'] !== 'POST') qifu_api_exit(array(), '请求方式错误', 405);
    qifu_api_require_write();
    require_once SYSTEM_ROOT.'site_meta.php';

    $now = time();
    $requests = isset($_SESSION['qifu_admin_site_meta_requests']) && is_array($_SESSION['qifu_admin_site_meta_requests']) ? $_SESSION['qifu_admin_site_meta_requests'] : array();
    $requests = array_values(array_filter($requests, function($timestamp) use ($now){ return intval($timestamp) >= $now - 60; }));
    if(count($requests) >= 12) qifu_api_exit(array(), '自动获取过于频繁，请稍后再试', 429);
    $requests[] = $now;
    $_SESSION['qifu_admin_site_meta_requests'] = $requests;

    $url = isset($_POST['url']) ? trim((string)$_POST['url']) : '';
    $error = '';
    $meta = qifu_site_meta_fetch($url, $error);
    if($meta === false) qifu_api_exit(array(), $error !== '' ? $error : '无法自动获取网站信息', 400);
    qifu_api_exit($meta, '网站信息获取成功');
}

if($action === 'save_settings'){
    qifu_api_require_write();
    $allowed = array('sitename','title','keywords','description','kfqq','announcement','intro','mobile_qq','admin_user','site_logo','site_favicon','site_beian','gongan_beian','gongan_beian_url','site公安备案','公安网安备','show_link_apply','link_mail_notify','search_engine','site_search_enabled','online_stats_enabled','online_stats_mode','online_stats_random_scheme','online_stats_random_active_min','online_stats_random_active_max','online_stats_random_today_min','online_stats_random_today_max','online_stats_random_trend','online_stats_random_start_date','online_stats_random_base_visits','online_stats_random_seed_date','online_stats_random_stable','online_stats_privacy_ip','online_stats_today_active','online_stats_today_update','online_stats_total_visits','online_stats_today_visits','online_stats_ip','qqjump','qq_jump','footer','footer_text','footer_link','footer_link_text','icp','show_search','show_tags','bg_custom');
    $settings = isset($_POST['settings']) && is_array($_POST['settings']) ? $_POST['settings'] : array();
    foreach($settings as $key=>$value){
        $key = (string)$key;
        if(!in_array($key, $allowed, true)) continue;
        $value = is_scalar($value) ? (string)$value : '';
        if($key === 'online_stats_enabled' || $key === 'online_stats_random_stable' || $key === 'online_stats_privacy_ip') $value = $value === '1' ? '1' : '0';
        if($key === 'online_stats_mode') $value = $value === 'random' ? 'random' : 'real';
        if($key === 'online_stats_random_scheme') $value = $value === 'rule' ? 'rule' : 'builtin';
        if($key === 'online_stats_random_trend' && !in_array($value, array('steady','rise','fall'), true)) $value = 'steady';
        if(in_array($key, array('online_stats_random_active_min','online_stats_random_active_max','online_stats_random_today_min','online_stats_random_today_max'), true)) $value = (string)max(0, min(1000000, intval($value)));
        if($key === 'online_stats_random_base_visits') $value = (string)max(0, min(1000000000, intval($value)));
        if($key === 'online_stats_random_start_date' || $key === 'online_stats_random_seed_date'){
            $check = DateTime::createFromFormat('!Y-m-d', $value);
            if(!$check || $check->format('Y-m-d') !== $value || $value > date('Y-m-d')) $value = date('Y-m-d');
        }
        saveSetting($key, $value);
    }
    global $CACHE;
    $CACHE->clear();
    $CACHE->update();
    writeLog('修改','设置',0,'保存 Art Design Pro 后台设置');
    qifu_api_exit(array('settings'=>qifu_api_settings()), '设置已保存');
}

if($action === 'site_save'){
    qifu_api_require_write();
    $id = intval(@$_POST['id']);
    $name = mb_substr(trim((string)@$_POST['name']),0,100);
    $url = mb_substr(trim((string)@$_POST['url']),0,255);
    $description = mb_substr(trim((string)@$_POST['description']),0,255);
    $desc_marquee = intval(@$_POST['desc_marquee']) === 1 ? 1 : 0;
    $desc_speed = isset($_POST['desc_speed']) && in_array((string)$_POST['desc_speed'], array('slow','normal','fast','rapid'), true) ? (string)$_POST['desc_speed'] : 'normal';
    $desc_color = isset($_POST['desc_color']) && in_array((string)$_POST['desc_color'], array('default','red','orange','yellow','green','cyan','blue','purple','rainbow'), true) ? (string)$_POST['desc_color'] : 'default';
    $icon = mb_substr(trim((string)@$_POST['icon']),0,255);
    $category = mb_substr(trim((string)@$_POST['category']),0,50);
    $sort = intval(@$_POST['sort']);
    $active = intval(@$_POST['active']) === 1 ? 1 : 0;
    if($name === '' || !filter_var($url,FILTER_VALIDATE_URL) || !preg_match('#^https?://#i',$url)) qifu_api_exit(array(),'名称或 URL 格式不正确',400);
    if($id > 0) $DB->prepared_query('UPDATE web_dh SET name=?,url=?,description=?,desc_marquee=?,desc_speed=?,desc_color=?,icon=?,category=?,sort=?,active=? WHERE id=?',array($name,$url,$description,$desc_marquee,$desc_speed,$desc_color,$icon,$category,$sort,$active,$id));
    else { $DB->prepared_query('INSERT INTO web_dh (name,url,description,desc_marquee,desc_speed,desc_color,icon,category,sort,active) VALUES (?,?,?,?,?,?,?,?,?,?)',array($name,$url,$description,$desc_marquee,$desc_speed,$desc_color,$icon,$category,$sort,$active)); $id = qifu_api_last_id(); }
    $CACHE->clear();
    writeLog($id > 0 ? '修改' : '添加','站点',$id,'Art Design Pro 后台保存站点:'.$name);
    qifu_api_exit(array('id'=>$id), '站点已保存');
}

if($action === 'site_delete'){
    qifu_api_require_write();
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : array(intval(@$_POST['id']));
    foreach($ids as $id){ $id=intval($id); if($id>0) $DB->prepared_query('DELETE FROM web_dh WHERE id=?',array($id)); }
    $CACHE->clear(); writeLog('删除','站点',0,'Art Design Pro 后台删除站点'); qifu_api_exit(array(),'站点已删除');
}

if($action === 'category_save'){
    qifu_api_require_write();
    $id=intval(@$_POST['id']); $name=mb_substr(trim((string)@$_POST['name']),0,50); $icon=mb_substr(trim((string)@$_POST['icon']),0,20); $sort=intval(@$_POST['sort']); $active=intval(@$_POST['active'])===1?1:0;
    if($name==='') qifu_api_exit(array(),'分类名称不能为空',400);
    if($id>0) {
        $DB->prepared_query('UPDATE web_category SET name=?,icon=?,sort=?,active=? WHERE id=?',array($name,$icon,$sort,$active,$id));
    } else {
        // SQLite requires the legacy addtime column even though MySQL installs may default it.
        $DB->prepared_query('INSERT INTO web_category (name,icon,sort,active,addtime) VALUES (?,?,?,?,?)',array($name,$icon,$sort,$active,time()));
        $id=qifu_api_last_id();
    }
    $CACHE->clear(); writeLog($id>0?'修改':'添加','分类',$id,'Art Design Pro 后台保存分类:'.$name); qifu_api_exit(array('id'=>$id),'分类已保存');
}

if($action === 'category_delete'){
    qifu_api_require_write(); $id=intval(@$_POST['id']); if($id<=0) qifu_api_exit(array(),'分类编号无效',400); $DB->prepared_query('DELETE FROM web_category WHERE id=?',array($id)); $CACHE->clear(); writeLog('删除','分类',$id,'Art Design Pro 后台删除分类'); qifu_api_exit(array(),'分类已删除');
}

if($action === 'link_toggle'){
    qifu_api_require_write(); $value=intval(@$_POST['enabled'])===1?'1':'0'; saveSetting('show_link_apply',$value); $CACHE->clear(); writeLog('修改','友链',0,$value==='1'?'开启前台友链申请入口':'关闭前台友链申请入口'); qifu_api_exit(array('enabled'=>$value==='1'),'友链申请入口已更新');
}

if($action === 'link_mail_toggle'){
    qifu_api_require_write();
    $value = intval(@$_POST['enabled']) === 1 ? '1' : '0';
    if($value === '1'){
        require_once ROOT.'includes/link_notification.php';
        $configuration_error = '';
        if(!qifu_link_notification_configuration_ready($conf, $configuration_error)) qifu_api_exit(array(), $configuration_error.'，请前往邮件通知完成设置', 400);
    }
    saveSetting('link_mail_notify', $value);
    $CACHE->clear();
    $conf = $CACHE->update();
    writeLog('修改','友链',0,$value==='1'?'开启友链申请邮件提醒':'关闭友链申请邮件提醒');
    qifu_api_exit(array('enabled'=>$value==='1','recipient'=>isset($conf['mail_to']) ? (string)$conf['mail_to'] : ''),'友链邮件提醒已更新');
}

if($action === 'link_audit'){
    qifu_api_require_write(); $id=intval(@$_POST['id']); $status=intval(@$_POST['status'])===1?1:2; $link=$DB->prepared_row('SELECT * FROM web_links WHERE id=?',array($id)); if(!$link) qifu_api_exit(array(),'友链记录不存在',404); $DB->prepared_query('UPDATE web_links SET status=? WHERE id=?',array($status,$id)); if($status===1) $DB->prepared_query('INSERT INTO web_dh (name,url,description,icon,category,active) VALUES (?,?,?,?,?,1)',array($link['name'],$link['url'],$link['description'],$link['icon'],$link['category'])); writeLog($status===1?'通过':'拒绝','友链',$id,'Art Design Pro 后台审核友链'); $CACHE->clear(); qifu_api_exit(array(),'审核结果已保存');
}

if($action === 'link_delete'){
    qifu_api_require_write(); $id=intval(@$_POST['id']); $DB->prepared_query('DELETE FROM web_links WHERE id=?',array($id)); writeLog('删除','友链',$id,'Art Design Pro 后台删除友链'); qifu_api_exit(array(),'友链已删除');
}

if($action === 'link_save'){
    qifu_api_require_write(); $id=intval(@$_POST['id']); $name=mb_substr(trim((string)@$_POST['name']),0,100); $url=mb_substr(trim((string)@$_POST['url']),0,255); $description=mb_substr(trim((string)@$_POST['description']),0,255); $icon=mb_substr(trim((string)@$_POST['icon']),0,255); if($id<=0 || $name==='' || !filter_var($url,FILTER_VALIDATE_URL)) qifu_api_exit(array(),'友链信息不完整',400); $DB->prepared_query('UPDATE web_links SET name=?,url=?,description=?,icon=? WHERE id=?',array($name,$url,$description,$icon,$id)); writeLog('修改','友链',$id,'Art Design Pro 后台修改友链'); qifu_api_exit(array(),'友链已保存');
}

if($action === 'ad_save'){
    qifu_api_require_write(); $allowed=array('ad_enabled','ad_position','ad_show_below','ad_show_right','ad_show_left','ad_image','ad_link','ad_title','ad_alt','ad_image2','ad_link2','ad_title2','ad_alt2','ad_image3','ad_link3','ad_title3','ad_alt3','ad_image4','ad_link4','ad_title4','ad_alt4','ad_right_image','ad_right_link','ad_left_image','ad_left_link'); $settings=isset($_POST['settings'])&&is_array($_POST['settings'])?$_POST['settings']:array(); foreach($settings as $key=>$value){if(in_array((string)$key,$allowed,true)) saveSetting($key,is_scalar($value)?(string)$value:'');} $CACHE->clear(); writeLog('修改','广告',0,'Art Design Pro 后台保存广告配置'); qifu_api_exit(array(),'广告设置已保存');
}

if($action === 'ad_upload'){
    qifu_api_require_write();
    if(!isset($_FILES['file'])) qifu_api_exit(array(),'没有收到上传文件',400);
    $file = $_FILES['file'];
    if(intval($file['error']) !== UPLOAD_ERR_OK) qifu_api_exit(array(),'图片上传失败',400);
    $slot = preg_replace('/[^a-z0-9_]+/i','_',isset($_POST['slot']) ? (string)$_POST['slot'] : 'ad');
    $positions = qifu_ad_positions();
    $position = isset($_POST['position']) && isset($positions[$_POST['position']]) ? $_POST['position'] : 'below_search';
    $upload_error = '';
    $upload_info = array();
    $filename = qifu_ad_upload_image($file, ROOT.'images/ad/', $slot.'_'.$position, $position, $upload_error, $upload_info);
    if($filename === false) qifu_api_exit(array(),$upload_error !== '' ? $upload_error : '图片上传失败',400);
    qifu_api_exit(array('url'=>qifu_media_upload_url('images/ad/'.$filename,$rooturl),'filename'=>$filename,'width'=>isset($upload_info['width']) ? intval($upload_info['width']) : 0,'height'=>isset($upload_info['height']) ? intval($upload_info['height']) : 0),'图片上传成功');
}

if($action === 'logs_clear'){
    qifu_api_require_write(); $DB->query(defined('SQLITE')?'DELETE FROM web_log':'TRUNCATE TABLE web_log'); writeLog('清理','日志',0,'Art Design Pro 后台清理操作日志'); qifu_api_exit(array(),'日志已清理');
}

if($action === 'backup_create'){
    qifu_api_require_write();
    try {
        $backup = qifu_backup_create_file($DB, 'manual');
        if(!qifu_backup_register_file($DB, $backup)) throw new RuntimeException('备份记录写入失败。');
        writeLog('备份','数据库',0,'Art Design Pro 后台生成完整备份:'.$backup['filename']);
        qifu_api_exit(array(
            'filename'=>$backup['filename'],
            'size'=>$backup['size'],
            'tableCount'=>$backup['tableCount'],
            'rowCount'=>$backup['rowCount']
        ),'完整备份已创建');
    } catch(Throwable $error) {
        qifu_api_exit(array(),$error->getMessage(),500);
    }
}

if($action === 'backup_restore'){
    qifu_api_require_write();
    if(!qifu_admin_password_verify(isset($_POST['password']) ? (string)$_POST['password'] : '')){
        qifu_api_exit(array(),'当前管理员密码错误',400);
    }
    if(!isset($_FILES['file']) || !is_array($_FILES['file'])) qifu_api_exit(array(),'没有收到备份文件',400);
    $file = $_FILES['file'];
    if(intval($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) qifu_api_exit(array(),'备份文件上传失败',400);
    $original = basename((string)($file['name'] ?? ''));
    if(!preg_match('/\.qifubak$/i', $original)) qifu_api_exit(array(),'请选择本系统导出的 .qifubak 备份文件',400);
    if(intval($file['size'] ?? 0) <= 0 || intval($file['size']) > 32 * 1024 * 1024) qifu_api_exit(array(),'备份文件大小必须在 32MB 以内',400);
    if(!isset($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) qifu_api_exit(array(),'备份上传来源无效',400);
    try {
        $result = qifu_backup_restore_file($DB, (string)$file['tmp_name']);
        $CACHE->clear();
        writeLog('恢复','数据库',0,'完整恢复 '.$result['tableCount'].' 张表、'.$result['rowCount'].' 条记录');
        qifu_api_exit($result,'数据恢复完成');
    } catch(Throwable $error) {
        $message = $error->getMessage();
        $status = (strpos($message, '自动回滚未完成') !== false || strpos($message, '数据已恢复') !== false) ? 500 : 400;
        qifu_api_exit(array(),$message,$status);
    }
}

if($action === 'backup_delete'){
    qifu_api_require_write(); $id=intval(@$_POST['id']); $row=$DB->prepared_row('SELECT * FROM web_backup WHERE id=?',array($id)); if($row){$file=ROOT.'backup/'.basename((string)$row['filename']);if(is_file($file))@unlink($file);$DB->prepared_query('DELETE FROM web_backup WHERE id=?',array($id));} writeLog('删除','数据库',$id,'Art Design Pro 后台删除备份'); qifu_api_exit(array(),'备份已删除');
}

if($action === 'password_change'){
    qifu_api_require_write(); $old=(string)@$_POST['oldpwd']; $username=trim((string)@$_POST['username']); $new1=(string)@$_POST['newpwd']; $new2=(string)@$_POST['newpwd2']; if(!$old||!$username||!$new1||!$new2) qifu_api_exit(array(),'请完整填写账号和密码',400); if(!qifu_username_valid($username)||!qifu_password_valid($new1)) qifu_api_exit(array(),'账号或密码格式不符合安全策略',400); if($new1!==$new2) qifu_api_exit(array(),'两次输入的新密码不一致',400); if(!qifu_admin_password_verify($old)) qifu_api_exit(array(),'原密码错误',400); saveSetting('admin_user',$username); saveSetting('admin_pwd_hash',password_hash($new1,PASSWORD_DEFAULT)); saveSetting('admin_pwd',''); saveSetting('admin_password_changed_at',(string)time()); saveSetting('admin_auth_version',qifu_auth_version()+1); $CACHE->clear(); writeLog('修改','账号安全',0,'Art Design Pro 后台更新登录凭据'); qifu_admin_logout_session(); qifu_api_exit(array('loggedOut'=>true),'账号安全设置已更新');
}

qifu_api_exit(array(),'未知接口操作',404);
?>
