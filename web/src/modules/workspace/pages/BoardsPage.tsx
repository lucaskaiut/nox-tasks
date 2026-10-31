import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { closestCenter, DndContext, KeyboardSensor, PointerSensor, TouchSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core'
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { Columns3, GripVertical, Plus, Trash2 } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { Button, Confirm, Dot, Empty, ErrorBlock, Field, IconButton, LoadingBlock, PageHeader, Panel, Pill, SelectInput, Sheet, TextArea, TextInput } from '../ui'
import { useBoard, useBoards, useWsMutation } from '../hooks/useWorkspace'
import { COLUMN_COLORS, COLUMN_KINDS } from '../lib/format'
import type { BoardColumn, ColumnKind } from '../lib/types'
import { wsApi } from '../services/workspace.api'

export default function BoardsPage() {
  const boards = useBoards()
  const navigate = useNavigate()
  const [creating, setCreating] = useState(false)
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const create = useWsMutation((body: Record<string, unknown>) => wsApi.boards.create(body), 'Quadro criado')

  return (
    <div className="ws-rise">
      <PageHeader
        eyebrow="Configuração"
        title="Quadros Kanban"
        description="Cada projeto usa um quadro. Colunas, cores e o significado de cada etapa são configuráveis."
        actions={
          <Button variant="primary" icon={<Plus className="size-3.5" />} onClick={() => setCreating(true)}>
            Novo quadro
          </Button>
        }
      />

      {boards.isLoading ? (
        <LoadingBlock />
      ) : boards.data?.data.length ? (
        <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {boards.data.data.map((board) => (
            <li key={board.id}>
              <Link to={`/w/boards/${board.id}`} className="block rounded-ws bg-ws-panel p-4 shadow-ws transition-shadow hover:shadow-ws-pop">
                <div className="flex items-start justify-between gap-2">
                  <p className="text-[14px] font-medium text-ws-ink">{board.name}</p>
                  <span className="tabular-nums text-[11px] text-ws-ink-3">{board.projects_count ?? 0} proj.</span>
                </div>
                {board.description && <p className="mt-1 line-clamp-2 text-[12.5px] text-ws-ink-2">{board.description}</p>}
                <div className="mt-4 flex gap-1">
                  {board.columns?.map((column) => (
                    <span key={column.id} className="h-1.5 flex-1 rounded-full" style={{ background: column.color }} title={column.name} />
                  ))}
                </div>
                <p className="mt-2 truncate text-[12px] text-ws-ink-3">{board.columns?.map((c) => c.name).join(' → ')}</p>
              </Link>
            </li>
          ))}
        </ul>
      ) : (
        <Empty icon={Columns3} title="Nenhum quadro" description="Quadros também são criados automaticamente com cada projeto novo." />
      )}

      <Sheet
        open={creating}
        onClose={() => setCreating(false)}
        eyebrow="Novo quadro"
        title="Criar quadro"
        footer={
          <Button
            variant="primary"
            loading={create.isPending}
            disabled={!name.trim()}
            onClick={async () => {
              const board = await create.mutateAsync({ name, description: description || null })
              setCreating(false)
              setName('')
              setDescription('')
              navigate(`/w/boards/${board.id}`)
            }}
          >
            Criar quadro
          </Button>
        }
      >
        <div className="flex flex-col gap-4">
          <Field label="Nome" htmlFor="b-name">
            <TextInput id="b-name" autoFocus value={name} onChange={(e) => setName(e.target.value)} />
          </Field>
          <Field label="Descrição" htmlFor="b-desc">
            <TextArea id="b-desc" value={description} onChange={(e) => setDescription(e.target.value)} rows={3} />
          </Field>
          <p className="text-[12.5px] text-ws-ink-3">O quadro começa com colunas padrão (A fazer, Em andamento, Aguardando retorno, Concluído), que você pode alterar em seguida.</p>
        </div>
      </Sheet>
    </div>
  )
}

function ColumnRow({ column, boardId, onDelete }: { column: BoardColumn; boardId: string; onDelete: (column: BoardColumn) => void }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: column.id })
  const [name, setName] = useState(column.name)
  const update = useWsMutation((body: Record<string, unknown>) => wsApi.boards.updateColumn(boardId, column.id, body))

  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn('grid grid-cols-[auto_1fr_auto] items-center gap-3 rounded-ws-sm bg-ws-panel px-3 py-2.5 md:grid-cols-[auto_minmax(0,1fr)_200px_auto_auto]', isDragging && 'relative z-10 rounded-ws shadow-ws-pop')}
    >
      <button type="button" className="cursor-grab touch-none text-ws-ink-3 hover:text-ws-ink" aria-label={`Reordenar ${column.name}`} {...attributes} {...listeners}>
        <GripVertical className="size-4" />
      </button>
      <div className="flex items-center gap-2">
        <Dot color={column.color} className="size-3" />
        <TextInput
          value={name}
          onChange={(e) => setName(e.target.value)}
          onBlur={() => name.trim() && name !== column.name && update.mutate({ name: name.trim() })}
          aria-label="Nome da coluna"
          className="h-8 bg-transparent shadow-none hover:bg-ws-sunken hover:shadow-none"
        />
      </div>
      <SelectInput
        aria-label="Tipo da coluna"
        value={column.kind}
        onChange={(e) => update.mutate({ kind: e.target.value as ColumnKind })}
        className="col-span-3 h-8 md:col-span-1"
      >
        {COLUMN_KINDS.map((kind) => (
          <option key={kind.value} value={kind.value}>
            {kind.label}
          </option>
        ))}
      </SelectInput>
      <div className="col-span-2 flex gap-1 md:col-span-1" role="radiogroup" aria-label="Cor">
        {COLUMN_COLORS.map((color) => (
          <button
            key={color}
            type="button"
            role="radio"
            aria-checked={column.color === color}
            aria-label={`Cor ${color}`}
            onClick={() => update.mutate({ color })}
            className={cn('size-4 rounded-full transition-transform', column.color === color ? 'scale-125 shadow-ws-sm' : 'opacity-70 hover:opacity-100')}
            style={{ background: color }}
          />
        ))}
      </div>
      <IconButton label={`Excluir coluna ${column.name}`} onClick={() => onDelete(column)}>
        <Trash2 className="size-3.5" />
      </IconButton>
    </li>
  )
}

