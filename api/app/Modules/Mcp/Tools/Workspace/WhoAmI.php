<?php

namespace App\Modules\Mcp\Tools\Workspace;

use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('whoami')]
#[Description('Identifica o usuário autenticado na conexão: perfil (professional|client), organização, cliente vinculado e permissões. Use no início para entender o que pode ser feito.')]
#[IsReadOnly]
class WhoAmI extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function run(Request $request, User $user): array
    {
        $user->loadMissing('client');

        return [
            'id' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'profile' => $user->isClientUser() ? 'client' : 'professional',
            'organization' => TenantContext::tenant()?->name,
            'client' => $user->client ? ['id' => $user->client->uuid, 'name' => $user->client->name] : null,
            'permissions' => $user->permissionValues()->values()->all(),
        ];
    }
}
