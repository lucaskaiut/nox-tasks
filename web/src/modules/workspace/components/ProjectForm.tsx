import { useEffect } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate } from 'react-router'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { ApiError } from '@/shared/api/errors'
import { Button, Field, SelectInput, Sheet, TextArea, TextInput } from '../ui'
import { PROJECT_STATUSES } from '../lib/format'
import type { Project } from '../lib/types'
import { useBoards, useClients, useMembers, useWsMutation } from '../hooks/useWorkspace'
import { wsApi } from '../services/workspace.api'
import { MemberPicker } from './MemberPicker'
import { nullIfEmpty, projectSchema, type ProjectFormValues } from './schemas'

export function ProjectFormSheet({
  open,
  onClose,
  project,
  defaultClientId,
}: {
  open: boolean
  onClose: () => void
  project?: Project
  defaultClientId?: string
}) {
  const navigate = useNavigate()
  const clients = useClients({ status: 'active', per_page: 100 }, open)
  const boards = useBoards(open)
  const members = useMembers(open)
  const hasTasks = (project?.progress?.total ?? 0) > 0

  const form = useForm<ProjectFormValues>({ resolver: zodResolver(projectSchema), defaultValues: blank(defaultClientId) })

  useEffect(() => {
    if (!open) return
    form.reset(
      project
        ? {
            name: project.name,
            description: project.description ?? '',
            client_id: project.client?.id ?? '',
            board_id: project.board?.id ?? '',
            status: project.status,
            starts_at: project.starts_at ?? '',
            due_at: project.due_at ?? '',
            internal_notes: project.internal_notes ?? '',
            member_ids: (project.members ?? []).map((m) => m.id),
          }
        : blank(defaultClientId),
    )
  }, [open, project, defaultClientId, form])

  const create = useWsMutation((body: Record<string, unknown>) => wsApi.projects.create(body), 'Projeto criado')
  const update = useWsMutation((body: Record<string, unknown>) => wsApi.projects.update(project!.id, body), 'Projeto atualizado')

  const submit = form.handleSubmit(async (values) => {
    const body: Record<string, unknown> = {
      name: values.name,
      description: nullIfEmpty(values.description),
      status: values.status,
      starts_at: nullIfEmpty(values.starts_at),
      due_at: nullIfEmpty(values.due_at),
      internal_notes: nullIfEmpty(values.internal_notes),
      member_ids: values.member_ids,
    }
    if (!project || !hasTasks) body.client_id = values.client_id
    if (values.board_id) body.board_id = values.board_id

    try {
      if (project) {
        await update.mutateAsync(body)
        onClose()
      } else {
        const created = await create.mutateAsync(body)
        onClose()
        navigate(`/w/projects/${created.id}`)
      }
    } catch (error) {
      applyApiErrorsToForm(form, error as ApiError)
    }
  })

  const errors = form.formState.errors

  return (
    <Sheet
      open={open}
      onClose={onClose}
      eyebrow={project ? 'Editar projeto' : 'Novo projeto'}
      title={project ? project.name : 'Criar projeto'}
      wide
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button variant="primary" loading={create.isPending || update.isPending} onClick={submit}>
            {project ? 'Salvar' : 'Criar projeto'}
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
        <Field label="Nome" htmlFor="p-name" error={errors.name?.message} className="sm:col-span-2">
          <TextInput id="p-name" autoFocus {...form.register('name')} aria-invalid={!!errors.name} />
        </Field>
        <Field
          label="Cliente"
          htmlFor="p-client"
          error={errors.client_id?.message}
          hint={project && hasTasks ? 'Não é possível trocar o cliente de um projeto com tarefas.' : undefined}
        >
          <SelectInput id="p-client" {...form.register('client_id')} disabled={Boolean(project && hasTasks)}>
            <option value="">Selecione…</option>
            {clients.data?.data.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </SelectInput>
        </Field>
        <Field label="Status" htmlFor="p-status">
          <SelectInput id="p-status" {...form.register('status')}>
            {PROJECT_STATUSES.map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </SelectInput>
        </Field>
        <Field label="Início" htmlFor="p-start">
          <TextInput id="p-start" type="date" {...form.register('starts_at')} />
        </Field>
        <Field label="Conclusão prevista" htmlFor="p-due" error={errors.due_at?.message}>
          <TextInput id="p-due" type="date" {...form.register('due_at')} />
        </Field>
        <Field
          label="Quadro Kanban"
          htmlFor="p-board"
          hint={project ? 'Ao trocar, as tarefas vão para a coluna equivalente.' : 'Vazio cria um quadro novo para o projeto.'}
        >
          <SelectInput id="p-board" {...form.register('board_id')}>
            {!project && <option value="">Criar quadro padrão</option>}
            {boards.data?.data.map((b) => (
              <option key={b.id} value={b.id}>
                {b.name}
              </option>
            ))}
          </SelectInput>
        </Field>
        <Field label="Profissionais">
          <Controller
            control={form.control}
            name="member_ids"
            render={({ field }) => (
              <MemberPicker multiple members={members.data?.data ?? []} value={field.value} onChange={field.onChange} placeholder="Ninguém" />
            )}
          />
        </Field>
        <Field label="Descrição" htmlFor="p-desc" className="sm:col-span-2">
          <TextArea id="p-desc" rows={4} {...form.register('description')} />
        </Field>
        <Field label="Observações internas" htmlFor="p-notes" hint="Nunca visíveis para o cliente." className="sm:col-span-2">
          <TextArea id="p-notes" rows={3} {...form.register('internal_notes')} />
        </Field>
        <button type="submit" className="hidden" />
      </form>
    </Sheet>
  )
}

function blank(clientId?: string): ProjectFormValues {
  return {
    name: '',
    description: '',
    client_id: clientId ?? '',
    board_id: '',
    status: 'planning',
    starts_at: '',
    due_at: '',
    internal_notes: '',
    member_ids: [],
  }
}
