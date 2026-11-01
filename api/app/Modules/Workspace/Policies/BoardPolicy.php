<?php

namespace App\Modules\Workspace\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Policies\Concerns\AuthorizesWorkspace;

/**
 * O quadro é único por tenant: as regras não dependem de uma instância.
 * As tarefas exibidas nele continuam filtradas por Task::visibleTo().
 */
class BoardPolicy
{
    use AuthorizesWorkspace;

    public function view(User $user): bool
    {
        return $user->hasPermission(Permission::BOARD_READ);
    }

    public function manage(User $user): bool
    {
        return $this->professional($user, Permission::BOARD_MANAGE);
    }
}
