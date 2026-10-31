<?php

namespace App\Modules\Mcp\Resources;

use App\Modules\Mcp\Support\WorkspaceResource;
use App\Modules\Mcp\Support\WorkspaceSnapshots;
use App\Modules\User\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('project-board')]
#[Description('Quadro Kanban de um projeto: colunas em ordem e as tarefas de cada coluna.')]
class ProjectBoardResource extends WorkspaceResource
{
    protected function template(): string
    {
        return 'nox://projects/{project_id}/board';
    }

    protected function content(Request $request, User $user): array
    {
        return app(WorkspaceSnapshots::class)->kanban($user, $this->authorizedProject($user, $request->get('project_id')));
    }
}
