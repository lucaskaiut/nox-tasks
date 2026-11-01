import { useState } from 'react'
import { Link } from 'react-router'
import { closestCenter, DndContext, KeyboardSensor, PointerSensor, TouchSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core'
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { ArrowLeft, GripVertical, Plus, Settings2, Trash2 } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { Permission } from '@/shared/constants/permissions'
import { Button, ButtonLink, Confirm, Dot, ErrorBlock, Field, IconButton, LoadingBlock, PageHeader, Panel, Segmented, SelectInput, Sheet, TextArea, TextInput } from '../ui'
import { useBoard, useBoardConfig, useProfile, useProjects, useWsMutation } from '../hooks/useWorkspace'
import { COLUMN_COLORS, COLUMN_KINDS } from '../lib/format'
import type { BoardColumn, ColumnKind } from '../lib/types'
import { wsApi } from '../services/workspace.api'
import { KanbanBoard } from '../components/KanbanBoard'
import { TaskFormSheet } from '../components/TaskForm'

/**
 * Quadro Kanban geral: um único quadro com as tarefas de todos os projetos
 * visíveis ao usuário. O filtro por projeto apenas restringe a visão.
 */
export default function BoardPage() {
  const { canManage, can, isClient } = useProfile()
  const [project, setProject] = useState('')
  const [scope, setScope] = useState<'all' | 'me'>('all')
  const [newTaskColumn, setNewTaskColumn] = useState<string | null>(null)

  const params: Record<string, unknown> = {}
  if (project) params.project = project
  if (scope === 'me') params.assignee = 'me'

  const board = useBoard(params)
  const projects = useProjects({ per_page: 100 })
  const move = useWsMutation((args: { id: string; column: string; after: string | null }) =>
    wsApi.tasks.move(args.id, args.after ? { column_id: args.column, after_id: args.after } : { column_id: args.column, position: 0 }),
  )

  if (board.isLoading) return <LoadingBlock rows={6} />
  if (board.isError || !board.data) return <ErrorBlock message="Não foi possível carregar o quadro." onRetry={() => board.refetch()} />

  const selected = projects.data?.data.find((item) => item.id === project)
  const canMove = canManage(Permission.TASK_MOVE)
  const canAdd = Boolean(selected) && !selected?.is_archived && can(Permission.TASK_CREATE)

  return (
    <div className="ws-rise">
      <PageHeader
        eyebrow="Kanban"
        title={board.data.board.name}
        description={board.data.board.description ?? 'Todas as tarefas, de todos os projetos, em um só fluxo.'}
        actions={
          canManage(Permission.BOARD_MANAGE) && (
            <ButtonLink to="/w/board/settings" icon={<Settings2 className="size-3.5" />}>
              Configurar colunas
            </ButtonLink>
          )
        }
      />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <SelectInput aria-label="Filtrar por projeto" value={project} onChange={(e) => setProject(e.target.value)} className="h-8 w-auto min-w-52">
          <option value="">Todos os projetos</option>
          {projects.data?.data.map((item) => (
            <option key={item.id} value={item.id}>
              {item.name}
            </option>
          ))}
        </SelectInput>
        {!isClient && (
          <Segmented
            label="Responsável"
            value={scope}
            onChange={setScope}
            options={[
              { value: 'all', label: 'Todas' },
              { value: 'me', label: 'Minhas' },
            ]}
          />
        )}
        <span className="tabular-nums text-[12px] text-ws-ink-3">{board.data.tasks.length} tarefa(s)</span>
        {!canMove && <span className="text-[12px] text-ws-ink-3">Visualização somente leitura.</span>}
      </div>

      <KanbanBoard
        columns={board.data.board.columns ?? []}
        tasks={board.data.tasks}
        canMove={canMove}
        showProject={!project}
        onMove={(id, column, _position, after) => move.mutateAsync({ id, column, after })}
        onAdd={canAdd ? (columnId) => setNewTaskColumn(canManage(Permission.TASK_UPDATE) ? columnId : '') : undefined}
      />

      {!canAdd && can(Permission.TASK_CREATE) && (
        <p className="mt-1 text-[12px] text-ws-ink-3">Selecione um projeto para criar tarefas direto no quadro.</p>
      )}

      <TaskFormSheet
        open={newTaskColumn !== null}
        onClose={() => setNewTaskColumn(null)}
        projectId={project || undefined}
        columns={board.data.board.columns}
        defaultColumnId={newTaskColumn || undefined}
      />
    </div>
  )
}

function ColumnRow({ column, onDelete }: { column: BoardColumn; onDelete: (column: BoardColumn) => void }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: column.id })
  const [name, setName] = useState(column.name)
  const update = useWsMutation((body: Record<string, unknown>) => wsApi.board.updateColumn(column.id, body))

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

/** Configuração do quadro geral: nome, colunas, cores e significado de cada etapa. */
export function BoardSettingsPage() {
  const board = useBoardConfig()
  const [order, setOrder] = useState<string[] | null>(null)
  const [deleting, setDeleting] = useState<BoardColumn | null>(null)
  const [moveTo, setMoveTo] = useState('')
  const [details, setDetails] = useState<{ name: string; description: string } | null>(null)

  const add = useWsMutation(() => wsApi.board.addColumn({ name: 'Nova coluna', kind: 'open' }))
  const reorder = useWsMutation((args: { columnId: string; position: number }) => wsApi.board.moveColumn(args.columnId, args.position))
  const removeColumn = useWsMutation((args: { columnId: string; moveTo?: string }) => wsApi.board.removeColumn(args.columnId, args.moveTo), 'Coluna removida')
  const updateBoard = useWsMutation((body: Record<string, unknown>) => wsApi.board.update(body), 'Quadro atualizado')

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(TouchSensor, { activationConstraint: { delay: 180, tolerance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  if (board.isLoading) return <LoadingBlock />
  if (board.isError || !board.data) return <ErrorBlock message="Não foi possível carregar o quadro." onRetry={() => board.refetch()} />

  const info = board.data
  const columns = info.columns ?? []
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
      <Link to="/w/board" className="mb-3 inline-flex items-center gap-1.5 text-[12.5px] text-ws-ink-2 hover:text-ws-ink">
        <ArrowLeft className="size-3.5" />
        Voltar ao quadro
      </Link>
      <PageHeader
        eyebrow="Configuração do quadro"
        title={info.name}
        description={info.description ?? 'Quadro único, compartilhado por todos os projetos. As colunas valem para todas as tarefas.'}
        actions={<Button onClick={() => setDetails({ name: info.name, description: info.description ?? '' })}>Editar nome e descrição</Button>}
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
                return column ? <ColumnRow key={column.id} column={column} onDelete={setDeleting} /> : null
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