export function BoardPage() {
  const { id = '' } = useParams()
  const navigate = useNavigate()
  const board = useBoard(id)
  const [order, setOrder] = useState<string[] | null>(null)
  const [deleting, setDeleting] = useState<BoardColumn | null>(null)
  const [moveTo, setMoveTo] = useState('')
  const [removingBoard, setRemovingBoard] = useState(false)
  const [details, setDetails] = useState<{ name: string; description: string } | null>(null)

  const add = useWsMutation(() => wsApi.boards.addColumn(id, { name: 'Nova coluna', kind: 'open' }))
  const reorder = useWsMutation((args: { columnId: string; position: number }) => wsApi.boards.moveColumn(id, args.columnId, args.position))
  const removeColumn = useWsMutation((args: { columnId: string; moveTo?: string }) => wsApi.boards.removeColumn(id, args.columnId, args.moveTo), 'Coluna removida')
  const updateBoard = useWsMutation((body: Record<string, unknown>) => wsApi.boards.update(id, body), 'Quadro atualizado')
  const removeBoard = useWsMutation(() => wsApi.boards.remove(id), 'Quadro removido')

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(TouchSensor, { activationConstraint: { delay: 180, tolerance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  if (board.isLoading) return <LoadingBlock />
  if (board.isError || !board.data) return <ErrorBlock message="Quadro não encontrado." />

  const columns = board.data.columns ?? []
  const ids = order ?? columns.map((c) => c.id)
  const byId = new Map(columns.map((c) => [c.id, c]))

  const onDragEnd = async ({ active, over }: DragEndEvent) => {
    if (!over || active.id === over.id) return
    const next = arrayMove(ids, ids.indexOf(String(active.id)), ids.indexOf(String(over.id)))
    setOrder(next)
    try {
      await reorder.mutateAsync({ columnId: String(active.id), position: next.indexOf(String(active.id)) })
    } finally {
      setOrder(null)
    }
  }

  return (
    <div className="ws-rise">
      <PageHeader
        eyebrow="Quadro Kanban"
        title={board.data.name}
        description={board.data.description}
        meta={<Pill>{board.data.projects_count ?? 0} projeto(s) usando este quadro</Pill>}
        actions={
          <>
            <Button onClick={() => setDetails({ name: board.data!.name, description: board.data!.description ?? '' })}>Renomear</Button>
            <Button variant="danger" onClick={() => setRemovingBoard(true)} disabled={(board.data.projects_count ?? 0) > 0}>
              Excluir
            </Button>
          </>
        }
      />

      <Panel
        eyebrow="Fluxo"
        title="Colunas"
        bodyClassName="p-1.5 pt-0"
        actions={
          <Button size="sm" icon={<Plus className="size-3.5" />} loading={add.isPending} onClick={() => add.mutate(undefined)}>
            Coluna
          </Button>
        }
      >
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
          <SortableContext items={ids} strategy={verticalListSortingStrategy}>
            <ul>
              {ids.map((columnId) => {
                const column = byId.get(columnId)
                return column ? <ColumnRow key={column.id} column={column} boardId={id} onDelete={setDeleting} /> : null
              })}
            </ul>
          </SortableContext>
        </DndContext>
      </Panel>

      <dl className="mt-4 grid gap-3 text-[12.5px] sm:grid-cols-2 lg:grid-cols-4">
        {COLUMN_KINDS.map((kind) => (
          <div key={kind.value} className="rounded-ws bg-ws-panel px-3 py-2.5 shadow-ws-sm">
            <dt className="font-medium text-ws-ink">{kind.label}</dt>
            <dd className="text-ws-ink-3">{kind.hint}</dd>
          </div>
        ))}
      </dl>

      <Confirm
        open={Boolean(deleting)}
        destructive
        title={`Excluir a coluna "${deleting?.name}"?`}
        description="Se houver tarefas nela, escolha para onde movê-las."
        confirmLabel="Excluir coluna"
        loading={removeColumn.isPending}
        onClose={() => {
          setDeleting(null)
          setMoveTo('')
        }}
        onConfirm={async () => {
          await removeColumn.mutateAsync({ columnId: deleting!.id, moveTo: moveTo || undefined })
          setDeleting(null)
          setMoveTo('')
        }}
      >
        <Field label="Mover tarefas para">
          <SelectInput value={moveTo} onChange={(e) => setMoveTo(e.target.value)}>
            <option value="">(a coluna está vazia)</option>
            {columns
              .filter((c) => c.id !== deleting?.id)
              .map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
          </SelectInput>
        </Field>
      </Confirm>

      <Confirm
        open={removingBoard}
        destructive
        title="Excluir quadro?"
        description="Só é possível excluir quadros que não estão associados a projetos."
        confirmLabel="Excluir"
        onClose={() => setRemovingBoard(false)}
        onConfirm={async () => {
          await removeBoard.mutateAsync(undefined)
          navigate('/w/boards')
        }}
      />

      <Sheet
        open={Boolean(details)}
        onClose={() => setDetails(null)}
        title="Editar quadro"
        footer={
          <Button
            variant="primary"
            loading={updateBoard.isPending}
            onClick={async () => {
              await updateBoard.mutateAsync({ name: details!.name, description: details!.description || null })
              setDetails(null)
            }}
          >
            Salvar
          </Button>
        }
      >
        {details && (
          <div className="flex flex-col gap-4">
            <Field label="Nome" htmlFor="bd-name">
              <TextInput id="bd-name" value={details.name} onChange={(e) => setDetails({ ...details, name: e.target.value })} />
            </Field>
            <Field label="Descrição" htmlFor="bd-desc">
              <TextArea id="bd-desc" value={details.description} onChange={(e) => setDetails({ ...details, description: e.target.value })} />
            </Field>
          </div>
        )}
      </Sheet>
    </div>
  )
}
