<template>
  <ElConfigProvider
    size="default"
    :locale="locales[language]"
    :z-index="3000"
    :card="{
      shadow: 'never'
    }"
  >
    <RouterView></RouterView>
  </ElConfigProvider>
</template>

<script setup lang="ts">
  import { useUserStore } from './store/modules/user'
  import { fetchSessionStatus } from './api/auth'
  import zh from 'element-plus/es/locale/lang/zh-cn'
  import en from 'element-plus/es/locale/lang/en'
  import { systemUpgrade } from './utils/sys'
  import { toggleTransition } from './utils/ui/animation'
  import { checkStorageCompatibility } from './utils/storage'
  import { initializeTheme } from './hooks/core/useTheme'

  const userStore = useUserStore()
  const { language } = storeToRefs(userStore)

  const locales = {
    zh: zh,
    en: en
  }

  const SESSION_HEARTBEAT_INTERVAL = 5 * 60 * 1000
  let sessionHeartbeatTimer: number | undefined
  let sessionHeartbeatRunning = false

  const refreshAdminSession = async () => {
    if (!userStore.isLogin || sessionHeartbeatRunning || document.visibilityState !== 'visible') {
      return
    }

    sessionHeartbeatRunning = true
    try {
      const session = await fetchSessionStatus()
      if (!session.authenticated && userStore.isLogin) userStore.logOut()
    } catch {
      // A temporary network failure must not clear an otherwise valid local login state.
    } finally {
      sessionHeartbeatRunning = false
    }
  }

  const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') void refreshAdminSession()
  }

  onBeforeMount(() => {
    toggleTransition(true)
    initializeTheme()
  })

  onMounted(() => {
    checkStorageCompatibility()
    toggleTransition(false)
    systemUpgrade()
    sessionHeartbeatTimer = window.setInterval(refreshAdminSession, SESSION_HEARTBEAT_INTERVAL)
    document.addEventListener('visibilitychange', handleVisibilityChange)
  })

  onBeforeUnmount(() => {
    if (sessionHeartbeatTimer !== undefined) window.clearInterval(sessionHeartbeatTimer)
    document.removeEventListener('visibilitychange', handleVisibilityChange)
  })
</script>
