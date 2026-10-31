<?php

namespace App\Modules\Workspace\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Workspace\Http\Requests\StoreBoardRequest;
use App\Modules\Workspace\Http\Requests\StoreColumnRequest;
use App\Modules\Workspace\Http\Requests\UpdateBoardRequest;
use App\Modules\Workspace\Http\Requests\UpdateColumnRequest;
use App\Modules\Workspace\Http\Resources\BoardColumnResource;
use App\Modules\Workspace\Http\Resources\BoardResource;
use App\Modules\Workspace\Models\Board;
use App\Modules\Workspace\Models\BoardColumn;
use App\Modules\Workspace\Services\BoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardController extends ApiController
{
    public function __construct(private readonly BoardService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated(BoardResource::collection($this->service->paginate(
            $request->user(),
            $request->string('search')->limit(100, '')->toString() ?: null,
            (int) $request->integer('per_page', 15),
        )));
    }

    public function show(Board $board): JsonResponse
    {
        return $this->success(BoardResource::make($this->service->load($board)));
    }

    public function store(StoreBoardRequest $request): JsonResponse
    {
        return $this->created(BoardResource::make($this->service->create($request->validated())), 'Quadro criado.');
    }

    public function update(UpdateBoardRequest $request, Board $board): JsonResponse
    {
        return $this->success(BoardResource::make($this->service->update($board, $request->validated())), 'Quadro atualizado.');
    }

    public function destroy(Board $board): JsonResponse
    {
        $this->service->delete($board);

        return $this->success(null, 'Quadro removido.');
    }

    public function storeColumn(StoreColumnRequest $request, Board $board): JsonResponse
    {
        return $this->created(BoardColumnResource::make($this->service->addColumn($board, $request->validated())), 'Coluna criada.');
    }

    public function updateColumn(UpdateColumnRequest $request, Board $board, BoardColumn $column): JsonResponse
    {
        return $this->success(BoardColumnResource::make($this->service->updateColumn($column, $request->validated())), 'Coluna atualizada.');
    }

    public function moveColumn(Request $request, Board $board, BoardColumn $column): JsonResponse
    {
        $data = $request->validate(['position' => ['required', 'integer', 'min:0', 'max:100']]);

        return $this->success(BoardColumnResource::make($this->service->moveColumn($column, (int) $data['position'])));
    }

    public function destroyColumn(Request $request, Board $board, BoardColumn $column): JsonResponse
    {
        $data = $request->validate(['move_tasks_to' => ['nullable', 'uuid']]);

        $target = filled($data['move_tasks_to'] ?? null)
            ? $board->columns()->where('uuid', $data['move_tasks_to'])->first()
            : null;

        $this->service->deleteColumn($column, $target);

        return $this->success(null, 'Coluna removida.');
    }
}
