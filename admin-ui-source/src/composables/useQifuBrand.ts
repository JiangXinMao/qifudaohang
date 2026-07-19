import { reactive, readonly } from 'vue'
import AppConfig from '@/config'
import { qifuBrand, type QifuBrand } from '@/api/qifu'

const fallbackName = AppConfig.systemInfo.name
const brand = reactive<QifuBrand>({
  name: fallbackName,
  logo: '',
  title: ''
})

let initialized = false
let pendingRequest: Promise<void> | null = null

function applyBrand(payload?: Partial<QifuBrand> | null) {
  const name = String(payload?.name || '').trim()
  brand.name = name || fallbackName
  brand.logo = String(payload?.logo || '').trim()
  brand.title = String(payload?.title || '').trim()
}

export function refreshQifuBrand(): Promise<void> {
  if (pendingRequest) return pendingRequest
  pendingRequest = qifuBrand()
    .then((payload) => applyBrand(payload))
    .catch(() => undefined)
    .finally(() => {
      pendingRequest = null
    })
  return pendingRequest
}

function handleBrandMessage(event: MessageEvent) {
  if (event.origin !== window.location.origin) return
  if (!event.data || event.data.type !== 'qifu-brand-updated') return
  applyBrand(event.data.payload)
}

export function initializeQifuBrand() {
  if (!initialized) {
    initialized = true
    window.addEventListener('message', handleBrandMessage)
  }
  return refreshQifuBrand()
}

export function useQifuBrand() {
  return readonly(brand)
}

export function getQifuBrandName() {
  return brand.name
}
