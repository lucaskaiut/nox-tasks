<?php

namespace App\Modules\Mcp\Tools\Projects;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\ProjectResource;
use App\Modules\Workspace\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_project')]
#[Description('Detalhes de um projeto: cliente, quadro e colunas, participantes, datas e indicadores de conclusão.')]
#[IsReadOnly]
class GetProject extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['project_id' => $schema->string()->format('uuid')->required()];
    }

    protected function availableTo(User $user): bool
    {
        return $user->hasPermission(Permission::PROJECT_READ);
    }

    protected function run(Request $request, User $user): array
    {
        $project = $this->authorizedProject($user, $request->get('project_id'));

        return $this->item(ProjectResource::class, app(ProjectService::class)->load($project, $user));
    }
}
