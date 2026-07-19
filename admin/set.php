<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

include __DIR__ . "/../includes/common.php";
$title='祈福导航系统 - 快捷设置';
if($islogin!=1){
    @header('Location: ./login.php');
    exit;
}

// 处理提交
if($_SERVER['REQUEST_METHOD']=='POST'){
    qifu_require_csrf();
    if(isset($_POST['base_settings'])){
        $sitename_save = mb_substr(trim(isset($_POST['sitename']) ? (string)$_POST['sitename'] : (string)$conf['sitename']), 0, 80);
        if($sitename_save === '') $sitename_save = isset($conf['sitename']) && trim((string)$conf['sitename']) !== '' ? trim((string)$conf['sitename']) : '网站名称';
        saveSetting('sitename', $sitename_save);
        saveSetting('title', isset($_POST['title']) ? $_POST['title'] : $conf['title']);
        $keywords_save = mb_substr(trim(isset($_POST['keywords']) ? (string)$_POST['keywords'] : (isset($conf['keywords']) ? (string)$conf['keywords'] : '')), 0, 255);
        saveSetting('keywords', $keywords_save);
        saveSetting('description', isset($_POST['description']) ? $_POST['description'] : $conf['description']);
        saveSetting('modal', isset($_POST['modal']) ? $_POST['modal'] : $conf['modal']);
        saveSetting('music', isset($_POST['music']) ? $_POST['music'] : (isset($conf['music']) ? $conf['music'] : ''));
        saveSetting('kfqq', isset($_POST['kfqq']) ? $_POST['kfqq'] : $conf['kfqq']);
        saveSetting('url', isset($_POST['url']) ? $_POST['url'] : (isset($conf['url']) ? $conf['url'] : ''));
        saveSetting('qqjump', isset($_POST['qqjump']) ? $_POST['qqjump'] : $conf['qqjump']);
        $site_logo = isset($_POST['site_logo']) ? trim($_POST['site_logo']) : '';
        $upload_dir = ROOT.'images/logo/';
        if(isset($_FILES['site_logo_upload']) && $_FILES['site_logo_upload']['error']==0){
            $upload_error = '';
            $filename = qifu_safe_image_upload($_FILES['site_logo_upload'], $upload_dir, 'logo', $upload_error);
            if($filename !== false) $site_logo = qifu_media_upload_url('images/logo/'.$filename, $rooturl);
            else $logo_upload_error = $upload_error !== '' ? $upload_error : 'LOGO 上传失败';
        }
        $site_logo = qifu_media_normalize_url($site_logo, $rooturl);
        if($site_logo !== '' && !preg_match('#^https?://#i', $site_logo) && qifu_media_local_relative_path($site_logo) === false) $site_logo = '';
        saveSetting('site_logo',$site_logo);
        writeLog('修改', '设置', 0, '保存基本设置');
        $CACHE->clear();
        $conf = $CACHE->update();
        $saved = true;
    }
    if(isset($_POST['bg_settings'])){
        $bg_mode_save = in_array($_POST['bg_mode'], array('default','bing','custom'), true) ? $_POST['bg_mode'] : 'default';
        saveSetting('bg_mode',$bg_mode_save);
        saveSetting('bg_custom',qifu_media_normalize_url(isset($_POST['bg_custom']) ? $_POST['bg_custom'] : '', $rooturl));
        $upload_dir = ROOT.'images/bg/';
        if($bg_mode_save=='custom' && isset($_FILES['bg_upload']) && $_FILES['bg_upload']['error']==0){
            $upload_error = '';
            $filename = qifu_safe_image_upload($_FILES['bg_upload'], $upload_dir, 'custom', $upload_error);
            if($filename !== false) saveSetting('bg_custom',qifu_media_upload_url('images/bg/'.$filename, $rooturl));
        }
        writeLog('修改', '设置', 0, '保存背景设置');
        $CACHE->clear();
        $conf = $CACHE->update();
        $saved = true;
    }
    if(isset($_POST['ui_settings'])){
        saveSetting('card_size',in_array($_POST['card_size'],array('small','normal','large'),true)?$_POST['card_size']:'normal');
        saveSetting('columns',in_array($_POST['columns'],array('2','3','4','auto'),true)?$_POST['columns']:'auto');
        saveSetting('time_format',in_array($_POST['time_format'],array('12','24'),true)?$_POST['time_format']:'24');
        saveSetting('clock_style',in_array($_POST['clock_style'],array('digital','simple'),true)?$_POST['clock_style']:'digital');
        saveSetting('announcement',$_POST['announcement']);
        saveSetting('show_search',$_POST['show_search']);
        saveSetting('site_search_enabled',isset($_POST['site_search_enabled']) && $_POST['site_search_enabled']==='1' ? '1' : '0');
        saveSetting('show_clock',$_POST['show_clock']);
        $show_tags_save = isset($_POST['show_tags']) && $_POST['show_tags'] === '0' ? '0' : '1';
        saveSetting('show_tags',$show_tags_save);
        $quick_tag_names_save = isset($_POST['quick_tag_name']) && is_array($_POST['quick_tag_name']) ? $_POST['quick_tag_name'] : array();
        $quick_tag_urls_save = isset($_POST['quick_tag_url']) && is_array($_POST['quick_tag_url']) ? $_POST['quick_tag_url'] : array();
        $quick_tags_invalid = 0;
        $quick_tags_save = qifu_quick_tags_from_input($quick_tag_names_save, $quick_tag_urls_save, $quick_tags_invalid);
        saveSetting('quick_tags',qifu_quick_tags_encode($quick_tags_save));
        saveSetting('bg_animation',$_POST['bg_animation']);
        saveSetting('card_animation',$_POST['card_animation']);
        writeLog('修改', '设置', 0, '保存界面设置');
        $CACHE->clear();
        $conf = $CACHE->update();
        $saved = true;
        $quick_tags_feedback = '快捷标签已保存，共 '.count($quick_tags_save).' 个。';
        if($quick_tags_invalid > 0) $quick_tags_feedback .= ' 有 '.$quick_tags_invalid.' 行因名称、链接或数量限制未保存。';
    }
    if(isset($_POST['media_settings'])){
        saveSetting('bg_music',$_POST['bg_music']);
        saveSetting('bg_music_volume',$_POST['bg_music_volume']);
        saveSetting('ping_enabled',$_POST['ping_enabled']);
        $ping_alert_latency_save = max(500, min(30000, intval($_POST['ping_alert_latency'])));
        saveSetting('ping_alert_latency',$ping_alert_latency_save);
        writeLog('修改', '设置', 0, '保存音乐与延迟设置');
        $CACHE->clear();
        $conf = $CACHE->update();
        $saved = true;
    }
    if(isset($_POST['online_stats_settings'])){
        $online_stats_enabled_save = isset($_POST['online_stats_enabled']) && $_POST['online_stats_enabled'] === '0' ? '0' : '1';
        $online_stats_mode_save = isset($_POST['online_stats_mode']) && $_POST['online_stats_mode'] === 'random' ? 'random' : 'real';
        $online_stats_color_save = isset($_POST['online_stats_color']) && $_POST['online_stats_color'] === 'highlight' ? 'highlight' : 'dark';
        $online_stats_random_scheme_save = isset($_POST['online_stats_random_scheme']) && $_POST['online_stats_random_scheme'] === 'rule' ? 'rule' : 'builtin';
        $online_stats_random_active_min_save = max(0, min(1000000, intval(isset($_POST['online_stats_random_active_min']) ? $_POST['online_stats_random_active_min'] : 1)));
        $online_stats_random_active_max_save = max(0, min(1000000, intval(isset($_POST['online_stats_random_active_max']) ? $_POST['online_stats_random_active_max'] : 8)));
        if($online_stats_random_active_max_save < $online_stats_random_active_min_save){
            $online_stats_random_swap = $online_stats_random_active_min_save;
            $online_stats_random_active_min_save = $online_stats_random_active_max_save;
            $online_stats_random_active_max_save = $online_stats_random_swap;
        }
        $online_stats_random_today_min_save = max(0, min(1000000, intval(isset($_POST['online_stats_random_today_min']) ? $_POST['online_stats_random_today_min'] : 8)));
        $online_stats_random_today_max_save = max(0, min(1000000, intval(isset($_POST['online_stats_random_today_max']) ? $_POST['online_stats_random_today_max'] : 36)));
        if($online_stats_random_today_max_save < $online_stats_random_today_min_save){
            $online_stats_random_swap = $online_stats_random_today_min_save;
            $online_stats_random_today_min_save = $online_stats_random_today_max_save;
            $online_stats_random_today_max_save = $online_stats_random_swap;
        }
        $online_stats_random_trend_save = isset($_POST['online_stats_random_trend']) && in_array($_POST['online_stats_random_trend'], array('steady','rise','fall'), true) ? $_POST['online_stats_random_trend'] : 'steady';
        $online_stats_random_start_date_save = trim((string)(isset($_POST['online_stats_random_start_date']) ? $_POST['online_stats_random_start_date'] : ''));
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $online_stats_random_start_date_save)) $online_stats_random_start_date_save = date('Y-m-d');
        $online_stats_random_start_check = DateTime::createFromFormat('!Y-m-d', $online_stats_random_start_date_save);
        if(!$online_stats_random_start_check || $online_stats_random_start_check->format('Y-m-d') !== $online_stats_random_start_date_save || $online_stats_random_start_date_save > date('Y-m-d')) $online_stats_random_start_date_save = date('Y-m-d');
        $online_stats_random_base_visits_save = max(0, min(1000000000, intval(isset($_POST['online_stats_random_base_visits']) ? $_POST['online_stats_random_base_visits'] : 5000)));
        $online_stats_random_stable_save = isset($_POST['online_stats_random_stable']) && $_POST['online_stats_random_stable'] === '1' ? '1' : '0';
        $online_stats_privacy_ip_save = isset($_POST['online_stats_privacy_ip']) && $_POST['online_stats_privacy_ip'] === '1' ? '1' : '0';
        saveSetting('online_stats_enabled',$online_stats_enabled_save);
        saveSetting('online_stats_mode',$online_stats_mode_save);
        saveSetting('online_stats_color',$online_stats_color_save);
        saveSetting('online_stats_random_scheme',$online_stats_random_scheme_save);
        saveSetting('online_stats_random_active_min',$online_stats_random_active_min_save);
        saveSetting('online_stats_random_active_max',$online_stats_random_active_max_save);
        saveSetting('online_stats_random_today_min',$online_stats_random_today_min_save);
        saveSetting('online_stats_random_today_max',$online_stats_random_today_max_save);
        saveSetting('online_stats_random_trend',$online_stats_random_trend_save);
        saveSetting('online_stats_random_start_date',$online_stats_random_start_date_save);
        saveSetting('online_stats_random_base_visits',$online_stats_random_base_visits_save);
        saveSetting('online_stats_random_stable',$online_stats_random_stable_save);
        saveSetting('online_stats_privacy_ip',$online_stats_privacy_ip_save);
        writeLog('修改', '设置', 0, '保存在线统计设置');
        $CACHE->clear();
        $conf = $CACHE->update();
        $saved = true;
        $online_stats_feedback = $online_stats_enabled_save === '0'
            ? '在线统计组件已关闭。'
            : '在线统计已开启，当前使用'.($online_stats_mode_save === 'random' ? '随机数据' : '真实数据').'模式。';
    }
    if(isset($_POST['footer_settings'])){
        saveSetting('footer_text',$_POST['footer_text']);
        $footer_link_save = trim((string)$_POST['footer_link']);
        if($footer_link_save !== '' && (!filter_var($footer_link_save,FILTER_VALIDATE_URL) || !preg_match('#^https?://#i',$footer_link_save))) $footer_link_save = '';
        saveSetting('footer_link',$footer_link_save);
        saveSetting('footer_link_text',$_POST['footer_link_text']);
        saveSetting('icp',mb_substr(trim((string)$_POST['icp']),0,80));
        $gongan_beian_save = mb_substr(trim(isset($_POST['gongan_beian']) ? (string)$_POST['gongan_beian'] : ''),0,80);
        $gongan_beian_url_save = trim(isset($_POST['gongan_beian_url']) ? (string)$_POST['gongan_beian_url'] : '');
        if($gongan_beian_url_save !== '' && (!filter_var($gongan_beian_url_save,FILTER_VALIDATE_URL) || !preg_match('#^https://#i',$gongan_beian_url_save))) $gongan_beian_url_save = '';
        saveSetting('gongan_beian',$gongan_beian_save);
        saveSetting('gongan_beian_url',$gongan_beian_url_save);
        $footer_opacity_save = max(5, min(100, intval($_POST['footer_opacity'])));
        $footer_size_save = max(10, min(18, intval($_POST['footer_size'])));
        saveSetting('footer_opacity',$footer_opacity_save);
        saveSetting('footer_size',$footer_size_save);
        writeLog('修改', '设置', 0, '保存页脚设置');
        $CACHE->clear();
        $conf = $CACHE->update();
        $saved = true;
    }
    if(isset($_POST['mail_settings'])){
        saveSetting('mail_enabled',$_POST['mail_enabled']);
        saveSetting('mail_to',$_POST['mail_to']);
        saveSetting('mail_user',$_POST['mail_user']);
        saveSetting('mail_pass',$_POST['mail_pass']);
        saveSetting('mail_host',$_POST['mail_host']);
        saveSetting('mail_port',$_POST['mail_port']);
        saveSetting('mail_sender',$_POST['mail_sender']);
        writeLog('修改', '设置', 0, '保存邮件设置');
        $CACHE->clear();
        $conf = $CACHE->update();
        $saved = true;
    }
}

