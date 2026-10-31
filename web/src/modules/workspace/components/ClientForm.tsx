import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { ApiError } from '@/shared/api/errors'
import { Button, Field, SelectInput, Sheet, TextArea, TextInput } from '../ui'
import type { Client } from '../lib/types'
import { useWsMutation } from '../hooks/useWorkspace'
import { wsApi } from '../services/workspace.api'
import { clientSchema, nullIfEmpty, type ClientFormValues } from './schemas'

export function ClientFormSheet({ open, onClose, client }: { open: boolean; onClose: () => void; client?: Client }) {
  const form = useForm<ClientFormValues>({ resolver: zodResolver(clientSchema), defaultValues: blank() })

  useEffect(() => {
    if (!open) return
    form.reset(
      client
        ? {
            name: client.name,
            email: client.email,
            phone: client.phone ?? '',
            document: client.document ?? '',
            notes: client.notes ?? '',
            status: client.status,
          }
        : blank(),
    )
  }, [open, client, form])

  const create = useWsMutation((body: Record<string, unknown>) => wsApi.clients.create(body), 'Cliente cadastrado')
  const update = useWsMutation((body: Record<string, unknown>) => wsApi.clients.update(client!.id, body), 'Cliente atualizado')

  const submit = form.handleSubmit(async (values) => {
    const body = { ...values, phone: nullIfEmpty(values.phone), document: nullIfEmpty(values.document), notes: nullIfEmpty(values.notes) }
    try {
      if (client) await update.mutateAsync(body)
      else await create.mutateAsync(body)
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
      eyebrow={client ? 'Editar cliente' : 'Novo cliente'}
      title={client ? client.name : 'Cadastrar cliente'}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button variant="primary" loading={create.isPending || update.isPending} onClick={submit}>
            Salvar
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="flex flex-col gap-4">
        <Field label="Nome ou razão social" htmlFor="c-name" error={errors.name?.message}>
          <TextInput id="c-name" autoFocus {...form.register('name')} aria-invalid={!!errors.name} />
        </Field>
        <Field label="E-mail" htmlFor="c-email" error={errors.email?.message}>
          <TextInput id="c-email" type="email" {...form.register('email')} aria-invalid={!!errors.email} />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Telefone" htmlFor="c-phone" error={errors.phone?.message}>
            <TextInput id="c-phone" {...form.register('phone')} />
          </Field>
          <Field label="CPF/CNPJ" htmlFor="c-doc" error={errors.document?.message}>
            <TextInput id="c-doc" {...form.register('document')} />
          </Field>
        </div>
        <Field label="Status" htmlFor="c-status">
          <SelectInput id="c-status" {...form.register('status')}>
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
          </SelectInput>
        </Field>
        <Field label="Observações internas" htmlFor="c-notes" hint="Visíveis apenas para profissionais.">
          <TextArea id="c-notes" {...form.register('notes')} />
        </Field>
        <button type="submit" className="hidden" />
      </form>
    </Sheet>
  )
}

function blank(): ClientFormValues {
  return { name: '', email: '', phone: '', document: '', notes: '', status: 'active' }
}
