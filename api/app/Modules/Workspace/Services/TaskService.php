<?php

namespace App\Modules\Workspace\Services;

use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\ColumnKind;
use App\Modules\Workspace\Enums\TaskPriority;
use App\Modules\Workspace\Enums\Visibility;
use App\Modules\Workspace\Models\BoardColumn;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Tag;
use App\Modules\Workspace\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    private const FIELDS = ['title', 'description', 'priority', 'visibility', 'starts_at', 'due_at', 'estimated_hours'];

    /** Campos que um usuário de cliente pode definir ao abrir uma tarefa. */
    private const CLIENT_FIELDS = ['title', 'description', 'priority', 'due_at'];

    private const SORTS = ['due_at', 'priority', 'created_at', 'updated_at', 'title', 'position'];

    public function __construct(
        private readonly BoardService $boards,
        private readonly MemberService $members,
        private readonly ActivityService $activity,
        private readonly WorkspaceNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(User $actor, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->query($actor, $filters)->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Consulta base de listagem. Sempre parte de visibleTo($actor); os filtros
     * do usuário apenas restringem esse conjunto.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Task>
     */
    public function query(User $actor, array $filters = []): Builder
    {
        $search = $filters['search'] ?? null;
        $assignee = $filters['assignee'] ?? null;
        $sort = in_array($filters['sort'] ?? null, self::SORTS, true) ? $filters['sort'] : 'position';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query = $this->withSummary(Task::query()->visibleTo($actor), $actor)
            ->when(filled($filters['project'] ?? null), fn (Builder $q) => $q->whereHas(
                'project',
                fn (Builder $p) => $p->where('uuid', $filters['project']),
            ))
            ->when(filled($filters['client'] ?? null), fn (Builder $q) => $q->whereHas(
                'project.client',
                fn (Builder $c) => $c->where('uuid', $filters['client']),
            ))
            ->when($assignee === 'me', fn (Builder $q) => $q->where('assignee_id', $actor->getKey()))
            ->when($assignee === 'none', fn (Builder $q) => $q->whereNull('assignee_id'))
            ->when(filled($assignee) && ! in_array($assignee, ['me', 'none'], true), fn (Builder $q) => $q->whereHas(
                'assignee',
                fn (Builder $u) => $u->where('uuid', $assignee),
            ))
            ->when(filled($filters['priority'] ?? null), fn (Builder $q) => $q->whereIn('priority', (array) $filters['priority']))
            ->when(filled($filters['column'] ?? null), fn (Builder $q) => $q->whereHas(
                'column',
                fn (Builder $c) => $c->where('uuid', $filters['column']),
            ))
            ->when(filled($filters['state'] ?? null), fn (Builder $q) => $q->whereHas(
                'column',
                fn (Builder $c) => $c->whereIn('kind', $this->kindsFor((string) $filters['state'])),
            ))
            ->when(filled($filters['visibility'] ?? null), fn (Builder $q) => $q->where('visibility', $filters['visibility']))
            ->when(filled($filters['tag'] ?? null), fn (Builder $q) => $q->whereHas(
                'tags',
                fn (Builder $t) => $t->where('name', $filters['tag']),
            ))
            ->when((bool) ($filters['overdue'] ?? false), fn (Builder $q) => $q
                ->whereNull('completed_at')
                ->whereDate('due_at', '<', today())
                ->whereHas('column', fn (Builder $c) => $c->whereNotIn('kind', ColumnKind::closedValues())))
            ->when(filled($filters['due_from'] ?? null), fn (Builder $q) => $q->whereDate('due_at', '>=', $filters['due_from']))
            ->when(filled($filters['due_to'] ?? null), fn (Builder $q) => $q->whereDate('due_at', '<=', $filters['due_to']))
            ->when(filled($search), fn (Builder $q) => $q->where(fn (Builder $s) => $s
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")));

        if ($sort === 'priority') {
            $query->orderByRaw(
                "CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'normal' THEN 2 ELSE 1 END {$direction}",
            );
        } elseif ($sort === 'due_at') {
            $query->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query->orderBy('id');
    }

    public function load(Task $task, User $actor): Task
    {
        $task->load([
            'project:id,uuid,name,client_id,archived_at',
            'project.client:id,uuid,name',
            'column',
            'assignee:id,uuid,name,email',
            'creator:id,uuid,name',
            'participants:id,uuid,name,email',
            'tags',
            'subtasks.assignee:id,uuid,name',
        ]);

        $task->loadCount([
            'comments' => fn (Builder $q) => $q->visibleTo($actor),
            'attachments' => fn (Builder $q) => $q->visibleTo($actor),
        ]);

        return $task;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, Project $project, array $data): Task
    {
        return DB::transaction(function () use ($actor, $project, $data): Task {
            if ($actor->isClientUser()) {
                // Tarefas abertas pelo cliente são sempre compartilhadas e sem atribuição.
                $data = [...Arr::only($data, [...self::CLIENT_FIELDS, 'tags']), 'visibility' => Visibility::SHARED->value];
            }

            $task = new Task(Arr::only($data, self::FIELDS));
            $task->priority ??= TaskPriority::NORMAL;
            $task->visibility ??= Visibility::INTERNAL;
            $task->project()->associate($project);
            $task->created_by = $actor->getKey();

            $column = $this->resolveColumn($data['column_id'] ?? null);
            $task->board_column_id = $column->getKey();
            $task->completed_at = $column->kind === ColumnKind::DONE ? now() : null;
            $last = Task::query()->withTrashed()->where('board_column_id', $column->getKey())->max('position');
            $task->position = $last === null ? 0 : (int) $last + 1;

            if (filled($data['assignee_id'] ?? null)) {
                $task->assignee_id = $this->members->findProfessional($data['assignee_id'])?->getKey();
            }

            $task->save();

            if (array_key_exists('participant_ids', $data)) {
                $task->participants()->sync($this->members->findProfessionals($data['participant_ids'] ?? [])->modelKeys());
            }

            if (array_key_exists('tags', $data)) {
                $this->syncTags($task, $data['tags'] ?? []);
            }

            $this->activity->record('task.created', $task, $task->visibility, task: $task);

            $this->notifyCreated($actor, $task);

            return $this->load($task, $actor);
        });
    }

    /**
     * Atualiza dados descritivos. Responsáveis e coluna têm operações próprias
     * (assign/move), com permissões específicas.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Task $task, array $data): Task
    {
        return DB::transaction(function () use ($actor, $task, $data): Task {
            $task->fill(Arr::only($data, self::FIELDS));
            $changes = $this->activity->diff($task, self::FIELDS);
            $dueChanged = isset($changes['due_at']);
            $task->save();

            if (array_key_exists('tags', $data)) {
                $changes += $this->syncTags($task, $data['tags'] ?? []);
            }

            if ($changes !== []) {
                // Mudança de visibilidade é registrada como interna para não
                // revelar ao cliente que a tarefa existia antes.
                $visibility = isset($changes['visibility']) ? Visibility::INTERNAL : $task->visibility;
                $this->activity->record('task.updated', $task, $visibility, task: $task, changes: $changes);
            }

            if ($dueChanged) {
                $this->notifier->aboutTask(
                    $task,
                    $this->stakeholders($task),
                    'task_due_changed',
                    'Prazo alterado',
                    "O prazo da tarefa \"{$task->title}\" foi alterado para "
                        .($task->due_at?->format('d/m/Y') ?? 'sem prazo').'.',
                    internal: ! $task->isShared(),
                );
            }

            return $this->load($task, $actor);
        });
    }

    /**
     * @param  list<string>|null  $participantUuids  null = mantém os participantes
     */
    public function assign(User $actor, Task $task, ?string $assigneeUuid, ?array $participantUuids = null): Task
    {
        return DB::transaction(function () use ($actor, $task, $assigneeUuid, $participantUuids): Task {
            $task->loadMissing('assignee');
            $previous = $task->assignee;
            $assignee = $this->members->findProfessional($assigneeUuid);

            if ($assigneeUuid !== null && $assignee === null) {
                throw ValidationException::withMessages(['assignee_id' => ['Responsável inválido.']]);
            }

            $changes = [];

            if ($previous?->getKey() !== $assignee?->getKey()) {
                $task->assignee()->associate($assignee);
                $task->save();
                $changes['assignee'] = ['from' => $previous?->name, 'to' => $assignee?->name];
            }

            if ($participantUuids !== null) {
                $before = $task->participants()->pluck('users.id')->sort()->values()->all();
                $participants = $this->members->findProfessionals($participantUuids);
                $task->participants()->sync($participants->modelKeys());
                $after = collect($participants->modelKeys())->sort()->values()->all();

                if ($before !== $after) {
                    $changes['participants'] = ['from' => count($before), 'to' => count($after)];
                }
            }

            if ($changes !== []) {
                $this->activity->record('task.assigned', $task, $task->visibility, task: $task, changes: $changes);
            }

            if (isset($changes['assignee']) && $assignee !== null) {
                $this->notifier->aboutTask(
                    $task,
                    [$assignee],
                    'task_assigned',
                    'Nova tarefa atribuída',
                    "Você é o responsável pela tarefa \"{$task->title}\".",
                    internal: true,
                );
            }

            return $this->load($task, $actor);
        });
    }

    /**
     * Move a tarefa para uma coluna do quadro geral. A ordem é única por
     * coluna (o quadro reúne tarefas de todos os projetos).
     *
     * $afterUuid posiciona a tarefa logo abaixo de outra da mesma coluna —
     * útil em visões filtradas, em que o índice exibido não corresponde ao
     * índice global. Sem ele, vale $position (0 = topo; padrão: final).
     *
     * @throws ValidationException
     */
    public function move(User $actor, Task $task, string $columnUuid, ?int $position = null, ?string $afterUuid = null): Task
    {
        return DB::transaction(function () use ($actor, $task, $columnUuid, $position, $afterUuid): Task {
            $task->loadMissing(['project', 'column']);

            $column = $this->boards->current()->columns()->where('uuid', $columnUuid)->first();

            if ($column === null) {
                throw ValidationException::withMessages([
                    'column_id' => ['A coluna informada não pertence ao quadro.'],
                ]);
            }

            $from = $task->column;

            $siblings = Task::query()->withTrashed()
                ->where('board_column_id', $column->getKey())
                ->whereKeyNot($task->getKey())
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id', 'uuid')
                ->all();

            if (filled($afterUuid) && array_key_exists($afterUuid, $siblings)) {
                $position = array_search($afterUuid, array_keys($siblings), true) + 1;
            }

            $siblings = array_values($siblings);
            $position = max(0, min($position ?? count($siblings), count($siblings)));
            array_splice($siblings, $position, 0, [$task->getKey()]);

            $task->board_column_id = $column->getKey();
            $task->position = $position;
            $task->completed_at = match (true) {
                $column->kind === ColumnKind::DONE => $task->completed_at ?? now(),
                default => null,
            };
            $task->save();

            foreach ($siblings as $index => $id) {
                if ($id !== $task->getKey()) {
                    Task::query()->whereKey($id)->where('position', '!=', $index)->update(['position' => $index]);
                }
            }

            $task->setRelation('column', $column);

            if ($from?->getKey() !== $column->getKey()) {
                $this->activity->record('task.moved', $task, $task->visibility, task: $task, changes: [
                    'status' => ['from' => $from?->name, 'to' => $column->name],
                ]);

                if ($column->kind === ColumnKind::DONE && $from?->kind !== ColumnKind::DONE) {
                    $this->notifier->aboutTask(
                        $task,
                        $this->stakeholders($task, includeClient: true),
                        'task_completed',
                        'Tarefa concluída',
                        "A tarefa \"{$task->title}\" foi concluída.",
                        internal: ! $task->isShared(),
                    );
                }
            }

            return $this->load($task, $actor);
        });
    }

    public function delete(Task $task): void
    {
        DB::transaction(function () use ($task): void {
            $task->delete();
            $this->activity->record('task.deleted', $task, $task->visibility, task: $task, changes: [
                'title' => ['from' => $task->title, 'to' => null],
            ]);
        });
    }

    /**
     * Destinatários interessados: responsável, participantes, criador e
     * (opcionalmente) usuários do cliente — o notifier aplica as Policies.
     *
     * @return Collection<int, User|null>
     */
    public function stakeholders(Task $task, bool $includeClient = false): Collection
    {
        $task->loadMissing(['assignee', 'creator', 'participants', 'project']);

        $users = collect([$task->assignee, $task->creator])->concat($task->participants);

        if ($includeClient && $task->project !== null) {
            $users = $users->concat($this->notifier->clientUsersOf($task->project));
        }

        return $users;
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function withSummary(Builder $query, User $actor): Builder
    {
        return $query
            ->with([
                'project:id,uuid,name,client_id',
                'project.client:id,uuid,name',
                'column:id,uuid,name,color,kind,board_id',
                'assignee:id,uuid,name',
                'tags:id,name,color',
            ])
            ->withCount([
                'subtasks',
                'subtasks as done_subtasks_count' => fn (Builder $q) => $q->where('status', 'done'),
                'comments' => fn (Builder $q) => $q->visibleTo($actor),
            ])
            ->withMax(['comments as last_comment_at' => fn (Builder $q) => $q->visibleTo($actor)], 'created_at');
    }

    private function notifyCreated(User $actor, Task $task): void
    {
        if ($task->assignee_id !== null) {
            $this->notifier->aboutTask(
                $task,
                [$task->assignee()->first()],
                'task_assigned',
                'Nova tarefa atribuída',
                "Você é o responsável pela tarefa \"{$task->title}\".",
                internal: true,
            );
        }

        if ($actor->isClientUser()) {
            $this->notifier->aboutTask(
                $task,
                $task->project->members()->get(),
                'task_created',
                'Nova tarefa do cliente',
                "{$actor->name} abriu a tarefa \"{$task->title}\".",
                internal: true,
            );
        }
    }

    private function resolveColumn(?string $columnUuid): BoardColumn
    {
        if (filled($columnUuid)) {
            $column = $this->boards->current()->columns()->where('uuid', $columnUuid)->first();

            if ($column === null) {
                throw ValidationException::withMessages([
                    'column_id' => ['A coluna informada não pertence ao quadro.'],
                ]);
            }

            return $column;
        }

        return $this->boards->defaultColumn();
    }

    /**
     * @param  list<string>  $names
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function syncTags(Task $task, array $names): array
    {
        $names = collect($names)
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->filter()
            ->unique()
            ->take(10)
            ->values();

        $before = $task->tags()->pluck('name')->sort()->values()->all();

        $ids = $names->map(fn (string $name) => Tag::query()->firstOrCreate(['name' => $name])->getKey());
        $task->tags()->sync($ids->all());
        $task->unsetRelation('tags');

        $after = $names->sort()->values()->all();

        return $before === $after ? [] : ['tags' => ['from' => $before, 'to' => $after]];
    }

    /**
     * @return list<string>
     */
    private function kindsFor(string $state): array
    {
        return match ($state) {
            'open' => [ColumnKind::OPEN->value, ColumnKind::WAITING->value],
            'waiting' => [ColumnKind::WAITING->value],
            'done' => [ColumnKind::DONE->value],
            'cancelled' => [ColumnKind::CANCELLED->value],
            'closed' => ColumnKind::closedValues(),
            default => [],
        };
    }
}
