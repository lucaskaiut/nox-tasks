import { useEffect, useRef, useState, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { X } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { IconButton } from './Button'

function useEscape(open: boolean, onClose: () => void) {
  useEffect(() => {
    if (!open) return
    const handler = (event: KeyboardEvent) => event.key === 'Escape' && onClose()
    document.addEventListener('keydown', handler)

    return () => document.removeEventListener('keydown', handler)
  }, [open, onClose])
}

/** Portal dentro do escopo `.ws` para herdar os tokens do Workspace. */
function WsPortal({ children }: { children: ReactNode }) {
  return createPortal(<div className="ws">{children}</div>, document.body)
}

/** Painel lateral — usado para formulários e detalhes rápidos. */
export function Sheet({
  open,
  onClose,
  title,
  eyebrow,
  children,
  footer,
  wide,
}: {
  open: boolean
  onClose: () => void
  title: string
  eyebrow?: string
  children: ReactNode
  footer?: ReactNode
  wide?: boolean
}) {
  useEscape(open, onClose)

  if (!open) return null

  return (
    <WsPortal>
      <div className="fixed inset-0 z-50 flex justify-end">
        <div className="absolute inset-0 bg-ws-scrim" onClick={onClose} aria-hidden="true" />
        <div
          role="dialog"
          aria-modal="true"
          aria-label={title}
          className={cn(
            'ws-slide-in relative flex h-full w-full flex-col border-l border-ws-line-strong bg-ws-panel',
            wide ? 'max-w-2xl' : 'max-w-lg',
          )}
        >
          <header className="flex items-start justify-between gap-3 border-b border-ws-line px-5 py-4">
            <div>
              {eyebrow && <p className="ws-eyebrow mb-1">{eyebrow}</p>}
              <h2 className="text-[16px] font-semibold text-ws-ink">{title}</h2>
            </div>
            <IconButton label="Fechar" onClick={onClose}>
              <X className="size-4" />
            </IconButton>
          </header>
          <div className="flex-1 overflow-y-auto px-5 py-5">{children}</div>
          {footer && <footer className="flex justify-end gap-2 border-t border-ws-line px-5 py-3">{footer}</footer>}
        </div>
      </div>
    </WsPortal>
  )
}

export function Confirm({
  open,
  title,
  description,
  confirmLabel = 'Confirmar',
  destructive,
  loading,
  onConfirm,
  onClose,
  children,
}: {
  open: boolean
  title: string
  description?: string
  confirmLabel?: string
  destructive?: boolean
  loading?: boolean
  onConfirm: () => void
  onClose: () => void
  children?: ReactNode
}) {
  useEscape(open, onClose)

  if (!open) return null

  return (
    <WsPortal>
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div className="absolute inset-0 bg-ws-scrim" onClick={onClose} aria-hidden="true" />
        <div role="alertdialog" aria-modal="true" aria-label={title} className="ws-rise relative w-full max-w-md rounded-ws border border-ws-line-strong bg-ws-panel">
          <div className="px-5 pt-5 pb-4">
            <h2 className="text-[15px] font-semibold text-ws-ink">{title}</h2>
            {description && <p className="mt-1.5 text-[13px] text-ws-ink-2">{description}</p>}
            {children && <div className="mt-4">{children}</div>}
          </div>
          <div className="flex justify-end gap-2 border-t border-ws-line px-5 py-3">
            <button type="button" onClick={onClose} className="h-8 rounded-ws px-3 text-[13px] text-ws-ink-2 hover:bg-ws-hover">
              Cancelar
            </button>
            <button
              type="button"
              disabled={loading}
              onClick={onConfirm}
              className={cn(
                'h-8 rounded-ws px-3 text-[13px] font-medium disabled:opacity-50',
                destructive ? 'bg-ws-danger text-white hover:opacity-90' : 'bg-ws-accent text-ws-accent-ink hover:bg-ws-accent-hover',
              )}
            >
              {confirmLabel}
            </button>
          </div>
        </div>
      </div>
    </WsPortal>
  )
}

/** Menu suspenso simples (clique fora / Esc fecham). */
export function Menu({
  trigger,
  children,
  align = 'end',
  className,
}: {
  trigger: (props: { open: boolean; toggle: () => void }) => ReactNode
  children: (close: () => void) => ReactNode
  align?: 'start' | 'end'
  className?: string
}) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useEscape(open, () => setOpen(false))

  useEffect(() => {
    if (!open) return
    const handler = (event: MouseEvent) => {
      if (!ref.current?.contains(event.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', handler)

    return () => document.removeEventListener('mousedown', handler)
  }, [open])

  return (
    <div ref={ref} className="relative">
      {trigger({ open, toggle: () => setOpen((value) => !value) })}
      {open && (
        <div
          role="menu"
          className={cn(
            'ws-rise absolute top-full z-40 mt-1 min-w-48 rounded-ws border border-ws-line-strong bg-ws-panel py-1',
            align === 'end' ? 'right-0' : 'left-0',
            className,
          )}
        >
          {children(() => setOpen(false))}
        </div>
      )}
    </div>
  )
}

export function MenuItem({
  onSelect,
  children,
  danger,
  icon,
}: {
  onSelect: () => void
  children: ReactNode
  danger?: boolean
  icon?: ReactNode
}) {
  return (
    <button
      type="button"
      role="menuitem"
      onClick={onSelect}
      className={cn(
        'flex w-full items-center gap-2 px-3 py-1.5 text-left text-[13px] hover:bg-ws-hover',
        danger ? 'text-ws-danger' : 'text-ws-ink',
      )}
    >
      {icon}
      {children}
    </button>
  )
}
