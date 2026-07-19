interface UpgradeLog {
  version: string
  title: string
  date: string
  detail?: string[]
  requireReLogin?: boolean
  remark?: string
}

export const upgradeLogList = ref<UpgradeLog[]>([
  {
    version: 'v1.5.0',
    title: '祈福导航 V1.5.0 正式版',
    date: '2026-07-19',
    detail: [
      '重构后台管理界面、登录页与移动端布局',
      '完善广告、站点、分类与友情链接管理',
      '新增访问统计、远程公告与在线更新能力',
      '完善数据备份恢复、个人中心与系统信息页'
    ]
  }
])
