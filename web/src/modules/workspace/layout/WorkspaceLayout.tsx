import { Suspense, useEffect } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import {
  Bell,
  Bot,
  Building2,
  Columns3,
  FolderKanban,
  LayoutGrid,
  ListChecks,
  LogOut,
  Moon,
  Settings2,
  Sun,
  Users,
} from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { Permission } from '@/shared/constants/permissions'
import { useSessionStore } from '@/shared/stores/session.store'
import { useTenantContextStore } from '@/shared/stores/tenant.store'
import { useSurfaceStore } from '@/shared/stores/surface.store'
import { resolveTheme, useThemeStore } from '@/shared/stores/theme.store'
import { useLogout } from '@/modules/auth/hooks/useAuth'
import { Initials, LoadingBlock, Menu, MenuItem, WsToaster } from '../ui'
import { useProfile, useUnreadCount } from '../hooks/useWorkspace'

interface NavEntry {
  to: string
  label: string
  icon: typeof LayoutGrid
  end?: boolean
  show: boolean
}

function useNavigation(): NavEntry[] {
  const { isClient, canManage, can } = useProfile()

  return [
    { to: '/w', label: isClient ? 'Início' : 'Visão geral', icon: LayoutGrid, end: true, show: true },
    { to: '/w/projects', label: 'Projetos', icon: FolderKanban, show: can(Permission.PROJECT_READ) },
    { to: '/w/tasks', label: 'Tarefas', icon: ListChecks, show: can(Permission.TASK_READ) },
    { to: '/w/board', label: 'Quadro', icon: Columns3, show: can(Permission.BOARD_READ) && can(Permission.TASK_READ) },
    { to: '/w/clients', label: 'Clientes', icon: Building2, show: canManage(Permission.CLIENT_READ) },
    { to: '/w/team', label: 'Equipe', icon: Users, show: canManage(Permission.TASK_ASSIGN) },
  ].filter((entry) => entry.show)
}

function Brand() {
  const tenant = useSessionStore((state) => state.tenant)
  const isMaster = useSessionStore((state) => state.isMaster)
  const availableTenants = useSessionStore((state) => state.availableTenants)
  const selectedTenantId = useTenantContextStore((state) => state.selectedTenantId)
  const { client } = useProfile()
  // Masters operam o tenant selecionado na administração; o nome reflete o contexto ativo.
  const activeName = isMaster ? (availableTenants.find((item) => item.id === selectedTenantId)?.name ?? tenant?.name) : tenant?.name

  return (
    <Link to="/w" className="flex shrink-0 items-center gap-2.5">
      <span className="flex size-8 items-center justify-center rounded-ws bg-ws-ink tabular-nums text-[13px] font-medium text-ws-canvas">n/</span>
      <span className="hidden leading-tight sm:block">
        <span className="block text-[13px] font-semibold text-ws-ink">{client?.name ?? activeName ?? 'Workspace'}</span>
        <span className="block tabular-nums text-[10.5px] tracking-wide text-ws-ink-3 uppercase">{client ? 'área do cliente' : 'nox tasks'}</span>
      </span>
    </Link>
  )
}

function NotificationsBell() {
  const unread = useUnreadCount()
  const count = unread.data?.unread ?? 0

  return (
    <NavLink
      to="/w/notifications"
      aria-label={count ? `Notificações (${count} não lidas)` : 'Notificações'}
      className={({ isActive }) =>
        cn('relative inline-flex size-8 items-center justify-center rounded-ws-sm text-ws-ink-2 hover:bg-ws-hover hover:text-ws-ink', isActive && 'bg-ws-hover text-ws-ink')
      }
    >
      <Bell className="size-4" />
      {count > 0 && (
        <span className="absolute -top-0.5 -right-0.5 min-w-4 rounded-full bg-ws-danger px-1 text-center tabular-nums text-[10px] leading-4 font-medium text-white">
          {count > 99 ? '99+' : count}
        </span>
      )}
    </NavLink>
  )
}