// 获取当前设置值
$conf = $CACHE->update();
if(empty($conf['cron_key'])){
    saveSetting('cron_key', bin2hex(random_bytes(24)));
    $CACHE->clear();
    $conf = $CACHE->update();
}

// 基本设置
$site_logo = isset($conf['site_logo']) ? $conf['site_logo'] : '';
$site_keywords = isset($conf['keywords']) ? (string)$conf['keywords'] : '';
$bg_mode = isset($conf['bg_mode']) ? $conf['bg_mode'] : 'default';
$bg_custom = isset($conf['bg_custom']) ? $conf['bg_custom'] : '';
$default_bg_preview = $rooturl.'images/moren.webp';

// UI设置
$card_size = isset($conf['card_size']) ? $conf['card_size'] : 'normal';
$columns = isset($conf['columns']) ? $conf['columns'] : 'auto';
$time_format = isset($conf['time_format']) ? $conf['time_format'] : '24';
$clock_style = isset($conf['clock_style']) ? $conf['clock_style'] : 'digital';
$announcement = isset($conf['announcement']) ? $conf['announcement'] : '';
$show_search = isset($conf['show_search']) ? $conf['show_search'] : '1';
$site_search_enabled = isset($conf['site_search_enabled']) && $conf['site_search_enabled'] === '1' ? '1' : '0';
$show_clock = isset($conf['show_clock']) ? $conf['show_clock'] : '1';
$show_tags = isset($conf['show_tags']) ? $conf['show_tags'] : '1';
$quick_tags = qifu_quick_tags_from_config($conf);
$bg_animation = isset($conf['bg_animation']) ? $conf['bg_animation'] : '1';
$card_animation = isset($conf['card_animation']) ? $conf['card_animation'] : '1';

// 广告设置
$ad_enabled = isset($conf['ad_enabled']) ? $conf['ad_enabled'] : '0';
$ad_position = isset($conf['ad_position']) ? $conf['ad_position'] : 'below_search';
$ad_image = isset($conf['ad_image']) ? $conf['ad_image'] : '';
$ad_link = isset($conf['ad_link']) ? $conf['ad_link'] : '';
$ad_title = isset($conf['ad_title']) ? $conf['ad_title'] : '';
$ad_alt = isset($conf['ad_alt']) ? $conf['ad_alt'] : '';
$ad_new_window = isset($conf['ad_new_window']) ? $conf['ad_new_window'] : '1';

// 音乐设置
$bg_music = isset($conf['bg_music']) ? $conf['bg_music'] : '';
$bg_music_volume = isset($conf['bg_music_volume']) ? $conf['bg_music_volume'] : '50';

// Ping延迟设置
$ping_enabled = isset($conf['ping_enabled']) ? $conf['ping_enabled'] : '0';
$ping_alert_latency = isset($conf['ping_alert_latency']) ? intval($conf['ping_alert_latency']) : 3000;
$ping_alert_latency = max(500, min(30000, $ping_alert_latency));
$ping_last_run = isset($conf['ping_last_run']) ? $conf['ping_last_run'] : '';
$ping_last_time = !empty($conf['ping_last_time']) ? date('Y-m-d H:i:s', intval($conf['ping_last_time'])) : '暂未检测';

// 在线统计设置
$online_stats_enabled = isset($conf['online_stats_enabled']) && $conf['online_stats_enabled'] === '0' ? '0' : '1';
$online_stats_mode = isset($conf['online_stats_mode']) && $conf['online_stats_mode'] === 'random' ? 'random' : 'real';
$online_stats_color = isset($conf['online_stats_color']) && $conf['online_stats_color'] === 'dark' ? 'dark' : 'highlight';
$online_stats_random_scheme = isset($conf['online_stats_random_scheme']) && $conf['online_stats_random_scheme'] === 'rule' ? 'rule' : 'builtin';
$online_stats_random_active_min = max(0, min(1000000, intval(isset($conf['online_stats_random_active_min']) ? $conf['online_stats_random_active_min'] : 1)));
$online_stats_random_active_max = max(0, min(1000000, intval(isset($conf['online_stats_random_active_max']) ? $conf['online_stats_random_active_max'] : 8)));
if($online_stats_random_active_max < $online_stats_random_active_min){ $online_stats_random_swap = $online_stats_random_active_min; $online_stats_random_active_min = $online_stats_random_active_max; $online_stats_random_active_max = $online_stats_random_swap; }
$online_stats_random_today_min = max(0, min(1000000, intval(isset($conf['online_stats_random_today_min']) ? $conf['online_stats_random_today_min'] : 8)));
$online_stats_random_today_max = max(0, min(1000000, intval(isset($conf['online_stats_random_today_max']) ? $conf['online_stats_random_today_max'] : 36)));
if($online_stats_random_today_max < $online_stats_random_today_min){ $online_stats_random_swap = $online_stats_random_today_min; $online_stats_random_today_min = $online_stats_random_today_max; $online_stats_random_today_max = $online_stats_random_swap; }
$online_stats_random_trend = isset($conf['online_stats_random_trend']) && in_array($conf['online_stats_random_trend'], array('steady','rise','fall'), true) ? $conf['online_stats_random_trend'] : 'steady';
$online_stats_random_start_raw = isset($conf['online_stats_random_start_date']) ? (string)$conf['online_stats_random_start_date'] : (isset($conf['online_stats_random_seed_date']) ? (string)$conf['online_stats_random_seed_date'] : '');
$online_stats_random_start_date = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $online_stats_random_start_raw) ? $online_stats_random_start_raw : date('Y-m-d');
$online_stats_random_start_check = DateTime::createFromFormat('!Y-m-d', $online_stats_random_start_date);
if(!$online_stats_random_start_check || $online_stats_random_start_check->format('Y-m-d') !== $online_stats_random_start_date || $online_stats_random_start_date > date('Y-m-d')) $online_stats_random_start_date = date('Y-m-d');
$online_stats_random_base_visits = max(0, min(1000000000, intval(isset($conf['online_stats_random_base_visits']) ? $conf['online_stats_random_base_visits'] : 5000)));
$online_stats_random_stable = isset($conf['online_stats_random_stable']) && $conf['online_stats_random_stable'] === '0' ? '0' : '1';
$online_stats_privacy_ip = isset($conf['online_stats_privacy_ip']) && $conf['online_stats_privacy_ip'] === '1' ? '1' : '0';

