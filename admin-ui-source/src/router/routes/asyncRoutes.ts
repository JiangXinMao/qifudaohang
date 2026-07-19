import { AppRouteRecord } from '@/types/router'

const page = '/qifu/admin-page'

function embeddedLink(link: string): string {
  const hashIndex = link.indexOf('#')
  const base = hashIndex >= 0 ? link.slice(0, hashIndex) : link
  const hash = hashIndex >= 0 ? link.slice(hashIndex) : ''
  const separator = base.includes('?') ? '&' : '?'
  return `${base}${separator}embedded=1${hash}`
}

function legacy(
  path: string,
  name: string,
  title: string,
  link: string,
  icon: string
): AppRouteRecord {
  return {
    path: `/outside/iframe/${path}`,
    name,
    meta: {
      title,
      icon,
      link: embeddedLink(link),
      isIframe: true,
      keepAlive: true,
      roles: ['R_SUPER', 'R_ADMIN']
    }
  }
}

/** 祈福导航后台菜单。旧 PHP 业务页嵌入 Art 外壳，保留原有表单和数据处理能力。 */
export const asyncRoutes: AppRouteRecord[] = [
  {
    name: 'Dashboard',
    path: '/dashboard',
    component: '/index/index',
    meta: { title: '工作台', icon: 'ri:pie-chart-line', roles: ['R_SUPER', 'R_ADMIN'] },
    children: [
      {
        path: 'console',
        name: 'Console',
        component: page,
        meta: { title: '仪表盘', icon: 'ri:dashboard-3-line', fixedTab: true }
      }
    ]
  },
  {
    name: 'System',
    path: '/system',
    component: '/index/index',
    meta: { title: '系统配置', icon: 'ri:settings-3-line', roles: ['R_SUPER', 'R_ADMIN'] },
    children: [
      {
        path: 'settings',
        name: 'Settings',
        redirect: '/outside/iframe/settings-basic',
        meta: { title: '快捷设置', isHide: true, isHideTab: true }
      },
      {
        path: '/outside/iframe/legacy-settings',
        name: 'LegacySettings',
        redirect: '/outside/iframe/settings-basic',
        meta: { title: '完整系统设置', isHide: true, isHideTab: true }
      },
      legacy(
        'settings-basic',
        'SettingsBasic',
        '站点信息',
        './set.php#basic-settings',
        'ri:settings-4-line'
      ),
      legacy(
        'settings-background',
        'SettingsBackground',
        '背景设置',
        './set.php#background-settings',
        'ri:image-line'
      ),
      legacy(
        'settings-interface',
        'SettingsInterface',
        '前台界面',
        './set.php#ui-settings',
        'ri:layout-grid-line'
      ),
      legacy(
        'settings-media',
        'SettingsMedia',
        '在线音乐',
        './set.php#media-settings',
        'ri:music-2-line'
      ),
      legacy(
        'settings-stats',
        'SettingsStats',
        '在线统计',
        './set.php#online-stats-settings',
        'ri:bar-chart-2-line'
      ),
      legacy(
        'settings-footer',
        'SettingsFooter',
        '底部信息',
        './set.php#footer-settings',
        'ri:file-list-3-line'
      ),
      legacy(
        'settings-mail',
        'SettingsMail',
        '邮件通知',
        './set.php#mail-settings',
        'ri:mail-settings-line'
      ),
      {
        path: 'user-center',
        name: 'UserCenter',
        component: page,
        meta: { title: '个人中心', icon: 'ri:user-3-line', keepAlive: true, isHide: true }
      },
      {
        path: 'password',
        name: 'Password',
        component: page,
        meta: { title: '账号安全', icon: 'ri:lock-password-line', keepAlive: true, isHide: true }
      }
    ]
  },
  {
    name: 'Content',
    path: '/content',
    component: '/index/index',
    meta: { title: '内容运营', icon: 'ri:layout-grid-line', roles: ['R_SUPER', 'R_ADMIN'] },
    children: [
      {
        path: 'sites',
        name: 'Sites',
        component: page,
        meta: { title: '站点管理', icon: 'ri:global-line', keepAlive: true }
      },
      {
        path: 'categories',
        name: 'Categories',
        component: page,
        meta: { title: '分类管理', icon: 'ri:folder-2-line', keepAlive: true }
      },
      {
        path: 'links',
        name: 'Links',
        component: page,
        meta: { title: '友链管理', icon: 'ri:links-line', keepAlive: true }
      },
      legacy('legacy-ads', 'LegacyAds', '广告管理', './ad.php', 'ri:advertisement-line')
    ]
  },
  {
    name: 'Maintenance',
    path: '/maintenance',
    component: '/index/index',
    meta: { title: '维护工具', icon: 'ri:tools-line', roles: ['R_SUPER', 'R_ADMIN'] },
    children: [
      {
        path: 'logs',
        name: 'Logs',
        component: page,
        meta: { title: '操作日志', icon: 'ri:file-list-3-line', keepAlive: true }
      },
      {
        path: 'backup',
        name: 'Backup',
        component: page,
        meta: { title: '数据备份', icon: 'ri:database-2-line', keepAlive: true }
      }
    ]
  },
  {
    name: 'About',
    path: '/about',
    component: '/index/index',
    meta: { title: '关于系统', icon: 'ri:information-line', roles: ['R_SUPER', 'R_ADMIN'] },
    children: [
      {
        path: 'index',
        name: 'AboutIndex',
        component: page,
        meta: { title: '检查更新', icon: 'ri:refresh-line', keepAlive: true }
      },
      {
        path: 'system-info',
        name: 'SystemInfo',
        component: page,
        meta: { title: '系统信息', icon: 'ri:computer-line', keepAlive: true }
      }
    ]
  }
]
