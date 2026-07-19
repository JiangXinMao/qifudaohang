<template>
  <div
    v-show="visible"
    class="art-notification-panel art-card-sm"
    :style="{
      transform: show ? 'translateY(0) scale(1)' : 'translateY(-8px) scale(0.98)',
      opacity: show ? 1 : 0
    }"
    @click.stop
  >
    <header class="notice-head">
      <div>
        <div class="notice-title-row">
          <h3>消息提醒</h3>
          <span v-if="unreadCount" class="notice-count">{{
            unreadCount > 99 ? '99+' : unreadCount
          }}</span>
        </div>
        <p>失效站点、远程公告与版本更新</p>
      </div>
      <button
        class="icon-action"
        type="button"
        title="刷新消息提醒"
        aria-label="刷新消息提醒"
        :disabled="loading"
        @click="loadNotifications"
      >
        <ArtSvgIcon icon="ri:refresh-line" :class="{ 'is-spinning': loading }" />
      </button>
    </header>

    <div class="notice-toolbar">
      <span>{{ unreadCount ? `${unreadCount} 项未读` : '已全部查看' }}</span>
      <button v-if="unreadCount" type="button" @click="markAllRead">全部已读</button>
    </div>

    <div class="notice-body scrollbar-thin">
      <div v-if="loading && !items.length" class="notice-state">
        <ArtSvgIcon icon="ri:loader-4-line" class="is-spinning" />
        <p>正在检查新消息</p>
      </div>

      <div v-else-if="loadError && !items.length" class="notice-state notice-state-error">
        <ArtSvgIcon icon="ri:wifi-off-line" />
        <p>消息数据暂时无法加载</p>
        <button type="button" @click="loadNotifications">重新加载</button>
      </div>

      <div v-else-if="!items.length" class="notice-state">
        <div class="empty-icon"><ArtSvgIcon icon="ri:checkbox-circle-line" /></div>
        <strong>暂时没有新消息</strong>
        <p>未发现失效站点、远程公告或版本更新</p>
      </div>

      <ul v-else class="notice-list">
        <li v-for="item in items" :key="item.id">
          <button
            type="button"
            class="notice-item"
            :class="[`is-${item.type}`, { 'is-unread': !isRead(item.id) }]"
            @click="openNotification(item)"
          >
            <span class="notice-icon">
              <ArtSvgIcon :icon="categoryMeta[item.category].icon" />
            </span>
            <span class="notice-copy">
              <span class="notice-meta">
                <span>{{ categoryMeta[item.category].label }}</span>
                <time>{{ formatRelativeTime(item.time) }}</time>
              </span>
              <strong>{{ item.title }}</strong>
              <span class="notice-description">{{ item.description }}</span>
            </span>
            <span v-if="!isRead(item.id)" class="unread-dot" aria-label="未读"></span>
            <ArtSvgIcon icon="ri:arrow-right-s-line" class="notice-arrow" />
          </button>
        </li>
      </ul>
    </div>

    <footer v-if="items.length" class="notice-foot">
      <span>点击提醒可直接前往处理</span>
      <span>最近检查 {{ lastCheckedText }}</span>
    </footer>
  </div>
</template>