function ThemeSwitch() {
  const theme = useThemeStore((state) => state.theme)
  const setTheme = useThemeStore((state) => state.setTheme)
  const dark = resolveTheme(theme) === 'dark'

  return (
    <button
      type="button"
      onClick={() => setTheme(dark ? 'light' : 'dark')}
      aria-label={dark ? 'Usar tema claro' : 'Usar tema escuro'}
      className="inline-flex size-8 items-center justify-center rounded-ws-sm text-ws-ink-2 hover:bg-ws-hover hover:text-ws-ink"
    >
      {dark ? <Sun className="size-4" /> : <Moon className="size-4" />}
    </button>
  )
}

function UserMenu() {
  const user = useSessionStore((state) => state.user)
  const navigate = useNavigate()
  const logout = useLogout()
  const { isClient, can } = useProfile()

  return (
    <Menu
      trigger={({ toggle }) => (
        <button type="button" onClick={toggle} aria-label="Menu da conta" className="rounded-ws-sm p-0.5 hover:bg-ws-hover">
          <Initials name={user?.name} size="md" />
        </button>
      )}
    >
      {(close) => (
        <>
          <div className="mb-1 rounded-ws-sm bg-ws-sunken px-2.5 py-2">
            <p className="truncate text-[13px] font-medium text-ws-ink">{user?.name}</p>
            <p className="truncate text-[12px] text-ws-ink-3">{user?.email}</p>
          </div>
          {can(Permission.MCP_USE) && (
            <MenuItem icon={<Bot className="size-3.5" />} onSelect={() => { close(); navigate('/w/connections') }}>
              Conexões de IA
            </MenuItem>
          )}
          {!isClient && (
            <MenuItem icon={<Settings2 className="size-3.5" />} onSelect={() => { close(); navigate('/dashboard') }}>
              Administração da plataforma
            </MenuItem>
          )}
          <MenuItem danger icon={<LogOut className="size-3.5" />} onSelect={() => logout.mutate()}>
            Sair
          </MenuItem>
        </>
      )}
    </Menu>
  )
}

/**
 * Shell do Workspace — identidade visual própria: navegação horizontal no topo
 * (desktop) e barra inferior (mobile).
 */
export function WorkspaceLayout() {
  const setSurface = useSurfaceStore((state) => state.setSurface)
  const navigation = useNavigation()

  useEffect(() => {
    setSurface('workspace')
    document.title = 'Nox Tasks'

    return () => setSurface('admin')
  }, [setSurface])

  return (
    <div className="ws min-h-dvh">
      <header className="sticky top-0 z-30 bg-ws-panel/95 shadow-ws-sm backdrop-blur-sm">
        <div className="mx-auto flex h-14 max-w-[1440px] items-center gap-6 px-4 md:px-6">
          <Brand />
          <nav aria-label="Principal" className="hidden items-center gap-1 md:flex">
            {navigation.map((entry) => (
              <NavLink
                key={entry.to}
                to={entry.to}
                end={entry.end}
                className={({ isActive }) =>
                  cn(
                    'flex h-8 items-center rounded-ws-sm px-3 text-[13px] transition-colors',
                    isActive ? 'bg-ws-accent-tint font-medium text-ws-accent' : 'text-ws-ink-2 hover:bg-ws-hover hover:text-ws-ink',
                  )
                }
              >
                {entry.label}
              </NavLink>
            ))}
          </nav>
          <div className="ml-auto flex items-center gap-1">
            <ThemeSwitch />
            <NotificationsBell />
            <UserMenu />
          </div>
        </div>
      </header>

      <main className="mx-auto w-full max-w-[1440px] px-4 pt-6 pb-24 md:px-6 md:pb-12">
        <Suspense fallback={<LoadingBlock rows={6} />}>
          <Outlet />
        </Suspense>
      </main>

      <nav aria-label="Principal" className="fixed inset-x-0 bottom-0 z-30 flex bg-ws-panel shadow-ws-up md:hidden">
        {navigation.slice(0, 5).map((entry) => (
          <NavLink
            key={entry.to}
            to={entry.to}
            end={entry.end}
            className={({ isActive }) =>
              cn('flex flex-1 flex-col items-center gap-0.5 py-2 text-[10.5px]', isActive ? 'text-ws-accent' : 'text-ws-ink-3')
            }
          >
            <entry.icon className="size-4.5" aria-hidden="true" />
            {entry.label}
          </NavLink>
        ))}
      </nav>

      <WsToaster />
    </div>
  )
}
