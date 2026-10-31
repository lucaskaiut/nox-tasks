<?php

namespace App\Modules\Mcp\Tools\Tasks;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\TaskResource;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Services\TaskService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_task')]
#[Description('Cria uma tarefa em um projeto. Sem column_id, entra na primeira coluna "em aberto". visibility: internal (padrão, invisível ao cliente) ou shared. Usuários de cliente criam sempre tarefas compartilhadas e sem responsável.')]
class CreateTask extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->format('uuid')->required(),
            'title' => $schema->string()->max(255)->required(),
            'description' => $schema->string()->max(20000),
            'column_id' => $schema->string()->format('uuid')->description('Coluna inicial (do quadro do projeto).'),
            'priority' => $schema->string()->enum(['low', 'normal', 'high', 'urgent']),
            'visibility' => $schema->string()->enum(['internal', 'shared']),
            'starts_at' => $schema->string()->format('date'),
            'due_at' => $schema->string()->format('date'),
            'estimated_hours' => $schema->number()->min(0)->max(9999),
            'assignee_id' => $schema->string()->format('uuid')->description('Profissional responsável (list_members).'),
            'participant_ids' => $schema->array()->items($schema->string()->format('uuid'))->max(20),
            'tags' => $schema->array()->items($schema->string()->max(40))->max(10),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return $user->hasPermission(Permission::TASK_CREATE);
    }

    protected function run(Request $request, User $user): array
    {
        $project = $this->findOrFail(Project::class, $request->get('project_id'));
        $this->authorizeFor($user, 'create', [Task::class, $project]);

        $data = $request->validate(WorkspaceRules::task());

        if (filled($data['assignee_id'] ?? null) || filled($data['participant_ids'] ?? null)) {
            $this->authorizeFor($user, 'assignOnCreate', Task::class);
        }

        return $this->item(TaskResource::class, app(TaskService::class)->create($user, $project, $data));
    }
}
