export type Priority = 'low' | 'normal' | 'high' | 'urgent'
export type Visibility = 'internal' | 'shared'
export type ColumnKind = 'open' | 'waiting' | 'done' | 'cancelled'
export type ProjectStatus = 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled'
export type SubtaskStatus = 'pending' | 'in_progress' | 'done'
export type ClientStatus = 'active' | 'inactive'

export interface Member {
  id: string
  name: string
  email?: string
  is_client?: boolean
}

export interface Ref {
  id: string
  name: string
}

export interface Client {
  id: string
  name: string
  email: string
  phone: string | null
  document: string | null
  notes: string | null
  status: ClientStatus
  status_label: string
  projects_count?: number
  users_count?: number
  users?: Member[]
  created_at: string
  updated_at: string
}

export interface BoardColumn {
  id: string
  name: string
  color: string
  kind: ColumnKind
  kind_label: string
  position: number
}

export interface Board {
  id: string
  name: string
  description: string | null
  columns?: BoardColumn[]
  created_at: string
  updated_at: string
}

export interface ProjectProgress {
  total: number
  done: number
  cancelled: number
  overdue: number
  open: number
  percent: number
}

export interface Project {
  id: string
  name: string
  description: string | null
  status: ProjectStatus
  status_label: string
  client?: Ref
  members?: Member[]
  starts_at: string | null
  due_at: string | null
  internal_notes?: string | null
  archived_at: string | null
  is_archived: boolean
  progress?: ProjectProgress
  creator?: Member | null
  created_at: string
  updated_at: string
}

export interface Subtask {
  id: string
  title: string
  description: string | null
  status: SubtaskStatus
  status_label: string
  priority: Priority
  due_at: string | null
  position: number
  completed_at: string | null
  assignee?: Member | null
}

export interface Task {
  id: string
  title: string
  description: string | null
  project?: { id: string; name: string; is_archived: boolean }
  client?: Ref
  status?: BoardColumn
  priority: Priority
  priority_label: string
  visibility: Visibility
  assignee?: Member | null
  participants?: Member[]
  creator?: Member | null
  tags?: Array<{ name: string; color: string }>
  starts_at: string | null
  due_at: string | null
  estimated_hours: number | null
  completed_at: string | null
  is_overdue: boolean
  position: number
  subtasks_summary: { total: number; done: number; percent: number } | null
  subtasks?: Subtask[]
  comments_count?: number
  attachments_count?: number
  last_comment_at?: string | null
  created_at: string
  updated_at: string
}

export interface Comment {
  id: string
  body: string
  visibility: Visibility
  source: 'ui' | 'api' | 'mcp'
  author?: Member | null
  created_at: string
}

export interface Attachment {
  id: string
  name: string
  mime_type: string | null
  size: number
  visibility: Visibility
  uploader?: Member | null
  created_at: string
}

export interface Activity {
  id: number
  event: string
  subject_type: string
  subject_id: string
  changes: Record<string, { from: unknown; to: unknown }> | null
  source: 'ui' | 'api' | 'mcp'
  visibility: Visibility
  user?: Member | null
  project?: Ref | null
  task?: { id: string; title: string } | null
  created_at: string
}

export interface WsNotification {
  id: string
  kind: string
  title: string
  message: string
  task_id?: string
  task_title?: string
  project_id?: string
  project_name?: string
  actor?: string | null
  read_at: string | null
  created_at: string
}

export interface Dashboard {
  profile: 'client' | 'professional'
  metrics: {
    active_projects: number
    open_tasks: number
    waiting_tasks: number
    overdue_tasks: number
    completed_tasks: number
    completed_last_7_days: number
    my_open_tasks: number | null
  }
  status_distribution: Array<{ kind: ColumnKind; label: string; total: number }>
  priority_distribution: Array<{ priority: Priority; label: string; total: number }>
  priority_tasks: Task[]
  upcoming_deadlines: Task[]
  recent_activity: Activity[]
}

export interface McpToken {
  id: number
  name: string
  last_used_at: string | null
  expires_at: string | null
  created_at: string
}

export interface Page<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number; unread?: number }
}
