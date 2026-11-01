<?php

namespace App\Modules\Mcp\Tools\Projects;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\ProjectResource;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Services\ProjectService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_project')]
#[Description('Cria um projeto para um cliente ativo. member_ids: IDs de profissionais (use list_members).')]
class CreateProject extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(160)->required(),
            'client_id' => $schema->string()->format('uuid')->required(),
            'description' => $schema->string()->max(10000),
            'status' => $schema->string()->enum(['planning', 'active', 'on_hold', 'completed', 'cancelled']),
            'starts_at' => $schema->string()->format('date'),
            'due_at' => $schema->string()->format('date'),
            'internal_notes' => $schema->string()->max(10000)->description('Nunca visível ao cliente.'),
            'member_ids' => $schema->array()->items($schema->string()->format('uuid'))->max(50),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::PROJECT_CREATE);
    }

    protected function run(Request $request, User $user): array
    {
        $this->authorizeFor($user, 'create', Project::class);

        $data = $request->validate(WorkspaceRules::project());

        return $this->item(ProjectResource::class, app(ProjectService::class)->create($user, $data));
    }
}
