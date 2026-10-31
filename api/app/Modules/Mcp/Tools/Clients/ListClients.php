<?php

namespace App\Modules\Mcp\Tools\Clients;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\ClientResource;
use App\Modules\Workspace\Models\Client;
use App\Modules\Workspace\Services\ClientService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_clients')]
#[Description('Lista clientes (somente profissionais). Filtros: search (nome, e-mail, documento), status (active|inactive). Paginado.')]
#[IsReadOnly]
class ListClients extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->max(100)->description('Busca por nome, e-mail ou documento.'),
            'status' => $schema->string()->enum(['active', 'inactive']),
            ...$this->paginationSchema($schema),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::CLIENT_READ);
    }

    protected function run(Request $request, User $user): array
    {
        $this->authorizeFor($user, 'viewAny', Client::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $this->usePage($filters['page'] ?? 1);

        return $this->page(
            app(ClientService::class)->paginate($user, $filters, $this->perPage($filters['per_page'] ?? null)),
            ClientResource::class,
        );
    }
}
