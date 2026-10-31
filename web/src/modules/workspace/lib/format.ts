import type { Activity, ColumnKind, Priority, ProjectStatus } from './types'

const dateFmt = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' })
const fullDateFmt = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' })
const timeFmt = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })

/** Datas "YYYY-MM-DD" são locais (sem fuso). */
export function parseDate(value: string | null | undefined): Date | null {
  if (!value) return null
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    const [y, m, d] = value.split('-').map(Number)
    return new Date(y, m - 1, d)
  }
  return new Date(value)
}

export function shortDate(value: string | null | undefined): string {
  const date = parseDate(value)
  return date ? dateFmt.format(date).replace('.', '') : '—'
}

export function longDate(value: string | null | undefined): string {
  const date = parseDate(value)
  return date ? fullDateFmt.format(date).replace('.', '') : '—'
}

export function dateTime(value: string | null | undefined): string {
  const date = parseDate(value)
  return date ? timeFmt.format(date).replace('.', '') : '—'
}

export function relative(value: string | null | undefined): string {
  const date = parseDate(value)
  if (!date) return '—'
  const diff = (Date.now() - date.getTime()) / 1000
  if (diff < 60) return 'agora'
  if (diff < 3600) return `${Math.floor(diff / 60)} min`
  if (diff < 86400) return `${Math.floor(diff / 3600)} h`
  if (diff < 86400 * 7) return `${Math.floor(diff / 86400)} d`
  return shortDate(value)
}

/** Dias até o prazo (negativo = atrasado). */
export function daysUntil(value: string | null | undefined): number | null {
  const date = parseDate(value)
  if (!date) return null
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return Math.round((date.getTime() - today.getTime()) / 86400000)
}

export function dueLabel(value: string | null | undefined): string {
  const days = daysUntil(value)
  if (days === null) return 'Sem prazo'
  if (days === 0) return 'Hoje'
  if (days === 1) return 'Amanhã'
  if (days === -1) return 'Ontem'
  if (days < 0) return `${Math.abs(days)}d atrasada`
  if (days <= 7) return `em ${days}d`
  return shortDate(value)
}

export const PRIORITIES: Array<{ value: Priority; label: string }> = [
  { value: 'urgent', label: 'Urgente' },
  { value: 'high', label: 'Alta' },
  { value: 'normal', label: 'Normal' },
  { value: 'low', label: 'Baixa' },
]

export const PROJECT_STATUSES: Array<{ value: ProjectStatus; label: string }> = [
  { value: 'planning', label: 'Planejamento' },
  { value: 'active', label: 'Em andamento' },
  { value: 'on_hold', label: 'Pausado' },
  { value: 'completed', label: 'Concluído' },
  { value: 'cancelled', label: 'Cancelado' },
]

export const COLUMN_KINDS: Array<{ value: ColumnKind; label: string; hint: string }> = [
  { value: 'open', label: 'Em aberto', hint: 'Trabalho a fazer ou em execução' },
  { value: 'waiting', label: 'Aguardando retorno', hint: 'Depende de resposta (ex.: do cliente)' },
  { value: 'done', label: 'Concluída', hint: 'Marca a tarefa como concluída' },
  { value: 'cancelled', label: 'Cancelada', hint: 'Fora dos indicadores de progresso' },
]

export const COLUMN_COLORS = ['#71717a', '#2563eb', '#7c3aed', '#d97706', '#dc2626', '#16a34a', '#0891b2', '#1b1a17']

export function fileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

const FIELD_LABELS: Record<string, string> = {
  title: 'título',
  description: 'descrição',
  priority: 'prioridade',
  visibility: 'visibilidade',
  due_at: 'prazo',
  starts_at: 'início',
  estimated_hours: 'estimativa',
  status: 'status',
  assignee: 'responsável',
  participants: 'participantes',
  tags: 'tags',
  name: 'nome',
  client: 'cliente',
  board: 'quadro',
  members: 'participantes',
  internal_notes: 'notas internas',
}

const EVENT_LABELS: Record<string, string> = {
  'project.created': 'criou o projeto',
  'project.updated': 'atualizou o projeto',
  'project.archived': 'arquivou o projeto',
  'project.restored': 'reativou o projeto',
  'task.created': 'criou a tarefa',
  'task.updated': 'editou a tarefa',
  'task.moved': 'moveu a tarefa',
  'task.assigned': 'alterou responsáveis de',
  'task.deleted': 'excluiu a tarefa',
  'subtask.created': 'adicionou subtarefa em',
  'subtask.updated': 'atualizou subtarefa em',
  'subtask.completed': 'concluiu subtarefa em',
  'subtask.deleted': 'removeu subtarefa de',
  'comment.created': 'comentou em',
  'attachment.created': 'anexou arquivo em',
  'attachment.deleted': 'removeu anexo de',
}

export function eventLabel(activity: Activity, withTarget = true): string {
  const label = EVENT_LABELS[activity.event] ?? activity.event

  return withTarget ? label : label.replace(/ (em|de)$/, '')
}

function show(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (Array.isArray(value)) return value.length ? value.join(', ') : '—'
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) return shortDate(value)
  return String(value)
}

export function changeLines(activity: Activity): string[] {
  if (!activity.changes) return []
  return Object.entries(activity.changes)
    .filter(([field]) => field !== 'subtask')
    .map(([field, change]) => `${FIELD_LABELS[field] ?? field}: ${show(change.from)} → ${show(change.to)}`)
}
