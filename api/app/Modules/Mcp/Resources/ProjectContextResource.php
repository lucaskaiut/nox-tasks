<?php

namespace App\Modules\Mcp\Resources;

use App\Modules\Mcp\Support\WorkspaceResource;
use App\Modules\Mcp\Support\WorkspaceSnapshots;
use App\Modules\User\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('project-context')]
#[Description('Contexto de um projeto: dados, indicadores de progresso, tarefas abertas e atividades recentes.')]
class ProjectContextResource extends WorkspaceResource
{
    protected function template(): string
    {
        return 'nox://projects/{project_id}';
    }

    protected function content(Request $request, User $user): array
    {
        return app(WorkspaceSnapshots::class)->project($user, $this->authorizedProject($user, $request->get('project_id')));
    }
}
