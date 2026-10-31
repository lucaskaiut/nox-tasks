import { z } from 'zod'

const optionalDate = z.string()

export const taskSchema = z
  .object({
    title: z.string().trim().min(1, 'Informe o título').max(255),
    description: z.string().max(20000),
    priority: z.enum(['low', 'normal', 'high', 'urgent']),
    visibility: z.enum(['internal', 'shared']),
    starts_at: optionalDate,
    due_at: optionalDate,
    estimated_hours: z.string().refine((v) => v === '' || (!Number.isNaN(Number(v)) && Number(v) >= 0), 'Valor inválido'),
    tags: z.string().max(400),
    column_id: z.string(),
    assignee: z.array(z.string()),
    participants: z.array(z.string()),
  })
  .refine((data) => !data.starts_at || !data.due_at || data.due_at >= data.starts_at, {
    path: ['due_at'],
    message: 'O prazo deve ser posterior ao início',
  })

export type TaskFormValues = z.infer<typeof taskSchema>

export const projectSchema = z
  .object({
    name: z.string().trim().min(1, 'Informe o nome').max(160),
    description: z.string().max(10000),
    client_id: z.string().min(1, 'Selecione o cliente'),
    board_id: z.string(),
    status: z.enum(['planning', 'active', 'on_hold', 'completed', 'cancelled']),
    starts_at: optionalDate,
    due_at: optionalDate,
    internal_notes: z.string().max(10000),
    member_ids: z.array(z.string()),
  })
  .refine((data) => !data.starts_at || !data.due_at || data.due_at >= data.starts_at, {
    path: ['due_at'],
    message: 'A conclusão deve ser posterior ao início',
  })

export type ProjectFormValues = z.infer<typeof projectSchema>

export const clientSchema = z.object({
  name: z.string().trim().min(1, 'Informe o nome').max(255),
  email: z.string().min(1, 'Informe o e-mail').email('E-mail inválido'),
  phone: z.string().max(20),
  document: z.string().max(20),
  notes: z.string().max(5000),
  status: z.enum(['active', 'inactive']),
})

export type ClientFormValues = z.infer<typeof clientSchema>

export const clientUserSchema = z.object({
  name: z.string().trim().min(1, 'Informe o nome'),
  email: z.string().email('E-mail inválido'),
  password: z.string().min(8, 'Mínimo de 8 caracteres'),
})

export type ClientUserFormValues = z.infer<typeof clientUserSchema>

export const nullIfEmpty = (value: string) => (value.trim() === '' ? null : value)
