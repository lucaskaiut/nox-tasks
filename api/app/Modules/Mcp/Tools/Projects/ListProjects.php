<?php

namespace App\Modules\Mcp\Tools\Projects;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\ProjectResource;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Services\ProjectService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_projects')]
#[Description('Lista projetos visíveis ao usuário, com progresso. Filtros: search, client (id), status (planning|active|on_hold|completed|cancelled), member (id do profissional), archived (exclude|include|only). Paginado.')]
#[IsReadOnly]
class ListProjects extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->max(100),
            'client' => $schema->string()->format('uuid')->description('ID do cliente.'),
            'status' => $schema->string()->enum(['planning', 'active', 'on_hold', 'completed', 'cancelled']),
            'member' => $schema->string()->format('uuid')->description('ID de um profissional participante.'),
            'archived' => $schema->string()->enum(['exclude', 'include', 'only'])->description('Padrão: exclude.'),
            ...$this->paginationSchema($schema),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return $user->hasPermission(Permission::PROJECT_READ);
    }

    protected function run(Request $request, User $user): array
    {
        $this->authorizeFor($user, 'viewAny', Project::class);

        $filters = $request->validate([...WorkspaceRules::projectFilters(), 'per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $this->usePage($filters['page'] ?? 1);

        return $this->page(
            app(ProjectService::class)->paginate($user, $filters, $this->perPage($filters['per_page'] ?? null)),
            ProjectResource::class,
        );
    }
}
