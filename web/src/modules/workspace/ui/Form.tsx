import { forwardRef, useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react'
import { cn } from '@/shared/utils/cn'

const control =
  'w-full rounded-ws border border-ws-line-strong bg-ws-panel px-2.5 text-[13.5px] text-ws-ink placeholder:text-ws-ink-3 transition-colors hover:border-ws-ink-3 focus:border-ws-accent focus:outline-none aria-[invalid=true]:border-ws-danger disabled:opacity-60'

export function Field({
  label,
  hint,
  error,
  children,
  className,
  htmlFor,
}: {
  label: string
  hint?: string
  error?: string
  children: ReactNode
  className?: string
  htmlFor?: string
}) {
  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      <label htmlFor={htmlFor} className="text-[12.5px] font-medium text-ws-ink-2">
        {label}
      </label>
      {children}
      {error ? (
        <p className="text-[12px] text-ws-danger">{error}</p>
      ) : hint ? (
        <p className="text-[12px] text-ws-ink-3">{hint}</p>
      ) : null}
    </div>
  )
}

export const TextInput = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function TextInput(
  { className, ...props },
  ref,
) {
  return <input ref={ref} className={cn(control, 'h-9', className)} {...props} />
})

export const TextArea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(function TextArea(
  { className, rows = 4, ...props },
  ref,
) {
  return <textarea ref={ref} rows={rows} className={cn(control, 'py-2 leading-relaxed', className)} {...props} />
})

export const SelectInput = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(function SelectInput(
  { className, children, ...props },
  ref,
) {
  return (
    <select ref={ref} className={cn(control, 'h-9 pr-7', className)} {...props}>
      {children}
    </select>
  )
})

/** Controle segmentado (ex.: visibilidade interna/compartilhada). */
export function Segmented<T extends string>({
  value,
  onChange,
  options,
  label,
}: {
  value: T
  onChange: (value: T) => void
  options: Array<{ value: T; label: string; icon?: ReactNode }>
  label: string
}) {
  const name = useId()

  return (
    <div role="radiogroup" aria-label={label} className="inline-flex rounded-ws border border-ws-line-strong bg-ws-sunken p-0.5">
      {options.map((option) => (
        <label
          key={option.value}
          className={cn(
            'flex cursor-pointer items-center gap-1.5 rounded-[2px] px-2.5 py-1 text-[12.5px] transition-colors',
            value === option.value ? 'bg-ws-panel text-ws-ink font-medium' : 'text-ws-ink-2 hover:text-ws-ink',
          )}
        >
          <input
            type="radio"
            name={name}
            value={option.value}
            checked={value === option.value}
            onChange={() => onChange(option.value)}
            className="sr-only"
          />
          {option.icon}
          {option.label}
        </label>
      ))}
    </div>
  )
}

export function Checkbox({ checked, onChange, label }: { checked: boolean; onChange: (checked: boolean) => void; label: ReactNode }) {
  return (
    <label className="flex cursor-pointer items-center gap-2 text-[13px] text-ws-ink">
      <input
        type="checkbox"
        checked={checked}
        onChange={(event) => onChange(event.target.checked)}
        className="size-3.5 rounded-[2px] accent-[var(--ws-accent)]"
      />
      {label}
    </label>
  )
}
