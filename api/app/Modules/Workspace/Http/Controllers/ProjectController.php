<?php

namespace App\Modules\Workspace\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Workspace\Http\Requests\ProjectFiltersRequest;
use App\Modules\Workspace\Http\Requests\StoreProjectRequest;
use App\Modules\Workspace\Http\Requests\UpdateProjectRequest;
use App\Modules\Workspace\Http\Resources\ActivityResource;
use App\Modules\Workspace\Http\Resources\BoardResource;
use App\Modules\Workspace\Http\Resources\ProjectResource;
use App\Modules\Workspace\Http\Resources\TaskResource;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Services\ActivityService;
use App\Modules\Workspace\Services\BoardService;
use App\Modules\Workspace\Services\ProjectService;
use App\Modules\Workspace\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends ApiController
{
    public function __construct(
        private readonly ProjectService $service,
        private readonly TaskService $tasks,
        private readonly ActivityService $activity,
    ) {}

    public function index(ProjectFiltersRequest $request): JsonResponse
    {
        $filters = $request->validated();

        return $this->paginated(ProjectResource::collection(
            $this->service->paginate($request->user(), $filters, (int) ($filters['per_page'] ?? 15)),
        ));
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        return $this->success(ProjectResource::make($this->service->load($project, $request->user())));
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->service->create($request->user(), $request->validated());

        return $this->created(ProjectResource::make($project), 'Projeto criado.');
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $project = $this->service->update($request->user(), $project, $request->validated());

        return $this->success(ProjectResource::make($project), 'Projeto atualizado.');
    }

    public function archive(Request $request, Project $project): JsonResponse
    {
        return $this->success(ProjectResource::make($this->service->archive($request->user(), $project)), 'Projeto arquivado.');
    }

    public function restore(Request $request, Project $project): JsonResponse
    {
        return $this->success(ProjectResource::make($this->service->restore($request->user(), $project)), 'Projeto reativado.');
    }

    /**
     * Quadro geral filtrado pelas tarefas do projeto visíveis ao usuário.
     */
    public function board(Request $request, Project $project, BoardService $boards): JsonResponse
    {
        $tasks = $this->tasks->query($request->user(), ['project' => $project->uuid])->limit(1000)->get();

        return $this->success([
            'board' => BoardResource::make($boards->load($boards->current())),
            'tasks' => TaskResource::collection($tasks),
        ]);
    }

    public function activity(Request $request, Project $project): JsonResponse
    {
        return $this->paginated(ActivityResource::collection(
            $this->activity->forProject($request->user(), $project, (int) $request->integer('per_page', 20)),
        ));
    }
}
