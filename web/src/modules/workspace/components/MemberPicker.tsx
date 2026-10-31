import { useMemo, useState } from 'react'
import { Check, ChevronDown } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { Initials, Menu, TextInput } from '../ui'
import type { Member } from '../lib/types'

/** Seleção de um ou vários profissionais. */
export function MemberPicker({
  members,
  value,
  onChange,
  multiple,
  placeholder = 'Selecionar',
  id,
}: {
  members: Member[]
  value: string[]
  onChange: (value: string[]) => void
  multiple?: boolean
  placeholder?: string
  id?: string
}) {
  const [query, setQuery] = useState('')
  const selected = members.filter((member) => value.includes(member.id))
  const filtered = useMemo(
    () => members.filter((member) => member.name.toLowerCase().includes(query.toLowerCase())),
    [members, query],
  )

  const toggle = (memberId: string, close: () => void) => {
    if (!multiple) {
      onChange(value[0] === memberId ? [] : [memberId])
      close()
      return
    }
    onChange(value.includes(memberId) ? value.filter((v) => v !== memberId) : [...value, memberId])
  }

  return (
    <Menu
      align="start"
      className="w-72"
      trigger={({ toggle: open }) => (
        <button
          id={id}
          type="button"
          onClick={open}
          className="flex h-9 w-full items-center gap-2 rounded-ws border border-ws-line-strong bg-ws-panel px-2.5 text-left text-[13.5px] hover:border-ws-ink-3"
        >
          {selected.length === 0 ? (
            <span className="flex-1 text-ws-ink-3">{placeholder}</span>
          ) : (
            <span className="flex min-w-0 flex-1 items-center gap-1.5">
              {selected.slice(0, 3).map((member) => (
                <Initials key={member.id} name={member.name} size="xs" />
              ))}
              <span className="truncate text-ws-ink">
                {selected.length === 1 ? selected[0].name : `${selected.length} pessoas`}
              </span>
            </span>
          )}
          <ChevronDown className="size-3.5 text-ws-ink-3" aria-hidden="true" />
        </button>
      )}
    >
      {(close) => (
        <div>
          <div className="px-2 pb-1">
            <TextInput autoFocus placeholder="Buscar pessoa…" value={query} onChange={(e) => setQuery(e.target.value)} className="h-8" />
          </div>
          <div className="max-h-60 overflow-y-auto">
            {filtered.length === 0 && <p className="px-3 py-2 text-[12.5px] text-ws-ink-3">Ninguém encontrado</p>}
            {filtered.map((member) => {
              const active = value.includes(member.id)

              return (
                <button
                  key={member.id}
                  type="button"
                  onClick={() => toggle(member.id, close)}
                  className={cn('flex w-full items-center gap-2 px-3 py-1.5 text-left text-[13px] hover:bg-ws-hover', active && 'text-ws-accent')}
                >
                  <Initials name={member.name} size="xs" />
                  <span className="flex-1 truncate">{member.name}</span>
                  {active && <Check className="size-3.5" aria-hidden="true" />}
                </button>
              )
            })}
          </div>
        </div>
      )}
    </Menu>
  )
}
