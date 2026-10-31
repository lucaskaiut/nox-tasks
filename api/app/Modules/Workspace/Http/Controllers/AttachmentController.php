<?php

namespace App\Modules\Workspace\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Workspace\Http\Requests\StoreAttachmentRequest;
use App\Modules\Workspace\Http\Resources\TaskAttachmentResource;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Models\TaskAttachment;
use App\Modules\Workspace\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends ApiController
{
    public function __construct(private readonly AttachmentService $service) {}

    public function index(Request $request, Task $task): JsonResponse
    {
        return $this->success(TaskAttachmentResource::collection($this->service->list($request->user(), $task)));
    }

    public function store(StoreAttachmentRequest $request, Task $task): JsonResponse
    {
        $attachment = $this->service->store(
            $request->user(),
            $task,
            $request->file('file'),
            $request->validated('visibility'),
        );

        return $this->created(TaskAttachmentResource::make($attachment), 'Arquivo anexado.');
    }

    public function download(TaskAttachment $attachment): StreamedResponse
    {
        return $this->service->download($attachment);
    }

    public function destroy(TaskAttachment $attachment): JsonResponse
    {
        $this->service->delete($attachment);

        return $this->success(null, 'Anexo removido.');
    }
}
