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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('archive_project')]
#[Description('Arquiva um projeto (ou reativa, com restore=true). Projetos arquivados não recebem novas tarefas.')]
#[IsIdempotent]
class ArchiveProject extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->format('uuid')->required(),
            'restore' => $schema->boolean()->description('true para reativar um projeto arquivado.'),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::PROJECT_ARCHIVE);
    }

    protected function run(Request $request, User $user): array
    {
        $project = $this->authorizedProject($user, $request->get('project_id'), 'archive');
        $data = $request->validate(['restore' => ['sometimes', 'boolean']]);
        $service = app(ProjectService::class);

        $project = ($data['restore'] ?? false) ? $service->restore($user, $project) : $service->archive($user, $project);

        return $this->item(ProjectResource::class, $project);
    }
}
