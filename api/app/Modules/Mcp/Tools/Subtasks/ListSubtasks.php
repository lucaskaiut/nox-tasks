<?php

namespace App\Modules\Mcp\Tools\Subtasks;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\SubtaskResource;
use App\Modules\Workspace\Services\SubtaskService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_subtasks')]
#[Description('Lista as subtarefas de uma tarefa (o acesso segue as regras da tarefa).')]
#[IsReadOnly]
class ListSubtasks extends WorkspaceTool
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
        $subtasks = app(SubtaskService::class)->list($task);
        $done = $subtasks->where('status.value', 'done')->count();

        return [
            'data' => $this->collection(SubtaskResource::class, $subtasks),
            'summary' => [
                'total' => $subtasks->count(),
                'done' => $done,
                'percent' => $subtasks->isEmpty() ? 0 : (int) round($done / $subtasks->count() * 100),
            ],
        ];
    }
}
