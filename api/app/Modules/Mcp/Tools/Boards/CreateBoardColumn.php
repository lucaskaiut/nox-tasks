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

#[Name('create_board_column')]
#[Description('Adiciona uma coluna a um quadro. kind: open|waiting|done|cancelled. position opcional (0 = primeira).')]
class CreateBoardColumn extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->format('uuid')->required(),
            'name' => $schema->string()->max(60)->required(),
            'color' => $schema->string()->pattern('^#[0-9a-fA-F]{6}$'),
            'kind' => $schema->string()->enum(['open', 'waiting', 'done', 'cancelled']),
            'position' => $schema->integer()->min(0)->max(100),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::BOARD_MANAGE);
    }

    protected function run(Request $request, User $user): array
    {
        $board = $this->authorizedBoard($user, $request->get('board_id'), 'manage');

        return $this->item(BoardColumnResource::class, app(BoardService::class)->addColumn($board, $request->validate(WorkspaceRules::column())));
    }
}
