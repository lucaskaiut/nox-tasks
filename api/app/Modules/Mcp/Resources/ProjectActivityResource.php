<?php

namespace App\Modules\Mcp\Resources;

use App\Modules\Mcp\Support\WorkspaceResource;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\ActivityResource;
use App\Modules\Workspace\Services\ActivityService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('project-activity')]
#[Description('Histórico das últimas 50 atividades de um projeto e de suas tarefas.')]
class ProjectActivityResource extends WorkspaceResource
{
    protected function template(): string
    {
        return 'nox://projects/{project_id}/activity';
    }

    protected function content(Request $request, User $user): array
    {
        $project = $this->authorizedProject($user, $request->get('project_id'));

        return ['data' => $this->collection(
            ActivityResource::class,
            app(ActivityService::class)->forProject($user, $project, 50)->getCollection(),
        )];
    }
}
