import { useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { Bot, Download, Eye, Lock, Paperclip, Pencil, Plus, Trash2, X } from 'lucide-react'
import { Permission } from '@/shared/constants/permissions'
import { cn } from '@/shared/utils/cn'
import { Bar, Button, Confirm, ErrorBlock, IconButton, Initials, LoadingBlock, Panel, Pill, Segmented, SelectInput, TextArea, TextInput } from '../ui'
import { ActivityFeed } from '../components/ActivityFeed'
import { MemberPicker } from '../components/MemberPicker'
import { TaskFormSheet } from '../components/TaskForm'
import { DueMark, PriorityMark, Tags, VisibilityMark } from '../components/TaskBits'
import { wsKeys } from '../hooks/keys'
import { useBoardConfig, useMembers, useProfile, useTask, useWsMutation } from '../hooks/useWorkspace'
import { dateTime, fileSize, longDate, PRIORITIES, relative } from '../lib/format'
import type { Subtask, Task } from '../lib/types'
import { wsApi } from '../services/workspace.api'

function Meta({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="grid grid-cols-[110px_1fr] items-center gap-3 py-2 text-[13px]">
      <dt className="text-ws-ink-3">{label}</dt>
      <dd className="min-w-0 text-ws-ink">{children}</dd>
    </div>
  )
}

function SubtaskList({ task, editable }: { task: Task; editable: boolean }) {
  const [title, setTitle] = useState('')
  const create = useWsMutation((t: string) => wsApi.tasks.subtasks.create(task.id, { title: t }))
  const update = useWsMutation((args: { id: string; body: Record<string, unknown> }) => wsApi.tasks.subtasks.update(task.id, args.id, args.body))
  const remove = useWsMutation((id: string) => wsApi.tasks.subtasks.remove(task.id, id))
  const subtasks = task.subtasks ?? []
  const done = subtasks.filter((s) => s.status === 'done').length

  const toggle = (subtask: Subtask) => update.mutate({ id: subtask.id, body: { status: subtask.status === 'done' ? 'pending' : 'done' } })

  return (
    <Panel
      eyebrow="Checklist"
      title={`Subtarefas · ${done}/${subtasks.length}`}
      actions={subtasks.length > 0 && <span className="tabular-nums text-[12px] text-ws-ink-2">{Math.round((done / subtasks.length) * 100)}%</span>}
    >
      {subtasks.length > 0 && <Bar value={(done / subtasks.length) * 100} className="mb-3" />}
      <ul className="flex flex-col">
        {subtasks.map((subtask) => (
          <li key={subtask.id} className="group flex items-center gap-2.5 py-2">
            <input
              type="checkbox"
              checked={subtask.status === 'done'}
              disabled={!editable}
              onChange={() => toggle(subtask)}
              aria-label={`Concluir ${subtask.title}`}
              className="size-3.5 accent-[var(--ws-accent)]"
            />
            <span className={cn('flex-1 text-[13px]', subtask.status === 'done' ? 'text-ws-ink-3 line-through' : 'text-ws-ink')}>{subtask.title}</span>
            {subtask.status === 'in_progress' && <Pill tone="info">Em andamento</Pill>}
            {subtask.assignee && <Initials name={subtask.assignee.name} size="xs" />}
            <DueMark due={subtask.due_at} closed={subtask.status === 'done'} />
            {editable && (
              <IconButton label="Remover subtarefa" className="size-6 opacity-0 group-hover:opacity-100 focus:opacity-100" onClick={() => remove.mutate(subtask.id)}>
                <X className="size-3.5" />
              </IconButton>
            )}
          </li>
        ))}
      </ul>
      {subtasks.length === 0 && !editable && <p className="text-[13px] text-ws-ink-3">Sem subtarefas.</p>}
      {editable && (
        <form
          className="mt-2 flex gap-2"
          onSubmit={async (event) => {
            event.preventDefault()
            if (!title.trim()) return
            await create.mutateAsync(title.trim())
            setTitle('')
          }}
        >
          <TextInput value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Adicionar subtarefa…" aria-label="Nova subtarefa" className="h-8" />
          <Button type="submit" size="sm" loading={create.isPending} icon={<Plus className="size-3.5" />}>
            Adicionar
          </Button>
        </form>
      )}
    </Panel>
  )
}

function Comments({ task }: { task: Task }) {
  const { isClient, can } = useProfile()
  const [body, setBody] = useState('')
  const [visibility, setVisibility] = useState<'internal' | 'shared'>(task.visibility === 'shared' ? 'shared' : 'internal')
  const comments = useQuery({ queryKey: wsKeys.taskComments(task.id), queryFn: () => wsApi.tasks.comments(task.id) })
  const create = useWsMutation((payload: { body: string; visibility?: string }) => wsApi.tasks.comment(task.id, payload))

  return (
    <Panel eyebrow="Conversa" title="Comentários">
      <ol className="flex flex-col gap-4">
        {comments.data?.data.map((comment) => (
          <li key={comment.id} className="flex gap-3">
            <Initials name={comment.author?.name} size="sm" />
            <div className="min-w-0 flex-1">
              <p className="flex flex-wrap items-center gap-2 text-[12.5px]">
                <span className="font-medium text-ws-ink">{comment.author?.name ?? 'Usuário removido'}</span>
                {comment.author?.is_client && <Pill tone="info">cliente</Pill>}
                {!isClient && comment.visibility === 'internal' && (
                  <Pill>
                    <Lock className="size-3" /> interno
                  </Pill>
                )}
                {comment.source === 'mcp' && (
                  <Pill tone="accent">
                    <Bot className="size-3" /> via agente
                  </Pill>
                )}
                <span className="tabular-nums text-[11px] text-ws-ink-3" title={dateTime(comment.created_at)}>
                  {relative(comment.created_at)}
                </span>
              </p>
              <p className={cn('mt-1 text-[13.5px] leading-relaxed whitespace-pre-wrap text-ws-ink', comment.visibility === 'internal' && !isClient && 'rounded-ws-sm bg-ws-sunken px-2.5 py-1.5')}>
                {comment.body}
              </p>
            </div>
          </li>
        ))}
        {comments.data?.data.length === 0 && <li className="text-[13px] text-ws-ink-3">Nenhum comentário ainda.</li>}
      </ol>

      {can(Permission.COMMENT_CREATE) && (
        <form
          className="mt-5 rounded-ws bg-ws-sunken p-3"
          onSubmit={async (event) => {
            event.preventDefault()
            if (!body.trim()) return
            await create.mutateAsync({ body: body.trim(), ...(isClient ? {} : { visibility }) })
            setBody('')
          }}
        >
          <TextArea value={body} onChange={(e) => setBody(e.target.value)} rows={3} placeholder={isClient ? 'Escreva uma mensagem para a equipe…' : 'Escreva um comentário…'} aria-label="Comentário" />
          <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
            {!isClient && task.visibility === 'shared' ? (
              <Segmented
                label="Visibilidade do comentário"
                value={visibility}
                onChange={setVisibility}
                options={[
                  { value: 'internal', label: 'Só equipe', icon: <Lock className="size-3" /> },
                  { value: 'shared', label: 'Cliente vê', icon: <Eye className="size-3" /> },
                ]}
              />
            ) : (
              <span className="text-[12px] text-ws-ink-3">{isClient ? 'Visível para a equipe do projeto.' : 'Tarefa interna: comentários ficam só com a equipe.'}</span>
            )}
            <Button type="submit" variant="primary" size="sm" loading={create.isPending}>
              Comentar
            </Button>
          </div>
        </form>
      )}
    </Panel>
  )
}

function Attachments({ task }: { task: Task }) {
  const { isClient, can } = useProfile()
  const input = useRef<HTMLInputElement>(null)
  const attachments = useQuery({ queryKey: wsKeys.taskAttachments(task.id), queryFn: () => wsApi.tasks.attachments(task.id) })
  const upload = useWsMutation((file: File) => wsApi.tasks.upload(task.id, file, task.visibility === 'shared' && !isClient ? 'shared' : undefined), 'Arquivo anexado')
  const remove = useWsMutation((id: string) => wsApi.tasks.removeAttachment(id))

  return (
    <Panel
      eyebrow="Arquivos"
      title="Anexos"
      actions={
        can(Permission.COMMENT_CREATE) && (
          <>
            <input
              ref={input}
              type="file"
              className="hidden"
              onChange={(e) => {
                const file = e.target.files?.[0]
                if (file) upload.mutate(file)
                e.target.value = ''
              }}
            />
            <Button size="sm" variant="ghost" loading={upload.isPending} icon={<Paperclip className="size-3.5" />} onClick={() => input.current?.click()}>
              Anexar
            </Button>
          </>
        )
      }
    >
      {attachments.data?.length ? (
        <ul className="flex flex-col">
          {attachments.data.map((file) => (
            <li key={file.id} className="flex items-center gap-2 py-2 text-[13px]">
              <Paperclip className="size-3.5 text-ws-ink-3" aria-hidden="true" />
              <a href={wsApi.tasks.downloadUrl(file.id)} className="min-w-0 flex-1 truncate text-ws-ink hover:underline">
                {file.name}
              </a>
              {!isClient && file.visibility === 'internal' && <Lock className="size-3 text-ws-ink-3" aria-label="Interno" />}
              <span className="tabular-nums text-[11px] text-ws-ink-3">{fileSize(file.size)}</span>
              <a href={wsApi.tasks.downloadUrl(file.id)} aria-label={`Baixar ${file.name}`} className="text-ws-ink-3 hover:text-ws-ink">
                <Download className="size-3.5" />
              </a>
              {!isClient && (
                <IconButton label="Remover anexo" className="size-6" onClick={() => remove.mutate(file.id)}>
                  <X className="size-3.5" />
                </IconButton>
              )}
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-[13px] text-ws-ink-3">Nenhum arquivo anexado.</p>
      )}
    </Panel>
  )
}

export default function TaskPage() {
  const { id = '' } = useParams()
  const navigate = useNavigate()
  const task = useTask(id)
  const { isClient, canManage } = useProfile()
  const board = useBoardConfig()
  const members = useMembers(canManage(Permission.TASK_ASSIGN))
  const activity = useQuery({ queryKey: wsKeys.taskActivity(id), queryFn: () => wsApi.tasks.activity(id, { per_page: 30 }), enabled: Boolean(task.data) })
  const [editing, setEditing] = useState(false)
  const [deleting, setDeleting] = useState(false)

  const update = useWsMutation((body: Record<string, unknown>) => wsApi.tasks.update(id, body))
  const move = useWsMutation((columnId: string) => wsApi.tasks.move(id, { column_id: columnId }), 'Status atualizado')
  const assign = useWsMutation((body: { assignee_id: string | null; participant_ids?: string[] }) => wsApi.tasks.assign(id, body))
  const remove = useWsMutation(() => wsApi.tasks.remove(id), 'Tarefa excluída')

  if (task.isLoading) return <LoadingBlock rows={8} />
  if (task.isError || !task.data) return <ErrorBlock message="Tarefa não encontrada ou sem acesso." />

  const t = task.data
  const editable = canManage(Permission.TASK_UPDATE)
  const columns = board.data?.columns ?? []

  return (
    <div className="ws-rise">
      <nav className="mb-3 flex flex-wrap items-center gap-1.5 text-[12.5px] text-ws-ink-3" aria-label="Trilha">
        <Link to="/w/projects" className="hover:text-ws-ink">Projetos</Link>/
        {t.project && (
          <Link to={`/w/projects/${t.project.id}/board`} className="hover:text-ws-ink">
            {t.project.name}
          </Link>
        )}
      </nav>

      <div className="mb-6 flex flex-col gap-4 pb-5 md:flex-row md:items-start md:justify-between">
        <div className="min-w-0">
          <div className="mb-2 flex flex-wrap items-center gap-2">
            <PriorityMark priority={t.priority} withLabel />
            {!isClient && <VisibilityMark visibility={t.visibility} />}
            {t.is_overdue && <Pill tone="danger">Atrasada</Pill>}
          </div>
          <h1 className="text-[22px] leading-snug font-semibold tracking-[-0.01em] text-ws-ink md:text-[24px]">{t.title}</h1>
          <p className="mt-1 text-[12.5px] text-ws-ink-3">
            Aberta por {t.creator?.name ?? '—'} · {dateTime(t.created_at)}
          </p>
        </div>
        {editable && (
          <div className="flex shrink-0 gap-2">
            <Button icon={<Pencil className="size-3.5" />} onClick={() => setEditing(true)}>
              Editar
            </Button>
            {canManage(Permission.TASK_DELETE) && (
              <Button variant="danger" icon={<Trash2 className="size-3.5" />} onClick={() => setDeleting(true)}>
                Excluir
              </Button>
            )}
          </div>
        )}
      </div>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div className="flex min-w-0 flex-col gap-6">
          <Panel eyebrow="Descrição" title="Detalhes">
            {t.description ? (
              <p className="text-[14px] leading-relaxed whitespace-pre-wrap text-ws-ink">{t.description}</p>
            ) : (
              <p className="text-[13px] text-ws-ink-3">Sem descrição.</p>
            )}
            {t.tags && t.tags.length > 0 && (
              <div className="mt-4">
                <Tags tags={t.tags} />
              </div>
            )}
          </Panel>
          <SubtaskList task={t} editable={editable} />
          <Comments task={t} />
        </div>

        <aside className="flex flex-col gap-6">
          <Panel eyebrow="Propriedades" title="Andamento" bodyClassName="px-4 py-1">
            <dl className="flex flex-col">
              <Meta label="Status">
                {canManage(Permission.TASK_MOVE) && columns.length > 0 ? (
                  <SelectInput aria-label="Status" value={t.status?.id ?? ''} onChange={(e) => move.mutate(e.target.value)} className="h-8">
                    {columns.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </SelectInput>
                ) : (
                  <span className="inline-flex items-center gap-1.5">
                    <span className="size-2 rounded-full" style={{ background: t.status?.color }} />
                    {t.status?.name}
                  </span>
                )}
              </Meta>
              <Meta label="Prioridade">
                {editable ? (
                  <SelectInput aria-label="Prioridade" value={t.priority} onChange={(e) => update.mutate({ priority: e.target.value })} className="h-8">
                    {PRIORITIES.map((p) => (
                      <option key={p.value} value={p.value}>
                        {p.label}
                      </option>
                    ))}
                  </SelectInput>
                ) : (
                  t.priority_label
                )}
              </Meta>
              <Meta label="Responsável">
                {canManage(Permission.TASK_ASSIGN) ? (
                  <MemberPicker
                    members={members.data?.data ?? []}
                    value={t.assignee ? [t.assignee.id] : []}
                    onChange={(value) => assign.mutate({ assignee_id: value[0] ?? null })}
                    placeholder="Ninguém"
                  />
                ) : (
                  t.assignee?.name ?? '—'
                )}
              </Meta>
              {!isClient && (
                <Meta label="Envolvidos">
                  {canManage(Permission.TASK_ASSIGN) ? (
                    <MemberPicker
                      multiple
                      members={members.data?.data ?? []}
                      value={(t.participants ?? []).map((p) => p.id)}
                      onChange={(value) => assign.mutate({ assignee_id: t.assignee?.id ?? null, participant_ids: value })}
                      placeholder="Ninguém"
                    />
                  ) : (
                    (t.participants ?? []).map((p) => p.name).join(', ') || '—'
                  )}
                </Meta>
              )}
              <Meta label="Prazo">
                {editable ? (
                  <TextInput type="date" aria-label="Prazo" value={t.due_at ?? ''} onChange={(e) => update.mutate({ due_at: e.target.value || null })} className="h-8" />
                ) : (
                  <span className={cn(t.is_overdue && 'text-ws-danger')}>{longDate(t.due_at)}</span>
                )}
              </Meta>
              <Meta label="Início">{longDate(t.starts_at)}</Meta>
              {!isClient && <Meta label="Estimativa">{t.estimated_hours !== null ? `${t.estimated_hours} h` : '—'}</Meta>}
              <Meta label="Cliente">{t.client?.name ?? '—'}</Meta>
              {t.completed_at && <Meta label="Concluída em">{dateTime(t.completed_at)}</Meta>}
              {!isClient && editable && (
                <Meta label="Visibilidade">
                  <Segmented
                    label="Visibilidade"
                    value={t.visibility}
                    onChange={(value) => update.mutate({ visibility: value })}
                    options={[
                      { value: 'internal', label: 'Interna' },
                      { value: 'shared', label: 'Cliente' },
                    ]}
                  />
                </Meta>
              )}
            </dl>
          </Panel>
          <Attachments task={t} />
          <Panel eyebrow="Rastreabilidade" title="Histórico">
            <ActivityFeed items={activity.data?.data ?? []} showTarget={false} />
          </Panel>
        </aside>
      </div>

      <TaskFormSheet open={editing} onClose={() => setEditing(false)} task={t} />
      <Confirm
        open={deleting}
        destructive
        title="Excluir tarefa?"
        description="A tarefa sai do quadro e das listas. O histórico é preservado."
        confirmLabel="Excluir"
        loading={remove.isPending}
        onClose={() => setDeleting(false)}
        onConfirm={async () => {
          await remove.mutateAsync(undefined)
          navigate(t.project ? `/w/projects/${t.project.id}/board` : '/w/tasks')
        }}
      />
    </div>
  )
}
