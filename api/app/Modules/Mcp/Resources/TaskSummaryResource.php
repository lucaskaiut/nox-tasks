<?php

namespace App\Modules\Mcp\Resources;

use App\Modules\Mcp\Support\WorkspaceResource;
use App\Modules\Mcp\Support\WorkspaceSnapshots;
use App\Modules\User\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('task-summary')]
#[Description('Resumo de uma tarefa: dados, subtarefas, comentários visíveis e histórico recente.')]
class TaskSummaryResource extends WorkspaceResource
{
    protected function template(): string
    {
        return 'nox://tasks/{task_id}';
    }

    protected function content(Request $request, User $user): array
    {
        return app(WorkspaceSnapshots::class)->task($user, $this->authorizedTask($user, $request->get('task_id')));
    }
}
