import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'
import type {
  Activity,
  Attachment,
  Board,
  BoardColumn,
  Client,
  Comment,
  Dashboard,
  McpToken,
  Member,
  Page,
  Project,
  Subtask,
  Task,
  WsNotification,
} from '../lib/types'

type Params = Record<string, unknown>

async function get<T>(url: string, params?: Params): Promise<T> {
  return (await http.get<ApiResponse<T>>(url, { params })).data.data
}

async function page<T>(url: string, params?: Params): Promise<Page<T>> {
  const { data } = await http.get<Page<T>>(url, { params })

  return { data: data.data, meta: data.meta }
}

async function send<T>(method: 'post' | 'put' | 'patch' | 'delete', url: string, body?: unknown): Promise<T> {
  const response = await http.request<ApiResponse<T>>({ method, url, data: body })

  return response.data.data
}

/** Cliente HTTP do Workspace — espelha 1:1 a API REST (mesmas regras do MCP). */
export const wsApi = {
  dashboard: () => get<Dashboard>('/workspace/dashboard'),
  members: (search?: string) => page<Member>('/workspace/members', { search, per_page: 100 }),
  tags: () => get<Array<{ name: string; color: string }>>('/workspace/tags'),

  clients: {
    list: (params: Params) => page<Client>('/clients', params),
    get: (id: string) => get<Client>(`/clients/${id}`),
    create: (body: Partial<Client>) => send<Client>('post', '/clients', body),
    update: (id: string, body: Partial<Client>) => send<Client>('patch', `/clients/${id}`, body),
    remove: (id: string) => send<null>('delete', `/clients/${id}`),
    addUser: (id: string, body: { name: string; email: string; password: string; phone?: string }) =>
      send<Member>('post', `/clients/${id}/users`, body),
    removeUser: (id: string, userId: string) => send<null>('delete', `/clients/${id}/users/${userId}`),
  },

  projects: {
    list: (params: Params) => page<Project>('/projects', params),
    get: (id: string) => get<Project>(`/projects/${id}`),
    create: (body: Params) => send<Project>('post', '/projects', body),
    update: (id: string, body: Params) => send<Project>('patch', `/projects/${id}`, body),
    archive: (id: string) => send<Project>('post', `/projects/${id}/archive`),
    restore: (id: string) => send<Project>('post', `/projects/${id}/restore`),
    board: (id: string) => get<{ board: Board; tasks: Task[] }>(`/projects/${id}/board`),
    activity: (id: string, params?: Params) => page<Activity>(`/projects/${id}/activity`, params),
  },

  tasks: {
    list: (params: Params) => page<Task>('/tasks', params),
    get: (id: string) => get<Task>(`/tasks/${id}`),
    create: (projectId: string, body: Params) => send<Task>('post', `/projects/${projectId}/tasks`, body),
    update: (id: string, body: Params) => send<Task>('patch', `/tasks/${id}`, body),
    remove: (id: string) => send<null>('delete', `/tasks/${id}`),
    assign: (id: string, body: { assignee_id: string | null; participant_ids?: string[] }) =>
      send<Task>('post', `/tasks/${id}/assign`, body),
    move: (id: string, body: { column_id: string; position?: number; after_id?: string | null }) => send<Task>('post', `/tasks/${id}/move`, body),
    activity: (id: string, params?: Params) => page<Activity>(`/tasks/${id}/activity`, params),
    comments: (id: string) => page<Comment>(`/tasks/${id}/comments`, { per_page: 100 }),
    comment: (id: string, body: { body: string; visibility?: string }) => send<Comment>('post', `/tasks/${id}/comments`, body),
    subtasks: {
      create: (taskId: string, body: Params) => send<Subtask>('post', `/tasks/${taskId}/subtasks`, body),
      update: (taskId: string, id: string, body: Params) => send<Subtask>('patch', `/tasks/${taskId}/subtasks/${id}`, body),
      remove: (taskId: string, id: string) => send<null>('delete', `/tasks/${taskId}/subtasks/${id}`),
    },
    attachments: (id: string) => get<Attachment[]>(`/tasks/${id}/attachments`),
    upload: (id: string, file: File, visibility?: string) => {
      const form = new FormData()
      form.append('file', file)
      if (visibility) form.append('visibility', visibility)

      return send<Attachment>('post', `/tasks/${id}/attachments`, form)
    },
    removeAttachment: (id: string) => send<null>('delete', `/attachments/${id}`),
    downloadUrl: (id: string) => `${http.defaults.baseURL}/attachments/${id}/download`,
  },

  /** Quadro Kanban geral (único) — tarefas de todos os projetos. */
  board: {
    get: (params?: Params) => get<{ board: Board; tasks: Task[] }>('/board', params),
    config: () => get<Board>('/board/config'),
    update: (body: Params) => send<Board>('patch', '/board', body),
    addColumn: (body: Params) => send<BoardColumn>('post', '/board/columns', body),
    updateColumn: (columnId: string, body: Params) => send<BoardColumn>('patch', `/board/columns/${columnId}`, body),
    moveColumn: (columnId: string, position: number) => send<BoardColumn>('post', `/board/columns/${columnId}/move`, { position }),
    removeColumn: (columnId: string, moveTo?: string) =>
      send<null>('delete', `/board/columns/${columnId}`, moveTo ? { move_tasks_to: moveTo } : undefined),
  },

  notifications: {
    list: (params?: Params) => page<WsNotification>('/notifications', params),
    unread: () => get<{ unread: number }>('/notifications/unread-count'),
    read: (id: string) => send<null>('post', `/notifications/${id}/read`),
    readAll: () => send<null>('post', '/notifications/read-all'),
  },

  mcp: {
    list: () => get<{ server_url: string; tokens: McpToken[] }>('/mcp/tokens'),
    create: (body: { name: string; expires_in_days?: number | null }) =>
      send<{ id: number; name: string; token: string; server_url: string; expires_at: string | null }>('post', '/mcp/tokens', body),
    revoke: (id: number) => send<null>('delete', `/mcp/tokens/${id}`),
  },
}
