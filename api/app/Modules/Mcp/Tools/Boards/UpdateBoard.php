<?php

namespace App\Modules\Mcp\Tools\Boards;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\BoardResource;
use App\Modules\Workspace\Services\BoardService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_board')]
#[Description('Altera nome e/ou descrição de um quadro. Para colunas, use as ferramentas de coluna.')]
#[IsIdempotent]
class UpdateBoard extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->format('uuid')->required(),
            'name' => $schema->string()->max(120),
            'description' => $schema->string()->max(2000)->nullable(),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::BOARD_MANAGE);
    }

    protected function run(Request $request, User $user): array
    {
        $board = $this->authorizedBoard($user, $request->get('board_id'), 'manage');

        return $this->item(BoardResource::class, app(BoardService::class)->update($board, $request->validate(WorkspaceRules::board(partial: true))));
    }
}
