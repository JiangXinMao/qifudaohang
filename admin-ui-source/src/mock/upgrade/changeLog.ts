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
    version: 'v1.8',
    title: '祈福导航 V1.8 正式版',
    date: '2026-07-27',
    detail: [
      '修复后台登录、安装后后台空白与会话频繁失效问题',
      '修复站点筛选、分类状态与分类改名同步问题',
      '新增批量修改站点归属分类功能',
      '统一安装器、后台前端及在线更新版本标识'
    ]
  },
  {
    version: 'v1.7',
    title: '祈福导航 V1.7 正式版',
    date: '2026-07-23',
    detail: [
      '重构后台管理界面、登录页与移动端布局',
      '完善广告、站点、分类与友情链接管理',
      '新增访问统计、远程公告与在线更新能力',
      '完善数据备份恢复、个人中心与系统信息页'
    ]
  }
])
