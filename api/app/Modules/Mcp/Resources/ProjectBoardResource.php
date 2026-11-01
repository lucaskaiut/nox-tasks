<?php

namespace App\Modules\Mcp\Resources;

use App\Modules\Mcp\Support\WorkspaceResource;
use App\Modules\Mcp\Support\WorkspaceSnapshots;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Services\BoardService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('project-board')]
#[Description('Quadro Kanban geral filtrado pelas tarefas de um projeto: colunas em ordem e as tarefas de cada coluna.')]
class ProjectBoardResource extends WorkspaceResource
{
    protected function template(): string
    {
        return 'nox://projects/{project_id}/board';
    }

    protected function content(Request $request, User $user): array
    {
        $project = $this->authorizedProject($user, $request->get('project_id'));

        return app(WorkspaceSnapshots::class)->kanban($user, app(BoardService::class)->current(), $project);
    }
}
