import { NavLink } from 'react-router'
import { cn } from '@/shared/utils/cn'

export function TabLinks({ tabs }: { tabs: Array<{ to: string; label: string; end?: boolean; count?: number }> }) {
  return (
    <nav className="ws-scroll-x inline-flex max-w-full gap-1 overflow-x-auto rounded-ws bg-ws-hover p-1" aria-label="Seções">
      {tabs.map((tab) => (
        <NavLink
          key={tab.to}
          to={tab.to}
          end={tab.end}
          className={({ isActive }) =>
            cn(
              'flex h-8 shrink-0 items-center gap-1.5 rounded-ws-sm px-3 text-[13px] transition-colors',
              isActive ? 'bg-ws-panel font-medium text-ws-ink shadow-ws-xs' : 'text-ws-ink-2 hover:text-ws-ink',
            )
          }
        >
          {tab.label}
          {tab.count !== undefined && <span className="tabular-nums text-[11px] text-ws-ink-3">{tab.count}</span>}
        </NavLink>
      ))}
    </nav>
  )
}
