<?php

namespace App\Modules\Mcp\Tools\Projects;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\ProjectResource;
use App\Modules\Workspace\Services\ProjectService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_project')]
#[Description('Atualiza um projeto. Envie apenas os campos a alterar. member_ids substitui a lista de participantes. Trocar o cliente só é possível em projetos sem tarefas.')]
class UpdateProject extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->format('uuid')->required(),
            'name' => $schema->string()->max(160),
            'client_id' => $schema->string()->format('uuid'),
            'description' => $schema->string()->max(10000)->nullable(),
            'board_id' => $schema->string()->format('uuid'),
            'status' => $schema->string()->enum(['planning', 'active', 'on_hold', 'completed', 'cancelled']),
            'starts_at' => $schema->string()->format('date')->nullable(),
            'due_at' => $schema->string()->format('date')->nullable(),
            'internal_notes' => $schema->string()->max(10000)->nullable(),
            'member_ids' => $schema->array()->items($schema->string()->format('uuid'))->max(50),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::PROJECT_UPDATE);
    }

    protected function run(Request $request, User $user): array
    {
        $project = $this->authorizedProject($user, $request->get('project_id'), 'update');

        $data = $request->validate(WorkspaceRules::project(partial: true));

        return $this->item(ProjectResource::class, app(ProjectService::class)->update($user, $project, $data));
    }
}
