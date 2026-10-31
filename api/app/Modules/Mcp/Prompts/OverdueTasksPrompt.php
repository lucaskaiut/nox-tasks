<?php

namespace App\Modules\Mcp\Prompts;

use App\Modules\Mcp\Support\WorkspacePrompt;
use App\Modules\Mcp\Support\WorkspaceSnapshots;
use App\Modules\User\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('overdue_tasks')]
#[Description('Identifica tarefas atrasadas (de um projeto ou de todos os projetos visíveis) e propõe um plano de recuperação.')]
class OverdueTasksPrompt extends WorkspacePrompt
{
    public function arguments(): array
    {
        return [new Argument('project_id', 'ID do projeto (opcional; vazio = todos os visíveis).')];
    }

    protected function compose(Request $request, User $user): string
    {
        $project = filled($request->get('project_id'))
            ? $this->authorizedProject($user, $request->get('project_id'))
            : null;

        $overdue = app(WorkspaceSnapshots::class)->overdue($user, $project);
        $scope = $project ? "do projeto \"{$project->name}\"" : 'de todos os projetos que você pode ver';

        return <<<TXT
Liste as tarefas atrasadas {$scope}, agrupadas por responsável, indicando quantos dias de atraso cada uma tem (hoje é {$this->today()}).
Em seguida, proponha um plano de recuperação: o que priorizar, o que renegociar e quem acionar.
Não altere prazos nem responsáveis sem confirmação explícita do usuário.

Tarefas atrasadas (JSON, até 100):
{$this->json(['overdue_tasks' => $overdue])}
TXT;
    }

    private function today(): string
    {
        return today()->toDateString();
    }
}
