<?php

namespace App\Modules\Workspace\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Workspace\Http\Requests\AssignTaskRequest;
use App\Modules\Workspace\Http\Requests\MoveTaskRequest;
use App\Modules\Workspace\Http\Requests\StoreTaskRequest;
use App\Modules\Workspace\Http\Requests\TaskFiltersRequest;
use App\Modules\Workspace\Http\Requests\UpdateTaskRequest;
use App\Modules\Workspace\Http\Resources\ActivityResource;
use App\Modules\Workspace\Http\Resources\TaskResource;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Services\ActivityService;
use App\Modules\Workspace\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskController extends ApiController
{
    public function __construct(
        private readonly TaskService $service,
        private readonly ActivityService $activity,
    ) {}

    public function index(TaskFiltersRequest $request): JsonResponse
    {
        $filters = $request->validated();

        return $this->paginated(TaskResource::collection(
            $this->service->paginate($request->user(), $filters, (int) ($filters['per_page'] ?? 20)),
        ));
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        return $this->success(TaskResource::make($this->service->load($task, $request->user())));
    }

    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();

        // Definir responsáveis na criação exige a mesma permissão de atribuição.
        if (filled($data['assignee_id'] ?? null) || filled($data['participant_ids'] ?? null)) {
            Gate::authorize('assignOnCreate', Task::class);
        }

        return $this->created(TaskResource::make($this->service->create($request->user(), $project, $data)), 'Tarefa criada.');
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        return $this->success(TaskResource::make($this->service->update($request->user(), $task, $request->validated())), 'Tarefa atualizada.');
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->service->delete($task);

        return $this->success(null, 'Tarefa removida.');
    }

    public function assign(AssignTaskRequest $request, Task $task): JsonResponse
    {
        $task = $this->service->assign(
            $request->user(),
            $task,
            $request->validated('assignee_id'),
            $request->has('participant_ids') ? ($request->validated('participant_ids') ?? []) : null,
        );

        return $this->success(TaskResource::make($task), 'Responsáveis atualizados.');
    }

    public function move(MoveTaskRequest $request, Task $task): JsonResponse
    {
        $task = $this->service->move(
            $request->user(),
            $task,
            $request->validated('column_id'),
            $request->validated('position'),
            $request->validated('after_id'),
        );

        return $this->success(TaskResource::make($task));
    }

    public function activity(Request $request, Task $task): JsonResponse
    {
        return $this->paginated(ActivityResource::collection(
            $this->activity->forTask($request->user(), $task, (int) $request->integer('per_page', 20)),
        ));
    }
}
