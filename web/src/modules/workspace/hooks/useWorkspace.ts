import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/shared/stores/toast.store'
import { useSessionStore } from '@/shared/stores/session.store'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { Permission } from '@/shared/constants/permissions'
import { wsApi } from '../services/workspace.api'
import { wsKeys } from './keys'

/** Perfil funcional: usuário vinculado a cliente x profissional. */
export function useProfile() {
  const user = useSessionStore((state) => state.user)
  const { can } = usePermissions()
  const isClient = Boolean(user?.client)

  return {
    user,
    isClient,
    client: user?.client ?? null,
    /** Ações de gestão: perfil profissional + permissão (o backend aplica a mesma regra). */
    canManage: (permission: Permission) => !isClient && can(permission),
    can,
  }
}

/** Invalida todo o cache do Workspace após uma mutação. */
export function useInvalidateWs() {
  const queryClient = useQueryClient()

  return () => queryClient.invalidateQueries({ queryKey: wsKeys.all })
}

export function useWsMutation<TArgs, TResult>(fn: (args: TArgs) => Promise<TResult>, success?: string) {
  const invalidate = useInvalidateWs()

  return useMutation({
    mutationFn: fn,
    onSuccess: () => {
      invalidate()
      if (success) toast.success(success)
    },
    onError: (error: { status?: number; message?: string }) => {
      if (error?.status === 422) toast.error('Verifique os dados', error.message)
      else if (error?.status === 404) toast.error('Não encontrado', 'O item não existe ou não está acessível.')
    },
  })
}

export const useDashboard = () => useQuery({ queryKey: wsKeys.dashboard, queryFn: wsApi.dashboard })

export const useMembers = (enabled = true) =>
  useQuery({ queryKey: wsKeys.members, queryFn: () => wsApi.members(), enabled, staleTime: 120_000 })

export const useProjects = (params: Record<string, unknown>) =>
  useQuery({ queryKey: wsKeys.projects(params), queryFn: () => wsApi.projects.list(params), placeholderData: keepPreviousData })

export const useProject = (id: string) => useQuery({ queryKey: wsKeys.project(id), queryFn: () => wsApi.projects.get(id) })

export const useProjectBoard = (id: string) =>
  useQuery({ queryKey: wsKeys.projectBoard(id), queryFn: () => wsApi.projects.board(id) })

export const useTasks = (params: Record<string, unknown>) =>
  useQuery({ queryKey: wsKeys.tasks(params), queryFn: () => wsApi.tasks.list(params), placeholderData: keepPreviousData })

export const useTask = (id: string | null) =>
  useQuery({ queryKey: wsKeys.task(id ?? ''), queryFn: () => wsApi.tasks.get(id!), enabled: Boolean(id) })

export const useClients = (params: Record<string, unknown>, enabled = true) =>
  useQuery({ queryKey: wsKeys.clients(params), queryFn: () => wsApi.clients.list(params), placeholderData: keepPreviousData, enabled })

export const useClient = (id: string) => useQuery({ queryKey: wsKeys.client(id), queryFn: () => wsApi.clients.get(id) })

/** Quadro geral e suas colunas, sem tarefas. */
export const useBoardConfig = (enabled = true) =>
  useQuery({ queryKey: wsKeys.boardConfig, queryFn: wsApi.board.config, enabled, staleTime: 60_000 })

export const useBoard = (params: Record<string, unknown> = {}) =>
  useQuery({ queryKey: wsKeys.board(params), queryFn: () => wsApi.board.get(params), placeholderData: keepPreviousData })

export const useUnreadCount = () =>
  useQuery({ queryKey: wsKeys.unread, queryFn: wsApi.notifications.unread, refetchInterval: 60_000 })
