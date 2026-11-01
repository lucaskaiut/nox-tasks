<?php

namespace App\Modules\Mcp\Tools\Boards;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\BoardColumnResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_board_columns')]
#[Description('Lista as colunas do quadro geral, em ordem. kind indica o significado: open, waiting (aguardando retorno), done (concluída), cancelled.')]
#[IsReadOnly]
class ListBoardColumns extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function availableTo(User $user): bool
    {
        return $user->hasPermission(Permission::BOARD_READ);
    }

    protected function run(Request $request, User $user): array
    {
        $board = $this->authorizedBoard($user);

        return ['data' => $this->collection(BoardColumnResource::class, $board->columns()->get())];
    }
}
