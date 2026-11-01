import { useState } from 'react'
import { Outlet, useOutletContext, useParams } from 'react-router'
import { Archive, ArchiveRestore, CalendarRange, Pencil, Plus } from 'lucide-react'
import { Permission } from '@/shared/constants/permissions'
import { Bar, Button, Confirm, ErrorBlock, Initials, LoadingBlock, PageHeader, Panel, Pill, TabLinks } from '../ui'
import { ActivityFeed } from '../components/ActivityFeed'
import { KanbanBoard } from '../components/KanbanBoard'
import { ProjectFormSheet } from '../components/ProjectForm'
import { TaskFormSheet } from '../components/TaskForm'
import { TaskRow, TaskTableHead } from '../components/TaskRow'
import { useBoardConfig, useProfile, useProject, useProjectBoard, useWsMutation } from '../hooks/useWorkspace'
import { longDate } from '../lib/format'
import type { Project } from '../lib/types'
import { wsApi } from '../services/workspace.api'
import { useQuery } from '@tanstack/react-query'
import { wsKeys } from '../hooks/keys'

type Ctx = { project: Project; newTask: (columnId?: string) => void }

export default function ProjectPage() {
  const { id = '' } = useParams()
  const project = useProject(id)
  const boardConfig = useBoardConfig()
  const { canManage, can } = useProfile()
  const [editing, setEditing] = useState(false)
  const [archiving, setArchiving] = useState(false)
  const [taskForm, setTaskForm] = useState<{ open: boolean; columnId?: string }>({ open: false })
  const archive = useWsMutation(() => (project.data?.is_archived ? wsApi.projects.restore(id) : wsApi.projects.archive(id)))

  if (project.isLoading) return <LoadingBlock rows={8} />
  if (project.isError || !project.data) return <ErrorBlock message="Projeto não encontrado ou sem acesso." />

  const p = project.data
  const canCreateTask = can(Permission.TASK_CREATE) && !p.is_archived

  return (
    <div className="ws-rise">
      <PageHeader
        eyebrow={p.client?.name}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {p.name}
            {p.is_archived && <Pill>Arquivado</Pill>}
          </span>
        }
        description={p.description}
        meta={
          <>
            <Pill tone={p.status === 'active' ? 'accent' : 'neutral'}>{p.status_label}</Pill>
            <span className="inline-flex items-center gap-1.5">
              <CalendarRange className="size-3.5" aria-hidden="true" />
              {longDate(p.starts_at)} → {longDate(p.due_at)}
            </span>
            {p.progress && (
              <span className="inline-flex items-center gap-2">
                <Bar value={p.progress.percent} className="w-24" />
                <span className="tabular-nums">{p.progress.percent}%</span>
              </span>
            )}
          </>
        }
        actions={
          <>
            {canCreateTask && (
              <Button variant="primary" icon={<Plus className="size-3.5" />} onClick={() => setTaskForm({ open: true })}>
                Nova tarefa
              </Button>
            )}
            {canManage(Permission.PROJECT_UPDATE) && (
              <Button icon={<Pencil className="size-3.5" />} onClick={() => setEditing(true)}>
                Editar
              </Button>
            )}
            {canManage(Permission.PROJECT_ARCHIVE) && (
              <Button
                variant="ghost"
                icon={p.is_archived ? <ArchiveRestore className="size-3.5" /> : <Archive className="size-3.5" />}
                onClick={() => setArchiving(true)}
              >
                {p.is_archived ? 'Reativar' : 'Arquivar'}
              </Button>
            )}
          </>
        }
      />

      <div className="mb-5">
        <TabLinks
          tabs={[
            { to: `/w/projects/${id}`, label: 'Visão geral', end: true },
            { to: `/w/projects/${id}/board`, label: 'Quadro' },
            { to: `/w/projects/${id}/tasks`, label: 'Tarefas', count: p.progress?.total },
            { to: `/w/projects/${id}/activity`, label: 'Histórico' },
          ]}
        />
      </div>

      <Outlet context={{ project: p, newTask: (columnId?: string) => setTaskForm({ open: true, columnId }) } satisfies Ctx} />

      <ProjectFormSheet open={editing} onClose={() => setEditing(false)} project={p} />
      <TaskFormSheet
        open={taskForm.open}
        onClose={() => setTaskForm({ open: false })}
        projectId={p.id}
        columns={boardConfig.data?.columns}
        defaultColumnId={taskForm.columnId}
      />
      <Confirm
        open={archiving}
        title={p.is_archived ? 'Reativar projeto?' : 'Arquivar projeto?'}
        description={p.is_archived ? 'O projeto volta a aceitar novas tarefas.' : 'O projeto sai das listas padrão e deixa de aceitar novas tarefas. Nada é excluído.'}
        confirmLabel={p.is_archived ? 'Reativar' : 'Arquivar'}
        loading={archive.isPending}
        onClose={() => setArchiving(false)}
        onConfirm={async () => {
          await archive.mutateAsync(undefined)
          setArchiving(false)
        }}
      />
    </div>
  )
}

