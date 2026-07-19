<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$failures = array();

function check_link_apply($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

$index_source = file_get_contents($root.'index.php');
$endpoint = $root.'ajax_site_meta.php';
$helper = $root.'includes/site_meta.php';
$link_endpoint = file_get_contents($root.'ajax_link.php');
$notification_helper = $root.'includes/link_notification.php';
$admin_api = file_get_contents($root.'admin/api.php');
$admin_view = file_get_contents($root.'admin-ui-source/src/views/qifu/admin-page.vue');

check_link_apply(strpos($index_source, "wrap.addEventListener('click'") === false, 'clicking the modal backdrop still closes the link application');
check_link_apply(strpos($index_source, "setTimeout(function(){ wrap.classList.remove('open'); }") === false, 'successful submission still closes the modal automatically');
check_link_apply(strpos($index_source, 'id="lkmCloseBtn" type="button"') !== false, 'the close control is not an explicit button');
check_link_apply(strpos($index_source, 'backdrop-filter:blur(30px) saturate(1.16)') !== false, 'the selected glacier glass treatment is missing');
check_link_apply(strpos($index_source, 'id="lkmDoneCloseBtn" type="button"') !== false, 'the success state has no explicit return action');
check_link_apply(strpos($index_source, 'titleEl.textContent = options.headerTitle') !== false && strpos($index_source, "headerTitle:'申请已提交'") !== false, 'the success state does not update the glass dialog heading');
check_link_apply(strpos($index_source, '<div class="lkm-tick">✅</div>') === false, 'the legacy emoji success state is still present');
check_link_apply(strpos($index_source, 'aria-live="polite"') !== false, 'submission completion is not announced to assistive technology');
check_link_apply(strpos($index_source, "setSubmitState('loading', '正在提交...')") !== false, 'the submit button has no loading feedback');
check_link_apply(strpos($index_source, 'function showDoneState(options)') !== false, 'the animated completion state helper is missing');
check_link_apply(strpos($index_source, "indexOf('已提交过申请')") !== false, 'duplicate pending applications are not handled as an in-review state');
check_link_apply(strpos($index_source, "buttonState:'pending'") !== false && strpos($index_source, "title:'该网站已提交过申请'") !== false, 'the duplicate application state does not present positive review feedback');
check_link_apply(strpos($index_source, "matchMedia('(prefers-reduced-motion: reduce)')") !== false, 'the completion transition does not respect reduced-motion preferences');
check_link_apply(strpos($index_source, "ajax_site_meta.php") !== false, 'the link form does not request site metadata');
check_link_apply(is_file($endpoint), 'site metadata endpoint is missing');
check_link_apply(is_file($helper), 'site metadata helper is missing');
check_link_apply(is_file($notification_helper), 'link application notification helper is missing');
check_link_apply(strpos($link_endpoint, 'qifu_send_link_application_notification') !== false, 'successful link applications do not trigger the notification service');
check_link_apply(strpos($admin_api, "if(\$action === 'link_mail_toggle')") !== false, 'link notification switch API is missing');
check_link_apply(strpos($admin_view, 'v-model="linkMailNotifyEnabled"') !== false && strpos($admin_view, "go('SettingsMail')") !== false, 'link notification controls are missing from the admin page');

if(is_file($helper)){
    $helper_source = file_get_contents($helper);
    check_link_apply(strpos($helper_source, 'CURLOPT_RESOLVE') !== false, 'metadata requests do not pin the validated DNS result');
    check_link_apply(strpos($helper_source, 'CURLOPT_FOLLOWLOCATION => false') !== false, 'metadata requests follow redirects without validating each target');
    check_link_apply(strpos($helper_source, 'CURLOPT_SSL_VERIFYPEER => true') !== false, 'metadata HTTPS certificate verification is not enforced');
    check_link_apply(strpos($helper_source, '$max_bytes = 1048576') !== false, 'metadata response size is not limited');
    $endpoint_source = file_get_contents($endpoint);
    check_link_apply(strpos($endpoint_source, 'qifu_csrf_valid') !== false, 'metadata endpoint does not enforce CSRF validation');

    define('IN_CRONLITE', true);
    require $root.'includes/security.php';
    require $helper;

    $html = '<!doctype html><html><head>'
        .'<title>Fallback title</title>'
        .'<meta property="og:site_name" content="Example Site">'
        .'<meta name="description" content="  A useful website for testing.  ">'
        .'</head><body></body></html>';
    $meta = qifu_site_meta_parse_html($html);
    check_link_apply(isset($meta['name']) && $meta['name'] === 'Example Site', 'site name was not parsed from metadata');
    check_link_apply(isset($meta['description']) && $meta['description'] === 'A useful website for testing.', 'site description was not parsed from metadata');

    $error = '';
    check_link_apply(qifu_site_meta_normalize_url('example.com') === 'https://example.com/', 'bare domains are not normalized to HTTPS URLs');
    check_link_apply(qifu_site_meta_resolve_target('http://127.0.0.1/', $error) === false, 'loopback metadata targets are not blocked');
    check_link_apply(qifu_site_meta_resolve_target('https://example.com:8443/', $error) === false, 'non-standard metadata ports are not blocked');
}

if(is_file($notification_helper)){
    if(!defined('IN_CRONLITE')) define('IN_CRONLITE', true);
    require_once $notification_helper;
    $configuration_error = '';
    $ready_config = array(
        'mail_enabled'=>'1', 'mail_to'=>'admin@example.com', 'mail_user'=>'sender@example.com',
        'mail_pass'=>'authorization-code', 'mail_host'=>'smtp.example.com', 'mail_port'=>'587'
    );
    check_link_apply(qifu_link_notification_configuration_ready($ready_config, $configuration_error), 'complete mail settings are not recognized');
    $ready_config['mail_to'] = 'invalid-email';
    check_link_apply(!qifu_link_notification_configuration_ready($ready_config, $configuration_error), 'invalid notification recipient is accepted');
}

if($failures){
    fwrite(STDERR, "Link application tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Link application tests passed.\n";
