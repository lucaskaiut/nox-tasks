<?php

namespace App\Modules\Workspace\Services;

use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\ColumnKind;
use App\Modules\Workspace\Enums\ProjectStatus;
use App\Modules\Workspace\Enums\Visibility;
use App\Modules\Workspace\Models\Board;
use App\Modules\Workspace\Models\Client;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    private const FIELDS = ['name', 'description', 'status', 'starts_at', 'due_at', 'internal_notes'];

    /** Campos cujo histórico nunca é exibido a clientes. */
    private const INTERNAL_FIELDS = ['internal_notes'];

    public function __construct(
        private readonly BoardService $boards,
        private readonly MemberService $members,
        private readonly ActivityService $activity,
        private readonly WorkspaceNotifier $notifier,
    ) {}

    /**
     * O escopo de autorização (visibleTo) é aplicado antes de qualquer filtro
     * informado pelo usuário — filtros nunca ampliam o acesso.
     *
     * @param  array{search?: ?string, client?: ?string, status?: ?string, member?: ?string, archived?: ?string}  $filters
     */
    public function paginate(User $actor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;
        $archived = $filters['archived'] ?? 'exclude';

        $query = Project::query()
            ->visibleTo($actor)
            ->with(['client:id,uuid,name', 'members:id,uuid,name'])
            ->when(filled($filters['client'] ?? null), fn (Builder $q) => $q->whereHas(
                'client',
                fn (Builder $c) => $c->where('uuid', $filters['client']),
            ))
            ->when(filled($filters['status'] ?? null), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(filled($filters['member'] ?? null), fn (Builder $q) => $q->whereHas(
                'members',
                fn (Builder $m) => $m->where('users.uuid', $filters['member']),
            ))
            ->when(filled($search), fn (Builder $q) => $q->where(fn (Builder $s) => $s
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($archived === 'exclude', fn (Builder $q) => $q->whereNull('archived_at'))
            ->when($archived === 'only', fn (Builder $q) => $q->whereNotNull('archived_at'));

        return $this->withProgress($query, $actor)
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function load(Project $project, User $actor): Project
    {
        $project->load(['client:id,uuid,name,email', 'board.columns', 'members:id,uuid,name,email', 'creator:id,uuid,name']);

        $counts = $this->withProgress(Project::query()->whereKey($project->getKey()), $actor)->first();

        foreach (['tasks_count', 'done_tasks_count', 'cancelled_tasks_count', 'overdue_tasks_count'] as $attribute) {
            $project->setAttribute($attribute, (int) $counts?->getAttribute($attribute));
        }

        return $project;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Project
    {
        return DB::transaction(function () use ($actor, $data): Project {
            $client = Client::query()->where('uuid', $data['client_id'])->firstOrFail();

            $board = filled($data['board_id'] ?? null)
                ? Board::query()->where('uuid', $data['board_id'])->firstOrFail()
                : $this->boards->createDefaultFor($data['name']);

            $project = new Project(Arr::only($data, self::FIELDS));
            $project->status ??= ProjectStatus::PLANNING;
            $project->client()->associate($client);
            $project->board()->associate($board);
            $project->created_by = $actor->getKey();
            $project->save();

            $members = $this->members->findProfessionals($data['member_ids'] ?? []);
            $project->members()->sync($members->modelKeys());

            $this->activity->record('project.created', $project, Visibility::SHARED, project: $project);

            $this->notifier->aboutProject(
                $project,
                $members,
                'project_member_added',
                'Você foi adicionado a um projeto',
                "Você agora participa do projeto \"{$project->name}\".",
                internal: true,
            );

            return $this->load($project, $actor);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(User $actor, Project $project, array $data): Project
    {
        return DB::transaction(function () use ($actor, $project, $data): Project {
            $project->fill(Arr::only($data, self::FIELDS));
            $changes = $this->activity->diff($project, self::FIELDS);

            if (array_key_exists('client_id', $data)) {
                $changes += $this->changeClient($project, $data['client_id']);
            }

            if (array_key_exists('board_id', $data) && filled($data['board_id'])) {
                $changes += $this->changeBoard($project, $data['board_id']);
            }

            $project->save();

            if (array_key_exists('member_ids', $data)) {
                $changes += $this->syncMembers($project, $data['member_ids'] ?? []);
            }

            $this->recordUpdate($project, $changes);

            return $this->load($project, $actor);
        });
    }

    public function archive(User $actor, Project $project): Project
    {
        if (! $project->isArchived()) {
            $project->forceFill(['archived_at' => now()])->save();
            $this->activity->record('project.archived', $project, Visibility::SHARED, project: $project);
        }

        return $this->load($project, $actor);
    }

    public function restore(User $actor, Project $project): Project
    {
        if ($project->isArchived()) {
            $project->forceFill(['archived_at' => null])->save();
            $this->activity->record('project.restored', $project, Visibility::SHARED, project: $project);
        }

        return $this->load($project, $actor);
    }

    /**
     * Contadores de tarefas respeitando o escopo do usuário: para um cliente,
     * só tarefas compartilhadas entram nos indicadores.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private function withProgress(Builder $query, User $actor): Builder
    {
        $visible = fn (Builder $q) => $q->visibleTo($actor);
        $inKind = fn (ColumnKind $kind) => fn (Builder $q) => $q->visibleTo($actor)
            ->whereHas('column', fn (Builder $c) => $c->where('kind', $kind->value));

        return $query->withCount([
            'tasks' => $visible,
            'tasks as done_tasks_count' => $inKind(ColumnKind::DONE),
            'tasks as cancelled_tasks_count' => $inKind(ColumnKind::CANCELLED),
            'tasks as overdue_tasks_count' => fn (Builder $q) => $q->visibleTo($actor)
                ->whereNull('completed_at')
                ->whereDate('due_at', '<', today())
                ->whereHas('column', fn (Builder $c) => $c->whereNotIn('kind', ColumnKind::closedValues())),
        ]);
    }

    /**
     * Trocar o cliente de um projeto com tarefas exporia (ou ocultaria) o
     * histórico para outro cliente; só é permitido enquanto não há tarefas.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     *
     * @throws ValidationException
     */
    private function changeClient(Project $project, string $clientUuid): array
    {
        $client = Client::query()->where('uuid', $clientUuid)->firstOrFail();

        if ((int) $client->getKey() === (int) $project->client_id) {
            return [];
        }

        if ($project->exists && $project->tasks()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'client_id' => ['Não é possível trocar o cliente de um projeto que já possui tarefas.'],
            ]);
        }

        $from = $project->client?->name;
        $project->client()->associate($client);

        return ['client' => ['from' => $from, 'to' => $client->name]];
    }

    /**
     * Ao trocar o quadro, as tarefas são realocadas na primeira coluna
     * equivalente (mesmo "kind") do novo quadro, ou na coluna padrão.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function changeBoard(Project $project, string $boardUuid): array
    {
        $board = Board::query()->where('uuid', $boardUuid)->firstOrFail();

        if ((int) $board->getKey() === (int) $project->board_id) {
            return [];
        }

        $from = $project->board?->name;
        $columns = $board->columns()->get();
        $fallback = $this->boards->defaultColumn($board);

        Task::query()->withTrashed()
            ->where('project_id', $project->getKey())
            ->with('column')
            ->get()
            ->each(function (Task $task) use ($columns, $fallback): void {
                $target = $columns->first(fn ($c) => $c->kind === $task->column?->kind) ?? $fallback;
                $task->board_column_id = $target->getKey();
                $task->save();
            });

        $project->board()->associate($board);

        return ['board' => ['from' => $from, 'to' => $board->name]];
    }

    /**
     * @param  list<string>  $memberUuids
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function syncMembers(Project $project, array $memberUuids): array
    {
        $before = $project->members()->pluck('users.id')->all();
        $members = $this->members->findProfessionals($memberUuids);
        $project->members()->sync($members->modelKeys());

        $added = $members->reject(fn (User $u) => in_array($u->getKey(), $before, true));

        $this->notifier->aboutProject(
            $project,
            $added,
            'project_member_added',
            'Você foi adicionado a um projeto',
            "Você agora participa do projeto \"{$project->name}\".",
            internal: true,
        );

        $after = $members->modelKeys();
        sort($before);
        sort($after);

        return $before === $after ? [] : ['members' => ['from' => count($before), 'to' => count($after)]];
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    private function recordUpdate(Project $project, array $changes): void
    {
        $internal = Arr::only($changes, [...self::INTERNAL_FIELDS, 'members', 'board']);
        $shared = Arr::except($changes, array_keys($internal));

        if ($shared !== []) {
            $this->activity->record('project.updated', $project, Visibility::SHARED, project: $project, changes: $shared);

            $this->notifier->aboutProject(
                $project,
                $project->members()->get()->concat($this->notifier->clientUsersOf($project)),
                'project_updated',
                'Projeto atualizado',
                "O projeto \"{$project->name}\" foi atualizado.",
            );
        }

        if ($internal !== []) {
            $this->activity->record('project.updated', $project, Visibility::INTERNAL, project: $project, changes: $internal);
        }
    }
}
