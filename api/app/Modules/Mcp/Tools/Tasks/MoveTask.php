<?php

namespace App\Modules\Mcp\Tools\Tasks;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\TaskResource;
use App\Modules\Workspace\Services\TaskService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('move_task')]
#[Description('Move uma tarefa para outra coluna do quadro geral (altera o status) e/ou posição. Colunas do tipo "done" marcam a tarefa como concluída. Use get_board para ver as colunas.')]
class MoveTask extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->format('uuid')->required(),
            'column_id' => $schema->string()->format('uuid')->required(),
            'position' => $schema->integer()->min(0)->description('Posição na coluna do quadro geral, que reúne todos os projetos (0 = topo). Padrão: final.'),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::TASK_MOVE);
    }

    protected function run(Request $request, User $user): array
    {
        $task = $this->authorizedTask($user, $request->get('task_id'), 'move');

        $data = $request->validate(WorkspaceRules::taskMove());

        return $this->item(TaskResource::class, app(TaskService::class)->move($user, $task, $data['column_id'], $data['position'] ?? null));
    }
}
