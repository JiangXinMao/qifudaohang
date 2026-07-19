<?php
/* 祈福导航系统 V1.5 官方开源：https://github.com/JiangXinMao/qifudaohang */

include __DIR__ . "/../includes/common.php";
$title = '关于我们';
if($islogin != 1){
    @header('Location: ./login.php');
    exit;
}

$official_site = defined('QIFU_OFFICIAL_SITE') ? QIFU_OFFICIAL_SITE : 'https://github.com/JiangXinMao/qifudaohang';
$about_product_title = preg_replace('/^祈福/u', '', QIFU_PRODUCT_NAME);
if($about_product_title === '') $about_product_title = QIFU_PRODUCT_NAME;
include __DIR__.'/head.php';
?>
<div class="container qf-status-page" style="padding-top:70px;">
<div class="qf-detail-content center-block">

<section class="qf-status-hero" id="qfServerStatus">
  <div class="qf-status-hero-main">
    <span class="qf-status-kicker">About Us</span>
    <h2><?php echo htmlspecialchars($about_product_title); ?> · 关于我们</h2>
    <p>祈福导航是一套简洁、高效的网址导航与友链管理程序，适合资源导航、内部工具导航、个人收藏站和企业导航页使用；本页同时提供服务器实时运营状态，便于维护时快速判断系统健康情况。</p>
    <div class="qf-status-hero-tags">
      <span><i></i><?php echo QIFU_PRODUCT_VERSION; ?> 正式版</span>
      <a class="qf-status-official-link" href="<?php echo htmlspecialchars($official_site); ?>" target="_blank" rel="noopener" aria-label="打开官方网站">官网</a>
      <span data-status-health>正在检测</span>
      <span>自动刷新 <b data-status-refresh>5</b>s</span>
    </div>
  </div>
  <div class="qf-status-hero-side">
    <b data-status-clock>--:--:--</b>
    <span>最后更新：<em data-status-updated>等待数据</em></span>
    <button type="button" class="btn btn-default btn-sm" id="qfStatusRefresh">
      <span class="glyphicon glyphicon-refresh"></span> 立即刷新
    </button>
  </div>
</section>

<div class="qf-status-grid qf-status-grid-primary">
  <div class="qf-status-card">
    <div class="qf-status-card-head">
      <span class="qf-status-icon cpu"><span class="glyphicon glyphicon-dashboard"></span></span>
      <div><b>CPU 负载</b><small data-status-cpu-sub>等待采集</small></div>
    </div>
    <strong data-status-cpu>--%</strong>
    <div class="qf-status-bar"><i data-status-bar="cpu"></i></div>
  </div>
  <div class="qf-status-card">
    <div class="qf-status-card-head">
      <span class="qf-status-icon memory"><span class="glyphicon glyphicon-tasks"></span></span>
      <div><b>内存占用</b><small data-status-memory-sub>等待采集</small></div>
    </div>
    <strong data-status-memory>--%</strong>
    <div class="qf-status-bar"><i data-status-bar="memory"></i></div>
  </div>
  <div class="qf-status-card">
    <div class="qf-status-card-head">
      <span class="qf-status-icon disk"><span class="glyphicon glyphicon-hdd"></span></span>
      <div><b>硬盘空间</b><small data-status-disk-sub>等待采集</small></div>
    </div>
    <strong data-status-disk>--%</strong>
    <div class="qf-status-bar"><i data-status-bar="disk"></i></div>
  </div>
  <div class="qf-status-card">
    <div class="qf-status-card-head">
      <span class="qf-status-icon gpu"><span class="glyphicon glyphicon-modal-window"></span></span>
      <div><b>GPU 状态</b><small data-status-gpu-sub>等待采集</small></div>
    </div>
    <strong data-status-gpu>--</strong>
    <div class="qf-status-bar"><i data-status-bar="gpu"></i></div>
  </div>
</div>

<div class="row qf-status-row">
  <div class="col-md-8">
    <div class="panel panel-default qf-status-panel">
      <div class="panel-heading">
        <h3 class="panel-title">资源实时曲线</h3>
      </div>
      <div class="panel-body">
        <div class="qf-status-chart" id="qfStatusChart">
          <svg viewBox="0 0 760 220" preserveAspectRatio="none" aria-label="资源实时曲线">
            <defs>
              <linearGradient id="qfStatusFill" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stop-color="#22c55e" stop-opacity=".22"></stop>
                <stop offset="100%" stop-color="#22c55e" stop-opacity="0"></stop>
              </linearGradient>
            </defs>
            <g class="qf-chart-grid">
              <line x1="34" y1="36" x2="736" y2="36"></line>
              <line x1="34" y1="82" x2="736" y2="82"></line>
              <line x1="34" y1="128" x2="736" y2="128"></line>
              <line x1="34" y1="174" x2="736" y2="174"></line>
            </g>
            <polygon data-chart-area points=""></polygon>
            <polyline data-chart-cpu points=""></polyline>
            <polyline data-chart-memory points=""></polyline>
            <polyline data-chart-disk points=""></polyline>
          </svg>
          <div class="qf-status-legend">
            <span><i class="cpu"></i>CPU</span>
            <span><i class="memory"></i>内存</span>
            <span><i class="disk"></i>硬盘</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="panel panel-default qf-status-panel">
      <div class="panel-heading">
        <h3 class="panel-title">运行环境</h3>
      </div>
      <ul class="list-group qf-status-list">
        <li class="list-group-item"><b>操作系统</b><span data-status-os>--</span></li>
        <li class="list-group-item"><b>服务器软件</b><span data-status-server>--</span></li>
        <li class="list-group-item"><b>PHP 版本</b><span data-status-php>--</span></li>
        <li class="list-group-item"><b>数据库</b><span data-status-db>--</span></li>
        <li class="list-group-item"><b>搭建时长</b><span data-status-uptime>--</span></li>
        <li class="list-group-item"><b>上传限制</b><span data-status-upload>--</span></li>
      </ul>
    </div>
  </div>