<script setup lang="ts">
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useRouter } from 'vue-router'
  import {
    qifuNotifications,
    type QifuNotification,
    type QifuNotificationCategory
  } from '@/api/qifu'

  defineOptions({ name: 'ArtNotification' })

  const props = defineProps<{ value: boolean }>()
  const emit = defineEmits<{
    'update:value': [value: boolean]
    'count-change': [value: number]
  }>()

  const router = useRouter()
  const show = ref(false)
  const visible = ref(false)
  const loading = ref(false)
  const loadError = ref(false)
  const items = ref<QifuNotification[]>([])
  const readIds = ref<string[]>([])
  const generatedAt = ref(0)
  let closeTimer: ReturnType<typeof setTimeout> | undefined
  let refreshTimer: ReturnType<typeof setInterval> | undefined

  const storageKey = `qifu-admin-read-notifications-v1:${window.location.pathname}`
  const categoryMeta: Record<QifuNotificationCategory, { label: string; icon: string }> = {
    site: { label: '失效站点', icon: 'ri:global-line' },
    announcement: { label: '远程公告', icon: 'ri:megaphone-line' },
    update: { label: '更新', icon: 'ri:download-cloud-2-line' }
  }

  const unreadCount = computed(() => items.value.filter((item) => !isRead(item.id)).length)
  const lastCheckedText = computed(() => {
    if (!generatedAt.value) return '--'
    return new Date(generatedAt.value * 1000).toLocaleTimeString('zh-CN', {
      hour: '2-digit',
      minute: '2-digit'
    })
  })

  function restoreReadState() {
    try {
      const stored = JSON.parse(localStorage.getItem(storageKey) || '[]')
      readIds.value = Array.isArray(stored)
        ? stored.filter((id) => typeof id === 'string').slice(-200)
        : []
    } catch {
      readIds.value = []
    }
  }

  function persistReadState() {
    try {
      localStorage.setItem(storageKey, JSON.stringify(readIds.value.slice(-200)))
    } catch {
      // The notification center still works when browser storage is unavailable.
    }
  }

  function isRead(id: string) {
    return readIds.value.includes(id)
  }

  function markRead(id: string) {
    if (isRead(id)) return
    readIds.value = [...readIds.value, id].slice(-200)
    persistReadState()
  }

  function markAllRead() {
    const ids = items.value.map((item) => item.id)
    readIds.value = Array.from(new Set([...readIds.value, ...ids])).slice(-200)
    persistReadState()
  }

  async function openNotification(item: QifuNotification) {
    markRead(item.id)
    emit('update:value', false)
    if (router.currentRoute.value.path !== item.route) await router.push(item.route)
  }

  async function loadNotifications() {
    if (loading.value) return
    loading.value = true
    loadError.value = false
    try {
      const response = await qifuNotifications()
      items.value = Array.isArray(response.items) ? response.items : []
      generatedAt.value = Number(response.generatedAt || Math.floor(Date.now() / 1000))
    } catch {
      loadError.value = true
    } finally {
      loading.value = false
    }
  }

  function formatRelativeTime(timestamp: number) {
    const seconds = Math.max(0, Math.floor(Date.now() / 1000) - Number(timestamp || 0))
    if (seconds < 60) return '刚刚'
    if (seconds < 3600) return `${Math.floor(seconds / 60)} 分钟前`
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} 小时前`
    if (seconds < 7 * 86400) return `${Math.floor(seconds / 86400)} 天前`
    return new Date(timestamp * 1000).toLocaleDateString('zh-CN')
  }

  function setPanelVisibility(open: boolean) {
    if (closeTimer) clearTimeout(closeTimer)
    if (open) {
      visible.value = true
      window.requestAnimationFrame(() => {
        show.value = true
      })
      void loadNotifications()
      return
    }
    show.value = false
    closeTimer = setTimeout(() => {
      visible.value = false
    }, 220)
  }

  function handleVisibilityChange() {
    if (document.visibilityState === 'visible') void loadNotifications()
  }

  watch(() => props.value, setPanelVisibility)
  watch(unreadCount, (count) => emit('count-change', count), { immediate: true })

  onMounted(() => {
    restoreReadState()
    void loadNotifications()
    refreshTimer = setInterval(() => void loadNotifications(), 300000)
    document.addEventListener('visibilitychange', handleVisibilityChange)
  })

  onBeforeUnmount(() => {
    if (closeTimer) clearTimeout(closeTimer)
    if (refreshTimer) clearInterval(refreshTimer)
    document.removeEventListener('visibilitychange', handleVisibilityChange)
  })
</script>

<style scoped>
  .art-notification-panel {
    position: absolute;
    top: 58px;
    right: 20px;
    z-index: 50;
    width: min(390px, calc(100vw - 24px));
    overflow: hidden;
    background: var(--default-box-color);
    border: 1px solid var(--art-card-border);
    border-radius: 10px;
    box-shadow: 0 18px 48px rgb(15 23 42 / 16%);
    transition:
      opacity 180ms ease,
      transform 220ms ease;
    transform-origin: top right;
  }

  .notice-head,
  .notice-toolbar,
  .notice-foot,
  .notice-meta,
  .notice-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .notice-head {
    padding: 18px 18px 14px;
  }

  .notice-title-row {
    gap: 8px;
    justify-content: flex-start;
  }

  .notice-head h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 650;
    color: var(--art-gray-900);
  }

  .notice-head p {
    margin: 4px 0 0;
    font-size: 12px;
    color: var(--art-gray-500);
  }

  .notice-count {
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    font-size: 11px;
    line-height: 20px;
    color: #fff;
    text-align: center;
    background: var(--el-color-danger);
    border-radius: 10px;
  }

  .icon-action {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    color: var(--art-gray-600);
    cursor: pointer;
    background: transparent;
    border: 0;
    border-radius: 6px;
  }

  .icon-action:hover {
    color: var(--theme-color);
    background: var(--art-gray-100);
  }

  .icon-action:disabled {
    cursor: default;
    opacity: 0.55;
  }

  .notice-toolbar {
    height: 38px;
    padding: 0 18px;
    font-size: 12px;
    color: var(--art-gray-500);
    background: var(--art-gray-100);
    border-top: 1px solid var(--art-card-border);
    border-bottom: 1px solid var(--art-card-border);
  }

  .notice-toolbar button,
  .notice-state button {
    font-size: 12px;
    color: var(--theme-color);
    cursor: pointer;
    background: transparent;
    border: 0;
  }

  .notice-body {
    max-height: min(480px, calc(100vh - 190px));
    overflow-y: auto;
  }

  .notice-list {
    padding: 6px 0;
    margin: 0;
    list-style: none;
  }

  .notice-list li + li {
    border-top: 1px solid var(--art-card-border);
  }

  .notice-item {
    position: relative;
    display: grid;
    grid-template-columns: 40px minmax(0, 1fr) 16px;
    gap: 12px;
    align-items: start;
    width: 100%;
    padding: 14px 14px 14px 18px;
    color: inherit;
    text-align: left;
    cursor: pointer;
    background: transparent;
    border: 0;
  }

  .notice-item:hover {
    background: var(--art-gray-100);
  }

  .notice-item.is-unread {
    background: color-mix(in srgb, var(--theme-color) 4%, transparent);
  }

  .notice-icon {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    font-size: 19px;
    color: var(--theme-color);
    background: color-mix(in srgb, var(--theme-color) 10%, transparent);
    border-radius: 8px;
  }

  .is-danger .notice-icon {
    color: var(--el-color-danger);
    background: rgb(245 108 108 / 11%);
  }

  .is-warning .notice-icon {
    color: var(--el-color-warning);
    background: rgb(230 162 60 / 12%);
  }

  .notice-copy {
    display: block;
    min-width: 0;
  }

  .notice-meta {
    margin-bottom: 4px;
    font-size: 11px;
    color: var(--art-gray-500);
  }

  .notice-copy strong {
    display: block;
    font-size: 13px;
    font-weight: 600;
    line-height: 20px;
    color: var(--art-gray-900);
  }

  .notice-description {
    display: block;
    margin-top: 3px;
    font-size: 12px;
    line-height: 18px;
    color: var(--art-gray-500);
  }

  .notice-arrow {
    align-self: center;
    font-size: 16px;
    color: var(--art-gray-400);
  }

  .unread-dot {
    position: absolute;
    top: 18px;
    right: 12px;
    width: 6px;
    height: 6px;
    background: var(--el-color-danger);
    border-radius: 50%;
  }

  .notice-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 260px;
    padding: 32px;
    color: var(--art-gray-500);
    text-align: center;
  }

  .notice-state > svg {
    font-size: 30px;
  }

  .notice-state strong {
    margin-top: 14px;
    font-size: 14px;
    color: var(--art-gray-800);
  }

  .notice-state p {
    margin: 7px 0 0;
    font-size: 12px;
  }

  .notice-state-error {
    color: var(--el-color-danger);
  }

  .empty-icon {
    display: grid;
    place-items: center;
    width: 50px;
    height: 50px;
    font-size: 26px;
    color: var(--el-color-success);
    background: rgb(103 194 58 / 11%);
    border-radius: 10px;
  }

  .notice-foot {
    height: 42px;
    padding: 0 18px;
    font-size: 11px;
    color: var(--art-gray-400);
    border-top: 1px solid var(--art-card-border);
  }

  .is-spinning {
    animation: notice-spin 800ms linear infinite;
  }

  @keyframes notice-spin {
    to {
      transform: rotate(360deg);
    }
  }

  .scrollbar-thin::-webkit-scrollbar {
    width: 5px;
  }

  .scrollbar-thin::-webkit-scrollbar-thumb {
    background: var(--art-gray-300);
    border-radius: 3px;
  }

  @media (width <= 640px) {
    .art-notification-panel {
      top: 64px;
      right: 12px;
    }

    .notice-body {
      max-height: calc(100vh - 210px);
    }
  }
</style>
