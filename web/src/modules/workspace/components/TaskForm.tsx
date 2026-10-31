import { useEffect } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Eye, Lock } from 'lucide-react'
import { Permission } from '@/shared/constants/permissions'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { ApiError } from '@/shared/api/errors'
import { Button, Field, Segmented, SelectInput, Sheet, TextArea, TextInput } from '../ui'
import { PRIORITIES } from '../lib/format'
import type { BoardColumn, Task } from '../lib/types'
import { useMembers, useProfile, useWsMutation } from '../hooks/useWorkspace'
import { wsApi } from '../services/workspace.api'
import { MemberPicker } from './MemberPicker'
import { nullIfEmpty, taskSchema, type TaskFormValues } from './schemas'

/**
 * Criação/edição de tarefa. Para clientes, só os campos permitidos aparecem
 * (o backend força visibilidade compartilhada e ignora atribuições).
 */
export function TaskFormSheet({
  open,
  onClose,
  projectId,
  task,
  columns,
  defaultColumnId,
}: {
  open: boolean
  onClose: () => void
  projectId?: string
  task?: Task
  columns?: BoardColumn[]
  defaultColumnId?: string
}) {
  const { isClient, canManage } = useProfile()
  const canAssign = canManage(Permission.TASK_ASSIGN)
  const members = useMembers(open && canAssign)

  const form = useForm<TaskFormValues>({
    resolver: zodResolver(taskSchema),
    defaultValues: empty(),
  })

  useEffect(() => {
    if (!open) return
    form.reset(
      task
        ? {
            title: task.title,
            description: task.description ?? '',
            priority: task.priority,
            visibility: task.visibility,
            starts_at: task.starts_at ?? '',
            due_at: task.due_at ?? '',
            estimated_hours: task.estimated_hours?.toString() ?? '',
            tags: (task.tags ?? []).map((t) => t.name).join(', '),
            column_id: task.status?.id ?? '',
            assignee: task.assignee ? [task.assignee.id] : [],
            participants: (task.participants ?? []).map((p) => p.id),
          }
        : { ...empty(), column_id: defaultColumnId ?? '' },
    )
  }, [open, task, defaultColumnId, form])

  const create = useWsMutation((body: Record<string, unknown>) => wsApi.tasks.create(projectId!, body), 'Tarefa criada')
  const update = useWsMutation((body: Record<string, unknown>) => wsApi.tasks.update(task!.id, body), 'Tarefa atualizada')

  const submit = form.handleSubmit(async (values) => {
    const tags = values.tags.split(',').map((t) => t.trim()).filter(Boolean)
    const common = {
      title: values.title,
      description: nullIfEmpty(values.description),
      priority: values.priority,
      due_at: nullIfEmpty(values.due_at),
    }
    const body = isClient
      ? common
      : {
          ...common,
          visibility: values.visibility,
          starts_at: nullIfEmpty(values.starts_at),
          estimated_hours: values.estimated_hours === '' ? null : Number(values.estimated_hours),
          tags,
        }

    try {
      if (task) {
        await update.mutateAsync(body)
      } else {
        await create.mutateAsync({
          ...body,
          ...(values.column_id ? { column_id: values.column_id } : {}),
          ...(canAssign && values.assignee[0] ? { assignee_id: values.assignee[0] } : {}),
          ...(canAssign && values.participants.length ? { participant_ids: values.participants } : {}),
        })
      }
      onClose()
    } catch (error) {
      applyApiErrorsToForm(form, error as ApiError)
    }
  })

  const errors = form.formState.errors
  const busy = create.isPending || update.isPending

  return (
    <Sheet
      open={open}
      onClose={onClose}
      eyebrow={task ? 'Editar tarefa' : 'Nova tarefa'}
      title={task ? task.title : isClient ? 'Abrir uma solicitação' : 'Criar tarefa'}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button variant="primary" loading={busy} onClick={submit}>
            {task ? 'Salvar alterações' : 'Criar tarefa'}
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="flex flex-col gap-4">
        <Field label="Título" htmlFor="t-title" error={errors.title?.message}>
          <TextInput id="t-title" autoFocus {...form.register('title')} aria-invalid={!!errors.title} />
        </Field>
        <Field label="Descrição" htmlFor="t-desc" error={errors.description?.message}>
          <TextArea id="t-desc" rows={6} {...form.register('description')} placeholder="Contexto, critérios de aceite, links…" />
        </Field>

        {!isClient && (
          <Field label="Visibilidade" hint="Tarefas internas nunca aparecem para o cliente.">
            <Controller
              control={form.control}
              name="visibility"
              render={({ field }) => (
                <Segmented
                  label="Visibilidade"
                  value={field.value}
                  onChange={field.onChange}
                  options={[
                    { value: 'internal', label: 'Interna', icon: <Lock className="size-3" /> },
                    { value: 'shared', label: 'Compartilhada', icon: <Eye className="size-3" /> },
                  ]}
                />
              )}
            />
          </Field>
        )}

        <div className="grid grid-cols-2 gap-3">
          <Field label="Prioridade" htmlFor="t-priority">
            <SelectInput id="t-priority" {...form.register('priority')}>
              {PRIORITIES.map((p) => (
                <option key={p.value} value={p.value}>
                  {p.label}
                </option>
              ))}
            </SelectInput>
          </Field>
          <Field label="Prazo" htmlFor="t-due" error={errors.due_at?.message}>
            <TextInput id="t-due" type="date" {...form.register('due_at')} />
          </Field>
        </div>

        {!isClient && (
          <>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Início" htmlFor="t-start">
                <TextInput id="t-start" type="date" {...form.register('starts_at')} />
              </Field>
              <Field label="Estimativa (horas)" htmlFor="t-est" error={errors.estimated_hours?.message}>
                <TextInput id="t-est" inputMode="decimal" {...form.register('estimated_hours')} placeholder="ex.: 4" />
              </Field>
            </div>

            {!task && columns && columns.length > 0 && (
              <Field label="Coluna inicial" htmlFor="t-col">
                <SelectInput id="t-col" {...form.register('column_id')}>
                  <option value="">Padrão (primeira em aberto)</option>
                  {columns.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </SelectInput>
              </Field>
            )}

            {!task && canAssign && (
              <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Responsável">
                  <Controller
                    control={form.control}
                    name="assignee"
                    render={({ field }) => (
                      <MemberPicker members={members.data?.data ?? []} value={field.value} onChange={field.onChange} placeholder="Ninguém" />
                    )}
                  />
                </Field>
                <Field label="Envolvidos">
                  <Controller
                    control={form.control}
                    name="participants"
                    render={({ field }) => (
                      <MemberPicker multiple members={members.data?.data ?? []} value={field.value} onChange={field.onChange} placeholder="Ninguém" />
                    )}
                  />
                </Field>
              </div>
            )}

            <Field label="Tags" htmlFor="t-tags" hint="Separe por vírgula. Ex.: frontend, revisão">
              <TextInput id="t-tags" {...form.register('tags')} />
            </Field>
          </>
        )}
        <button type="submit" className="hidden" />
      </form>
    </Sheet>
  )
}

function empty(): TaskFormValues {
  return {
    title: '',
    description: '',
    priority: 'normal',
    visibility: 'internal',
    starts_at: '',
    due_at: '',
    estimated_hours: '',
    tags: '',
    column_id: '',
    assignee: [],
    participants: [],
  }
}
