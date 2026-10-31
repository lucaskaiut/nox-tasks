<?php

namespace App\Modules\Workspace\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Models\Client;
use App\Modules\Workspace\Policies\Concerns\AuthorizesWorkspace;
use Illuminate\Auth\Access\Response;

/**
 * Gestão de clientes é exclusiva de profissionais.
 */
class ClientPolicy
{
    use AuthorizesWorkspace;

    public function viewAny(User $user): bool
    {
        return $this->professional($user, Permission::CLIENT_READ);
    }

    public function view(User $user, Client $client): Response
    {
        return $this->readable($this->inTenant($client) && $this->professional($user, Permission::CLIENT_READ));
    }

    public function create(User $user): bool
    {
        return $this->professional($user, Permission::CLIENT_CREATE);
    }

    public function update(User $user, Client $client): Response
    {
        return $this->act($this->visible($user, $client), $this->professional($user, Permission::CLIENT_UPDATE));
    }

    public function delete(User $user, Client $client): Response
    {
        return $this->act($this->visible($user, $client), $this->professional($user, Permission::CLIENT_DELETE));
    }

    public function manageUsers(User $user, Client $client): Response
    {
        return $this->act($this->visible($user, $client), $this->professional($user, Permission::CLIENT_MANAGE_USERS));
    }

    private function visible(User $user, Client $client): bool
    {
        return $this->inTenant($client) && ! $user->isClientUser();
    }
}