</div>

<div class="row qf-status-row">
  <div class="col-md-8">
    <div class="panel panel-default qf-status-panel">
      <div class="panel-heading">
        <h3 class="panel-title">站点运营数据</h3>
      </div>
      <div class="panel-body">
        <div class="qf-status-mini-grid">
          <div><strong data-status-sites-total>0</strong><span>站点总数</span></div>
          <div><strong data-status-sites-active>0</strong><span>显示站点</span></div>
          <div><strong data-status-categories>0</strong><span>分类数量</span></div>
          <div><strong data-status-today-clicks>0</strong><span>今日点击</span></div>
          <div><strong data-status-total-clicks>0</strong><span>累计点击</span></div>
          <div><strong data-status-pending-links>0</strong><span>待审友链</span></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="panel panel-default qf-status-panel">
      <div class="panel-heading">
        <h3 class="panel-title">进程信息</h3>
      </div>
      <ul class="list-group qf-status-list">
        <li class="list-group-item"><b>PHP 内存</b><span data-status-process-memory>--</span></li>
        <li class="list-group-item"><b>内存上限</b><span data-status-memory-limit>--</span></li>
        <li class="list-group-item"><b>站点根目录</b><span data-status-root>--</span></li>
        <li class="list-group-item"><b>今日日志</b><span data-status-today-logs>--</span></li>
      </ul>
    </div>
  </div>
</div>

</div>
</div>

