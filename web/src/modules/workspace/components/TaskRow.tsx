import { Link } from 'react-router'
import { cn } from '@/shared/utils/cn'
import type { Task } from '../lib/types'
import { DueMark, PersonTag, PriorityMark, StatusTag, TaskSignals, VisibilityMark } from './TaskBits'

/** Linha de tarefa — tabela responsiva (em telas pequenas vira um bloco). */
export function TaskRow({ task, showProject = true, showVisibility = true }: { task: Task; showProject?: boolean; showVisibility?: boolean }) {
  const closed = task.status?.kind === 'done' || task.status?.kind === 'cancelled'

  return (
    <Link
      to={`/w/tasks/${task.id}`}
      className="group grid grid-cols-[auto_1fr] items-center gap-x-3 gap-y-1 rounded-ws-sm px-3 py-2.5 transition-colors hover:bg-ws-hover md:grid-cols-[auto_minmax(0,1fr)_150px_150px_110px]"
    >
      <PriorityMark priority={task.priority} />
      <div className="min-w-0">
        <p className={cn('truncate text-[13.5px] text-ws-ink', closed && 'text-ws-ink-3 line-through')}>
          {task.title}
        </p>
        <div className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[12px] text-ws-ink-3">
          {showProject && task.project && <span className="truncate">{task.project.name}</span>}
          {showVisibility && task.visibility === 'internal' && <VisibilityMark visibility="internal" compact />}
          <TaskSignals task={task} />
          <span className="md:hidden">
            <DueMark due={task.due_at} closed={closed} />
          </span>
        </div>
      </div>
      <div className="col-start-2 md:col-start-auto">
        <StatusTag status={task.status} />
      </div>
      <div className="hidden md:block">
        <PersonTag member={task.assignee} empty="—" />
      </div>
      <div className="hidden text-right md:block">
        <DueMark due={task.due_at} closed={closed} />
      </div>
    </Link>
  )
}

export function TaskTableHead({ label = 'Tarefa' }: { label?: string }) {
  return (
    <div className="mb-1 hidden grid-cols-[auto_minmax(0,1fr)_150px_150px_110px] gap-x-3 rounded-ws-sm bg-ws-sunken px-3 py-1.5 md:grid">
      <span className="ws-eyebrow w-3.5" />
      <span className="ws-eyebrow">{label}</span>
      <span className="ws-eyebrow">Status</span>
      <span className="ws-eyebrow">Responsável</span>
      <span className="ws-eyebrow text-right">Prazo</span>
    </div>
  )
}
