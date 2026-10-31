<?php

namespace App\Modules\Mcp\Tools\Tasks;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Services\TaskService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_task')]
#[Description('Exclui uma tarefa (exclusão lógica). Ação destrutiva: exige a permissão task.delete. Confirme com o usuário antes de usar.')]
#[IsDestructive]
class DeleteTask extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['task_id' => $schema->string()->format('uuid')->required()];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::TASK_DELETE);
    }

    protected function run(Request $request, User $user): array
    {
        $task = $this->authorizedTask($user, $request->get('task_id'), 'delete');

        app(TaskService::class)->delete($task);

        return ['deleted' => true, 'task_id' => $task->uuid];
    }
}
