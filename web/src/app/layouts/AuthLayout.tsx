import { Suspense } from 'react'
import { Outlet } from 'react-router'
import { Moon, Sun } from 'lucide-react'
import { Loading } from '@/shared/design-system'
import { resolveTheme, useThemeStore } from '@/shared/stores/theme.store'

function ThemeSwitch() {
  const theme = useThemeStore((state) => state.theme)
  const setTheme = useThemeStore((state) => state.setTheme)
  const dark = resolveTheme(theme) === 'dark'

  return (
    <button
      type="button"
      onClick={() => setTheme(dark ? 'light' : 'dark')}
      aria-label={dark ? 'Usar tema claro' : 'Usar tema escuro'}
      className="inline-flex size-8 items-center justify-center rounded-ws-sm text-ws-ink-2 hover:bg-ws-hover hover:text-ws-ink"
    >
      {dark ? <Sun className="size-4" /> : <Moon className="size-4" />}
    </button>
  )
}

function BrandMark({ className }: { className?: string }) {
  return (
    <span
      className={`flex size-8 items-center justify-center rounded-ws bg-ws-ink tabular-nums text-[13px] font-medium text-ws-canvas ${className ?? ''}`}
    >
      n/
    </span>
  )
}

/** Miniatura decorativa de um quadro Kanban, usada no painel de apresentação. */
function BoardPreview() {
  const columns = [
    { title: 'A fazer', cards: [72, 54, 64] },
    { title: 'Em andamento', cards: [60, 80] },
    { title: 'Concluído', cards: [48, 66, 40] },
  ]

  return (
    <div aria-hidden="true" className="grid grid-cols-3 gap-3">
      {columns.map((column, columnIndex) => (
        <div key={column.title} className="flex flex-col gap-2 rounded-ws-lg bg-white/[0.04] p-2.5">
          <div className="flex items-center gap-1.5 px-0.5">
            <span
              className="size-1.5 rounded-full"
              style={{ background: ['#e7a94b', '#86a9e0', '#6cc3a0'][columnIndex] }}
            />
            <span className="text-[10.5px] tracking-wide text-white/50 uppercase">{column.title}</span>
          </div>
          {column.cards.map((width, cardIndex) => (
            <div key={cardIndex} className="flex flex-col gap-1.5 rounded-ws bg-white/[0.07] p-2.5">
              <span className="h-1.5 rounded-full bg-white/30" style={{ width: `${width}%` }} />
              <span className="h-1.5 w-2/5 rounded-full bg-white/15" />
            </div>
          ))}
        </div>
      ))}
    </div>
  )
}

export function AuthLayout() {
  return (
    <div className="ws flex min-h-dvh">
      <aside className="relative hidden w-[46%] max-w-[640px] flex-col justify-between overflow-hidden bg-[#13211c] p-10 text-white lg:flex">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute -top-32 -right-32 size-[420px] rounded-full bg-[#6cc3a0]/15 blur-3xl"
        />
        <div className="relative flex items-center gap-2.5">
          <span className="flex size-8 items-center justify-center rounded-ws bg-white tabular-nums text-[13px] font-medium text-[#13211c]">
            n/
          </span>
          <span className="text-[13px] font-semibold tracking-tight">nox tasks</span>
        </div>

        <div className="relative flex flex-col gap-8">
          <div className="max-w-sm">
            <h2 className="text-[26px] leading-tight font-semibold tracking-tight">
              Projetos, tarefas e clientes no mesmo fluxo.
            </h2>
            <p className="mt-3 text-[14px] leading-relaxed text-white/60">
              Acompanhe o andamento de cada entrega, organize quadros e mantenha seus clientes por dentro.
            </p>
          </div>
          <BoardPreview />
        </div>

        <p className="relative text-[12px] text-white/40">© {new Date().getFullYear()} Nox</p>
      </aside>

      <main className="relative flex flex-1 flex-col px-4 py-6 sm:px-8">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2.5 lg:invisible">
            <BrandMark />
            <span className="text-[13px] font-semibold text-ws-ink">nox tasks</span>
          </div>
          <ThemeSwitch />
        </div>

        <div className="flex flex-1 items-center justify-center py-10">
          <Suspense fallback={<Loading />}>
            <Outlet />
          </Suspense>
        </div>

        <p className="text-center text-[12px] text-ws-ink-3 lg:hidden">© {new Date().getFullYear()} Nox</p>
      </main>
    </div>
  )
}
