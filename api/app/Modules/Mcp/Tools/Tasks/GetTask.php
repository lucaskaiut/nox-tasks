<?php

namespace App\Modules\Mcp\Tools\Tasks;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\TaskAttachmentResource;
use App\Modules\Workspace\Http\Resources\TaskResource;
use App\Modules\Workspace\Services\AttachmentService;
use App\Modules\Workspace\Services\TaskService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_task')]
#[Description('Detalhes de uma tarefa: status (coluna), prioridade, responsáveis, prazos, tags, subtarefas, contadores e anexos (inclusive os enviados em comentários; leia o conteúdo com read_task_attachment).')]
#[IsReadOnly]
class GetTask extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['task_id' => $schema->string()->format('uuid')->required()];
    }

    protected function availableTo(User $user): bool
    {
        return $user->hasPermission(Permission::TASK_READ);
    }

    protected function run(Request $request, User $user): array
    {
        $task = $this->authorizedTask($user, $request->get('task_id'));

        return [
            ...$this->item(TaskResource::class, app(TaskService::class)->load($task, $user)),
            'attachments' => $this->collection(TaskAttachmentResource::class, app(AttachmentService::class)->list($user, $task)),
        ];
    }
}
