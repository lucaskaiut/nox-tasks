import type { ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'
import { cn } from '@/shared/utils/cn'

type Tone = 'neutral' | 'accent' | 'danger' | 'warn' | 'ok' | 'info'

const tones: Record<Tone, string> = {
  neutral: 'bg-ws-sunken text-ws-ink-2',
  accent: 'bg-ws-accent-tint text-ws-accent',
  danger: 'bg-ws-danger-tint text-ws-danger',
  warn: 'bg-ws-warn-tint text-ws-warn',
  ok: 'bg-ws-ok-tint text-ws-ok',
  info: 'bg-ws-info-tint text-ws-info',
}

export function Pill({ tone = 'neutral', children, className }: { tone?: Tone; children: ReactNode; className?: string }) {
  return (
    <span className={cn('inline-flex h-5 items-center gap-1 rounded-[5px] px-1.5 text-[11.5px] font-medium', tones[tone], className)}>
      {children}
    </span>
  )
}

export function Dot({ color, className }: { color: string; className?: string }) {
  return <span aria-hidden="true" className={cn('inline-block size-2 shrink-0 rounded-full', className)} style={{ background: color }} />
}

export function Initials({ name, size = 'sm', className }: { name?: string | null; size?: 'xs' | 'sm' | 'md'; className?: string }) {
  const letters = (name ?? '?')
    .replace(/[^\p{L}\p{N}\s]/gu, '')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')

  return (
    <span
      title={name ?? undefined}
      className={cn(
        'inline-flex shrink-0 items-center justify-center rounded-ws-sm bg-ws-hover tabular-nums font-medium text-ws-ink-2',
        size === 'xs' && 'size-5 text-[9.5px]',
        size === 'sm' && 'size-6 text-[10.5px]',
        size === 'md' && 'size-8 text-[12px]',
        className,
      )}
    >
      {letters}
    </span>
  )
}

export function Panel({
  title,
  eyebrow,
  actions,
  children,
  className,
  bodyClassName,
}: {
  title?: ReactNode
  eyebrow?: string
  actions?: ReactNode
  children: ReactNode
  className?: string
  bodyClassName?: string
}) {
  return (
    <section className={cn('rounded-ws bg-ws-panel shadow-ws', className)}>
      {(title || actions || eyebrow) && (
        <header className="flex min-h-11 items-center justify-between gap-3 px-4 pt-3 pb-1">
          <div className="min-w-0">
            {eyebrow && <p className="ws-eyebrow">{eyebrow}</p>}
            {title && <h2 className="truncate text-[13.5px] font-semibold text-ws-ink">{title}</h2>}
          </div>
          {actions && <div className="flex shrink-0 items-center gap-1.5">{actions}</div>}
        </header>
      )}
      <div className={cn('p-4', bodyClassName)}>{children}</div>
    </section>
  )
}

export function PageHeader({
  eyebrow,
  title,
  description,
  actions,
  meta,
}: {
  eyebrow?: string
  title: ReactNode
  description?: ReactNode
  actions?: ReactNode
  meta?: ReactNode
}) {
  return (
    <div className="mb-6 flex flex-col gap-4 pb-1 md:flex-row md:items-end md:justify-between">
      <div className="min-w-0">
        {eyebrow && <p className="ws-eyebrow mb-1.5">{eyebrow}</p>}
        <h1 className="text-[22px] leading-tight font-semibold tracking-[-0.01em] text-ws-ink md:text-[26px]">{title}</h1>
        {description && <p className="mt-1.5 max-w-2xl text-[13.5px] text-ws-ink-2">{description}</p>}
        {meta && <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-[12.5px] text-ws-ink-2">{meta}</div>}
      </div>
      {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
    </div>
  )
}

export function Empty({
  icon: Icon,
  title,
  description,
  action,
  className,
}: {
  icon?: LucideIcon
  title: string
  description?: string
  action?: ReactNode
  className?: string
}) {
  return (
    <div className={cn('flex flex-col items-start gap-2 rounded-ws bg-ws-panel px-5 py-8 shadow-ws-sm', className)}>
      {Icon && <Icon className="size-5 text-ws-ink-3" strokeWidth={1.5} aria-hidden="true" />}
      <p className="text-[14px] font-medium text-ws-ink">{title}</p>
      {description && <p className="max-w-md text-[13px] text-ws-ink-2">{description}</p>}
      {action && <div className="mt-2">{action}</div>}
    </div>
  )
}

export function Bar({ value, className, tone = 'accent' }: { value: number; className?: string; tone?: 'accent' | 'danger' }) {
  const clamped = Math.max(0, Math.min(100, value))

  return (
    <div
      role="progressbar"
      aria-valuenow={clamped}
      aria-valuemin={0}
      aria-valuemax={100}
      className={cn('h-1.5 w-full overflow-hidden rounded-full bg-ws-hover', className)}
    >
      <div
        className={cn('h-full rounded-full transition-[width] duration-300', tone === 'accent' ? 'bg-ws-accent' : 'bg-ws-danger')}
        style={{ width: `${clamped}%` }}
      />
    </div>
  )
}

export function Stat({ label, value, hint, tone }: { label: string; value: ReactNode; hint?: ReactNode; tone?: 'danger' | 'accent' }) {
  return (
    <div className="flex flex-col gap-1 px-4 py-1 first:pl-0">
      <span className="ws-eyebrow">{label}</span>
      <span
        className={cn(
          'tabular-nums text-[28px] leading-none font-medium tracking-[-0.02em]',
          tone === 'danger' ? 'text-ws-danger' : tone === 'accent' ? 'text-ws-accent' : 'text-ws-ink',
        )}
      >
        {value}
      </span>
      {hint && <span className="text-[12px] text-ws-ink-3">{hint}</span>}
    </div>
  )
}

export function Skeleton({ className }: { className?: string }) {
  return <div className={cn('animate-pulse rounded-ws bg-ws-hover', className)} />
}

export function LoadingBlock({ rows = 4 }: { rows?: number }) {
  return (
    <div className="flex flex-col gap-2" aria-busy="true" aria-label="Carregando">
      {Array.from({ length: rows }, (_, i) => (
        <Skeleton key={i} className="h-10" />
      ))}
    </div>
  )
}

export function ErrorBlock({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="rounded-ws bg-ws-danger-tint px-4 py-3 text-[13px] text-ws-danger">
      {message}
      {onRetry && (
        <button type="button" onClick={onRetry} className="ml-2 underline underline-offset-2">
          Tentar novamente
        </button>
      )}
    </div>
  )
}
