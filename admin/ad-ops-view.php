<?php
$ad_ops_slots = array(1 => array(), 2 => array(), 3 => array(), 4 => array());
foreach($ads_by_position['below_search'] as $ad_ops_row){
    $ad_ops_slots[max(1, min(4, intval($ad_ops_row['slot'])))][] = $ad_ops_row;
}

$ad_ops_region_aliases = array(
    'top' => 'below_search',
    'left' => 'pc_left',
    'right' => 'pc_right',
    'below_search' => 'below_search',
    'pc_left' => 'pc_left',
    'pc_right' => 'pc_right',
);
$ad_ops_requested_region = isset($_GET['region']) ? strtolower(trim((string)$_GET['region'])) : '';
$ad_ops_active_region = isset($ad_ops_region_aliases[$ad_ops_requested_region]) ? $ad_ops_region_aliases[$ad_ops_requested_region] : 'below_search';
$ad_ops_active_slot = isset($_GET['slot']) ? max(1, min(4, intval($_GET['slot']))) : 0;
if($ad_ops_active_slot < 1){
    foreach($slot_labels as $ad_ops_slot_value => $ad_ops_slot_name){
        if(!empty($ad_ops_slots[$ad_ops_slot_value])){
            $ad_ops_active_slot = intval($ad_ops_slot_value);
            break;
        }
    }
}
if($ad_ops_active_slot < 1) $ad_ops_active_slot = 1;

