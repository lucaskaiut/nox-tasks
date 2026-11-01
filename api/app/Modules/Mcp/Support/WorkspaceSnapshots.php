<?php

namespace App\Modules\Mcp\Support;

use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\ActivityResource;
use App\Modules\Workspace\Http\Resources\BoardColumnResource;
use App\Modules\Workspace\Http\Resources\ProjectResource;
use App\Modules\Workspace\Http\Resources\SubtaskResource;
use App\Modules\Workspace\Http\Resources\TaskCommentResource;
use App\Modules\Workspace\Http\Resources\TaskResource;
use App\Modules\Workspace\Models\Board;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Services\ActivityService;
use App\Modules\Workspace\Services\CommentService;
use App\Modules\Workspace\Services\ProjectService;
use App\Modules\Workspace\Services\TaskService;

/**
 * Visões consolidadas usadas por Tools, Resources e Prompts do MCP.
 * Recebem modelos JÁ AUTORIZADOS e só consultam via escopos visibleTo($user).
 */
class WorkspaceSnapshots
{
    private const MAX_TASKS = 300;

    public function __construct(
        private readonly ProjectService $projects,
        private readonly TaskService $tasks,
        private readonly ActivityService $activity,
        private readonly CommentService $comments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function project(User $user, Project $project): array
    {
        $open = $this->tasks->query($user, ['project' => $project->uuid, 'state' => 'open', 'sort' => 'due_at'])->limit(50)->get();

        return [
            'project' => ProjectResource::make($this->projects->load($project, $user))->resolve(request()),
            'open_tasks' => $this->compactTasks(TaskResource::collection($open)->resolve(request())),
            'recent_activity' => ActivityResource::collection(
                $this->activity->forProject($user, $project, 15)->getCollection(),
            )->resolve(request()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function task(User $user, Task $task): array
    {
        $task = $this->tasks->load($task, $user);

        return [
            'task' => TaskResource::make($task)->resolve(request()),
            'subtasks' => SubtaskResource::collection($task->subtasks)->resolve(request()),
            'comments' => TaskCommentResource::collection(
                $this->comments->paginate($user, $task, 20)->getCollection(),
            )->resolve(request()),
            'recent_activity' => ActivityResource::collection(
                $this->activity->forTask($user, $task, 10)->getCollection(),
            )->resolve(request()),
        ];
    }

    /**
     * Kanban do quadro geral: colunas em ordem com as tarefas visíveis de
     * cada uma, opcionalmente restritas a um projeto.
     *
     * @return array<string, mixed>
     */
    public function kanban(User $user, Board $board, ?Project $project = null): array
    {
        $board->loadMissing('columns');
        $filters = $project !== null ? ['project' => $project->uuid] : [];
        $tasks = collect($this->compactTasks(TaskResource::collection(
            $this->tasks->query($user, $filters)->limit(self::MAX_TASKS)->get(),
        )->resolve(request())));

        return [
            'project' => $project !== null ? ['id' => $project->uuid, 'name' => $project->name] : null,
            'board' => ['id' => $board->uuid, 'name' => $board->name],
            'columns' => $board->columns->map(fn ($column) => [
                ...BoardColumnResource::make($column)->resolve(request()),
                'tasks' => $tasks->where('status_id', $column->uuid)->values()->all(),
            ])->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function overdue(User $user, ?Project $project = null): array
    {
        $filters = ['overdue' => true, 'sort' => 'due_at'];

        if ($project !== null) {
            $filters['project'] = $project->uuid;
        }

        return $this->compactTasks(TaskResource::collection(
            $this->tasks->query($user, $filters)->limit(100)->get(),
        )->resolve(request()));
    }

    /**
     * Remove do retrato tudo que é interno (para textos destinados ao cliente).
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function withoutInternal(array $snapshot): array
    {
        unset($snapshot['project']['internal_notes']);

        $snapshot['open_tasks'] = array_values(array_filter(
            $snapshot['open_tasks'] ?? [],
            fn (array $task) => $task['visibility'] === 'shared',
        ));

        $snapshot['recent_activity'] = array_values(array_filter(
            $snapshot['recent_activity'] ?? [],
            fn (array $activity) => ($activity['visibility'] ?? null) === 'shared',
        ));

        return $snapshot;
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return list<array<string, mixed>>
     */
    private function compactTasks(array $tasks): array
    {
        // Recursos aninhados (status, projeto...) ainda não estão serializados:
        // acessá-los como array leria atributos do model (ex.: id interno).
        $tasks = json_decode(json_encode($tasks, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        return array_map(fn (array $task) => [
            'id' => $task['id'],
            'title' => $task['title'],
            'project' => $task['project']['name'] ?? null,
            'status' => $task['status']['name'] ?? null,
            'status_id' => $task['status']['id'] ?? null,
            'priority' => $task['priority'],
            'visibility' => $task['visibility'],
            'assignee' => $task['assignee']['name'] ?? null,
            'due_at' => $task['due_at'],
            'is_overdue' => $task['is_overdue'],
            'subtasks' => $task['subtasks_summary'],
            'tags' => collect($task['tags'] ?? [])->pluck('name')->all(),
        ], $tasks);
    }
}
