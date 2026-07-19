<template>
  <div class="qifu-admin-page">
    <div v-if="loading" class="page-loading"><ElSkeleton :rows="7" animated /></div>

    <template v-else-if="pageName === 'Console'">
      <div class="welcome-card">
        <div><h2>欢迎回来，管理员</h2><p>站点运营、访问趋势和内容维护都集中在这里。</p></div>
        <div class="welcome-actions"
          ><ElTag type="success" effect="light">系统运行正常</ElTag
          ><ElButton type="primary" @click="go('Sites')"
            ><ElIcon><Plus /></ElIcon>新增站点</ElButton
          ></div
        >
      </div>
      <div class="stat-grid">
        <button
          v-for="item in statCards"
          :key="item.key"
          type="button"
          class="stat-card insight-stat-card"
          :class="item.tone"
          :aria-label="`${item.label}：${item.value}，${item.insightValue}${item.insightText}，${item.actionLabel}`"
          @click="handleStatCard(item)"
        >
          <span class="insight-stat-head">
            <span class="stat-label">{{ item.label }}</span>
            <span class="stat-icon" :class="item.tone"
              ><ElIcon><component :is="item.icon" /></ElIcon
            ></span>
          </span>
          <strong class="insight-stat-value">{{ item.value }}</strong>
          <span class="insight-stat-message"
            ><b>{{ item.insightValue }}</b
            ><span>{{ item.insightText }}</span></span
          >
          <span class="insight-stat-action"
            ><small>{{ item.note }}</small
            ><span>{{ item.actionLabel }} <i aria-hidden="true">→</i></span></span
          >
        </button>
      </div>
      <div class="content-grid">
        <ElCard shadow="never" class="art-card trend-card">
          <template #header>
            <div class="card-head trend-card-head">
              <div
                ><b>近 14 天访问趋势</b
                ><small>折线展示全站浏览量与站点点击量，选择日期可查看各站点明细</small></div
              >
              <div class="trend-actions">
                <span class="trend-legend is-clicks"><i></i>点击量</span>
                <span class="trend-legend is-views"><i></i>浏览量</span>
                <ElButton text type="primary" :loading="trendRefreshing" @click="refreshTrend"
                  >刷新</ElButton
                >
              </div>
            </div>
          </template>
          <div class="trend-chart trend-line-chart">
            <svg
              viewBox="0 0 720 220"
              preserveAspectRatio="none"
              role="img"
              aria-label="近14天访问趋势折线图"
            >
              <line
                v-for="level in 4"
                :key="`grid-${level}`"
                class="trend-grid-line"
                x1="34"
                :y1="18 + (level - 1) * 56"
                x2="708"
                :y2="18 + (level - 1) * 56"
              />
              <polyline
                v-if="trendPoints.length"
                class="trend-polyline trend-polyline-views"
                :points="trendViewsPolyline"
              />
              <polyline
                v-if="trendPoints.length"
                class="trend-polyline trend-polyline-clicks"
                :points="trendClicksPolyline"
              />
              <g
                v-for="point in trendPoints"
                :key="point.date"
                class="trend-point-group"
                :class="{ active: selectedTrendDate === point.date }"
                @click="loadSiteStats(point.date)"
              >
                <line class="trend-hit-area" :x1="point.x" y1="18" :x2="point.x" y2="186" />
                <circle
                  class="trend-point trend-point-views"
                  :cx="point.x"
                  :cy="point.viewsY"
                  r="4"
                />
                <circle
                  class="trend-point trend-point-clicks"
                  :cx="point.x"
                  :cy="point.clicksY"
                  r="5"
                />
                <text class="trend-date" :x="point.x" y="208" text-anchor="middle">
                  {{ point.date.slice(5) }}
                </text>
              </g>
            </svg>
            <div v-if="!trendHasData" class="trend-empty">暂无访问或站点点击数据</div>
          </div>
          <div class="trend-detail" :class="{ 'is-empty': !selectedTrendDate }">
            <div class="trend-detail-toolbar">
              <div class="trend-detail-head">
                <b>{{
                  selectedTrendDate
                    ? `${selectedTrendDate} 站点${trendMetricLabel}明细`
                    : `选择折线上的日期查看站点${trendMetricLabel}明细`
                }}</b>
                <small>{{
                  trendMetric === 'views'
                    ? '浏览量按站点卡片实际进入访客视口统计'
                    : '点击量按访客打开站点的次数统计'
                }}</small>
              </div>
              <ElSegmented
                v-model="trendMetric"
                :options="trendMetricOptions"
                size="small"
                aria-label="站点明细指标"
                @change="changeTrendMetric"
              />
              <ElIcon v-if="siteStatLoading" class="is-loading"><Loading /></ElIcon>
            </div>
            <div v-if="selectedTrendDate && siteStatRows.length" class="trend-detail-list">
              <div class="trend-list-summary">
                <span
                  ><ElIcon><DataAnalysis /></ElIcon>记录
                  <b>{{ siteStatRows.length }}</b> 个站点</span
                >
                <span
                  >合计 <strong>{{ siteStatTotal }}</strong> 次{{ trendMetricLabel }}</span
                >
              </div>
              <ElTable :data="siteStatRows" size="small" class="trend-detail-table">
                <ElTableColumn label="#" width="52" align="center"
                  ><template #default="scope"
                    ><span class="trend-rank">{{ scope.$index + 1 }}</span></template
                  ></ElTableColumn
                >
                <ElTableColumn label="站点" min-width="190"
                  ><template #default="scope"
                    ><div class="trend-site-cell"
                      ><span class="trend-site-mark">{{
                        String(scope.row.name || '?')
                          .slice(0, 1)
                          .toUpperCase()
                      }}</span
                      ><b>{{ scope.row.name }}</b></div
                    ></template
                  ></ElTableColumn
                >
                <ElTableColumn label="分类" width="140"
                  ><template #default="scope"
                    ><ElTag size="small" effect="plain" type="info">{{
                      scope.row.category || '未分类'
                    }}</ElTag></template
                  ></ElTableColumn
                >
                <ElTableColumn :label="`${trendMetricLabel}次数`" width="120" align="right"
                  ><template #default="scope"
                    ><span class="trend-count" :class="`is-${trendMetric}`"
                      ><strong>{{ scope.row.count }}</strong
                      ><small>次</small></span
                    ></template
                  ></ElTableColumn
                >
                <ElTableColumn label="访问地址" min-width="220"
                  ><template #default="scope"
                    ><a
                      class="trend-site-url"
                      :href="scope.row.url"
                      target="_blank"
                      rel="noopener noreferrer"
                      ><ElIcon><Link /></ElIcon><span>{{ scope.row.url }}</span></a
                    ></template
                  ></ElTableColumn
                >
              </ElTable>
            </div>
            <ElEmpty
              v-else-if="selectedTrendDate && !siteStatLoading"
              :description="`该日暂无站点${trendMetricLabel}记录`"
              :image-size="56"
            />
          </div>
        </ElCard>
        <ElCard shadow="never" class="art-card"
          ><template #header
            ><div class="card-head"
              ><b>站点运营</b><ElTag type="success">{{ data.stats.activeSites }} 个显示</ElTag></div
            ></template
          ><div class="mini-list"
            ><div
              ><span>显示站点</span><strong>{{ data.stats.activeSites }}</strong></div
            ><div
              ><span>隐藏站点</span><strong>{{ data.stats.hiddenSites }}</strong></div
            ><div
              ><span>今日点击</span><strong>{{ data.stats.todayClicks }}</strong></div
            ><div
              ><span>累计点击</span><strong>{{ data.stats.totalClicks }}</strong></div
            ></div
          ></ElCard
        >
      </div>
      <div class="content-grid lower-grid"
        ><ElCard shadow="never" class="art-card"
          ><template #header
            ><div class="card-head"
              ><b>最近添加的站点</b
              ><ElButton text type="primary" @click="go('Sites')">查看全部</ElButton></div
            ></template
          ><ElTable :data="data.sites.slice(0, 6)" size="small"
            ><ElTableColumn prop="name" label="名称" min-width="180" /><ElTableColumn
              prop="category"
              label="分类"
              width="120"
            /><ElTableColumn label="状态" width="90"
              ><template #default="scope"
                ><ElTag :type="scope.row.active == 1 ? 'success' : 'info'">{{
                  scope.row.active == 1 ? '显示' : '隐藏'
                }}</ElTag></template
              ></ElTableColumn
            ></ElTable
          ></ElCard
        ><ElCard shadow="never" class="art-card"
          ><template #header
            ><div class="card-head"
              ><b>最新操作</b><ElButton text type="primary" @click="go('Logs')">日志</ElButton></div
            ></template
          ><ElTimeline
            ><ElTimelineItem
              v-for="log in data.logs.slice(0, 5)"
              :key="log.id"
              :timestamp="formatTime(log.addtime)"
              >{{ log.action }} · {{ log.detail || log.target }}</ElTimelineItem
            ></ElTimeline
          ></ElCard
        ></div
      >
    </template>

    <template v-else-if="pageName === 'Settings'"
      ><PageTitle
        icon="Setting"
        title="快捷设置"
        desc="站点基础资料、搜索入口、在线统计与备案信息。"
      /><ElCard shadow="never" class="art-card"
        ><ElTabs v-model="settingsTab"
          ><ElTabPane label="基本设置" name="base"
            ><ElForm label-position="top" class="form-grid"
              ><ElFormItem label="网站名称"><ElInput v-model="settingsDraft.sitename" /></ElFormItem
              ><ElFormItem label="标题栏后缀"><ElInput v-model="settingsDraft.title" /></ElFormItem
              ><ElFormItem label="网站描述"
                ><ElInput v-model="settingsDraft.description" /></ElFormItem
              ><ElFormItem label="客服 QQ"><ElInput v-model="settingsDraft.kfqq" /></ElFormItem
              ><ElFormItem label="导航介绍语"
                ><ElInput v-model="settingsDraft.announcement" /></ElFormItem
              ><ElFormItem label="手机 QQ 跳转浏览器"
                ><ElSelect v-model="settingsDraft.qqjump"
                  ><ElOption label="关闭" value="0" /><ElOption
                    label="开启"
                    value="1" /></ElSelect></ElFormItem
              ><ElFormItem label="网站 LOGO"
                ><ElInput
                  v-model="settingsDraft.site_logo"
                  placeholder="填写图片地址" /></ElFormItem
              ><ElFormItem label="公安网安备"
                ><ElInput
                  v-model="settingsDraft.gongan_beian"
                  placeholder="例如：京公网安备 11000002000001号" /></ElFormItem
              ><ElFormItem label="公安备案链接"
                ><ElInput
                  v-model="settingsDraft.gongan_beian_url"
                  placeholder="https://beian.mps.gov.cn/" /></ElFormItem></ElForm
            ><div class="form-actions"
              ><ElButton type="primary" @click="saveSettings">保存基本设置</ElButton></div
            ></ElTabPane
          ><ElTabPane label="搜索与统计" name="stats"
            ><ElForm label-position="top" class="form-grid"
              ><ElFormItem label="本站搜索功能"
                ><ElSwitch
                  v-model="settingsDraft.site_search_enabled"
                  active-value="1"
                  inactive-value="0" /></ElFormItem
              ><ElFormItem label="在线统计显示"
                ><ElSwitch
                  v-model="settingsDraft.online_stats_enabled"
                  active-value="1"
                  inactive-value="0" /></ElFormItem
              ><ElFormItem label="在线统计数据模式"
                ><ElSelect v-model="settingsDraft.online_stats_mode"
                  ><ElOption label="真实数据" value="real" /><ElOption
                    label="随机演示数据"
                    value="random" /></ElSelect></ElFormItem></ElForm
            ><div class="form-actions"
              ><ElButton type="primary" @click="saveSettings">保存统计设置</ElButton></div
            ></ElTabPane
          ><ElTabPane label="页脚备案" name="footer"
            ><ElForm label-position="top"
              ><ElFormItem label="网站底部备案文本"
                ><ElInput
                  v-model="settingsDraft.footer_text"
                  type="textarea"
                  :rows="5" /></ElFormItem
              ><ElFormItem label="网站 ICP 备案"><ElInput v-model="settingsDraft.icp" /></ElFormItem
              ><ElFormItem label="公安网安备"
                ><ElInput v-model="settingsDraft.gongan_beian" /></ElFormItem></ElForm
            ><div class="form-actions"
              ><ElButton type="primary" @click="saveSettings">保存备案设置</ElButton></div
            ></ElTabPane
          ></ElTabs
        ></ElCard
      ></template
    >

    <template v-else-if="pageName === 'Sites'"
      ><PageTitle icon="Globe" title="站点管理" desc="维护前台导航站点、分类和显示状态。"
        ><ElButton type="primary" @click="openSite()"
          ><ElIcon><Plus /></ElIcon>新增站点</ElButton
        ></PageTitle
      ><ElCard shadow="never" class="art-card"
        ><div class="toolbar"
          ><ElInput
            v-model="siteKeyword"
            clearable
            placeholder="搜索名称、URL或描述"
            style="max-width: 320px"
          /><ElSelect v-model="siteCategory" clearable placeholder="全部分类" style="width: 160px"
            ><ElOption
              v-for="cat in data.categories"
              :key="cat.id"
              :label="cat.name"
              :value="cat.name" /></ElSelect
          ><ElButton type="primary" @click="refresh">筛选</ElButton></div
        ><ElTable :data="filteredSites" row-key="id" stripe
          ><ElTableColumn label="图标" width="76" align="center"
            ><template #default="scope"
              ><div class="site-list-icon-cell"
                ><img
                  v-if="siteIconPrimarySource(scope.row)"
                  class="site-list-favicon"
                  :src="siteIconPrimarySource(scope.row)"
                  :alt="`${scope.row.name} 图标`"
                  decoding="async"
                  referrerpolicy="no-referrer"
                  @load="validateSiteIconImage($event, scope.row)"
                  @error="loadNextSiteIcon($event, scope.row)" />
                <span v-else class="site-list-favicon site-list-favicon-fallback" aria-hidden="true">{{
                  siteIconTextFallback(scope.row)
                }}</span></div
              ></template
            ></ElTableColumn
          ><ElTableColumn prop="name" label="名称" min-width="170" show-overflow-tooltip /><ElTableColumn
            prop="url"
            label="URL"
            min-width="240"
            show-overflow-tooltip /><ElTableColumn
            prop="category"
            label="分类"
            width="130" /><ElTableColumn prop="sort" label="排序" width="80" /><ElTableColumn
            label="状态"
            width="100"
            ><template #default="scope"
              ><ElSwitch
                v-model="scope.row.active"
                :active-value="1"
                :inactive-value="0"
                @change="saveSiteStatus(scope.row, $event)" /></template></ElTableColumn
          ><ElTableColumn label="操作" width="196" fixed="right"
            ><template #default="scope"
              ><div class="site-row-actions"
                ><ElButton
                  class="site-row-edit"
                  size="small"
                  type="primary"
                  @click="openSite(scope.row)"
                  ><ElIcon><Edit /></ElIcon>编辑</ElButton
                ><ElTooltip content="删除站点" placement="top"
                  ><ElButton
                    class="site-row-delete"
                    size="small"
                    plain
                    type="danger"
                    :aria-label="`删除站点 ${scope.row.name}`"
                    @click="removeSite(scope.row)"
                    ><ElIcon
                      ><Delete /></ElIcon></ElButton></ElTooltip></div></template></ElTableColumn></ElTable></ElCard
      ><ElDialog
        v-model="siteDialog"
        :title="siteForm.id ? '编辑站点' : '新增站点'"
        width="620px"
        @closed="resetSiteMetaState()"
        ><ElForm label-position="top" class="form-grid"
          ><ElFormItem label="名称"><ElInput v-model="siteForm.name" /></ElFormItem
          ><ElFormItem label="URL" class="site-url-item"
            ><ElInput
              v-model="siteForm.url"
              placeholder="例如：https://example.com"
              @input="scheduleSiteMeta"
              @blur="fetchSiteMeta"
              ><template #suffix
                ><ElIcon v-if="siteMetaLoading" class="is-loading"
                  ><Loading /></ElIcon></template></ElInput
            ><div
              class="site-meta-status"
              :class="siteMetaStatus"
              role="status"
              aria-live="polite"
              >{{ siteMetaMessage }}</div
            ></ElFormItem
          ><ElFormItem label="站点图标" class="site-icon-form-item"
            ><div class="site-icon-field"
              ><img
                v-if="siteIconPrimarySource(siteForm)"
                class="site-icon-preview"
                :src="siteIconPrimarySource(siteForm)"
                :alt="`${siteForm.name || '站点'} 图标预览`"
                decoding="async"
                referrerpolicy="no-referrer"
                @load="validateSiteIconImage($event, siteForm)"
                @error="loadNextSiteIcon($event, siteForm)" />
              <span v-else class="site-icon-preview site-icon-preview-fallback" aria-hidden="true">{{
                siteIconTextFallback(siteForm)
              }}</span
              ><div class="site-icon-input"
                ><ElInput
                  v-model="siteForm.icon"
                  clearable
                  placeholder="自动获取，也可输入图标图片 URL"
                  @update:model-value="markSiteIconManual"
                /><span>优先使用自动缓存图标；无法获取时会显示站点首字，不会留空。</span></div
              ><ElTooltip content="按当前网址重新获取图标" placement="top"
                ><ElButton
                  class="site-icon-refresh"
                  :loading="siteMetaLoading"
                  :disabled="!normalizeSiteMetaUrl(siteForm.url)"
                  aria-label="重新获取站点图标"
                  @click="refreshSiteIcon"
                  ><ElIcon><Refresh /></ElIcon></ElButton
                ></ElTooltip
              ></div
            ></ElFormItem
          ><ElFormItem label="分类"
            ><ElSelect v-model="siteForm.category"
              ><ElOption
                v-for="cat in data.categories"
                :key="cat.id"
                :label="cat.name"
                :value="cat.name" /></ElSelect></ElFormItem
          ><ElFormItem label="排序"><ElInputNumber v-model="siteForm.sort" :min="0" /></ElFormItem
          ><ElFormItem label="描述"
            ><ElInput
              v-model="siteForm.description"
              type="textarea"
              :rows="3"
              placeholder="用于卡片副标题，支持省略显示或跑马灯展示" /></ElFormItem
          ><ElFormItem label="描述跑马灯" class="marquee-settings-item"
            ><div class="marquee-setting-row"
              ><ElSwitch
                v-model="siteForm.desc_marquee"
                :active-value="1"
                :inactive-value="0"
              /><span class="setting-hint">开启后，前台卡片描述会循环滚动</span></div
            ></ElFormItem
          ><ElFormItem label="跑马灯速度"
            ><ElSelect v-model="siteForm.desc_speed"
              ><ElOption label="慢速 · 16 秒一轮" value="slow" /><ElOption
                label="正常 · 10 秒一轮"
                value="normal" /><ElOption label="快速 · 7 秒一轮" value="fast" /><ElOption
                label="极速 · 4 秒一轮"
                value="rapid" /></ElSelect></ElFormItem
          ><ElFormItem label="描述颜色" class="desc-color-item"
            ><div class="desc-color-picker"
              ><ElRadioGroup v-model="siteForm.desc_color"
                ><ElRadio
                  v-for="color in descColors"
                  :key="color.value"
                  :value="color.value"
                  class="desc-color-option"
                  ><span
                    class="desc-color-swatch"
                    :class="`is-${color.value}`"
                    aria-hidden="true"
                  ></span
                  >{{ color.label }}</ElRadio
                ></ElRadioGroup
              ></div
            ></ElFormItem
          ><ElFormItem label="前台显示"
            ><ElSwitch
              v-model="siteForm.active"
              :active-value="1"
              :inactive-value="0" /></ElFormItem></ElForm
        ><template #footer
          ><ElButton @click="siteDialog = false">取消</ElButton
          ><ElButton type="primary" @click="saveSite(siteForm)">保存</ElButton></template
        ></ElDialog
      ></template
    >

    <template v-else-if="pageName === 'Categories'">
      <PageTitle
        icon="Folder"
        title="分类管理"
        desc="管理前台标签栏显示的分类、图标、排序和启用状态。"
        ><ElButton type="primary" @click="openCategory()"
          ><ElIcon><Plus /></ElIcon>新增分类</ElButton
        ></PageTitle
      >
      <ElCard shadow="never" class="art-card">
        <ElTable :data="data.categories" stripe>
          <ElTableColumn label="图标" width="90"
            ><template #default="scope"
              ><span class="category-list-icon" :aria-label="`${scope.row.name}图标`">{{
                scope.row.icon || '·'
              }}</span></template
            ></ElTableColumn
          >
          <ElTableColumn prop="name" label="名称" min-width="220" /><ElTableColumn
            prop="sort"
            label="排序"
            width="100"
          />
          <ElTableColumn label="状态" width="110"
            ><template #default="scope"
              ><ElTag :type="scope.row.active == 1 ? 'success' : 'info'">{{
                scope.row.active == 1 ? '启用' : '停用'
              }}</ElTag></template
            ></ElTableColumn
          >
          <ElTableColumn label="操作" width="196" fixed="right" align="right" header-align="right"
            ><template #default="scope"
              ><div class="site-row-actions"
                ><ElButton
                  class="site-row-edit"
                  size="small"
                  type="primary"
                  @click="openCategory(scope.row)"
                  ><ElIcon><Edit /></ElIcon>编辑</ElButton
                ><ElTooltip content="删除分类" placement="top"
                  ><ElButton
                    class="site-row-delete"
                    size="small"
                    plain
                    type="danger"
                    :aria-label="`删除分类 ${scope.row.name}`"
                    @click="removeCategory(scope.row)"
                    ><ElIcon><Delete /></ElIcon></ElButton></ElTooltip></div></template
          ></ElTableColumn>
        </ElTable>
      </ElCard>
      <ElDialog
        v-model="categoryDialog"
        :title="categoryForm.id ? '编辑分类' : '新增分类'"
        width="520px"
        class="category-dialog"
        @closed="closeCategoryIconPicker"
      >
        <ElForm label-position="top">
          <ElFormItem label="分类名称"><ElInput v-model="categoryForm.name" /></ElFormItem>
          <ElFormItem label="分类图标" class="category-icon-form-item">
            <div class="category-icon-field">
              <span class="category-icon-preview" aria-hidden="true">{{
                categoryForm.icon || '＋'
              }}</span>
              <ElInput
                v-model="categoryForm.icon"
                maxlength="20"
                placeholder="输入 Emoji、文字或符号"
                aria-label="自定义分类图标"
                @input="closeCategoryIconPicker"
              />
              <ElPopover
                v-model:visible="categoryIconPickerVisible"
                placement="bottom-end"
                trigger="click"
                :width="categoryIconPickerWidth"
              >
                <template #reference
                  ><ElButton class="category-icon-trigger" aria-label="选择彩色分类图标"
                    ><ElIcon><Grid /></ElIcon><span>选择图标</span></ElButton
                  ></template
                >
                <div class="category-icon-picker">
                  <div class="category-icon-picker-head"
                    ><div><strong>彩色图标库</strong><small>共 100 个，点击即可选用</small></div
                    ><ElTag size="small" type="primary" effect="light">{{
                      filteredCategoryIcons.length
                    }}</ElTag></div
                  >
                  <ElInput
                    v-model="categoryIconKeyword"
                    clearable
                    placeholder="搜索图标名称"
                    aria-label="搜索彩色分类图标"
                    ><template #prefix
                      ><ElIcon><Search /></ElIcon></template
                  ></ElInput>
                  <div class="category-icon-grid" role="listbox" aria-label="彩色分类图标">
                    <button
                      v-for="item in filteredCategoryIcons"
                      :key="item.icon"
                      type="button"
                      class="category-icon-option"
                      :class="{ 'is-selected': categoryForm.icon === item.icon }"
                      role="option"
                      :aria-selected="categoryForm.icon === item.icon"
                      :aria-label="item.label"
                      :title="item.label"
                      @click="chooseCategoryIcon(item.icon)"
                      >{{ item.icon }}</button
                    >
                  </div>
                  <ElEmpty
                    v-if="!filteredCategoryIcons.length"
                    description="没有匹配的图标"
                    :image-size="46"
                  />
                </div>
              </ElPopover>
            </div>
            <div class="category-icon-help"
              >也可以直接输入自己的 Emoji、中文或符号，最多 20 个字符。</div
            >
          </ElFormItem>
          <ElFormItem label="排序"
            ><ElInputNumber v-model="categoryForm.sort" :min="0"
          /></ElFormItem>
          <ElFormItem label="启用"
            ><ElSwitch v-model="categoryForm.active" :active-value="1" :inactive-value="0"
          /></ElFormItem>
        </ElForm>
        <template #footer
          ><ElButton @click="categoryDialog = false">取消</ElButton
          ><ElButton type="primary" @click="saveCategory">保存</ElButton></template
        >
      </ElDialog>
    </template>

    <template v-else-if="pageName === 'Links'">
      <PageTitle
        icon="Connection"
        title="友链管理"
        desc="审核前台提交的友链申请，并维护已通过的合作站点。"
      >
        <div class="link-page-actions">
          <div class="link-page-control is-entry">
            <span class="link-feature-icon" aria-hidden="true"
              ><ElIcon><Link /></ElIcon
            ></span>
            <span class="link-feature-copy"
              ><b>前台申请入口</b><small>允许访客提交友链</small></span
            >
            <ElSwitch
              v-model="linkEnabled"
              inline-prompt
              active-text="开"
              inactive-text="关"
              aria-label="前台友链申请入口"
              @change="toggleLinks"
            />
          </div>
          <div class="link-page-control is-mail" :class="{ 'is-ready': linkMailConfigured }">
            <span class="link-feature-icon" aria-hidden="true"
              ><ElIcon><Message /></ElIcon
            ></span>
            <span class="link-feature-copy"
              ><b>邮件推送提醒</b><small>{{ linkMailRecipient }}</small></span
            >
            <ElSwitch
              v-model="linkMailNotifyEnabled"
              :loading="linkMailSaving"
              inline-prompt
              active-text="开"
              inactive-text="关"
              aria-label="友链申请邮件推送提醒"
              @change="toggleLinkMailNotify"
            />
            <ElTooltip content="配置邮件通知" placement="top"
              ><ElButton
                class="link-config-button"
                circle
                plain
                type="primary"
                aria-label="配置邮件通知"
                @click="go('SettingsMail')"
                ><ElIcon><Setting /></ElIcon></ElButton
            ></ElTooltip>
          </div>
        </div>
      </PageTitle>
      <ElAlert
        v-if="!linkMailConfigured"
        class="link-mail-alert"
        title="邮件提醒尚未就绪"
        type="warning"
        :closable="false"
        show-icon
      >
        请先在“邮件通知”中开启邮件服务并完成发件邮箱、SMTP 和收件邮箱配置。
      </ElAlert>
      <ElCard shadow="never" class="art-card"
        ><template #header
          ><div class="card-head"
            ><b>待审核申请</b
            ><ElBadge :value="pendingLinks.length" type="warning" /></div></template
        ><ElEmpty v-if="!pendingLinks.length" description="暂无待审核申请" /><ElTable
          v-else
          :data="pendingLinks"
          stripe
          ><ElTableColumn prop="name" label="名称" /><ElTableColumn
            prop="url"
            label="URL"
            min-width="220"
            show-overflow-tooltip
          /><ElTableColumn prop="description" label="描述" min-width="200" /><ElTableColumn
            label="操作"
            width="160"
            ><template #default="scope"
              ><ElButton type="success" size="small" @click="auditLink(scope.row, 1)">通过</ElButton
              ><ElButton type="danger" plain size="small" @click="auditLink(scope.row, 2)"
                >拒绝</ElButton
              ></template
            ></ElTableColumn
          ></ElTable
        ></ElCard
      >
      <ElCard shadow="never" class="art-card"
        ><template #header
          ><div class="card-head"
            ><b>已通过友链</b
            ><ElBadge :value="approvedLinks.length" type="success" /></div></template
        ><ElEmpty v-if="!approvedLinks.length" description="暂无已通过友链" /><ElTable
          v-else
          :data="approvedLinks"
          stripe
          ><ElTableColumn prop="name" label="名称" /><ElTableColumn
            prop="url"
            label="URL"
            min-width="260"
          /><ElTableColumn prop="category" label="分类" /><ElTableColumn label="操作" width="100"
            ><template #default="scope"
              ><ElButton text type="danger" @click="removeLink(scope.row)">删除</ElButton></template
            ></ElTableColumn
          ></ElTable
        ></ElCard
      >
    </template>

    <template v-else-if="pageName === 'Ads'"
      ><PageTitle
        icon="Picture"
        title="广告管理"
        desc="管理广告区域开关、素材地址、跳转链接和展示位置。"
        ><ElSwitch
          v-model="adsDraft.ad_enabled"
          active-value="1"
          inactive-value="0"
          active-text="广告总开关" /></PageTitle
      ><ElCard shadow="never" class="art-card"
        ><ElForm label-position="top" class="form-grid"
          ><ElFormItem label="广告位置"
            ><ElSelect v-model="adsDraft.ad_position"
              ><ElOption label="搜索栏下方" value="below_search" /><ElOption
                label="内容区域"
                value="content" /></ElSelect></ElFormItem
          ><ElFormItem label="下方区域显示"
            ><ElSwitch
              v-model="adsDraft.ad_show_below"
              active-value="1"
              inactive-value="0" /></ElFormItem
          ><ElFormItem label="右侧区域显示"
            ><ElSwitch
              v-model="adsDraft.ad_show_right"
              active-value="1"
              inactive-value="0" /></ElFormItem
          ><ElFormItem label="左侧区域显示"
            ><ElSwitch
              v-model="adsDraft.ad_show_left"
              active-value="1"
              inactive-value="0" /></ElFormItem
          ><ElFormItem label="主广告图片"
            ><ElInput v-model="adsDraft.ad_image" placeholder="上传后自动回填地址" /><input
              class="native-file"
              type="file"
              accept="image/*"
              @change="uploadAd($event, 'ad_image', 'below_search')" /></ElFormItem
          ><ElFormItem label="主广告链接"><ElInput v-model="adsDraft.ad_link" /></ElFormItem
          ><ElFormItem label="广告标题"><ElInput v-model="adsDraft.ad_title" /></ElFormItem
          ><ElFormItem label="广告替代文本"
            ><ElInput v-model="adsDraft.ad_alt" /></ElFormItem></ElForm
        ><div class="form-actions"
          ><ElButton type="primary" @click="saveAds">保存广告设置</ElButton></div
        ></ElCard
      ></template
    >

    <template v-else-if="pageName === 'Logs'"
      ><PageTitle
        icon="Document"
        title="操作日志"
        desc="记录后台设置、内容维护和数据操作，用于安全审计与问题追踪。"
        ><ElButton type="danger" plain @click="clearLogs"
          ><ElIcon><Delete /></ElIcon>清空日志</ElButton
        ></PageTitle
      ><ElCard shadow="never" class="art-card"
        ><div class="toolbar"
          ><ElSelect v-model="logAction" clearable placeholder="全部操作" style="width: 150px"
            ><ElOption
              v-for="item in logActions"
              :key="item"
              :label="item"
              :value="item" /></ElSelect
          ><ElSelect v-model="logTarget" clearable placeholder="全部对象" style="width: 150px"
            ><ElOption
              v-for="item in logTargets"
              :key="item"
              :label="item"
              :value="item" /></ElSelect
          ><ElButton type="primary" @click="refresh">筛选</ElButton
          ><ElText type="info">共 {{ data.logs.length }} 条记录</ElText></div
        ><ElTable :data="filteredLogs" stripe
          ><ElTableColumn prop="addtime" label="时间" width="170"
            ><template #default="scope">{{
              formatTime(scope.row.addtime)
            }}</template></ElTableColumn
          ><ElTableColumn prop="action" label="操作" width="110" /><ElTableColumn
            prop="target"
            label="对象"
            width="110" /><ElTableColumn prop="detail" label="详情" min-width="300" /><ElTableColumn
            prop="ip"
            label="IP"
            width="150" /></ElTable></ElCard
    ></template>

    <template v-else-if="pageName === 'Backup'">
      <PageTitle
        icon="Coin"
        title="数据备份"
        desc="完整备份站点设置、导航内容、分类、统计与操作记录。"
      >
        <div class="backup-page-actions">
          <ElButton type="primary" :loading="backupCreating" @click="createBackup"
            ><ElIcon v-if="!backupCreating"><Plus /></ElIcon
            >{{ backupCreating ? '正在创建备份' : '创建新备份' }}</ElButton
          >
          <ElButton :disabled="backupCreating || restoreBusy" @click="chooseBackupFile"
            ><ElIcon><Upload /></ElIcon>恢复数据</ElButton
          >
          <input
            ref="backupFileInput"
            type="file"
            accept=".qifubak"
            hidden
            @change="selectBackupFile"
          />
        </div>
      </PageTitle>
      <section v-if="backupCreating" class="backup-progress-panel" aria-live="polite">
        <span class="backup-progress-orbit" aria-hidden="true"></span>
        <div class="backup-progress-copy">
          <div><strong>正在创建完整备份</strong><span>{{ backupProgress }}%</span></div>
          <small>{{ backupStageLabel }}</small>
          <ElProgress :percentage="backupProgress" :show-text="false" :stroke-width="5" />
        </div>
      </section>
      <div class="stat-grid">
        <div class="stat-card"
          ><div class="stat-icon blue"
            ><ElIcon><Files /></ElIcon></div
          ><div
            ><div class="stat-label">备份文件</div><strong>{{ data.backups.length }}</strong
            ><small>当前存储数量</small></div
          ></div
        >
        <div class="stat-card"
          ><div class="stat-icon green"
            ><ElIcon><Download /></ElIcon></div
          ><div
            ><div class="stat-label">文件总大小</div><strong>{{ backupSize }}</strong
            ><small>完整数据库备份</small></div
          ></div
        >
      </div>
      <ElCard shadow="never" class="art-card">
        <template #header
          ><div class="card-head"
            ><div><b>备份文件列表</b><small>下载的 .qifubak 文件可从右上角恢复</small></div></div
          ></template
        >
        <ElEmpty v-if="!data.backups.length" description="暂无备份文件" />
        <ElTable v-else :data="data.backups" stripe>
          <ElTableColumn prop="filename" label="文件名" min-width="260" show-overflow-tooltip />
          <ElTableColumn label="大小" width="120"
            ><template #default="scope">{{ formatSize(scope.row.size) }}</template></ElTableColumn
          >
          <ElTableColumn label="时间" width="180"
            ><template #default="scope">{{
              formatTime(scope.row.addtime)
            }}</template></ElTableColumn
          >
          <ElTableColumn label="操作" width="190"
            ><template #default="scope"
              ><ElButton text type="primary" @click="downloadBackup(scope.row)">下载</ElButton
              ><ElButton text type="danger" @click="deleteBackup(scope.row)"
                >删除</ElButton
              ></template
            ></ElTableColumn
          >
        </ElTable>
      </ElCard>

      <ElDialog
        v-model="restoreDialog"
        title="恢复备份数据"
        width="520px"
        :close-on-click-modal="false"
        @closed="resetBackupRestore"
      >
        <div class="restore-file-summary">
          <span class="restore-file-icon"
            ><ElIcon><Files /></ElIcon
          ></span>
          <span
            ><strong>{{ restoreFile?.name }}</strong
            ><small>{{ restoreFile ? formatSize(restoreFile.size) : '' }}</small></span
          >
        </div>
        <ElAlert title="恢复会覆盖当前站点数据" type="warning" :closable="false" show-icon>
          系统会先自动创建恢复前快照；校验、写入或提交失败时会自动回滚。当前管理员账号与密码不会被覆盖。
        </ElAlert>
        <ElForm label-position="top" class="restore-form">
          <ElFormItem label="当前管理员密码">
            <ElInput
              v-model="restorePassword"
              type="password"
              show-password
              maxlength="128"
              autocomplete="current-password"
              placeholder="用于确认本次高风险操作"
              @keyup.enter="restoreBackup"
            />
          </ElFormItem>
        </ElForm>
        <template #footer>
          <ElButton :disabled="restoreBusy" @click="restoreDialog = false">取消</ElButton>
          <ElButton
            type="danger"
            :loading="restoreBusy"
            :disabled="!restorePassword"
            @click="restoreBackup"
            >开始恢复</ElButton
          >
        </template>
      </ElDialog>
    </template>

    <template v-else-if="pageName === 'UserCenter'">
      <PageTitle icon="User" title="个人中心" desc="维护管理员资料、登录状态和个人偏好。" />
      <section class="profile-summary" aria-label="管理员资料摘要">
        <div class="profile-avatar-wrap">
          <img
            :src="profileState.user.avatar || defaultAvatar"
            alt="管理员头像"
            class="profile-avatar"
          />
          <button
            type="button"
            class="profile-avatar-action"
            title="更换头像"
            :disabled="profileAvatarUploading"
            @click="chooseProfileAvatar"
            ><ElIcon :class="{ 'is-loading': profileAvatarUploading }"
              ><Loading v-if="profileAvatarUploading" /><Upload v-else /></ElIcon
          ></button>
          <input
            ref="profileAvatarInput"
            type="file"
            accept="image/jpeg,image/png,image/gif,image/webp"
            hidden
            @change="uploadProfileAvatar"
          />
        </div>
        <div class="profile-summary-main">
          <div class="profile-title-line"
            ><h2>{{ profileForm.nickname || '管理员' }}</h2
            ><ElTag type="primary" effect="light">{{ profileState.roleLabel }}</ElTag></div
          >
          <p>登录账号：{{ profileState.user.userName || data.user.userName }}</p>
        </div>
        <div class="profile-session-state"
          ><span class="profile-status-dot" aria-hidden="true"></span
          ><div
            ><strong>当前会话正常</strong
            ><small>{{ profileState.lastLoginIp || '本机登录' }}</small></div
          ></div
        >
      </section>

      <div class="profile-main-grid">
        <ElCard shadow="never" class="art-card profile-form-card">
          <template #header
            ><div class="card-head"
              ><div><b>基本资料</b><small>用于后台身份展示与系统通知</small></div></div
            ></template
          >
          <ElForm label-position="top" :model="profileForm">
            <ElFormItem label="管理员昵称"
              ><ElInput
                v-model="profileForm.nickname"
                maxlength="40"
                show-word-limit
                placeholder="例如：站点管理员"
            /></ElFormItem>
            <ElFormItem label="登录账号"
              ><ElInput
                :model-value="profileState.user.userName || data.user.userName"
                disabled
              /><div class="profile-field-hint">登录账号和密码请前往账号安全修改</div></ElFormItem
            >
            <ElFormItem label="通知邮箱"
              ><ElInput
                v-model="profileForm.notificationEmail"
                type="email"
                maxlength="120"
                placeholder="用于接收后台邮件通知"
            /></ElFormItem>
            <ElButton type="primary" :loading="profileSaving" @click="saveProfile"
              >保存个人资料</ElButton
            >
          </ElForm>
        </ElCard>

        <ElCard shadow="never" class="art-card profile-status-card">
          <template #header
            ><div class="card-head"
              ><div><b>登录状态</b><small>当前管理员账号的安全状态</small></div
              ><ElTag type="success" effect="plain">在线</ElTag></div
            ></template
          >
          <ElDescriptions :column="1" border>
            <ElDescriptionsItem label="账号角色">{{ profileState.roleLabel }}</ElDescriptionsItem>
            <ElDescriptionsItem label="最近登录">{{
              formatTime(profileState.lastLoginAt)
            }}</ElDescriptionsItem>
            <ElDescriptionsItem label="登录 IP">{{
              profileState.lastLoginIp || '-'
            }}</ElDescriptionsItem>
            <ElDescriptionsItem label="会话开始">{{
              formatTime(profileState.sessionStartedAt)
            }}</ElDescriptionsItem>
            <ElDescriptionsItem label="密码更新">{{
              formatTime(profileState.passwordChangedAt)
            }}</ElDescriptionsItem>
          </ElDescriptions>
          <div class="profile-security-action"
            ><div><strong>需要修改登录凭据？</strong><small>修改后当前会话会立即退出</small></div
            ><ElButton text type="primary" @click="go('Password')">账号安全</ElButton></div
          >
        </ElCard>
      </div>

      <ElCard shadow="never" class="art-card profile-preference-card">
        <template #header
          ><div class="card-head"
            ><div><b>个人偏好</b><small>保存管理员常用的后台显示习惯</small></div></div
          ></template
        >
        <ElForm label-position="top" class="profile-preference-grid">
          <ElFormItem label="默认进入页面"
            ><ElSelect v-model="profileForm.defaultPage"
              ><ElOption label="仪表盘" value="/dashboard/console" /><ElOption
                label="站点管理"
                value="/content/sites" /><ElOption
                label="操作日志"
                value="/maintenance/logs" /></ElSelect
          ></ElFormItem>
          <ElFormItem label="表格密度"
            ><ElSegmented v-model="profileForm.tableDensity" :options="profileDensityOptions"
          /></ElFormItem>
          <ElFormItem label="界面主题"
            ><ElSelect v-model="profileForm.theme"
              ><ElOption label="跟随系统" value="auto" /><ElOption
                label="浅色"
                value="light" /><ElOption label="深色" value="dark" /></ElSelect
          ></ElFormItem>
          <ElFormItem label="界面语言"
            ><ElSelect v-model="profileForm.language"
              ><ElOption label="简体中文" value="zh" /><ElOption
                label="English"
                value="en" /></ElSelect
          ></ElFormItem>
        </ElForm>
        <div class="form-actions"
          ><ElButton type="primary" :loading="profileSaving" @click="saveProfile"
            >保存个人偏好</ElButton
          ></div
        >
      </ElCard>

      <ElCard shadow="never" class="art-card profile-activity-card">
        <template #header
          ><div class="card-head"
            ><div><b>最近操作</b><small>最近 10 条管理员操作记录</small></div
            ><ElButton text type="primary" @click="go('Logs')">查看全部</ElButton></div
          ></template
        >
        <ElTable :data="profileState.recentLogs" :size="profileTableSize" stripe>
          <ElTableColumn prop="addtime" label="时间" width="170"
            ><template #default="scope">{{
              formatTime(scope.row.addtime)
            }}</template></ElTableColumn
          >
          <ElTableColumn prop="action" label="操作" width="100" />
          <ElTableColumn prop="target" label="对象" width="110" />
          <ElTableColumn prop="detail" label="详情" min-width="260" show-overflow-tooltip />
          <ElTableColumn prop="ip" label="IP" width="150" />
          <template #empty><ElEmpty description="暂无管理员操作记录" :image-size="64" /></template>
        </ElTable>
      </ElCard>
    </template>

    <template v-else-if="pageName === 'Password'"
      ><PageTitle
        icon="Lock"
        title="账号安全"
        desc="修改后台登录账号与密码，保存后当前会话会立即失效。"
      /><div class="security-grid"
        ><ElCard shadow="never" class="art-card security-note"
          ><ElResult
            icon="success"
            title="安全策略"
            sub-title="账号和密码都使用服务端校验与不可逆哈希保存。"
            ><template #extra
              ><ElDescriptions :column="1" border size="small"
                ><ElDescriptionsItem label="账号">6-18 位字母或数字</ElDescriptionsItem
                ><ElDescriptionsItem label="密码">6-18 位字母或数字</ElDescriptionsItem
                ><ElDescriptionsItem label="会话"
                  >修改后立即失效</ElDescriptionsItem
                ></ElDescriptions
              ></template
            ></ElResult
          ></ElCard
        ><ElCard shadow="never" class="art-card"
          ><ElForm
            ref="passwordFormRef"
            :model="passwordForm"
            :rules="passwordRules"
            label-position="top"
            ><ElFormItem label="原密码" prop="oldpwd"
              ><ElInput v-model="passwordForm.oldpwd" type="password" show-password /></ElFormItem
            ><ElFormItem label="新账号" prop="username"
              ><ElInput v-model="passwordForm.username" /></ElFormItem
            ><ElFormItem label="新密码" prop="newpwd"
              ><ElInput v-model="passwordForm.newpwd" type="password" show-password /></ElFormItem
            ><ElFormItem label="确认新密码" prop="newpwd2"
              ><ElInput v-model="passwordForm.newpwd2" type="password" show-password /></ElFormItem
            ><ElButton type="primary" @click="changePassword">确认修改</ElButton></ElForm
          ></ElCard
        ></div
      ></template
    >

    <template v-else-if="pageName === 'SystemInfo'">
      <PageTitle icon="Monitor" title="系统信息" />
      <ElCard shadow="never" class="art-card system-intro-card">
        <div class="system-intro-main">
          <span class="system-intro-icon" aria-hidden="true"
            ><ElIcon><Monitor /></ElIcon
          ></span>
          <div>
            <h2>祈福导航系统</h2>
            <p
              >一套面向个人与团队的可部署网址导航与内容运营系统。前台提供分类导航、站内搜索、友链申请和广告展示，后台集中完成内容维护、运营统计与系统管理。</p
            >
          </div>
        </div>
        <div class="system-intro-details">
          <section>
            <h3>技术栈</h3>
            <div class="system-intro-tags" aria-label="系统技术栈">
              <ElTag effect="plain">Vue 3</ElTag><ElTag effect="plain">TypeScript</ElTag
              ><ElTag effect="plain">Element Plus</ElTag><ElTag effect="plain">PHP 8.2+</ElTag
              ><ElTag effect="plain">SQLite / MySQL</ElTag
              ><ElTag effect="plain">Apache ECharts</ElTag>
            </div>
          </section>
          <section>
            <h3>开源许可</h3>
            <p
              >后台框架基于 Art Design Pro 构建。Vue 3、Element Plus、Vite 及 Art Design Pro 遵循
              MIT 协议；TypeScript 与 Apache ECharts 遵循 Apache-2.0 协议。</p
            >
          </section>
        </div>
        <div class="system-feature-list" aria-label="系统主要功能">
          <span>站点与分类</span><span>搜索导航</span><span>友链审核</span><span>广告管理</span
          ><span>访问统计</span><span>邮件提醒</span><span>备份恢复</span><span>在线更新</span>
        </div>
      </ElCard>
      <div class="system-info-grid" v-loading="systemInfoLoading">
        <ElCard shadow="never" class="art-card system-info-card">
          <template #header
            ><div class="card-head"
              ><div><b>程序与环境</b><small>当前站点实际运行信息</small></div
              ><ElTag type="primary" effect="light">{{ systemInfo.currentVersion }}</ElTag></div
            ></template
          >
          <ElDescriptions :column="systemInfoColumns" border>
            <ElDescriptionsItem label="程序名称">{{ systemInfo.productName }}</ElDescriptionsItem>
            <ElDescriptionsItem label="当前版本">{{
              systemInfo.currentVersion
            }}</ElDescriptionsItem>
            <ElDescriptionsItem label="PHP 版本">{{ systemInfo.phpVersion }}</ElDescriptionsItem>
            <ElDescriptionsItem label="数据库">{{ systemInfo.database }}</ElDescriptionsItem>
            <ElDescriptionsItem label="后台目录">{{
              systemInfo.adminDirectory
            }}</ElDescriptionsItem>
            <ElDescriptionsItem label="时区">{{ systemInfo.timezone }}</ElDescriptionsItem>
            <ElDescriptionsItem label="服务器时间">{{
              formatTime(systemInfo.serverTime)
            }}</ElDescriptionsItem>
            <ElDescriptionsItem label="技术栈">Vue 3 / TypeScript / PHP</ElDescriptionsItem>
          </ElDescriptions>
          <div class="system-project-links" aria-label="项目入口">
            <a
              class="system-project-link"
              href="https://github.com/JiangXinMao/qifudaohang"
              target="_blank"
              rel="noopener noreferrer"
            >
              <span class="system-project-link-icon is-github" aria-hidden="true"
                ><ArtSvgIcon icon="ri:github-fill" />
              </span>
              <span class="system-project-link-copy"
                ><b>开源地址</b><small>JiangXinMao/qifudaohang</small></span
              >
              <ElIcon class="system-project-link-arrow" aria-hidden="true"><Link /></ElIcon>
            </a>
            <a
              class="system-project-link"
              href="https://www.jiangxinmao.com"
              target="_blank"
              rel="noopener noreferrer"
            >
              <span class="system-project-link-icon is-site" aria-hidden="true"
                ><ElIcon><Monitor /></ElIcon
              ></span>
              <span class="system-project-link-copy"
                ><b>官网 <ElTag size="small" type="info" effect="plain">建设中</ElTag></b
                ><small>www.jiangxinmao.com</small></span
              >
              <ElIcon class="system-project-link-arrow" aria-hidden="true"><Link /></ElIcon>
            </a>
          </div>
        </ElCard>
        <ElCard shadow="never" class="art-card system-info-card">
          <template #header
            ><div class="card-head"
              ><div><b>程序基础服务环境</b><small>保障程序正常运行的服务端能力</small></div></div
            ></template
          >
          <div class="runtime-check-list">
            <div
              ><span><b>PHP Sodium</b><small>用于程序安全校验，请确保已安装并启用</small></span
              ><ElTag :type="systemInfo.sodiumReady ? 'success' : 'danger'" effect="light">{{
                systemInfo.sodiumReady ? '可用' : '未启用'
              }}</ElTag></div
            >
            <div
              ><span><b>PHP Zip</b><small>用于文件解压处理，请确保已安装并启用</small></span
              ><ElTag :type="systemInfo.zipReady ? 'success' : 'danger'" effect="light">{{
                systemInfo.zipReady ? '可用' : '未启用'
              }}</ElTag></div
            >
            <div
              ><span><b>安装状态</b><small>确认程序已经完成初始化安装</small></span
              ><ElTag :type="systemInfo.installLocked ? 'success' : 'warning'" effect="light">{{
                systemInfo.installLocked ? '已安装' : '待确认'
              }}</ElTag></div
            >
          </div>
        </ElCard>
      </div>
    </template>

    <template v-else>
      <PageTitle
        icon="InfoFilled"
        title="检查更新"
        desc="检查远程发布版本、执行在线更新并查看本地更新日志。"
      />
      <div class="about-sections">
        <ElCard
          v-if="updateState.updateAvailable"
          shadow="never"
          class="art-card update-compare-card"
        >
          <template #header>
            <div class="update-compare-head">
              <div><b>版本对照</b><small>确认版本变化后开始更新</small></div>
              <ElTag type="warning" effect="light">发现新版本</ElTag>
            </div>
          </template>
          <div class="update-compare-body">
            <div class="update-version-compare">
              <section class="update-version-side">
                <strong>{{ currentVersion }}</strong>
                <small>当前安装版本</small>
                <dl>
                  <div><dt>运行状态</dt><dd>正常</dd></div>
                  <div><dt>数据策略</dt><dd>保留现有数据</dd></div>
                </dl>
              </section>
              <span class="update-compare-arrow" aria-hidden="true">→</span>
              <section class="update-package-card">
                <div class="update-package-object" aria-hidden="true">
                  <span
                    ><ElIcon><Box /></ElIcon
                  ></span>
                </div>
                <div class="update-package-content">
                  <div class="update-package-head">
                    <span>程序更新包</span>
                    <b>最新</b>
                  </div>
                  <strong>{{ updateState.remoteVersion }}</strong>
                  <dl>
                    <div
                      ><dt>发布日期</dt><dd>{{ remoteReleaseDate }}</dd></div
                    >
                    <div><dt>安装方式</dt><dd>文件覆盖</dd></div>
                  </dl>
                </div>
              </section>
            </div>
            <div v-if="!updateProgress.visible" class="update-compare-safe"
              ><ElIcon><Check /></ElIcon
              ><span
                >新版本文件已完成安全检查；更新前自动备份，更新后保持现有数据库和安装状态。</span
              ></div
            >
            <div v-if="updateProgress.visible" class="update-progress-panel" aria-live="polite">
              <div class="update-progress-head">
                <span>{{ updateProgress.message }}</span>
                <strong>{{ updateProgress.percentage }}%</strong>
              </div>
              <ElProgress
                :percentage="updateProgress.percentage"
                :show-text="false"
                :stroke-width="8"
                :status="
                  updateProgress.status === 'failed'
                    ? 'exception'
                    : updateProgress.status === 'completed'
                      ? 'success'
                      : undefined
                "
              />
              <div class="update-phase-list">
                <div
                  v-for="(phase, index) in updatePhases"
                  :key="phase.key"
                  class="update-phase-item"
                  :class="updatePhaseState(phase, index)"
                >
                  <span class="update-phase-dot"
                    ><ElIcon v-if="updatePhaseState(phase, index) === 'done'"><Check /></ElIcon
                    ><ElIcon v-else-if="updatePhaseState(phase, index) === 'active'"
                      ><Loading /></ElIcon
                    ><ElIcon v-else-if="updatePhaseState(phase, index) === 'error'"
                      ><Close /></ElIcon
                    ><i v-else>{{ index + 1 }}</i></span
                  >
                  <span
                    ><b>{{ phase.label }}</b
                    ><small>{{ phase.note }}</small></span
                  >
                </div>
              </div>
            </div>
          </div>
          <div class="update-compare-footer">
            <span v-if="updateState.checkedAt" class="update-compare-checked"
              >最后检查：{{ formatTime(updateState.checkedAt) }}</span
            >
            <div class="update-compare-actions">
              <ElButton
                :loading="updateChecking"
                :disabled="updateInstalling"
                @click="checkForUpdates"
                ><ElIcon><Refresh /></ElIcon>重新检查</ElButton
              >
              <button
                class="update-compare-button"
                type="button"
                :disabled="updateChecking || updateInstalling"
                :aria-busy="updateInstalling"
                @click="applyOnlineUpdate"
              >
                <span
                  ><ElIcon :class="{ 'is-loading': updateInstalling }"
                    ><Loading v-if="updateInstalling" /><Download v-else /></ElIcon
                  >{{ updateInstalling ? '正在更新' : '立即更新' }}</span
                >
                <b>{{ updateState.remoteVersion }}</b>
              </button>
            </div>
          </div>
        </ElCard>
        <ElCard v-else shadow="never" class="art-card about-card">
          <ElResult
            :icon="updateState.serviceAvailable ? 'success' : 'error'"
            :title="updateServiceTitle"
            :sub-title="updateServiceSubtitle"
          >
            <template #extra>
              <div class="about-actions">
                <ElButton
                  type="primary"
                  :loading="updateChecking"
                  :disabled="updateInstalling"
                  @click="checkForUpdates"
                  >检查更新</ElButton
                >
                <ElButton plain disabled>当前版本 {{ currentVersion }}</ElButton>
              </div>
              <div v-if="updateState.checkedAt" class="update-checked-at"
                >最后检查：{{ formatTime(updateState.checkedAt) }}</div
              >
            </template>
          </ElResult>
        </ElCard>
        <ElCard shadow="never" class="art-card changelog-card">
          <template #header>
            <div class="changelog-head">
              <div
                ><b>更新日志</b><small>远程版本说明会自动写入本地数据库，最新记录置顶</small></div
              >
              <ElTag type="primary" effect="light">{{ latestTimelineVersion }}</ElTag>
            </div>
          </template>
          <div class="changelog-scroll" tabindex="0" aria-label="系统更新日志">
            <article
              v-for="(release, index) in updateHistory"
              :key="release.version"
              class="changelog-item"
            >
              <div class="changelog-marker" aria-hidden="true"><span></span><i></i></div>
              <div class="changelog-content">
                <header>
                  <div class="changelog-version">
                    <strong>{{ release.version }}</strong>
                    <ElTag v-if="index === 0" type="success" size="small" effect="light"
                      >最新记录</ElTag
                    >
                    <ElTag
                      v-if="release.version === currentVersion"
                      type="info"
                      size="small"
                      effect="plain"
                      >已安装</ElTag
                    >
                    <ElTag
                      v-if="release.source === 'official'"
                      type="success"
                      size="small"
                      effect="plain"
                      >正式版</ElTag
                    >
                    <ElTag
                      v-else-if="release.source === 'remote'"
                      type="primary"
                      size="small"
                      effect="plain"
                      >远程同步</ElTag
                    >
                  </div>
                  <time>{{ release.date }}</time>
                </header>
                <h3>{{ release.title }}</h3>
                <ul
                  ><li v-for="detail in release.details" :key="detail">{{ detail }}</li></ul
                >
              </div>
            </article>
          </div>
        </ElCard>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
  import {
    qifuAction,
    qifuApplyUpdate,
    qifuBootstrap,
    qifuCheckUpdates,
    qifuProfile,
    qifuRestoreBackup,
    qifuSaveProfile,
    qifuSiteMeta,
    qifuSiteStats,
    qifuSystemInfo,
    qifuTrend,
    qifuUpdateProgress,
    qifuUpdateStatus,
    qifuUpload,
    qifuUploadAvatar,
    type QifuBootstrap,
    type QifuProfile,
    type QifuSiteMetric,
    type QifuSiteStatRow,
    type QifuSystemInfo,
    type QifuUpdateProgress,
    type QifuUpdateStatus
  } from '@/api/qifu'
  import { qifuSuccess } from '@/utils/qifu-notification'
  import { refreshQifuBrand } from '@/composables/useQifuBrand'
  import { qifuChangeLog } from './qifu-changelog'
  import { qifuCategoryIcons } from './qifu-category-icons'
  import { useWindowSize } from '@vueuse/core'
  import {
    Box,
    Check,
    Close,
    Delete,
    Download,
    Edit,
    Files,
    Folder,
    Grid,
    Link,
    Loading,
    Message,
    Monitor,
    Plus,
    Refresh,
    Search,
    Setting,
    Upload,
    View,
    DataAnalysis
  } from '@element-plus/icons-vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'
  import { useUserStore } from '@/store/modules/user'
  import defaultAvatar from '@imgs/user/avatar.webp'
  import { useTheme } from '@/hooks/core/useTheme'
  import { LanguageEnum, SystemThemeEnum } from '@/enums/appEnum'
  import { useI18n } from 'vue-i18n'

  defineOptions({ name: 'QifuAdminPage' })

  const route = useRoute()
  const router = useRouter()
  const userStore = useUserStore()
  const { locale } = useI18n()
  const { switchThemeStyles } = useTheme()
  const { width: viewportWidth } = useWindowSize()
  const loading = ref(true)
  const trendRefreshing = ref(false)
  const selectedTrendDate = ref('')
  const trendMetric = ref<QifuSiteMetric>('views')
  const trendMetricOptions = [
    { label: '浏览量', value: 'views' },
    { label: '点击量', value: 'clicks' }
  ]
  const trendMetricLabel = computed(() => (trendMetric.value === 'views' ? '浏览' : '点击'))
  const siteStatRows = ref<QifuSiteStatRow[]>([])
  const siteStatTotal = computed(() =>
    siteStatRows.value.reduce((total, row) => total + Number(row.count || 0), 0)
  )
  const siteStatLoading = ref(false)
  const settingsTab = ref('base')
  const siteDialog = ref(false)
  const categoryDialog = ref(false)
  const categoryIconPickerVisible = ref(false)
  const categoryIconKeyword = ref('')
  const siteKeyword = ref('')
  const siteCategory = ref('')
  const logAction = ref('')
  const logTarget = ref('')
  const backupCreating = ref(false)
  const backupProgress = ref(0)
  const backupStage = ref<'idle' | 'preparing' | 'writing' | 'verifying' | 'complete'>('idle')
  const linkMailSaving = ref(false)
  const backupFileInput = ref<HTMLInputElement>()
  const restoreDialog = ref(false)
  const restoreFile = ref<File | null>(null)
  const restorePassword = ref('')
  const restoreBusy = ref(false)
  const data = reactive<QifuBootstrap>({
    user: {} as Api.Auth.UserInfo,
    settings: {},
    ads: {},
    stats: {
      todayViews: 0,
      yesterdayViews: 0,
      totalViews: 0,
      totalSites: 0,
      activeSites: 0,
      hiddenSites: 0,
      totalCategories: 0,
      todayClicks: 0,
      totalClicks: 0,
      trend: []
    },
    categories: [],
    sites: [],
    links: [],
    logs: [],
    backups: [],
    csrf: ''
  })
  const settingsDraft = reactive<Record<string, any>>({})
  const adsDraft = reactive<Record<string, any>>({})
  const siteForm = reactive<any>({
    id: 0,
    name: '',
    url: '',
    description: '',
    desc_marquee: 0,
    desc_speed: 'normal',
    desc_color: 'default',
    icon: '',
    category: '',
    sort: 10,
    active: 1
  })
  const descColors = [
    { value: 'default', label: '默认' },
    { value: 'red', label: '红色' },
    { value: 'orange', label: '橙色' },
    { value: 'yellow', label: '黄色' },
    { value: 'green', label: '绿色' },
    { value: 'cyan', label: '青色' },
    { value: 'blue', label: '蓝色' },
    { value: 'purple', label: '紫色' },
    { value: 'rainbow', label: '彩虹' }
  ]
  const siteMetaLoading = ref(false)
  const siteMetaStatus = ref<'idle' | 'loading' | 'success' | 'error'>('idle')
  const siteMetaMessage = ref('输入网址后自动获取网站名称和描述')
  let siteMetaTimer: ReturnType<typeof setTimeout> | undefined
  let siteMetaRequestId = 0
  let siteMetaLastUrl = ''
  let siteMetaAutoName = ''
  let siteMetaAutoDescription = ''
  let siteMetaAutoIcon = ''
  let refreshRequest: Promise<void> | null = null
  const savingSiteStatusIds = new Set<number>()
  const categoryForm = reactive<any>({ id: 0, name: '', icon: '', sort: 10, active: 1 })
  const categoryIconPickerWidth = computed(() =>
    Math.max(280, Math.min(380, viewportWidth.value - 48))
  )
  const filteredCategoryIcons = computed(() => {
    const keyword = categoryIconKeyword.value.trim().toLocaleLowerCase()
    if (!keyword) return qifuCategoryIcons
    return qifuCategoryIcons.filter(
      (item) => item.label.toLocaleLowerCase().includes(keyword) || item.icon.includes(keyword)
    )
  })
  const passwordForm = reactive({ oldpwd: '', username: '', newpwd: '', newpwd2: '' })
  const passwordFormRef = ref<FormInstance>()
  const profileAvatarInput = ref<HTMLInputElement>()
  const profileSaving = ref(false)
  const profileAvatarUploading = ref(false)
  const profileState = reactive<QifuProfile>({
    user: {} as Api.Auth.UserInfo,
    roleLabel: '超级管理员',
    lastLoginAt: 0,
    lastLoginIp: '',
    passwordChangedAt: 0,
    sessionStartedAt: 0,
    sessionActive: true,
    preferences: {
      defaultPage: '/dashboard/console',
      tableDensity: 'default',
      theme: 'auto',
      language: 'zh'
    },
    recentLogs: []
  })
  const profileForm = reactive<{
    nickname: string
    notificationEmail: string
    defaultPage: QifuProfile['preferences']['defaultPage']
    tableDensity: QifuProfile['preferences']['tableDensity']
    theme: QifuProfile['preferences']['theme']
    language: QifuProfile['preferences']['language']
  }>({
    nickname: '',
    notificationEmail: '',
    defaultPage: '/dashboard/console',
    tableDensity: 'default',
    theme: 'auto',
    language: 'zh'
  })
  const profileDensityOptions = [
    { label: '紧凑', value: 'compact' },
    { label: '标准', value: 'default' },
    { label: '宽松', value: 'comfortable' }
  ]
  const profileTableSize = computed(() =>
    profileForm.tableDensity === 'compact'
      ? 'small'
      : profileForm.tableDensity === 'comfortable'
        ? 'large'
        : 'default'
  )
  const pageName = computed(() => String(route.name || 'Console'))
  const updateChecking = ref(false)
  const updateInstalling = ref(false)
  const systemInfoLoading = ref(false)
  const systemInfo = reactive<QifuSystemInfo>({
    productName: '-',
    currentVersion: '-',
    phpVersion: '-',
    database: '-',
    timezone: '-',
    serverTime: 0,
    adminDirectory: '-',
    sodiumReady: false,
    zipReady: false,
    installLocked: false
  })
  const systemInfoColumns = computed(() => (viewportWidth.value <= 680 ? 1 : 2))
  let updateProgressPollToken = 0
  const updateProgress = reactive<QifuUpdateProgress & { visible: boolean }>({
    visible: false,
    requestId: '',
    phase: 'verify',
    percentage: 0,
    message: '准备开始在线更新',
    status: 'running',
    updatedAt: 0
  })
  const updatePhases = [
    { key: 'verify', label: '远程验签', note: '确认发布来源', threshold: 20 },
    { key: 'download', label: '下载安装包', note: '校验文件签名', threshold: 55 },
    { key: 'overlay', label: '覆盖程序', note: '备份并替换文件', threshold: 95 },
    { key: 'complete', label: '更新完成', note: '切换最新版本', threshold: 100 }
  ] as const
  const updateState = reactive<QifuUpdateStatus>({
    currentVersion: 'V1.5.0',
    latestVersion: qifuChangeLog[0]?.version || 'V1.5.0',
    remoteVersion: '',
    updateAvailable: false,
    serviceAvailable: true,
    checkedAt: 0,
    history: qifuChangeLog.map((entry) => ({
      ...entry,
      source: 'official' as const,
      recordedAt: 0
    }))
  })
  const currentVersion = computed(() => updateState.currentVersion || 'V1.5.0')
  const updateHistory = computed(() => updateState.history)
  const latestTimelineVersion = computed(
    () => updateHistory.value[0]?.version || currentVersion.value
  )
  const remoteReleaseDate = computed(
    () =>
      updateHistory.value.find((release) => release.version === updateState.remoteVersion)?.date ||
      '-'
  )
  const updateServiceTitle = computed(() =>
    updateState.updateAvailable
      ? '发现新版本'
      : updateState.serviceAvailable
        ? '在线更新服务正常'
        : '在线更新服务暂不可用'
  )
  const updateServiceSubtitle = computed(() =>
    updateState.updateAvailable
      ? `发现新版本 ${updateState.remoteVersion}，当前安装版本为 ${currentVersion.value}。`
      : updateState.serviceAvailable
        ? '系统已连接更新服务，可检查最新版本并查看本地历史更新日志。'
        : '暂时无法连接远程服务，已保留本地更新日志，请稍后重试。'
  )
  const trendPoints = computed(() => {
    const source = data.stats.trend || []
    const maxValue = Math.max(
      1,
      ...source.flatMap((point) => [Number(point.views) || 0, Number(point.clicks) || 0])
    )
    const width = 720
    const height = 220
    const left = 34
    const right = 12
    const top = 18
    const bottom = 34
    const plotWidth = width - left - right
    const plotHeight = height - top - bottom
    return source.map((point, index) => {
      const x =
        source.length > 1 ? left + (plotWidth * index) / (source.length - 1) : left + plotWidth / 2
      const views = Number(point.views) || 0
      const clicks = Number(point.clicks) || 0
      return {
        ...point,
        x,
        views,
        clicks,
        viewsY: top + plotHeight - (views / maxValue) * plotHeight,
        clicksY: top + plotHeight - (clicks / maxValue) * plotHeight
      }
    })
  })
  const trendViewsPolyline = computed(() =>
    trendPoints.value.map((point) => `${point.x},${point.viewsY}`).join(' ')
  )
  const trendClicksPolyline = computed(() =>
    trendPoints.value.map((point) => `${point.x},${point.clicksY}`).join(' ')
  )
  const trendHasData = computed(() =>
    trendPoints.value.some((point) => point.views > 0 || point.clicks > 0)
  )

  const PageTitle = (props: any, { slots }: any) =>
    h('section', { class: 'page-title' }, [
      h('div', { class: 'page-title-main' }, [
        h('div', [h('h2', props.title), h('p', props.desc)])
      ]),
      slots?.default ? h('div', { class: 'page-title-actions' }, slots.default()) : null
    ])

  const statCards = computed(() => {
    const todayViews = Number(data.stats.todayViews || 0)
    const yesterdayViews = Number(data.stats.yesterdayViews || 0)
    const viewDifference = todayViews - yesterdayViews
    const activeSites = Number(data.stats.activeSites || 0)
    const totalSites = Number(data.stats.totalSites || 0)
    const totalCategories = Number(data.stats.totalCategories || 0)

    return [
      {
        key: 'today',
        label: '今日浏览',
        value: todayViews,
        note: `昨日 ${yesterdayViews}`,
        icon: View,
        tone: 'blue',
        route: '',
        insightValue:
          viewDifference === 0
            ? '持平'
            : `${viewDifference > 0 ? '+' : '↓ '}${Math.abs(viewDifference)}`,
        insightText:
          viewDifference === 0
            ? '与昨日访问量一致'
            : `较昨日${viewDifference > 0 ? '增加' : '减少'}`,
        actionLabel: '刷新数据'
      },
      {
        key: 'total',
        label: '累计浏览量',
        value: Number(data.stats.totalViews || 0),
        note: '全站历史访问',
        icon: DataAnalysis,
        tone: 'green',
        route: '',
        insightValue: `+${todayViews}`,
        insightText: '今日新增访问',
        actionLabel: '更新统计'
      },
      {
        key: 'sites',
        label: '站点总数',
        value: totalSites,
        note: `${activeSites} 个前台显示`,
        icon: Monitor,
        tone: 'amber',
        route: 'Sites',
        insightValue: totalSites > 0 ? `${activeSites}/${totalSites}` : '建议',
        insightText: totalSites > 0 ? '站点当前正常展示' : '先添加常用站点',
        actionLabel: '管理站点'
      },
      {
        key: 'categories',
        label: '分类总数',
        value: totalCategories,
        note: '前台标签分类',
        icon: Folder,
        tone: 'cyan',
        route: 'Categories',
        insightValue: totalCategories > 0 ? '正常' : '建议',
        insightText: totalCategories > 0 ? '分类结构已启用' : '建立前台分类结构',
        actionLabel: '管理分类'
      }
    ]
  })
  const filteredSites = computed(() =>
    data.sites.filter(
      (item: any) =>
        (!siteCategory.value || item.category === siteCategory.value) &&
        (!siteKeyword.value ||
          `${item.name} ${item.url} ${item.description}`
            .toLowerCase()
            .includes(siteKeyword.value.toLowerCase()))
    )
  )
  const pendingLinks = computed(() => data.links.filter((item: any) => Number(item.status) === 0))
  const approvedLinks = computed(() => data.links.filter((item: any) => Number(item.status) === 1))
  const linkEnabled = computed({
    get: () => data.settings.show_link_apply === '1',
    set: (v) => {
      data.settings.show_link_apply = v ? '1' : '0'
    }
  })
  const linkMailNotifyEnabled = computed({
    get: () => data.settings.link_mail_notify === '1',
    set: (v) => {
      data.settings.link_mail_notify = v ? '1' : '0'
    }
  })
  const linkMailConfigured = computed(() => data.settings.mail_configured === '1')
  const linkMailRecipient = computed(() => {
    const recipient = String(data.settings.mail_to || '').trim()
    return recipient !== '' ? `收件人：${recipient}` : '尚未配置收件邮箱'
  })
  const filteredLogs = computed(() =>
    data.logs.filter(
      (item: any) =>
        (!logAction.value || item.action === logAction.value) &&
        (!logTarget.value || item.target === logTarget.value)
    )
  )
  const logActions = ['添加', '修改', '删除', '清理', '备份', '通过', '拒绝']
  const logTargets = ['站点', '分类', '设置', '友链', '广告', '数据库', '日志', '账号安全']
  const backupSize = computed(() =>
    formatSize(data.backups.reduce((sum: number, item: any) => sum + Number(item.size || 0), 0))
  )
  const backupStageLabel = computed(() => {
    const labels = {
      idle: '',
      preparing: '正在整理需要导出的站点与系统数据…',
      writing: '正在生成备份文件，请保持此页面打开…',
      verifying: '正在校验备份结果并写入备份清单…',
      complete: '备份已完成，正在刷新列表…'
    }
    return labels[backupStage.value]
  })
  const passwordRules: FormRules = {
    oldpwd: [{ required: true, message: '请输入原密码', trigger: 'blur' }],
    username: [{ required: true, message: '请输入新账号', trigger: 'blur' }],
    newpwd: [{ required: true, message: '请输入新密码', trigger: 'blur' }],
    newpwd2: [{ required: true, message: '请确认新密码', trigger: 'blur' }]
  }

  function refresh() {
    if (refreshRequest) return refreshRequest
    loading.value = true
    refreshRequest = (async () => {
      try {
        const result = await qifuBootstrap()
        Object.assign(data, result)
        Object.assign(settingsDraft, result.settings)
        Object.assign(adsDraft, result.ads)
        if (!passwordForm.username) passwordForm.username = result.user.userName
      } catch (error: any) {
        ElMessage.error(error?.message || '后台数据加载失败')
      } finally {
        loading.value = false
        refreshRequest = null
      }
    })()
    return refreshRequest
  }
  async function refreshTrend() {
    if (trendRefreshing.value) return
    trendRefreshing.value = true
    try {
      data.stats.trend = await qifuTrend()
      if (selectedTrendDate.value) await loadSiteStats(selectedTrendDate.value)
    } catch (error: any) {
      ElMessage.error(error?.message || '访问趋势刷新失败')
    } finally {
      trendRefreshing.value = false
    }
  }
  async function loadSiteStats(date: string) {
    selectedTrendDate.value = date
    siteStatLoading.value = true
    try {
      siteStatRows.value = await qifuSiteStats(date, trendMetric.value)
    } catch (error: any) {
      siteStatRows.value = []
      ElMessage.error(error?.message || `站点${trendMetricLabel.value}明细加载失败`)
    } finally {
      siteStatLoading.value = false
    }
  }
  async function changeTrendMetric() {
    if (selectedTrendDate.value) await loadSiteStats(selectedTrendDate.value)
  }
  function go(name: string) {
    router.push({ name })
  }
  function handleStatCard(item: { route?: string }) {
    if (item.route) {
      go(item.route)
      return
    }
    refresh()
  }
  function formatTime(value: number) {
    return value
      ? new Date(value * 1000).toLocaleString('zh-CN', { hour12: false }).replaceAll('/', '-')
      : '-'
  }
  function formatSize(value: number) {
    if (!value) return '0 KB'
    return value > 1024 * 1024
      ? `${(value / 1024 / 1024).toFixed(1)} MB`
      : `${(value / 1024).toFixed(1)} KB`
  }
  function applyProfile(result: QifuProfile) {
    Object.assign(profileState, result)
    profileForm.nickname = result.user.nickName || '管理员'
    profileForm.notificationEmail = result.user.email || ''
    profileForm.defaultPage = result.preferences.defaultPage
    profileForm.tableDensity = result.preferences.tableDensity
    profileForm.theme = result.preferences.theme
    profileForm.language = result.preferences.language
    Object.assign(data.user, result.user)
    userStore.setUserInfo(result.user)
  }
  async function loadProfile() {
    try {
      applyProfile(await qifuProfile())
    } catch (error: any) {
      ElMessage.error(error?.message || '个人资料加载失败')
    }
  }
  async function saveProfile() {
    if (profileSaving.value) return
    if (!profileForm.nickname.trim()) {
      ElMessage.warning('请填写管理员昵称')
      return
    }
    profileSaving.value = true
    try {
      const result = await qifuSaveProfile({
        ...profileForm,
        nickname: profileForm.nickname.trim(),
        notificationEmail: profileForm.notificationEmail.trim()
      })
      applyProfile(result)
      switchThemeStyles(profileForm.theme as SystemThemeEnum)
      locale.value = profileForm.language as LanguageEnum
      userStore.setLanguage(profileForm.language as LanguageEnum)
      qifuSuccess('个人资料已保存', '管理员资料与个人偏好已同步更新。')
    } catch (error: any) {
      ElMessage.error(error?.message || '个人资料保存失败')
    } finally {
      profileSaving.value = false
    }
  }
  function chooseProfileAvatar() {
    profileAvatarInput.value?.click()
  }
  async function uploadProfileAvatar(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (!file || profileAvatarUploading.value) return
    profileAvatarUploading.value = true
    try {
      applyProfile(await qifuUploadAvatar(file))
      qifuSuccess('头像已更新', '右上角个人菜单已同步显示新头像。')
    } catch (error: any) {
      ElMessage.error(error?.message || '头像上传失败')
    } finally {
      profileAvatarUploading.value = false
      input.value = ''
    }
  }
  async function loadUpdateStatus(showError = false) {
    try {
      Object.assign(updateState, await qifuUpdateStatus())
    } catch (error: any) {
      updateState.serviceAvailable = false
      if (showError) ElMessage.error(error?.message || '在线更新状态获取失败')
    }
  }
  async function loadSystemInfo() {
    if (systemInfoLoading.value) return
    systemInfoLoading.value = true
    try {
      Object.assign(systemInfo, await qifuSystemInfo())
    } catch (error: any) {
      ElMessage.error(error?.message || '系统信息获取失败')
    } finally {
      systemInfoLoading.value = false
    }
  }
  async function checkForUpdates() {
    if (updateChecking.value) return
    updateChecking.value = true
    try {
      Object.assign(updateState, await qifuCheckUpdates())
      if (!updateState.serviceAvailable)
        ElMessage.warning('远程更新服务暂时不可用，本地更新日志仍可查看')
      else if (updateState.updateAvailable)
        ElMessage.warning(`发现新版本 ${updateState.remoteVersion}，版本说明已写入本地更新日志`)
      else ElMessage.success(`当前已是最新版本 ${currentVersion.value}`)
    } catch (error: any) {
      updateState.serviceAvailable = false
      ElMessage.error(error?.message || '检查更新失败，请稍后重试')
    } finally {
      updateChecking.value = false
    }
  }
  function updatePhaseState(phase: (typeof updatePhases)[number], index: number) {
    if (updateProgress.status === 'failed') {
      const failedIndex = updatePhases.findIndex(
        (item) => updateProgress.percentage < item.threshold
      )
      if (failedIndex < 0) return index === updatePhases.length - 1 ? 'error' : 'done'
      if (index < failedIndex) return 'done'
      return index === failedIndex ? 'error' : 'pending'
    }
    if (updateProgress.percentage >= phase.threshold) return 'done'
    return updateProgress.phase === phase.key ? 'active' : 'pending'
  }
  async function pollOnlineUpdateProgress(operationId: string, token: number) {
    while (updateInstalling.value && updateProgressPollToken === token) {
      try {
        const progress = await qifuUpdateProgress(operationId)
        if (updateProgressPollToken !== token) return
        Object.assign(updateProgress, progress, { visible: true })
        if (progress.status === 'completed' || progress.status === 'failed') return
      } catch {
        // The update request remains authoritative; retry transient polling failures.
      }
      await new Promise((resolve) => window.setTimeout(resolve, 450))
    }
  }
  async function applyOnlineUpdate() {
    if (updateInstalling.value || !updateState.updateAvailable) return
    try {
      await ElMessageBox.confirm(
        `将下载并安装 ${updateState.remoteVersion}。程序文件会先备份再覆盖，网站配置、数据库和安装状态不会改变。`,
        '确认在线更新',
        {
          confirmButtonText: '立即更新',
          cancelButtonText: '取消',
          type: 'warning',
          distinguishCancelAndClose: true
        }
      )
    } catch {
      return
    }
    updateInstalling.value = true
    const operationId = `web-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`
    const pollToken = ++updateProgressPollToken
    Object.assign(updateProgress, {
      visible: true,
      requestId: operationId,
      phase: 'verify',
      percentage: 1,
      message: '正在准备远程验签',
      status: 'running',
      updatedAt: Math.floor(Date.now() / 1000)
    })
    void pollOnlineUpdateProgress(operationId, pollToken)
    try {
      const result = await qifuApplyUpdate(operationId)
      updateState.currentVersion = result.version
      updateState.updateAvailable = false
      Object.assign(updateProgress, {
        phase: 'complete',
        percentage: 100,
        message: '更新完成，正在重新载入后台',
        status: 'completed'
      })
      ElMessage.success(`已更新到 ${result.version}，正在重新载入后台`)
      window.setTimeout(() => window.location.reload(), 1800)
    } catch (error: any) {
      Object.assign(updateProgress, {
        phase: 'failed',
        message: error?.message || '在线更新失败，程序已自动回滚',
        status: 'failed'
      })
      ElMessage.error(error?.message || '在线更新失败，程序已自动回滚')
    } finally {
      updateInstalling.value = false
      updateProgressPollToken++
    }
  }
  function normalizeSiteMetaUrl(value: string) {
    let normalized = String(value || '').trim()
    if (!normalized) return ''
    if (!/^[a-z][a-z0-9+.-]*:\/\//i.test(normalized)) normalized = `https://${normalized}`
    try {
      const parsed = new URL(normalized)
      return ['http:', 'https:'].includes(parsed.protocol) && parsed.hostname
        ? parsed.toString()
        : ''
    } catch {
      return ''
    }
  }
  function resetSiteMetaState(url = '') {
    if (siteMetaTimer) clearTimeout(siteMetaTimer)
    siteMetaRequestId += 1
    siteMetaLoading.value = false
    siteMetaStatus.value = 'idle'
    siteMetaMessage.value = '输入网址后自动获取网站名称和描述'
    siteMetaLastUrl = normalizeSiteMetaUrl(url)
    siteMetaAutoName = ''
    siteMetaAutoDescription = ''
    const currentIcon = String(siteForm.icon || '').trim()
    siteMetaAutoIcon = /^site_icon\.php\?url=/i.test(currentIcon) ? currentIcon : ''
  }
  function siteIconFallback(name: string, url: string) {
    const label = String(name || '').trim().slice(0, 1) || (() => {
      try {
        return new URL(url).hostname.slice(0, 1).toUpperCase()
      } catch {
        return '网'
      }
    })()
    const palette = ['#2563eb', '#0f766e', '#7c3aed', '#b45309', '#be123c', '#0369a1']
    let seed = 0
    for (const char of `${name}|${url}`) seed = (seed * 31 + char.codePointAt(0)!) >>> 0
    const color = palette[seed % palette.length]
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="${color}"/><text x="32" y="40" text-anchor="middle" font-family="Arial, sans-serif" font-size="30" font-weight="700" fill="#fff">${label.replace(/[&<>]/g, '')}</text></svg>`
    return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`
  }
  function isSiteIconImage(value: unknown) {
    const icon = String(value || '').trim()
    return /^(https?:\/\/|\/|\.\.\/|site_icon\.php\?)/i.test(icon)
  }
  function siteIconSources(site: { name?: string; url?: string; icon?: string }) {
    const normalized = normalizeSiteMetaUrl(String(site.url || ''))
    const sources: string[] = []
    const manual = String(site.icon || '').trim()
    if (isSiteIconImage(manual))
      sources.push(/^site_icon\.php\?/i.test(manual) ? `../${manual}` : manual)
    if (normalized) {
      const parsed = new URL(normalized)
      const host = parsed.hostname
      sources.push(`../site_icon.php?url=${encodeURIComponent(normalized)}`)
      sources.push(`${parsed.origin}/favicon.ico`)
      sources.push(`https://favicon.im/${encodeURIComponent(host)}?larger=true`)
      sources.push(`https://www.google.com/s2/favicons?domain_url=${encodeURIComponent(normalized)}&sz=64`)
    }
    sources.push(siteIconFallback(String(site.name || ''), normalized))
    return [...new Set(sources)]
  }
  function siteIconPrimarySource(site: { name?: string; url?: string; icon?: string }) {
    return siteIconSources(site)[0] || ''
  }
  function siteIconTextFallback(site: { name?: string; url?: string; icon?: string }) {
    const icon = String(site.icon || '').trim()
    if (icon && !isSiteIconImage(icon)) return icon.slice(0, 2)
    return String(site.name || '').trim().slice(0, 1) || '网'
  }
  function loadNextSiteIcon(event: Event, site: { name?: string; url?: string; icon?: string }) {
    const image = event.currentTarget as HTMLImageElement
    const sources = siteIconSources(site)
    const next = Number(image.dataset.siteIconIndex || '0') + 1
    if (next >= sources.length) return
    image.dataset.siteIconIndex = String(next)
    image.src = sources[next]
  }
  function validateSiteIconImage(event: Event, site: { name?: string; url?: string; icon?: string }) {
    const image = event.currentTarget as HTMLImageElement
    if (image.naturalWidth < 12 || image.naturalHeight < 12) loadNextSiteIcon(event, site)
  }
  function applyAutomaticSiteIcon(force = false) {
    const normalized = normalizeSiteMetaUrl(siteForm.url)
    if (!normalized) return false
    if (String(siteForm.icon || '').trim() && siteForm.icon !== siteMetaAutoIcon) return false
    const refresh = force ? `&refresh=${Date.now()}` : ''
    const icon = `site_icon.php?url=${encodeURIComponent(normalized)}${refresh}`
    siteForm.icon = icon
    siteMetaAutoIcon = icon
    return true
  }
  function markSiteIconManual(value: string) {
    if (value !== siteMetaAutoIcon) siteMetaAutoIcon = ''
  }
  function refreshSiteIcon() {
    if (applyAutomaticSiteIcon(true)) ElMessage.success('已按当前网址重新获取图标')
  }
  function scheduleSiteMeta() {
    if (siteMetaTimer) clearTimeout(siteMetaTimer)
    const requestId = ++siteMetaRequestId
    siteMetaLoading.value = false
    siteMetaStatus.value = 'idle'
    siteMetaMessage.value = siteForm.url.trim()
      ? '输入完成后将自动获取网站名称和描述'
      : '输入网址后自动获取网站名称和描述'
    const normalized = normalizeSiteMetaUrl(siteForm.url)
    applyAutomaticSiteIcon()
    if (!normalized || normalized === siteMetaLastUrl) return
    siteMetaTimer = setTimeout(() => {
      if (requestId === siteMetaRequestId) fetchSiteMeta()
    }, 700)
  }
  async function fetchSiteMeta() {
    if (siteMetaTimer) clearTimeout(siteMetaTimer)
    const rawUrl = String(siteForm.url || '').trim()
    if (!rawUrl) return
    const normalized = normalizeSiteMetaUrl(rawUrl)
    if (!normalized) {
      siteMetaStatus.value = 'error'
      siteMetaMessage.value = '请输入正确的网站域名或 URL'
      return
    }
    if (normalized === siteMetaLastUrl && siteMetaStatus.value === 'success') return
    applyAutomaticSiteIcon()
    const requestId = ++siteMetaRequestId
    siteMetaLoading.value = true
    siteMetaStatus.value = 'loading'
    siteMetaMessage.value = '正在获取网站名称和描述…'
    try {
      const meta = await qifuSiteMeta(rawUrl)
      if (requestId !== siteMetaRequestId) return
      const filled: string[] = []
      const preserved: string[] = []
      if (meta.name) {
        if (!String(siteForm.name || '').trim() || siteForm.name === siteMetaAutoName) {
          siteForm.name = meta.name
          siteMetaAutoName = meta.name
          filled.push('名称')
        } else preserved.push('名称')
      }
      if (meta.description) {
        if (
          !String(siteForm.description || '').trim() ||
          siteForm.description === siteMetaAutoDescription
        ) {
          siteForm.description = meta.description
          siteMetaAutoDescription = meta.description
          filled.push('描述')
        } else preserved.push('描述')
      }
      if (meta.url) siteForm.url = meta.url
      applyAutomaticSiteIcon()
      siteMetaLastUrl = normalizeSiteMetaUrl(meta.url || normalized)
      siteMetaStatus.value = 'success'
      if (filled.length && preserved.length)
        siteMetaMessage.value = `已填入${filled.join('和')}，已填写的${preserved.join('和')}保持不变`
      else if (filled.length) siteMetaMessage.value = `已自动填入网站${filled.join('和')}`
      else if (preserved.length)
        siteMetaMessage.value = `网站信息已获取，已填写的${preserved.join('和')}保持不变`
      else siteMetaMessage.value = '网站信息已获取，请补充缺少的内容'
    } catch (error: any) {
      if (requestId !== siteMetaRequestId) return
      siteMetaStatus.value = 'error'
      siteMetaMessage.value = error?.message || '自动获取失败，请手动填写'
    } finally {
      if (requestId === siteMetaRequestId) siteMetaLoading.value = false
    }
  }
  function resetSite(row?: any) {
    Object.assign(
      siteForm,
      row
        ? { desc_marquee: 0, desc_speed: 'normal', desc_color: 'default', ...row }
        : {
            id: 0,
            name: '',
            url: '',
            description: '',
            desc_marquee: 0,
            desc_speed: 'normal',
            desc_color: 'default',
            icon: '',
            category: data.categories[0]?.name || '',
            sort: 10,
            active: 1
          }
    )
    resetSiteMetaState(siteForm.url)
    siteDialog.value = true
  }
  const openSite = resetSite
  function syncSiteStats() {
    data.stats.totalSites = data.sites.length
    data.stats.activeSites = data.sites.filter((site: any) => Number(site.active) === 1).length
    data.stats.hiddenSites = data.stats.totalSites - data.stats.activeSites
  }
  function closeCategoryIconPicker() {
    categoryIconPickerVisible.value = false
    categoryIconKeyword.value = ''
  }
  function chooseCategoryIcon(icon: string) {
    categoryForm.icon = icon
    closeCategoryIconPicker()
  }
  function resetCategory(row?: any) {
    Object.assign(
      categoryForm,
      row ? { ...row } : { id: 0, name: '', icon: '⭐', sort: 10, active: 1 }
    )
    closeCategoryIconPicker()
    categoryDialog.value = true
  }
  const openCategory = resetCategory
  async function saveSettings() {
    await qifuAction('save_settings', { settings: { ...settingsDraft } })
    await refreshQifuBrand()
    qifuSuccess('设置已保存', '新配置已同步到后台与前台。')
    await refresh()
  }
  async function saveAds() {
    await qifuAction('ad_save', { settings: { ...adsDraft } })
    qifuSuccess('广告设置已保存', '广告展示规则已按新配置更新。')
    await refresh()
  }
  async function saveSite(row: any) {
    const payload = { ...row, active: Number(row.active) === 1 ? 1 : 0 }
    resetSiteMetaState(payload.url)
    const result = await qifuAction<{ id: number }>('site_save', payload)
    const saved = { ...payload, id: Number(result?.id || payload.id) }
    const index = data.sites.findIndex((site: any) => Number(site.id) === saved.id)
    if (index >= 0) Object.assign(data.sites[index], saved)
    else data.sites.unshift(saved)
    syncSiteStats()
    siteDialog.value = false
    qifuSuccess('站点已保存', '站点信息与显示状态已更新。')
  }
  async function saveSiteStatus(row: any, value: unknown) {
    const id = Number(row.id)
    if (!id || savingSiteStatusIds.has(id)) return
    const active = Number(value) === 1 ? 1 : 0
    const previous = active === 1 ? 0 : 1
    savingSiteStatusIds.add(id)
    row.active = active
    try {
      await qifuAction('site_save', { ...row, active })
      syncSiteStats()
      qifuSuccess('站点状态已更新', active === 1 ? '站点已在前台显示。' : '站点已从前台隐藏。')
    } catch (error: any) {
      row.active = previous
      syncSiteStats()
      ElMessage.error(error?.message || '站点状态保存失败')
    } finally {
      savingSiteStatusIds.delete(id)
    }
  }
  async function removeSite(row: any) {
    await ElMessageBox.confirm(`确定删除“${row.name}”？`, '删除站点', { type: 'warning' })
    await qifuAction('site_delete', { id: row.id })
    qifuSuccess('站点已删除', '站点列表已同步更新。')
    await refresh()
  }
  async function saveCategory() {
    await qifuAction('category_save', { ...categoryForm })
    categoryDialog.value = false
    qifuSuccess('分类已保存', '前台分类结构已同步更新。')
    await refresh()
  }
  async function removeCategory(row: any) {
    await ElMessageBox.confirm(`确定删除“${row.name}”？`, '删除分类', { type: 'warning' })
    await qifuAction('category_delete', { id: row.id })
    qifuSuccess('分类已删除', '分类列表已同步更新。')
    await refresh()
  }
  async function toggleLinks() {
    await qifuAction('link_toggle', { enabled: linkEnabled.value ? 1 : 0 })
    qifuSuccess('友链入口设置已更新', '前台友链入口状态已同步。')
  }
  async function toggleLinkMailNotify(value: string | number | boolean) {
    const enabled = value === true || value === 1 || value === '1'
    if (enabled && !linkMailConfigured.value) {
      linkMailNotifyEnabled.value = false
      ElMessage.warning('请先在邮件通知中完成发件邮箱、SMTP 和收件邮箱配置')
      return
    }
    if (linkMailSaving.value) return
    linkMailSaving.value = true
    try {
      await qifuAction('link_mail_toggle', { enabled: enabled ? 1 : 0 })
      qifuSuccess(
        '邮件提醒设置已更新',
        enabled ? '新的友链申请将推送到收件邮箱。' : '新的友链申请将不再发送邮件提醒。'
      )
    } catch (error: any) {
      linkMailNotifyEnabled.value = !enabled
      ElMessage.error(error?.message || '邮件提醒设置保存失败')
    } finally {
      linkMailSaving.value = false
    }
  }
  async function auditLink(row: any, status: number) {
    await qifuAction('link_audit', { id: row.id, status })
    qifuSuccess(status === 1 ? '友链已通过' : '友链已拒绝', '审核结果已保存。')
    await refresh()
  }
  async function removeLink(row: any) {
    await ElMessageBox.confirm(`确定删除“${row.name}”？`, '删除友链', { type: 'warning' })
    await qifuAction('link_delete', { id: row.id })
    qifuSuccess('友链已删除', '友链列表已同步更新。')
    await refresh()
  }
  async function clearLogs() {
    await ElMessageBox.confirm('清空后无法恢复，确定继续吗？', '清空日志', { type: 'warning' })
    await qifuAction('logs_clear')
    qifuSuccess('日志已清理', '操作日志列表已更新。')
    await refresh()
  }
  async function createBackup() {
    if (backupCreating.value) return
    backupCreating.value = true
    backupProgress.value = 12
    backupStage.value = 'preparing'
    const startedAt = Date.now()
    const progressTimer = window.setInterval(() => {
      const increment = backupProgress.value < 46 ? 7 : 3
      backupProgress.value = Math.min(82, backupProgress.value + increment)
      if (backupProgress.value >= 46) backupStage.value = 'writing'
    }, 280)
    try {
      const result = await qifuAction<{ tableCount: number; rowCount: number }>('backup_create')
      backupStage.value = 'verifying'
      backupProgress.value = 92
      const remaining = Math.max(0, 1800 - (Date.now() - startedAt))
      if (remaining) await new Promise((resolve) => window.setTimeout(resolve, remaining))
      backupStage.value = 'complete'
      backupProgress.value = 100
      await new Promise((resolve) => window.setTimeout(resolve, 260))
      qifuSuccess(
        '完整备份已创建',
        `已导出 ${result.tableCount} 张数据表、${result.rowCount} 条记录。`
      )
      await refresh()
    } finally {
      window.clearInterval(progressTimer)
      backupCreating.value = false
      backupProgress.value = 0
      backupStage.value = 'idle'
    }
  }
  function chooseBackupFile() {
    if (!backupFileInput.value) return
    backupFileInput.value.value = ''
    backupFileInput.value.click()
  }
  function selectBackupFile(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (!file) return
    if (!file.name.toLowerCase().endsWith('.qifubak')) {
      ElMessage.error('请选择本系统下载的 .qifubak 备份文件')
      input.value = ''
      return
    }
    if (file.size <= 0 || file.size > 32 * 1024 * 1024) {
      ElMessage.error('备份文件大小必须在 32MB 以内')
      input.value = ''
      return
    }
    restoreFile.value = file
    restorePassword.value = ''
    restoreDialog.value = true
  }
  function resetBackupRestore() {
    if (restoreBusy.value) return
    restoreFile.value = null
    restorePassword.value = ''
    if (backupFileInput.value) backupFileInput.value.value = ''
  }
  async function restoreBackup() {
    if (!restoreFile.value || !restorePassword.value || restoreBusy.value) return
    restoreBusy.value = true
    try {
      const result = await qifuRestoreBackup(restoreFile.value, restorePassword.value)
      restoreDialog.value = false
      qifuSuccess(
        '数据恢复完成',
        `已恢复 ${result.tableCount} 张表、${result.rowCount} 条记录；恢复前快照为 ${result.safetyBackup}。`
      )
      await refresh()
    } catch (error: any) {
      ElMessage.error(error?.message || '数据恢复失败')
    } finally {
      restoreBusy.value = false
    }
  }
  async function deleteBackup(row: any) {
    await ElMessageBox.confirm('确定删除这个备份文件？', '删除备份', { type: 'warning' })
    await qifuAction('backup_delete', { id: row.id })
    qifuSuccess('备份已删除', '备份文件列表已同步更新。')
    await refresh()
  }
  function downloadBackup(row: any) {
    window.open(`./backup.php?action=download&id=${row.id}`, '_blank', 'noopener')
  }
  async function changePassword() {
    if (!passwordFormRef.value) return
    const valid = await passwordFormRef.value.validate().catch(() => false)
    if (!valid) return
    await qifuAction('password_change', { ...passwordForm })
    qifuSuccess('账号安全设置已更新', '登录凭据已更新，即将返回登录页。')
    setTimeout(() => router.push({ name: 'Login' }), 800)
  }
  async function uploadAd(event: Event, key: string, position: string) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (!file) return
    try {
      const result = await qifuUpload(file, key, position, data.csrf)
      adsDraft[key] = result.url
      qifuSuccess('图片上传成功', '广告素材地址已自动回填。')
    } catch (error: any) {
      ElMessage.error(error?.message || '图片上传失败')
    } finally {
      input.value = ''
    }
  }

  onMounted(refresh)
  watch(() => route.name, refresh)
  watch(
    pageName,
    (name) => {
      if (name.startsWith('About')) void loadUpdateStatus()
    },
    { immediate: true }
  )
  watch(
    pageName,
    (name) => {
      if (name === 'SystemInfo') void loadSystemInfo()
    },
    { immediate: true }
  )
  watch(
    pageName,
    (name) => {
      if (name === 'UserCenter') void loadProfile()
    },
    { immediate: true }
  )
