<?php

namespace App\Modules\Workspace\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Policies\Concerns\AuthorizesWorkspace;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    use AuthorizesWorkspace;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PROJECT_READ);
    }

    public function view(User $user, Project $project): Response
    {
        return $this->readable($this->visible($user, $project));
    }

    public function create(User $user): bool
    {
        return $this->professional($user, Permission::PROJECT_CREATE);
    }

    public function update(User $user, Project $project): Response
    {
        return $this->act($this->visible($user, $project), $this->professional($user, Permission::PROJECT_UPDATE));
    }

    public function archive(User $user, Project $project): Response
    {
        return $this->act($this->visible($user, $project), $this->professional($user, Permission::PROJECT_ARCHIVE));
    }

    /**
     * Visível = mesmo tenant + permissão de leitura + (profissional ou projeto do próprio cliente).
     */
    public function visible(User $user, Project $project): bool
    {
        if (! $this->inTenant($project) || ! $user->hasPermission(Permission::PROJECT_READ)) {
            return false;
        }

        return ! $user->isClientUser() || (int) $project->client_id === (int) $user->client_id;
    }
}
