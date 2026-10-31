import { ArrowUpRight, FolderKanban, Plus } from 'lucide-react'
import { Link } from 'react-router'
import { useState } from 'react'
import { Permission } from '@/shared/constants/permissions'
import { cn } from '@/shared/utils/cn'
import { Button, ButtonLink, Empty, ErrorBlock, LoadingBlock, PageHeader, Panel, Stat } from '../ui'
import { ActivityFeed } from '../components/ActivityFeed'
import { ProjectFormSheet } from '../components/ProjectForm'
import { TaskRow } from '../components/TaskRow'
import { useDashboard, useProfile, useProjects } from '../hooks/useWorkspace'
import type { Dashboard } from '../lib/types'

const KIND_COLORS: Record<string, string> = {
  open: 'var(--ws-ink-2)',
  waiting: 'var(--ws-warn)',
  done: 'var(--ws-ok)',
  cancelled: 'var(--ws-line-strong)',
}

function Distribution({ data }: { data: Dashboard['status_distribution'] }) {
  const total = data.reduce((sum, item) => sum + item.total, 0)

  return (
    <div>
      <div className="flex h-2.5 w-full overflow-hidden rounded-[1px] bg-ws-sunken" role="img" aria-label="Distribuição de tarefas por status">
        {total > 0 &&
          data.map((item) => (
            <div key={item.kind} style={{ width: `${(item.total / total) * 100}%`, background: KIND_COLORS[item.kind] }} />
          ))}
      </div>
      <dl className="mt-4 grid grid-cols-2 gap-x-6 gap-y-3">
        {data.map((item) => (
          <div key={item.kind}>
            <dt className="flex items-center gap-1.5 text-[12px] text-ws-ink-2">
              <span className="size-2 rounded-[1px]" style={{ background: KIND_COLORS[item.kind] }} aria-hidden="true" />
              {item.label}
            </dt>
            <dd className="mt-0.5 font-ws-mono text-[18px] text-ws-ink tabular-nums">{item.total}</dd>
          </div>
        ))}
      </dl>
    </div>
  )
}

function PriorityBars({ data }: { data: Dashboard['priority_distribution'] }) {
  const max = Math.max(1, ...data.map((item) => item.total))

  return (
    <ul className="flex flex-col gap-2.5">
      {data
        .slice()
        .reverse()
        .map((item) => (
          <li key={item.priority} className="grid grid-cols-[72px_1fr_32px] items-center gap-3 text-[12.5px]">
            <span className="text-ws-ink-2">{item.label}</span>
            <span className="h-2 bg-ws-sunken">
              <span
                className={cn('block h-full', item.priority === 'urgent' ? 'bg-ws-danger' : item.priority === 'high' ? 'bg-ws-warn' : 'bg-ws-ink-3')}
                style={{ width: `${(item.total / max) * 100}%` }}
              />
            </span>
            <span className="text-right font-ws-mono text-ws-ink tabular-nums">{item.total}</span>
          </li>
        ))}
    </ul>
  )
}

function greeting(): string {
  const hour = new Date().getHours()
  return hour < 12 ? 'Bom dia' : hour < 18 ? 'Boa tarde' : 'Boa noite'
}

