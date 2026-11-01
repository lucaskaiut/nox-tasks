<?php

namespace App\Modules\Mcp\Prompts;

use App\Modules\Mcp\Support\WorkspacePrompt;
use App\Modules\Mcp\Support\WorkspaceSnapshots;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Services\BoardService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('organize_tasks')]
#[Description('Sugere uma organização das tarefas de um projeto no quadro Kanban geral: priorização, ordem, responsáveis e quebra em subtarefas.')]
class OrganizeTasksPrompt extends WorkspacePrompt
{
    public function arguments(): array
    {
        return [new Argument('project_id', 'ID do projeto.', required: true)];
    }

    protected function compose(Request $request, User $user): string
    {
        $project = $this->authorizedProject($user, $request->get('project_id'));
        $kanban = app(WorkspaceSnapshots::class)->kanban($user, app(BoardService::class)->current(), $project);

        return <<<TXT
Com base no quadro Kanban abaixo, sugira uma organização das tarefas:
- ordem de execução por coluna (considerando prioridade e prazo);
- tarefas que deveriam mudar de prioridade ou de coluna;
- tarefas grandes que merecem ser quebradas em subtarefas;
- tarefas sem responsável que precisam de atribuição.
Apresente as sugestões como uma lista de ações. Só execute (move_task, update_task, assign_task, create_subtask) após o usuário confirmar cada ação.

Quadro (JSON):
{$this->json($kanban)}
TXT;
    }
}
