import { useEffect, useMemo, useRef, useState } from 'react'
import {
  closestCorners,
  DndContext,
  DragOverlay,
  KeyboardSensor,
  PointerSensor,
  TouchSensor,
  useDroppable,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragOverEvent,
  type DragStartEvent,
} from '@dnd-kit/core'
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { Lock, Plus } from 'lucide-react'
import { useNavigate } from 'react-router'
import { cn } from '@/shared/utils/cn'
import { toast } from '@/shared/stores/toast.store'
import { Dot, IconButton, Initials } from '../ui'
import type { BoardColumn, Task } from '../lib/types'
import { DueMark, PriorityMark, Tags, TaskSignals } from './TaskBits'

type Lanes = Record<string, string[]>

function buildLanes(columns: BoardColumn[], tasks: Task[]): Lanes {
  const lanes: Lanes = Object.fromEntries(columns.map((column) => [column.id, [] as string[]]))
  ;[...tasks]
    .sort((a, b) => a.position - b.position)
    .forEach((task) => {
      const lane = task.status?.id
      if (lane && lanes[lane]) lanes[lane].push(task.id)
    })

  return lanes
}

function laneOf(lanes: Lanes, id: string): string | undefined {
  if (id in lanes) return id

  return Object.keys(lanes).find((lane) => lanes[lane].includes(id))
}

/**
 * Kanban configurável. A movimentação é otimista: a UI muda na hora e a
 * API (que valida permissões e regras) confirma ou desfaz.
 */
export function KanbanBoard({
  columns,
  tasks,
  canMove,
  onMove,
  onAdd,
  showProject = false,
}: {
  columns: BoardColumn[]
  tasks: Task[]
  canMove: boolean
  /**
   * afterId: tarefa logo acima na coluna exibida (null = topo). A ordem é
   * global por coluna; em visões filtradas o índice exibido não basta.
   */
  onMove: (taskId: string, columnId: string, position: number, afterId: string | null) => Promise<unknown>
  onAdd?: (columnId: string) => void
  showProject?: boolean
}) {
  const [lanes, setLanes] = useState<Lanes>(() => buildLanes(columns, tasks))
  const [activeId, setActiveId] = useState<string | null>(null)
  const origin = useRef<{ lane: string; index: number } | null>(null)

  useEffect(() => {
    if (!activeId) setLanes(buildLanes(columns, tasks))
  }, [columns, tasks, activeId])

  const byId = useMemo(() => new Map(tasks.map((task) => [task.id, task])), [tasks])

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(TouchSensor, { activationConstraint: { delay: 220, tolerance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const onDragStart = ({ active }: DragStartEvent) => {
    const id = String(active.id)
    const lane = laneOf(lanes, id)
    origin.current = lane ? { lane, index: lanes[lane].indexOf(id) } : null
    setActiveId(id)
  }

  const onDragOver = ({ active, over }: DragOverEvent) => {
    if (!over) return
    const id = String(active.id)
    const from = laneOf(lanes, id)
    const to = laneOf(lanes, String(over.id))
    if (!from || !to || from === to) return

    setLanes((current) => {
      const target = [...current[to]]
      const overIndex = target.indexOf(String(over.id))
      target.splice(overIndex >= 0 ? overIndex : target.length, 0, id)

      return { ...current, [from]: current[from].filter((item) => item !== id), [to]: target }
    })
  }

  const onDragEnd = async ({ active, over }: DragEndEvent) => {
    const id = String(active.id)
    const start = origin.current
    setActiveId(null)
    origin.current = null
    if (!over || !start) return

    const lane = laneOf(lanes, id)
    if (!lane) return

    let next = lanes
    const overIndex = lanes[lane].indexOf(String(over.id))
    const currentIndex = lanes[lane].indexOf(id)
    if (overIndex >= 0 && overIndex !== currentIndex) {
      next = { ...lanes, [lane]: arrayMove(lanes[lane], currentIndex, overIndex) }
      setLanes(next)
    }

    const index = next[lane].indexOf(id)
    if (lane === start.lane && index === start.index) return

    try {
      await onMove(id, lane, index, index > 0 ? next[lane][index - 1] : null)
    } catch {
      setLanes(buildLanes(columns, tasks))
      toast.error('Não foi possível mover a tarefa', 'A alteração foi desfeita.')
    }
  }

  const active = activeId ? byId.get(activeId) : undefined

  return (
    <DndContext
      sensors={sensors}
      collisionDetection={closestCorners}
      onDragStart={canMove ? onDragStart : undefined}
      onDragOver={canMove ? onDragOver : undefined}
      onDragEnd={canMove ? onDragEnd : undefined}
      onDragCancel={() => {
        setActiveId(null)
        setLanes(buildLanes(columns, tasks))
      }}
    >
      <div className="ws-scroll-x -mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-4 md:mx-0 md:snap-none md:px-0">
        {columns.map((column) => (
          <Lane key={column.id} column={column} ids={lanes[column.id] ?? []} byId={byId} canMove={canMove} onAdd={onAdd} showProject={showProject} />
        ))}
      </div>
      <DragOverlay dropAnimation={{ duration: 160, easing: 'ease-out' }}>
        {active ? <Card task={active} dragging showProject={showProject} /> : null}
      </DragOverlay>
    </DndContext>
  )
}

function Lane({
  column,
  ids,
  byId,
  canMove,
  onAdd,
  showProject,
}: {
  column: BoardColumn
  ids: string[]
  byId: Map<string, Task>
  canMove: boolean
  onAdd?: (columnId: string) => void
  showProject: boolean
}) {
  const { setNodeRef, isOver } = useDroppable({ id: column.id })
  const closed = column.kind === 'done' || column.kind === 'cancelled'

  return (
    <section
      aria-label={`Coluna ${column.name}`}
      className={cn(
        'flex w-[84vw] shrink-0 snap-start flex-col rounded-ws-lg bg-ws-hover/70 transition-colors sm:w-[300px]',
        isOver && 'bg-ws-accent-tint',
      )}
    >
      <header className="flex items-center gap-2 px-3 pt-3 pb-1">
        <Dot color={column.color} />
        <h3 className="flex-1 truncate text-[12.5px] font-semibold tracking-wide text-ws-ink uppercase" title={`${column.name} · ${column.kind_label}`}>
          {column.name}
        </h3>
        <span className="tabular-nums text-[11px] text-ws-ink-3">{ids.length}</span>
        {onAdd && !closed && (
          <IconButton label={`Nova tarefa em ${column.name}`} className="size-6" onClick={() => onAdd(column.id)}>
            <Plus className="size-3.5" />
          </IconButton>
        )}
      </header>
      <SortableContext id={column.id} items={ids} strategy={verticalListSortingStrategy}>
        <div ref={setNodeRef} className="flex min-h-24 flex-1 flex-col gap-2 p-2">
          {ids.map((id) => {
            const task = byId.get(id)

            return task ? <SortableCard key={id} task={task} disabled={!canMove} showProject={showProject} /> : null
          })}
          {ids.length === 0 && <p className="px-1 py-6 text-center text-[12px] text-ws-ink-3">Arraste tarefas para cá</p>}
        </div>
      </SortableContext>
    </section>
  )
}

function SortableCard({ task, disabled, showProject }: { task: Task; disabled: boolean; showProject: boolean }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: task.id, disabled })

  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn(isDragging && 'opacity-30')}
      {...attributes}
      {...listeners}
    >
      <Card task={task} showProject={showProject} />
    </div>
  )
}

