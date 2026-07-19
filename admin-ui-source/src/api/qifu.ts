import request from '@/utils/http'

export interface QifuBootstrap {
  user: Api.Auth.UserInfo
  settings: Record<string, string>
  ads: Record<string, string>
  stats: {
    todayViews: number
    yesterdayViews: number
    totalViews: number
    totalSites: number
    activeSites: number
    hiddenSites: number
    totalCategories: number
    todayClicks: number
    totalClicks: number
    trend: Array<{ date: string; views: number; clicks: number }>
  }
  categories: any[]
  sites: any[]
  links: any[]
  logs: any[]
  backups: any[]
  csrf: string
}

export type QifuNotificationType = 'danger' | 'warning' | 'info'
export type QifuNotificationCategory = 'site' | 'announcement' | 'update'

export interface QifuNotification {
  id: string
  type: QifuNotificationType
  category: QifuNotificationCategory
  title: string
  description: string
  time: number
  route: string
}

export interface QifuNotificationResponse {
  items: QifuNotification[]
  generatedAt: number
}

export interface QifuBrand {
  name: string
  logo: string
  title: string
}

export interface QifuUpdateHistoryEntry {
  version: string
  date: string
  title: string
  details: string[]
  source: 'bundled' | 'remote' | 'official'
  recordedAt: number
}

export interface QifuUpdateStatus {
  currentVersion: string
  latestVersion: string
  remoteVersion: string
  updateAvailable: boolean
  serviceAvailable: boolean
  checkedAt: number
  history: QifuUpdateHistoryEntry[]
}

export interface QifuUpdateResult {
  version: string
  operationId: string
  changedFiles: number
  backupPath: string
}

export interface QifuUpdateProgress {
  requestId: string
  phase: 'verify' | 'download' | 'overlay' | 'complete' | 'failed'
  percentage: number
  message: string
  status: 'running' | 'completed' | 'failed'
  updatedAt: number
}

export interface QifuSystemInfo {
  productName: string
  currentVersion: string
  phpVersion: string
  database: string
  timezone: string
  serverTime: number
  adminDirectory: string
  sodiumReady: boolean
  zipReady: boolean
  installLocked: boolean
}

export interface QifuProfileLog {
  id: number
  action: string
  target: string
  detail: string
  ip: string
  addtime: number
}

export interface QifuProfile {
  user: Api.Auth.UserInfo
  roleLabel: string
  lastLoginAt: number
  lastLoginIp: string
  passwordChangedAt: number
  sessionStartedAt: number
  sessionActive: boolean
  preferences: {
    defaultPage: '/dashboard/console' | '/content/sites' | '/maintenance/logs'
    tableDensity: 'default' | 'compact' | 'comfortable'
    theme: 'light' | 'dark' | 'auto'
    language: 'zh' | 'en'
  }
  recentLogs: QifuProfileLog[]
}

export function qifuBootstrap() {
  return request.get<QifuBootstrap>({ url: './api.php?action=bootstrap' })
}

export function qifuTrend() {
  return request.get<Array<{ date: string; views: number; clicks: number }>>({
    url: './api.php?action=trend'
  })
}

export interface QifuSiteClickRow {
  id: number
  name: string
  url: string
  category: string
  clicks: number
}

export function qifuSiteClicks(date: string) {
  return request.get<QifuSiteClickRow[]>({
    url: `./api.php?action=site_clicks&date=${encodeURIComponent(date)}`
  })
}

export type QifuSiteMetric = 'views' | 'clicks'

export interface QifuSiteStatRow {
  id: number
  name: string
  url: string
  category: string
  count: number
}

export function qifuSiteStats(date: string, metric: QifuSiteMetric) {
  return request.get<QifuSiteStatRow[]>({
    url: `./api.php?action=site_stats&date=${encodeURIComponent(date)}&metric=${encodeURIComponent(metric)}`
  })
}

export function qifuBrand() {
  return request.get<QifuBrand>({ url: './api.php?action=brand' })
}

export function qifuNotifications() {
  return request.get<QifuNotificationResponse>({ url: './api.php?action=notifications' })
}

export function qifuUpdateStatus() {
  return request.get<QifuUpdateStatus>({ url: './api.php?action=update_status' })
}

