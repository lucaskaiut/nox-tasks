<?php

namespace App\Modules\Mcp\Tools\Tasks;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\TaskResource;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Services\TaskService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_tasks')]
#[Description('Lista tarefas visíveis ao usuário. Filtros: search (título/descrição), project, client, assignee (id | "me" | "none"), priority (lista), state (open|waiting|done|cancelled|closed), column, tag, overdue, due_from, due_to, sort (due_at|priority|created_at|updated_at|title|position), direction. Paginado.')]
#[IsReadOnly]
class ListTasks extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->max(100),
            'project' => $schema->string()->format('uuid'),
            'client' => $schema->string()->format('uuid'),
            'assignee' => $schema->string()->description('ID do responsável, "me" ou "none".'),
            'priority' => $schema->array()->items($schema->string()->enum(['low', 'normal', 'high', 'urgent'])),
            'state' => $schema->string()->enum(['open', 'waiting', 'done', 'cancelled', 'closed']),
            'column' => $schema->string()->format('uuid')->description('ID da coluna (status).'),
            'visibility' => $schema->string()->enum(['internal', 'shared']),
            'tag' => $schema->string()->max(40),
            'overdue' => $schema->boolean(),
            'due_from' => $schema->string()->format('date'),
            'due_to' => $schema->string()->format('date'),
            'sort' => $schema->string()->enum(['due_at', 'priority', 'created_at', 'updated_at', 'title', 'position']),
            'direction' => $schema->string()->enum(['asc', 'desc']),
            ...$this->paginationSchema($schema),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return $user->hasPermission(Permission::TASK_READ);
    }

    protected function run(Request $request, User $user): array
    {
        $this->authorizeFor($user, 'viewAny', Task::class);

        $filters = $request->validate([...WorkspaceRules::taskFilters(), 'per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $this->usePage($filters['page'] ?? 1);

        return $this->page(
            app(TaskService::class)->paginate($user, $filters, $this->perPage($filters['per_page'] ?? null)),
            TaskResource::class,
        );
    }
}