function Card({ task, dragging, showProject }: { task: Task; dragging?: boolean; showProject?: boolean }) {
  const navigate = useNavigate()
  const closed = task.status?.kind === 'done' || task.status?.kind === 'cancelled'

  return (
    <article
      role="button"
      tabIndex={-1}
      onClick={() => !dragging && navigate(`/w/tasks/${task.id}`)}
      className={cn(
        'cursor-pointer rounded-ws bg-ws-panel p-3 text-left shadow-ws-sm transition-shadow hover:shadow-ws',
        dragging && 'rotate-[1.5deg] cursor-grabbing shadow-ws-pop',
      )}
    >
      {showProject && task.project && (
        <p className="mb-1.5 truncate text-[11px] font-medium text-ws-ink-3" title={task.project.name}>
          {task.project.name}
        </p>
      )}
      <div className="flex items-start gap-2">
        <PriorityMark priority={task.priority} />
        <p className={cn('flex-1 text-[13px] leading-snug text-ws-ink', closed && 'text-ws-ink-3 line-through')}>{task.title}</p>
        {task.visibility === 'internal' && <Lock className="mt-0.5 size-3 shrink-0 text-ws-ink-3" aria-label="Interna" />}
      </div>
      {task.tags && task.tags.length > 0 && (
        <div className="mt-2">
          <Tags tags={task.tags} />
        </div>
      )}
      <div className="mt-2.5 flex items-center gap-2">
        {task.assignee ? <Initials name={task.assignee.name} size="xs" /> : <span className="size-5" />}
        <TaskSignals task={task} />
        <span className="ml-auto">
          <DueMark due={task.due_at} closed={closed} />
        </span>
      </div>
    </article>
  )
}
