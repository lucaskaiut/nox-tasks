<?php

namespace App\Modules\Mcp\Tools\Workspace;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\MemberResource;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Services\MemberService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_members')]
#[Description('Lista os profissionais que podem ser responsáveis ou participantes de projetos e tarefas (IDs para assign_task, create_task e member_ids).')]
#[IsReadOnly]
class ListMembers extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['search' => $schema->string()->max(100), ...$this->paginationSchema($schema)];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::TASK_ASSIGN);
    }

    protected function run(Request $request, User $user): array
    {
        $this->authorizeFor($user, 'assignOnCreate', Task::class);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $this->usePage($data['page'] ?? 1);

        return $this->page(app(MemberService::class)->paginate($this->perPage($data['per_page'] ?? null, 50), $data['search'] ?? null), MemberResource::class);
    }
}
