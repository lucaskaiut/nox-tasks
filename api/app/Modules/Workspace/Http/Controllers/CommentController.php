<?php

namespace App\Modules\Workspace\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Workspace\Http\Requests\StoreCommentRequest;
use App\Modules\Workspace\Http\Resources\TaskCommentResource;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends ApiController
{
    public function __construct(private readonly CommentService $service) {}

    public function index(Request $request, Task $task): JsonResponse
    {
        return $this->paginated(TaskCommentResource::collection(
            $this->service->paginate($request->user(), $task, (int) $request->integer('per_page', 30)),
        ));
    }

    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        $comment = $this->service->create(
            $request->user(),
            $task,
            $request->validated('body'),
            $request->validated('visibility'),
        );

        return $this->created(TaskCommentResource::make($comment), 'Comentário adicionado.');
    }
}
