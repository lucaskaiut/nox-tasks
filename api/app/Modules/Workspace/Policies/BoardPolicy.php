<?php

namespace App\Modules\Workspace\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Models\Board;
use App\Modules\Workspace\Policies\Concerns\AuthorizesWorkspace;
use Illuminate\Auth\Access\Response;

class BoardPolicy
{
    use AuthorizesWorkspace;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::BOARD_READ);
    }

    public function view(User $user, Board $board): Response
    {
        return $this->readable($this->visible($user, $board));
    }

    public function create(User $user): bool
    {
        return $this->professional($user, Permission::BOARD_MANAGE);
    }

    public function manage(User $user, Board $board): Response
    {
        return $this->act($this->visible($user, $board), $this->professional($user, Permission::BOARD_MANAGE));
    }

    public function visible(User $user, Board $board): bool
    {
        if (! $this->inTenant($board) || ! $user->hasPermission(Permission::BOARD_READ)) {
            return false;
        }

        return ! $user->isClientUser()
            || $board->projects()->where('client_id', $user->client_id)->exists();
    }
}
