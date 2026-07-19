<!-- 登录页面 -->
<template>
  <div class="auth-login-page">
    <AuthTopBar :hide-mobile-brand="true" />

    <main class="auth-login-panel">
      <section class="auth-brand-pane" :aria-label="brand.name">
        <div class="brand-lockup">
          <ArtLogo size="46" />
          <strong>{{ brand.name }}</strong>
        </div>

        <div class="brand-visual" aria-hidden="true">
          <img :src="loginArtwork" alt="" />
        </div>

        <div class="brand-message">
          <h2>{{ $t('login.leftView.title') }}</h2>
        </div>
      </section>

      <section class="auth-form-pane">
        <div class="auth-right-wrap">
          <div class="form">
            <div class="auth-form-header">
              <h1 class="title">{{ $t('login.title') }}</h1>
              <p class="sub-title">{{ $t('login.subTitle') }}</p>
            </div>
            <ElForm
              ref="formRef"
              :model="formData"
              :rules="rules"
              :key="formKey"
              @keyup.enter="handleSubmit"
              autocomplete="off"
              class="login-form"
            >
              <ElFormItem prop="username">
                <ElInput
                  class="custom-height"
                  :placeholder="$t('login.placeholder.username')"
                  v-model.trim="formData.username"
                  autocomplete="off"
                >
                  <template #prefix>
                    <ArtSvgIcon icon="ri:user-3-line" class="input-icon" />
                  </template>
                </ElInput>
              </ElFormItem>
              <ElFormItem prop="password">
                <ElInput
                  class="custom-height"
                  :placeholder="$t('login.placeholder.password')"
                  v-model.trim="formData.password"
                  type="password"
                  autocomplete="new-password"
                  show-password
                >
                  <template #prefix>
                    <ArtSvgIcon icon="ri:lock-password-line" class="input-icon" />
                  </template>
                </ElInput>
              </ElFormItem>

              <!-- 推拽验证 -->
              <div class="drag-verify-field">
                <div
                  class="drag-verify-shell"
                  :class="{ '!border-[#FF4E4F]': !isPassing && isClickPass }"
                >
                  <ArtDragVerify
                    ref="dragVerify"
                    v-model:value="isPassing"
                    :text="$t('login.sliderText')"
                    textColor="var(--art-gray-700)"
                    :successText="$t('login.sliderSuccessText')"
                    progressBarBg="var(--main-color)"
                    :background="isDark ? '#26272F' : '#F1F1F4'"
                    handlerBg="var(--default-box-color)"
                  />
                </div>
                <p class="drag-verify-error" :class="{ 'is-visible': !isPassing && isClickPass }">
                  {{ $t('login.placeholder.slider') }}
                </p>
              </div>

              <div class="login-options">
                <ElCheckbox v-model="formData.rememberPassword">{{
                  $t('login.rememberPwd')
                }}</ElCheckbox>
              </div>

              <div class="submit-row">
                <ElButton
                  class="w-full custom-height"
                  type="primary"
                  @click="handleSubmit"
                  :loading="loading"
                  v-ripple
                >
                  {{ $t('login.btnText') }}
                </ElButton>
              </div>
            </ElForm>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup lang="ts">
  import { useQifuBrand } from '@/composables/useQifuBrand'
  import { useUserStore } from '@/store/modules/user'
  import { useI18n } from 'vue-i18n'
  import { HttpError } from '@/utils/http/error'
  import { fetchLogin } from '@/api/auth'
  import { ElNotification, type FormInstance, type FormRules } from 'element-plus'
  import { useSettingStore } from '@/store/modules/setting'
  import loginArtwork from '@imgs/login/lf_icon2.webp'

  defineOptions({ name: 'Login' })

  const settingStore = useSettingStore()
  const { isDark } = storeToRefs(settingStore)
  const { t, locale } = useI18n()
  const formKey = ref(0)

  // 监听语言切换，重置表单
  watch(locale, () => {
    formKey.value++
  })

  const dragVerify = ref()

  const userStore = useUserStore()
  const router = useRouter()
  const route = useRoute()
  const isPassing = ref(false)
  const isClickPass = ref(false)

  const brand = useQifuBrand()
  const formRef = ref<FormInstance>()

  const formData = reactive({
    username: '',
    password: '',
    rememberPassword: false
  })

  const rules = computed<FormRules>(() => ({
    username: [{ required: true, message: t('login.placeholder.username'), trigger: 'blur' }],
    password: [{ required: true, message: t('login.placeholder.password'), trigger: 'blur' }]
  }))

  const loading = ref(false)

  // 登录
  const handleSubmit = async () => {
    if (!formRef.value) return

    try {
      // 表单验证
      const valid = await formRef.value.validate()
      if (!valid) return

      // 拖拽验证
      if (!isPassing.value) {
        isClickPass.value = true
        return
      }

      loading.value = true

      // 登录请求
      const { username, password } = formData

      const { token, refreshToken, user } = await fetchLogin({
        userName: username,
        password
      })

      // 验证token
      if (!token) {
        throw new Error('Login failed - no token received')
      }

      // 存储 token 和登录状态
      userStore.setToken(token, refreshToken)
      userStore.setLoginStatus(true)

      // 登录成功处理
      showLoginSuccessNotice()

      // 获取 redirect 参数，如果存在则跳转到指定页面，否则跳转到首页
      const redirect = route.query.redirect as string
      router.push(redirect || user?.homePath || '/')
    } catch (error) {
      // 处理 HttpError
      if (error instanceof HttpError) {
        // console.log(error.code)
      } else {
        // 处理非 HttpError
        // ElMessage.error('登录失败，请稍后重试')
        console.error('[Login] Unexpected error:', error)
      }
    } finally {
      loading.value = false
      resetDragVerify()
    }
  }

  // 重置拖拽验证
  const resetDragVerify = () => {
    dragVerify.value.reset()
  }

  // 登录成功提示
  const showLoginSuccessNotice = () => {
    setTimeout(() => {
      ElNotification({
        title: t('login.success.title'),
        type: 'success',
        duration: 2500,
        zIndex: 10000,
        message: `${t('login.success.message')}, ${brand.name}!`
      })
    }, 1000)
  }
</script>

<style scoped>
  @import './style.css';
</style>

<style scoped>
  .login-form :deep(.el-form-item) {
    margin-bottom: 18px;
  }

  .login-form :deep(.el-input__wrapper) {
    padding: 0 14px;
    border-radius: 8px;
    box-shadow: 0 0 0 1px var(--art-gray-300) inset;
    transition:
      box-shadow 180ms ease,
      background-color 180ms ease;
  }

  .login-form :deep(.el-input__wrapper:hover) {
    box-shadow: 0 0 0 1px var(--el-color-primary-light-5) inset;
  }

  .login-form :deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 1px var(--el-color-primary) inset;
  }

  .drag-verify-shell :deep(.art-drag-verify) {
    height: 48px !important;
  }

  .submit-row :deep(.el-button) {
    border-radius: 8px;
  }

  @media only screen and (height <= 640px) and (width > 760px) {
    .login-form :deep(.el-form-item) {
      margin-bottom: 14px;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .login-form :deep(.el-input__wrapper) {
      transition: none;
    }
  }
</style>
