<?php

namespace App\Modules\Workspace\Services;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Services\UserService;
use App\Modules\Workspace\Enums\ClientStatus;
use App\Modules\Workspace\Enums\Visibility;
use App\Modules\Workspace\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientService
{
    private const FIELDS = ['name', 'email', 'phone', 'document', 'notes', 'status'];

    public function __construct(
        private readonly UserService $users,
        private readonly ActivityService $activity,
    ) {}

    /**
     * @param  array{search?: ?string, status?: ?string}  $filters
     */
    public function paginate(User $actor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;

        return Client::query()
            ->visibleTo($actor)
            ->withCount(['projects', 'users'])
            ->when(filled($filters['status'] ?? null), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(filled($search), function (Builder $query) use ($search): void {
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function load(Client $client): Client
    {
        return $client->load(['users' => fn ($q) => $q->orderBy('name')])->loadCount(['projects', 'users']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Client
    {
        $client = new Client(Arr::only($data, self::FIELDS));
        $client->status ??= ClientStatus::ACTIVE;
        $client->save();

        $this->activity->record('client.created', $client, Visibility::INTERNAL);

        return $this->load($client);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Client $client, array $data): Client
    {
        $client->fill(Arr::only($data, self::FIELDS));
        $changes = $this->activity->diff($client, self::FIELDS);
        $client->save();

        if ($changes !== []) {
            $this->activity->record('client.updated', $client, Visibility::INTERNAL, changes: $changes);
        }

        return $this->load($client);
    }

    /**
     * @throws ValidationException
     */
    public function delete(Client $client): void
    {
        if ($client->projects()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'client' => ['Não é possível remover um cliente que possui projetos. Inative-o.'],
            ]);
        }

        DB::transaction(function () use ($client): void {
            $client->users()->get()->each(fn (User $user) => $this->users->delete($user));
            $client->delete();
            $this->activity->record('client.deleted', $client, Visibility::INTERNAL);
        });
    }

    /**
     * Cria um usuário de acesso do cliente com o papel padrão "Cliente".
     * O vínculo (client_id) é definido aqui e nunca a partir do payload.
     *
     * @param  array{name: string, email: string, password: string, phone?: ?string}  $data
     */
    public function createUser(Client $client, array $data): User
    {
        return DB::transaction(function () use ($client, $data): User {
            $role = Role::query()->where('name', DefaultRole::CLIENT->value)->firstOrFail();

            $user = $this->users->create([
                ...Arr::only($data, ['name', 'email', 'password', 'phone']),
                'role_ids' => [$role->getKey()],
            ]);

            $user->forceFill(['client_id' => $client->getKey()])->save();

            $this->activity->record('client.user_created', $client, Visibility::INTERNAL, changes: [
                'user' => ['from' => null, 'to' => $user->email],
            ]);

            return $user->refresh();
        });
    }

    /**
     * Remover o acesso de um usuário do cliente exclui o usuário: desvincular
     * sem excluir o transformaria em profissional (escalonamento de acesso).
     *
     * @throws ValidationException
     */
    public function removeUser(Client $client, User $user): void
    {
        if ((int) $user->client_id !== (int) $client->getKey()) {
            throw ValidationException::withMessages(['user' => ['Usuário não pertence a este cliente.']]);
        }

        $this->users->delete($user);

        $this->activity->record('client.user_removed', $client, Visibility::INTERNAL, changes: [
            'user' => ['from' => $user->email, 'to' => null],
        ]);
    }
}
