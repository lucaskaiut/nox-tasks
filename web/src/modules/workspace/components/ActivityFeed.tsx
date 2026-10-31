import { Bot } from 'lucide-react'
import { Link } from 'react-router'
import { Initials } from '../ui'
import { changeLines, eventLabel, relative } from '../lib/format'
import type { Activity } from '../lib/types'

export function ActivityFeed({ items, showTarget = true }: { items: Activity[]; showTarget?: boolean }) {
  if (items.length === 0) return <p className="text-[13px] text-ws-ink-3">Nenhuma atividade ainda.</p>

  return (
    <ol className="relative flex flex-col">
      {items.map((activity) => {
        const lines = changeLines(activity)

        return (
          <li key={activity.id} className="relative flex gap-3 pb-4 last:pb-0">
            <span className="absolute top-7 bottom-0 left-3 w-px bg-ws-line" aria-hidden="true" />
            <Initials name={activity.user?.name ?? 'Sistema'} size="sm" className="relative z-[1] bg-ws-panel" />
            <div className="min-w-0 flex-1 pt-0.5">
              <p className="text-[13px] leading-snug text-ws-ink-2">
                <span className="font-medium text-ws-ink">{activity.user?.name ?? 'Sistema'}</span> {eventLabel(activity, showTarget)}{' '}
                {showTarget && activity.task && (
                  <Link to={`/w/tasks/${activity.task.id}`} className="font-medium text-ws-ink underline-offset-2 hover:underline">
                    {activity.task.title}
                  </Link>
                )}
                {showTarget && !activity.task && activity.project && (
                  <Link to={`/w/projects/${activity.project.id}`} className="font-medium text-ws-ink underline-offset-2 hover:underline">
                    {activity.project.name}
                  </Link>
                )}
              </p>
              {lines.length > 0 && (
                <ul className="mt-1 flex flex-col gap-0.5">
                  {lines.map((line) => (
                    <li key={line} className="tabular-nums text-[11.5px] text-ws-ink-3">
                      {line}
                    </li>
                  ))}
                </ul>
              )}
              <p className="mt-1 flex items-center gap-2 tabular-nums text-[11px] text-ws-ink-3">
                {relative(activity.created_at)}
                {activity.source === 'mcp' && (
                  <span className="inline-flex items-center gap-1 rounded-[5px] bg-ws-accent-tint px-1.5 text-ws-accent" title="Executado por um agente de IA via MCP">
                    <Bot className="size-3" aria-hidden="true" /> agente IA
                  </span>
                )}
                {activity.source === 'api' && <span>via API</span>}
              </p>
            </div>
          </li>
        )
      })}
    </ol>
  )
}
