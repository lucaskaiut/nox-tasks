<?php

namespace App\Modules\Mcp\Tools\Clients;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\ClientResource;
use App\Modules\Workspace\Services\ClientService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_client')]
#[Description('Detalhes de um cliente, com usuários de acesso e contagem de projetos (somente profissionais).')]
#[IsReadOnly]
class GetClient extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['client_id' => $schema->string()->format('uuid')->required()->description('ID do cliente.')];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::CLIENT_READ);
    }

    protected function run(Request $request, User $user): array
    {
        $client = $this->authorizedClient($user, $request->get('client_id'));

        return $this->item(ClientResource::class, app(ClientService::class)->load($client));
    }
}
