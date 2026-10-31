<?php

namespace App\Modules\Mcp\Tools\Boards;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceSnapshots;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\BoardResource;
use App\Modules\Workspace\Services\BoardService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_board')]
#[Description('Retorna um quadro e suas colunas. Informe project_id para obter o Kanban do projeto com as tarefas agrupadas por coluna, ou board_id para apenas a configuração do quadro.')]
#[IsReadOnly]
class GetBoard extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->format('uuid'),
            'project_id' => $schema->string()->format('uuid'),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return $user->hasPermission(Permission::BOARD_READ);
    }

    protected function run(Request $request, User $user): array
    {
        $data = $request->validate(['board_id' => ['nullable', 'uuid'], 'project_id' => ['nullable', 'uuid']]);

        if (filled($data['project_id'] ?? null)) {
            return app(WorkspaceSnapshots::class)->kanban($user, $this->authorizedProject($user, $data['project_id']));
        }

        if (! filled($data['board_id'] ?? null)) {
            throw ValidationException::withMessages(['board_id' => ['Informe board_id ou project_id.']]);
        }

        return $this->item(BoardResource::class, app(BoardService::class)->load($this->authorizedBoard($user, $data['board_id'])));
    }
}
