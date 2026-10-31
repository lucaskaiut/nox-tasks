<?php

namespace App\Modules\Workspace\Services;

use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\ColumnKind;
use App\Modules\Workspace\Enums\ProjectStatus;
use App\Modules\Workspace\Enums\TaskPriority;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Indicadores do dashboard. Todas as contagens partem dos escopos
 * visibleTo($actor): o dashboard de um cliente nunca contabiliza
 * dados de outros clientes nem tarefas internas.
 */
class DashboardService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly ActivityService $activity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $actor): array
    {
        $tasks = fn (): Builder => Task::query()->visibleTo($actor);
        $inKinds = fn (array $kinds): Builder => $tasks()->whereHas('column', fn (Builder $c) => $c->whereIn('kind', $kinds));
        $openKinds = [ColumnKind::OPEN->value, ColumnKind::WAITING->value];

        $byKind = $tasks()
            ->join('board_columns', 'board_columns.id', '=', 'tasks.board_column_id')
            ->selectRaw('board_columns.kind as kind, count(*) as total')
            ->groupBy('board_columns.kind')
            ->pluck('total', 'kind');

        $byPriority = $inKinds($openKinds)
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        return [
            'profile' => $actor->isClientUser() ? 'client' : 'professional',
            'metrics' => [
                'active_projects' => Project::query()->visibleTo($actor)
                    ->whereNull('archived_at')
                    ->whereIn('status', array_map(fn ($s) => $s->value, ProjectStatus::inProgress()))
                    ->count(),
                'open_tasks' => $inKinds($openKinds)->count(),
                'waiting_tasks' => $inKinds([ColumnKind::WAITING->value])->count(),
                'overdue_tasks' => $this->overdue($inKinds($openKinds))->count(),
                'completed_tasks' => $inKinds([ColumnKind::DONE->value])->count(),
                'completed_last_7_days' => $inKinds([ColumnKind::DONE->value])
                    ->where('completed_at', '>=', now()->subDays(7))
                    ->count(),
                'my_open_tasks' => $actor->isClientUser()
                    ? null
                    : $inKinds($openKinds)->where('assignee_id', $actor->getKey())->count(),
            ],
            'status_distribution' => collect(ColumnKind::cases())->map(fn (ColumnKind $kind) => [
                'kind' => $kind->value,
                'label' => $kind->label(),
                'total' => (int) ($byKind[$kind->value] ?? 0),
            ])->values()->all(),
            'priority_distribution' => collect(TaskPriority::cases())->map(fn (TaskPriority $priority) => [
                'priority' => $priority->value,
                'label' => $priority->label(),
                'total' => (int) ($byPriority[$priority->value] ?? 0),
            ])->values()->all(),
            'priority_tasks' => $this->priorityTasks($actor, $openKinds),
            'upcoming_deadlines' => $this->upcoming($actor, $openKinds),
            'recent_activity' => $this->activity->recent($actor, 12),
        ];
    }

    /**
     * @param  list<string>  $openKinds
     * @return Collection<int, Task>
     */
    private function priorityTasks(User $actor, array $openKinds): Collection
    {
        return $this->tasks->withSummary(Task::query()->visibleTo($actor), $actor)
            ->whereIn('priority', [TaskPriority::URGENT->value, TaskPriority::HIGH->value])
            ->whereHas('column', fn (Builder $c) => $c->whereIn('kind', $openKinds))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 ELSE 1 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit(8)
            ->get();
    }

    /**
     * @param  list<string>  $openKinds
     * @return Collection<int, Task>
     */
    private function upcoming(User $actor, array $openKinds): Collection
    {
        return $this->tasks->withSummary(Task::query()->visibleTo($actor), $actor)
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<=', today()->addDays(14))
            ->whereHas('column', fn (Builder $c) => $c->whereIn('kind', $openKinds))
            ->orderBy('due_at')
            ->limit(8)
            ->get();
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    private function overdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_at')->whereDate('due_at', '<', today());
    }
}
