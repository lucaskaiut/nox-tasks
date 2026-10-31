<?php

namespace App\Modules\Mcp\Tools\Clients;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\ClientResource;
use App\Modules\Workspace\Services\ClientService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_client')]
#[Description('Atualiza dados de um cliente. Envie apenas os campos a alterar. O vínculo de usuários com clientes não é alterável por aqui.')]
#[IsIdempotent]
class UpdateClient extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->string()->format('uuid')->required(),
            'name' => $schema->string()->max(255),
            'email' => $schema->string()->format('email'),
            'phone' => $schema->string()->max(20)->nullable(),
            'document' => $schema->string()->nullable(),
            'notes' => $schema->string()->max(5000)->nullable(),
            'status' => $schema->string()->enum(['active', 'inactive']),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::CLIENT_UPDATE);
    }

    protected function run(Request $request, User $user): array
    {
        $client = $this->authorizedClient($user, $request->get('client_id'), 'update');

        $data = WorkspaceRules::normalizeDocument($request->all());
        $request->merge($data);
        $data = $request->validate(WorkspaceRules::client(partial: true));

        return $this->item(ClientResource::class, app(ClientService::class)->update($client, $data));
    }
}
