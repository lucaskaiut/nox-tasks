<?php

namespace App\Modules\Mcp\Prompts;

use App\Modules\Mcp\Support\WorkspacePrompt;
use App\Modules\Mcp\Support\WorkspaceSnapshots;
use App\Modules\User\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('project_progress_summary')]
#[Description('Gera um resumo de andamento de um projeto, para a equipe interna ou para o cliente.')]
class ProjectProgressPrompt extends WorkspacePrompt
{
    public function arguments(): array
    {
        return [
            new Argument('project_id', 'ID do projeto.', required: true),
            new Argument('audience', 'internal (padrão) ou client.'),
        ];
    }

    protected function compose(Request $request, User $user): string
    {
        $project = $this->authorizedProject($user, $request->get('project_id'));
        $snapshots = app(WorkspaceSnapshots::class);
        $snapshot = $snapshots->project($user, $project);

        // Usuários de cliente já só recebem dados compartilhados; o público é sempre o cliente.
        $audience = $user->isClientUser() || $request->get('audience') === 'client' ? 'client' : 'internal';

        if ($audience === 'client') {
            $snapshot = $snapshots->withoutInternal($snapshot);
        }

        $guidance = $audience === 'client'
            ? 'O texto será enviado ao cliente: tom cordial e objetivo, sem mencionar notas internas, custos, margens ou tarefas internas.'
            : 'O texto é para a equipe interna: seja direto, destaque riscos, bloqueios e responsáveis.';

        return <<<TXT
Escreva um resumo de andamento do projeto "{$project->name}" em português do Brasil.
{$guidance}
Estrutura: progresso geral (percentual e marcos), o que foi concluído recentemente, o que está em andamento, próximos prazos e pontos de atenção.

Dados (JSON):
{$this->json($snapshot)}
TXT;
    }
}
