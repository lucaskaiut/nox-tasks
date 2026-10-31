import { useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router'
import { Building2, Mail, Pencil, Phone, Plus, Search, UserPlus, X } from 'lucide-react'
import { Permission } from '@/shared/constants/permissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { formatDocument } from '@/shared/utils/document'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { ApiError } from '@/shared/api/errors'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Confirm, Empty, ErrorBlock, Field, IconButton, Initials, LoadingBlock, PageHeader, Panel, Pill, SelectInput, Sheet, TextInput } from '../ui'
import { ClientFormSheet } from '../components/ClientForm'
import { ProjectFormSheet } from '../components/ProjectForm'
import { clientUserSchema, type ClientUserFormValues } from '../components/schemas'
import { useClient, useClients, useProfile, useProjects, useWsMutation } from '../hooks/useWorkspace'
import { shortDate } from '../lib/format'
import type { Member } from '../lib/types'
import { wsApi } from '../services/workspace.api'
import { Pager } from './parts/Pager'

export default function ClientsPage() {
  const { canManage } = useProfile()
  const [params, setParams] = useSearchParams()
  const [search, setSearch] = useState('')
  const debounced = useDebounce(search, 300)
  const [creating, setCreating] = useState(false)
  const status = params.get('status') ?? ''
  const clients = useClients({ search: debounced || undefined, status: status || undefined, page: Number(params.get('page') ?? 1), per_page: 20 })

  return (
    <div className="ws-rise">
      <PageHeader
        eyebrow="Relacionamento"
        title="Clientes"
        description="Empresas e pessoas atendidas, seus projetos e acessos à área do cliente."
        actions={
          canManage(Permission.CLIENT_CREATE) && (
            <Button variant="primary" icon={<Plus className="size-3.5" />} onClick={() => setCreating(true)}>
              Novo cliente
            </Button>
          )
        }
      />
      <div className="mb-4 flex flex-col gap-2 sm:flex-row">
        <div className="relative sm:w-72">
          <Search className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-ws-ink-3" aria-hidden="true" />
          <TextInput value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Nome, e-mail ou documento…" className="pl-8" aria-label="Buscar cliente" />
        </div>
        <SelectInput aria-label="Status" value={status} onChange={(e) => setParams(e.target.value ? { status: e.target.value } : {})} className="sm:w-40">
          <option value="">Todos</option>
          <option value="active">Ativos</option>
          <option value="inactive">Inativos</option>
        </SelectInput>
      </div>

      {clients.isLoading ? (
        <LoadingBlock />
      ) : clients.isError ? (
        <ErrorBlock message="Não foi possível carregar os clientes." />
      ) : clients.data?.data.length ? (
        <>
          <div className="overflow-hidden rounded-ws border border-ws-line bg-ws-panel">
            {clients.data.data.map((client) => (
              <Link
                key={client.id}
                to={`/w/clients/${client.id}`}
                className="grid grid-cols-[auto_1fr_auto] items-center gap-3 border-b border-ws-line px-4 py-3 last:border-b-0 hover:bg-ws-hover md:grid-cols-[auto_minmax(0,1fr)_minmax(0,1fr)_90px_90px_80px]"
              >
                <Initials name={client.name} size="md" />
                <div className="min-w-0">
                  <p className="truncate text-[13.5px] font-medium text-ws-ink">{client.name}</p>
                  <p className="truncate text-[12px] text-ws-ink-3 md:hidden">{client.email}</p>
                </div>
                <p className="hidden truncate text-[12.5px] text-ws-ink-2 md:block">{client.email}</p>
                <span className="hidden font-ws-mono text-[12px] text-ws-ink-2 md:block">{client.projects_count} proj.</span>
                <span className="hidden font-ws-mono text-[12px] text-ws-ink-2 md:block">{client.users_count} acesso(s)</span>
                <Pill tone={client.status === 'active' ? 'ok' : 'neutral'}>{client.status_label}</Pill>
              </Link>
            ))}
          </div>
          <Pager meta={clients.data.meta} onPage={(page) => setParams({ ...(status ? { status } : {}), page: String(page) })} />
        </>
      ) : (
        <Empty icon={Building2} title="Nenhum cliente encontrado" />
      )}

      <ClientFormSheet open={creating} onClose={() => setCreating(false)} />
    </div>
  )
}

function AddAccessSheet({ clientId, open, onClose }: { clientId: string; open: boolean; onClose: () => void }) {
  const form = useForm<ClientUserFormValues>({ resolver: zodResolver(clientUserSchema), defaultValues: { name: '', email: '', password: '' } })
  const create = useWsMutation((body: ClientUserFormValues) => wsApi.clients.addUser(clientId, body), 'Acesso criado')
  const submit = form.handleSubmit(async (values) => {
    try {
      await create.mutateAsync(values)
      form.reset()
      onClose()
    } catch (error) {
      applyApiErrorsToForm(form, error as ApiError)
    }
  })
  const errors = form.formState.errors

  return (
    <Sheet
      open={open}
      onClose={onClose}
      eyebrow="Área do cliente"
      title="Novo acesso"
      footer={
        <Button variant="primary" loading={create.isPending} onClick={submit}>
          Criar acesso
        </Button>
      }
    >
      <form onSubmit={submit} className="flex flex-col gap-4">
        <p className="text-[12.5px] text-ws-ink-2">
          A pessoa poderá entrar na plataforma e ver apenas os projetos deste cliente, as tarefas compartilhadas e os comentários marcados como visíveis para o cliente.
        </p>
        <Field label="Nome" htmlFor="u-name" error={errors.name?.message}>
          <TextInput id="u-name" {...form.register('name')} />
        </Field>
        <Field label="E-mail" htmlFor="u-email" error={errors.email?.message}>
          <TextInput id="u-email" type="email" {...form.register('email')} />
        </Field>
        <Field label="Senha inicial" htmlFor="u-pass" error={errors.password?.message}>
          <TextInput id="u-pass" type="password" autoComplete="new-password" {...form.register('password')} />
        </Field>
        <button type="submit" className="hidden" />
      </form>
    </Sheet>
  )
}

export function ClientPage() {
  const { id = '' } = useParams()
  const { canManage } = useProfile()
  const client = useClient(id)
  const projects = useProjects({ client: id, archived: 'include', per_page: 50 })
  const [editing, setEditing] = useState(false)
  const [adding, setAdding] = useState(false)
  const [newProject, setNewProject] = useState(false)
  const [removing, setRemoving] = useState<Member | null>(null)
  const removeUser = useWsMutation((userId: string) => wsApi.clients.removeUser(id, userId), 'Acesso removido')

  if (client.isLoading) return <LoadingBlock />
  if (client.isError || !client.data) return <ErrorBlock message="Cliente não encontrado." />
  const c = client.data

  return (
    <div className="ws-rise">
      <PageHeader
        eyebrow="Cliente"
        title={c.name}
        meta={
          <>
            <Pill tone={c.status === 'active' ? 'ok' : 'neutral'}>{c.status_label}</Pill>
            <span className="inline-flex items-center gap-1.5">
              <Mail className="size-3.5" /> {c.email}
            </span>
            {c.phone && (
              <span className="inline-flex items-center gap-1.5">
                <Phone className="size-3.5" /> {c.phone}
              </span>
            )}
            {c.document && <span className="font-ws-mono">{formatDocument(c.document)}</span>}
          </>
        }
        actions={
          <>
            {canManage(Permission.PROJECT_CREATE) && (
              <Button variant="primary" icon={<Plus className="size-3.5" />} onClick={() => setNewProject(true)}>
                Novo projeto
              </Button>
            )}
            {canManage(Permission.CLIENT_UPDATE) && (
              <Button icon={<Pencil className="size-3.5" />} onClick={() => setEditing(true)}>
                Editar
              </Button>
            )}
          </>
        }
      />

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <Panel eyebrow="Portfólio" title="Projetos" bodyClassName="p-0">
          {projects.data?.data.length ? (
            projects.data.data.map((project) => (
              <Link key={project.id} to={`/w/projects/${project.id}`} className="flex items-center gap-3 border-b border-ws-line px-4 py-3 last:border-b-0 hover:bg-ws-hover">
                <div className="min-w-0 flex-1">
                  <p className="truncate text-[13.5px] text-ws-ink">{project.name}</p>
                  <p className="text-[12px] text-ws-ink-3">
                    {project.status_label} · entrega {shortDate(project.due_at)}
                  </p>
                </div>
                {project.is_archived && <Pill>Arquivado</Pill>}
                <span className="font-ws-mono text-[12px] text-ws-ink-2">{project.progress?.percent ?? 0}%</span>
              </Link>
            ))
          ) : (
            <p className="px-4 py-6 text-[13px] text-ws-ink-3">Nenhum projeto para este cliente.</p>
          )}
        </Panel>

        <div className="flex flex-col gap-6">
          <Panel
            eyebrow="Área do cliente"
            title="Acessos"
            actions={
              canManage(Permission.CLIENT_MANAGE_USERS) && (
                <Button size="sm" variant="ghost" icon={<UserPlus className="size-3.5" />} onClick={() => setAdding(true)}>
                  Adicionar
                </Button>
              )
            }
          >
            {c.users?.length ? (
              <ul className="flex flex-col gap-2.5">
                {c.users.map((user) => (
                  <li key={user.id} className="flex items-center gap-2.5">
                    <Initials name={user.name} size="sm" />
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-[13px] text-ws-ink">{user.name}</p>
                      <p className="truncate text-[12px] text-ws-ink-3">{user.email}</p>
                    </div>
                    {canManage(Permission.CLIENT_MANAGE_USERS) && (
                      <IconButton label={`Remover acesso de ${user.name}`} onClick={() => setRemoving(user)}>
                        <X className="size-3.5" />
                      </IconButton>
                    )}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-[13px] text-ws-ink-3">Nenhum acesso criado.</p>
            )}
          </Panel>
          {c.notes && (
            <Panel eyebrow="Somente equipe" title="Observações">
              <p className="text-[13px] whitespace-pre-wrap text-ws-ink-2">{c.notes}</p>
            </Panel>
          )}
        </div>
      </div>

      <ClientFormSheet open={editing} onClose={() => setEditing(false)} client={c} />
      <AddAccessSheet clientId={c.id} open={adding} onClose={() => setAdding(false)} />
      <ProjectFormSheet open={newProject} onClose={() => setNewProject(false)} defaultClientId={c.id} />
      <Confirm
        open={Boolean(removing)}
        destructive
        title={`Remover o acesso de ${removing?.name}?`}
        description="O usuário é desativado e perde o acesso imediatamente."
        confirmLabel="Remover acesso"
        loading={removeUser.isPending}
        onClose={() => setRemoving(null)}
        onConfirm={async () => {
          await removeUser.mutateAsync(removing!.id)
          setRemoving(null)
        }}
      />
    </div>
  )
}
