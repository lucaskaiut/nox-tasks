import { NavLink } from 'react-router'
import { cn } from '@/shared/utils/cn'

export function TabLinks({ tabs }: { tabs: Array<{ to: string; label: string; end?: boolean; count?: number }> }) {
  return (
    <nav className="ws-scroll-x -mb-px flex gap-5 overflow-x-auto border-b border-ws-line" aria-label="Seções">
      {tabs.map((tab) => (
        <NavLink
          key={tab.to}
          to={tab.to}
          end={tab.end}
          className={({ isActive }) =>
            cn(
              'flex h-10 shrink-0 items-center gap-1.5 border-b-2 text-[13px] transition-colors',
              isActive ? 'border-ws-ink font-medium text-ws-ink' : 'border-transparent text-ws-ink-2 hover:text-ws-ink',
            )
          }
        >
          {tab.label}
          {tab.count !== undefined && <span className="font-ws-mono text-[11px] text-ws-ink-3">{tab.count}</span>}
        </NavLink>
      ))}
    </nav>
  )
}
