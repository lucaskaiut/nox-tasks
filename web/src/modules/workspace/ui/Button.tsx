import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react'
import { Link, type LinkProps } from 'react-router'
import { cn } from '@/shared/utils/cn'

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger'
type Size = 'sm' | 'md'

const base =
  'inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-ws-sm font-medium transition-[background-color,box-shadow,color] duration-100 disabled:pointer-events-none disabled:opacity-50 select-none'

const variants: Record<Variant, string> = {
  primary: 'bg-ws-accent text-ws-accent-ink shadow-ws-sm hover:bg-ws-accent-hover',
  secondary: 'bg-ws-panel text-ws-ink shadow-ws-sm hover:bg-ws-hover',
  ghost: 'bg-transparent text-ws-ink-2 hover:bg-ws-hover hover:text-ws-ink',
  danger: 'bg-ws-danger-tint text-ws-danger hover:bg-ws-danger hover:text-white',
}

const sizes: Record<Size, string> = {
  sm: 'h-7 px-2.5 text-[12.5px]',
  md: 'h-9 px-3.5 text-[13.5px]',
}

export interface WsButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  size?: Size
  loading?: boolean
  icon?: ReactNode
}

export const Button = forwardRef<HTMLButtonElement, WsButtonProps>(function Button(
  { variant = 'secondary', size = 'md', loading, icon, className, children, disabled, type = 'button', ...props },
  ref,
) {
  return (
    <button
      ref={ref}
      type={type}
      disabled={disabled || loading}
      className={cn(base, variants[variant], sizes[size], className)}
      {...props}
    >
      {loading ? <Spinner /> : icon}
      {children}
    </button>
  )
})

export function ButtonLink({
  variant = 'secondary',
  size = 'md',
  icon,
  className,
  children,
  ...props
}: LinkProps & { variant?: Variant; size?: Size; icon?: ReactNode }) {
  return (
    <Link className={cn(base, variants[variant], sizes[size], className)} {...props}>
      {icon}
      {children}
    </Link>
  )
}

export const IconButton = forwardRef<HTMLButtonElement, ButtonHTMLAttributes<HTMLButtonElement> & { label: string }>(
  function IconButton({ label, className, children, type = 'button', ...props }, ref) {
    return (
      <button
        ref={ref}
        type={type}
        aria-label={label}
        title={label}
        className={cn(
          'inline-flex size-8 items-center justify-center rounded-ws-sm text-ws-ink-2 transition-colors hover:bg-ws-hover hover:text-ws-ink disabled:opacity-40',
          className,
        )}
        {...props}
      >
        {children}
      </button>
    )
  },
)

export function Spinner({ className }: { className?: string }) {
  return (
    <span
      aria-hidden="true"
      className={cn('inline-block size-3.5 animate-spin rounded-full border-[1.5px] border-current border-r-transparent', className)}
    />
  )
}