export function ProjectOverview() {
  const { project } = useOutletContext<Ctx>()
  const { isClient } = useProfile()
  const progress = project.progress
  const tasks = useQuery({
    queryKey: wsKeys.tasks({ project: project.id, state: 'open', sort: 'due_at', per_page: 8 }),
    queryFn: () => wsApi.tasks.list({ project: project.id, state: 'open', sort: 'due_at', per_page: 8 }),
  })

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
      <Panel eyebrow="Próximas entregas" title="Tarefas em aberto" bodyClassName="p-1.5 pt-0">
        <TaskTableHead />
        {tasks.data?.data.length ? (
          tasks.data.data.map((task) => <TaskRow key={task.id} task={task} showProject={false} showVisibility={!isClient} />)
        ) : (
          <p className="px-4 py-6 text-[13px] text-ws-ink-3">Nenhuma tarefa em aberto.</p>
        )}
      </Panel>
      <div className="flex flex-col gap-6">
        <Panel eyebrow="Indicadores" title="Conclusão">
          <p className="tabular-nums text-[36px] leading-none text-ws-ink">{progress?.percent ?? 0}%</p>
          <Bar value={progress?.percent ?? 0} className="mt-3" />
          <dl className="mt-4 grid grid-cols-2 gap-3 text-[12.5px]">
            <div>
              <dt className="text-ws-ink-3">Concluídas</dt>
              <dd className="tabular-nums text-ws-ink">{progress?.done ?? 0}</dd>
            </div>
            <div>
              <dt className="text-ws-ink-3">Em aberto</dt>
              <dd className="tabular-nums text-ws-ink">{progress?.open ?? 0}</dd>
            </div>
            <div>
              <dt className="text-ws-ink-3">Atrasadas</dt>
              <dd className="tabular-nums text-ws-danger">{progress?.overdue ?? 0}</dd>
            </div>
            <div>
              <dt className="text-ws-ink-3">Canceladas</dt>
              <dd className="tabular-nums text-ws-ink">{progress?.cancelled ?? 0}</dd>
            </div>
          </dl>
        </Panel>
        <Panel eyebrow="Pessoas" title="Profissionais responsáveis">
          {project.members?.length ? (
            <ul className="flex flex-col gap-2">
              {project.members.map((member) => (
                <li key={member.id} className="flex items-center gap-2 text-[13px]">
                  <Initials name={member.name} size="sm" />
                  <span className="text-ws-ink">{member.name}</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-[13px] text-ws-ink-3">Ninguém definido.</p>
          )}
        </Panel>
        {!isClient && project.internal_notes && (
          <Panel eyebrow="Somente equipe" title="Observações internas">
            <p className="text-[13px] whitespace-pre-wrap text-ws-ink-2">{project.internal_notes}</p>
          </Panel>
        )}
      </div>
    </div>
  )
}

export function ProjectBoard() {
  const { project, newTask } = useOutletContext<Ctx>()
  const { canManage, can } = useProfile()
  const board = useProjectBoard(project.id)
  const move = useWsMutation((args: { id: string; column: string; position: number; after: string | null }) =>
    wsApi.tasks.move(args.id, args.after ? { column_id: args.column, after_id: args.after } : { column_id: args.column, position: 0 }),
  )

  if (board.isLoading) return <LoadingBlock rows={4} />
  if (board.isError || !board.data) return <ErrorBlock message="Não foi possível carregar o quadro." onRetry={() => board.refetch()} />

  const canMove = canManage(Permission.TASK_MOVE) && !project.is_archived

  return (
    <div>
      {!canMove && (
        <p className="mb-3 text-[12.5px] text-ws-ink-3">Visualização somente leitura — a equipe atualiza o andamento das tarefas.</p>
      )}
      <KanbanBoard
        columns={board.data.board.columns ?? []}
        tasks={board.data.tasks}
        canMove={canMove}
        onMove={(id, column, position, after) => move.mutateAsync({ id, column, position, after })}
        onAdd={can(Permission.TASK_CREATE) && !project.is_archived ? (columnId) => newTask(canManage(Permission.TASK_UPDATE) ? columnId : undefined) : undefined}
      />
    </div>
  )
}

export function ProjectTasks() {
  const { project } = useOutletContext<Ctx>()
  const { isClient } = useProfile()
  const tasks = useQuery({
    queryKey: wsKeys.tasks({ project: project.id, per_page: 100, sort: 'due_at' }),
    queryFn: () => wsApi.tasks.list({ project: project.id, per_page: 100, sort: 'due_at' }),
  })

  if (tasks.isLoading) return <LoadingBlock />

  return (
    <div className="rounded-ws bg-ws-panel p-1.5 shadow-ws">
      <TaskTableHead />
      {tasks.data?.data.length ? (
        tasks.data.data.map((task) => <TaskRow key={task.id} task={task} showProject={false} showVisibility={!isClient} />)
      ) : (
        <p className="px-4 py-6 text-[13px] text-ws-ink-3">Nenhuma tarefa neste projeto.</p>
      )}
    </div>
  )
}

export function ProjectActivity() {
  const { project } = useOutletContext<Ctx>()
  const activity = useQuery({
    queryKey: wsKeys.projectActivity(project.id),
    queryFn: () => wsApi.projects.activity(project.id, { per_page: 50 }),
  })

  if (activity.isLoading) return <LoadingBlock />

  return (
    <Panel eyebrow="Histórico" title="Atividades do projeto">
      <ActivityFeed items={activity.data?.data ?? []} />
    </Panel>
  )
}
