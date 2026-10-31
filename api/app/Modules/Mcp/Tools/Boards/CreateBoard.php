<?php

namespace App\Modules\Mcp\Tools\Boards;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\BoardResource;
use App\Modules\Workspace\Models\Board;
use App\Modules\Workspace\Services\BoardService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_board')]
#[Description('Cria um quadro Kanban. columns opcional: lista de {name, color (#rrggbb), kind (open|waiting|done|cancelled)}; sem colunas, usa um conjunto padrão.')]
class CreateBoard extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(120)->required(),
            'description' => $schema->string()->max(2000),
            'columns' => $schema->array()->max(20)->items($schema->object([
                'name' => $schema->string()->max(60)->required(),
                'color' => $schema->string()->pattern('^#[0-9a-fA-F]{6}$'),
                'kind' => $schema->string()->enum(['open', 'waiting', 'done', 'cancelled']),
            ])),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::BOARD_MANAGE);
    }

    protected function run(Request $request, User $user): array
    {
        $this->authorizeFor($user, 'create', Board::class);

        return $this->item(BoardResource::class, app(BoardService::class)->create($request->validate(WorkspaceRules::board())));
    }
}