</script>

<style scoped>
  .qifu-admin-page {
    --qifu-primary: var(--main-color);

    color: var(--art-gray-800);
  }

  .page-loading {
    padding: 24px;
    background: var(--art-bg-color);
    border-radius: 8px;
  }

  :deep(.page-title) {
    display: flex;
    gap: 20px;
    align-items: center;
    justify-content: space-between;
    padding: 18px 22px;
    margin-bottom: 18px;
    background: var(--art-main-bg-color);
    border: 1px solid var(--art-border-color);
    border-radius: 10px;
  }

  :deep(.page-title-main) {
    display: flex;
    align-items: center;
    min-width: 0;
  }

  :deep(.page-title h2) {
    margin: 0 0 5px;
    font-size: 20px;
    line-height: 1.25;
  }

  :deep(.page-title p) {
    margin: 0;
    font-size: 13px;
    color: var(--art-gray-600);
  }

  :deep(.page-title-actions) {
    display: flex;
    flex: 0 0 auto;
    gap: 10px;
    align-items: center;
  }

  .backup-page-actions {
    display: flex;
    gap: 10px;
    align-items: center;
  }

  .backup-progress-panel {
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr);
    gap: 12px;
    align-items: center;
    padding: 14px 16px;
    margin: -2px 0 18px;
    background: var(--el-color-primary-light-9);
    border: 1px solid var(--el-color-primary-light-7);
    border-radius: 8px;
    animation: backup-progress-enter .22s cubic-bezier(.22, 1, .36, 1);
  }

  .backup-progress-orbit {
    width: 30px;
    height: 30px;
    border: 3px solid var(--el-color-primary-light-5);
    border-top-color: var(--el-color-primary);
    border-radius: 50%;
    animation: backup-progress-spin .9s linear infinite;
  }

  .backup-progress-copy {
    min-width: 0;
  }

  .backup-progress-copy > div:first-child {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    color: var(--art-gray-900);
    font-size: 13px;
  }

  .backup-progress-copy strong {
    font-weight: 600;
  }

  .backup-progress-copy span {
    font-variant-numeric: tabular-nums;
    color: var(--el-color-primary);
    font-size: 12px;
    font-weight: 700;
  }

  .backup-progress-copy small {
    display: block;
    margin: 3px 0 8px;
    overflow: hidden;
    color: var(--art-gray-600);
    font-size: 12px;
    line-height: 1.45;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .backup-progress-copy :deep(.el-progress-bar__outer) {
    background: var(--el-color-primary-light-7);
  }

  @keyframes backup-progress-enter {
    from {
      opacity: 0;
      transform: translate3d(16px, 0, 0);
    }
    to {
      opacity: 1;
      transform: translateZ(0);
    }
  }

  @keyframes backup-progress-spin {
    to {
      transform: rotate(360deg);
    }
  }

  .restore-file-summary {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 14px;
    margin-bottom: 16px;
    background: var(--el-fill-color-light, var(--art-bg-color));
    border: 1px solid var(--el-border-color, var(--art-border-color));
    border-radius: 8px;
  }

  .restore-file-summary > span:last-child {
    min-width: 0;
  }

  .restore-file-summary strong,
  .restore-file-summary small {
    display: block;
  }

  .restore-file-summary strong {
    overflow: hidden;
    font-size: 13px;
    color: var(--art-gray-900);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .restore-file-summary small {
    margin-top: 4px;
    font-size: 11px;
    color: var(--art-gray-500);
  }

  .restore-file-icon {
    display: grid;
    flex: 0 0 38px;
    place-items: center;
    width: 38px;
    height: 38px;
    font-size: 18px;
    color: var(--main-color);
    background: var(--el-color-primary-light-9);
    border-radius: 8px;
  }

  .restore-form {
    margin-top: 18px;
  }

  .restore-form :deep(.el-form-item) {
    margin-bottom: 0;
  }

  .welcome-card {
    display: flex;
    gap: 20px;
    align-items: center;
    justify-content: space-between;
    padding: 26px 28px;
    margin-bottom: 18px;
    background: var(--default-box-color, var(--el-bg-color, #fff));
    border: 1px solid var(--el-border-color, #dcdfe6);
    border-radius: 10px;
  }

  .eyebrow {
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: 600;
    color: var(--main-color);
  }

  .welcome-card h2 {
    margin: 0 0 8px;
    font-size: 25px;
  }

  .welcome-card p {
    margin: 0;
    color: var(--art-gray-600);
  }

  .welcome-actions {
    display: flex;
    gap: 10px;
    align-items: center;
  }

  .stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 18px;
  }

  .stat-card {
    display: flex;
    gap: 14px;
    align-items: center;
    min-height: 116px;
    padding: 20px;
    background: var(--default-box-color, var(--el-bg-color, #fff));
    border: 1px solid var(--el-border-color, #dcdfe6);
    border-radius: 10px;
  }

  .stat-icon {
    display: grid;
    flex: 0 0 46px;
    place-items: center;
    width: 46px;
    height: 46px;
    font-size: 20px;
    color: var(--main-color);
    background: var(--art-primary-light);
    border-radius: 10px;
  }

  .stat-icon.green {
    color: #13ce66;
    background: #ecfbf3;
  }

  .stat-icon.amber {
    color: #e6a23c;
    background: #fff8e8;
  }

  .stat-icon.cyan {
    color: #20a7c9;
    background: #eafaff;
  }

  .stat-label {
    margin-bottom: 6px;
    font-size: 12px;
    color: var(--art-gray-600);
  }

  .stat-card strong {
    display: block;
    font-size: 25px;
    line-height: 1;
  }

  .stat-card small {
    display: block;
    margin-top: 7px;
    font-size: 11px;
    color: var(--art-gray-500);
  }

  .insight-stat-card {
    --insight-accent: var(--main-color);
    --insight-soft: var(--art-primary-light);

    position: relative;
    display: block;
    min-height: 188px;
    overflow: hidden;
    font: inherit;
    color: inherit;
    text-align: left;
    appearance: none;
    cursor: pointer;
    isolation: isolate;
    transition:
      border-color 0.2s ease,
      transform 0.2s ease,
      background-color 0.2s ease;
  }

  .insight-stat-card.green {
    --insight-accent: #13a965;
    --insight-soft: #ecfbf3;
  }

  .insight-stat-card.amber {
    --insight-accent: #d58b1b;
    --insight-soft: #fff8e8;
  }

  .insight-stat-card.cyan {
    --insight-accent: #168eac;
    --insight-soft: #eafaff;
  }

  .insight-stat-card::after {
    position: absolute;
    right: -35px;
    bottom: -48px;
    z-index: -1;
    width: 132px;
    height: 132px;
    content: '';
    background: var(--insight-soft);
    border-radius: 50%;
    transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1);
  }

  .insight-stat-card:hover {
    border-color: var(--insight-accent);
    transform: translateY(-2px);
  }

  .insight-stat-card:hover::after {
    transform: scale(1.18);
  }

  .insight-stat-card:active {
    transform: translateY(0);
  }

  .insight-stat-card:focus-visible {
    outline: 2px solid var(--insight-accent);
    outline-offset: 3px;
  }

  .insight-stat-head {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
  }

  .insight-stat-head .stat-label {
    margin: 0;
    font-size: 13px;
    font-weight: 600;
    color: var(--art-gray-700);
  }

  .insight-stat-head .stat-icon {
    flex-basis: 40px;
    width: 40px;
    height: 40px;
    font-size: 18px;
    border-radius: 9px;
  }

  .insight-stat-value {
    margin: 24px 0 12px;
    font-size: 38px !important;
    font-variant-numeric: tabular-nums;
    color: var(--art-gray-900);
    letter-spacing: -0.02em;
  }

  .insight-stat-message {
    display: flex;
    gap: 7px;
    align-items: center;
    min-height: 20px;
    font-size: 12px;
    color: var(--art-gray-600);
  }

  .insight-stat-message b {
    font-size: 12px;
    font-weight: 700;
    color: var(--insight-accent);
  }

  .insight-stat-action {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    padding-top: 12px;
    margin-top: 18px;
    font-size: 11px;
    color: var(--art-gray-600);
    border-top: 1px solid var(--el-border-color-lighter, var(--el-border-color, #dcdfe6));
  }

  .insight-stat-action small {
    min-width: 0;
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .insight-stat-action > span {
    flex: 0 0 auto;
    font-weight: 600;
    color: var(--insight-accent);
  }

  .insight-stat-action i {
    display: inline-block;
    font-style: normal;
    transition: transform 0.2s ease;
  }

  .insight-stat-card:hover .insight-stat-action i {
    transform: translateX(3px);
  }

  .content-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(300px, 0.8fr);
    gap: 18px;
    margin-bottom: 18px;
  }

  .lower-grid {
    grid-template-columns: minmax(0, 1.2fr) minmax(300px, 0.8fr);
  }

  .art-card {
    border-radius: 10px;
  }

  .card-head {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
  }

  .card-head b {
    font-size: 15px;
  }

  .card-head small {
    display: block;
    margin-top: 5px;
    font-size: 11px;
    color: var(--art-gray-500);
  }

  .trend-actions {
    display: flex;
    gap: 8px;
    align-items: center;
  }

  .trend-chart {
    display: flex;
    gap: 10px;
    align-items: flex-end;
    height: 230px;
    padding: 20px 6px 0;
    border-bottom: 1px solid var(--art-border-color);
  }

  .trend-column {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
    height: 100%;
  }

  .trend-column span {
    width: 100%;
    max-width: 28px;
    min-height: 8px;
    background: linear-gradient(180deg, var(--main-color), var(--art-primary-light));
    border-radius: 6px 6px 0 0;
  }

  .trend-column small {
    font-size: 10px;
    color: var(--art-gray-500);
    transform: rotate(-35deg);
  }

  .mini-list {
    display: grid;
    gap: 12px;
  }

  .mini-list div {
    display: flex;
    justify-content: space-between;
    padding-bottom: 11px;
    border-bottom: 1px solid var(--art-border-color);
  }

  .mini-list span {
    color: var(--art-gray-600);
  }

  .mini-list strong {
    font-variant-numeric: tabular-nums;
  }

  .toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-bottom: 16px;
  }

  .form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 4px 18px;
  }

  .form-grid .el-form-item:last-child {
    grid-column: 1 / -1;
  }

  .form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 12px;
  }

  .native-file {
    display: block;
    width: 100%;
    margin-top: 8px;
  }

  .security-grid {
    display: grid;
    grid-template-columns: minmax(320px, 0.7fr) minmax(420px, 1.3fr);
    gap: 18px;
  }

  .security-note :deep(.el-result) {
    padding: 20px 0;
  }

  .about-sections {
    display: grid;
    gap: 18px;
  }

  .about-card {
    display: grid;
    place-items: center;
    min-height: 360px;
    transition:
      border-color 0.2s ease,
      background-color 0.2s ease;
  }

  .about-card.has-update {
    background: var(--el-fill-color-extra-light, #f7f9fc);
    border-color: var(--el-color-primary-light-5);
  }

  .about-card.has-update :deep(.el-card__body) {
    width: 100%;
  }

  .about-card.has-update :deep(.el-result) {
    width: 100%;
    padding: 42px 32px;
  }

  .about-card.has-update :deep(.el-result__icon) {
    margin-bottom: 18px;
  }

  .about-card.has-update :deep(.el-result__title p) {
    font-size: 24px;
    font-weight: 600;
    color: var(--art-gray-900);
  }

  .about-card.has-update :deep(.el-result__subtitle p) {
    font-size: 14px;
    color: var(--art-gray-600);
  }

  .about-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: center;
  }

  .update-ready-note {
    display: flex;
    gap: 7px;
    align-items: center;
    width: fit-content;
    max-width: 100%;
    margin: 14px auto 0;
    font-size: 12px;
    line-height: 1.5;
    color: var(--art-gray-600);
  }

  .update-ready-note .el-icon {
    flex: 0 0 auto;
    font-size: 15px;
    color: var(--el-color-success);
  }

  .update-checked-at {
    margin-top: 12px;
    font-size: 12px;
    font-variant-numeric: tabular-nums;
    color: var(--art-gray-600);
    text-align: center;
  }

  .link-page-actions {
    display: flex;
    gap: 12px;
    align-items: stretch;
  }

  .link-page-control {
    --link-control-color: var(--el-color-primary);
    --link-control-soft: var(--el-color-primary-light-9);
    --link-control-border: var(--el-color-primary-light-7);

    display: grid;
    grid-template-columns: 36px minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    min-width: 244px;
    padding: 11px 12px;
    background: var(--link-control-soft);
    border: 1px solid var(--link-control-border);
    border-radius: 8px;
    transition:
      border-color 0.2s ease,
      background-color 0.2s ease;
  }

  .link-page-control.is-mail {
    --link-control-color: var(--el-color-warning);
    --link-control-soft: var(--el-color-warning-light-9);
    --link-control-border: var(--el-color-warning-light-7);

    grid-template-columns: 36px minmax(0, 1fr) auto auto;
    min-width: 300px;
  }

  .link-page-control.is-mail.is-ready {
    --link-control-color: var(--el-color-success);
    --link-control-soft: var(--el-color-success-light-9);
    --link-control-border: var(--el-color-success-light-7);
  }

  .link-page-control:hover {
    border-color: var(--link-control-color);
  }

  .link-feature-icon {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    font-size: 17px;
    color: var(--link-control-color);
    background: var(--default-box-color, var(--el-bg-color, #fff));
    border-radius: 8px;
  }

  .link-feature-copy {
    min-width: 0;
  }

  .link-feature-copy b,
  .link-feature-copy small {
    display: block;
  }

  .link-feature-copy b {
    font-size: 13px;
    line-height: 1.35;
    color: var(--art-gray-900);
  }

  .link-feature-copy small {
    max-width: 210px;
    margin-top: 3px;
    overflow: hidden;
    font-size: 11px;
    line-height: 1.35;
    color: var(--art-gray-600);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .link-config-button {
    width: 30px !important;
    height: 30px !important;
    margin: 0 !important;
    color: var(--link-control-color) !important;
    background: var(--default-box-color, var(--el-bg-color, #fff)) !important;
    border-color: var(--link-control-border) !important;
  }

  .link-config-button:hover,
  .link-config-button:focus-visible {
    color: #fff !important;
    background: var(--link-control-color) !important;
    border-color: var(--link-control-color) !important;
  }

  .link-mail-alert {
    margin: -2px 0 18px;
  }

  .update-compare-card :deep(.el-card__body) {
    padding: 0;
  }

  .update-compare-head {
    display: flex;
    gap: 16px;
    align-items: center;
    justify-content: space-between;
  }

  .update-compare-head b,
  .update-compare-head small {
    display: block;
  }

  .update-compare-head b {
    font-size: 15px;
    color: var(--art-gray-900);
  }

  .update-compare-head small {
    margin-top: 4px;
    font-size: 11px;
    color: var(--art-gray-500);
  }

  .update-compare-body {
    padding: 26px;
  }

  .update-version-compare {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 44px minmax(0, 1fr);
    align-items: stretch;
  }

  .update-version-side {
    min-width: 0;
    padding: 21px;
    background: var(--default-box-color, var(--el-bg-color, #fff));
    border: 1px solid var(--art-border-color);
    border-radius: 9px;
  }

  .update-version-side > strong,
  .update-version-side > small {
    display: block;
  }

  .update-version-side > strong {
    font-size: 27px;
    font-variant-numeric: tabular-nums;
    line-height: 1.2;
    color: var(--art-gray-900);
  }

  .update-version-side > small {
    margin-top: 6px;
    font-size: 12px;
    color: var(--art-gray-500);
  }

  .update-version-side dl {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin: 18px 0 0;
  }

  .update-version-side dl > div {
    min-width: 0;
    padding-top: 12px;
    border-top: 1px solid var(--art-border-color);
  }

  .update-version-side dt,
  .update-version-side dd {
    display: block;
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .update-version-side dt {
    font-size: 11px;
    color: var(--art-gray-500);
  }

  .update-version-side dd {
    margin-top: 5px;
    font-size: 13px;
    font-weight: 600;
    color: var(--art-gray-800);
  }

  .update-package-card {
    display: grid;
    grid-template-columns: 105px minmax(0, 1fr);
    min-width: 0;
    overflow: hidden;
    background: var(--el-color-warning-light-9, #fff9ed);
    border: 1px solid var(--el-color-warning-light-5, #f0c987);
    border-radius: 9px;
  }

  .update-package-object {
    display: grid;
    place-items: center;
    min-height: 176px;
    background: var(--el-color-warning-light-8, #fff3dc);
    border-right: 1px solid var(--el-color-warning-light-7, #f2d9ac);
  }

  .update-package-object > span {
    display: grid;
    place-items: center;
    width: 52px;
    height: 52px;
    color: var(--el-color-warning-dark-2, #ba7610);
    background: var(--default-box-color, var(--el-bg-color, #fff));
    border: 1px solid var(--el-color-warning, #e5a63e);
    border-radius: 9px;
  }

  .update-package-object .el-icon {
    font-size: 27px;
  }

  .update-package-content {
    min-width: 0;
    padding: 21px;
  }

  .update-package-head {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    justify-content: space-between;
    font-size: 11px;
    font-weight: 600;
    color: var(--el-color-warning-dark-2, #8a570d);
  }

  .update-package-head > b {
    flex: 0 0 auto;
    padding: 4px 7px;
    font-size: 10px;
    line-height: 1;
    color: var(--el-color-warning-dark-2, #9a5d08);
    background: var(--el-color-warning-light-7, #ffebc4);
    border-radius: 5px;
  }

  .update-package-content > strong {
    display: block;
    margin-top: 17px;
    overflow: hidden;
    font-size: 27px;
    font-variant-numeric: tabular-nums;
    line-height: 1.2;
    color: var(--el-color-warning-dark-2, #9d5b08);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .update-package-content dl {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin: 18px 0 0;
  }

  .update-package-content dl > div {
    min-width: 0;
    padding-top: 10px;
    border-top: 1px solid var(--el-color-warning-light-6, #eedbb7);
  }

  .update-package-content dt,
  .update-package-content dd {
    display: block;
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .update-package-content dt {
    font-size: 10px;
    color: var(--el-color-warning-dark-2, #8a6f49);
  }

  .update-package-content dd {
    margin-top: 5px;
    font-size: 12px;
    font-weight: 600;
    color: var(--art-gray-800);
  }

  .update-compare-arrow {
    display: grid;
    place-items: center;
    font-size: 20px;
    color: var(--main-color);
  }

  .update-compare-safe {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    margin-top: 22px;
    font-size: 12px;
    line-height: 1.6;
    color: var(--art-gray-600);
  }

  .update-compare-safe .el-icon {
    flex: 0 0 auto;
    margin-top: 2px;
    font-size: 16px;
    color: var(--el-color-success);
  }

  .update-compare-footer {
    display: flex;
    gap: 18px;
    align-items: center;
    justify-content: space-between;
    min-height: 78px;
    padding: 16px 22px;
    border-top: 1px solid var(--art-border-color);
  }

  .update-compare-checked {
    font-size: 12px;
    font-variant-numeric: tabular-nums;
    color: var(--art-gray-500);
  }

  .update-compare-actions {
    display: flex;
    gap: 10px;
    align-items: center;
  }

  .update-compare-button {
    display: inline-flex;
    align-items: stretch;
    min-height: 42px;
    padding: 0;
    overflow: hidden;
    font: inherit;
    color: #fff;
    cursor: pointer;
    background: var(--el-color-danger);
    border: 0;
    border-radius: 7px;
    transition: background-color 0.18s ease;
  }

  .update-compare-button:hover:not(:disabled) {
    background: var(--el-color-danger-dark-2);
  }

  .update-compare-button:focus-visible {
    outline: 2px solid var(--el-color-danger);
    outline-offset: 3px;
  }

  .update-compare-button:disabled {
    cursor: not-allowed;
    opacity: 0.65;
  }

  .update-compare-button > span,
  .update-compare-button > b {
    display: inline-flex;
    gap: 7px;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 16px;
    white-space: nowrap;
  }

  .update-compare-button > span {
    font-size: 14px;
    font-weight: 500;
  }

  .update-compare-button > b {
    font-size: 13px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    background: var(--el-color-danger-dark-2);
    border-left: 1px solid rgb(255 255 255 / 32%);
  }

  .update-compare-card .update-progress-panel {
    width: 100%;
    margin: 22px 0 0;
  }

  .update-progress-panel {
    width: min(680px, calc(100vw - 112px));
    padding-top: 18px;
    margin: 22px auto 0;
    text-align: left;
    border-top: 1px solid var(--el-border-color-lighter, var(--art-border-color));
  }

  .system-intro-card {
    margin-bottom: 18px;
  }

  .system-intro-card :deep(.el-card__body) {
    padding: 24px;
  }

  .system-intro-main {
    display: flex;
    gap: 16px;
    align-items: flex-start;
  }

  .system-intro-icon {
    display: grid;
    flex: 0 0 44px;
    place-items: center;
    width: 44px;
    height: 44px;
    font-size: 21px;
    color: var(--main-color);
    background: var(--el-color-primary-light-9);
    border-radius: 9px;
  }

  .system-intro-main h2 {
    margin: 1px 0 7px;
    font-size: 20px;
    line-height: 1.35;
    color: var(--art-gray-900);
  }

  .system-intro-main p {
    max-width: 76ch;
    margin: 0;
    font-size: 13px;
    line-height: 1.75;
    color: var(--art-gray-600);
  }

  .system-intro-details {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
    gap: 28px;
    padding-top: 20px;
    margin-top: 22px;
    border-top: 1px solid var(--el-border-color-lighter, var(--art-border-color));
  }

  .system-intro-details section {
    min-width: 0;
  }

  .system-intro-details h3 {
    margin: 0 0 10px;
    font-size: 13px;
    color: var(--art-gray-800);
  }

  .system-intro-details p {
    margin: 0;
    font-size: 12px;
    line-height: 1.75;
    color: var(--art-gray-600);
  }

  .system-intro-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .system-feature-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    padding: 13px 15px;
    margin-top: 20px;
    font-size: 12px;
    color: var(--art-gray-700);
    background: var(--el-fill-color-light, var(--art-bg-color));
    border-radius: 8px;
  }

  .system-feature-list span {
    display: inline-flex;
    gap: 7px;
    align-items: center;
    white-space: nowrap;
  }

  .system-feature-list span::before {
    width: 5px;
    height: 5px;
    content: '';
    background: var(--main-color);
    border-radius: 50%;
  }

  .system-info-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.65fr);
    gap: 18px;
  }

  .system-info-card {
    min-width: 0;
  }

  .system-info-card :deep(.el-descriptions__label) {
    width: 120px;
    font-weight: 500;
    color: var(--art-gray-600);
  }

  .system-project-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px;
    margin-top: 16px;
  }

  .system-project-link {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    min-width: 0;
    min-height: 58px;
    padding: 10px 12px;
    color: inherit;
    text-decoration: none;
    background: var(--el-fill-color-light, var(--art-bg-color));
    border: 1px solid var(--art-border-color);
    border-radius: 8px;
    transition:
      color .18s ease,
      background-color .18s ease,
      border-color .18s ease,
      transform .18s cubic-bezier(.22, 1, .36, 1);
  }

  .system-project-link:hover,
  .system-project-link:focus-visible {
    color: var(--main-color);
    background: var(--el-color-primary-light-9);
    border-color: var(--el-color-primary-light-5);
    outline: none;
    transform: translateY(-1px);
  }

  .system-project-link:focus-visible {
    box-shadow: 0 0 0 3px var(--el-color-primary-light-8);
  }

  .system-project-link-icon {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    font-size: 18px;
    color: var(--main-color);
    background: var(--el-color-primary-light-9);
    border-radius: 7px;
  }

  .system-project-link-icon.is-github {
    color: var(--art-gray-800);
    background: var(--el-fill-color, #eceff3);
  }

  .system-project-link-copy {
    min-width: 0;
  }

  .system-project-link-copy b,
  .system-project-link-copy small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .system-project-link-copy b {
    display: flex;
    gap: 6px;
    align-items: center;
    color: var(--art-gray-800);
    font-size: 13px;
    font-weight: 600;
  }

  .system-project-link-copy :deep(.el-tag) {
    flex: 0 0 auto;
  }

  .system-project-link-copy small {
    margin-top: 4px;
    color: var(--art-gray-600);
    font-size: 11px;
  }

  .system-project-link-arrow {
    flex: 0 0 auto;
    color: var(--art-gray-500);
    font-size: 15px;
  }

  .runtime-check-list {
    display: grid;
    gap: 0;
  }

  .runtime-check-list > div {
    display: flex;
    gap: 16px;
    align-items: center;
    justify-content: space-between;
    min-height: 70px;
    padding: 12px 0;
    border-bottom: 1px solid var(--el-border-color-lighter, var(--art-border-color));
  }

  .runtime-check-list > div:first-child {
    padding-top: 2px;
  }

  .runtime-check-list > div:last-child {
    padding-bottom: 2px;
    border-bottom: 0;
  }

  .runtime-check-list span {
    min-width: 0;
  }

  .runtime-check-list b,
  .runtime-check-list small {
    display: block;
  }

  .runtime-check-list b {
    font-size: 13px;
    color: var(--art-gray-800);
  }

  .runtime-check-list small {
    margin-top: 5px;
    font-size: 11px;
    line-height: 1.5;
    color: var(--art-gray-500);
  }

  .update-progress-head {
    display: flex;
    gap: 16px;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 13px;
    color: var(--art-gray-700);
  }

  .update-progress-head span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .update-progress-head strong {
    flex: 0 0 auto;
    font-size: 13px;
    font-variant-numeric: tabular-nums;
    color: var(--art-gray-900);
  }

  .update-phase-list {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-top: 16px;
  }

  .update-phase-item {
    display: flex;
    gap: 9px;
    align-items: center;
    min-width: 0;
    color: var(--art-gray-500);
  }

  .update-phase-dot {
    display: grid;
    flex: 0 0 26px;
    place-items: center;
    width: 26px;
    height: 26px;
    font-size: 12px;
    color: var(--art-gray-500);
    background: var(--el-bg-color, #fff);
    border: 1px solid var(--el-border-color, var(--art-border-color));
    border-radius: 50%;
  }

  .update-phase-dot i {
    font-style: normal;
  }

  .update-phase-item > span:last-child {
    min-width: 0;
  }

  .update-phase-item b,
  .update-phase-item small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .update-phase-item b {
    font-size: 12px;
    font-weight: 600;
    color: inherit;
  }

  .update-phase-item small {
    margin-top: 2px;
    font-size: 10px;
    color: var(--art-gray-500);
  }

  .update-phase-item.active {
    color: var(--main-color);
  }

  .update-phase-item.active .update-phase-dot {
    color: var(--main-color);
    background: var(--el-color-primary-light-9);
    border-color: var(--main-color);
  }

  .update-phase-item.active .el-icon {
    animation: rotating 1.2s linear infinite;
  }

  .update-phase-item.done {
    color: var(--el-color-success);
  }

  .update-phase-item.done .update-phase-dot {
    color: var(--el-color-success);
    background: var(--el-color-success-light-9);
    border-color: var(--el-color-success);
  }

  .update-phase-item.error {
    color: var(--el-color-danger);
  }

  .update-phase-item.error .update-phase-dot {
    color: var(--el-color-danger);
    background: var(--el-color-danger-light-9);
    border-color: var(--el-color-danger);
  }

  .changelog-head {
    display: flex;
    gap: 16px;
    align-items: center;
    justify-content: space-between;
  }

  .changelog-head b {
    display: block;
    font-size: 15px;
    color: var(--art-gray-900);
  }

  .changelog-head small {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: var(--art-gray-600);
  }

  .changelog-scroll {
    max-height: 360px;
    padding: 2px 6px 2px 0;
    overflow-y: auto;
    overscroll-behavior: contain;
    outline: none;
    scrollbar-gutter: stable;
  }

  .changelog-scroll:focus-visible {
    border-radius: 6px;
    outline: 2px solid var(--main-color);
    outline-offset: 4px;
  }

  .changelog-item {
    display: grid;
    grid-template-columns: 18px minmax(0, 1fr);
    gap: 12px;
    min-width: 0;
    padding-bottom: 24px;
  }

  .changelog-item:last-child {
    padding-bottom: 0;
  }

  .changelog-marker {
    position: relative;
    display: flex;
    justify-content: center;
  }

  .changelog-marker span {
    position: relative;
    z-index: 1;
    width: 10px;
    height: 10px;
    margin-top: 5px;
    background: var(--default-box-color, var(--el-bg-color, #fff));
    border: 2px solid var(--main-color);
    border-radius: 50%;
  }

  .changelog-marker i {
    position: absolute;
    top: 17px;
    bottom: -19px;
    width: 1px;
    background: var(--el-border-color, var(--art-border-color));
  }

  .changelog-item:last-child .changelog-marker i {
    display: none;
  }

  .changelog-content {
    min-width: 0;
    padding-bottom: 2px;
  }

  .changelog-content header {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
  }

  .changelog-version {
    display: flex;
    gap: 8px;
    align-items: center;
    min-width: 0;
  }

  .changelog-version strong {
    font-size: 14px;
    color: var(--art-gray-900);
  }

  .changelog-content time {
    flex: 0 0 auto;
    font-size: 12px;
    color: var(--art-gray-500);
  }

  .changelog-content h3 {
    margin: 8px 0 7px;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.5;
    color: var(--art-gray-800);
  }

  .changelog-content ul {
    padding-left: 18px;
    margin: 0;
    font-size: 13px;
    line-height: 1.75;
    color: var(--art-gray-700);
  }

  .changelog-content li + li {
    margin-top: 2px;
  }

  .site-meta-status {
    min-height: 20px;
    margin-top: 6px;
    font-size: 12px;
    line-height: 20px;
    color: var(--art-gray-600);
    transition: color 0.2s ease;
  }

  .site-row-actions {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    white-space: nowrap;
  }

  .site-list-icon-cell {
    display: flex;
    justify-content: center;
  }

  .site-list-favicon,
  .site-icon-preview {
    display: grid;
    flex: 0 0 auto;
    width: 32px;
    height: 32px;
    place-items: center;
    overflow: hidden;
    color: #fff;
    background: var(--el-fill-color-light);
    border-radius: 8px;
    object-fit: cover;
  }

  .site-list-favicon-fallback,
  .site-icon-preview-fallback {
    font-size: 14px;
    font-weight: 700;
    color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
  }

  .site-icon-form-item {
    grid-column: 1 / -1;
  }

  .site-icon-form-item :deep(.el-form-item__content) {
    display: block;
  }

  .site-icon-field {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) 40px;
    gap: 10px;
    align-items: center;
    width: 100%;
  }

  .site-icon-preview {
    width: 44px;
    height: 40px;
    border-radius: 8px;
  }

  .site-icon-input {
    min-width: 0;
  }

  .site-icon-input > span {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    line-height: 18px;
    color: var(--art-gray-600);
  }

  .site-icon-refresh {
    width: 40px;
    height: 40px;
    padding: 0;
  }

  .site-row-actions :deep(.el-button) {
    height: 36px;
    margin: 0;
    font-size: 13px;
  }

  .site-row-actions :deep(.el-button.site-row-edit) {
    min-width: 92px;
    padding: 0 15px;
  }

  .site-row-actions :deep(.el-button.site-row-edit .el-icon) {
    margin-right: 5px;
    font-size: 14px;
  }

  .site-row-actions :deep(.el-button.site-row-delete) {
    width: 38px;
    min-width: 38px;
    padding: 0;
  }

  .site-row-actions :deep(.el-button.site-row-delete .el-icon) {
    margin: 0;
    font-size: 15px;
  }

  .category-list-icon {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    font-size: 20px;
    line-height: 1;
    background: var(--el-fill-color-light);
    border-radius: 8px;
  }

  .category-icon-form-item :deep(.el-form-item__content) {
    display: block;
  }

  .category-icon-field {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    width: 100%;
  }

  .category-icon-preview {
    display: grid;
    place-items: center;
    width: 44px;
    height: 40px;
    font-size: 22px;
    line-height: 1;
    color: var(--art-gray-700);
    background: var(--el-fill-color-light);
    border-radius: 8px;
  }

  .category-icon-trigger {
    height: 40px;
    margin: 0;
  }

  .category-icon-picker {
    display: grid;
    gap: 12px;
  }

  .category-icon-picker-head {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
  }

  .category-icon-picker-head strong {
    display: block;
    font-size: 14px;
    color: var(--art-gray-900);
  }

  .category-icon-picker-head small {
    display: block;
    margin-top: 3px;
    font-size: 11px;
    color: var(--art-gray-500);
  }

  .category-icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(38px, 1fr));
    gap: 6px;
    max-height: 160px;
    padding: 2px 2px 2px 0;
    overflow-y: auto;
  }

  .category-icon-option {
    display: grid;
    place-items: center;
    min-width: 38px;
    aspect-ratio: 1;
    font-size: 21px;
    line-height: 1;
    cursor: pointer;
    background: var(--el-fill-color-light);
    border: 0;
    border-radius: 8px;
    transition:
      background-color 0.16s ease,
      transform 0.16s cubic-bezier(0.25, 1, 0.5, 1);
  }

  .category-icon-option:hover,
  .category-icon-option:focus-visible {
    background: var(--el-color-primary-light-9);
    outline: 2px solid var(--el-color-primary-light-5);
    outline-offset: 0;
    transform: translateY(-1px);
  }

  .category-icon-option.is-selected {
    background: var(--el-color-primary-light-9);
    outline: 2px solid var(--el-color-primary);
  }

  .category-icon-help {
    margin-top: 8px;
    font-size: 11px;
    line-height: 1.5;
    color: var(--art-gray-500);
  }

  .site-meta-status.loading {
    color: var(--main-color);
  }

  .site-meta-status.success {
    color: var(--el-color-success);
  }

  .site-meta-status.error {
    color: var(--el-color-danger);
  }

  .profile-summary {
    display: flex;
    gap: 18px;
    align-items: center;
    padding: 20px 22px;
    margin-bottom: 18px;
    background: var(--default-box-color, var(--el-bg-color, #fff));
    border: 1px solid var(--art-border-color);
    border-radius: 10px;
  }

  .profile-avatar-wrap {
    position: relative;
    flex: 0 0 72px;
    width: 72px;
    height: 72px;
  }

  .profile-avatar {
    display: block;
    width: 72px;
    height: 72px;
    object-fit: cover;
    background: var(--art-bg-color);
    border: 1px solid var(--art-border-color);
    border-radius: 50%;
  }

  .profile-avatar-action {
    position: absolute;
    right: -2px;
    bottom: -2px;
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    padding: 0;
    color: #fff;
    cursor: pointer;
    background: var(--main-color);
    border: 2px solid var(--default-box-color, var(--el-bg-color, #fff));
    border-radius: 50%;
  }

  .profile-avatar-action:disabled {
    cursor: wait;
    opacity: 0.7;
  }

  .profile-summary-main {
    flex: 1;
    min-width: 0;
  }

  .profile-title-line {
    display: flex;
    gap: 10px;
    align-items: center;
  }

  .profile-title-line h2 {
    margin: 0;
    overflow: hidden;
    font-size: 20px;
    line-height: 1.35;
    color: var(--art-gray-900);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .profile-summary-main p {
    margin: 7px 0 0;
    font-size: 13px;
    color: var(--art-gray-600);
  }

  .profile-session-state {
    display: flex;
    gap: 10px;
    align-items: center;
    min-width: 160px;
    padding-left: 20px;
    border-left: 1px solid var(--art-border-color);
  }

  .profile-status-dot {
    flex: 0 0 9px;
    width: 9px;
    height: 9px;
    background: var(--el-color-success);
    border-radius: 50%;
    box-shadow: 0 0 0 4px var(--el-color-success-light-9);
  }

  .profile-session-state strong,
  .profile-session-state small {
    display: block;
  }

  .profile-session-state strong {
    font-size: 13px;
    color: var(--art-gray-800);
  }

  .profile-session-state small {
    margin-top: 4px;
    font-size: 11px;
    color: var(--art-gray-500);
  }

  .profile-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(340px, 0.85fr);
    gap: 18px;
  }

  .profile-form-card,
  .profile-status-card,
  .profile-preference-card,
  .profile-activity-card {
    margin-bottom: 18px;
  }

  .profile-field-hint {
    margin-top: 6px;
    font-size: 12px;
    color: var(--art-gray-500);
  }

  .profile-security-action {
    display: flex;
    gap: 16px;
    align-items: center;
    justify-content: space-between;
    padding-top: 16px;
    margin-top: 18px;
    border-top: 1px solid var(--art-border-color);
  }

  .profile-security-action strong,
  .profile-security-action small {
    display: block;
  }

  .profile-security-action strong {
    font-size: 13px;
  }

  .profile-security-action small {
    margin-top: 4px;
    font-size: 11px;
    color: var(--art-gray-500);
  }

  .profile-preference-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
  }

  .profile-preference-grid :deep(.el-form-item) {
    margin-bottom: 0;
  }

  .profile-preference-grid :deep(.el-select),
  .profile-preference-grid :deep(.el-segmented) {
    width: 100%;
  }

  @media (width <= 1100px) {
    .stat-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .content-grid,
    .lower-grid,
    .security-grid,
    .system-info-grid,
    .system-intro-details,
    .profile-main-grid {
      grid-template-columns: 1fr;
    }

    .welcome-card {
      flex-direction: column;
      align-items: flex-start;
    }

    :deep(.page-title) {
      flex-direction: column;
      align-items: flex-start;
    }

    :deep(.page-title-actions) {
      width: 100%;
    }
  }

  @media (width <= 680px) {
    .stat-grid {
      grid-template-columns: 1fr;
    }

    .insight-stat-card {
      min-height: 166px;
    }

    .insight-stat-value {
      margin: 18px 0 10px;
      font-size: 34px !important;
    }

    .form-grid,
    .profile-preference-grid {
      grid-template-columns: 1fr;
    }

    .form-grid .el-form-item:last-child {
      grid-column: auto;
    }

    .toolbar > * {
      width: 100% !important;
    }

    .welcome-card {
      padding: 20px;
    }

    .welcome-actions {
      justify-content: space-between;
      width: 100%;
    }

    .backup-page-actions {
      flex-direction: column;
      width: 100%;
    }

    .backup-page-actions .el-button {
      width: 100%;
      margin: 0;
    }

    .content-grid {
      display: block;
    }

    .art-card {
      margin-bottom: 16px;
    }

    .category-icon-field {
      grid-template-columns: 44px minmax(0, 1fr);
    }

    .site-icon-field {
      grid-template-columns: 44px minmax(0, 1fr);
    }

    .site-icon-refresh {
      grid-column: 2;
      width: 100%;
    }

    .category-icon-trigger {
      grid-column: 2;
      width: 100%;
      margin: 0;
    }

    .changelog-content header {
      flex-direction: column;
      gap: 4px;
      align-items: flex-start;
    }

    .changelog-scroll {
      max-height: 420px;
    }

    .changelog-head {
      align-items: flex-start;
    }

    .system-intro-card :deep(.el-card__body) {
      padding: 18px;
    }

    .system-intro-main {
      gap: 12px;
    }

    .system-intro-icon {
      flex-basis: 38px;
      width: 38px;
      height: 38px;
    }

    .system-intro-main h2 {
      font-size: 18px;
    }

    .system-intro-details {
      gap: 18px;
      padding-top: 17px;
      margin-top: 18px;
    }

    .system-feature-list {
      gap: 8px 14px;
      margin-top: 17px;
    }

    .system-info-card :deep(.el-descriptions__label) {
      width: 104px;
    }

    .about-card.has-update :deep(.el-result) {
      padding: 34px 18px;
    }

    .about-card.has-update :deep(.el-result__title p) {
      font-size: 22px;
    }

    .update-ready-note {
      align-items: flex-start;
      text-align: left;
    }

    .update-progress-panel {
      width: 100%;
    }

    .update-phase-list {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      row-gap: 14px;
    }

    .update-progress-head span {
      white-space: normal;
    }

    .update-checked-at {
      margin-top: 16px;
    }

    .profile-summary {
      flex-wrap: wrap;
      align-items: flex-start;
      padding: 18px;
    }

    .profile-avatar-wrap,
    .profile-avatar {
      width: 60px;
      height: 60px;
    }

    .profile-avatar-wrap {
      flex-basis: 60px;
    }

    .profile-summary-main {
      width: calc(100% - 78px);
    }

    .profile-title-line {
      flex-direction: column;
      gap: 5px;
      align-items: flex-start;
    }

    .profile-title-line h2 {
      font-size: 18px;
    }

    .profile-session-state {
      width: 100%;
      padding: 14px 0 0;
      border-top: 1px solid var(--art-border-color);
      border-left: 0;
    }

    .profile-preference-grid {
      gap: 0;
    }

    .profile-security-action {
      align-items: flex-start;
    }

    .profile-activity-card :deep(.el-card__body) {
      padding: 0 14px 14px;
    }
  }

  @media (width <= 680px) {
    .update-compare-body {
      padding: 18px;
    }

    .update-version-compare {
      grid-template-columns: 1fr;
      gap: 10px;
    }

    .update-compare-arrow {
      height: 24px;
      transform: rotate(90deg);
    }

    .update-version-side {
      padding: 18px;
    }

    .update-version-side > strong,
    .update-package-content > strong {
      font-size: 23px;
    }

    .update-package-card {
      grid-template-columns: 86px minmax(0, 1fr);
    }

    .update-package-object {
      min-height: 164px;
    }

    .update-package-object > span {
      width: 46px;
      height: 46px;
    }

    .update-package-content {
      padding: 18px;
    }

    .update-compare-footer {
      flex-direction: column;
      align-items: stretch;
    }

    .update-compare-actions {
      display: grid;
      grid-template-columns: 1fr;
    }

    .update-compare-actions .el-button,
    .update-compare-button {
      width: 100%;
      margin: 0;
    }

    .update-compare-button > span {
      flex: 1;
    }

    .update-compare-checked {
      text-align: left;
    }
  }

  @media (width <= 680px) {
    .link-page-actions {
      flex-direction: column;
      width: 100%;
    }

    .link-page-control,
    .link-page-control.is-mail {
      width: 100%;
      min-width: 0;
    }

    .link-feature-copy small {
      max-width: calc(100vw - 210px);
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .update-phase-item.active .el-icon,
    .backup-progress-orbit {
      animation: none;
    }

    .backup-progress-panel {
      animation: none;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .insight-stat-card,
    .insight-stat-card::after,
    .insight-stat-action i,
    .site-meta-status,
    .system-project-link {
      transition: none;
    }

    .insight-stat-card:hover,
    .system-project-link:hover {
      transform: none;
    }
  }

  .trend-line-chart {
    position: relative;
    display: block;
    height: 248px;
    padding: 12px 0 0;
    overflow: hidden;
  }

  .trend-line-chart svg {
    display: block;
    width: 100%;
    height: 220px;
    overflow: visible;
  }

  .trend-grid-line {
    stroke: var(--art-border-color);
    stroke-dasharray: 3 5;
    stroke-width: 1;
  }

  .trend-polyline {
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 3;
    vector-effect: non-scaling-stroke;
  }

  .trend-polyline-views {
    stroke: #8b9aaa;
    stroke-dasharray: 5 4;
  }

  .trend-polyline-clicks {
    stroke: var(--main-color);
  }

  .trend-point-group {
    cursor: pointer;
  }

  .trend-point-group .trend-hit-area {
    stroke: transparent;
    stroke-width: 22;
  }

  .trend-point {
    vector-effect: non-scaling-stroke;
    transition:
      transform 0.16s ease,
      r 0.16s ease;
  }

  .trend-point-views {
    fill: #fff;
    stroke: #8b9aaa;
    stroke-width: 2;
  }

  .trend-point-clicks {
    fill: var(--main-color);
    stroke: #fff;
    stroke-width: 2;
  }

  .trend-point-group:hover .trend-point,
  .trend-point-group.active .trend-point {
    r: 7;
  }

  .trend-point-group.active .trend-date {
    font-weight: 700;
    fill: var(--main-color);
  }

  .trend-date {
    font-size: 11px;
    fill: var(--art-gray-500);
  }

  .trend-legend {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    font-size: 12px;
    color: var(--art-gray-600);
    white-space: nowrap;
  }

  .trend-legend i {
    width: 18px;
    height: 3px;
    background: var(--main-color);
    border-radius: 2px;
  }

  .trend-legend.is-views i {
    height: 0;
    background: transparent;
    border-top: 2px dashed #8b9aaa;
  }

  .trend-empty {
    position: absolute;
    inset: 80px 0 auto;
    font-size: 12px;
    color: var(--art-gray-500);
    text-align: center;
    pointer-events: none;
  }

  .trend-detail {
    padding-top: 14px;
    margin-top: 14px;
    border-top: 1px solid var(--art-border-color);
  }

  .trend-detail.is-empty {
    min-height: 64px;
  }

  .trend-detail-toolbar {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 12px;
  }

  .trend-detail-head {
    flex: 1;
    min-width: 0;
  }

  .trend-detail-head b {
    display: block;
    font-size: 13px;
    color: var(--art-gray-900);
  }

  .trend-detail-head small {
    display: block;
    margin-top: 4px;
    font-size: 11px;
    line-height: 1.5;
    color: var(--art-gray-500);
  }

  .trend-detail-toolbar :deep(.el-segmented) {
    flex: 0 0 auto;
  }

  .trend-detail-toolbar > .el-icon {
    flex: 0 0 auto;
    color: var(--main-color);
  }

  .trend-detail-list {
    overflow: hidden;
    background: var(--el-bg-color, #fff);
    border: 1px solid var(--art-border-color);
    border-radius: 8px;
  }

  .trend-list-summary {
    display: flex;
    gap: 16px;
    align-items: center;
    justify-content: space-between;
    min-height: 42px;
    padding: 0 14px;
    font-size: 12px;
    color: var(--art-gray-600);
    background: var(--el-fill-color-extra-light, #fafafa);
    border-bottom: 1px solid var(--art-border-color);
  }

  .trend-list-summary span {
    display: inline-flex;
    gap: 5px;
    align-items: center;
  }

  .trend-list-summary .el-icon {
    font-size: 15px;
    color: var(--main-color);
  }

  .trend-list-summary b {
    color: var(--art-gray-900);
  }

  .trend-list-summary strong {
    font-size: 16px;
    font-variant-numeric: tabular-nums;
    color: var(--main-color);
  }

  .trend-detail-table :deep(.el-table__inner-wrapper::before) {
    display: none;
  }

  .trend-detail-table :deep(.el-table__header-wrapper th.el-table__cell) {
    height: 40px;
    font-size: 12px;
    font-weight: 600;
    color: var(--art-gray-600);
    background: var(--el-fill-color-light, #f5f7fa);
  }

  .trend-detail-table :deep(.el-table__row td.el-table__cell) {
    height: 56px;
  }

  .trend-detail-table :deep(.el-table__row:hover > td.el-table__cell) {
    background: var(--el-color-primary-light-9);
  }

  .trend-rank {
    display: inline-grid;
    place-items: center;
    width: 24px;
    height: 24px;
    font-size: 11px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--art-gray-600);
    background: var(--el-fill-color-light, #f5f7fa);
    border-radius: 6px;
  }

  .trend-detail-table :deep(.el-table__row:first-child) .trend-rank {
    color: var(--main-color);
    background: var(--el-color-primary-light-8);
  }

  .trend-site-cell {
    display: flex;
    gap: 10px;
    align-items: center;
    min-width: 0;
  }

  .trend-site-cell b {
    overflow: hidden;
    font-size: 13px;
    color: var(--art-gray-900);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .trend-site-mark {
    display: grid;
    flex: 0 0 30px;
    place-items: center;
    width: 30px;
    height: 30px;
    font-size: 12px;
    font-weight: 700;
    color: var(--main-color);
    background: var(--el-color-primary-light-9);
    border-radius: 7px;
  }

  .trend-count {
    display: inline-flex;
    gap: 3px;
    align-items: baseline;
    justify-content: flex-end;
    min-width: 58px;
    padding: 5px 8px;
    color: var(--main-color);
    background: var(--el-color-primary-light-9);
    border-radius: 6px;
  }

  .trend-count.is-views {
    color: var(--art-gray-700);
    background: var(--el-fill-color-light, #f5f7fa);
  }

  .trend-count strong {
    font-size: 15px;
    font-variant-numeric: tabular-nums;
  }

  .trend-count small {
    font-size: 10px;
  }

  .trend-site-url {
    display: flex;
    gap: 6px;
    align-items: center;
    min-width: 0;
    font-size: 12px;
    color: var(--art-gray-600);
    text-decoration: none;
  }

  .trend-site-url .el-icon {
    flex: 0 0 auto;
  }

  .trend-site-url span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .trend-site-url:hover,
  .trend-site-url:focus-visible {
    color: var(--main-color);
  }

  .trend-site-url:focus-visible {
    outline: 2px solid var(--el-color-primary-light-5);
    outline-offset: 2px;
  }

  @media (width <= 680px) {
    .trend-card-head {
      flex-direction: column;
      align-items: flex-start;
    }

    .trend-actions {
      flex-wrap: wrap;
      width: 100%;
    }

    .trend-actions .el-button {
      margin-left: auto;
    }

    .trend-detail-toolbar {
      flex-direction: column;
      align-items: stretch;
    }

    .trend-detail-toolbar :deep(.el-segmented) {
      width: 100%;
    }

    .trend-detail-toolbar > .el-icon {
      align-self: flex-end;
    }

    .trend-list-summary {
      flex-direction: column;
      gap: 4px;
      align-items: flex-start;
      padding: 9px 12px;
    }

    .trend-line-chart {
      height: 230px;
    }

    .trend-line-chart svg {
      height: 202px;
    }
  }

  .marquee-setting-row {
    display: flex;
    gap: 10px;
    align-items: center;
    min-height: 32px;
  }

  .setting-hint {
    font-size: 12px;
    color: var(--art-gray-500);
  }

  .desc-color-picker :deep(.el-radio-group) {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 12px;
  }

  .desc-color-option {
    margin: 0 !important;
  }

  .desc-color-option :deep(.el-radio__label) {
    display: inline-flex;
    gap: 5px;
    align-items: center;
    padding-left: 6px;
  }

  .desc-color-swatch {
    display: inline-block;
    width: 14px;
    height: 14px;
    background: #8c98a8;
    border: 1px solid rgb(0 0 0 / 12%);
    border-radius: 50%;
  }

  .desc-color-swatch.is-red {
    background: #ff6b6b;
  }

  .desc-color-swatch.is-orange {
    background: #ff9f43;
  }

  .desc-color-swatch.is-yellow {
    background: #ffd166;
  }

  .desc-color-swatch.is-green {
    background: #62f29a;
  }

  .desc-color-swatch.is-cyan {
    background: #5ee6ff;
  }

  .desc-color-swatch.is-blue {
    background: #8ab4ff;
  }

  .desc-color-swatch.is-purple {
    background: #c4a7ff;
  }

  .desc-color-swatch.is-rainbow {
    background: linear-gradient(135deg, #ff5f6d, #ffc371, #5df1b0, #58d8ff, #8ab4ff, #c084fc);
  }

  .desc-color-option.is-checked :deep(.el-radio__label) {
    font-weight: 600;
    color: var(--main-color);
  }
</style>