export default function DashboardPage() {
  const { user, isClient, client, canManage } = useProfile()
  const dashboard = useDashboard()
  const projects = useProjects({ per_page: 6, archived: 'exclude' })
  const [creating, setCreating] = useState(false)

  if (dashboard.isLoading) return <LoadingBlock rows={8} />
  if (dashboard.isError || !dashboard.data) return <ErrorBlock message="Não foi possível carregar o painel." onRetry={() => dashboard.refetch()} />

  const data = dashboard.data
  const m = data.metrics
  const firstName = user?.name.split(' ')[0]

  return (
    <div className="ws-rise">
      <PageHeader
        eyebrow={new Intl.DateTimeFormat('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date())}
        title={`${greeting()}, ${firstName}.`}
        description={
          isClient
            ? `Acompanhe o andamento dos projetos de ${client?.name}.`
            : m.overdue_tasks > 0
              ? `${m.overdue_tasks} ${m.overdue_tasks === 1 ? 'tarefa está atrasada' : 'tarefas estão atrasadas'} — vale olhar primeiro.`
              : 'Nenhuma tarefa atrasada. Bom ritmo.'
        }
        actions={
          canManage(Permission.PROJECT_CREATE) && (
            <Button variant="primary" icon={<Plus className="size-3.5" />} onClick={() => setCreating(true)}>
              Novo projeto
            </Button>
          )
        }
      />

      <div className="mb-6 grid grid-cols-2 gap-y-5 rounded-ws border border-ws-line bg-ws-panel px-5 py-5 md:grid-cols-5">
        <Stat label={isClient ? 'Em andamento' : 'Projetos ativos'} value={m.active_projects} />
        <Stat label="Tarefas abertas" value={m.open_tasks} hint={m.my_open_tasks !== null ? `${m.my_open_tasks} com você` : undefined} />
        <Stat label="Atrasadas" value={m.overdue_tasks} tone={m.overdue_tasks > 0 ? 'danger' : undefined} />
        <Stat label={isClient ? 'Aguardando você' : 'Aguardando retorno'} value={m.waiting_tasks} />
        <Stat label="Concluídas" value={m.completed_tasks} hint={`${m.completed_last_7_days} nos últimos 7 dias`} tone="accent" />
      </div>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div className="flex min-w-0 flex-col gap-6">
          <Panel
            eyebrow="Atenção"
            title="Tarefas prioritárias"
            bodyClassName="p-0"
            actions={<ButtonLink to="/w/tasks?priority=urgent,high" variant="ghost" size="sm">Ver todas</ButtonLink>}
          >
            {data.priority_tasks.length === 0 ? (
              <p className="px-4 py-6 text-[13px] text-ws-ink-3">Nenhuma tarefa urgente ou de alta prioridade em aberto.</p>
            ) : (
              data.priority_tasks.map((task) => <TaskRow key={task.id} task={task} showVisibility={!isClient} />)
            )}
          </Panel>

          <Panel eyebrow="Próximos 14 dias" title="Prazos" bodyClassName="p-0">
            {data.upcoming_deadlines.length === 0 ? (
              <p className="px-4 py-6 text-[13px] text-ws-ink-3">Nenhum prazo nas próximas duas semanas.</p>
            ) : (
              data.upcoming_deadlines.map((task) => <TaskRow key={task.id} task={task} showVisibility={!isClient} />)
            )}
          </Panel>

          <Panel
            eyebrow="Portfólio"
            title={isClient ? 'Seus projetos' : 'Projetos em andamento'}
            actions={<ButtonLink to="/w/projects" variant="ghost" size="sm">Todos</ButtonLink>}
          >
            {projects.data?.data.length ? (
              <ul className="grid gap-3 sm:grid-cols-2">
                {projects.data.data.map((project) => (
                  <li key={project.id}>
                    <Link to={`/w/projects/${project.id}`} className="group block rounded-ws border border-ws-line p-3 hover:border-ws-line-strong">
                      <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                          <p className="truncate text-[13.5px] font-medium text-ws-ink">{project.name}</p>
                          <p className="truncate text-[12px] text-ws-ink-3">{project.client?.name}</p>
                        </div>
                        <ArrowUpRight className="size-3.5 text-ws-ink-3 group-hover:text-ws-ink" aria-hidden="true" />
                      </div>
                      <div className="mt-3 flex items-center gap-2">
                        <div className="h-1 flex-1 bg-ws-sunken">
                          <div className="h-full bg-ws-accent" style={{ width: `${project.progress?.percent ?? 0}%` }} />
                        </div>
                        <span className="font-ws-mono text-[11px] text-ws-ink-2 tabular-nums">{project.progress?.percent ?? 0}%</span>
                      </div>
                    </Link>
                  </li>
                ))}
              </ul>
            ) : (
              <Empty icon={FolderKanban} title="Nenhum projeto ativo" />
            )}
          </Panel>
        </div>

        <div className="flex flex-col gap-6">
          <Panel eyebrow="Distribuição" title="Tarefas por status">
            <Distribution data={data.status_distribution} />
          </Panel>
          <Panel eyebrow="Em aberto" title="Por prioridade">
            <PriorityBars data={data.priority_distribution} />
          </Panel>
          <Panel eyebrow="Recente" title={isClient ? 'Atualizações dos projetos' : 'Atividades recentes'}>
            <ActivityFeed items={data.recent_activity} />
          </Panel>
        </div>
      </div>

      <ProjectFormSheet open={creating} onClose={() => setCreating(false)} />
    </div>
  )
}
