<?php

namespace App\Modules\Mcp\Tools\Subtasks;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Services\SubtaskService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_subtask')]
#[Description('Exclui uma subtarefa. Ação destrutiva: confirme com o usuário antes de usar.')]
#[IsDestructive]
class DeleteSubtask extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->format('uuid')->required(),
            'subtask_id' => $schema->string()->format('uuid')->required(),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::TASK_UPDATE);
    }

    protected function run(Request $request, User $user): array
    {
        $task = $this->authorizedTask($user, $request->get('task_id'), 'update');
        $subtask = $task->subtasks()->where('uuid', (string) $request->get('subtask_id'))->firstOrFail();

        app(SubtaskService::class)->delete($task, $subtask);

        return ['deleted' => true, 'subtask_id' => $subtask->uuid];
    }
}
