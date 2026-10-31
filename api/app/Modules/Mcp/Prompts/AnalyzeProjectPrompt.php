<?php

namespace App\Modules\Mcp\Prompts;

use App\Modules\Mcp\Support\WorkspacePrompt;
use App\Modules\Mcp\Support\WorkspaceSnapshots;
use App\Modules\User\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('analyze_project')]
#[Description('Analisa a situação de um projeto: riscos, gargalos, prazos e próximos passos.')]
class AnalyzeProjectPrompt extends WorkspacePrompt
{
    public function arguments(): array
    {
        return [new Argument('project_id', 'ID do projeto a analisar.', required: true)];
    }

    protected function compose(Request $request, User $user): string
    {
        $project = $this->authorizedProject($user, $request->get('project_id'));
        $snapshot = app(WorkspaceSnapshots::class)->project($user, $project);

        return <<<TXT
Analise a situação do projeto abaixo e responda em português do Brasil com:
1. Visão geral (status, progresso percentual, prazo do projeto).
2. Riscos e gargalos (tarefas atrasadas, urgentes sem responsável, concentração de trabalho, itens aguardando retorno).
3. Próximos passos recomendados, em ordem de prioridade.
Baseie-se apenas nos dados fornecidos; use as ferramentas (ex.: get_task) se precisar de detalhes. Não altere nada sem confirmação do usuário.

Dados do projeto (JSON):
{$this->json($snapshot)}
TXT;
    }
}
