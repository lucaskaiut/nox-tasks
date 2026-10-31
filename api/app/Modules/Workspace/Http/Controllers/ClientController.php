<?php

namespace App\Modules\Workspace\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Requests\StoreClientRequest;
use App\Modules\Workspace\Http\Requests\StoreClientUserRequest;
use App\Modules\Workspace\Http\Requests\UpdateClientRequest;
use App\Modules\Workspace\Http\Resources\ClientResource;
use App\Modules\Workspace\Http\Resources\MemberResource;
use App\Modules\Workspace\Models\Client;
use App\Modules\Workspace\Services\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends ApiController
{
    public function __construct(private readonly ClientService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->paginated(ClientResource::collection(
            $this->service->paginate($request->user(), $filters, (int) ($filters['per_page'] ?? 15)),
        ));
    }

    public function show(Client $client): JsonResponse
    {
        return $this->success(ClientResource::make($this->service->load($client)));
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        return $this->created(ClientResource::make($this->service->create($request->validated())), 'Cliente criado.');
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        return $this->success(ClientResource::make($this->service->update($client, $request->validated())), 'Cliente atualizado.');
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->service->delete($client);

        return $this->success(null, 'Cliente removido.');
    }

    public function storeUser(StoreClientUserRequest $request, Client $client): JsonResponse
    {
        $user = $this->service->createUser($client, $request->validated());

        return $this->created(MemberResource::make($user), 'Acesso do cliente criado.');
    }

    public function destroyUser(Client $client, User $user): JsonResponse
    {
        $this->service->removeUser($client, $user);

        return $this->success(null, 'Acesso do cliente removido.');
    }
}