export function qifuSystemInfo() {
  return request.get<QifuSystemInfo>({ url: './api.php?action=system_info' })
}

export function qifuCheckUpdates() {
  return qifuAction<QifuUpdateStatus>('update_check')
}

export function qifuApplyUpdate(operationId: string) {
  return qifuCsrf().then((csrf) =>
    request.post<QifuUpdateResult>({
      url: './api.php?action=update_apply',
      headers: { 'X-CSRF-Token': csrf },
      params: { operationId },
      timeout: 300000
    })
  )
}

export function qifuUpdateProgress(operationId: string) {
  return request.get<QifuUpdateProgress>({
    url: `./api.php?action=update_progress&id=${encodeURIComponent(operationId)}`
  })
}

export function qifuTrackAdminUsage(event: string) {
  return qifuAction<{ event: string }>('admin_usage_track', { event })
}

export function qifuUserInfo() {
  return request.get<Api.Auth.UserInfo>({ url: './api.php?action=user_info' })
}

export function qifuProfile() {
  return request.get<QifuProfile>({ url: './api.php?action=profile' })
}

export function qifuSaveProfile(params: {
  nickname: string
  notificationEmail: string
  defaultPage: QifuProfile['preferences']['defaultPage']
  tableDensity: QifuProfile['preferences']['tableDensity']
  theme: QifuProfile['preferences']['theme']
  language: QifuProfile['preferences']['language']
}) {
  return qifuAction<QifuProfile>('profile_save', params)
}

export async function qifuUploadAvatar(file: File) {
  const csrf = await qifuCsrf()
  const form = new FormData()
  form.append('file', file)
  const response = await fetch('./api.php?action=profile_avatar_upload', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-CSRF-Token': csrf },
    body: form
  })
  const json = await response.json().catch(() => null)
  if (!json || json.code !== 200) throw new Error(json?.msg || '头像上传失败')
  return json.data as QifuProfile
}

export async function qifuCsrf() {
  const response = await request.get<{ csrf: string }>({ url: './api.php?action=csrf' })
  return response.csrf
}

export async function qifuLogin(params: Api.Auth.LoginParams) {
  const csrf = await request.get<{ csrf: string }>({ url: './api.php?action=csrf' })
  return request.post<Api.Auth.LoginResponse & { user: Api.Auth.UserInfo }>({
    url: './api.php?action=login',
    headers: { 'X-CSRF-Token': csrf.csrf },
    params
  })
}

export function qifuAction<T = unknown>(action: string, params: Record<string, any> = {}) {
  return qifuCsrf().then((csrf) =>
    request.post<T>({
      url: `./api.php?action=${encodeURIComponent(action)}`,
      headers: { 'X-CSRF-Token': csrf },
      params
    })
  )
}

export interface QifuBackupRestoreResult {
  tableCount: number
  rowCount: number
  sourceVersion: string
  safetyBackup: string
}

export async function qifuRestoreBackup(file: File, password: string) {
  const csrf = await qifuCsrf()
  const form = new FormData()
  form.append('file', file)
  form.append('password', password)
  const response = await fetch('./api.php?action=backup_restore', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-CSRF-Token': csrf },
    body: form
  })
  const json = await response.json().catch(() => null)
  if (!json || json.code !== 200) throw new Error(json?.msg || '数据恢复失败')
  return json.data as QifuBackupRestoreResult
}

export interface QifuSiteMeta {
  name: string
  description: string
  url: string
}

export async function qifuSiteMeta(url: string) {
  const csrf = await qifuCsrf()
  const response = await fetch('./api.php?action=site_meta', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrf
    },
    body: JSON.stringify({ url })
  })
  const json = await response.json().catch(() => null)
  if (!json || json.code !== 200) throw new Error(json?.msg || '自动获取失败，请手动填写')
  return json.data as QifuSiteMeta
}

export async function qifuUpload(file: File, slot: string, position: string, csrf: string) {
  const form = new FormData()
  form.append('file', file)
  form.append('slot', slot)
  form.append('position', position)
  const response = await fetch(`./api.php?action=ad_upload`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-CSRF-Token': csrf },
    body: form
  })
  const json = await response.json()
  if (json.code !== 200) throw new Error(json.msg || '上传失败')
  return json.data as { url: string; width?: number; height?: number }
}