<script>
(function(){
  if(window.__qfServerStatusTimer){
    clearInterval(window.__qfServerStatusTimer);
    window.__qfServerStatusTimer = null;
  }

  var root = document.getElementById('qfServerStatus');
  if(!root) return;

  var history = [];
  var maxPoints = 20;
  var refreshing = false;
  var refreshSeconds = 5;

  function pick(path, data, fallback){
    var parts = path.split('.');
    var value = data;
    for(var i = 0; i < parts.length; i++){
      if(value == null || typeof value !== 'object' || !(parts[i] in value)) return fallback;
      value = value[parts[i]];
    }
    return value == null || value === '' ? fallback : value;
  }

  function text(selector, value){
    var el = document.querySelector(selector);
    if(el) el.textContent = value == null || value === '' ? '--' : value;
  }

  function bar(name, value){
    var el = document.querySelector('[data-status-bar="' + name + '"]');
    var num = parseFloat(value);
    if(!el || isNaN(num)) return;
    num = Math.max(0, Math.min(100, num));
    el.style.width = num + '%';
    el.className = num >= 90 ? 'danger' : (num >= 75 ? 'warning' : '');
  }

  function numberText(value){
    var num = parseInt(value, 10);
    if(isNaN(num)) return '0';
    return String(num).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function updateHealth(data){
    var cpu = parseFloat(pick('cpu.percent', data, 0)) || 0;
    var memory = parseFloat(pick('memory.percent', data, 0)) || 0;
    var disk = parseFloat(pick('disk.percent', data, 0)) || 0;
    var max = Math.max(cpu, memory, disk);
    var label = max >= 90 ? '需要关注' : (max >= 75 ? '负载偏高' : '运行正常');
    var el = document.querySelector('[data-status-health]');
    if(el){
      el.textContent = label;
      el.className = max >= 90 ? 'is-danger' : (max >= 75 ? 'is-warning' : 'is-ok');
    }
  }

  function drawChart(data){
    var cpu = parseFloat(pick('cpu.percent', data, 0)) || 0;
    var memory = parseFloat(pick('memory.percent', data, 0)) || 0;
    var disk = parseFloat(pick('disk.percent', data, 0)) || 0;
    history.push({cpu: cpu, memory: memory, disk: disk});
    if(history.length > maxPoints) history.shift();

    var width = 760, top = 24, bottom = 190, left = 34, right = 24;
    var plotW = width - left - right;
    function points(key){
      if(history.length === 1){
        var ySingle = bottom - (Math.max(0, Math.min(100, history[0][key])) / 100) * (bottom - top);
        return left + ',' + ySingle;
      }
      var out = [];
      for(var i = 0; i < history.length; i++){
        var x = left + (i * plotW / (maxPoints - 1));
        var val = Math.max(0, Math.min(100, history[i][key]));
        var y = bottom - (val / 100) * (bottom - top);
        out.push(Math.round(x * 100) / 100 + ',' + Math.round(y * 100) / 100);
      }
      return out.join(' ');
    }
    var cpuPoints = points('cpu');
    var area = '';
    if(cpuPoints){
      var firstX = left;
      var lastX = left + ((history.length - 1) * plotW / (maxPoints - 1));
      area = firstX + ',' + bottom + ' ' + cpuPoints + ' ' + lastX + ',' + bottom;
    }
    var cpuEl = document.querySelector('[data-chart-cpu]');
    var memEl = document.querySelector('[data-chart-memory]');
    var diskEl = document.querySelector('[data-chart-disk]');
    var areaEl = document.querySelector('[data-chart-area]');
    if(cpuEl) cpuEl.setAttribute('points', cpuPoints);
    if(memEl) memEl.setAttribute('points', points('memory'));
    if(diskEl) diskEl.setAttribute('points', points('disk'));
    if(areaEl) areaEl.setAttribute('points', area);
  }

  function applyData(data){
    text('[data-status-clock]', pick('generated_time', data, '--'));
    text('[data-status-updated]', pick('generated_at', data, '--'));
    text('[data-status-refresh]', refreshSeconds);

    var cpuPercent = pick('cpu.percent', data, null);
    text('[data-status-cpu]', cpuPercent === null ? '--' : cpuPercent + '%');
    text('[data-status-cpu-sub]', pick('cpu.label', data, '负载数据不可用'));
    bar('cpu', cpuPercent);

    var memoryPercent = pick('memory.percent', data, null);
    text('[data-status-memory]', memoryPercent === null ? '--' : memoryPercent + '%');
    text('[data-status-memory-sub]', pick('memory.used_human', data, '--') + ' / ' + pick('memory.total_human', data, '--'));
    bar('memory', memoryPercent);

    var diskPercent = pick('disk.percent', data, null);
    text('[data-status-disk]', diskPercent === null ? '--' : diskPercent + '%');
    text('[data-status-disk-sub]', pick('disk.used_human', data, '--') + ' / ' + pick('disk.total_human', data, '--'));
    bar('disk', diskPercent);

    var gpuPercent = pick('gpu.percent', data, null);
    text('[data-status-gpu]', gpuPercent === null ? pick('gpu.state', data, '--') : gpuPercent + '%');
    text('[data-status-gpu-sub]', pick('gpu.label', data, '未检测到 GPU 指标'));
    bar('gpu', gpuPercent === null ? 0 : gpuPercent);

    text('[data-status-os]', pick('server.os', data, '--'));
    text('[data-status-server]', pick('server.software', data, '--'));
    text('[data-status-php]', pick('server.php_version', data, '--'));
    text('[data-status-db]', pick('server.db_version', data, '--'));
    text('[data-status-uptime]', pick('server.uptime', data, '--'));
    text('[data-status-upload]', pick('server.upload_max', data, '--'));
    text('[data-status-process-memory]', pick('process.memory_usage_human', data, '--'));
    text('[data-status-memory-limit]', pick('process.memory_limit', data, '--'));
    text('[data-status-root]', pick('server.root_path', data, '--'));
    text('[data-status-today-logs]', numberText(pick('site.today_logs', data, 0)));

    text('[data-status-sites-total]', numberText(pick('site.total_sites', data, 0)));
    text('[data-status-sites-active]', numberText(pick('site.active_sites', data, 0)));
    text('[data-status-categories]', numberText(pick('site.categories', data, 0)));
    text('[data-status-today-clicks]', numberText(pick('site.today_clicks', data, 0)));
    text('[data-status-total-clicks]', numberText(pick('site.total_clicks', data, 0)));
    text('[data-status-pending-links]', numberText(pick('site.pending_links', data, 0)));

    updateHealth(data);
    drawChart(data);
  }

  function fetchStatus(){
    if(refreshing) return;
    if(!document.getElementById('qfServerStatus')){
      if(window.__qfServerStatusTimer) clearInterval(window.__qfServerStatusTimer);
      return;
    }
    refreshing = true;
    var btn = document.getElementById('qfStatusRefresh');
    if(btn) btn.classList.add('is-loading');
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'ajax_server_status.php?_=' + Date.now(), true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function(){
      refreshing = false;
      if(btn) btn.classList.remove('is-loading');
      var data = null;
      try { data = JSON.parse(xhr.responseText || '{}'); } catch(e) { data = null; }
      if(data && data.code == 1) applyData(data);
    };
    xhr.onerror = function(){
      refreshing = false;
      if(btn) btn.classList.remove('is-loading');
    };
    xhr.send(null);
  }

  var btn = document.getElementById('qfStatusRefresh');
  if(btn){
    btn.addEventListener('click', function(){
      fetchStatus();
    });
  }
  fetchStatus();
  window.__qfServerStatusTimer = setInterval(fetchStatus, refreshSeconds * 1000);
})();
</script>
