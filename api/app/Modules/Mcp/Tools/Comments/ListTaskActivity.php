<?php

namespace App\Modules\Mcp\Tools\Comments;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\ActivityResource;
use App\Modules\Workspace\Services\ActivityService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_task_activity')]
#[Description('Histórico de alterações de uma tarefa (mais recentes primeiro): quem, quando, o quê, valores anteriores/novos e origem (ui, api ou mcp).')]
#[IsReadOnly]
class ListTaskActivity extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['task_id' => $schema->string()->format('uuid')->required(), ...$this->paginationSchema($schema)];
    }

    protected function availableTo(User $user): bool
    {
        return $user->hasPermission(Permission::TASK_READ);
    }

    protected function run(Request $request, User $user): array
    {
        $task = $this->authorizedTask($user, $request->get('task_id'));
        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $this->usePage($data['page'] ?? 1);

        return $this->page(app(ActivityService::class)->forTask($user, $task, $this->perPage($data['per_page'] ?? null, 20)), ActivityResource::class);
    }
}
