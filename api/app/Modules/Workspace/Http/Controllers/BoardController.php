<?php

namespace App\Modules\Workspace\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Workspace\Http\Requests\StoreColumnRequest;
use App\Modules\Workspace\Http\Requests\UpdateBoardRequest;
use App\Modules\Workspace\Http\Requests\UpdateColumnRequest;
use App\Modules\Workspace\Http\Resources\BoardColumnResource;
use App\Modules\Workspace\Http\Resources\BoardResource;
use App\Modules\Workspace\Http\Resources\TaskResource;
use App\Modules\Workspace\Models\BoardColumn;
use App\Modules\Workspace\Services\BoardService;
use App\Modules\Workspace\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quadro Kanban geral do tenant (único) e suas colunas.
 */
class BoardController extends ApiController
{
    public function __construct(
        private readonly BoardService $service,
        private readonly TaskService $tasks,
    ) {}

    /**
     * Quadro com as tarefas visíveis ao usuário. Filtros opcionais
     * (projeto, cliente, responsável, prioridade, busca) apenas restringem
     * esse conjunto.
     */
    public function show(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'project' => ['nullable', 'uuid'],
            'client' => ['nullable', 'uuid'],
            'assignee' => ['nullable', 'string', 'max:36'],
            'priority' => ['nullable', 'array'],
            'priority.*' => ['string', 'max:10'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $board = $this->service->load($this->service->current());
        $tasks = $this->tasks->query($request->user(), array_filter($filters))->limit(1000)->get();

        return $this->success([
            'board' => BoardResource::make($board),
            'tasks' => TaskResource::collection($tasks),
        ]);
    }

    /**
     * Apenas o quadro e suas colunas, sem tarefas.
     */
    public function config(): JsonResponse
    {
        return $this->success(BoardResource::make($this->service->load($this->service->current())));
    }

    public function update(UpdateBoardRequest $request): JsonResponse
    {
        $board = $this->service->update($this->service->current(), $request->validated());

        return $this->success(BoardResource::make($board), 'Quadro atualizado.');
    }

    public function storeColumn(StoreColumnRequest $request): JsonResponse
    {
        $column = $this->service->addColumn($this->service->current(), $request->validated());

        return $this->created(BoardColumnResource::make($column), 'Coluna criada.');
    }

    public function updateColumn(UpdateColumnRequest $request, BoardColumn $column): JsonResponse
    {
        return $this->success(BoardColumnResource::make($this->service->updateColumn($column, $request->validated())), 'Coluna atualizada.');
    }

    public function moveColumn(Request $request, BoardColumn $column): JsonResponse
    {
        $data = $request->validate(['position' => ['required', 'integer', 'min:0', 'max:100']]);

        return $this->success(BoardColumnResource::make($this->service->moveColumn($column, (int) $data['position'])));
    }

    public function destroyColumn(Request $request, BoardColumn $column): JsonResponse
    {
        $data = $request->validate(['move_tasks_to' => ['nullable', 'uuid']]);

        $target = filled($data['move_tasks_to'] ?? null)
            ? BoardColumn::query()->where('uuid', $data['move_tasks_to'])->first()
            : null;

        $this->service->deleteColumn($column, $target);

        return $this->success(null, 'Coluna removida.');
    }
}
