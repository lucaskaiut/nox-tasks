import { CheckCircle2, Info, TriangleAlert, X, XCircle } from 'lucide-react'
import { useToastStore } from '@/shared/stores/toast.store'
import { cn } from '@/shared/utils/cn'

const icons = { success: CheckCircle2, error: XCircle, warning: TriangleAlert, info: Info }
const tones = { success: 'text-ws-ok', error: 'text-ws-danger', warning: 'text-ws-warn', info: 'text-ws-info' }

/** Notificações efêmeras com a identidade do Workspace (mesma store da plataforma). */
export function WsToaster() {
  const toasts = useToastStore((state) => state.toasts)
  const dismiss = useToastStore((state) => state.dismiss)

  return (
    <div aria-live="polite" className="pointer-events-none fixed right-4 bottom-20 z-[60] flex w-[min(360px,calc(100vw-2rem))] flex-col gap-2 md:bottom-4">
      {toasts.map((item) => {
        const Icon = icons[item.type]

        return (
          <div key={item.id} className="ws-rise pointer-events-auto flex gap-2.5 rounded-ws bg-ws-panel px-3.5 py-3 shadow-ws-pop">
            <Icon className={cn('mt-0.5 size-4 shrink-0', tones[item.type])} aria-hidden="true" />
            <div className="min-w-0 flex-1">
              <p className="text-[13px] font-medium text-ws-ink">{item.title}</p>
              {item.description && <p className="mt-0.5 text-[12.5px] text-ws-ink-2">{item.description}</p>}
            </div>
            <button type="button" onClick={() => dismiss(item.id)} aria-label="Fechar" className="text-ws-ink-3 hover:text-ws-ink">
              <X className="size-3.5" />
            </button>
          </div>
        )
      })}
    </div>
  )
}
