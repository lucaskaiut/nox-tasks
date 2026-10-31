import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { ListChecks, Search } from 'lucide-react'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { cn } from '@/shared/utils/cn'
import { Empty, ErrorBlock, LoadingBlock, PageHeader, SelectInput, TextInput } from '../ui'
import { TaskRow, TaskTableHead } from '../components/TaskRow'
import { useClients, useMembers, useProfile, useProjects, useTasks } from '../hooks/useWorkspace'
import { PRIORITIES } from '../lib/format'
import { Pager } from './parts/Pager'

const STATES = [
  { value: 'open', label: 'Em aberto' },
  { value: 'waiting', label: 'Aguardando retorno' },
  { value: 'done', label: 'Concluídas' },
  { value: 'closed', label: 'Encerradas' },
  { value: '', label: 'Todas' },
]

export default function TasksPage() {
  const { isClient } = useProfile()
  const [params, setParams] = useSearchParams()
  const [search, setSearch] = useState(params.get('search') ?? '')
  const debounced = useDebounce(search, 300)
  const state = params.get('state') ?? 'open'

  const filters = {
    search: debounced || undefined,
    project: params.get('project') || undefined,
    client: params.get('client') || undefined,
    assignee: params.get('assignee') || undefined,
    priority: params.get('priority')?.split(',').filter(Boolean),
    state: state || undefined,
    overdue: params.get('overdue') === '1' ? 1 : undefined,
    visibility: params.get('visibility') || undefined,
    sort: params.get('sort') || 'due_at',
    direction: params.get('sort') === 'priority' ? 'desc' : 'asc',
    page: Number(params.get('page') ?? 1),
    per_page: 25,
  }

  const tasks = useTasks(filters)
  const projects = useProjects({ per_page: 100, archived: 'include' })
  const clients = useClients({ per_page: 100 }, !isClient)
  const members = useMembers(!isClient)

  const set = (key: string, value: string) => {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    next.delete('page')
    setParams(next, { replace: true })
  }

  const quick = [
    { label: 'Todas', active: !filters.assignee && !filters.overdue, apply: () => { set('assignee', ''); set('overdue', '') } },
    ...(isClient ? [] : [{ label: 'Comigo', active: filters.assignee === 'me', apply: () => set('assignee', 'me') }]),
    { label: 'Atrasadas', active: Boolean(filters.overdue), apply: () => set('overdue', '1') },
  ]

  return (
    <div className="ws-rise">
      <PageHeader eyebrow={isClient ? 'Área do cliente' : 'Execução'} title="Tarefas" description="Filtre por projeto, cliente, responsável, prioridade e status." />

      <div className="mb-3 flex flex-wrap items-center gap-1">
        {quick.map((item) => (
          <button
            key={item.label}
            type="button"
            onClick={item.apply}
            className={cn(
              'h-7 rounded-full px-3 text-[12.5px] transition-colors',
              item.active ? 'bg-ws-ink text-ws-canvas shadow-ws-sm' : 'bg-ws-panel text-ws-ink-2 shadow-ws-xs hover:text-ws-ink',
            )}
          >
            {item.label}
          </button>
        ))}
      </div>

      <div className="mb-4 grid grid-cols-2 gap-2 md:flex md:flex-wrap">
        <div className="relative col-span-2 md:w-64">
          <Search className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-ws-ink-3" aria-hidden="true" />
          <TextInput value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar em títulos e descrições…" className="pl-8" aria-label="Buscar tarefas" />
        </div>
        <SelectInput aria-label="Status" value={state} onChange={(e) => set('state', e.target.value)} className="md:w-40">
          {STATES.map((s) => (
            <option key={s.value} value={s.value}>
              {s.label}
            </option>
          ))}
        </SelectInput>
        <SelectInput aria-label="Projeto" value={filters.project ?? ''} onChange={(e) => set('project', e.target.value)} className="md:w-48">
          <option value="">Todos os projetos</option>
          {projects.data?.data.map((p) => (
            <option key={p.id} value={p.id}>
              {p.name}
            </option>
          ))}
        </SelectInput>
        {!isClient && (
          <>
            <SelectInput aria-label="Cliente" value={filters.client ?? ''} onChange={(e) => set('client', e.target.value)} className="md:w-44">
              <option value="">Todos os clientes</option>
              {clients.data?.data.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </SelectInput>
            <SelectInput aria-label="Responsável" value={filters.assignee ?? ''} onChange={(e) => set('assignee', e.target.value)} className="md:w-44">
              <option value="">Qualquer responsável</option>
              <option value="me">Eu</option>
              <option value="none">Sem responsável</option>
              {members.data?.data.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.name}
                </option>
              ))}
            </SelectInput>
          </>
        )}
        <SelectInput aria-label="Prioridade" value={params.get('priority') ?? ''} onChange={(e) => set('priority', e.target.value)} className="md:w-36">
          <option value="">Prioridade</option>
          <option value="urgent,high">Urgente + alta</option>
          {PRIORITIES.map((p) => (
            <option key={p.value} value={p.value}>
              {p.label}
            </option>
          ))}
        </SelectInput>
        <SelectInput aria-label="Ordenar" value={filters.sort} onChange={(e) => set('sort', e.target.value)} className="md:w-40">
          <option value="due_at">Ordenar: prazo</option>
          <option value="priority">Ordenar: prioridade</option>
          <option value="updated_at">Ordenar: atualização</option>
          <option value="created_at">Ordenar: criação</option>
        </SelectInput>
      </div>

      {tasks.isLoading ? (
        <LoadingBlock rows={8} />
      ) : tasks.isError ? (
        <ErrorBlock message="Não foi possível carregar as tarefas." onRetry={() => tasks.refetch()} />
      ) : tasks.data?.data.length ? (
        <>
          <div className="rounded-ws bg-ws-panel p-1.5 shadow-ws">
            <TaskTableHead />
            {tasks.data.data.map((task) => (
              <TaskRow key={task.id} task={task} showVisibility={!isClient} />
            ))}
          </div>
          <Pager meta={tasks.data.meta} onPage={(page) => set('page', String(page))} />
        </>
      ) : (
        <Empty icon={ListChecks} title="Nenhuma tarefa encontrada" description="Ajuste os filtros para ver outras tarefas." />
      )}
    </div>
  )
}
