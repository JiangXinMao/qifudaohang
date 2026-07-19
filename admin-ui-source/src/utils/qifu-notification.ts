export interface QifuSuccessNotice {
  title: string
  message?: string
  meta?: string
  status?: string
}

export function qifuSuccess(
  title: string,
  message = '更改已保存并立即生效。',
  status = '已同步更新'
): void {
  window.dispatchEvent(
    new CustomEvent<QifuSuccessNotice>('qifu-admin-success', {
      detail: { title, message, status, meta: '保存时间：刚刚' }
    })
  )
}
