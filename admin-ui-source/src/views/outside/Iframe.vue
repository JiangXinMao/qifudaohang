<template>
  <div class="qifu-iframe-shell" v-loading="isLoading">
    <iframe
      ref="iframeRef"
      :src="iframeUrl"
      :style="{ height: frameHeight }"
      :class="{ 'is-ready': !isLoading }"
      frameborder="0"
      class="qifu-business-frame"
      @load="handleIframeLoad"
    ></iframe>
  </div>
</template>

<script setup lang="ts">
  import { IframeRouteManager } from '@/router/core'

  defineOptions({ name: 'IframeView' })

  const route = useRoute()
  const isLoading = ref(true)
  const iframeUrl = ref('')
  const frameHeight = ref('calc(100dvh - 150px)')
  const iframeRef = ref<HTMLIFrameElement | null>(null)
  let frameResizeObserver: ResizeObserver | null = null
  let frameMutationObserver: MutationObserver | null = null

  const embeddedStyle = `
    html, body {
      width: 100% !important;
      min-width: 0 !important;
      min-height: 0 !important;
      margin: 0 !important;
      overflow-x: hidden !important;
      background: transparent !important;
    }
    body.qf-admin,
    body.qf-admin.qf-sidebar-collapsed,
    html body.qf-admin.qf-art-pro.qf-detail-page {
      width: 100% !important;
      min-width: 0 !important;
      min-height: 0 !important;
      margin: 0 !important;
      padding: 0 !important;
      overflow-x: hidden !important;
    }
    body.qf-admin .qf-sidebar,
    body.qf-admin .qf-topbar,
    body.qf-admin .qf-worktabs,
    body.qf-admin .qf-progress,
    body.qf-admin .qf-mobile-mask {
      display: none !important;
    }
    body.qf-admin > .container,
    body.qf-admin > .container-fluid,
    html body.qf-admin.qf-art-pro.qf-detail-page > .container,
    html body.qf-admin.qf-art-pro.qf-detail-page > .container-fluid {
      width: 100% !important;
      max-width: none !important;
      min-width: 0 !important;
      min-height: 0 !important;
      margin: 0 !important;
      padding: 0 0 20px !important;
      box-sizing: border-box !important;
    }
    body.qf-admin > .container > .center-block,
    body.qf-admin > .container-fluid > .center-block,
    body.qf-admin > .container > .center-block[class*="col-"],
    body.qf-admin > .container-fluid > .center-block[class*="col-"],
    body.qf-admin > .container > [class*="col-"][style*="float"],
    body.qf-admin > .container-fluid > [class*="col-"][style*="float"] {
      width: 100% !important;
      max-width: none !important;
      min-width: 0 !important;
      float: none !important;
      margin-right: auto !important;
      margin-left: auto !important;
      padding: 0 !important;
    }
    body.qf-admin > .container > .qf-detail-content,
    body.qf-admin > .container-fluid > .qf-detail-content {
      width: 100% !important;
      max-width: none !important;
      min-width: 0 !important;
      float: none !important;
      margin-right: auto !important;
      margin-left: auto !important;
      padding: 0 !important;
    }
    body.qf-admin .panel,
    body.qf-admin .art-page-header,
    body.qf-admin .art-link-switch,
    body.qf-admin .ad-page-head,
    body.qf-admin .ad-global-control,
    body.qf-admin .ad-media-band {
      width: 100% !important;
      max-width: none !important;
      box-sizing: border-box !important;
    }
    body.qf-admin #settingsShortcuts { display: none !important; }
    @media (max-width: 760px) {
      body.qf-admin > .container,
      body.qf-admin > .container-fluid { padding: 0 0 16px !important; }
    }
  `

  const stopFrameObservers = () => {
    frameResizeObserver?.disconnect()
    frameMutationObserver?.disconnect()
    frameResizeObserver = null
    frameMutationObserver = null
    window.removeEventListener('resize', updateFrameHeight)
  }

  const forceEmbeddedFullWidth = (doc: Document): void => {
    const root = doc.documentElement
    const body = doc.body

    ;[root, body].forEach((element) => {
      element.style.setProperty('width', '100%', 'important')
      element.style.setProperty('max-width', 'none', 'important')
      element.style.setProperty('min-width', '0', 'important')
      element.style.setProperty('margin', '0', 'important')
      element.style.setProperty('padding-left', '0', 'important')
      element.style.setProperty('padding-right', '0', 'important')
    })

    body.style.setProperty('padding-top', '0', 'important')
    body.style.setProperty('background', 'transparent', 'important')

    doc
      .querySelectorAll<HTMLElement>('body > .container, body > .container-fluid')
      .forEach((container) => {
        container.style.setProperty('width', '100%', 'important')
        container.style.setProperty('max-width', 'none', 'important')
        container.style.setProperty('min-width', '0', 'important')
        container.style.setProperty('margin', '0', 'important')
        container.style.setProperty('padding', '0 0 20px', 'important')
      })

    doc
      .querySelectorAll<HTMLElement>(
        '.qf-detail-content, body > .container > .center-block, body > .container-fluid > .center-block'
      )
      .forEach((content) => {
        content.style.setProperty('width', '100%', 'important')
        content.style.setProperty('max-width', 'none', 'important')
        content.style.setProperty('min-width', '0', 'important')
        content.style.setProperty('margin-left', '0', 'important')
        content.style.setProperty('margin-right', '0', 'important')
        content.style.setProperty('padding-left', '0', 'important')
        content.style.setProperty('padding-right', '0', 'important')
      })
  }

  const updateFrameHeight = () => {
    const frame = iframeRef.value
    const doc = frame?.contentDocument
    if (!frame || !doc) return

    const documentHeight = doc.documentElement?.scrollHeight || 0
    const bodyHeight = doc.body?.scrollHeight || 0
    const availableHeight = Math.max(540, window.innerHeight - 150)
    frameHeight.value = `${Math.max(availableHeight, documentHeight, bodyHeight)}px`
  }

  const observeFrameContent = () => {
    const doc = iframeRef.value?.contentDocument
    if (!doc?.documentElement || !doc.body) return

    stopFrameObservers()
    frameResizeObserver = new ResizeObserver(updateFrameHeight)
    frameResizeObserver.observe(doc.documentElement)
    frameResizeObserver.observe(doc.body)
    frameMutationObserver = new MutationObserver(updateFrameHeight)
    frameMutationObserver.observe(doc.body, {
      attributes: true,
      childList: true,
      subtree: true
    })
    window.addEventListener('resize', updateFrameHeight)
    updateFrameHeight()
  }

  /**
   * 初始化 iframe URL
   * 从路由配置中获取对应的外部链接地址
   */
  const syncIframeUrl = () => {
    stopFrameObservers()
    const iframeRoute = IframeRouteManager.getInstance().findByPath(route.path)

    if (iframeRoute?.meta) {
      iframeUrl.value = iframeRoute.meta.link || ''
      isLoading.value = true
    }
  }

  onMounted(syncIframeUrl)
  watch(() => route.path, syncIframeUrl)

  /**
   * 处理 iframe 加载完成事件
   * 隐藏加载状态
   */
  const handleIframeLoad = (): void => {
    try {
      const doc = iframeRef.value?.contentDocument
      if (doc?.head && !doc.getElementById('qifu-art-embed-style')) {
        const style = doc.createElement('style')
        style.id = 'qifu-art-embed-style'
        style.textContent = embeddedStyle
        doc.head.appendChild(style)
      }
      doc?.body?.classList.add('qf-art-embedded')
      if (doc?.documentElement && doc.body) forceEmbeddedFullWidth(doc)
      observeFrameContent()
    } catch (error) {
      console.warn('[QifuAdmin] 无法应用内嵌页面样式', error)
    }
    isLoading.value = false
  }

  onBeforeUnmount(stopFrameObservers)
</script>

<style scoped>
  .qifu-iframe-shell {
    width: 100%;
    min-width: 0;
    max-width: none;
    min-height: calc(100dvh - 150px);
    padding: 0;
    margin: 0;
    overflow: visible;
    background: transparent;
  }

  .qifu-business-frame {
    display: block;
    width: 100%;
    min-width: 0;
    max-width: none;
    min-height: calc(100dvh - 150px);
    padding: 0;
    margin: 0;
    background: transparent;
    border: 0;
    opacity: 0;
    transform: translate3d(16px, 0, 0);
    transition:
      opacity 220ms cubic-bezier(0.22, 1, 0.36, 1),
      transform 220ms cubic-bezier(0.22, 1, 0.36, 1);
    will-change: opacity, transform;
  }

  .qifu-business-frame.is-ready {
    opacity: 1;
    transform: translate3d(0, 0, 0);
  }

  @media (width <= 760px) {
    .qifu-iframe-shell,
    .qifu-business-frame {
      min-height: calc(100dvh - 128px);
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .qifu-business-frame {
      transition: none;
    }
  }
</style>
