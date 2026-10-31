<?php

namespace App\Modules\Mcp\Tools\Subtasks;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\SubtaskResource;
use App\Modules\Workspace\Services\SubtaskService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_subtask')]
#[Description('Atualiza uma subtarefa (ex.: status "done" para concluir). A subtarefa precisa pertencer à tarefa informada.')]
class UpdateSubtask extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->format('uuid')->required(),
            'subtask_id' => $schema->string()->format('uuid')->required(),
            'title' => $schema->string()->max(255),
            'description' => $schema->string()->max(5000)->nullable(),
            'status' => $schema->string()->enum(['pending', 'in_progress', 'done']),
            'priority' => $schema->string()->enum(['low', 'normal', 'high', 'urgent']),
            'due_at' => $schema->string()->format('date')->nullable(),
            'assignee_id' => $schema->string()->format('uuid')->nullable(),
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

        $data = $request->validate(WorkspaceRules::subtask(partial: true));

        return $this->item(SubtaskResource::class, app(SubtaskService::class)->update($task, $subtask, $data));
    }
}
