import { CalendarClock, ChevronsUp, Equal, Eye, Lock, MessageSquare, Minus, SquareCheck, TriangleAlert } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { Dot, Initials, Pill } from '../ui'
import { daysUntil, dueLabel, shortDate } from '../lib/format'
import type { BoardColumn, Member, Priority, Task, Visibility } from '../lib/types'

const PRIORITY: Record<Priority, { label: string; icon: typeof ChevronsUp; className: string }> = {
  urgent: { label: 'Urgente', icon: TriangleAlert, className: 'text-ws-danger' },
  high: { label: 'Alta', icon: ChevronsUp, className: 'text-ws-warn' },
  normal: { label: 'Normal', icon: Equal, className: 'text-ws-ink-3' },
  low: { label: 'Baixa', icon: Minus, className: 'text-ws-ink-3' },
}

export function PriorityMark({ priority, withLabel = false }: { priority: Priority; withLabel?: boolean }) {
  const { label, icon: Icon, className } = PRIORITY[priority]

  return (
    <span className={cn('inline-flex items-center gap-1 text-[12px]', className)} title={`Prioridade ${label.toLowerCase()}`}>
      <Icon className="size-3.5" strokeWidth={2} aria-hidden="true" />
      {withLabel ? <span className="font-medium">{label}</span> : <span className="sr-only">{label}</span>}
    </span>
  )
}

export function DueMark({ due, closed, className }: { due: string | null; closed?: boolean; className?: string }) {
  if (!due) return null
  const days = daysUntil(due)
  const late = !closed && days !== null && days < 0
  const soon = !closed && days !== null && days >= 0 && days <= 2

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 tabular-nums text-[11.5px]',
        late ? 'text-ws-danger' : soon ? 'text-ws-warn' : 'text-ws-ink-3',
        className,
      )}
    >
      <CalendarClock className="size-3" aria-hidden="true" />
      {closed ? shortDate(due) : dueLabel(due)}
    </span>
  )
}

export function VisibilityMark({ visibility, compact }: { visibility: Visibility; compact?: boolean }) {
  return visibility === 'internal' ? (
    <Pill tone="neutral">
      <Lock className="size-3" aria-hidden="true" />
      {compact ? <span className="sr-only">Interna</span> : 'Interna'}
    </Pill>
  ) : (
    <Pill tone="info">
      <Eye className="size-3" aria-hidden="true" />
      {compact ? <span className="sr-only">Compartilhada com o cliente</span> : 'Compartilhada'}
    </Pill>
  )
}

export function StatusTag({ status }: { status?: BoardColumn }) {
  if (!status) return null

  return (
    <span className="inline-flex items-center gap-1.5 text-[12.5px] text-ws-ink-2">
      <Dot color={status.color} />
      {status.name}
    </span>
  )
}

export function PersonTag({ member, empty = 'Sem responsável' }: { member?: Member | null; empty?: string }) {
  if (!member) return <span className="text-[12.5px] text-ws-ink-3">{empty}</span>

  return (
    <span className="inline-flex min-w-0 items-center gap-1.5 text-[12.5px] text-ws-ink-2">
      <Initials name={member.name} size="xs" />
      <span className="truncate">{member.name}</span>
    </span>
  )
}

export function TaskSignals({ task }: { task: Task }) {
  const recent = task.last_comment_at && Date.now() - new Date(task.last_comment_at).getTime() < 48 * 3600 * 1000

  return (
    <span className="inline-flex items-center gap-2.5 tabular-nums text-[11px] text-ws-ink-3">
      {task.subtasks_summary && task.subtasks_summary.total > 0 && (
        <span
          className={cn('inline-flex items-center gap-1', task.subtasks_summary.percent === 100 && 'text-ws-ok')}
          title="Subtarefas concluídas"
        >
          <SquareCheck className="size-3" aria-hidden="true" />
          {task.subtasks_summary.done}/{task.subtasks_summary.total}
        </span>
      )}
      {(task.comments_count ?? 0) > 0 && (
        <span className={cn('inline-flex items-center gap-1', recent && 'text-ws-accent')} title={recent ? 'Comentário recente' : 'Comentários'}>
          <MessageSquare className="size-3" aria-hidden="true" />
          {task.comments_count}
          {recent && <span className="size-1.5 rounded-full bg-ws-accent" aria-label="novo" />}
        </span>
      )}
    </span>
  )
}

export function Tags({ tags }: { tags?: Array<{ name: string }> }) {
  if (!tags?.length) return null

  return (
    <span className="flex flex-wrap gap-1">
      {tags.map((tag) => (
        <span key={tag.name} className="rounded-[5px] bg-ws-hover px-1.5 tabular-nums text-[10.5px] text-ws-ink-2">
          #{tag.name}
        </span>
      ))}
    </span>
  )
}
