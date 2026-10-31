import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { FolderKanban, Plus, Search } from 'lucide-react'
import { Permission } from '@/shared/constants/permissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { Bar, Button, Empty, ErrorBlock, Initials, LoadingBlock, PageHeader, Pill, SelectInput, TextInput } from '../ui'
import { ProjectFormSheet } from '../components/ProjectForm'
import { useClients, useMembers, useProfile, useProjects } from '../hooks/useWorkspace'
import { PROJECT_STATUSES, shortDate } from '../lib/format'
import type { Project } from '../lib/types'
import { Pager } from './parts/Pager'

const STATUS_TONE = { planning: 'neutral', active: 'accent', on_hold: 'warn', completed: 'ok', cancelled: 'neutral' } as const

function ProjectLine({ project }: { project: Project }) {
  const progress = project.progress

  return (
    <Link
      to={`/w/projects/${project.id}`}
      className="grid grid-cols-1 gap-3 border-b border-ws-line px-4 py-3.5 last:border-b-0 hover:bg-ws-hover md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_180px_110px_90px] md:items-center"
    >
      <div className="min-w-0">
        <div className="flex items-center gap-2">
          <p className="truncate text-[14px] font-medium text-ws-ink">{project.name}</p>
          {project.is_archived && <Pill>Arquivado</Pill>}
        </div>
        <p className="mt-0.5 truncate text-[12.5px] text-ws-ink-3">{project.client?.name}</p>
      </div>
      <div className="flex items-center gap-2">
        <Pill tone={STATUS_TONE[project.status]}>{project.status_label}</Pill>
        {(progress?.overdue ?? 0) > 0 && <Pill tone="danger">{progress?.overdue} atrasada(s)</Pill>}
      </div>
      <div className="flex items-center gap-2">
        <Bar value={progress?.percent ?? 0} className="flex-1" />
        <span className="w-16 text-right font-ws-mono text-[11.5px] text-ws-ink-2 tabular-nums">
          {progress?.done ?? 0}/{(progress?.total ?? 0) - (progress?.cancelled ?? 0)}
        </span>
      </div>
      <div className="flex -space-x-1">
        {(project.members ?? []).slice(0, 4).map((member) => (
          <Initials key={member.id} name={member.name} size="xs" className="bg-ws-panel" />
        ))}
      </div>
      <span className="font-ws-mono text-[12px] text-ws-ink-2 md:text-right">{shortDate(project.due_at)}</span>
    </Link>
  )
}

export default function ProjectsPage() {
  const { isClient, canManage } = useProfile()
  const [params, setParams] = useSearchParams()
  const [creating, setCreating] = useState(false)
  const [search, setSearch] = useState(params.get('search') ?? '')
  const debounced = useDebounce(search, 300)

  const filters = {
    search: debounced || undefined,
    client: params.get('client') || undefined,
    status: params.get('status') || undefined,
    member: params.get('member') || undefined,
    archived: params.get('archived') || 'exclude',
    page: Number(params.get('page') ?? 1),
    per_page: 20,
  }

  const projects = useProjects(filters)
  const clients = useClients({ per_page: 100 }, !isClient)
  const members = useMembers(!isClient)

  const set = (key: string, value: string) => {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    next.delete('page')
    setParams(next, { replace: true })
  }

  return (
    <div className="ws-rise">
      <PageHeader
        eyebrow={isClient ? 'Área do cliente' : 'Portfólio'}
        title="Projetos"
        description={isClient ? 'Projetos em que sua empresa está envolvida.' : 'Acompanhe o andamento de todos os projetos da organização.'}
        actions={
          canManage(Permission.PROJECT_CREATE) && (
            <Button variant="primary" icon={<Plus className="size-3.5" />} onClick={() => setCreating(true)}>
              Novo projeto
            </Button>
          )
        }
      />

      <div className="mb-4 flex flex-col gap-2 md:flex-row md:items-center">
        <div className="relative md:w-72">
          <Search className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-ws-ink-3" aria-hidden="true" />
          <TextInput value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar projeto…" className="pl-8" aria-label="Buscar projeto" />
        </div>
        <div className="grid grid-cols-2 gap-2 md:flex">
          {!isClient && (
            <SelectInput aria-label="Cliente" value={filters.client ?? ''} onChange={(e) => set('client', e.target.value)} className="md:w-44">
              <option value="">Todos os clientes</option>
              {clients.data?.data.map((client) => (
                <option key={client.id} value={client.id}>
                  {client.name}
                </option>
              ))}
            </SelectInput>
          )}
          <SelectInput aria-label="Status" value={filters.status ?? ''} onChange={(e) => set('status', e.target.value)} className="md:w-40">
            <option value="">Todos os status</option>
            {PROJECT_STATUSES.map((status) => (
              <option key={status.value} value={status.value}>
                {status.label}
              </option>
            ))}
          </SelectInput>
          {!isClient && (
            <SelectInput aria-label="Profissional" value={filters.member ?? ''} onChange={(e) => set('member', e.target.value)} className="md:w-44">
              <option value="">Qualquer profissional</option>
              {members.data?.data.map((member) => (
                <option key={member.id} value={member.id}>
                  {member.name}
                </option>
              ))}
            </SelectInput>
          )}
          <SelectInput aria-label="Arquivados" value={filters.archived} onChange={(e) => set('archived', e.target.value)} className="md:w-36">
            <option value="exclude">Ativos</option>
            <option value="only">Arquivados</option>
            <option value="include">Todos</option>
          </SelectInput>
        </div>
      </div>

      {projects.isLoading ? (
        <LoadingBlock rows={6} />
      ) : projects.isError ? (
        <ErrorBlock message="Não foi possível carregar os projetos." onRetry={() => projects.refetch()} />
      ) : projects.data?.data.length ? (
        <>
          <div className="overflow-hidden rounded-ws border border-ws-line bg-ws-panel">
            <div className="hidden grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_180px_110px_90px] gap-3 border-b border-ws-line bg-ws-sunken px-4 py-1.5 md:grid">
              <span className="ws-eyebrow">Projeto</span>
              <span className="ws-eyebrow">Status</span>
              <span className="ws-eyebrow">Conclusão</span>
              <span className="ws-eyebrow">Equipe</span>
              <span className="ws-eyebrow text-right">Entrega</span>
            </div>
            {projects.data.data.map((project) => (
              <ProjectLine key={project.id} project={project} />
            ))}
          </div>
          <Pager meta={projects.data.meta} onPage={(page) => set('page', String(page))} />
        </>
      ) : (
        <Empty
          icon={FolderKanban}
          title="Nenhum projeto encontrado"
          description={isClient ? 'Quando houver projetos para sua empresa, eles aparecem aqui.' : 'Ajuste os filtros ou crie um novo projeto.'}
        />
      )}

      <ProjectFormSheet open={creating} onClose={() => setCreating(false)} defaultClientId={filters.client} />
    </div>
  )
}
