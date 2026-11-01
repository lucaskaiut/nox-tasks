<?php

namespace App\Modules\Mcp\Tools\Boards;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\BoardColumnResource;
use App\Modules\Workspace\Services\BoardService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_board_column')]
#[Description('Altera nome, cor ou tipo (kind) de uma coluna. Mudar kind para/de "done" ajusta a conclusão das tarefas da coluna.')]
class UpdateBoardColumn extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'column_id' => $schema->string()->format('uuid')->required(),
            'name' => $schema->string()->max(60),
            'color' => $schema->string()->pattern('^#[0-9a-fA-F]{6}$'),
            'kind' => $schema->string()->enum(['open', 'waiting', 'done', 'cancelled']),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::BOARD_MANAGE);
    }

    protected function run(Request $request, User $user): array
    {
        $board = $this->authorizedBoard($user, 'manage');
        $column = $board->columns()->where('uuid', (string) $request->get('column_id'))->firstOrFail();

        $data = $request->validate(WorkspaceRules::column(partial: true));
        unset($data['position']);

        return $this->item(BoardColumnResource::class, app(BoardService::class)->updateColumn($column, $data));
    }
}
