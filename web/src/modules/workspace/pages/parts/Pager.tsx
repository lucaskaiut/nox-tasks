import { ChevronLeft, ChevronRight } from 'lucide-react'
import { IconButton } from '../../ui'

export function Pager({ meta, onPage }: { meta: { current_page: number; last_page: number; total: number }; onPage: (page: number) => void }) {
  if (meta.last_page <= 1) return <p className="mt-3 tabular-nums text-[11.5px] text-ws-ink-3">{meta.total} itens</p>

  return (
    <div className="mt-3 flex items-center justify-between">
      <p className="tabular-nums text-[11.5px] text-ws-ink-3">
        {meta.total} itens · página {meta.current_page} de {meta.last_page}
      </p>
      <div className="flex gap-1">
        <IconButton label="Página anterior" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>
          <ChevronLeft className="size-4" />
        </IconButton>
        <IconButton label="Próxima página" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>
          <ChevronRight className="size-4" />
        </IconButton>
      </div>
    </div>
  )
}
