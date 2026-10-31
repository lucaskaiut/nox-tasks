import { useNavigate } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { Bell, CheckCheck } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { Button, Empty, LoadingBlock, PageHeader } from '../ui'
import { wsKeys } from '../hooks/keys'
import { useWsMutation } from '../hooks/useWorkspace'
import { relative } from '../lib/format'
import { wsApi } from '../services/workspace.api'

export default function NotificationsPage() {
  const navigate = useNavigate()
  const notifications = useQuery({ queryKey: wsKeys.notifications, queryFn: () => wsApi.notifications.list({ per_page: 50 }) })
  const readAll = useWsMutation(() => wsApi.notifications.readAll())
  const read = useWsMutation((id: string) => wsApi.notifications.read(id))

  return (
    <div className="ws-rise mx-auto max-w-3xl">
      <PageHeader
        eyebrow="Central"
        title="Notificações"
        actions={
          (notifications.data?.meta.unread ?? 0) > 0 && (
            <Button size="sm" icon={<CheckCheck className="size-3.5" />} onClick={() => readAll.mutate(undefined)}>
              Marcar todas como lidas
            </Button>
          )
        }
      />
      {notifications.isLoading ? (
        <LoadingBlock />
      ) : notifications.data?.data.length ? (
        <ul className="overflow-hidden rounded-ws border border-ws-line bg-ws-panel">
          {notifications.data.data.map((item) => (
            <li key={item.id}>
              <button
                type="button"
                onClick={async () => {
                  if (!item.read_at) await read.mutateAsync(item.id)
                  if (item.task_id) navigate(`/w/tasks/${item.task_id}`)
                  else if (item.project_id) navigate(`/w/projects/${item.project_id}`)
                }}
                className={cn('flex w-full gap-3 border-b border-ws-line px-4 py-3 text-left last:border-b-0 hover:bg-ws-hover', !item.read_at && 'bg-ws-accent-tint/40')}
              >
                <span className={cn('mt-1.5 size-2 shrink-0 rounded-full', item.read_at ? 'bg-transparent' : 'bg-ws-accent')} aria-label={item.read_at ? undefined : 'Não lida'} />
                <span className="min-w-0 flex-1">
                  <span className="block text-[13.5px] font-medium text-ws-ink">{item.title}</span>
                  <span className="block text-[13px] text-ws-ink-2">{item.message}</span>
                  <span className="mt-1 block font-ws-mono text-[11px] text-ws-ink-3">
                    {item.project_name && `${item.project_name} · `}
                    {relative(item.created_at)}
                  </span>
                </span>
              </button>
            </li>
          ))}
        </ul>
      ) : (
        <Empty icon={Bell} title="Tudo em dia" description="Você será avisado sobre atribuições, comentários, prazos e conclusões." />
      )}
    </div>
  )
}
