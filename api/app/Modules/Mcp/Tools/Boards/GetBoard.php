<?php

namespace App\Modules\Mcp\Tools\Boards;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceSnapshots;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_board')]
#[Description('Retorna o quadro Kanban geral (único, compartilhado por todos os projetos): colunas em ordem com as tarefas de cada uma. Informe project_id para ver apenas as tarefas de um projeto.')]
#[IsReadOnly]
class GetBoard extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->format('uuid'),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return $user->hasPermission(Permission::BOARD_READ);
    }

    protected function run(Request $request, User $user): array
    {
        $data = $request->validate(['project_id' => ['nullable', 'uuid']]);
        $board = $this->authorizedBoard($user);

        $project = filled($data['project_id'] ?? null) ? $this->authorizedProject($user, $data['project_id']) : null;

        return app(WorkspaceSnapshots::class)->kanban($user, $board, $project);
    }
}
