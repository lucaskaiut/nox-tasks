<?php

namespace App\Modules\Mcp\Tools\Boards;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Services\BoardService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_board_column')]
#[Description('Exclui uma coluna. Se houver tarefas nela, move_tasks_to (outra coluna do mesmo quadro) é obrigatório. Ação destrutiva: confirme com o usuário.')]
#[IsDestructive]
class DeleteBoardColumn extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->format('uuid')->required(),
            'column_id' => $schema->string()->format('uuid')->required(),
            'move_tasks_to' => $schema->string()->format('uuid'),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::BOARD_MANAGE);
    }

    protected function run(Request $request, User $user): array
    {
        $board = $this->authorizedBoard($user, $request->get('board_id'), 'manage');
        $column = $board->columns()->where('uuid', (string) $request->get('column_id'))->firstOrFail();
        $data = $request->validate(['move_tasks_to' => ['nullable', 'uuid']]);

        $target = filled($data['move_tasks_to'] ?? null)
            ? $board->columns()->where('uuid', $data['move_tasks_to'])->first()
            : null;

        app(BoardService::class)->deleteColumn($column, $target);

        return ['deleted' => true, 'column_id' => $column->uuid];
    }
}
