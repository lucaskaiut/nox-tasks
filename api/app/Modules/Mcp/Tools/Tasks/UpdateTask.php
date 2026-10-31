<?php

namespace App\Modules\Mcp\Tools\Tasks;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\TaskResource;
use App\Modules\Workspace\Services\TaskService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_task')]
#[Description('Atualiza dados de uma tarefa (título, descrição, prioridade, visibilidade, datas, estimativa, tags). Para status use move_task; para responsáveis use assign_task.')]
class UpdateTask extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->format('uuid')->required(),
            'title' => $schema->string()->max(255),
            'description' => $schema->string()->max(20000)->nullable(),
            'priority' => $schema->string()->enum(['low', 'normal', 'high', 'urgent']),
            'visibility' => $schema->string()->enum(['internal', 'shared']),
            'starts_at' => $schema->string()->format('date')->nullable(),
            'due_at' => $schema->string()->format('date')->nullable(),
            'estimated_hours' => $schema->number()->min(0)->max(9999)->nullable(),
            'tags' => $schema->array()->items($schema->string()->max(40))->max(10)->description('Substitui as tags.'),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::TASK_UPDATE);
    }

    protected function run(Request $request, User $user): array
    {
        $task = $this->authorizedTask($user, $request->get('task_id'), 'update');

        $data = $request->validate(WorkspaceRules::task(partial: true));

        return $this->item(TaskResource::class, app(TaskService::class)->update($user, $task, $data));
    }
}
