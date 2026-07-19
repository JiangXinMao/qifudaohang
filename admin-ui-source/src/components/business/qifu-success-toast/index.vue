<template>
  <Transition name="qifu-success-toast">
    <aside
      v-if="visible"
      class="qifu-success-notice"
      data-success-style="G"
      role="status"
      aria-live="polite"
    >
      <span class="qifu-success-notice__icon" aria-hidden="true">
        <ArtSvgIcon icon="ri:check-line" />
      </span>
      <div class="qifu-success-notice__body">
        <strong>{{ notice.title }}</strong>
        <p>{{ notice.message }}</p>
      </div>
      <button
        type="button"
        class="qifu-success-notice__close"
        aria-label="关闭成功提示"
        @click="hide"
      >
        <ArtSvgIcon icon="ri:close-line" />
      </button>
    </aside>
  </Transition>
</template>

<script setup lang="ts">
  interface SuccessNoticePayload {
    title?: string
    message?: string
    meta?: string
    status?: string
  }

  defineOptions({ name: 'QifuSuccessToast' })

  const isSilentContentRoute = () => {
    const path = window.location.hash.replace(/^#/, '').split('?')[0].replace(/\/+$/, '') || '/'
    return (
      path === '/content' || path.startsWith('/content/') || path === '/outside/iframe/legacy-ads'
    )
  }

  const visible = ref(false)
  const notice = reactive({
    title: '保存成功',
    message: '更改已保存并立即生效。',
    meta: '保存时间：刚刚',
    status: '已同步更新'
  })
  let closeTimer: ReturnType<typeof setTimeout> | undefined

  const hide = () => {
    if (closeTimer) clearTimeout(closeTimer)
    visible.value = false
  }

  const show = async (payload: SuccessNoticePayload = {}) => {
    if (isSilentContentRoute()) {
      hide()
      return
    }
    if (closeTimer) clearTimeout(closeTimer)
    Object.assign(notice, {
      title: payload.title || '保存成功',
      message: payload.message || '更改已保存并立即生效。',
      meta: payload.meta || '保存时间：刚刚',
      status: payload.status || '已同步更新'
    })
    visible.value = false
    await nextTick()
    visible.value = true
    closeTimer = setTimeout(hide, 4800)
  }

  const handleSuccessEvent = (event: Event) => {
    show((event as CustomEvent<SuccessNoticePayload>).detail || {})
  }

  const handleFrameMessage = (event: MessageEvent) => {
    if (event.origin !== window.location.origin) return
    if (!event.data || event.data.type !== 'qifu-admin-success') return
    show(event.data.payload || {})
  }

  const handleRouteChange = () => {
    if (isSilentContentRoute()) hide()
  }

  onMounted(() => {
    window.addEventListener('qifu-admin-success', handleSuccessEvent)
    window.addEventListener('message', handleFrameMessage)
    window.addEventListener('hashchange', handleRouteChange)
  })

  onBeforeUnmount(() => {
    hide()
    window.removeEventListener('qifu-admin-success', handleSuccessEvent)
    window.removeEventListener('message', handleFrameMessage)
    window.removeEventListener('hashchange', handleRouteChange)
  })
</script>

<style scoped>
  .qifu-success-notice {
    --qifu-z-success-toast: 2200;

    position: fixed;
    top: 76px;
    left: 50%;
    z-index: var(--qifu-z-success-toast);
    display: flex;
    gap: 9px;
    align-items: center;
    width: min(440px, calc(100vw - 24px));
    min-height: 46px;
    padding: 10px 12px;
    color: #087545;
    background: #ecfdf3;
    border: 1px solid #bfead3;
    border-radius: 8px;
    transform: translateX(-50%);
  }

  .qifu-success-notice__icon {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    width: 24px;
    height: 24px;
    font-size: 14px;
    color: #fff;
    background: #12b76a;
    border-radius: 50%;
  }

  .qifu-success-notice__body {
    display: flex;
    flex: 1;
    gap: 6px;
    align-items: baseline;
    min-width: 0;
  }

  .qifu-success-notice__body strong {
    flex: 0 0 auto;
    font-size: 13px;
  }

  .qifu-success-notice__body p {
    min-width: 0;
    margin: 0;
    overflow: hidden;
    font-size: 11px;
    line-height: 1.5;
    color: #3c7f5f;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .qifu-success-notice__close {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    width: 24px;
    height: 24px;
    padding: 0;
    font-size: 15px;
    color: #2d8a5b;
    cursor: pointer;
    background: transparent;
    border: 0;
    border-radius: 6px;
  }

  .qifu-success-notice__close:hover {
    color: #087545;
    background: rgb(18 183 106 / 10%);
  }

  .qifu-success-notice__close:focus-visible {
    outline: 3px solid rgb(93 135 255 / 28%);
    outline-offset: 2px;
  }

  .qifu-success-toast-enter-active,
  .qifu-success-toast-leave-active {
    transition:
      opacity 0.2s cubic-bezier(0.22, 1, 0.36, 1),
      transform 0.24s cubic-bezier(0.22, 1, 0.36, 1);
  }

  .qifu-success-toast-enter-from,
  .qifu-success-toast-leave-to {
    opacity: 0;
    transform: translate(-50%, -12px);
  }

  @media (width <= 760px) {
    .qifu-success-notice {
      top: 68px;
      right: 12px;
      left: 12px;
      width: auto;
      transform: none;
    }

    .qifu-success-notice__body {
      display: block;
    }

    .qifu-success-notice__body strong {
      display: block;
    }

    .qifu-success-toast-enter-from,
    .qifu-success-toast-leave-to {
      transform: translateY(-12px);
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .qifu-success-toast-enter-active,
    .qifu-success-toast-leave-active {
      transition-duration: 0.01ms;
    }
  }
</style>
