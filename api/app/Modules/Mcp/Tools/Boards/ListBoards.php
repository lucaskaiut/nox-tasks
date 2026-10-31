<?php

namespace App\Modules\Mcp\Tools\Boards;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\BoardResource;
use App\Modules\Workspace\Models\Board;
use App\Modules\Workspace\Services\BoardService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_boards')]
#[Description('Lista quadros Kanban visíveis ao usuário, com suas colunas. Paginado.')]
#[IsReadOnly]
class ListBoards extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['search' => $schema->string()->max(100), ...$this->paginationSchema($schema)];
    }

    protected function availableTo(User $user): bool
    {
        return $user->hasPermission(Permission::BOARD_READ);
    }

    protected function run(Request $request, User $user): array
    {
        $this->authorizeFor($user, 'viewAny', Board::class);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $this->usePage($data['page'] ?? 1);

        return $this->page(
            app(BoardService::class)->paginate($user, $data['search'] ?? null, $this->perPage($data['per_page'] ?? null)),
            BoardResource::class,
        );
    }
}
