<?php

namespace App\Modules\Mcp\Tools\Boards;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\BoardColumnResource;
use App\Modules\Workspace\Services\BoardService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('move_board_column')]
#[Description('Reordena uma coluna do quadro geral (0 = primeira posição).')]
class MoveBoardColumn extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'column_id' => $schema->string()->format('uuid')->required(),
            'position' => $schema->integer()->min(0)->max(100)->required(),
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
        $data = $request->validate(['position' => ['required', 'integer', 'min:0', 'max:100']]);

        return $this->item(BoardColumnResource::class, app(BoardService::class)->moveColumn($column, (int) $data['position']));
    }
}
