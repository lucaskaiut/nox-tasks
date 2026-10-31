<?php

namespace App\Modules\Mcp\Tools\Clients;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\ClientResource;
use App\Modules\Workspace\Models\Client;
use App\Modules\Workspace\Services\ClientService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_client')]
#[Description('Cadastra um cliente (somente profissionais com permissão client.create).')]
class CreateClient extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(255)->required()->description('Nome ou razão social.'),
            'email' => $schema->string()->format('email')->required(),
            'phone' => $schema->string()->max(20),
            'document' => $schema->string()->description('CPF ou CNPJ (opcional).'),
            'notes' => $schema->string()->max(5000)->description('Observações internas.'),
            'status' => $schema->string()->enum(['active', 'inactive']),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::CLIENT_CREATE);
    }

    protected function run(Request $request, User $user): array
    {
        $this->authorizeFor($user, 'create', Client::class);

        $request->merge(WorkspaceRules::normalizeDocument($request->all()));
        $data = $request->validate(WorkspaceRules::client());

        return $this->item(ClientResource::class, app(ClientService::class)->create($data));
    }
}
