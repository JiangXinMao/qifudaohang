<!-- 系统logo -->
<template>
  <div class="flex-cc">
    <img
      v-if="brand.logo && !logoFailed"
      :style="logoStyle"
      :src="brand.logo"
      :alt="`${brand.name} LOGO`"
      class="w-full h-full object-contain"
      @error="logoFailed = true"
    />
    <img
      v-else
      :style="logoStyle"
      src="@imgs/common/logo.webp"
      alt="logo"
      class="w-full h-full object-contain"
    />
  </div>
</template>

<script setup lang="ts">
  import { useQifuBrand } from '@/composables/useQifuBrand'

  defineOptions({ name: 'ArtLogo' })

  interface Props {
    /** logo 大小 */
    size?: number | string
  }

  const props = withDefaults(defineProps<Props>(), {
    size: 36
  })

  const brand = useQifuBrand()
  const logoFailed = ref(false)
  const logoStyle = computed(() => ({ width: `${props.size}px` }))

  watch(
    () => brand.logo,
    () => {
      logoFailed.value = false
    }
  )
</script>