$ad_ops_render_material = function($ad, $position_key, $slot_value, $meta) use ($check_images, $slot_labels) {
    $status = qifu_ad_status_text($ad);
    $ad_id = intval($ad['id']);
    $is_side = $position_key === 'pc_left' || $position_key === 'pc_right';
    $start_value = !empty($ad['start_at']) ? date('Y-m-d\\TH:i', strtotime($ad['start_at'])) : '';
    $end_value = !empty($ad['end_at']) ? date('Y-m-d\\TH:i', strtotime($ad['end_at'])) : '';
    $schedule = !empty($ad['start_at']) || !empty($ad['end_at'])
        ? (!empty($ad['start_at']) ? '自 '.$ad['start_at'].' 起' : '已设定下线时间')
        : '长期投放';
    $image_check = $check_images ? qifu_ad_check_image($ad['image']) : array(null, '');
    ?>
    <div class="ad-ops-material-group" data-material-group="<?php echo $ad_id; ?>">
      <article class="ad-ops-material-card">
        <div class="ad-ops-material-preview <?php echo $is_side ? 'is-side' : ''; ?>">
          <?php if(!empty($ad['image'])): ?>
            <img class="ad-ops-material-image" src="<?php echo htmlspecialchars($ad['image']); ?>" alt="<?php echo htmlspecialchars($ad['alt'] ?: ($ad['title'] ?: '广告素材')); ?>">
          <?php else: ?>
            <span class="glyphicon glyphicon-picture" aria-hidden="true"></span>
          <?php endif; ?>
        </div>
        <div class="ad-ops-material-name">
          <b><?php echo htmlspecialchars($ad['title'] ?: '未命名广告'); ?></b>
          <small><?php echo htmlspecialchars($ad['link'] ?: '未设置跳转链接'); ?></small>
        </div>
        <div class="ad-ops-material-status">
          <span class="ad-ops-status <?php echo htmlspecialchars($status[0]); ?>"><i></i><?php echo htmlspecialchars($status[1]); ?></span>
          <small><?php echo htmlspecialchars($schedule); ?></small>
        </div>
        <div class="ad-ops-material-rule">
          <b><?php echo $is_side ? '固定展示' : '槽位 '.qifu_ad_slot_label($slot_value); ?></b>
          <small><?php echo $is_side ? '单一素材 · 固定展示' : '四格固定展示 · 可移动到空槽位'; ?></small>
          <small data-image-dimensions>读取图片尺寸中</small>
        </div>
        <div class="ad-ops-material-actions">
          <button class="btn ad-ops-button ad-ops-button-muted" type="button" data-edit-ad="<?php echo $ad_id; ?>" aria-controls="adOpsEditor-<?php echo $ad_id; ?>" aria-expanded="false"><span class="glyphicon glyphicon-pencil"></span> 编辑</button>
          <form method="post" class="ad-ops-delete-form" data-feedback-delay="900">
            <?php echo qifu_csrf_input(); ?>
            <input type="hidden" name="action" value="delete_ad">
            <input type="hidden" name="id" value="<?php echo $ad_id; ?>">
            <button class="btn ad-ops-button ad-ops-button-danger" type="submit" title="删除素材"><span class="glyphicon glyphicon-trash"></span></button>
          </form>
        </div>
      </article>
      <div class="ad-ops-editor-drawer" id="adOpsEditor-<?php echo $ad_id; ?>" data-editor-drawer="<?php echo $ad_id; ?>" aria-hidden="true">
        <form method="post" enctype="multipart/form-data" class="ad-ops-material-form" data-feedback-delay="900">
          <?php echo qifu_csrf_input(); ?>
          <input type="hidden" name="action" value="save_ad">
          <input type="hidden" name="id" value="<?php echo $ad_id; ?>">
          <input type="hidden" name="position" value="<?php echo htmlspecialchars($position_key); ?>" class="ad-position-input">
          <input type="hidden" name="sort" value="<?php echo intval($ad['sort']); ?>">
          <input type="hidden" name="weight" value="<?php echo max(1, min(50, intval($ad['weight']))); ?>">
          <?php if($is_side): ?><input type="hidden" name="slot" value="1" class="ad-slot-input"><?php endif; ?>
          <aside class="ad-ops-editor-preview <?php echo $is_side ? 'is-side' : ''; ?>" data-image-preview>
            <b>前台裁切预览</b>
            <div><img src="<?php echo htmlspecialchars($ad['image']); ?>" alt="<?php echo htmlspecialchars($ad['alt'] ?: ($ad['title'] ?: '广告预览')); ?>"></div>
            <small><?php echo $is_side ? '宽屏下显示，窗口宽度小于等于 1440px 时自动隐藏。' : '前台会按广告位居中裁切铺满。'; ?></small>
          </aside>
          <div class="ad-ops-editor-content">
            <div class="ad-ops-field-grid">
              <label class="ad-ops-field"><span>广告标题</span><input type="text" name="title" value="<?php echo htmlspecialchars($ad['title']); ?>" class="form-control"></label>
              <label class="ad-ops-field"><span>跳转链接</span><input type="text" name="link" value="<?php echo htmlspecialchars($ad['link']); ?>" class="form-control" placeholder="https://example.com"></label>
              <?php if(!$is_side): ?><label class="ad-ops-field"><span>投放位置</span><select name="slot" class="form-control ad-slot-input" aria-label="广告位置"><?php foreach($slot_labels as $slot_option => $slot_label): ?><option value="<?php echo intval($slot_option); ?>" <?php echo intval($slot_option) === intval($slot_value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($slot_label); ?></option><?php endforeach; ?></select></label><?php endif; ?>
              <label class="ad-ops-field ad-ops-field-wide"><span>图片外链 / 上传后自动填入</span><input type="text" name="image" value="<?php echo htmlspecialchars($ad['image']); ?>" class="form-control ad-url-input" placeholder="https://example.com/ad.png"></label>
              <label class="ad-ops-field ad-ops-field-wide"><span>上传替换图片</span><input type="file" name="ad_file" class="form-control ad-upload-input" accept="image/jpeg,image/png,image/gif,image/webp"><span class="ad-upload-progress"><i></i></span><small class="ad-upload-msg" aria-live="polite"></small></label>
              <div class="ad-ops-image-note ad-ops-field-wide" data-ad-image-fit-note><b></b><span></span></div>
              <label class="ad-ops-field ad-ops-field-wide"><span>图片说明</span><input type="text" name="alt" value="<?php echo htmlspecialchars($ad['alt']); ?>" class="form-control"></label>
              <label class="ad-ops-toggle-field"><input type="checkbox" name="active" value="1" <?php echo intval($ad['active']) === 1 ? 'checked' : ''; ?>><i></i><span><b>启用广告</b><small>允许当前素材参与前台展示</small></span></label>
            </div>
            <div class="ad-ops-advanced" id="adOpsAdvanced-<?php echo $ad_id; ?>" data-advanced-panel="<?php echo $ad_id; ?>">
              <label class="ad-ops-field"><span>定时上线</span><input type="datetime-local" name="start_at" value="<?php echo $start_value; ?>" class="form-control"></label>
              <label class="ad-ops-field"><span>定时下线</span><input type="datetime-local" name="end_at" value="<?php echo $end_value; ?>" class="form-control"></label>
            </div>
            <?php if($check_images): ?><p class="ad-ops-image-diagnostic <?php echo $image_check[0] === false ? 'is-error' : ''; ?>">图片检测：<?php echo htmlspecialchars($image_check[1]); ?></p><?php endif; ?>
            <div class="ad-ops-editor-actions">
              <button class="btn ad-ops-button ad-ops-button-text" type="button" data-toggle-advanced="<?php echo $ad_id; ?>" aria-controls="adOpsAdvanced-<?php echo $ad_id; ?>" aria-expanded="false">投放时间</button>
              <span class="ad-ops-editor-spacer"></span>
              <button class="btn ad-ops-button ad-ops-button-muted" type="button" data-close-editor="<?php echo $ad_id; ?>">取消</button>
              <button class="btn ad-ops-button ad-ops-button-primary" type="submit"><span class="glyphicon glyphicon-ok"></span> 保存素材</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php
};
?>
<style>
.ad-ops-shell{--ad-ops-primary:var(--qf-art-primary,#5d87ff);--ad-ops-primary-soft:#eef3ff;--ad-ops-ink:#30324d;--ad-ops-muted:#697386;--ad-ops-border:#e4e7ed;--ad-ops-divider:#edf0f4;--ad-ops-surface:#fff;max-width:1340px!important;width:100%!important;margin:0 auto;padding:24px 20px 48px;color:var(--ad-ops-ink)}
body.qf-detail-page > .ad-ops-shell,body.qf-detail-page.qf-art-embedded > .ad-ops-shell{padding:24px 20px 48px!important}
.ad-ops-header{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:18px}.ad-ops-header h1{margin:0 0 4px;color:var(--ad-ops-ink);font-size:22px;line-height:1.25;font-weight:600}.ad-ops-header p{margin:0;color:var(--ad-ops-muted);font-size:13px;line-height:1.55}.ad-ops-header-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.ad-ops-header-actions form{margin:0}
.ad-ops-button{display:inline-flex!important;align-items:center;justify-content:center;gap:6px;min-height:36px;padding:0 12px!important;border-radius:6px!important;font-size:13px!important;font-weight:500!important;line-height:1!important;white-space:nowrap;box-shadow:none!important;transition:background-color .16s ease,border-color .16s ease,color .16s ease,transform .16s ease}.ad-ops-button:active{transform:translateY(1px)}.ad-ops-button-primary{border-color:var(--ad-ops-primary)!important;background:var(--ad-ops-primary)!important;color:#fff!important}.ad-ops-button-primary:hover,.ad-ops-button-primary:focus{border-color:#416fe9!important;background:#416fe9!important;color:#fff!important}.ad-ops-button-muted{border-color:#dfe4ec!important;background:#fff!important;color:#4d5875!important}.ad-ops-button-muted:hover,.ad-ops-button-muted:focus{border-color:#bac8df!important;background:#f7f9fd!important;color:#30324d!important}.ad-ops-button-danger{min-width:36px;padding:0!important;border-color:#ffd4d1!important;background:#fff!important;color:#c93b33!important}.ad-ops-button-danger:hover{background:#fff4f2!important}.ad-ops-button-text{border-color:transparent!important;background:transparent!important;color:var(--ad-ops-primary)!important;padding:0 4px!important}.ad-ops-button-text:hover{background:var(--ad-ops-primary-soft)!important}
.ad-ops-notice{display:flex;align-items:center;gap:8px;margin:0 0 10px;padding:11px 13px;border:1px solid #cfe7da;border-radius:6px;background:#f0faf4;color:#19734a;font-size:13px;animation:ad-ops-notice-in .28s cubic-bezier(.22,.61,.36,1) both}.ad-ops-notice.is-error{border-color:#ffd1cc;background:#fff3f1;color:#b42318}@keyframes ad-ops-notice-in{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}@media(prefers-reduced-motion:reduce){.ad-ops-notice{animation-duration:.01ms}}
.ad-ops-global{display:grid;grid-template-columns:minmax(160px,1fr) auto 1px auto minmax(300px,1fr);align-items:center;gap:20px;margin-bottom:14px;padding:15px 18px;border:1px solid var(--ad-ops-border);border-radius:8px;background:var(--ad-ops-surface)}.ad-ops-global-copy b{display:block;margin-bottom:4px;font-size:14px;font-weight:600}.ad-ops-global-copy small{display:block;color:var(--ad-ops-muted);font-size:11px;line-height:1.45}.ad-ops-global-divider{align-self:stretch;width:1px;background:var(--ad-ops-divider)}
.ad-ops-inline-toggle,.ad-ops-region-toggle,.ad-ops-toggle-field{display:flex;align-items:center;gap:9px;min-width:0;margin:0;cursor:pointer}.ad-ops-inline-toggle input,.ad-ops-region-toggle input,.ad-ops-toggle-field input{position:absolute;opacity:0;pointer-events:none}.ad-ops-inline-toggle>i,.ad-ops-region-toggle>i,.ad-ops-toggle-field>i{position:relative;display:block;flex:0 0 44px;width:44px;height:24px;border-radius:999px;background:#c0c4cc;transition:background-color .16s ease}.ad-ops-inline-toggle>i:after,.ad-ops-region-toggle>i:after,.ad-ops-toggle-field>i:after{position:absolute;top:2px;left:2px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.18);content:"";transition:transform .16s ease}.ad-ops-inline-toggle input:checked+i,.ad-ops-region-toggle input:checked+i,.ad-ops-toggle-field input:checked+i{background:var(--ad-ops-primary)}.ad-ops-inline-toggle input:checked+i:after,.ad-ops-region-toggle input:checked+i:after,.ad-ops-toggle-field input:checked+i:after{transform:translateX(20px)}.ad-ops-inline-toggle input:focus-visible+i,.ad-ops-region-toggle input:focus-visible+i,.ad-ops-toggle-field input:focus-visible+i{outline:2px solid rgba(93,135,255,.45);outline-offset:2px}.ad-ops-inline-toggle b,.ad-ops-region-toggle b,.ad-ops-toggle-field b{display:block;color:#394260;font-size:12px;line-height:1.35;font-weight:600}.ad-ops-inline-toggle small,.ad-ops-region-toggle small,.ad-ops-toggle-field small{display:block;margin-top:2px;color:var(--ad-ops-muted);font-size:10px;line-height:1.4}
.ad-ops-metrics{display:flex;align-items:center;justify-content:flex-end;gap:0;min-width:0}.ad-ops-metrics span{padding:0 13px;border-right:1px solid var(--ad-ops-divider);color:var(--ad-ops-muted);font-size:11px;white-space:nowrap}.ad-ops-metrics span:last-child{padding-right:0;border-right:0}.ad-ops-metrics b{margin-left:4px;color:#394260;font-size:12px;font-weight:600;font-variant-numeric:tabular-nums}
.ad-ops-workspace{display:grid;grid-template-columns:220px minmax(0,1fr);min-height:548px;overflow:hidden;border:1px solid var(--ad-ops-border);border-radius:8px;background:var(--ad-ops-surface)}.ad-ops-nav{padding:16px 10px;border-right:1px solid var(--ad-ops-border);background:#fbfcff}.ad-ops-nav-label{padding:0 10px 9px;color:#697386;font-size:12px;font-weight:600}.ad-ops-nav-item{--region-accent:#3568e8;--region-soft:#edf4ff;--region-border:#cfe0ff;display:flex;align-items:center;justify-content:space-between;width:100%;gap:10px;margin:4px 0;padding:10px;border:1px solid var(--region-border);border-radius:6px;background:var(--region-soft);color:#4d5875;text-align:left;transition:background-color .16s ease,border-color .16s ease,color .16s ease,box-shadow .16s ease}.ad-ops-nav-item[data-region-theme="pc_left"]{--region-accent:#16836e;--region-soft:#eef9f5;--region-border:#cce9df}.ad-ops-nav-item[data-region-theme="pc_right"]{--region-accent:#b7691d;--region-soft:#fff7eb;--region-border:#f2dcc0}.ad-ops-nav-item:hover{border-color:var(--region-accent);background:var(--region-soft)}.ad-ops-nav-item:focus-visible{outline:2px solid var(--region-accent);outline-offset:2px}.ad-ops-nav-item.is-active{border-color:var(--region-accent);background:var(--region-soft);color:var(--region-accent);box-shadow:inset 3px 0 0 var(--region-accent)}.ad-ops-nav-item strong,.ad-ops-nav-item small{display:block}.ad-ops-nav-item strong{font-size:13px;font-weight:600;line-height:1.35}.ad-ops-nav-item small{margin-top:2px;color:#7a8499;font-size:11px;line-height:1.35}.ad-ops-nav-item.is-active small{color:var(--region-accent)}.ad-ops-nav-badge{min-width:30px;padding:3px 6px;border-radius:999px;background:rgba(255,255,255,.72);color:var(--region-accent);text-align:center;font-size:11px;font-variant-numeric:tabular-nums}.ad-ops-nav-item.is-active .ad-ops-nav-badge{background:#fff;color:var(--region-accent)}.ad-ops-nav-foot{margin:18px 8px 0;padding-top:15px;border-top:1px solid var(--ad-ops-divider);color:#7a8499;font-size:11px;line-height:1.65}
.ad-ops-main{min-width:0;padding:24px}.ad-ops-region{--region-accent:#3568e8;--region-soft:#edf4ff;display:none}.ad-ops-region[data-region-theme="pc_left"]{--region-accent:#16836e;--region-soft:#eef9f5}.ad-ops-region[data-region-theme="pc_right"]{--region-accent:#b7691d;--region-soft:#fff7eb}.ad-ops-region.is-active{display:block;animation:ad-ops-panel-in .18s cubic-bezier(.22,.61,.36,1) both}@keyframes ad-ops-panel-in{from{opacity:.01;transform:translate3d(12px,0,0)}to{opacity:1;transform:translate3d(0,0,0)}}.ad-ops-region-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:20px}.ad-ops-region-title{display:flex;align-items:flex-start;gap:12px;min-width:0}.ad-ops-region-key{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border-radius:7px;background:var(--region-soft);color:var(--region-accent);font-size:10px;font-weight:700}.ad-ops-region-title h2{margin:1px 0 4px;color:var(--ad-ops-ink);font-size:18px;line-height:1.25;font-weight:600}.ad-ops-region-title p{margin:0;color:var(--ad-ops-muted);font-size:12px;line-height:1.55}.ad-ops-region-meta{display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap}.ad-ops-region-meta select{width:154px;height:34px;border:1px solid #dbe1ea;border-radius:6px;background:#fff;color:#4d5875;font-size:12px;box-shadow:none}.ad-ops-region-meta select:focus{border-color:var(--region-accent);outline:0;box-shadow:0 0 0 2px rgba(93,135,255,.12)}
.ad-ops-slot-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:0 0 20px}.ad-ops-slot{display:flex;align-items:center;gap:9px;min-width:0;padding:8px;border:1px solid #e2e7f0;border-radius:6px;background:#fff;color:#4d5875;text-align:left;transition:border-color .16s ease,background-color .16s ease}.ad-ops-slot:hover{border-color:#bdccec;background:#f8faff}.ad-ops-slot.is-active{border-color:var(--ad-ops-primary);background:#f4f7ff}.ad-ops-slot-thumb{display:grid;place-items:center;flex:0 0 48px;width:48px;height:38px;overflow:hidden;border:1px solid #e1e6ee;border-radius:4px;background:#f5f7fa;color:#97a1b3;font-size:18px}.ad-ops-slot-thumb img{width:100%;height:100%;object-fit:cover}.ad-ops-slot-copy{min-width:0}.ad-ops-slot-copy b,.ad-ops-slot-copy small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ad-ops-slot-copy b{color:#3b4562;font-size:12px;font-weight:600}.ad-ops-slot-copy small{margin-top:3px;color:#7a8499;font-size:10px}.ad-ops-slot.is-active .ad-ops-slot-copy b{color:var(--ad-ops-primary)}
.ad-ops-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin:0 0 10px}.ad-ops-section-head h3{margin:0 0 3px;color:#3b4562;font-size:14px;line-height:1.35;font-weight:600}.ad-ops-section-head p{margin:0;color:var(--ad-ops-muted);font-size:11px;line-height:1.55}.ad-ops-section-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.ad-ops-material-list{overflow:hidden;border:1px solid var(--ad-ops-border);border-radius:7px;background:#fff}.ad-ops-list-columns,.ad-ops-material-card{display:grid;grid-template-columns:86px minmax(180px,1fr) 146px 150px auto;align-items:center;gap:14px}.ad-ops-list-columns{padding:10px 14px;border-bottom:1px solid var(--ad-ops-divider);background:#fbfcfe;color:#7a8499;font-size:11px;font-weight:600}.ad-ops-material-group+.ad-ops-material-group{border-top:1px solid var(--ad-ops-divider)}.ad-ops-material-card{min-height:82px;padding:12px 14px}.ad-ops-material-preview{display:grid;place-items:center;width:72px;height:42px;overflow:hidden;border:1px solid #e1e6ee;border-radius:4px;background:#f5f7fa;color:#97a1b3}.ad-ops-material-preview.is-side{width:32px;height:68px;margin-left:18px}.ad-ops-material-preview img{width:100%;height:100%;object-fit:cover}.ad-ops-material-name,.ad-ops-material-status,.ad-ops-material-rule{min-width:0}.ad-ops-material-name b,.ad-ops-material-name small,.ad-ops-material-status small,.ad-ops-material-rule b,.ad-ops-material-rule small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ad-ops-material-name b,.ad-ops-material-rule b{margin-bottom:4px;color:#3b4562;font-size:12px;font-weight:600}.ad-ops-material-name small,.ad-ops-material-status small,.ad-ops-material-rule small{color:#7a8499;font-size:10px;line-height:1.45}.ad-ops-status{display:inline-flex;align-items:center;gap:5px;max-width:100%;margin-bottom:4px;color:#16855a;font-size:11px;font-weight:600}.ad-ops-status i{width:6px;height:6px;border-radius:50%;background:currentColor}.ad-ops-status.off,.ad-ops-status.end{color:#8490a3}.ad-ops-status.wait{color:#b7791f}.ad-ops-status.bad{color:#c93b33}.ad-ops-material-actions{display:flex;justify-content:flex-end;gap:6px;align-items:center}.ad-ops-material-actions form{margin:0}
.ad-ops-editor-drawer{display:none;padding:16px 14px 18px;border-top:1px solid var(--ad-ops-divider);background:#fbfcff}.ad-ops-editor-drawer.is-open{display:block;animation:ad-ops-drawer-in .17s cubic-bezier(.22,.61,.36,1) both}@keyframes ad-ops-drawer-in{from{opacity:.01;transform:translate3d(0,-5px,0)}to{opacity:1;transform:translate3d(0,0,0)}}.ad-ops-material-form{display:grid;grid-template-columns:190px minmax(0,1fr);gap:18px;margin:0}.ad-ops-editor-preview{min-width:0}.ad-ops-editor-preview>b{display:block;margin:0 0 8px;color:#4d5875;font-size:11px;font-weight:600}.ad-ops-editor-preview>div{display:grid;place-items:center;width:100%;aspect-ratio:7/2;overflow:hidden;border:1px solid #dfe5ee;border-radius:5px;background:#fff}.ad-ops-editor-preview.is-side>div{width:88px;max-width:100%;aspect-ratio:14/31;margin:0 auto}.ad-ops-editor-preview img{width:100%;height:100%;object-fit:cover}.ad-ops-editor-preview small{display:block;margin-top:8px;color:#7a8499;font-size:10px;line-height:1.55}.ad-ops-editor-content{min-width:0}.ad-ops-field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px 12px}.ad-ops-field{display:block;min-width:0;margin:0}.ad-ops-field>span{display:block;margin:0 0 5px;color:#4d5875;font-size:11px;font-weight:600}.ad-ops-field .form-control{height:34px;border-color:#dbe1ea;border-radius:5px;box-shadow:none;color:#394260;font-size:12px}.ad-ops-field .form-control:focus{border-color:var(--ad-ops-primary);box-shadow:0 0 0 2px rgba(93,135,255,.12)}.ad-ops-field-wide{grid-column:1/-1}.ad-ops-upload-progress{display:none}.ad-upload-progress{display:none;overflow:hidden;height:5px;margin-top:7px;border-radius:99px;background:#e8edf5}.ad-upload-progress i{display:block;width:100%;height:100%;background:var(--ad-ops-primary);transform:scaleX(0);transform-origin:left}.ad-upload-msg{display:block;min-height:15px;margin-top:5px;color:#7a8499;font-size:10px;line-height:1.4}.ad-ops-image-note{display:flex;flex-wrap:wrap;gap:3px 8px;color:#7a8499;font-size:10px;line-height:1.5}.ad-ops-image-note b{color:#4d5875;font-weight:600}.ad-ops-toggle-field{grid-column:1/-1;padding-top:1px}.ad-ops-advanced{display:none;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px 12px;margin-top:12px;padding-top:12px;border-top:1px solid var(--ad-ops-divider)}.ad-ops-advanced.is-open{display:grid}.ad-ops-image-diagnostic{margin:11px 0 0;color:#19734a;font-size:11px}.ad-ops-image-diagnostic.is-error{color:#b42318}.ad-ops-editor-actions{display:flex;align-items:center;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid var(--ad-ops-divider)}.ad-ops-editor-spacer{flex:1}.ad-ops-empty{display:grid;place-items:center;min-height:158px;padding:22px;text-align:center}.ad-ops-empty .glyphicon{display:block;margin-bottom:8px;color:#a4afc0;font-size:25px}.ad-ops-empty b{display:block;margin-bottom:4px;color:#4d5875;font-size:13px;font-weight:600}.ad-ops-empty span{display:block;max-width:390px;color:#7a8499;font-size:11px;line-height:1.55}.ad-ops-empty .ad-ops-button{margin-top:12px}
.ad-ops-editor-preview:not(.has-image) img{display:none}
.ad-ops-side-summary{display:grid;grid-template-columns:112px minmax(0,1fr);align-items:center;gap:18px;margin:0 0 20px;padding:15px;border:1px solid var(--ad-ops-border);border-radius:7px;background:#fbfcfe}.ad-ops-side-visual{display:grid;place-items:center;min-height:128px;border-right:1px solid var(--ad-ops-divider)}.ad-ops-side-visual>div{width:58px;aspect-ratio:14/31;overflow:hidden;border:1px solid #dfe5ee;border-radius:4px;background:#f1f4f8}.ad-ops-side-visual img{width:100%;height:100%;object-fit:cover}.ad-ops-side-copy h3{margin:0 0 5px;color:#3b4562;font-size:14px;font-weight:600}.ad-ops-side-copy p{max-width:670px;margin:0;color:var(--ad-ops-muted);font-size:11px;line-height:1.65}.ad-ops-rule-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}.ad-ops-rule-tags span{padding:3px 7px;border-radius:999px;background:#eef2f8;color:#62708a;font-size:10px}
.ad-ops-add-source{display:none}.ad-ops-add-mount:not(:empty){margin-top:10px}.ad-ops-add-mount .ad-ops-editor-drawer{border:1px solid #dbe5fb;border-radius:7px;background:#f8faff}.ad-ops-add-mount .ad-ops-editor-drawer.is-open{display:block}.ad-ops-add-label{display:inline-flex;align-items:center;gap:5px;margin:0 0 9px;color:#4d5875;font-size:11px;font-weight:600}.ad-ops-add-label b{color:var(--ad-ops-primary)}.ad-ops-save-bar{position:fixed;right:22px;bottom:20px;z-index:30;display:flex;align-items:center;gap:12px;max-width:calc(100vw - 44px);padding:10px 12px 10px 14px;border:1px solid #dbe5fb;border-radius:7px;background:#fff;box-shadow:0 5px 12px rgba(32,53,92,.12);opacity:0;pointer-events:none;transform:translate3d(0,10px,0);transition:opacity .16s ease,transform .16s ease}.ad-ops-save-bar.is-visible{opacity:1;pointer-events:auto;transform:translate3d(0,0,0)}.ad-ops-save-bar strong{color:#394260;font-size:12px;font-weight:600}.ad-ops-save-bar-actions{display:flex;gap:6px}.ad-ops-save-bar .ad-ops-button{min-height:32px}
.ad-ops-region-mode{display:inline-flex;align-items:center;min-height:34px;padding:0 10px;border:1px solid #dbe1ea;border-radius:6px;background:#f8faff;color:#4d5875;font-size:12px;white-space:nowrap}.ad-ops-region-stats{display:flex;flex-wrap:wrap;gap:5px 10px;margin-top:8px;color:var(--ad-ops-muted);font-size:10px;line-height:1.4}.ad-ops-region-stats span{white-space:nowrap}.ad-ops-region-stats b{color:#4d5875;font-size:11px;font-weight:600;font-variant-numeric:tabular-nums}.ad-ops-metrics{flex-wrap:wrap}.ad-ops-field .form-control,.ad-ops-field select.form-control{height:34px!important;border-radius:5px!important;font-size:12px!important}.ad-ops-field>span{margin-bottom:5px!important;color:#4d5875!important}.ad-ops-advanced{grid-template-columns:repeat(2,minmax(0,1fr))}.ad-ops-nav-item small,.ad-ops-nav-foot,.ad-ops-slot-copy small,.ad-ops-list-columns,.ad-ops-material-name small,.ad-ops-material-status small,.ad-ops-material-rule small,.ad-upload-msg,.ad-ops-editor-preview small{color:var(--ad-ops-muted)}.ad-ops-save-bar{right:auto;left:50%;width:min(720px,calc(100vw - 44px));justify-content:space-between;transform:translate3d(-50%,10px,0)}.ad-ops-save-bar.is-visible{transform:translate3d(-50%,0,0)}
@media(max-width:1100px){.ad-ops-global{grid-template-columns:minmax(140px,1fr) auto 1px auto;gap:14px}.ad-ops-metrics{grid-column:1/-1;justify-content:flex-start;padding-top:11px;border-top:1px solid var(--ad-ops-divider)}.ad-ops-workspace{grid-template-columns:195px minmax(0,1fr)}.ad-ops-main{padding:20px}.ad-ops-list-columns,.ad-ops-material-card{grid-template-columns:76px minmax(150px,1fr) 130px auto}.ad-ops-list-columns span:nth-child(4),.ad-ops-material-card .ad-ops-material-rule{display:none}.ad-ops-advanced{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:820px){body.qf-detail-page > .ad-ops-shell,body.qf-detail-page.qf-art-embedded > .ad-ops-shell{padding:20px 14px 42px!important}.ad-ops-shell{padding:20px 14px 42px}.ad-ops-header{align-items:flex-start;flex-direction:column}.ad-ops-global{grid-template-columns:1fr 1fr;gap:14px}.ad-ops-global-copy{grid-column:1/-1}.ad-ops-global-divider{display:none}.ad-ops-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.ad-ops-metrics span{padding:0;border:0;text-align:left}.ad-ops-workspace{display:block}.ad-ops-nav{display:flex;gap:5px;overflow-x:auto;padding:9px;border-right:0;border-bottom:1px solid var(--ad-ops-border)}.ad-ops-nav-label,.ad-ops-nav-foot{display:none}.ad-ops-nav-item{flex:0 0 138px;margin:0;padding:8px}.ad-ops-main{padding:17px}.ad-ops-region-head{align-items:stretch;flex-direction:column;gap:12px}.ad-ops-region-meta{justify-content:flex-start}.ad-ops-slot-strip{grid-template-columns:repeat(2,minmax(0,1fr))}.ad-ops-list-columns{display:none}.ad-ops-material-card{grid-template-columns:72px minmax(0,1fr) auto;gap:10px;padding:12px}.ad-ops-material-status{grid-column:2/3}.ad-ops-material-actions{grid-column:3/4;grid-row:1/3}.ad-ops-material-form{grid-template-columns:1fr}.ad-ops-editor-preview{display:grid;grid-template-columns:140px minmax(0,1fr);align-items:center;gap:12px}.ad-ops-editor-preview>b{display:none}.ad-ops-editor-preview>div{max-width:140px}.ad-ops-editor-preview.is-side>div{margin:0}.ad-ops-editor-preview small{margin:0}.ad-ops-side-summary{grid-template-columns:82px minmax(0,1fr);gap:12px}.ad-ops-side-visual{min-height:105px}.ad-ops-side-visual>div{width:47px}}
@media(max-width:520px){.ad-ops-global{grid-template-columns:1fr}.ad-ops-inline-toggle{padding:9px 0;border-top:1px solid var(--ad-ops-divider)}.ad-ops-metrics{grid-template-columns:1fr 1fr}.ad-ops-metrics span:last-child{grid-column:1/-1}.ad-ops-region-title h2{font-size:16px}.ad-ops-slot{padding:7px}.ad-ops-slot-thumb{flex-basis:38px;width:38px;height:32px}.ad-ops-slot-copy small{font-size:9px}.ad-ops-section-head{flex-direction:column}.ad-ops-material-card{grid-template-columns:58px minmax(0,1fr) auto}.ad-ops-material-preview{width:58px;height:38px}.ad-ops-material-preview.is-side{width:28px;height:60px;margin-left:12px}.ad-ops-material-status{display:none}.ad-ops-material-actions .ad-ops-button-muted{min-width:34px;padding:0!important}.ad-ops-material-actions .ad-ops-button-muted .glyphicon{margin:0}.ad-ops-material-actions .ad-ops-button-muted{font-size:0!important}.ad-ops-material-actions .ad-ops-button-muted .glyphicon{font-size:13px}.ad-ops-editor-drawer{padding:13px}.ad-ops-field-grid,.ad-ops-advanced{grid-template-columns:1fr}.ad-ops-editor-preview{grid-template-columns:105px minmax(0,1fr)}.ad-ops-editor-preview>div{max-width:105px}.ad-ops-editor-actions{flex-wrap:wrap}.ad-ops-editor-spacer{display:none;flex-basis:100%;height:0}.ad-ops-editor-actions .ad-ops-button-text{margin-right:auto}.ad-ops-side-summary{grid-template-columns:1fr}.ad-ops-side-visual{display:none}.ad-ops-save-bar{right:12px;bottom:12px;left:12px;max-width:none;justify-content:space-between}}
@media(max-width:1100px){.ad-ops-material-card .ad-ops-material-rule{display:block;grid-column:2/3}.ad-ops-material-status{grid-column:3/4}.ad-ops-material-actions{grid-column:4/5;grid-row:1/3}}
@media(max-width:820px){.ad-ops-header-actions{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));width:100%;gap:8px}.ad-ops-header-actions form,.ad-ops-header-actions .ad-ops-button{width:100%;min-width:0}.ad-ops-material-card .ad-ops-material-rule{display:block;grid-column:2/3;grid-row:3/4}.ad-ops-material-status{display:block;grid-column:2/3;grid-row:2/3}.ad-ops-material-actions{grid-column:3/4;grid-row:1/4}.ad-ops-region-stats{margin-top:7px}.ad-ops-metrics span:last-child{grid-column:auto}}
@media(max-width:520px){.ad-ops-slot-strip{grid-template-columns:1fr}.ad-ops-material-status{display:block}.ad-ops-save-bar{right:12px;bottom:12px;left:12px;width:auto;max-width:none;transform:translate3d(0,10px,0)}.ad-ops-save-bar.is-visible{transform:translate3d(0,0,0)}}
@media(prefers-reduced-motion:reduce){.ad-ops-button,.ad-ops-inline-toggle>i,.ad-ops-inline-toggle>i:after,.ad-ops-region-toggle>i,.ad-ops-region-toggle>i:after,.ad-ops-toggle-field>i,.ad-ops-toggle-field>i:after,.ad-ops-nav-item,.ad-ops-slot,.ad-ops-save-bar{transition:none!important}.ad-ops-region.is-active,.ad-ops-editor-drawer.is-open{animation:none!important}}
</style>

<main class="container ad-ops-shell" data-ad-ops-root>
  <?php foreach($tips as $tip): ?><div class="ad-ops-notice <?php echo $tip['type'] === 'error' ? 'is-error' : 'is-success'; ?>"><span class="glyphicon <?php echo $tip['type'] === 'error' ? 'glyphicon-alert' : 'glyphicon-ok'; ?>"></span><?php echo htmlspecialchars($tip['text']); ?></div><?php endforeach; ?>
  <?php foreach($errors as $error): ?><div class="ad-ops-notice is-error"><span class="glyphicon glyphicon-remove"></span><?php echo htmlspecialchars($error); ?></div><?php endforeach; ?>

  <header class="ad-ops-header">
    <div><h1>广告管理</h1><p>按广告位管理素材与投放规则。</p></div>
    <div class="ad-ops-header-actions"><a class="btn ad-ops-button ad-ops-button-muted" href="../" target="_blank" rel="noopener"><span class="glyphicon glyphicon-eye-open"></span> 查看前台</a><form method="post" data-feedback-delay="900"><?php echo qifu_csrf_input(); ?><input type="hidden" name="action" value="clear_cache"><button class="btn ad-ops-button ad-ops-button-muted" type="submit"><span class="glyphicon glyphicon-refresh"></span> 刷新缓存</button></form><button class="btn ad-ops-button ad-ops-button-primary" type="submit" form="adGlobalForm"><span class="glyphicon glyphicon-ok"></span> 保存更改</button></div>
  </header>

  <form id="adGlobalForm" method="post" class="ad-ops-global" data-feedback-delay="900">
    <?php echo qifu_csrf_input(); ?><input type="hidden" name="action" value="save_global">
    <div class="ad-ops-global-copy"><b>全局投放</b><small>关闭后广告数据与配置仍会保留</small></div>
    <label class="ad-ops-inline-toggle"><input type="checkbox" name="ad_enabled" value="1" <?php echo $ad_enabled === '1' ? 'checked' : ''; ?> data-global-master><i></i><span><b data-global-master-label><?php echo $ad_enabled === '1' ? '广告系统已开启' : '广告系统已关闭'; ?></b><small>控制全部广告位</small></span></label>
    <span class="ad-ops-global-divider" aria-hidden="true"></span>
    <label class="ad-ops-inline-toggle"><input type="checkbox" name="ad_new_window" value="1" <?php echo $ad_new_window === '1' ? 'checked' : ''; ?>><i></i><span><b>新窗口打开</b><small>广告链接打开方式</small></span></label>
    <div class="ad-ops-metrics"><span>今日曝光 <b><?php echo number_format($ad_today_views); ?></b></span><span>今日点击 <b><?php echo number_format($ad_today_clicks); ?></b></span><span>点击率 <b><?php echo $ad_today_ctr; ?>%</b></span></div>
  </form>

  <section class="ad-ops-workspace" aria-label="广告位运营清单">
    <nav class="ad-ops-nav" aria-label="广告位">
      <div class="ad-ops-nav-label">广告位</div>
      <?php foreach($position_order as $position_key): $meta = $position_meta[$position_key]; $summary = $position_summary[$position_key]; $nav_count = $position_key === 'below_search' ? count($summary['slots']).'/4' : ($summary['count'] > 0 ? '1' : '0'); ?>
        <button class="ad-ops-nav-item <?php echo $ad_ops_active_region === $position_key ? 'is-active' : ''; ?>" type="button" data-region-nav="<?php echo $position_key; ?>" data-region-theme="<?php echo $position_key; ?>" aria-controls="adOpsRegion-<?php echo $position_key; ?>" aria-pressed="<?php echo $ad_ops_active_region === $position_key ? 'true' : 'false'; ?>"><span><strong><?php echo htmlspecialchars($meta['short']); ?></strong><small><?php echo $position_key === 'below_search' ? '四个固定槽位' : '单一广告位'; ?></small></span><span class="ad-ops-nav-badge"><?php echo $nav_count; ?></span></button>
      <?php endforeach; ?>
      <p class="ad-ops-nav-foot">点击广告位后，只显示当前区域的素材与配置。侧边悬浮广告会在页面宽度小于等于 1440px 时自动隐藏。</p>
    </nav>

    <div class="ad-ops-main">
      <?php foreach($position_order as $position_key):
        $meta = $position_meta[$position_key];
        $summary = $position_summary[$position_key];
        $region_enabled = $meta['enabled'] === '1';
        $is_top_region = $position_key === 'below_search';
        $side_primary = $summary['primary'];
      ?>
      <section class="ad-ops-region <?php echo $ad_ops_active_region === $position_key ? 'is-active' : ''; ?>" id="adOpsRegion-<?php echo $position_key; ?>" data-region-panel="<?php echo $position_key; ?>" data-region-theme="<?php echo $position_key; ?>">
        <header class="ad-ops-region-head">
          <div class="ad-ops-region-title"><span class="ad-ops-region-key"><?php echo $is_top_region ? 'TOP' : ($position_key === 'pc_left' ? 'LEFT' : 'RIGHT'); ?></span><div><h2><?php echo htmlspecialchars($meta['short']); ?></h2><p><?php echo $is_top_region ? 'PC 与手机端都会显示，每个槽位只保留一条素材。' : '仅在屏幕宽度大于 1440px 时展示，严格限制为一个广告位。'; ?></p><div class="ad-ops-region-stats"><span>素材 <b><?php echo number_format($summary['count']); ?></b></span><span>投放中 <b><?php echo number_format($summary['active']); ?></b></span><span>累计曝光 <b><?php echo number_format($summary['views']); ?></b></span><span>累计点击 <b><?php echo number_format($summary['clicks']); ?></b></span><span>点击率 <b><?php echo $summary['views'] > 0 ? round($summary['clicks'] * 100 / $summary['views'], 1) : 0; ?>%</b></span></div></div></div>
          <div class="ad-ops-region-meta">
            <label class="ad-ops-region-toggle"><input form="adGlobalForm" type="checkbox" name="<?php echo $meta['toggle']; ?>" value="1" <?php echo $region_enabled ? 'checked' : ''; ?> data-region-toggle="<?php echo $position_key; ?>"><i></i><span><b data-region-switch-label><?php echo $region_enabled ? '区域已开启' : '区域已关闭'; ?></b><small>独立控制前台展示</small></span></label>
            <span class="ad-ops-region-mode"><?php echo $is_top_region ? '四格固定展示' : '单一素材固定展示'; ?></span>
          </div>
        </header>

        <?php if($is_top_region): ?>
          <div class="ad-ops-slot-strip" aria-label="搜索栏下方四个槽位">
            <?php foreach($slot_labels as $slot_value => $slot_name): $slot_first = !empty($ad_ops_slots[$slot_value]) ? $ad_ops_slots[$slot_value][0] : null; ?>
              <button class="ad-ops-slot <?php echo $ad_ops_active_slot === intval($slot_value) ? 'is-active' : ''; ?>" type="button" data-slot-button="<?php echo intval($slot_value); ?>" aria-controls="adOpsSlot-<?php echo intval($slot_value); ?>" aria-pressed="<?php echo $ad_ops_active_slot === intval($slot_value) ? 'true' : 'false'; ?>"><span class="ad-ops-slot-thumb"><?php if($slot_first && !empty($slot_first['image'])): ?><img src="<?php echo htmlspecialchars($slot_first['image']); ?>" alt="<?php echo htmlspecialchars($slot_name); ?>广告"><?php else: ?>＋<?php endif; ?></span><span class="ad-ops-slot-copy"><b><?php echo htmlspecialchars($slot_name); ?></b><small><?php echo empty($ad_ops_slots[$slot_value]) ? '未投放' : count($ad_ops_slots[$slot_value]).' 条素材'; ?></small></span></button>
            <?php endforeach; ?>
          </div>
          <?php foreach($slot_labels as $slot_value => $slot_name): $slot_ads = $ad_ops_slots[$slot_value]; ?>
            <section class="ad-ops-slot-panel <?php echo $ad_ops_active_slot === intval($slot_value) ? 'is-active' : ''; ?>" id="adOpsSlot-<?php echo intval($slot_value); ?>" data-slot-panel="<?php echo intval($slot_value); ?>" <?php echo $ad_ops_active_slot === intval($slot_value) ? '' : 'hidden'; ?>>
              <div class="ad-ops-section-head"><div><h3><?php echo htmlspecialchars($slot_name); ?>素材</h3><p><?php echo empty($slot_ads) ? '当前为空位，添加素材后会自动绑定到这里。' : '当前槽位已绑定素材，编辑不会影响其它三个位置。'; ?></p></div><div class="ad-ops-section-actions"><?php if(empty($slot_ads)): ?><button class="btn ad-ops-button ad-ops-button-primary" type="button" data-open-add data-position="below_search" data-slot="<?php echo intval($slot_value); ?>"><span class="glyphicon glyphicon-plus"></span> 添加素材</button><?php endif; ?></div></div>
              <div class="ad-ops-material-list">
                <?php if(!empty($slot_ads)): ?><div class="ad-ops-list-columns"><span>素材</span><span>名称与跳转</span><span>状态与排期</span><span>投放设置</span><span>操作</span></div><?php foreach($slot_ads as $slot_ad) $ad_ops_render_material($slot_ad, 'below_search', $slot_value, $meta); else: ?><div class="ad-ops-empty"><div><span class="glyphicon glyphicon-picture"></span><b>这个槽位还没有广告素材</b><span>推荐上传 <?php echo htmlspecialchars($meta['recommended']); ?> 的图片，前台会居中裁切铺满。</span><button class="btn ad-ops-button ad-ops-button-primary" type="button" data-open-add data-position="below_search" data-slot="<?php echo intval($slot_value); ?>"><span class="glyphicon glyphicon-plus"></span> 添加素材</button></div></div><?php endif; ?>
              </div>
              <div class="ad-ops-add-mount" data-add-mount data-position="below_search" data-slot="<?php echo intval($slot_value); ?>"></div>
            </section>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="ad-ops-side-summary">
            <div class="ad-ops-side-visual"><div><?php if($side_primary && !empty($side_primary['image'])): ?><img src="<?php echo htmlspecialchars($side_primary['image']); ?>" alt="<?php echo htmlspecialchars($side_primary['alt'] ?: $meta['short']); ?>"><?php endif; ?></div></div>
            <div class="ad-ops-side-copy"><h3><?php echo htmlspecialchars($meta['short']); ?>广告</h3><p>推荐 <?php echo htmlspecialchars($meta['recommended']); ?>。前台会按比例居中裁切铺满；在小于等于 1440px 的窗口中自动隐藏。</p><div class="ad-ops-rule-tags"><span>单一素材</span><span>固定展示</span><span>宽屏条件</span></div></div>
          </div>
          <?php if(!empty($side_ad_conflicts[$position_key])): ?><div class="ad-ops-notice is-error"><span class="glyphicon glyphicon-alert"></span>检测到 <?php echo intval($side_ad_conflicts[$position_key]); ?> 条历史重复素材。系统不会自动删除，请保留需要的素材后手动删除其余记录。</div><?php endif; ?>
          <div class="ad-ops-section-head"><div><h3>当前素材</h3><p>侧边广告只有一个素材位，不能继续添加第二条记录。</p></div><?php if(empty($ads_by_position[$position_key])): ?><div class="ad-ops-section-actions"><button class="btn ad-ops-button ad-ops-button-primary" type="button" data-open-add data-position="<?php echo $position_key; ?>" data-slot="1"><span class="glyphicon glyphicon-plus"></span> 添加素材</button></div><?php endif; ?></div>
          <div class="ad-ops-material-list">
            <?php if(!empty($ads_by_position[$position_key])): ?><div class="ad-ops-list-columns"><span>素材</span><span>名称与跳转</span><span>状态与排期</span><span>投放设置</span><span>操作</span></div><?php foreach($ads_by_position[$position_key] as $side_ad) $ad_ops_render_material($side_ad, $position_key, 1, $meta); else: ?><div class="ad-ops-empty"><div><span class="glyphicon glyphicon-picture"></span><b>这个区域还没有广告素材</b><span>添加后会在宽屏前台按当前规则展示。</span><button class="btn ad-ops-button ad-ops-button-primary" type="button" data-open-add data-position="<?php echo $position_key; ?>" data-slot="1"><span class="glyphicon glyphicon-plus"></span> 添加素材</button></div></div><?php endif; ?>
          </div>
          <div class="ad-ops-add-mount" data-add-mount data-position="<?php echo $position_key; ?>" data-slot="1"></div>
        <?php endif; ?>
      </section>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="ad-ops-add-source" id="adOpsAddFormHome">
    <div class="ad-ops-editor-drawer is-open" id="adOpsAddDrawer" aria-hidden="true">
      <form id="adOpsAddForm" method="post" enctype="multipart/form-data" class="ad-ops-material-form" data-feedback-delay="900">
        <?php echo qifu_csrf_input(); ?><input type="hidden" name="action" value="save_ad"><input type="hidden" name="position" value="below_search" class="ad-position-input"><input type="hidden" name="slot" value="1" class="ad-slot-input"><input type="hidden" name="sort" value="100"><input type="hidden" name="weight" value="1">
        <aside class="ad-ops-editor-preview" data-image-preview data-preview-alt="待添加广告素材预览"><b>前台裁切预览</b><div></div><small>前台会按广告位居中裁切铺满。</small></aside>
        <div class="ad-ops-editor-content">
          <div class="ad-ops-add-label">正在添加到 <b data-add-slot-label>左上</b></div>
          <div class="ad-ops-field-grid">
            <label class="ad-ops-field"><span>广告标题</span><input type="text" name="title" class="form-control" placeholder="用于后台识别这条广告"></label>
            <label class="ad-ops-field"><span>跳转链接</span><input type="text" name="link" class="form-control" placeholder="https://example.com"></label>
            <label class="ad-ops-field ad-ops-field-wide"><span>图片外链 / 上传后自动填入</span><input type="text" name="image" class="form-control ad-url-input" placeholder="https://example.com/ad.png"></label>
            <label class="ad-ops-field ad-ops-field-wide"><span>上传图片，支持 JPG、PNG、GIF、WebP</span><input type="file" name="ad_file" class="form-control ad-upload-input" accept="image/jpeg,image/png,image/gif,image/webp"><span class="ad-upload-progress"><i></i></span><small class="ad-upload-msg" aria-live="polite"></small></label>
            <div class="ad-ops-image-note ad-ops-field-wide" data-ad-image-fit-note><b></b><span></span></div>
            <label class="ad-ops-field ad-ops-field-wide"><span>图片说明</span><input type="text" name="alt" class="form-control" placeholder="图片无法显示时展示的文字"></label>
            <label class="ad-ops-toggle-field"><input type="checkbox" name="active" value="1" checked><i></i><span><b>立即启用</b><small>保存后允许前台展示</small></span></label>
          </div>
          <div class="ad-ops-advanced" id="adOpsAddAdvanced" data-add-advanced><label class="ad-ops-field"><span>定时上线</span><input type="datetime-local" name="start_at" class="form-control"></label><label class="ad-ops-field"><span>定时下线</span><input type="datetime-local" name="end_at" class="form-control"></label></div>
          <div class="ad-ops-editor-actions"><button class="btn ad-ops-button ad-ops-button-text" type="button" data-toggle-add-advanced aria-controls="adOpsAddAdvanced" aria-expanded="false">投放时间</button><span class="ad-ops-editor-spacer"></span><button class="btn ad-ops-button ad-ops-button-muted" type="button" data-cancel-add>取消</button><button class="btn ad-ops-button ad-ops-button-primary" type="submit"><span class="glyphicon glyphicon-plus"></span> 添加素材</button></div>
        </div>
      </form>
    </div>
  </div>
</main>

<aside class="ad-ops-save-bar" data-save-bar><strong>有未保存的全局更改</strong><div class="ad-ops-save-bar-actions"><button class="btn ad-ops-button ad-ops-button-muted" type="button" data-reset-global>恢复</button><button class="btn ad-ops-button ad-ops-button-primary" type="submit" form="adGlobalForm"><span class="glyphicon glyphicon-ok"></span> 保存</button></div></aside>

<script>
// 无刷新提交：POST 后仅替换 .ad-ops-shell 与保存条，再重新初始化；iframe 内不整页重载，消除白闪/硬刷新
function qfAdReplaceAndInit(html){
  var doc;
  try{ doc = new DOMParser().parseFromString(html, 'text/html'); }catch(e){ return false; }
  var newMain = doc.querySelector('main[data-ad-ops-root]');
  var curMain = document.querySelector('main[data-ad-ops-root]');
  if(!newMain || !curMain) return false; // 响应非广告页（如登录/异常）→ 交回原生提交
  // 锁定文档高度：iframe 由父级按内容高度撑开，替换节点若高度骤降→回升会导致父页"跳动"
  var lockH = document.documentElement.scrollHeight;
  var body = document.body;
  var prevMinH = body.style.minHeight;
  body.style.minHeight = lockH + 'px';
  var imported = document.importNode(newMain, true);
  curMain.parentNode.replaceChild(imported, curMain);
  var newBar = doc.querySelector('.ad-ops-save-bar');
  var curBar = document.querySelector('.ad-ops-save-bar');
  if(newBar && curBar) curBar.parentNode.replaceChild(document.importNode(newBar, true), curBar);
  if(window.qifuEnsureCsrfTokens) window.qifuEnsureCsrfTokens(document);
  if(window.qifuPreserveEmbeddedContext) window.qifuPreserveEmbeddedContext(document);
  if(window.qifuEnsureAccessibleFormLabels) window.qifuEnsureAccessibleFormLabels(document);
  initAdOps();
  // 新内容布局稳定后再释放高度锁（两帧），避免瞬时塌陷
  var release = function(){ body.style.minHeight = prevMinH; };
  if(window.requestAnimationFrame){ requestAnimationFrame(function(){ requestAnimationFrame(release); }); }
  else { window.setTimeout(release, 60); }
  // 成功提示已通过 postMessage 让父级 Vue 弹 toast，iframe 内不再强制滚动（那会造成视觉跳动）
  if(window.qifuPromoteSuccessAlerts) window.qifuPromoteSuccessAlerts(imported);
  return true;
}
function qfAdSubmitAjax(form, button, actionUrl){
  var fallback = function(){ HTMLFormElement.prototype.submit.call(form); };
  if(!window.fetch || !window.FormData || !window.DOMParser){ fallback(); return; }
  var data = new FormData(form);
  if(button && button.name) data.append(button.name, button.value || '');
  window.fetch(actionUrl, {
    method: 'POST', body: data, credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  }).then(function(resp){
    if(!resp.ok) throw new Error('HTTP ' + resp.status);
    return resp.text();
  }).then(function(text){ if(!qfAdReplaceAndInit(text)) fallback(); }).catch(function(){ fallback(); });
}
function initAdOps(){
  var root = document.querySelector('[data-ad-ops-root]');
  if(!root) return;
  var activeRegion = root.querySelector('[data-region-panel].is-active');
  var activeRegionKey = activeRegion ? activeRegion.getAttribute('data-region-panel') : 'below_search';
  var addForm = document.getElementById('adOpsAddForm');
  var addDrawer = document.getElementById('adOpsAddDrawer');
  var addHome = document.getElementById('adOpsAddFormHome');
  var saveBar = document.querySelector('[data-save-bar]');
  var addReturnFocus = null;

  // 找到触发本次提交的按钮（含 form="adGlobalForm" 这类外部按钮）
  function resolveSubmitButton(form, event){
    if(event && event.submitter) return event.submitter;
    if(form.id){
      var external = document.querySelector('[form="' + form.id + '"][type="submit"]');
      if(external) return external;
    }
    return form.querySelector('button[type="submit"],input[type="submit"]');
  }

  document.querySelectorAll('form[data-feedback-delay]').forEach(function(form){
    form.addEventListener('submit', function(event){
      if(form.getAttribute('data-delay-submitting') === 'true'){
        event.preventDefault();
        return;
      }
      // 删除等需二次确认的表单：确认后再安排提交。
      if(form.classList.contains('ad-ops-delete-form') && !window.confirm('确定删除这个广告吗？对应统计也会一起删除。')){
        event.preventDefault();
        return;
      }
      event.preventDefault();
      form.setAttribute('data-delay-submitting', 'true');
      var button = resolveSubmitButton(form, event);
      // 按钮本身保持原样，短暂延迟后再提交并展示服务端成功反馈。
      var actionUrl = form.getAttribute('action') || window.location.href;
      var doSubmit = function(){ qfAdSubmitAjax(form, button, actionUrl); };
      var submitDelay = Math.max(0, parseInt(form.getAttribute('data-feedback-delay'), 10) || 0);
      window.setTimeout(doSubmit, submitDelay);
    });
  });

  function setMessage(box, text, ok){ if(!box) return; box.textContent = text; box.style.color = ok ? '#19734a' : '#b42318'; }
  function updateUrl(params){
    try{
      var url = new URL(window.location.href);
      Object.keys(params).forEach(function(key){ url.searchParams.set(key, params[key]); });
      window.history.replaceState({}, '', url.pathname + (url.search || '') + (url.hash || ''));
    }catch(error){}
  }
  function setRegion(region){
    activeRegionKey = region;
    root.querySelectorAll('[data-region-nav]').forEach(function(button){ var active = button.getAttribute('data-region-nav') === region; button.classList.toggle('is-active', active); button.setAttribute('aria-pressed', active ? 'true' : 'false'); });
    root.querySelectorAll('[data-region-panel]').forEach(function(panel){
      var active = panel.getAttribute('data-region-panel') === region;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
    closeAdd(false);
    updateUrl({region:region});
  }
  function setSlot(slot){
    root.querySelectorAll('[data-slot-button]').forEach(function(button){ var active = button.getAttribute('data-slot-button') === String(slot); button.classList.toggle('is-active', active); button.setAttribute('aria-pressed', active ? 'true' : 'false'); });
    root.querySelectorAll('[data-slot-panel]').forEach(function(panel){
      var active = panel.getAttribute('data-slot-panel') === String(slot);
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
    closeAdd(false);
    updateUrl({slot:slot});
  }
  function closeEditor(drawer, restoreFocus){
    if(!drawer) return;
    var id = drawer.getAttribute('data-editor-drawer');
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    var trigger = id ? root.querySelector('[data-edit-ad="' + id + '"]') : null;
    if(trigger) trigger.setAttribute('aria-expanded', 'false');
    if(restoreFocus && trigger) trigger.focus();
  }
  function closeDrawers(except){
    root.querySelectorAll('[data-editor-drawer]').forEach(function(drawer){
      if(drawer === except) return;
      closeEditor(drawer, false);
    });
  }
  function updatePreview(form, value){
    if(!form) return;
    var preview = form.querySelector('[data-image-preview]');
    var frame = preview ? preview.querySelector('div') : null;
    var image = preview ? preview.querySelector('img') : null;
    if(!preview || !frame) return;
    value = String(value || '').trim();
    if(value){
      if(!image){
        image = document.createElement('img');
        image.alt = preview.getAttribute('data-preview-alt') || '广告预览';
        frame.appendChild(image);
      }
      image.src = value;
      preview.classList.add('has-image');
    } else {
      if(image) image.remove();
      preview.classList.remove('has-image');
    }
  }
  function updateFormGuidance(form){
    if(!form) return;
    var position = form.querySelector('.ad-position-input');
    var isSide = position && (position.value === 'pc_left' || position.value === 'pc_right');
    var preview = form.querySelector('[data-image-preview]');
    var note = form.querySelector('[data-ad-image-fit-note]');
    if(preview) preview.classList.toggle('is-side', !!isSide);
    if(note){
      var heading = note.querySelector('b');
      var detail = note.querySelector('span');
      if(heading) heading.textContent = isSide ? '推荐上传 560 × 1240 px（14:31）' : '推荐上传 840 × 240 px（7:2）';
      if(detail) detail.textContent = '前台会按广告位居中裁切铺满；按推荐比例制作不会额外裁切。';
    }
  }
  function closeAdd(reset, restoreFocus){
    if(!addForm || !addHome) return;
    if(addForm.parentNode !== addHome) addHome.appendChild(addDrawer);
    addDrawer.classList.remove('is-open');
    addDrawer.setAttribute('aria-hidden', 'true');
    if(reset){
      addForm.reset();
      var position = addForm.querySelector('.ad-position-input');
      var slot = addForm.querySelector('.ad-slot-input');
      if(position) position.value = 'below_search';
      if(slot) slot.value = '1';
      updatePreview(addForm, '');
      var message = addForm.querySelector('.ad-upload-msg');
      if(message) message.textContent = '';
    }
    if(restoreFocus && addReturnFocus && document.contains(addReturnFocus)) addReturnFocus.focus();
    if(reset) addReturnFocus = null;
  }
  function openAdd(position, slot, trigger){
    var mount = root.querySelector('[data-add-mount][data-position="' + position + '"][data-slot="' + slot + '"]');
    if(!mount || !addForm || !addDrawer) return;
    closeDrawers();
    addReturnFocus = trigger || null;
    mount.appendChild(addDrawer);
    var positionInput = addForm.querySelector('.ad-position-input');
    var slotInput = addForm.querySelector('.ad-slot-input');
    var label = addForm.querySelector('[data-add-slot-label]');
    if(positionInput) positionInput.value = position;
    if(slotInput) slotInput.value = slot;
    if(label) label.textContent = position === 'below_search' ? ({1:'左上',2:'右上',3:'左下',4:'右下'}[Number(slot)] || '当前槽位') : (position === 'pc_left' ? 'PC 左侧悬浮' : 'PC 右侧悬浮');
    updateFormGuidance(addForm);
    addDrawer.classList.add('is-open');
    addDrawer.setAttribute('aria-hidden', 'false');
    var title = addForm.querySelector('input[name="title"]');
    if(title) window.setTimeout(function(){ title.focus(); }, 80);
  }
  root.querySelectorAll('[data-region-nav]').forEach(function(button){ button.addEventListener('click', function(){ setRegion(button.getAttribute('data-region-nav')); }); });
  root.querySelectorAll('[data-slot-button]').forEach(function(button){ button.addEventListener('click', function(){ setSlot(button.getAttribute('data-slot-button')); }); });
  root.querySelectorAll('[data-edit-ad]').forEach(function(button){ button.addEventListener('click', function(){
    var id = button.getAttribute('data-edit-ad');
    var drawer = root.querySelector('[data-editor-drawer="' + id + '"]');
    if(!drawer) return;
    var opening = !drawer.classList.contains('is-open');
    closeDrawers(drawer);
    drawer.classList.toggle('is-open', opening);
    drawer.setAttribute('aria-hidden', opening ? 'false' : 'true');
    button.setAttribute('aria-expanded', opening ? 'true' : 'false');
    if(!opening) button.focus();
  }); });
  root.querySelectorAll('[data-close-editor]').forEach(function(button){ button.addEventListener('click', function(){ closeEditor(button.closest('[data-editor-drawer]'), true); }); });
  root.querySelectorAll('[data-toggle-advanced]').forEach(function(button){ button.addEventListener('click', function(){ var panel = root.querySelector('[data-advanced-panel="' + button.getAttribute('data-toggle-advanced') + '"]'); if(panel){ var opening = !panel.classList.contains('is-open'); panel.classList.toggle('is-open', opening); button.setAttribute('aria-expanded', opening ? 'true' : 'false'); } }); });
  root.querySelectorAll('[data-open-add]').forEach(function(button){ button.addEventListener('click', function(){ openAdd(button.getAttribute('data-position'), button.getAttribute('data-slot'), button); }); });
  if(addForm){
    var cancelAdd = addForm.querySelector('[data-cancel-add]');
    if(cancelAdd) cancelAdd.addEventListener('click', function(){ closeAdd(true, true); });
    var addAdvanced = addForm.querySelector('[data-toggle-add-advanced]');
    if(addAdvanced) addAdvanced.addEventListener('click', function(){ var panel = addForm.querySelector('[data-add-advanced]'); if(panel){ var opening = !panel.classList.contains('is-open'); panel.classList.toggle('is-open', opening); addAdvanced.setAttribute('aria-expanded', opening ? 'true' : 'false'); } });
  }
  function isPreviewableImageUrl(value){
    return /^(?:https?:\/\/|\/|\.\/|\.\.\/|images\/|[a-z0-9.-]+\.[a-z]{2,}(?:\/|$))/i.test(String(value || '').trim());
  }
  function schedulePreview(form, value){
    if(!form) return;
    if(form._adPreviewTimer) window.clearTimeout(form._adPreviewTimer);
    form._adPreviewTimer = window.setTimeout(function(){
      value = String(value || '').trim();
      if(value === '' || isPreviewableImageUrl(value)) updatePreview(form, value);
    }, 220);
  }
  root.querySelectorAll('.ad-url-input').forEach(function(input){
    var form = input.closest('form');
    updatePreview(form, input.value);
    input.addEventListener('input', function(){ schedulePreview(form, input.value); });
  });
  root.querySelectorAll('.ad-ops-material-form').forEach(function(form){ updateFormGuidance(form); });
  function updateRegionState(input){
    var panel = root.querySelector('[data-region-panel="' + input.getAttribute('data-region-toggle') + '"]');
    if(!panel) return;
    var label = panel.querySelector('[data-region-switch-label]');
    if(label) label.textContent = input.checked ? '区域已开启' : '区域已关闭';
  }
  root.querySelectorAll('[data-region-toggle]').forEach(function(input){ input.addEventListener('change', function(){ updateRegionState(input); markDirty(); }); });
  var master = root.querySelector('[data-global-master]');
  if(master) master.addEventListener('change', function(){ var label = root.querySelector('[data-global-master-label]'); if(label) label.textContent = master.checked ? '广告系统已开启' : '广告系统已关闭'; markDirty(); });
  var dirtyControls = document.querySelectorAll('#adGlobalForm input, #adGlobalForm select, [form="adGlobalForm"]');
  function markDirty(){ if(saveBar) saveBar.classList.add('is-visible'); }
  dirtyControls.forEach(function(control){ control.addEventListener('change', markDirty); });
  document.querySelectorAll('[data-reset-global]').forEach(function(button){ button.addEventListener('click', function(){ window.location.reload(); }); });
  // 删除确认已并入统一提交处理器（confirm 前置），此处不再重复注册
  function showImageDimensions(image){
    var label = image.closest('.ad-ops-material-card');
    label = label ? label.querySelector('[data-image-dimensions]') : null;
    if(label) label.textContent = image.naturalWidth && image.naturalHeight ? image.naturalWidth + ' × ' + image.naturalHeight + ' px' : '无法读取图片尺寸';
  }
  root.querySelectorAll('.ad-ops-material-image').forEach(function(image){ if(image.complete) showImageDimensions(image); image.addEventListener('load', function(){ showImageDimensions(image); }); image.addEventListener('error', function(){ var label = image.closest('.ad-ops-material-card'); label = label ? label.querySelector('[data-image-dimensions]') : null; if(label) label.textContent = '图片加载失败'; }); });
  function fitAdDimensions(width, height, position){
    var box = position === 'pc_left' || position === 'pc_right' ? {width:560,height:1240} : {width:840,height:240};
    var scale = Math.max(box.width / width, box.height / height);
    var sourceWidth = Math.min(width, box.width / scale);
    var sourceHeight = Math.min(height, box.height / scale);
    return {
      width:box.width,
      height:box.height,
      sourceWidth:sourceWidth,
      sourceHeight:sourceHeight,
      sourceX:Math.max(0,(width-sourceWidth)/2),
      sourceY:Math.max(0,(height-sourceHeight)/2),
      cropped:sourceWidth < width || sourceHeight < height,
      resized:width !== box.width || height !== box.height
    };
  }
  function prepareAdFile(file, position){ return new Promise(function(resolve){
    var fallback = {file:file,name:file && file.name,resized:false,originalWidth:0,originalHeight:0,width:0,height:0};
    if(!file || file.type === 'image/gif' || !/^image\/(jpeg|png|webp)$/i.test(file.type)){ resolve(fallback); return; }
    var objectUrl = URL.createObjectURL(file); var image = new Image();
    image.onload = function(){ var originalWidth = image.naturalWidth || image.width; var originalHeight = image.naturalHeight || image.height; var fit = fitAdDimensions(originalWidth,originalHeight,position); if(!fit.resized){ URL.revokeObjectURL(objectUrl); resolve({file:file,name:file.name,resized:false,cropped:false,originalWidth:originalWidth,originalHeight:originalHeight,width:originalWidth,height:originalHeight}); return; } var canvas = document.createElement('canvas'); canvas.width=fit.width; canvas.height=fit.height; var context=canvas.getContext('2d'); if(!context){URL.revokeObjectURL(objectUrl);resolve(fallback);return;} context.imageSmoothingEnabled=true; context.imageSmoothingQuality='high'; context.drawImage(image,fit.sourceX,fit.sourceY,fit.sourceWidth,fit.sourceHeight,0,0,fit.width,fit.height); URL.revokeObjectURL(objectUrl); canvas.toBlob(function(blob){resolve(blob ? {file:blob,name:file.name,resized:true,cropped:fit.cropped,originalWidth:originalWidth,originalHeight:originalHeight,width:fit.width,height:fit.height}:fallback);},file.type,file.type==='image/png'?undefined:.88); };
    image.onerror = function(){ URL.revokeObjectURL(objectUrl); resolve(fallback); }; image.src = objectUrl;
  }); }
  root.querySelectorAll('.ad-upload-input').forEach(function(input){ input.addEventListener('change', function(){
    if(!input.files || !input.files[0]) return;
    var sourceFile=input.files[0], form=input.closest('form'), target=form.querySelector('.ad-url-input'), positionInput=form.querySelector('.ad-position-input'), position=positionInput?positionInput.value:'below_search', submitButton=form.querySelector('button[type="submit"]'), bar=form.querySelector('.ad-upload-progress'), fill=bar?bar.querySelector('i'):null, message=form.querySelector('.ad-upload-msg'), meta=document.querySelector('meta[name="qifu-csrf"]');
    if(!target || !bar || !fill || !meta){ setMessage(message,'上传组件初始化失败，请刷新后重试。',false); return; }
    bar.style.display='block'; fill.style.transform='scaleX(0)'; input.disabled=true; if(submitButton) submitButton.disabled=true; setMessage(message,'正在检查并适配图片尺寸...',true);
    prepareAdFile(sourceFile,position).then(function(prepared){ var data=new FormData(); data.append('file',prepared.file,prepared.name||sourceFile.name); data.append('slot','ad_admin'); data.append('position',position); data.append('_csrf',meta.getAttribute('content')); var xhr=new XMLHttpRequest(); xhr.open('POST','./ajax_upload_ad.php',true); setMessage(message,prepared.resized?'已从 '+prepared.originalWidth+'×'+prepared.originalHeight+(prepared.cropped?' 居中裁切':' 等比适配')+' 为 '+prepared.width+'×'+prepared.height+'，正在上传...':(sourceFile.type==='image/gif'?'GIF 动画保持原图，正在上传...':'图片已符合组件尺寸，正在上传...'),true); xhr.upload.onprogress=function(event){if(event.lengthComputable)fill.style.transform='scaleX('+(event.loaded/event.total)+')';}; xhr.onload=function(){input.disabled=false;if(submitButton)submitButton.disabled=false;var response;try{response=JSON.parse(xhr.responseText);}catch(error){setMessage(message,'服务器返回异常：'+xhr.responseText.substring(0,120),false);return;}if(response.code==1){fill.style.transform='scaleX(1)';target.value=response.url;updatePreview(form,response.url);input.value='';setMessage(message,(prepared.resized?'上传成功，图片已处理为组件尺寸。':String(response.msg||'上传成功'))+' 保存素材后生效。',true);}else{setMessage(message,response.msg||'上传失败，可直接点保存尝试兜底上传。',false);}}; xhr.onerror=function(){input.disabled=false;if(submitButton)submitButton.disabled=false;setMessage(message,'网络错误，可直接点保存尝试兜底上传。',false);}; xhr.send(data); }).catch(function(){ input.disabled=false;if(submitButton)submitButton.disabled=false;setMessage(message,'图片处理失败，可直接点保存尝试兜底上传。',false); });
  }); });
  setRegion(activeRegionKey);
}
initAdOps();
</script>
