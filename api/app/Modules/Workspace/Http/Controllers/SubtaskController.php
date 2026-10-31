<?php

namespace App\Modules\Workspace\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Workspace\Http\Requests\StoreSubtaskRequest;
use App\Modules\Workspace\Http\Requests\UpdateSubtaskRequest;
use App\Modules\Workspace\Http\Resources\SubtaskResource;
use App\Modules\Workspace\Models\Subtask;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Services\SubtaskService;
use Illuminate\Http\JsonResponse;

/**
 * Rotas aninhadas em /tasks/{task} com scoped bindings: a subtarefa só é
 * resolvida se pertencer à tarefa autorizada.
 */
class SubtaskController extends ApiController
{
    public function __construct(private readonly SubtaskService $service) {}

    public function index(Task $task): JsonResponse
    {
        return $this->success(SubtaskResource::collection($this->service->list($task)));
    }

    public function store(StoreSubtaskRequest $request, Task $task): JsonResponse
    {
        return $this->created(SubtaskResource::make($this->service->create($task, $request->validated())), 'Subtarefa criada.');
    }

    public function update(UpdateSubtaskRequest $request, Task $task, Subtask $subtask): JsonResponse
    {
        return $this->success(SubtaskResource::make($this->service->update($task, $subtask, $request->validated())));
    }

    public function destroy(Task $task, Subtask $subtask): JsonResponse
    {
        $this->service->delete($task, $subtask);

        return $this->success(null, 'Subtarefa removida.');
    }
}