// 页脚设置
$footer_text = isset($conf['footer_text']) ? $conf['footer_text'] : '祈福导航系统 · 精选优质资源';
$footer_link = isset($conf['footer_link']) ? $conf['footer_link'] : '';
$footer_link_text = isset($conf['footer_link_text']) ? $conf['footer_link_text'] : '';
$icp = isset($conf['icp']) ? $conf['icp'] : '';
$gongan_beian = isset($conf['gongan_beian']) ? $conf['gongan_beian'] : '';
$gongan_beian_url = isset($conf['gongan_beian_url']) ? $conf['gongan_beian_url'] : '';
$footer_opacity = isset($conf['footer_opacity']) ? intval($conf['footer_opacity']) : 25;
$footer_size = isset($conf['footer_size']) ? intval($conf['footer_size']) : 12;
$footer_opacity = max(5, min(100, $footer_opacity));
$footer_size = max(10, min(18, $footer_size));

// 邮件设置
$mail_enabled = isset($conf['mail_enabled']) ? $conf['mail_enabled'] : '0';
$mail_to = isset($conf['mail_to']) ? $conf['mail_to'] : '';
$mail_user = isset($conf['mail_user']) ? $conf['mail_user'] : '';
$mail_pass = isset($conf['mail_pass']) ? $conf['mail_pass'] : '';
$mail_host = isset($conf['mail_host']) ? $conf['mail_host'] : 'smtp.qq.com';
$mail_port = isset($conf['mail_port']) ? $conf['mail_port'] : '587';
$mail_sender = isset($conf['mail_sender']) ? $conf['mail_sender'] : '';
include __DIR__.'/head.php';
?>
<div class="container" style="padding-top:70px;">
<?php if(isset($saved)){ ?>
    <div class="alert alert-success" style="margin:0 0 20px 0;"><span class="glyphicon glyphicon-ok"></span> 保存成功！</div>
    <?php if(isset($logo_upload_error)){ ?>
    <div class="alert alert-danger" role="alert" style="margin:0 0 20px 0;"><span class="glyphicon glyphicon-exclamation-sign"></span> <?php echo htmlspecialchars($logo_upload_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>
    <?php if(isset($_POST['base_settings'])){ ?>
    <script>
    if(window.parent && window.parent !== window){
        window.parent.postMessage({
            type: 'qifu-brand-updated',
            payload: <?php echo json_encode(array('name'=>$conf['sitename'], 'logo'=>$site_logo), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
        }, window.location.origin);
    }
    </script>
    <?php } ?>
<?php } ?>
    <nav class="qf-settings-shortcuts" id="settingsShortcuts" aria-label="快捷设置分类">
      <a class="qf-settings-shortcut is-active" href="#basic-settings" data-setting-target="basic-settings"><span class="glyphicon glyphicon-cog" aria-hidden="true"></span><span>基本设置</span></a>
      <a class="qf-settings-shortcut" href="#background-settings" data-setting-target="background-settings"><span class="glyphicon glyphicon-picture" aria-hidden="true"></span><span>背景设置</span></a>
      <a class="qf-settings-shortcut" href="#ui-settings" data-setting-target="ui-settings"><span class="glyphicon glyphicon-th-large" aria-hidden="true"></span><span>前台界面</span></a>
      <a class="qf-settings-shortcut" href="./ad.php"><span class="glyphicon glyphicon-picture" aria-hidden="true"></span><span>广告设置</span><span class="glyphicon glyphicon-new-window qf-settings-shortcut-external" aria-hidden="true"></span></a>
      <a class="qf-settings-shortcut" href="#media-settings" data-setting-target="media-settings"><span class="glyphicon glyphicon-music" aria-hidden="true"></span><span>音乐检测</span></a>
      <a class="qf-settings-shortcut" href="#online-stats-settings" data-setting-target="online-stats-settings"><span class="glyphicon glyphicon-stats" aria-hidden="true"></span><span>在线统计</span></a>
      <a class="qf-settings-shortcut" href="#footer-settings" data-setting-target="footer-settings"><span class="glyphicon glyphicon-list-alt" aria-hidden="true"></span><span>页脚备案</span></a>
      <a class="qf-settings-shortcut" href="#mail-settings" data-setting-target="mail-settings"><span class="glyphicon glyphicon-envelope" aria-hidden="true"></span><span>邮件通知</span></a>
    </nav>
    <div class="qf-detail-content qf-settings-content center-block">

      <!-- 基本设置 -->
      <div class="panel panel-primary" id="basic-settings">
        <div class="panel-heading">
          <h3 class="panel-title"><span class="glyphicon glyphicon-cog art-title-glyph" aria-hidden="true"></span>基本设置</h3>
        </div>
        <div class="panel-body" style="padding:20px;">
          <form action="./set.php" method="post" enctype="multipart/form-data">
            <?php echo qifu_csrf_input(); ?>
            <input type="hidden" name="base_settings" value="1">
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>网站名称</label>
                  <input type="text" id="qfSiteNameInput" name="sitename" value="<?php echo htmlspecialchars($conf['sitename']); ?>" class="form-control" required>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label>标题栏后缀</label>
                  <input type="text" name="title" value="<?php echo htmlspecialchars($conf['title']); ?>" class="form-control">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-12">
                <div class="form-group">
                  <label for="qfSiteKeywordsInput">网站关键词</label>
                  <input type="text" id="qfSiteKeywordsInput" name="keywords" value="<?php echo htmlspecialchars($site_keywords, ENT_QUOTES, 'UTF-8'); ?>" class="form-control" maxlength="255" placeholder="例如：网址导航,资源导航,效率工具" aria-describedby="qfSiteKeywordsHelp">
                  <p class="help-block" id="qfSiteKeywordsHelp">多个关键词请使用逗号分隔，用于搜索引擎识别网站主题。</p>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-8">
                <div class="form-group">
                  <label>网站描述</label>
                  <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($conf['description']); ?></textarea>
                </div>
              </div>
              <div class="col-sm-4">
                <div class="form-group">
                  <label>客服QQ</label>
                  <input type="text" name="kfqq" value="<?php echo htmlspecialchars($conf['kfqq']); ?>" class="form-control">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>导航介绍语</label>
                  <input type="text" name="modal" value="<?php echo htmlspecialchars($conf['modal']); ?>" class="form-control">
                </div>
              </div>
              <div class="col-sm-3">
                <div class="form-group">
                  <label>手机QQ跳转浏览器</label>
                  <select name="qqjump" class="form-control" default="<?php echo $conf['qqjump']?>">
                    <option value="0">关闭</option>
                    <option value="1">开启</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-3">
                <div class="form-group">
                  <label>密码重置(留空不变)</label>
                  <input type="text" name="pwd" value="" class="form-control" placeholder="不修改请留空">
                </div>
              </div>
            </div>
            <div class="row qf-logo-setting-row">
              <div class="col-sm-8 qf-logo-upload-column">
                <div class="form-group">
                  <label>网站LOGO</label>
                  <div class="qf-logo-control">
                    <input type="text" id="qfSiteLogoUrl" name="site_logo" value="<?php echo htmlspecialchars($site_logo); ?>" class="form-control" placeholder="可填写图片链接，留空则显示网站名称首字">
                    <label class="btn btn-default qf-logo-upload-button" for="qfSiteLogoFile">
                      <span class="glyphicon glyphicon-upload" aria-hidden="true"></span>
                      <span>选择图片</span>
                    </label>
                    <input type="file" id="qfSiteLogoFile" name="site_logo_upload" accept="image/jpeg,image/png,image/gif,image/webp" class="qf-visually-hidden-file">
                  </div>
                  <p class="help-block qf-logo-upload-status" id="qfSiteLogoStatus" role="status" aria-live="polite">支持 JPG、PNG、GIF、WEBP；选择图片后会自动上传、回填地址并实时预览。</p>
                </div>
              </div>
              <div class="col-sm-4 qf-logo-preview-column">
                <div class="form-group">
                  <label>当前预览</label>
                  <div class="qf-setting-logo-preview" id="qfSiteLogoPreview">
                    <img id="qfSiteLogoPreviewImage" src="<?php echo htmlspecialchars($site_logo); ?>" alt="LOGO预览"<?php echo $site_logo ? '' : ' style="display:none;"'; ?>>
                    <span id="qfSiteLogoPreviewFallback"<?php echo $site_logo ? ' style="display:none;"' : ''; ?>><?php echo htmlspecialchars(function_exists('mb_substr') ? mb_substr($conf['sitename'],0,1,'UTF-8') : substr($conf['sitename'],0,1)); ?></span>
                    <div class="qf-setting-logo-copy">
                      <strong id="qfSiteLogoPreviewName"><?php echo htmlspecialchars($conf['sitename']); ?></strong>
                      <small>保存后同步到后台左侧导航</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="qf-form-actions"><button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>保存基本设置</button></div>
          </form>
        </div>
      </div>

      <!-- 背景设置 -->
      <div class="panel panel-info" id="background-settings">
        <div class="panel-heading">
          <h3 class="panel-title"><span class="glyphicon glyphicon-picture art-title-glyph" aria-hidden="true"></span>背景设置</h3>
        </div>
        <div class="panel-body" style="padding:20px;">
          <form action="./set.php" method="post" enctype="multipart/form-data">
            <?php echo qifu_csrf_input(); ?>
            <input type="hidden" name="bg_settings" value="1">
            <div class="form-group">
              <label>背景模式</label>
              <select id="qfBackgroundMode" name="bg_mode" class="form-control" onchange="toggleBgOpts(this.value)">
                <option value="default" <?php echo $bg_mode=='default'?'selected':''; ?>>默认背景</option>
                <option value="custom" <?php echo $bg_mode=='custom'?'selected':''; ?>>自定义上传</option>
                <option value="bing" <?php echo $bg_mode=='bing'?'selected':''; ?>>必应壁纸(每日更新)</option>
              </select>
            </div>
            <div id="customBgOpts" style="display:<?php echo $bg_mode=='custom'?'block':'none'; ?>">
              <div class="form-group">
                <label>上传背景图片 / 输入图片URL</label>
                <div class="qf-background-control">
                  <input type="text" id="qfBackgroundUrl" name="bg_custom" value="<?php echo htmlspecialchars($bg_custom); ?>" class="form-control" placeholder="直接填写图片链接">
                  <label class="btn btn-default qf-background-upload-button" for="qfBackgroundFile">
                    <span class="glyphicon glyphicon-upload" aria-hidden="true"></span>
                    <span>选择图片</span>
                  </label>
                  <input type="file" id="qfBackgroundFile" name="bg_upload" accept="image/jpeg,image/png,image/gif,image/webp" class="qf-visually-hidden-file">
                </div>
                <span class="help-block" id="qfBackgroundUploadStatus">支持 JPG、PNG、GIF、WEBP；选择图片后会立即显示预览。</span>
              </div>
            </div>
            <div id="bingOpts" style="display:<?php echo $bg_mode=='bing'?'block':'none'; ?>">
              <div class="alert alert-info" style="margin:0;">
                <span class="glyphicon glyphicon-info-sign"></span> 必应壁纸每日自动更新
              </div>
            </div>
            <section class="qf-background-preview-card" id="qfBackgroundPreview" aria-labelledby="qfBackgroundPreviewTitle">
              <div class="qf-background-preview-head">
                <div class="qf-background-preview-copy">
                  <strong id="qfBackgroundPreviewTitle">背景效果预览</strong>
                  <small id="qfBackgroundPreviewText">当前使用默认背景</small>
                </div>
                <span class="qf-background-preview-badge" id="qfBackgroundPreviewBadge">默认背景</span>
              </div>
              <div class="qf-background-preview-stage">
                <img id="qfBackgroundPreviewImage" src="<?php echo htmlspecialchars($default_bg_preview, ENT_QUOTES, 'UTF-8'); ?>" alt="前台背景效果预览">
                <div class="qf-background-preview-empty" id="qfBackgroundPreviewEmpty" hidden>
                  <span class="glyphicon glyphicon-picture" aria-hidden="true"></span>
                  <strong>暂时无法显示背景</strong>
                  <small>请检查图片地址或重新选择图片</small>
                </div>
                <div class="qf-background-preview-mock" aria-hidden="true">
                  <div class="qf-background-preview-brand">
                    <span><?php echo htmlspecialchars(function_exists('mb_substr') ? mb_substr($conf['sitename'],0,1,'UTF-8') : substr($conf['sitename'],0,1)); ?></span>
                    <b><?php echo htmlspecialchars($conf['sitename'], ENT_QUOTES, 'UTF-8'); ?></b>
                  </div>
                  <div class="qf-background-preview-search"><i></i><span>搜索您需要的内容</span><b>搜索</b></div>
                  <div class="qf-background-preview-sites"><i></i><i></i><i></i><i></i></div>
                </div>
              </div>
            </section>
            <div class="qf-form-actions"><button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>保存背景设置</button></div>
          </form>
        </div>
      </div>

      <!-- 前台界面设置 -->
      <div class="panel panel-success" id="ui-settings">
        <div class="panel-heading">
          <h3 class="panel-title"><span class="glyphicon glyphicon-th-large art-title-glyph" aria-hidden="true"></span>前台界面设置</h3>
        </div>
        <div class="panel-body" style="padding:20px;">
          <form action="./set.php" method="post">
            <?php echo qifu_csrf_input(); ?>
            <input type="hidden" name="ui_settings" value="1">

            <h4 class="qf-form-section-title"><span class="glyphicon glyphicon-th" aria-hidden="true"></span>布局设置</h4>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>卡片大小</label>
                  <select name="card_size" class="form-control">
                    <option value="small" <?php echo $card_size=='small'?'selected':''; ?>>小卡片</option>
                    <option value="normal" <?php echo $card_size=='normal'?'selected':''; ?>>标准卡片</option>
                    <option value="large" <?php echo $card_size=='large'?'selected':''; ?>>大卡片</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label>网格列数</label>
                  <select name="columns" class="form-control">
                    <option value="2" <?php echo $columns=='2'?'selected':''; ?>>2列</option>
                    <option value="3" <?php echo $columns=='3'?'selected':''; ?>>3列</option>
                    <option value="4" <?php echo $columns=='4'?'selected':''; ?>>4列</option>
                    <option value="auto" <?php echo $columns=='auto'?'selected':''; ?>>自适应</option>
                  </select>
                </div>
              </div>
            </div>

            <h4 class="qf-form-section-title"><span class="glyphicon glyphicon-time" aria-hidden="true"></span>时钟设置</h4>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>时间格式</label>
                  <select name="time_format" class="form-control">
                    <option value="24" <?php echo $time_format=='24'?'selected':''; ?>>24小时制</option>
                    <option value="12" <?php echo $time_format=='12'?'selected':''; ?>>12小时制</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label>时钟样式</label>
                  <select name="clock_style" class="form-control">
                    <option value="digital" <?php echo $clock_style=='digital'?'selected':''; ?>>数字时钟</option>
                    <option value="simple" <?php echo $clock_style=='simple'?'selected':''; ?>>简约文字钟</option>
                  </select>
                </div>
              </div>
            </div>

            <h4 class="qf-form-section-title"><span class="glyphicon glyphicon-bullhorn" aria-hidden="true"></span>首页公告</h4>
            <div class="form-group">
              <label>公告内容（留空则不显示）</label>
              <textarea name="announcement" class="form-control" rows="2" placeholder="输入公告内容，如：🎉 新年快乐！"><?php echo htmlspecialchars($announcement); ?></textarea>
            </div>

            <h4 class="qf-form-section-title"><span class="glyphicon glyphicon-eye-open" aria-hidden="true"></span>显示控制</h4>
            <div class="row">
              <div class="col-sm-3">
                <div class="form-group">
                  <label>搜索框</label>
                  <select name="show_search" class="form-control">
                    <option value="1" <?php echo $show_search=='1'?'selected':''; ?>>显示</option>
                    <option value="0" <?php echo $show_search=='0'?'selected':''; ?>>隐藏</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-3">
                <div class="form-group">
                  <label>本站搜索</label>
                  <select name="site_search_enabled" class="form-control">
                    <option value="1" <?php echo $site_search_enabled==='1'?'selected':''; ?>>开启并设为默认</option>
                    <option value="0" <?php echo $site_search_enabled==='0'?'selected':''; ?>>关闭</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-3">
                <div class="form-group">
                  <label>时钟</label>
                  <select name="show_clock" class="form-control">
                    <option value="1" <?php echo $show_clock=='1'?'selected':''; ?>>显示</option>
                    <option value="0" <?php echo $show_clock=='0'?'selected':''; ?>>隐藏</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-3">
                <div class="form-group">
                  <label>快捷标签</label>
                  <select name="show_tags" class="form-control">
                    <option value="1" <?php echo $show_tags=='1'?'selected':''; ?>>显示</option>
                    <option value="0" <?php echo $show_tags=='0'?'selected':''; ?>>隐藏</option>
                  </select>
                </div>
              </div>
            </div>
            <p class="help-block" style="margin-top:-4px;">开启后搜索引擎菜单会增加“本站搜索”并默认选中；关闭后默认使用百度。</p>

            <div class="qf-quick-tags-editor" id="quickTagsEditor" data-max-tags="12">
              <div class="qf-quick-tags-toolbar">
                <h4><span class="glyphicon glyphicon-tags" aria-hidden="true"></span> 快捷标签内容</h4>
                <span class="qf-quick-tags-count">最多 12 个</span>
              </div>
              <?php if(isset($quick_tags_feedback)): ?>
              <div class="alert alert-success" role="status">
                <span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
                <?php echo htmlspecialchars($quick_tags_feedback, ENT_QUOTES, 'UTF-8'); ?>
              </div>
              <?php endif; ?>
              <div class="qf-quick-tags-head" aria-hidden="true">
                <span>标签名称</span>
                <span>跳转链接</span>
                <span>操作</span>
              </div>
              <div id="quickTagList">
                <?php foreach($quick_tags as $quick_tag): ?>
                <div class="qf-quick-tag-row">
                  <div class="qf-quick-tag-field">
                    <label>标签名称</label>
                    <input type="text" name="quick_tag_name[]" class="form-control" maxlength="30" required value="<?php echo htmlspecialchars($quick_tag['name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="如：开发资源">
                  </div>
                  <div class="qf-quick-tag-field">
                    <label>跳转链接</label>
                    <input type="url" name="quick_tag_url[]" class="form-control" maxlength="500" required value="<?php echo htmlspecialchars($quick_tag['url'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://example.com">
                  </div>
                  <button type="button" class="btn btn-default qf-quick-tag-remove" title="删除标签" aria-label="删除标签">
                    <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
                  </button>
                </div>
                <?php endforeach; ?>
              </div>
              <div class="qf-quick-tags-empty" id="quickTagsEmpty">暂无快捷标签</div>
              <button type="button" class="btn btn-default" id="addQuickTagBtn">
                <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> 添加标签
              </button>
              <p class="help-block">标签名称最多 30 个字符，链接仅支持 HTTP 或 HTTPS。</p>
            </div>

            <h4 class="qf-form-section-title"><span class="glyphicon glyphicon-transfer" aria-hidden="true"></span>动画设置</h4>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>背景动画</label>
                  <select name="bg_animation" class="form-control">
                    <option value="1" <?php echo $bg_animation=='1'?'selected':''; ?>>开启</option>
                    <option value="0" <?php echo $bg_animation=='0'?'selected':''; ?>>关闭</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label>卡片悬浮动画</label>
                  <select name="card_animation" class="form-control">
                    <option value="1" <?php echo $card_animation=='1'?'selected':''; ?>>开启</option>
                    <option value="0" <?php echo $card_animation=='0'?'selected':''; ?>>关闭</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="qf-form-actions"><button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>保存界面设置</button></div>
          </form>
        </div>
      </div>

      <!-- 音乐与延迟检测 -->
      <div class="panel panel-info">
        <div class="panel-heading">
          <h3 class="panel-title"><span class="glyphicon glyphicon-picture art-title-glyph" aria-hidden="true"></span>广告设置</h3>
        </div>
        <div class="panel-body" style="padding:20px;">
          <form action="./set.php" method="post" enctype="multipart/form-data">
            <?php echo qifu_csrf_input(); ?>
            <input type="hidden" name="ad_settings" value="1">
            <div class="row">
              <div class="col-sm-4">
                <div class="form-group">
                  <label>广告状态</label>
                  <select name="ad_enabled" class="form-control">
                    <option value="0" <?php echo $ad_enabled=='0'?'selected':''; ?>>关闭</option>
                    <option value="1" <?php echo $ad_enabled=='1'?'selected':''; ?>>开启</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-4">
                <div class="form-group">
                  <label>展示位置</label>
                  <select name="ad_position" class="form-control">
                    <option value="below_search" <?php echo $ad_position=='below_search'?'selected':''; ?>>搜索栏下方</option>
                    <option value="pc_side" <?php echo $ad_position=='pc_side'?'selected':''; ?>>PC端右侧悬浮</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-4">
                <div class="form-group">
                  <label>打开方式</label>
                  <select name="ad_new_window" class="form-control">
                    <option value="1" <?php echo $ad_new_window=='1'?'selected':''; ?>>新窗口打开</option>
                    <option value="0" <?php echo $ad_new_window=='0'?'selected':''; ?>>当前窗口打开</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>广告图片 URL / 上传图片</label>
              <div class="input-group">
                <input type="text" name="ad_image" value="<?php echo htmlspecialchars($ad_image); ?>" class="form-control" placeholder="https://example.com/ad.jpg">
                <span class="input-group-btn"><label class="btn btn-default" style="margin:0;">上传<input type="file" name="ad_upload" accept="image/*" style="display:none;"></label></span>
              </div>
              <span class="help-block">支持 jpg、png、gif、webp。建议搜索栏下方使用横幅图，PC侧边使用竖图或方图。</span>
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>广告跳转链接</label>
                  <input type="text" name="ad_link" value="<?php echo htmlspecialchars($ad_link); ?>" class="form-control" placeholder="https://example.com">
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label>广告标题</label>
                  <input type="text" name="ad_title" value="<?php echo htmlspecialchars($ad_title); ?>" class="form-control" placeholder="可选，用于悬停提示">
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>图片说明</label>
              <input type="text" name="ad_alt" value="<?php echo htmlspecialchars($ad_alt); ?>" class="form-control" placeholder="可选，用于图片无法加载时显示">
            </div>
            <?php if($ad_image): ?>
            <div class="form-group">
              <label>当前广告预览</label>
              <div style="max-width:520px;border:1px solid #ddd;border-radius:6px;padding:10px;background:#fafafa;">
                <img src="<?php echo htmlspecialchars($ad_image); ?>" alt="" style="max-width:100%;height:auto;border-radius:4px;">
              </div>
            </div>
            <?php endif; ?>
            <div class="qf-form-actions"><button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>保存广告设置</button></div>
          </form>
        </div>
      </div>

      <div class="panel panel-warning" id="media-settings">
        <div class="panel-heading">
          <h3 class="panel-title"><span class="glyphicon glyphicon-music art-title-glyph" aria-hidden="true"></span>音乐与延迟检测</h3>
        </div>
        <div class="panel-body" style="padding:20px;">
          <form action="./set.php" method="post">
            <?php echo qifu_csrf_input(); ?>
            <input type="hidden" name="media_settings" value="1">

            <h4 style="margin-bottom:15px;">🎧 背景音乐</h4>
            <div class="row">
              <div class="col-sm-8">
                <div class="form-group">
                  <label>音乐直链（MP3/OGG格式）</label>
                  <input type="text" name="bg_music" value="<?php echo htmlspecialchars($bg_music); ?>" class="form-control" placeholder="https://example.com/music.mp3">
                </div>
              </div>
              <div class="col-sm-4">
                <div class="form-group">
                  <label>音量（0-100）</label>
                  <input type="number" name="bg_music_volume" value="<?php echo $bg_music_volume; ?>" class="form-control" min="0" max="100">
                </div>
              </div>
            </div>
            <div class="alert alert-info">
              <span class="glyphicon glyphicon-info-sign"></span> 填入音乐直链后前台会自动显示音乐控制按钮，支持自动播放/暂停/调节音量
            </div>

            <h4 style="margin:25px 0 15px;">📡 站点延迟检测</h4>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>显示站点Ping延迟</label>
                  <select name="ping_enabled" class="form-control">
                    <option value="0" <?php echo $ping_enabled=='0'?'selected':''; ?>>关闭</option>
                    <option value="1" <?php echo $ping_enabled=='1'?'selected':''; ?>>开启</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-6" style="padding-top:25px;">
                <div class="alert alert-warning" style="margin:0;">
                  <span class="glyphicon glyphicon-warning-sign"></span> 开启后前台卡片右上角显示状态灯（绿色=正常/红色=不可访问或延迟过高）
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>红灯延迟阈值（毫秒）</label>
                  <input type="number" name="ping_alert_latency" value="<?php echo $ping_alert_latency; ?>" class="form-control" min="500" max="30000">
                  <span class="help-block">默认 3000ms，超过该值前台显示红灯</span>
                </div>
              </div>
            </div>
            <div class="alert alert-info">
              <span class="glyphicon glyphicon-info-sign"></span>
              每天 0 点自动检测建议在宝塔/服务器计划任务中访问：
              <code><?php echo htmlspecialchars($rooturl.'cron_site_status.php?key='.rawurlencode($conf['cron_key']).'&force=1', ENT_QUOTES, 'UTF-8'); ?></code>
              <br>最近检测：<?php echo htmlspecialchars($ping_last_time); ?><?php if($ping_last_run){ ?>（<?php echo htmlspecialchars($ping_last_run); ?>）<?php } ?>
            </div>
            <div class="alert alert-danger">
              <span class="glyphicon glyphicon-envelope"></span>
              红灯邮件提醒：开启本功能并在下方开启邮件通知后，每日计划任务检测到站点无法访问或延迟超过阈值，会自动发送汇总提醒到接收通知邮箱。
            </div>

            <div class="qf-form-actions"><button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>保存音乐与延迟设置</button></div>
          </form>
        </div>
      </div>

      <!-- 在线统计设置 -->
      <div class="panel panel-info" id="online-stats-settings">
        <div class="panel-heading">
          <h3 class="panel-title"><span class="glyphicon glyphicon-stats"></span> 在线统计设置</h3>
        </div>
        <div class="panel-body" style="padding:20px;">
          <form id="onlineStatsForm" action="./set.php#online-stats-settings" method="post">
            <?php echo qifu_csrf_input(); ?>
            <input type="hidden" name="online_stats_settings" value="1">
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label style="display:block;">前台统计组件</label>
                  <select name="online_stats_enabled" class="form-control" aria-label="前台统计组件显示状态">
                    <option value="1" <?php echo $online_stats_enabled==='1'?'selected':''; ?>>显示</option>
                    <option value="0" <?php echo $online_stats_enabled==='0'?'selected':''; ?>>关闭</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label style="display:block;">统计数据模式</label>
                  <select name="online_stats_mode" class="form-control" aria-label="统计数据模式">
                    <option value="real" <?php echo $online_stats_mode==='real'?'selected':''; ?>>真实数据</option>
                    <option value="random" <?php echo $online_stats_mode==='random'?'selected':''; ?>>随机数据</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label for="onlineStatsColor" style="display:block;">统计文字颜色</label>
                  <select id="onlineStatsColor" name="online_stats_color" class="form-control" aria-label="统计文字颜色">
                    <option value="dark" <?php echo $online_stats_color==='dark'?'selected':''; ?>>暗色</option>
                    <option value="highlight" <?php echo $online_stats_color==='highlight'?'selected':''; ?>>高亮（与站点名称一致）</option>
                  </select>
                  <p class="help-block">选择前台统计文字的显示亮度。</p>
                </div>
              </div>
            </div>
            <div id="onlineStatsRandomOptions" class="qf-online-stats-random" <?php echo $online_stats_mode === 'random' ? '' : 'hidden'; ?>>
              <div class="qf-online-stats-heading">
                <div><strong>随机数据设置</strong><p>选择内置方案直接使用，或通过规则方案控制前台统计数值。</p></div>
                <div class="qf-online-stats-segments" role="group" aria-label="随机数据方案">
                  <label class="online-stats-scheme <?php echo $online_stats_random_scheme === 'builtin' ? 'active' : ''; ?>">
                    <input type="radio" name="online_stats_random_scheme" value="builtin" <?php echo $online_stats_random_scheme === 'builtin' ? 'checked' : ''; ?>><span>内置随机</span>
                  </label>
                  <label class="online-stats-scheme <?php echo $online_stats_random_scheme === 'rule' ? 'active' : ''; ?>">
                    <input type="radio" name="online_stats_random_scheme" value="rule" <?php echo $online_stats_random_scheme === 'rule' ? 'checked' : ''; ?>><span>规则随机</span>
                  </label>
                </div>
              </div>
              <div id="onlineStatsRuleFields" <?php echo $online_stats_random_scheme === 'rule' ? '' : 'hidden'; ?>>
                <fieldset class="qf-online-stats-group">
                  <legend>基础设置</legend>
                  <div class="row">
                    <div class="col-sm-5">
                      <div class="form-group">
                        <label for="onlineStatsBaseVisits">累计访问基数</label>
                        <div class="input-group"><input id="onlineStatsBaseVisits" type="number" min="0" max="1000000000" class="form-control" name="online_stats_random_base_visits" value="<?php echo $online_stats_random_base_visits; ?>"><span class="input-group-addon">人次</span></div>
                        <p class="help-block">前台“本站已有 X 人访问”从这个数值起算。</p>
                      </div>
                    </div>
                    <div class="col-sm-7">
                      <div class="form-group">
                        <label for="onlineStatsStartDate">规则开始日期</label>
                        <input id="onlineStatsStartDate" type="date" max="<?php echo date('Y-m-d'); ?>" name="online_stats_random_start_date" class="form-control" value="<?php echo htmlspecialchars($online_stats_random_start_date, ENT_QUOTES, 'UTF-8'); ?>">
                        <p class="help-block">从该日期开始累计访问量；增长或回落会根据已经运行的天数逐步推进，不会修改真实统计数据。</p>
                      </div>
                    </div>
                  </div>
                </fieldset>
                <fieldset class="qf-online-stats-group">
                  <legend>每日随机范围</legend>
                  <div class="row">
                    <div class="col-sm-6">
                      <div class="form-group">
                        <label for="onlineStatsActiveMin">今日活跃人数</label>
                        <div class="qf-online-stats-range"><input id="onlineStatsActiveMin" type="number" min="0" max="1000000" class="form-control" name="online_stats_random_active_min" value="<?php echo $online_stats_random_active_min; ?>" aria-label="当前在线最小值"><span>至</span><input type="number" min="0" max="1000000" class="form-control" name="online_stats_random_active_max" value="<?php echo $online_stats_random_active_max; ?>" aria-label="当前在线最大值"></div>
                      </div>
                    </div>
                    <div class="col-sm-6">
                      <div class="form-group">
                        <label for="onlineStatsTodayMin">今日访问人数</label>
                        <div class="qf-online-stats-range"><input id="onlineStatsTodayMin" type="number" min="0" max="1000000" class="form-control" name="online_stats_random_today_min" value="<?php echo $online_stats_random_today_min; ?>" aria-label="今日访问最小值"><span>至</span><input type="number" min="0" max="1000000" class="form-control" name="online_stats_random_today_max" value="<?php echo $online_stats_random_today_max; ?>" aria-label="今日访问最大值"></div>
                      </div>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-sm-6">
                      <div class="form-group">
                        <label for="onlineStatsTrend">访问趋势</label>
                        <select id="onlineStatsTrend" name="online_stats_random_trend" class="form-control">
                          <option value="steady" <?php echo $online_stats_random_trend === 'steady' ? 'selected' : ''; ?>>平稳波动</option>
                          <option value="rise" <?php echo $online_stats_random_trend === 'rise' ? 'selected' : ''; ?>>逐步增长</option>
                          <option value="fall" <?php echo $online_stats_random_trend === 'fall' ? 'selected' : ''; ?>>逐步回落</option>
                        </select>
                        <p class="help-block">趋势只调整区间内的取值方向，数值不会突破上下限。</p>
                      </div>
                    </div>
                    <div class="col-sm-6 qf-online-stats-check-column">
                      <label class="qf-online-stats-check"><input type="checkbox" name="online_stats_random_stable" value="1" <?php echo $online_stats_random_stable === '1' ? 'checked' : ''; ?>><span><strong>按日期固定结果</strong><small>同一天刷新时保持相同，次日按规则生成新数据。</small></span></label>
                    </div>
                  </div>
                </fieldset>
              </div>
              <div class="qf-online-stats-display-options">
                <label class="qf-online-stats-check"><input type="checkbox" name="online_stats_privacy_ip" value="1" <?php echo $online_stats_privacy_ip === '1' ? 'checked' : ''; ?>><span><strong>隐藏访客 IP</strong><small>前台保留原位置，仅将 IP 内容显示为“已隐藏”。</small></span></label>
              </div>
            </div>
            <div class="alert alert-info" style="margin:4px 0 16px;">
              <span class="glyphicon glyphicon-info-sign"></span>
              真实数据按日期匿名去重访客 IP，并读取站点操作日志与页面浏览量；随机数据每天保持稳定，不会随刷新跳变。
            </div>
            <?php if(isset($online_stats_feedback)): ?>
            <div class="alert alert-success" role="status" style="margin:0 0 16px;">
              <span class="glyphicon glyphicon-ok-sign"></span>
              <?php echo htmlspecialchars($online_stats_feedback, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endif; ?>
            <div class="qf-form-actions">
              <button type="button" class="btn btn-default" id="online-stats-reset"><span class="glyphicon glyphicon-repeat" aria-hidden="true"></span> 恢复默认</button>
              <button type="submit" class="btn <?php echo isset($online_stats_feedback) ? 'btn-success' : 'btn-primary'; ?>">
                <span class="glyphicon glyphicon-floppy-disk"></span>
                <?php echo isset($online_stats_feedback) ? '设置已保存' : '保存在线统计设置'; ?>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- 页脚与备案设置 -->
      <div class="panel panel-default" id="footer-settings">
        <div class="panel-heading">
          <h3 class="panel-title"><span class="glyphicon glyphicon-list-alt art-title-glyph" aria-hidden="true"></span>页脚与备案设置</h3>
        </div>
        <div class="panel-body" style="padding:20px;">
          <form action="./set.php" method="post">
            <?php echo qifu_csrf_input(); ?>
            <input type="hidden" name="footer_settings" value="1">
            <div class="form-group">
              <label>版权文字</label>
              <input type="text" name="footer_text" value="<?php echo htmlspecialchars($footer_text); ?>" class="form-control" placeholder="如：祈福导航系统 · 精选优质资源">
            </div>
            <div class="row">
              <div class="col-sm-5">
                <div class="form-group">
                  <label>底部链接文字</label>
                  <input type="text" name="footer_link_text" value="<?php echo htmlspecialchars($footer_link_text); ?>" class="form-control" placeholder="如：关于本站">
                </div>
              </div>
              <div class="col-sm-7">
                <div class="form-group">
                  <label>底部链接地址</label>
                  <input type="text" name="footer_link" value="<?php echo htmlspecialchars($footer_link); ?>" class="form-control" placeholder="https://example.com">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>ICP备案号</label>
                  <input type="text" name="icp" value="<?php echo htmlspecialchars($icp); ?>" class="form-control" maxlength="80" placeholder="如：豫ICP备2025000000号-1">
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label>公安网安备案号</label>
                  <input type="text" name="gongan_beian" value="<?php echo htmlspecialchars($gongan_beian); ?>" class="form-control" maxlength="80" placeholder="如：豫公网安备 41000000000000号">
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>公安网安备案查询链接</label>
              <input type="url" name="gongan_beian_url" value="<?php echo htmlspecialchars($gongan_beian_url); ?>" class="form-control" placeholder="可留空，将根据备案号自动生成官方查询链接">
              <span class="help-block">仅接受 HTTPS 链接；留空时自动链接公安部互联网安全管理服务平台。</span>
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>底部透明度（5-100）</label>
                  <input type="number" name="footer_opacity" value="<?php echo $footer_opacity; ?>" class="form-control" min="5" max="100">
                  <span class="help-block">数值越小越透明，默认 25</span>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label>底部文字大小（10-18px）</label>
                  <input type="number" name="footer_size" value="<?php echo $footer_size; ?>" class="form-control" min="10" max="18">
                  <span class="help-block">默认 12px</span>
                </div>
              </div>
            </div>
            <div class="alert alert-info">
              <span class="glyphicon glyphicon-info-sign"></span> ICP 备案默认链接工信部，公安网安备案默认链接公安部查询平台；对应备案号留空则不显示
            </div>
            <div class="qf-form-actions"><button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>保存页脚设置</button></div>
          </form>
        </div>
      </div>

      <!-- 邮件通知设置 -->
      <div class="panel panel-danger" id="mail-settings">
        <div class="panel-heading">
          <h3 class="panel-title"><span class="glyphicon glyphicon-envelope art-title-glyph" aria-hidden="true"></span>邮件通知设置</h3>
        </div>
        <div class="panel-body" style="padding:20px;">
          <form action="./set.php" method="post">
            <?php echo qifu_csrf_input(); ?>
            <input type="hidden" name="mail_settings" value="1">
            <div class="row">
              <div class="col-sm-4">
                <div class="form-group">
                  <label>开启邮件通知</label>
                  <select name="mail_enabled" class="form-control">
                    <option value="0" <?php echo $mail_enabled=='0'?'selected':''; ?>>关闭</option>
                    <option value="1" <?php echo $mail_enabled=='1'?'selected':''; ?>>开启</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-8">
                <div class="form-group">
                  <label>接收通知邮箱</label>
                  <input type="email" name="mail_to" value="<?php echo htmlspecialchars($mail_to); ?>" class="form-control" placeholder="管理员工箱地址">
                </div>
              </div>
            </div>
            <div class="alert alert-info" style="margin:10px 0;">
              <h4 class="qf-alert-title"><span class="glyphicon glyphicon-envelope" aria-hidden="true"></span>QQ邮箱 SMTP 设置教程</h4>
              <ol style="margin:0;padding-left:18px;font-size:13px;line-height:1.8;">
                <li>登录 <a href="https://mail.qq.com" target="_blank">mail.qq.com</a> → 设置 → 账户</li>
                <li>开启 <b>POP3/SMTP服务</b></li>
                <li>生成 <b>授权码</b>（不是QQ密码！）</li>
                <li>将授权码填入下方"邮箱密码"栏</li>
                <li>SMTP服务器填 <code>smtp.qq.com</code>，端口填 <code>587</code></li>
              </ol>
            </div>
            <div class="alert alert-warning">
              <span class="glyphicon glyphicon-bell"></span>
              此邮件配置同时用于友链审核通知和站点红灯提醒；当 Ping 检测发现站点无法访问或延迟过高变红时，系统会每日发送一次汇总邮件。
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>SMTP服务器</label>
                  <input type="text" name="mail_host" value="<?php echo htmlspecialchars($mail_host); ?>" class="form-control" placeholder="smtp.qq.com">
                </div>
              </div>
              <div class="col-sm-3">
                <div class="form-group">
                  <label>SMTP端口</label>
                  <input type="number" name="mail_port" value="<?php echo htmlspecialchars($mail_port); ?>" class="form-control" placeholder="587">
                </div>
              </div>
              <div class="col-sm-3">
                <div class="form-group">
                  <label>发件人名称</label>
                  <input type="text" name="mail_sender" value="<?php echo htmlspecialchars($mail_sender); ?>" class="form-control" placeholder="友链系统">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>发件邮箱</label>
                  <input type="email" name="mail_user" value="<?php echo htmlspecialchars($mail_user); ?>" class="form-control" placeholder="123456@qq.com">
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label>邮箱密码/授权码</label>
                  <input type="password" name="mail_pass" value="<?php echo htmlspecialchars($mail_pass); ?>" class="form-control" placeholder="填授权码，不是QQ密码">
                </div>
              </div>
            </div>
            <div class="qf-form-actions">
              <button type="button" class="btn btn-default" id="testMailBtn"><span class="glyphicon glyphicon-send" aria-hidden="true"></span>发送测试邮件</button>
              <button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>保存邮件设置</button>
            </div>
          </form>
          <div id="testMailMsg" style="margin-top:10px;"></div>
        </div>
      </div>

    </div>
</div>

<script>
$("input[name='ad_settings']").closest(".panel").remove();
var items = $("select[default]");
for (i = 0; i < items.length; i++) {
    $(items[i]).val($(items[i]).attr("default")||0);
}
function toggleBgOpts(mode){
    document.getElementById('customBgOpts').style.display = (mode=='custom')?'block':'none';
    document.getElementById('bingOpts').style.display = (mode=='bing')?'block':'none';
    if(window.qfUpdateBackgroundPreview) window.qfUpdateBackgroundPreview(mode);
}

(function(){
    var nav = document.getElementById('settingsShortcuts');
    if(!nav) return;
    var sectionMap = {
        'base_settings': 'basic-settings',
        'bg_settings': 'background-settings',
        'ui_settings': 'ui-settings',
        'media_settings': 'media-settings',
        'online_stats_settings': 'online-stats-settings',
        'footer_settings': 'footer-settings',
        'mail_settings': 'mail-settings'
    };
    Object.keys(sectionMap).forEach(function(formName){
        var input = document.querySelector('input[name="' + formName + '"]');
        var panel = input ? input.closest('.panel') : null;
        if(panel){
            panel.id = sectionMap[formName];
            var form = input.closest('form');
            if(form) form.setAttribute('action', './set.php#' + sectionMap[formName]);
        }
    });
    var links = Array.prototype.slice.call(nav.querySelectorAll('[data-setting-target]'));
    var sections = links.map(function(link){
        var section = document.getElementById(link.getAttribute('data-setting-target'));
        if(section) section.classList.add('qf-settings-panel');
        return section;
    }).filter(Boolean);
    function activate(id, updateUrl){
        var matched = false;
        links.forEach(function(link){
            var active = link.getAttribute('data-setting-target') === id;
            link.classList.toggle('is-active', active);
            if(active) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
            if(active) matched = true;
        });
        if(!matched && sections.length) id = sections[0].id;
        sections.forEach(function(section){
            var active = section.id === id;
            section.classList.toggle('qf-settings-panel-active', active);
            section.hidden = !active;
        });
        if(updateUrl && window.history && window.history.replaceState){
            window.history.replaceState(null, '', '#' + id);
        }
    }
    links.forEach(function(link){
        link.addEventListener('click', function(event){
            event.preventDefault();
            activate(link.getAttribute('data-setting-target'), true);
        });
    });
    var hash = window.location.hash ? window.location.hash.substring(1) : '';
    activate(hash && document.getElementById(hash) ? hash : 'basic-settings', false);
    document.body.classList.add('qf-settings-tabs-ready');
}());

(function(){
    var editor = document.getElementById('quickTagsEditor');
    var list = document.getElementById('quickTagList');
    var empty = document.getElementById('quickTagsEmpty');
    var addButton = document.getElementById('addQuickTagBtn');
    if(!editor || !list || !empty || !addButton) return;
    var maxTags = parseInt(editor.getAttribute('data-max-tags'), 10) || 12;

    function refreshQuickTags(){
        var count = list.querySelectorAll('.qf-quick-tag-row').length;
        empty.style.display = count === 0 ? 'block' : 'none';
        addButton.disabled = count >= maxTags;
        addButton.title = count >= maxTags ? '最多只能添加 ' + maxTags + ' 个标签' : '';
    }

    function createQuickTagRow(){
        var row = document.createElement('div');
        row.className = 'qf-quick-tag-row';
        row.innerHTML = '<div class="qf-quick-tag-field"><label>标签名称</label><input type="text" name="quick_tag_name[]" class="form-control" maxlength="30" required placeholder="如：开发资源"></div>' +
            '<div class="qf-quick-tag-field"><label>跳转链接</label><input type="url" name="quick_tag_url[]" class="form-control" maxlength="500" required placeholder="https://example.com"></div>' +
            '<button type="button" class="btn btn-default qf-quick-tag-remove" title="删除标签" aria-label="删除标签"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></button>';
        list.appendChild(row);
        row.querySelector('input').focus();
        refreshQuickTags();
    }

    addButton.addEventListener('click', function(){
        if(list.querySelectorAll('.qf-quick-tag-row').length < maxTags) createQuickTagRow();
    });

    list.addEventListener('click', function(event){
        var button = event.target.closest('.qf-quick-tag-remove');
        if(!button) return;
        var row = button.closest('.qf-quick-tag-row');
        if(row) row.remove();
        refreshQuickTags();
    });

    refreshQuickTags();
}());

(function(){
    var nameInput = document.getElementById('qfSiteNameInput');
    var logoUrlInput = document.getElementById('qfSiteLogoUrl');
    var logoFileInput = document.getElementById('qfSiteLogoFile');
    var previewImage = document.getElementById('qfSiteLogoPreviewImage');
    var previewFallback = document.getElementById('qfSiteLogoPreviewFallback');
    var previewName = document.getElementById('qfSiteLogoPreviewName');
    var status = document.getElementById('qfSiteLogoStatus');
    if(!nameInput || !logoUrlInput || !logoFileInput || !previewImage || !previewFallback || !previewName || !status) return;

    var objectUrl = '';
    var previewSequence = 0;
    var defaultStatus = '支持 JPG、PNG、GIF、WEBP；选择图片后会在右侧实时预览。';

    function currentName(){
        var value = String(nameInput.value || '').trim();
        return value || '网站名称';
    }

    function firstCharacter(value){
        var characters = Array.from(String(value || '').trim());
        return characters.length ? characters[0] : '站';
    }

    function updateNamePreview(){
        var value = currentName();
        previewName.textContent = value;
        previewFallback.textContent = firstCharacter(value);
    }

    function showFallback(message){
        previewSequence++;
        previewImage.onload = null;
        previewImage.onerror = null;
        previewImage.removeAttribute('src');
        previewImage.style.display = 'none';
        previewFallback.style.display = 'inline-flex';
        if(message) status.textContent = message;
    }

    function showImage(source, successMessage){
        source = String(source || '').trim();
        if(!source){
            previewImage.removeAttribute('src');
            showFallback('未设置 LOGO，将使用网站名称首字。');
            return;
        }
        var requestSequence = ++previewSequence;
        previewImage.onload = function(){
            if(requestSequence !== previewSequence) return;
            previewFallback.style.display = 'none';
            previewImage.style.display = 'block';
            status.textContent = successMessage || 'LOGO 预览已更新，保存设置后生效。';
        };
        previewImage.onerror = function(){
            if(requestSequence !== previewSequence) return;
            showFallback('图片无法加载，请检查地址或重新选择图片。');
        };
        previewImage.src = source;
    }

    nameInput.addEventListener('input', updateNamePreview);
    logoUrlInput.addEventListener('input', function(){
        if(logoFileInput.value) logoFileInput.value = '';
        if(objectUrl){
            URL.revokeObjectURL(objectUrl);
            objectUrl = '';
        }
        showImage(logoUrlInput.value);
    });
    logoFileInput.addEventListener('change', async function(){
        var file = logoFileInput.files && logoFileInput.files[0];
        if(!file){
            showImage(logoUrlInput.value);
            status.textContent = defaultStatus;
            return;
        }
        if(['image/jpeg','image/png','image/gif','image/webp'].indexOf(file.type) === -1){
            logoFileInput.value = '';
            showImage(logoUrlInput.value);
            status.textContent = '请选择 JPG、PNG、GIF 或 WEBP 图片。';
            return;
        }
        if(objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        showImage(objectUrl, '正在上传：' + file.name + '…');

        var form = logoFileInput.closest('form');
        var csrfInput = form ? form.querySelector('input[name="_csrf"]') : null;
        var submitButton = form ? form.querySelector('button[type="submit"]') : null;
        if(!csrfInput){
            status.textContent = '安全令牌缺失，请刷新页面后重新选择图片。';
            return;
        }

        var uploadData = new FormData();
        uploadData.append('file', file);
        if(submitButton) submitButton.disabled = true;
        try {
            var response = await fetch('./api.php?action=logo_upload', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-Token': csrfInput.value },
                body: uploadData
            });
            var json = await response.json();
            if(!response.ok || !json || json.code !== 200 || !json.data || !json.data.url){
                throw new Error(json && json.msg ? json.msg : 'LOGO 上传失败');
            }
            logoUrlInput.value = json.data.url;
            logoUrlInput.dispatchEvent(new Event('input', { bubbles: true }));
            logoFileInput.value = '';
            if(objectUrl){
                URL.revokeObjectURL(objectUrl);
                objectUrl = '';
            }
            showImage(json.data.url, '上传成功，文件地址已回填；保存基本设置后全站生效。');
        } catch(error) {
            status.textContent = error && error.message ? error.message + '；可重新选择图片。' : 'LOGO 上传失败，请重新选择图片。';
        } finally {
            if(submitButton) submitButton.disabled = false;
        }
    });
    window.addEventListener('beforeunload', function(){
        if(objectUrl) URL.revokeObjectURL(objectUrl);
    });

    updateNamePreview();
    showImage(logoUrlInput.value, defaultStatus);
}());

(function(){
    var modeInput = document.getElementById('qfBackgroundMode');
    var urlInput = document.getElementById('qfBackgroundUrl');
    var fileInput = document.getElementById('qfBackgroundFile');
    var previewImage = document.getElementById('qfBackgroundPreviewImage');
    var previewEmpty = document.getElementById('qfBackgroundPreviewEmpty');
    var previewText = document.getElementById('qfBackgroundPreviewText');
    var previewBadge = document.getElementById('qfBackgroundPreviewBadge');
    var uploadStatus = document.getElementById('qfBackgroundUploadStatus');
    if(!modeInput || !urlInput || !fileInput || !previewImage || !previewEmpty || !previewText || !previewBadge || !uploadStatus) return;

    var defaultImage = <?php echo json_encode($default_bg_preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var fileReadSequence = 0;
    var previewSequence = 0;
    var filePreviewActive = false;
    var defaultUploadStatus = '支持 JPG、PNG、GIF、WEBP；选择图片后会立即显示预览。';

    function setMeta(badge, text){
        previewBadge.textContent = badge;
        previewText.textContent = text;
    }

    function showEmpty(message){
        previewSequence++;
        previewImage.onload = null;
        previewImage.onerror = null;
        previewImage.removeAttribute('src');
        previewImage.hidden = true;
        previewEmpty.hidden = false;
        if(message) previewEmpty.querySelector('small').textContent = message;
    }

    function showImage(source, fallbackSource){
        source = String(source || '').trim();
        if(!source){
            showEmpty('请填写可访问的图片地址或选择本地图片');
            return;
        }
        var requestSequence = ++previewSequence;
        previewImage.onload = function(){
            if(requestSequence !== previewSequence) return;
            previewEmpty.hidden = true;
            previewImage.hidden = false;
        };
        previewImage.onerror = function(){
            if(requestSequence !== previewSequence) return;
            if(fallbackSource && source !== fallbackSource){
                showImage(fallbackSource, '');
                return;
            }
            showEmpty('图片加载失败，请检查地址或重新选择图片');
        };
        previewImage.src = source;
    }

    function updatePreview(mode){
        mode = mode || modeInput.value;
        if(mode === 'custom'){
            setMeta('自定义背景', filePreviewActive ? '正在预览本次选择的本地图片，保存后生效' : '当前使用自定义背景图片');
            if(!filePreviewActive) showImage(urlInput.value, defaultImage);
            return;
        }
        if(mode === 'bing'){
            setMeta('必应每日更新', '前台每日自动获取必应壁纸，获取失败时使用默认背景');
            showImage(defaultImage, '');
            return;
        }
        setMeta('默认背景', '当前使用系统内置默认背景');
        showImage(defaultImage, '');
    }

    window.qfUpdateBackgroundPreview = updatePreview;

    modeInput.addEventListener('change', function(){
        updatePreview(modeInput.value);
    });
    urlInput.addEventListener('input', function(){
        fileReadSequence++;
        filePreviewActive = false;
        if(fileInput.value) fileInput.value = '';
        uploadStatus.textContent = defaultUploadStatus;
        if(modeInput.value === 'custom') updatePreview('custom');
    });
    fileInput.addEventListener('change', function(){
        var file = fileInput.files && fileInput.files[0];
        var readSequence = ++fileReadSequence;
        filePreviewActive = false;
        if(!file){
            uploadStatus.textContent = defaultUploadStatus;
            updatePreview(modeInput.value);
            return;
        }
        if(['image/jpeg','image/png','image/gif','image/webp'].indexOf(file.type) === -1){
            fileInput.value = '';
            uploadStatus.textContent = '请选择 JPG、PNG、GIF 或 WEBP 图片。';
            updatePreview(modeInput.value);
            return;
        }
        filePreviewActive = true;
        uploadStatus.textContent = '已选择：' + file.name + '，保存背景设置后上传。';
        if(modeInput.value === 'custom'){
            setMeta('自定义背景', '正在预览本次选择的本地图片，保存后生效');
        }
        var reader = new FileReader();
        reader.onload = function(event){
            if(readSequence !== fileReadSequence || !filePreviewActive || modeInput.value !== 'custom') return;
            var dataUrl = event && event.target ? String(event.target.result || '') : '';
            if(!dataUrl){
                uploadStatus.textContent = '无法读取所选图片，请重新选择。';
                showEmpty('图片读取失败，请重新选择图片');
                return;
            }
            showImage(dataUrl, '');
        };
        reader.onerror = function(){
            if(readSequence !== fileReadSequence || !filePreviewActive || modeInput.value !== 'custom') return;
            uploadStatus.textContent = '无法读取所选图片，请重新选择。';
            showEmpty('图片读取失败，请重新选择图片');
        };
        reader.readAsDataURL(file);
    });

    updatePreview(modeInput.value);
}());

document.getElementById('testMailBtn').addEventListener('click', function(){
    var btn = this;
    var msg = document.getElementById('testMailMsg');
    btn.disabled = true;
    btn.textContent = '发送中...';
    function showMailMessage(type, text){
        msg.className = 'alert alert-' + type;
        msg.style.margin = '0';
        msg.textContent = text;
    }
    showMailMessage('info', '正在连接邮箱服务器...');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax_test_mail.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function(){
        btn.disabled = false;
        btn.innerHTML = '<span class="glyphicon glyphicon-send" aria-hidden="true"></span>发送测试邮件';
        var res;
        try {
            res = JSON.parse(xhr.responseText);
        } catch (e) {
            var raw = xhr.responseText ? xhr.responseText.replace(/<[^>]*>/g, '').trim() : '';
            showMailMessage('danger', '服务器返回格式错误：' + (raw ? raw.substring(0, 160) : '空响应'));
            return;
        }
        if(res.code == 1){
            showMailMessage('success', String(res.msg || '发送成功'));
        } else {
            showMailMessage('danger', '发送失败：' + String(res.msg || '未知错误'));
        }
    };
    xhr.onerror = function(){
        btn.disabled = false;
        btn.innerHTML = '<span class="glyphicon glyphicon-send" aria-hidden="true"></span>发送测试邮件';
        showMailMessage('danger', '网络错误，请检查配置');
    };
    var form = btn.form;
    var data = [
        'mail_enabled=' + encodeURIComponent(form.mail_enabled.value),
        'mail_to=' + encodeURIComponent(form.mail_to.value),
        'mail_host=' + encodeURIComponent(form.mail_host.value),
        'mail_port=' + encodeURIComponent(form.mail_port.value),
        'mail_sender=' + encodeURIComponent(form.mail_sender.value),
        'mail_user=' + encodeURIComponent(form.mail_user.value),
        'mail_pass=' + encodeURIComponent(form.mail_pass.value),
        '_csrf=' + encodeURIComponent(document.querySelector('meta[name="qifu-csrf"]').getAttribute('content'))
    ].join('&');
    xhr.send(data);
});

(function(){
    var mode = document.querySelector('select[name="online_stats_mode"]');
    var options = document.getElementById('onlineStatsRandomOptions');
    var ruleFields = document.getElementById('onlineStatsRuleFields');
    var form = document.getElementById('onlineStatsForm');
    var resetButton = document.getElementById('online-stats-reset');
    if(!mode || !options || !ruleFields) return;
    var schemes = Array.prototype.slice.call(document.querySelectorAll('input[name="online_stats_random_scheme"]'));
    function selectedScheme(){
        var selected = schemes.filter(function(input){ return input.checked; })[0];
        return selected ? selected.value : 'builtin';
    }
    function sync(){
        var random = mode.value === 'random';
        var rule = selectedScheme() === 'rule';
        options.hidden = !random;
        options.setAttribute('aria-hidden', random ? 'false' : 'true');
        ruleFields.hidden = !random || !rule;
        schemes.forEach(function(input){
            var label = input.closest('.online-stats-scheme');
            if(label) label.classList.toggle('active', input.checked);
        });
    }
    mode.addEventListener('change', sync);
    schemes.forEach(function(input){ input.addEventListener('change', sync); });
    if(form && resetButton){
        resetButton.addEventListener('click', function(){
            form.elements.online_stats_enabled.value = '1';
            mode.value = 'real';
            form.elements.online_stats_color.value = 'highlight';
            schemes.forEach(function(input){ input.checked = input.value === 'builtin'; });
            form.elements.online_stats_random_active_min.value = '1';
            form.elements.online_stats_random_active_max.value = '8';
            form.elements.online_stats_random_today_min.value = '8';
            form.elements.online_stats_random_today_max.value = '36';
            form.elements.online_stats_random_trend.value = 'steady';
            form.elements.online_stats_random_start_date.value = form.elements.online_stats_random_start_date.max;
            form.elements.online_stats_random_base_visits.value = '5000';
            form.elements.online_stats_random_stable.checked = true;
            form.elements.online_stats_privacy_ip.checked = false;
            sync();
        });
    }
    sync();
}());
</script>
